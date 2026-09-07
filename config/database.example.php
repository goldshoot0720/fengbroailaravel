<?php
/**
 * 資料庫設定範例
 *
 * 使用方式：
 *   1. 複製本檔為 config/database.php（該檔已列入 .gitignore，不會進版控）
 *   2. 修改下面四個常數為實際連線資訊
 *   3. 瀏覽 install.php 建立資料庫與資料表（或直接匯入 database.sql）
 */

// 以下請自行手動改成實際值（保持 __ 開頭的佔位字串會連線失敗，提醒你還沒改）
define('DB_HOST', 'localhost');
define('DB_NAME', '__YOUR_DB_NAME__');   // database.sql 預設建立的是 feng_laravel
define('DB_USER', '__YOUR_DB_USER__');
define('DB_PASS', '__YOUR_DB_PASSWORD__');
define('DB_CHARSET', 'utf8mb4');

/**
 * 取得共用 PDO 連線（單例，同一次請求只連一次）。
 */
function getConnection() {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // 不要把帳密或完整錯誤丟到前端
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('資料庫連線失敗，請檢查 config/database.php 設定。');
    }

    return $pdo;
}
