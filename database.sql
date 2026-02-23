-- 鋒兄系統資料庫結構
-- 本地環境使用 fengxiong_db，遠端環境使用 feng_laravel

-- 本地與遠端皆使用 feng_laravel
CREATE DATABASE IF NOT EXISTS feng_laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE feng_laravel;

-- 使用者資料表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 訂閱資料表 (從 Appwrite 轉換)
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 食品資料表 (從 Appwrite 轉換)
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
);

-- 筆記資料表
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    category VARCHAR(50),
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 常用項目資料表
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500),
    description TEXT,
    icon VARCHAR(50),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 媒體檔案資料表（圖片、影片、音樂、文件）
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    type ENUM('image', 'video', 'music', 'document') NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    title VARCHAR(200),
    description TEXT,
    artist VARCHAR(100),
    album VARCHAR(100),
    duration INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 圖片資料表 (從 Appwrite 轉換)
CREATE TABLE IF NOT EXISTS image (
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
);

-- 音樂資料表 (從 Appwrite 轉換)
CREATE TABLE IF NOT EXISTS music (
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
);

-- 播客資料表 (從 Appwrite 轉換)
CREATE TABLE IF NOT EXISTS podcast (
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
);

-- 銀行資料表 (從 Appwrite 轉換)
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
);

-- 例行事項資料表 (從 Appwrite 轉換)
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
);

-- 系統設定資料表
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_setting (user_id, setting_key)
);

-- 通用文件資料表 (從 Appwrite 轉換)
CREATE TABLE IF NOT EXISTS commondocument (
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
);

-- 通用帳號資料表 (從 Appwrite 轉換)
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
);

-- 文章資料表 (從 Appwrite 轉換)
CREATE TABLE IF NOT EXISTS article (
    id VARCHAR(36) PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    content VARCHAR(1000),
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
);

-- Web Push 訂閱資料表
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint TEXT NOT NULL,
    auth VARCHAR(255) NOT NULL,
    p256dh VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_endpoint (endpoint(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VAPID 金鑰儲存於 settings 表，user_id = NULL
-- 由 push_send.php?action=init_vapid 自動插入：
--   setting_key = 'vapid_public_key'  → base64url 公鑰
--   setting_key = 'vapid_private_key' → EC 私鑰 PEM

-- 插入預設使用者
INSERT INTO users (username, email, password) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
