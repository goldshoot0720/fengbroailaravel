<?php
require_once 'config/database.php';

echo "<h1>鋒兄系統 - 資料庫安裝</h1>";
echo "<pre>";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 建立資料庫
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ 資料庫 " . DB_NAME . " 建立成功\n";

    $pdo->exec("USE " . DB_NAME);

    // 建立資料表
    $tables = [
        "subscription" => "CREATE TABLE IF NOT EXISTS subscription (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            site VARCHAR(500),
            price INT,
            nextdate DATETIME,
            note VARCHAR(100),
            account VARCHAR(100),
            currency VARCHAR(100),
            `continue` BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "food" => "CREATE TABLE IF NOT EXISTS food (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            amount INT,
            price INT,
            shop VARCHAR(100),
            todate DATETIME,
            photo VARCHAR(500),
            photohash VARCHAR(256),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "article" => "CREATE TABLE IF NOT EXISTS article (
            id VARCHAR(36) PRIMARY KEY,
            title VARCHAR(100) NOT NULL,
            content TEXT,
            category VARCHAR(100),
            ref VARCHAR(100),
            newDate DATETIME,
            url1 VARCHAR(500),
            url2 VARCHAR(500),
            url3 VARCHAR(500),
            file1 VARCHAR(150),
            file1name VARCHAR(100),
            file1type VARCHAR(100),
            file2 VARCHAR(150),
            file2name VARCHAR(100),
            file2type VARCHAR(100),
            file3 VARCHAR(150),
            file3name VARCHAR(100),
            file3type VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "image" => "CREATE TABLE IF NOT EXISTS image (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            file VARCHAR(150),
            filetype VARCHAR(50),
            note VARCHAR(100),
            ref VARCHAR(100),
            category VARCHAR(100),
            hash VARCHAR(300),
            cover VARCHAR(150),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "music" => "CREATE TABLE IF NOT EXISTS music (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            file VARCHAR(150),
            lyrics TEXT,
            note VARCHAR(100),
            ref VARCHAR(100),
            category VARCHAR(100),
            hash VARCHAR(300),
            language VARCHAR(100),
            cover VARCHAR(150),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "podcast" => "CREATE TABLE IF NOT EXISTS podcast (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            file VARCHAR(150),
            note VARCHAR(20),
            ref VARCHAR(100),
            category VARCHAR(100),
            hash VARCHAR(300),
            cover VARCHAR(150),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "bank" => "CREATE TABLE IF NOT EXISTS bank (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            deposit INT,
            site VARCHAR(500),
            address VARCHAR(100),
            withdrawals INT,
            transfer INT,
            activity VARCHAR(500),
            card VARCHAR(100),
            account VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "routine" => "CREATE TABLE IF NOT EXISTS routine (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            note VARCHAR(100),
            lastdate1 DATETIME,
            lastdate2 DATETIME,
            lastdate3 DATETIME,
            link VARCHAR(500),
            photo VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "commondocument" => "CREATE TABLE IF NOT EXISTS commondocument (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            file VARCHAR(150),
            note VARCHAR(100),
            ref VARCHAR(100),
            category VARCHAR(100),
            hash VARCHAR(300),
            cover VARCHAR(150),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "push_subscriptions" => "CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            endpoint TEXT NOT NULL,
            auth VARCHAR(255) NOT NULL,
            p256dh VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_endpoint (endpoint(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        echo "✓ 資料表 {$name} 建立成功\n";
    }

    // commonaccount 表 (有很多欄位)
    $commonaccountSQL = "CREATE TABLE IF NOT EXISTS commonaccount (
        id VARCHAR(36) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,";

    for ($i = 1; $i <= 37; $i++) {
        $idx = str_pad($i, 2, '0', STR_PAD_LEFT);
        $commonaccountSQL .= "site{$idx} VARCHAR(100),";
    }
    for ($i = 1; $i <= 37; $i++) {
        $idx = str_pad($i, 2, '0', STR_PAD_LEFT);
        $commonaccountSQL .= "note{$idx} VARCHAR(100),";
    }
    $commonaccountSQL .= "photohash VARCHAR(256),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    $pdo->exec($commonaccountSQL);
    echo "✓ 資料表 commonaccount 建立成功\n";

    // 升級既有欄位（舊資料庫補欄位）
    $upgrades = [
        "ALTER TABLE article MODIFY COLUMN content TEXT",
        "ALTER TABLE article MODIFY COLUMN file1type VARCHAR(100)",
        "ALTER TABLE article MODIFY COLUMN file2type VARCHAR(100)",
        "ALTER TABLE article MODIFY COLUMN file3type VARCHAR(100)",
        "ALTER TABLE image ADD COLUMN filetype VARCHAR(50) AFTER file",
    ];
    foreach ($upgrades as $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ 欄位升級: {$sql}\n";
        } catch (PDOException $e) {
            echo "- 欄位升級略過: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=============================\n";
    echo "✓ 所有資料表建立完成！\n";
    echo "=============================\n";
    echo "\n<a href='index.php'>返回首頁</a>";

} catch (PDOException $e) {
    echo "✗ 錯誤: " . $e->getMessage() . "\n";
}

echo "</pre>";
