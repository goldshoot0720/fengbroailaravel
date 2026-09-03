-- 鋒兄系統資料庫結構（對齊 install.php 與實際頁面）
-- 實際庫名以 config/database.php 為準；此檔預設 feng_laravel。
CREATE DATABASE IF NOT EXISTS feng_laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE feng_laravel;

-- Resend 收件備援會讀取 users.email
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS food (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 筆記頁使用 article（非 notes）
CREATE TABLE IF NOT EXISTS article (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS image (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS music (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS podcast (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS video (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS routine (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commondocument (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 常用帳號頁使用 commonaccount（非 favorites）
CREATE TABLE IF NOT EXISTS commonaccount (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    site01 VARCHAR(100),
    site02 VARCHAR(100),
    site03 VARCHAR(100),
    site04 VARCHAR(100),
    site05 VARCHAR(100),
    site06 VARCHAR(100),
    site07 VARCHAR(100),
    site08 VARCHAR(100),
    site09 VARCHAR(100),
    site10 VARCHAR(100),
    site11 VARCHAR(100),
    site12 VARCHAR(100),
    site13 VARCHAR(100),
    site14 VARCHAR(100),
    site15 VARCHAR(100),
    site16 VARCHAR(100),
    site17 VARCHAR(100),
    site18 VARCHAR(100),
    site19 VARCHAR(100),
    site20 VARCHAR(100),
    site21 VARCHAR(100),
    site22 VARCHAR(100),
    site23 VARCHAR(100),
    site24 VARCHAR(100),
    site25 VARCHAR(100),
    site26 VARCHAR(100),
    site27 VARCHAR(100),
    site28 VARCHAR(100),
    site29 VARCHAR(100),
    site30 VARCHAR(100),
    site31 VARCHAR(100),
    site32 VARCHAR(100),
    site33 VARCHAR(100),
    site34 VARCHAR(100),
    site35 VARCHAR(100),
    site36 VARCHAR(100),
    site37 VARCHAR(100),
    note01 VARCHAR(100),
    note02 VARCHAR(100),
    note03 VARCHAR(100),
    note04 VARCHAR(100),
    note05 VARCHAR(100),
    note06 VARCHAR(100),
    note07 VARCHAR(100),
    note08 VARCHAR(100),
    note09 VARCHAR(100),
    note10 VARCHAR(100),
    note11 VARCHAR(100),
    note12 VARCHAR(100),
    note13 VARCHAR(100),
    note14 VARCHAR(100),
    note15 VARCHAR(100),
    note16 VARCHAR(100),
    note17 VARCHAR(100),
    note18 VARCHAR(100),
    note19 VARCHAR(100),
    note20 VARCHAR(100),
    note21 VARCHAR(100),
    note22 VARCHAR(100),
    note23 VARCHAR(100),
    note24 VARCHAR(100),
    note25 VARCHAR(100),
    note26 VARCHAR(100),
    note27 VARCHAR(100),
    note28 VARCHAR(100),
    note29 VARCHAR(100),
    note30 VARCHAR(100),
    note31 VARCHAR(100),
    note32 VARCHAR(100),
    note33 VARCHAR(100),
    note34 VARCHAR(100),
    note35 VARCHAR(100),
    note36 VARCHAR(100),
    note37 VARCHAR(100),
    photohash VARCHAR(256),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 系統設定：user_id 為 NULL（VAPID / Resend / BigGo）
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_setting (user_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resend_notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(191) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id VARCHAR(64) NOT NULL,
    target_date DATE NOT NULL,
    recipient_email VARCHAR(191) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_resend_event (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint TEXT NOT NULL,
    auth VARCHAR(255) NOT NULL,
    p256dh VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_endpoint (endpoint(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tool_price_history (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tool_phone_product_history (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trialpurchase (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reinstall (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    system VARCHAR(10) DEFAULT 'win',
    softwareType VARCHAR(20) DEFAULT 'free',
    licenseType VARCHAR(20) DEFAULT 'none',
    serial VARCHAR(500),
    viewPassword VARCHAR(100),
    site VARCHAR(2000),
    note VARCHAR(3337),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VAPID 金鑰儲存於 settings，user_id = NULL
-- 由 push_send.php?action=init_vapid 自動插入：
--   setting_key = 'vapid_public_key'  → base64url 公鑰
--   setting_key = 'vapid_private_key' → EC 私鑰 PEM

INSERT INTO users (username, email, password) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
