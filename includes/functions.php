<?php
$fengbroDatabaseConfigPath = __DIR__ . '/../config/database.php';

if (is_file($fengbroDatabaseConfigPath)) {
    require_once $fengbroDatabaseConfigPath;
} else {
    $GLOBALS['ENV'] = $GLOBALS['ENV'] ?? 'local';
}

function fengbroDatabaseConfigured(): bool {
    return is_file(__DIR__ . '/../config/database.php')
        && defined('DB_HOST')
        && defined('DB_NAME')
        && defined('DB_USER')
        && DB_NAME !== ''
        && DB_NAME !== 'your_local_db_name'
        && DB_NAME !== 'your_remote_db_name';
}

if (!function_exists('getConnection')) {
    function getConnection() {
        throw new RuntimeException('Database is not configured. Copy config/database_example.php to config/database.php and update the connection settings.');
    }
}

function renderSetupEmptyState(string $context = 'home'): void {
    $isDashboard = $context === 'dashboard';
    $title = $isDashboard ? 'Dashboard 尚未連接資料庫' : '尚未完成本地設定';
    $copy = $isDashboard
        ? '目前還沒有可用的資料庫設定，因此已暫停媒體統計、儲存空間統計與到期檢查，避免首頁持續觸發 500。'
        : '請先建立 config/database.php 並完成資料庫安裝。設定完成前，系統會先停用自動載入的音樂、影片、圖片、Podcast、儲存空間與到期檢查模組。';
    ?>
    <div class="content-header">
        <div class="page-intro">
            <span class="eyebrow">Setup required</span>
            <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars($copy, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
    <div class="content-body">
        <section class="hero-panel hero-panel-home" style="align-items:stretch;">
            <div class="hero-copy">
                <span class="eyebrow">Local configuration</span>
                <h2>先完成 Appwrite / MySQL 設定，再啟用資料模組</h2>
                <p>需要的檔案是 <code>config/database.php</code>。可以從 <code>config/database_example.php</code> 複製一份，填入本機連線資訊後，再執行資料庫安裝。</p>
                <div class="hero-actions">
                    <a href="index.php?page=settings" class="btn btn-primary">
                        <i class="fa-solid fa-gear"></i> 前往設定
                    </a>
                    <a href="config/database_example.php" class="btn btn-ghost">
                        <i class="fa-solid fa-file-code"></i> 查看範例設定
                    </a>
                </div>
            </div>
            <div class="hero-stack">
                <article class="signal-card signal-card-primary">
                    <span class="signal-label">Paused until setup</span>
                    <strong>music / video / image / podcast</strong>
                    <p>媒體模組不會在首頁或 dashboard 預先查詢。</p>
                </article>
                <article class="signal-card">
                    <span class="signal-label">Paused until setup</span>
                    <strong>storage stats / check-expiry</strong>
                    <p>儲存空間掃描與到期提醒會等資料庫設定完成後才啟用。</p>
                </article>
            </div>
        </section>
    </div>
    <?php
}

function requireDatabaseOrSetup(string $context = 'home'): bool {
    if (fengbroDatabaseConfigured()) {
        return true;
    }

    renderSetupEmptyState($context);
    return false;
}

function setupRequiredPayload(string $message = 'Database is not configured yet.'): array {
    return [
        'success' => false,
        'setupRequired' => true,
        'message' => $message,
        'next' => 'settings'
    ];
}

function jsonSetupRequiredResponse(string $message = 'Database is not configured yet.'): void {
    jsonResponse(setupRequiredPayload($message), 503);
}

function databaseHealth(): array {
    $configured = fengbroDatabaseConfigured();
    $connected = false;
    $error = null;

    if ($configured) {
        try {
            getConnection();
            $connected = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    return [
        'databaseConfigured' => $configured,
        'databaseConnected' => $connected,
        'setupRequired' => !$configured,
        'error' => $error,
        'next' => $configured && !$connected ? 'check_database_credentials' : (!$configured ? 'settings' : null)
    ];
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getAll($table, $orderBy = 'created_at DESC') {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$orderBy}");
    return $stmt->fetchAll();
}

function getById($table, $id) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function deleteById($table, $id) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    return $stmt->execute([$id]);
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('Y-m-d', strtotime($date));
}

function formatDateTime($date) {
    if (empty($date)) return '-';
    return date('Y-m-d H:i', strtotime($date));
}

function formatMoney($amount) {
    if (empty($amount)) return '$0';
    return '$' . number_format($amount);
}

function findExistingImportRecordId(PDO $pdo, string $table, array $data, array $identityColumns = []): ?string {
    $ignored = ['id', 'created_at', 'updated_at'];
    if (!$identityColumns && !empty($data['hash'])) {
        $identityColumns = ['hash'];
    }
    $columns = $identityColumns ?: array_values(array_diff(array_keys($data), $ignored));
    $where = [];
    $values = [];

    foreach ($columns as $column) {
        if (in_array($column, $ignored, true) || !array_key_exists($column, $data)) {
            continue;
        }
        $value = $data[$column];
        if ($value === '') {
            $value = null;
        }
        if ($value === null) {
            $where[] = "`{$column}` IS NULL";
        } else {
            $where[] = "`{$column}` = ?";
            $values[] = $value;
        }
    }

    if (!$where) {
        return null;
    }

    $sql = "SELECT id FROM `{$table}` WHERE " . implode(' AND ', $where) . " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    $id = $stmt->fetchColumn();
    return $id ? (string)$id : null;
}

function importRecordExists(PDO $pdo, string $table, array $identity): bool {
    return findExistingImportRecordId($pdo, $table, $identity, array_keys($identity)) !== null;
}
