<?php
define('FENGBRO_PUBLIC_ENTRY', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';

if (fengbroIsAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempts = (array) ($_SESSION['login_attempts'] ?? []);
    $attempts = array_values(array_filter($attempts, static fn($time) => (int) $time > time() - 600));
    if (count($attempts) >= 5) {
        http_response_code(429);
        $error = '登入嘗試過於頻繁，請 10 分鐘後再試。';
    }
    $token = (string) ($_POST['_csrf'] ?? '');
    if ($error !== '') {
        // Keep the generic lockout response above.
    } elseif (!hash_equals(fengbroCsrfToken(), $token)) {
        $error = '登入頁已過期，請重新嘗試。';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $valid = false;
        $userId = '';
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, (string) $user['password'])) {
                $valid = true;
                $userId = (string) $user['id'];
                $username = (string) $user['username'];
            }
        } catch (Throwable $e) {}

        $envUser = (string) (getenv('FENGBRO_ADMIN_USER') ?: 'admin');
        $envHash = (string) getenv('FENGBRO_ADMIN_PASSWORD_HASH');
        if (!$valid && $envHash !== '' && hash_equals($envUser, $username) && password_verify($password, $envHash)) {
            $valid = true;
            $userId = 'environment-admin';
        }

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['fengbro_user_id'] = $userId;
            $_SESSION['fengbro_username'] = $username;
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            unset($_SESSION['login_attempts']);
            $next = (string) ($_POST['next'] ?? 'index.php');
            if (!preg_match('#^/(?!/)[A-Za-z0-9_./?&=%-]*$#', $next)) $next = '/index.php';
            header('Location: ' . $next);
            exit;
        }
        $attempts[] = time();
        $_SESSION['login_attempts'] = $attempts;
        usleep(500000);
        $error = '帳號或密碼不正確。';
    }
}
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '/index.php');
?>
<!doctype html><html lang="zh-TW"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>登入 Fengbro AI</title><link rel="stylesheet" href="assets/css/style.css?v=20260821"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></head>
<body class="auth-page"><main class="auth-shell"><section class="auth-panel" aria-labelledby="loginTitle"><div class="auth-brand"><i class="fa-solid fa-shield-halved"></i></div><h1 id="loginTitle">登入個人作業中樞</h1><p>這是私人管理系統，請驗證身分後繼續。</p><?php if ($error): ?><div class="auth-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?><form method="post" class="auth-form"><input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(fengbroCsrfToken()); ?>"><input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>"><label for="username">帳號或 Email</label><input id="username" name="username" type="text" autocomplete="username" required autofocus><label for="password">密碼</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit"><i class="fa-solid fa-arrow-right-to-bracket"></i>安全登入</button></form></section></main></body></html>
