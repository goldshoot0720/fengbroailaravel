<?php
/**
 * 資料庫配置範例
 * 複製此檔案為 database.php 並填入真實憑證
 */

// 自動偵測環境
$host = $_SERVER['HTTP_HOST'] ?? '';
$isRemote = (strpos($host, 'your-domain.com') !== false);
$GLOBALS['ENV'] = $isRemote ? 'remote' : 'local';
$ENV = $GLOBALS['ENV'];

$dbConfig = [
    'local' => [
        'host'    => '127.0.0.1',
        'name'    => 'your_local_db_name',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4'
    ],
    'remote' => [
        'host'    => 'localhost',
        'name'    => 'your_remote_db_name',
        'user'    => 'your_remote_db_user',
        'pass'    => 'your_remote_db_password',
        'charset' => 'utf8mb4'
    ]
];

$config = $dbConfig[$ENV];

define('DB_HOST', $config['host']);
define('DB_NAME', $config['name']);
define('DB_USER', $config['user']);
define('DB_PASS', $config['pass']);
define('DB_CHARSET', $config['charset']);

function getConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("資料庫連接失敗: " . $e->getMessage());
    }
}
