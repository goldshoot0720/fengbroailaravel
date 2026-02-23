<?php
/**
 * WebPushHelper
 *
 * Pure-PHP implementation of the Web Push Protocol (RFC 8030 / RFC 8291).
 * No Composer or external libraries required.
 *
 * Requirements:
 *   - PHP 8.1+  (openssl_pkey_derive for ECDH)
 *   - ext-openssl
 *   - ext-curl
 *
 * High-level flow:
 *   1. Generate a VAPID key pair once and store in the `settings` table.
 *   2. The browser subscribes via the Push API and sends its endpoint + keys
 *      to push_subscribe.php, which stores them in `push_subscriptions`.
 *   3. When a notification must be sent, call sendNotification().
 *      Internally it:
 *        a. Encrypts the JSON payload with RFC 8291 aes128gcm.
 *        b. Signs a VAPID JWT with ES256.
 *        c. POSTs the encrypted record to the browser's push endpoint via cURL.
 *   4. The browser's push service (FCM / Mozilla) delivers the message to
 *      sw.js even when the PWA is closed.
 */
class WebPushHelper
{
    /** VAPID "sub" claim – identifies the application server to push services. */
    const VAPID_SUB = 'mailto:admin@localhost';

    // =========================================================================
    // Base64Url helpers
    // =========================================================================
    // The Web Push spec uses Base64url encoding (RFC 4648 §5) throughout:
    // VAPID keys, JWT segments, and subscription key material all arrive and
    // leave in this alphabet ('+' → '-', '/' → '_', no padding '=').

    public static function base64UrlEncode(string $data): string
    {
        // base64_encode → swap alphabet → strip padding
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        // Restore missing padding before decoding
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // =========================================================================
    // VAPID key generation
    // =========================================================================

    /**
     * Generate a fresh VAPID key pair on the P-256 (prime256v1) elliptic curve.
     *
     * The public key is returned as a Base64url-encoded 65-byte uncompressed
     * EC point (0x04 || x || y) – the format required by the browser's
     * pushManager.subscribe({ applicationServerKey }) call.
     *
     * The private key is returned as a PEM string for storage in the DB and
     * later use with openssl_sign().
     *
     * @return array{publicKey: string, privateKey: string}
     */
    public static function generateVapidKeys(): array
    {
        // Generate an EC key pair on the P-256 curve
        $key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (!$key) {
            throw new RuntimeException('Failed to generate EC key pair: ' . openssl_error_string());
        }

        // Extract the raw x and y coordinates of the public key point.
        // OpenSSL may return them shorter than 32 bytes if leading bits are 0,
        // so we left-pad each coordinate to exactly 32 bytes.
        $details = openssl_pkey_get_details($key);
        $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT); // 32 bytes
        $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT); // 32 bytes

        // Uncompressed EC point format: 0x04 || x(32) || y(32) = 65 bytes
        $publicKeyRaw = "\x04" . $x . $y;

        // Export the full private key as PEM (includes the curve parameters)
        openssl_pkey_export($key, $privateKeyPem);

        return [
            'publicKey'  => self::base64UrlEncode($publicKeyRaw), // for browser
            'privateKey' => $privateKeyPem,                        // for DB storage
        ];
    }

    // =========================================================================
    // VAPID JWT (ES256)
    // =========================================================================

    /**
     * Create a signed VAPID JWT for authenticating the push request.
     *
     * Structure: base64url(header) . '.' . base64url(payload) . '.' . base64url(sig)
     *
     * The JWT is sent in the Authorization header:
     *   Authorization: vapid t=<jwt>,k=<publicKey>
     *
     * Each JWT is valid for 12 hours. A new one is created per sendNotification()
     * call so no caching logic is needed here.
     *
     * @param string $endpoint      The subscriber's push endpoint URL.
     *                              The "aud" claim must be its origin only.
     * @param string $privateKeyPem EC private key PEM (from generateVapidKeys).
     * @return string Signed JWT string.
     */
    public static function createVapidJwt(string $endpoint, string $privateKeyPem): string
    {
        // "aud" = origin of the push endpoint (scheme + host [+ port])
        $urlParts = parse_url($endpoint);
        $audience = $urlParts['scheme'] . '://' . $urlParts['host'];
        if (isset($urlParts['port'])) {
            $audience .= ':' . $urlParts['port'];
        }

        // JWT header: algorithm ES256 = ECDSA with SHA-256 on P-256
        $header = self::base64UrlEncode(
            json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES)
        );

        // JWT payload: audience, expiry (12 h), and subscriber contact
        $payload = self::base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200, // Unix timestamp 12 hours from now
            'sub' => self::VAPID_SUB,
        ], JSON_UNESCAPED_SLASHES));

        // The data to sign is "header.payload" (both already Base64url-encoded)
        $signingInput = $header . '.' . $payload;

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            throw new RuntimeException('Invalid VAPID private key');
        }

        // openssl_sign with ALGO_SHA256 on an EC key produces an ES256 signature
        // in DER/ASN.1 format. JWT requires the raw r||s (64-byte) format instead.
        if (!openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('JWT signing failed: ' . openssl_error_string());
        }

        $rawSig = self::derToRawSignature($derSignature);
        return $signingInput . '.' . self::base64UrlEncode($rawSig);
    }

    /**
     * Convert an ASN.1 DER-encoded ECDSA signature to the raw r||s form.
     *
     * DER layout for ECDSA:
     *   30 <seq_len>
     *     02 <r_len> <r_bytes>   ← INTEGER r
     *     02 <s_len> <s_bytes>   ← INTEGER s
     *
     * DER integers are signed, so a leading 0x00 byte is added when the high
     * bit of r or s is set. JWT/JWA need exactly 32 bytes each with no padding.
     *
     * @param string $der Binary DER signature from openssl_sign().
     * @return string 64-byte raw signature (r || s).
     */
    private static function derToRawSignature(string $der): string
    {
        $offset = 0;

        // Skip SEQUENCE tag (0x30) and its length byte
        $offset += 1; // tag
        $seqLen = ord($der[$offset]);
        $offset += 1; // length
        // Handle long-form length encoding (rare for ECDSA, but be safe)
        if ($seqLen & 0x80) {
            $offset += ($seqLen & 0x7f);
        }

        // Read r: skip INTEGER tag (0x02) + length, then read r_len bytes
        $offset += 1; // 0x02
        $rLen    = ord($der[$offset]);
        $offset += 1;
        $r       = substr($der, $offset, $rLen);
        $offset += $rLen;

        // Read s: same structure
        $offset += 1; // 0x02
        $sLen    = ord($der[$offset]);
        $offset += 1;
        $s       = substr($der, $offset, $sLen);

        // Trim the DER-added leading 0x00 (sign byte), then pad to 32 bytes
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        // Take only the last 32 bytes in case of unexpected over-length values
        return substr($r, -32) . substr($s, -32);
    }

    // =========================================================================
    // RFC 8291 payload encryption (aes128gcm)
    // =========================================================================

    /**
     * Encrypt a plaintext payload according to RFC 8291 §4 (aes128gcm).
     *
     * The encrypted record is a self-contained binary blob that the browser's
     * push service forwards untouched to the service worker. The structure is:
     *
     *   salt (16 B)
     *   + record size rs (4 B big-endian, = 4096)
     *   + sender public key length (1 B, = 0x41 = 65)
     *   + sender ECDH public key (65 B, uncompressed P-256 point)
     *   + AES-128-GCM ciphertext
     *   + AES-128-GCM authentication tag (16 B, appended by openssl_encrypt)
     *
     * Key derivation summary (HKDF with SHA-256):
     *   sharedSecret = ECDH(senderPriv, recipientPub)   // Diffie-Hellman
     *   PRK_key      = HMAC-SHA256(auth, sharedSecret)
     *   IKM          = HKDF-Expand(PRK_key, keyInfo, 32)
     *   PRK          = HMAC-SHA256(salt, IKM)
     *   CEK          = HKDF-Expand(PRK, "Content-Encoding: aes128gcm\x00", 16)
     *   Nonce        = HKDF-Expand(PRK, "Content-Encoding: nonce\x00", 12)
     *
     * @param array  $subscription Subscription row: {endpoint, keys:{auth, p256dh}}
     * @param string $payload      Plaintext JSON to encrypt.
     * @return string Binary encrypted record.
     */
    public static function encryptPayload(array $subscription, string $payload): string
    {
        // openssl_pkey_derive() for ECDH requires PHP 8.1+
        if (!function_exists('openssl_pkey_derive')) {
            throw new RuntimeException('PHP 8.1+ (openssl_pkey_derive) required for Web Push encryption');
        }

        // Decode the subscriber's key material from Base64url
        $auth   = self::base64UrlDecode($subscription['keys']['auth']);   // 16 bytes  – authentication secret
        $p256dh = self::base64UrlDecode($subscription['keys']['p256dh']); // 65 bytes  – subscriber's EC public key

        // Step 1: Random 16-byte salt (unique per message, never reused)
        $salt = random_bytes(16);

        // Step 2: Generate a fresh ephemeral ECDH key pair for this message only
        $senderKey = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (!$senderKey) {
            throw new RuntimeException('Failed to generate sender EC key');
        }

        // Build the sender's 65-byte uncompressed public key (0x04 || x || y)
        $senderDetails  = openssl_pkey_get_details($senderKey);
        $sx             = str_pad($senderDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $sy             = str_pad($senderDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $senderPublicKey = "\x04" . $sx . $sy; // 65 bytes, goes into the record header

        // Step 3: Wrap the subscriber's raw P-256 point in a PEM envelope so
        //         OpenSSL can parse it as a proper EC public key object
        $recipientKey = self::rawP256ToOpensslKey($p256dh);

        // Step 4: ECDH – derive the shared secret using the subscriber's public
        //         key and our ephemeral private key
        $sharedSecret = openssl_pkey_derive($recipientKey, $senderKey);
        if ($sharedSecret === false) {
            throw new RuntimeException('ECDH computation failed: ' . openssl_error_string());
        }

        // Step 5: HKDF key derivation (RFC 8291 §3.4)
        // ----- Pseudo-random key extraction -----
        // PRK_key mixes the ECDH output with the subscriber's auth secret so
        // that only the intended subscriber can decrypt the message.
        $prkKey = hash_hmac('sha256', $sharedSecret, $auth, true);

        // The key_info binds this derivation to the specific subscriber and
        // sender key pair, preventing cross-subscription key reuse.
        $keyInfo = "WebPush: info\x00" . $p256dh . $senderPublicKey;

        // IKM is the "input key material" passed into the second HKDF stage
        $ikm = self::hkdfExpand($prkKey, $keyInfo, 32);

        // ----- Second HKDF stage (salt-keyed) -----
        // The salt provides forward secrecy: even if the shared secret leaks,
        // past messages encrypted with different salts remain protected.
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // CEK: 16-byte AES-128 key (aes128gcm uses 128-bit keys)
        $cek = self::hkdfExpand($prk, "Content-Encoding: aes128gcm\x00", 16);

        // Nonce: 12-byte IV for AES-GCM (GCM's standard 96-bit nonce)
        $nonce = self::hkdfExpand($prk, "Content-Encoding: nonce\x00", 12);

        // Step 6: Append the RFC 8291 padding delimiter byte (0x02 = last record)
        // A full implementation would split payloads > (rs - 103) bytes into
        // multiple records; for typical JSON payloads one record is sufficient.
        $paddedPayload = $payload . "\x02";

        // Step 7: AES-128-GCM encryption
        // openssl_encrypt returns ciphertext; the 16-byte tag is written to $tag.
        $tag        = '';
        $ciphertext = openssl_encrypt(
            $paddedPayload,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,  // output parameter: GCM authentication tag
            '',    // no additional authenticated data (AAD)
            16     // tag length in bytes
        );
        if ($ciphertext === false) {
            throw new RuntimeException('AES-GCM encryption failed: ' . openssl_error_string());
        }
        // Concatenate ciphertext and tag so the receiver can verify integrity
        $ciphertext .= $tag;

        // Step 8: Build the RFC 8291 content-coding record
        // rs = record size (4096 = max bytes per record, including overhead)
        $rs = pack('N', 4096); // 4-byte big-endian unsigned int

        // 0x41 = 65 = length of the sender's uncompressed EC public key ("keyid")
        return $salt . $rs . "\x41" . $senderPublicKey . $ciphertext;
    }

    /**
     * Wrap a raw 65-byte uncompressed P-256 EC point in a DER + PEM envelope
     * so that OpenSSL's functions can accept it as a public key resource.
     *
     * The DER structure for a SubjectPublicKeyInfo (SPKI) on P-256 is fixed-
     * length and can be constructed by prepending a known 26-byte header to
     * the 65-byte uncompressed point.
     *
     * Hex of the prefix:
     *   30 59            SEQUENCE (89 bytes total)
     *     30 13          SEQUENCE (19 bytes – AlgorithmIdentifier)
     *       06 07 2a 86 48 ce 3d 02 01  OID id-ecPublicKey
     *       06 08 2a 86 48 ce 3d 03 01 07  OID prime256v1 (P-256)
     *     03 42          BIT STRING (66 bytes)
     *       00           0 unused bits
     *       04 <x><y>    uncompressed EC point (65 bytes)
     *
     * @param string $rawPoint 65-byte binary uncompressed EC point.
     * @return OpenSSLAsymmetricKey
     */
    private static function rawP256ToOpensslKey(string $rawPoint)
    {
        $derPrefix = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
                   . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";

        // DER = fixed header + raw point
        $der = $derPrefix . $rawPoint;

        // Wrap DER in PEM armour (Base64 with 64-char line wrapping)
        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        if (!$key) {
            throw new RuntimeException('Failed to parse p256dh public key: ' . openssl_error_string());
        }
        return $key;
    }

    // =========================================================================
    // HKDF-Expand (RFC 5869 §2.3)
    // =========================================================================

    /**
     * HKDF Expand step using HMAC-SHA-256.
     *
     * Produces `$length` bytes of keying material from the pseudo-random key
     * `$prk` and the context string `$info`. Uses iterative HMAC blocks:
     *   T(1) = HMAC(prk, "" || info || 0x01)
     *   T(2) = HMAC(prk, T(1) || info || 0x02)
     *   ...
     * Output = T(1) || T(2) || ... truncated to $length bytes.
     *
     * Note: Only Expand is needed here because the PRK values are computed
     * externally with hash_hmac() (which serves as the Extract step).
     *
     * @param string $prk    Pseudo-random key (32 bytes for SHA-256).
     * @param string $info   Context/application-specific label string.
     * @param int    $length Desired output length in bytes.
     * @return string Output keying material of exactly $length bytes.
     */
    private static function hkdfExpand(string $prk, string $info, int $length): string
    {
        $t   = '';   // previous block (empty for first iteration)
        $okm = '';   // accumulated output
        $i   = 0;    // block counter

        while (strlen($okm) < $length) {
            $i++;
            // Each block feeds the previous block, the info label, and the counter
            $t    = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
        }

        return substr($okm, 0, $length);
    }

    // =========================================================================
    // Send notification
    // =========================================================================

    /**
     * Encrypt and deliver a Web Push notification to a single subscriber.
     *
     * Internally:
     *   1. Builds a JSON payload from title/body/url.
     *   2. Encrypts it with encryptPayload() (RFC 8291 aes128gcm).
     *   3. Signs a VAPID JWT with createVapidJwt() (ES256).
     *   4. POSTs the encrypted binary to the subscriber's push endpoint
     *      with the required headers via cURL.
     *
     * Push service response codes:
     *   201/202  Success – message accepted for delivery.
     *   410 Gone – subscription has expired; caller should delete the row.
     *   4xx/5xx  Failure – see returned 'error' field.
     *
     * @param array  $subscription Row from push_subscriptions:
     *                             {endpoint, keys:{auth, p256dh}}
     *                             (pass the DB row directly after restructuring keys).
     * @param string $title        Notification title shown in the OS UI.
     * @param string $body         Notification body text.
     * @param string $url          URL to open when the notification is clicked.
     * @return array{success: bool, http_code: int, error: string|null}
     */
    public static function sendNotification(
        array  $subscription,
        string $title,
        string $body,
        string $url = 'index.php?page=dashboard'
    ): array {
        $endpoint = $subscription['endpoint'];

        // Build the JSON payload that sw.js will receive in the push event
        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => '/icon-192x192.png',
            'url'   => $url,
            'tag'   => 'fengxiong-subscription', // replaces any existing notification with this tag
        ], JSON_UNESCAPED_UNICODE);

        // Load VAPID keys from the settings table
        $publicKey     = self::getVapidPublicKey();
        $privateKeyPem = self::getVapidPrivateKey();

        if (!$publicKey || !$privateKeyPem) {
            return ['success' => false, 'http_code' => 0, 'error' => 'VAPID keys not configured'];
        }

        // Encrypt the payload (throws on failure)
        try {
            $encrypted = self::encryptPayload($subscription, $payload);
        } catch (Exception $e) {
            return ['success' => false, 'http_code' => 0, 'error' => 'Encryption failed: ' . $e->getMessage()];
        }

        // Create the VAPID JWT for this specific endpoint
        try {
            $jwt = self::createVapidJwt($endpoint, $privateKeyPem);
        } catch (Exception $e) {
            return ['success' => false, 'http_code' => 0, 'error' => 'JWT failed: ' . $e->getMessage()];
        }

        // Build HTTP headers per RFC 8030 §5 and the VAPID spec (RFC 8292)
        $headers = [
            'Content-Type: application/octet-stream',      // raw encrypted binary
            'Content-Encoding: aes128gcm',                 // tells the push service the encoding used
            'Authorization: vapid t=' . $jwt . ',k=' . $publicKey, // VAPID auth
            'TTL: 86400',     // max delivery delay: 24 h (push service may cache the message)
            'Urgency: normal', // delivery priority hint to the push service
        ];

        // POST the encrypted record to the push endpoint
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $encrypted, // raw binary body
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,       // always verify TLS (push endpoints are public services)
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // 200/201/202 all indicate the push service accepted the message
        $success = in_array($httpCode, [200, 201, 202]);
        return [
            'success'   => $success,
            'http_code' => $httpCode,
            // On failure, prefer the cURL transport error; fall back to response body
            'error'     => $success ? null : ($curlError ?: substr($response, 0, 200)),
        ];
    }

    // =========================================================================
    // Settings table accessors
    // =========================================================================

    /**
     * Read the VAPID public key (Base64url) from the `settings` table.
     * Returns an empty string if not yet initialized.
     */
    public static function getVapidPublicKey(): string
    {
        try {
            $pdo  = getConnection();
            $stmt = $pdo->query(
                "SELECT setting_value FROM settings
                 WHERE setting_key = 'vapid_public_key' AND user_id IS NULL
                 LIMIT 1"
            );
            return $stmt ? ($stmt->fetchColumn() ?: '') : '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Read the VAPID private key (PEM) from the `settings` table.
     * Returns an empty string if not yet initialized.
     */
    public static function getVapidPrivateKey(): string
    {
        try {
            $pdo  = getConnection();
            $stmt = $pdo->query(
                "SELECT setting_value FROM settings
                 WHERE setting_key = 'vapid_private_key' AND user_id IS NULL
                 LIMIT 1"
            );
            return $stmt ? ($stmt->fetchColumn() ?: '') : '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Upsert a global setting (user_id = NULL) in the `settings` table.
     *
     * The table has UNIQUE KEY (user_id, setting_key), but in MySQL NULL values
     * are not considered equal in unique indexes, so ON DUPLICATE KEY UPDATE
     * would not fire for NULL user_id rows. We use a SELECT-then-INSERT/UPDATE
     * pattern to avoid duplicate rows.
     *
     * @param string $key   Setting key (e.g. 'vapid_public_key').
     * @param string $value Setting value to store.
     */
    public static function saveSetting(string $key, string $value): void
    {
        $pdo  = getConnection();

        // Check whether a row already exists for this global key
        $stmt = $pdo->prepare(
            "SELECT id FROM settings WHERE setting_key = ? AND user_id IS NULL"
        );
        $stmt->execute([$key]);

        if ($stmt->fetch()) {
            // Row exists – update in place
            $pdo->prepare(
                "UPDATE settings SET setting_value = ?
                 WHERE setting_key = ? AND user_id IS NULL"
            )->execute([$value, $key]);
        } else {
            // No row yet – insert with NULL user_id (global scope)
            $pdo->prepare(
                "INSERT INTO settings (user_id, setting_key, setting_value)
                 VALUES (NULL, ?, ?)"
            )->execute([$key, $value]);
        }
    }
}
