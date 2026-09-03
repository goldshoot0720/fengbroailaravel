<?php
/**
 * 試用／首購與重灌：建表、欄位正規化、CSV 對應。
 * 對齊 fengbroaiappwrite 的 trialpurchase / reinstall。
 */

function fengbroTrialPurchaseCreateSql(): string
{
    return "CREATE TABLE IF NOT EXISTS trialpurchase (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            eventDate DATETIME NULL,
            firstPurchasePrice INT DEFAULT 0,
            regularPrice INT DEFAULT 0,
            account VARCHAR(200),
            note VARCHAR(3337),
            trialStatus VARCHAR(20) DEFAULT 'untried',
            purchaseStatus VARCHAR(30) DEFAULT 'not_purchased',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function fengbroReinstallCreateSql(): string
{
    return "CREATE TABLE IF NOT EXISTS reinstall (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            `system` VARCHAR(10) DEFAULT 'win',
            softwareType VARCHAR(20) DEFAULT 'free',
            licenseType VARCHAR(20) DEFAULT 'none',
            serial VARCHAR(500),
            viewPassword VARCHAR(100),
            subscriptionSoftware TINYINT(1) DEFAULT 0,
            subscriptionPeriod VARCHAR(20) DEFAULT '',
            subscriptionPrice INT DEFAULT 0,
            subscriptionCurrency VARCHAR(10) DEFAULT 'TWD',
            site VARCHAR(2000),
            note VARCHAR(3337),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function fengbroEnsureTableColumns(PDO $pdo, string $table, array $columns): void
{
    foreach ($columns as $sql) {
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$sql}");
        } catch (Throwable $e) {
            // 欄位已存在則略過
        }
    }
}

function fengbroEnsureTrialPurchaseTable(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getConnection();
    $pdo->exec(fengbroTrialPurchaseCreateSql());
    fengbroEnsureTableColumns($pdo, 'trialpurchase', [
        "eventDate DATETIME NULL",
        "firstPurchasePrice INT DEFAULT 0",
        "regularPrice INT DEFAULT 0",
        "account VARCHAR(200)",
        "note VARCHAR(3337)",
        "trialStatus VARCHAR(20) DEFAULT 'untried'",
        "purchaseStatus VARCHAR(30) DEFAULT 'not_purchased'",
    ]);
}

function fengbroEnsureReinstallTable(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getConnection();
    $pdo->exec(fengbroReinstallCreateSql());
    fengbroEnsureTableColumns($pdo, 'reinstall', [
        "`system` VARCHAR(10) DEFAULT 'win'",
        "softwareType VARCHAR(20) DEFAULT 'free'",
        "licenseType VARCHAR(20) DEFAULT 'none'",
        "serial VARCHAR(500)",
        "viewPassword VARCHAR(100)",
        "subscriptionSoftware TINYINT(1) DEFAULT 0",
        "subscriptionPeriod VARCHAR(20) DEFAULT ''",
        "subscriptionPrice INT DEFAULT 0",
        "subscriptionCurrency VARCHAR(10) DEFAULT 'TWD'",
        "site VARCHAR(2000)",
        "note VARCHAR(3337)",
    ]);
}

function fengbroMbCut(?string $value, int $max): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function fengbroNonNegativeInt($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    $clean = preg_replace('/[^\d\-]/u', '', (string) $value);
    if ($clean === '' || $clean === '-') {
        return 0;
    }
    return max(0, (int) $clean);
}

function fengbroOptionalDate($value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('/', '-', $value);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
        $date = DateTime::createFromFormat('Y-m-d', $m[1]);
        if ($date && $date->format('Y-m-d') === $m[1]) {
            return $m[1] . ' 00:00:00';
        }
    }
    return null;
}

function fengbroNormalizeTrialStatus($value): string
{
    $key = trim((string) $value);
    $map = [
        'untried' => 'untried',
        'tried' => 'tried',
        '尚未試用' => 'untried',
        '已試用' => 'tried',
    ];
    return $map[$key] ?? 'untried';
}

function fengbroNormalizePurchaseStatus($value): string
{
    $key = trim((string) $value);
    $map = [
        'not_purchased' => 'not_purchased',
        'purchased' => 'purchased',
        'unavailable' => 'unavailable',
        '未首購' => 'not_purchased',
        '已首購' => 'purchased',
        '無提供首購' => 'unavailable',
    ];
    return $map[$key] ?? 'not_purchased';
}

function fengbroTrialStatusLabel(string $status): string
{
    return $status === 'tried' ? '已試用' : '尚未試用';
}

function fengbroPurchaseStatusLabel(string $status): string
{
    if ($status === 'purchased') {
        return '已首購';
    }
    if ($status === 'unavailable') {
        return '無提供首購';
    }
    return '未首購';
}

function fengbroNormalizeReinstallSystem($value): string
{
    $key = strtolower(trim((string) $value));
    if (in_array($key, ['mac', 'macos', 'osx', '蘋果'], true) || $value === 'Mac') {
        return 'mac';
    }
    return 'win';
}

function fengbroNormalizeSoftwareType($value): string
{
    $key = trim((string) $value);
    $map = [
        'trial' => 'trial',
        'free' => 'free',
        'paid' => 'paid',
        '試用' => 'trial',
        '試用軟體' => 'trial',
        '免費' => 'free',
        '免費軟體' => 'free',
        '付費' => 'paid',
        '付費軟體' => 'paid',
    ];
    return $map[$key] ?? 'free';
}

function fengbroNormalizeLicenseType($value): string
{
    $key = trim((string) $value);
    $map = [
        'none' => 'none',
        'paid_serial' => 'paid_serial',
        '無序號' => 'none',
        '付費序號' => 'paid_serial',
    ];
    return $map[$key] ?? 'none';
}

function fengbroReinstallSystemLabel(string $system): string
{
    return $system === 'mac' ? 'Mac' : 'Windows';
}

function fengbroSoftwareTypeLabel(string $type): string
{
    if ($type === 'trial') {
        return '試用軟體';
    }
    if ($type === 'paid') {
        return '付費軟體';
    }
    return '免費軟體';
}

function fengbroLicenseTypeLabel(string $type): string
{
    return $type === 'paid_serial' ? '付費序號' : '無序號';
}

function fengbroParseBoolean($value, bool $default = false, bool $strict = false): bool
{
    if ($value === true || $value === 1) {
        return true;
    }
    if ($value === false || $value === 0) {
        return false;
    }
    $raw = trim((string) $value);
    if ($raw === '') {
        return $default;
    }
    $key = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    $map = [
        '1' => true,
        'true' => true,
        'yes' => true,
        'on' => true,
        '是' => true,
        '訂閱制' => true,
        '0' => false,
        'false' => false,
        'no' => false,
        'off' => false,
        '否' => false,
    ];
    if (array_key_exists($key, $map)) {
        return $map[$key];
    }
    if ($strict) {
        throw new InvalidArgumentException('訂閱制軟體不正確');
    }
    return $default;
}

function fengbroParseReinstallSubscriptionPeriod(?string $value): array
{
    if (preg_match('/^([1-9]\d{0,3})(年|月)$/u', trim((string) $value), $m)) {
        return ['count' => (int) $m[1], 'unit' => $m[2] === '年' ? 'year' : 'month'];
    }
    return ['count' => 1, 'unit' => 'month'];
}

function fengbroFormatReinstallSubscriptionPeriod(int $count, string $unit): string
{
    return $count . ($unit === 'year' || $unit === '年' ? '年' : '月');
}

function fengbroReinstallSubscriptionPeriodLabel(?string $value): string
{
    $parsed = fengbroParseReinstallSubscriptionPeriod($value);
    return $parsed['unit'] === 'year' ? ($parsed['count'] . ' 年') : ($parsed['count'] . ' 個月');
}

function fengbroNormalizeReinstallSubscriptionPeriod(array $input, bool $enabled): string
{
    if (!$enabled) {
        return '';
    }
    $raw = trim((string) ($input['subscriptionPeriod'] ?? ''));
    if ($raw !== '') {
        if (!preg_match('/^[1-9]\d{0,3}(年|月)$/u', $raw)) {
            throw new InvalidArgumentException('訂閱週期必須是 ?年 或 ?月，例如 1年、3月');
        }
        return $raw;
    }
    $countRaw = $input['subscriptionPeriodCount'] ?? 1;
    $count = ($countRaw === null || $countRaw === '') ? 1 : fengbroNonNegativeInt($countRaw);
    if ($count < 1) {
        throw new InvalidArgumentException('訂閱週期必須是 1 以上的整數');
    }
    $unitRaw = trim((string) ($input['subscriptionPeriodUnit'] ?? 'month'));
    $unitMap = [
        'year' => 'year',
        'month' => 'month',
        '年' => 'year',
        '月' => 'month',
    ];
    if ($unitRaw === '') {
        $unit = 'month';
    } elseif (!isset($unitMap[$unitRaw])) {
        throw new InvalidArgumentException('訂閱週期必須是 ?年 或 ?月，例如 1年、3月');
    } else {
        $unit = $unitMap[$unitRaw];
    }
    return fengbroFormatReinstallSubscriptionPeriod($count, $unit);
}

function fengbroNormalizeReinstallCurrency($value, bool $enabled): string
{
    if (!$enabled) {
        return 'TWD';
    }
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'TWD';
    }
    $ascii = strtoupper($raw);
    $map = [
        'TWD' => 'TWD',
        '台幣' => 'TWD',
        '臺幣' => 'TWD',
        'USD' => 'USD',
        '美元' => 'USD',
        'JPY' => 'JPY',
        '日圓' => 'JPY',
        '日元' => 'JPY',
        'CNY' => 'CNY',
        '人民幣' => 'CNY',
    ];
    if (isset($map[$ascii])) {
        return $map[$ascii];
    }
    if (isset($map[$raw])) {
        return $map[$raw];
    }
    throw new InvalidArgumentException('訂閱費用幣別不正確');
}

function fengbroFormatReinstallMoney(int $amount, string $currency): string
{
    try {
        $currency = fengbroNormalizeReinstallCurrency($currency ?: 'TWD', true);
    } catch (InvalidArgumentException $e) {
        $currency = 'TWD';
    }
    $rates = ['TWD' => 1, 'USD' => 35, 'JPY' => 0.35, 'CNY' => 4.5];
    $symbols = ['TWD' => 'NT$', 'USD' => '$', 'JPY' => '¥', 'CNY' => '¥'];
    $twd = (int) round($amount * ($rates[$currency] ?? 1));
    if ($currency === 'TWD') {
        return 'NT$ ' . number_format($amount);
    }
    return 'NT$ ' . number_format($twd) . ' (' . $symbols[$currency] . ' ' . number_format($amount) . ')';
}

function fengbroSafeHttpUrl($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $value)) {
        return '';
    }
    $parts = parse_url($value);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    return $value;
}

function fengbroSanitizeTrialPurchaseRow(array $input): array
{
    $name = fengbroMbCut($input['name'] ?? '', 100);
    if ($name === '') {
        throw new InvalidArgumentException('請填寫服務名稱');
    }
    return [
        'name' => $name,
        'eventDate' => fengbroOptionalDate($input['eventDate'] ?? ''),
        'firstPurchasePrice' => fengbroNonNegativeInt($input['firstPurchasePrice'] ?? 0),
        'regularPrice' => fengbroNonNegativeInt($input['regularPrice'] ?? 0),
        'account' => fengbroMbCut($input['account'] ?? '', 200),
        'note' => fengbroMbCut($input['note'] ?? '', 3337),
        'trialStatus' => fengbroNormalizeTrialStatus($input['trialStatus'] ?? 'untried'),
        'purchaseStatus' => fengbroNormalizePurchaseStatus($input['purchaseStatus'] ?? 'not_purchased'),
    ];
}

function fengbroSanitizeReinstallRow(array $input): array
{
    $name = fengbroMbCut($input['name'] ?? '', 100);
    if ($name === '') {
        throw new InvalidArgumentException('請填寫服務名稱');
    }
    $licenseType = fengbroNormalizeLicenseType($input['licenseType'] ?? 'none');
    $serial = $licenseType === 'paid_serial' ? fengbroMbCut($input['serial'] ?? '', 500) : '';
    $viewPassword = $licenseType === 'paid_serial' ? fengbroMbCut($input['viewPassword'] ?? '', 100) : '';
    $subscriptionSoftware = fengbroParseBoolean($input['subscriptionSoftware'] ?? false, false, true);
    $site = fengbroSafeHttpUrl($input['site'] ?? '');
    if (trim((string) ($input['site'] ?? '')) !== '' && $site === '') {
        throw new InvalidArgumentException('軟體網站必須是完整 http 或 https 網址');
    }
    return [
        'name' => $name,
        'system' => fengbroNormalizeReinstallSystem($input['system'] ?? 'win'),
        'softwareType' => fengbroNormalizeSoftwareType($input['softwareType'] ?? 'free'),
        'licenseType' => $licenseType,
        'serial' => $serial,
        'viewPassword' => $viewPassword,
        'subscriptionSoftware' => $subscriptionSoftware ? 1 : 0,
        'subscriptionPeriod' => fengbroNormalizeReinstallSubscriptionPeriod($input, $subscriptionSoftware),
        'subscriptionPrice' => $subscriptionSoftware ? fengbroNonNegativeInt($input['subscriptionPrice'] ?? 0) : 0,
        'subscriptionCurrency' => fengbroNormalizeReinstallCurrency($input['subscriptionCurrency'] ?? 'TWD', $subscriptionSoftware),
        'site' => $site,
        'note' => fengbroMbCut($input['note'] ?? '', 3337),
    ];
}

function fengbroFindTrialPurchaseImportId(PDO $pdo, array $data): ?string
{
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        return null;
    }
    $account = trim((string) ($data['account'] ?? ''));
    $stmt = $pdo->prepare(
        "SELECT id FROM trialpurchase
         WHERE LOWER(TRIM(name)) = LOWER(?)
           AND LOWER(TRIM(IFNULL(account, ''))) = LOWER(?)
         LIMIT 1"
    );
    $stmt->execute([$name, $account]);
    $id = $stmt->fetchColumn();
    return $id ? (string) $id : null;
}

function fengbroFindReinstallImportId(PDO $pdo, array $data): ?string
{
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        return null;
    }
    $system = fengbroNormalizeReinstallSystem($data['system'] ?? 'win');
    $stmt = $pdo->prepare(
        "SELECT id FROM reinstall
         WHERE LOWER(TRIM(name)) = LOWER(?)
           AND LOWER(TRIM(IFNULL(`system`, 'win'))) = LOWER(?)
         LIMIT 1"
    );
    $stmt->execute([$name, $system]);
    $id = $stmt->fetchColumn();
    return $id ? (string) $id : null;
}

function fengbroManagementImportFieldMap(): array
{
    return [
        '服務' => 'name',
        '服務名稱' => 'name',
        'event_date' => 'eventDate',
        'date' => 'eventDate',
        '日期' => 'eventDate',
        '試用日' => 'eventDate',
        '首購日' => 'eventDate',
        '到期日' => 'eventDate',
        '扣款日' => 'eventDate',
        '試用／首購／到期日' => 'eventDate',
        '試用/首購/到期日' => 'eventDate',
        '試用／首購／到期日（扣款日）' => 'eventDate',
        'first_purchase_price' => 'firstPurchasePrice',
        '首購價格' => 'firstPurchasePrice',
        'regular_price' => 'regularPrice',
        '非首購價格' => 'regularPrice',
        '一般價格' => 'regularPrice',
        '帳號' => 'account',
        '備註' => 'note',
        'trial_status' => 'trialStatus',
        '試用狀態' => 'trialStatus',
        'purchase_status' => 'purchaseStatus',
        '首購狀態' => 'purchaseStatus',
        '使用系統' => 'system',
        '系統' => 'system',
        'software_type' => 'softwareType',
        '軟體類型' => 'softwareType',
        'license_type' => 'licenseType',
        '授權方式' => 'licenseType',
        '付費序號' => 'serial',
        '序號' => 'serial',
        'view_password' => 'viewPassword',
        '查看密碼' => 'viewPassword',
        'subscription_software' => 'subscriptionSoftware',
        '訂閱制軟體' => 'subscriptionSoftware',
        '訂閱制' => 'subscriptionSoftware',
        'subscription_period' => 'subscriptionPeriod',
        '訂閱週期' => 'subscriptionPeriod',
        '週期' => 'subscriptionPeriod',
        'subscription_price' => 'subscriptionPrice',
        '訂閱費用' => 'subscriptionPrice',
        '費用' => 'subscriptionPrice',
        'subscription_currency' => 'subscriptionCurrency',
        '訂閱費用幣別' => 'subscriptionCurrency',
        '幣別' => 'subscriptionCurrency',
        '軟體網站' => 'site',
        '網站' => 'site',
    ];
}

function fengbroQuotaCreateSql(): string
{
    return "CREATE TABLE IF NOT EXISTS quota (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            serviceType VARCHAR(20) DEFAULT 'general',
            account VARCHAR(200),
            quotaRemaining INT DEFAULT 0,
            quotaRatio INT DEFAULT 0,
            quotaExpiry DATETIME NULL,
            ratio5h INT DEFAULT 0,
            expiry5h VARCHAR(10) DEFAULT '',
            ratioWeek INT DEFAULT 0,
            expiryWeek VARCHAR(10) DEFAULT '',
            ratioMonth INT DEFAULT 0,
            expiryMonth VARCHAR(10) DEFAULT '',
            note VARCHAR(3337),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function fengbroEnsureQuotaTable(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getConnection();
    $pdo->exec(fengbroQuotaCreateSql());
    fengbroEnsureTableColumns($pdo, 'quota', [
        "serviceType VARCHAR(20) DEFAULT 'general'",
        "account VARCHAR(200)",
        "quotaRemaining INT DEFAULT 0",
        "quotaRatio INT DEFAULT 0",
        "quotaExpiry DATETIME NULL",
        "ratio5h INT DEFAULT 0",
        "expiry5h VARCHAR(10) DEFAULT ''",
        "ratioWeek INT DEFAULT 0",
        "expiryWeek VARCHAR(10) DEFAULT ''",
        "ratioMonth INT DEFAULT 0",
        "expiryMonth VARCHAR(10) DEFAULT ''",
        "note VARCHAR(3337)",
    ]);
}

function fengbroNormalizeQuotaServiceType($value): string
{
    $key = trim((string) $value);
    $map = [
        'general' => 'general',
        'ai' => 'ai',
        '一般' => 'general',
        '一般服務' => 'general',
        'AI' => 'ai',
        'AI 服務' => 'ai',
        'ai 服務' => 'ai',
    ];
    return $map[$key] ?? 'general';
}

function fengbroQuotaServiceTypeLabel(string $type): string
{
    return $type === 'ai' ? 'AI 服務' : '一般';
}

function fengbroSanitizeQuotaRow(array $input): array
{
    $name = fengbroMbCut($input['name'] ?? '', 100);
    if ($name === '') {
        throw new InvalidArgumentException('請填寫服務名稱');
    }
    $serviceType = fengbroNormalizeQuotaServiceType($input['serviceType'] ?? 'general');
    $expiry5h = trim((string) ($input['expiry5h'] ?? ''));
    if ($expiry5h !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $expiry5h)) {
        throw new InvalidArgumentException('5 小時到期需為 HH:mm（24 小時制，例如 14:30）');
    }
    $expiryWeek = trim((string) ($input['expiryWeek'] ?? ''));
    if ($expiryWeek !== '' && !preg_match('/^\d{4}-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[01])$/', $expiryWeek)) {
        throw new InvalidArgumentException('一週到期格式需為 西元年-月-日（例如 2026-09-30）');
    }
    $expiryMonth = trim((string) ($input['expiryMonth'] ?? ''));
    if ($expiryMonth !== '' && !preg_match('/^\d{4}-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[01])$/', $expiryMonth)) {
        throw new InvalidArgumentException('一月到期格式需為 西元年-月-日（例如 2026-12-31）');
    }
    $payload = [
        'name' => $name,
        'serviceType' => $serviceType,
        'account' => fengbroMbCut($input['account'] ?? '', 200),
        'quotaRemaining' => fengbroNonNegativeInt($input['quotaRemaining'] ?? 0),
        'quotaRatio' => fengbroNonNegativeInt($input['quotaRatio'] ?? 0),
        'note' => fengbroMbCut($input['note'] ?? '', 3337),
    ];
    $quotaExpiry = fengbroOptionalDate($input['quotaExpiry'] ?? '');
    $payload['quotaExpiry'] = $quotaExpiry;
    if ($serviceType === 'ai') {
        $payload['ratio5h'] = fengbroNonNegativeInt($input['ratio5h'] ?? 0);
        $payload['expiry5h'] = $expiry5h;
        $payload['ratioWeek'] = fengbroNonNegativeInt($input['ratioWeek'] ?? 0);
        $payload['expiryWeek'] = $expiryWeek;
        $payload['ratioMonth'] = fengbroNonNegativeInt($input['ratioMonth'] ?? 0);
        $payload['expiryMonth'] = $expiryMonth;
    } else {
        $payload['ratio5h'] = 0;
        $payload['expiry5h'] = '';
        $payload['ratioWeek'] = 0;
        $payload['expiryWeek'] = '';
        $payload['ratioMonth'] = 0;
        $payload['expiryMonth'] = '';
    }
    return $payload;
}

function fengbroFindQuotaImportId(PDO $pdo, array $data): ?string
{
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        return null;
    }
    $account = trim((string) ($data['account'] ?? ''));
    $stmt = $pdo->prepare(
        "SELECT id FROM quota
         WHERE LOWER(TRIM(name)) = LOWER(?)
           AND LOWER(TRIM(IFNULL(account, ''))) = LOWER(?)
         LIMIT 1"
    );
    $stmt->execute([$name, $account]);
    $id = $stmt->fetchColumn();
    return $id ? (string) $id : null;
}

function fengbroShoppingListCreateSql(): string
{
    return "CREATE TABLE IF NOT EXISTS shoppinglist (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            plannedDate DATETIME NULL,
            price INT DEFAULT 0,
            currency VARCHAR(10) DEFAULT 'TWD',
            quantity INT DEFAULT 1,
            shop VARCHAR(100),
            pickupMethod VARCHAR(30),
            imageUrl VARCHAR(2000) DEFAULT '',
            account VARCHAR(200),
            note VARCHAR(3337),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function fengbroEnsureShoppingListTable(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getConnection();
    $pdo->exec(fengbroShoppingListCreateSql());
    fengbroEnsureTableColumns($pdo, 'shoppinglist', [
        "plannedDate DATETIME NULL",
        "price INT DEFAULT 0",
        "currency VARCHAR(10) DEFAULT 'TWD'",
        "quantity INT DEFAULT 1",
        "shop VARCHAR(100)",
        "pickupMethod VARCHAR(30)",
        "imageUrl VARCHAR(2000) DEFAULT ''",
        "account VARCHAR(200)",
        "note VARCHAR(3337)",
    ]);
}

function fengbroNormalizeShoppingCurrency($value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'TWD';
    }
    $ascii = strtoupper($raw);
    $map = [
        'TWD' => 'TWD',
        '台幣' => 'TWD',
        '新台幣' => 'TWD',
        'USD' => 'USD',
        '美元' => 'USD',
        '美金' => 'USD',
        'JPY' => 'JPY',
        '日圓' => 'JPY',
        '日幣' => 'JPY',
        'CNY' => 'CNY',
        '人民幣' => 'CNY',
        'RMB' => 'CNY',
        'rmb' => 'CNY',
    ];
    if (isset($map[$ascii])) {
        return $map[$ascii];
    }
    if (isset($map[$raw])) {
        return $map[$raw];
    }
    throw new InvalidArgumentException('幣別需為 台幣／美元／日圓／人民幣（TWD/USD/JPY/CNY）');
}

function fengbroSanitizeShoppingItemRow(array $input): array
{
    $name = fengbroMbCut($input['name'] ?? '', 100);
    if ($name === '') {
        throw new InvalidArgumentException('請填寫購物名稱');
    }
    $quantity = fengbroNonNegativeInt($input['quantity'] ?? 1);
    if ($quantity < 1) {
        throw new InvalidArgumentException('預定數量必須是 1 以上的整數');
    }
    $pickupMethod = fengbroMbCut($input['pickupMethod'] ?? '', 30);
    $imageRaw = trim((string) ($input['imageUrl'] ?? ''));
    $imageUrl = $imageRaw !== '' ? fengbroSafeHttpUrl($imageRaw) : '';
    if ($imageRaw !== '' && $imageUrl === '') {
        throw new InvalidArgumentException('商品圖片網址必須是完整 http 或 https 網址');
    }
    return [
        'name' => $name,
        'plannedDate' => fengbroOptionalDate($input['plannedDate'] ?? ''),
        'price' => fengbroNonNegativeInt($input['price'] ?? 0),
        'currency' => fengbroNormalizeShoppingCurrency($input['currency'] ?? 'TWD'),
        'quantity' => $quantity,
        'shop' => fengbroMbCut($input['shop'] ?? '', 100),
        'pickupMethod' => $pickupMethod,
        'imageUrl' => $imageUrl,
        'account' => fengbroMbCut($input['account'] ?? '', 200),
        'note' => fengbroMbCut($input['note'] ?? '', 3337),
    ];
}

function fengbroFindShoppingImportId(PDO $pdo, array $data): ?string
{
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT id FROM shoppinglist
         WHERE LOWER(TRIM(name)) = LOWER(?)
         LIMIT 1"
    );
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    return $id ? (string) $id : null;
}

function fengbroManualPriceCreateSql(): string
{
    return "CREATE TABLE IF NOT EXISTS manualprice (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            currency VARCHAR(10) DEFAULT 'TWD',
            recordsJson TEXT,
            localId VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_local_id (localId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function fengbroEnsureManualPriceTable(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getConnection();
    $pdo->exec(fengbroManualPriceCreateSql());
    fengbroEnsureTableColumns($pdo, 'manualprice', [
        "name VARCHAR(200) NOT NULL",
        "currency VARCHAR(10) DEFAULT 'TWD'",
        "recordsJson TEXT",
        "localId VARCHAR(100)",
    ]);
}

const FENGBRO_MANUAL_PRICE_CURRENCIES = ['TWD', 'USD', 'JPY'];
const FENGBRO_MANUAL_PRICE_MAX_RECORDS = 200;

function fengbroManualPriceNormalizeCurrency($value): string
{
    $code = strtoupper(trim((string) $value));
    return in_array($code, FENGBRO_MANUAL_PRICE_CURRENCIES, true) ? $code : 'TWD';
}

/** 解析並正規化 recordsJson（每筆需 id/price/date，price>=0；note 可選）。 */
function fengbroManualPriceParseRecordsJson(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $parsed = json_decode($raw, true);
    if (!is_array($parsed)) {
        return [];
    }
    $out = [];
    foreach ($parsed as $item) {
        if (!is_array($item)) {
            continue;
        }
        $price = filter_var($item['price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($price === false || $price < 0) {
            continue;
        }
        $date = trim((string) ($item['date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }
        $id = trim((string) ($item['id'] ?? ''));
        if ($id === '') {
            $id = generateUUID();
        }
        $note = trim((string) ($item['note'] ?? ''));
        $rec = [
            'id' => $id,
            'price' => (float) $price,
            'date' => $date,
        ];
        if ($note !== '') {
            $rec['note'] = fengbroMbCut($note, 200);
        }
        $out[] = $rec;
        if (count($out) >= FENGBRO_MANUAL_PRICE_MAX_RECORDS) {
            break;
        }
    }
    usort($out, static function (array $a, array $b): int {
        return strcmp((string) $a['date'], (string) $b['date']) ?: strcmp((string) $a['id'], (string) $b['id']);
    });
    return $out;
}

function fengbroManualPriceSerializeRecords(array $records): string
{
    $records = fengbroManualPriceParseRecordsJson(json_encode($records, JSON_UNESCAPED_UNICODE));
    $clean = array_map(static function (array $r): array {
        $item = ['id' => $r['id'], 'price' => $r['price'], 'date' => $r['date']];
        if (!empty($r['note'])) {
            $item['note'] = $r['note'];
        }
        return $item;
    }, $records);
    return json_encode($clean, JSON_UNESCAPED_UNICODE);
}

function fengbroSanitizeManualPriceRow(array $input): array
{
    $name = fengbroMbCut($input['name'] ?? '', 200);
    if ($name === '') {
        throw new InvalidArgumentException('商品名稱為必填');
    }
    $recordsSource = $input['records'] ?? null;
    if ($recordsSource !== null) {
        $recordsJson = fengbroManualPriceSerializeRecords(is_array($recordsSource) ? $recordsSource : []);
    } else {
        $recordsRaw = $input['recordsJson'] ?? '[]';
        $recordsJson = fengbroManualPriceSerializeRecords(
            fengbroManualPriceParseRecordsJson(
                is_string($recordsRaw) ? $recordsRaw : json_encode(is_array($recordsRaw) ? $recordsRaw : [])
            )
        );
    }
    $localId = trim((string) ($input['localId'] ?? ''));
    if ($localId !== '' && strlen($localId) > 100) {
        $localId = substr($localId, 0, 100);
    }
    return [
        'name' => $name,
        'currency' => fengbroManualPriceNormalizeCurrency($input['currency'] ?? 'TWD'),
        'recordsJson' => $recordsJson,
        'localId' => $localId,
    ];
}

/** 把 DB row 轉成前端用的 product 物件。 */
function fengbroManualPriceToClientProduct(array $row): array
{
    $created = strtotime((string) ($row['created_at'] ?? 'now')) ?: time();
    $updated = strtotime((string) ($row['updated_at'] ?? 'now')) ?: time();
    return [
        'id' => (string) $row['id'],
        'name' => (string) ($row['name'] ?? ''),
        'currency' => fengbroManualPriceNormalizeCurrency($row['currency'] ?? 'TWD'),
        'localId' => trim((string) ($row['localId'] ?? '')) !== '' ? (string) $row['localId'] : null,
        'createdAt' => $created * 1000,
        'updatedAt' => $updated * 1000,
        'records' => fengbroManualPriceParseRecordsJson($row['recordsJson'] ?? null),
    ];
}

function fengbroFinanceInstrumentCreateSql(): string
{
    return "CREATE TABLE IF NOT EXISTS financeinstrument (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            symbol VARCHAR(64) NOT NULL,
            provider VARCHAR(20) NOT NULL DEFAULT 'yahoo',
            `group` VARCHAR(20) DEFAULT 'other',
            imageUrl1 VARCHAR(2000) DEFAULT '',
            imageUrl2 VARCHAR(2000) DEFAULT '',
            imageUrl3 VARCHAR(2000) DEFAULT '',
            youtubeUrl VARCHAR(2000) DEFAULT '',
            bilibiliUrl VARCHAR(2000) DEFAULT '',
            linkUrl1 VARCHAR(1000) DEFAULT '',
            linkUrl2 VARCHAR(1000) DEFAULT '',
            linkUrl3 VARCHAR(1000) DEFAULT '',
            featured TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_provider_symbol (provider, symbol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function fengbroEnsureFinanceInstrumentTable(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getConnection();
    $pdo->exec(fengbroFinanceInstrumentCreateSql());
    fengbroEnsureTableColumns($pdo, 'financeinstrument', [
        "name VARCHAR(200) NOT NULL",
        "symbol VARCHAR(64) NOT NULL",
        "provider VARCHAR(20) NOT NULL DEFAULT 'yahoo'",
        "`group` VARCHAR(20) DEFAULT 'other'",
        "imageUrl1 VARCHAR(2000) DEFAULT ''",
        "imageUrl2 VARCHAR(2000) DEFAULT ''",
        "imageUrl3 VARCHAR(2000) DEFAULT ''",
        "youtubeUrl VARCHAR(2000) DEFAULT ''",
        "bilibiliUrl VARCHAR(2000) DEFAULT ''",
        "linkUrl1 VARCHAR(1000) DEFAULT ''",
        "linkUrl2 VARCHAR(1000) DEFAULT ''",
        "linkUrl3 VARCHAR(1000) DEFAULT ''",
        "featured TINYINT(1) DEFAULT 0",
    ]);
}
