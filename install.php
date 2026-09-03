<?php
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/management_tables.php';

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
            deleted_at DATETIME NULL,
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
            deleted_at DATETIME NULL,
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
            filetype VARCHAR(50),
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
            filetype VARCHAR(50),
            note VARCHAR(20),
            ref VARCHAR(100),
            category VARCHAR(100),
            hash VARCHAR(300),
            cover VARCHAR(150),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "video" => "CREATE TABLE IF NOT EXISTS video (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            file VARCHAR(500),
            filetype VARCHAR(20),
            note VARCHAR(500),
            ref VARCHAR(300),
            category VARCHAR(100),
            hash VARCHAR(300),
            cover VARCHAR(500),
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
            filetype VARCHAR(50),
            note VARCHAR(100),
            ref VARCHAR(100),
            category VARCHAR(100),
            hash VARCHAR(300),
            cover VARCHAR(150),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

        "settings" => "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            setting_key VARCHAR(50) NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_setting (user_id, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "resend_notification_log" => "CREATE TABLE IF NOT EXISTS resend_notification_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_key VARCHAR(191) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            table_name VARCHAR(50) NOT NULL,
            record_id VARCHAR(64) NOT NULL,
            target_date DATE NOT NULL,
            recipient_email VARCHAR(191) NOT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_resend_event (event_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "push_subscriptions" => "CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            endpoint TEXT NOT NULL,
            auth VARCHAR(255) NOT NULL,
            p256dh VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_endpoint (endpoint(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "tool_price_history" => "CREATE TABLE IF NOT EXISTS tool_price_history (
            id VARCHAR(36) PRIMARY KEY,
            tool_type VARCHAR(30) NOT NULL,
            query_text VARCHAR(500) NOT NULL,
            title VARCHAR(500),
            source VARCHAR(100),
            current_price INT NULL,
            high_price INT NULL,
            low_price INT NULL,
            result_url VARCHAR(1000),
            notice TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tool_query (tool_type, query_text(191), created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "tool_phone_product_history" => "CREATE TABLE IF NOT EXISTS tool_phone_product_history (
            id VARCHAR(36) PRIMARY KEY,
            product_id VARCHAR(190) NOT NULL,
            brand VARCHAR(50),
            name VARCHAR(500) NOT NULL,
            source VARCHAR(50) NOT NULL,
            price INT NULL,
            source_url VARCHAR(1000),
            snapshot_day DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_product_day_source (product_id, snapshot_day, source),
            INDEX idx_product_day (product_id, snapshot_day)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "trialpurchase" => fengbroTrialPurchaseCreateSql(),

        "reinstall" => fengbroReinstallCreateSql(),

        "quota" => fengbroQuotaCreateSql(),

        "shoppinglist" => fengbroShoppingListCreateSql(),

        "manualprice" => fengbroManualPriceCreateSql(),

        "tubechannel" => "CREATE TABLE IF NOT EXISTS tubechannel (
            id VARCHAR(36) PRIMARY KEY,
            sourceUrl VARCHAR(500) NOT NULL,
            alias VARCHAR(200) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_source_url (sourceUrl(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "financeinstrument" => fengbroFinanceInstrumentCreateSql(),

        "sitevisit" => "CREATE TABLE IF NOT EXISTS sitevisit (
            id VARCHAR(36) PRIMARY KEY,
            count INT NOT NULL DEFAULT 0,
            lastVisitAt DATETIME NULL,
            currentStreak INT NOT NULL DEFAULT 0,
            lastVisitDate VARCHAR(10) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "menuusage" => "CREATE TABLE IF NOT EXISTS menuusage (
            id VARCHAR(36) PRIMARY KEY,
            moduleId VARCHAR(100) NOT NULL,
            count INT NOT NULL DEFAULT 0,
            lastUsedAt DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_module_id (moduleId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
        "ALTER TABLE music ADD COLUMN filetype VARCHAR(50) AFTER file",
        "ALTER TABLE podcast ADD COLUMN filetype VARCHAR(50) AFTER file",
        "ALTER TABLE commondocument ADD COLUMN filetype VARCHAR(50) AFTER file",
        "ALTER TABLE subscription ADD COLUMN deleted_at DATETIME NULL",
        "ALTER TABLE article ADD COLUMN deleted_at DATETIME NULL",
        "ALTER TABLE trialpurchase ADD COLUMN eventDate DATETIME NULL",
        "ALTER TABLE trialpurchase ADD COLUMN firstPurchasePrice INT DEFAULT 0",
        "ALTER TABLE trialpurchase ADD COLUMN regularPrice INT DEFAULT 0",
        "ALTER TABLE trialpurchase ADD COLUMN account VARCHAR(200)",
        "ALTER TABLE trialpurchase ADD COLUMN note VARCHAR(3337)",
        "ALTER TABLE trialpurchase ADD COLUMN trialStatus VARCHAR(20) DEFAULT 'untried'",
        "ALTER TABLE trialpurchase ADD COLUMN purchaseStatus VARCHAR(30) DEFAULT 'not_purchased'",
        "ALTER TABLE reinstall ADD COLUMN `system` VARCHAR(10) DEFAULT 'win'",
        "ALTER TABLE reinstall ADD COLUMN softwareType VARCHAR(20) DEFAULT 'free'",
        "ALTER TABLE reinstall ADD COLUMN licenseType VARCHAR(20) DEFAULT 'none'",
        "ALTER TABLE reinstall ADD COLUMN serial VARCHAR(500)",
        "ALTER TABLE reinstall ADD COLUMN viewPassword VARCHAR(100)",
        "ALTER TABLE reinstall ADD COLUMN subscriptionSoftware TINYINT(1) DEFAULT 0",
        "ALTER TABLE reinstall ADD COLUMN subscriptionPeriod VARCHAR(20) DEFAULT ''",
        "ALTER TABLE reinstall ADD COLUMN subscriptionPrice INT DEFAULT 0",
        "ALTER TABLE reinstall ADD COLUMN subscriptionCurrency VARCHAR(10) DEFAULT 'TWD'",
        "ALTER TABLE reinstall ADD COLUMN site VARCHAR(2000)",
        "ALTER TABLE reinstall ADD COLUMN note VARCHAR(3337)",
        "ALTER TABLE quota ADD COLUMN serviceType VARCHAR(20) DEFAULT 'general'",
        "ALTER TABLE quota ADD COLUMN account VARCHAR(200)",
        "ALTER TABLE quota ADD COLUMN quotaRemaining INT DEFAULT 0",
        "ALTER TABLE quota ADD COLUMN quotaRatio INT DEFAULT 0",
        "ALTER TABLE quota ADD COLUMN quotaExpiry DATETIME NULL",
        "ALTER TABLE quota ADD COLUMN ratio5h INT DEFAULT 0",
        "ALTER TABLE quota ADD COLUMN expiry5h VARCHAR(10) DEFAULT ''",
        "ALTER TABLE quota ADD COLUMN ratioWeek INT DEFAULT 0",
        "ALTER TABLE quota ADD COLUMN expiryWeek VARCHAR(10) DEFAULT ''",
        "ALTER TABLE quota ADD COLUMN ratioMonth INT DEFAULT 0",
        "ALTER TABLE quota ADD COLUMN expiryMonth VARCHAR(10) DEFAULT ''",
        "ALTER TABLE quota ADD COLUMN note VARCHAR(3337)",
        "ALTER TABLE shoppinglist ADD COLUMN plannedDate DATETIME NULL",
        "ALTER TABLE shoppinglist ADD COLUMN price INT DEFAULT 0",
        "ALTER TABLE shoppinglist ADD COLUMN currency VARCHAR(10) DEFAULT 'TWD'",
        "ALTER TABLE shoppinglist ADD COLUMN quantity INT DEFAULT 1",
        "ALTER TABLE shoppinglist ADD COLUMN shop VARCHAR(100)",
        "ALTER TABLE shoppinglist ADD COLUMN pickupMethod VARCHAR(100)",
        "ALTER TABLE shoppinglist ADD COLUMN imageUrl VARCHAR(2000) DEFAULT ''",
        "ALTER TABLE shoppinglist ADD COLUMN account VARCHAR(200)",
        "ALTER TABLE shoppinglist ADD COLUMN note VARCHAR(3337)",
        "ALTER TABLE sitevisit ADD COLUMN count INT NOT NULL DEFAULT 0",
        "ALTER TABLE sitevisit ADD COLUMN lastVisitAt DATETIME NULL",
        "ALTER TABLE sitevisit ADD COLUMN currentStreak INT NOT NULL DEFAULT 0",
        "ALTER TABLE sitevisit ADD COLUMN lastVisitDate VARCHAR(10) DEFAULT ''",
        "ALTER TABLE menuusage ADD COLUMN moduleId VARCHAR(100) NOT NULL",
        "ALTER TABLE menuusage ADD COLUMN count INT NOT NULL DEFAULT 0",
        "ALTER TABLE menuusage ADD COLUMN lastUsedAt DATETIME NULL",
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
