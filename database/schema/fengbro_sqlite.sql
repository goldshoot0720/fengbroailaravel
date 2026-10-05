CREATE TABLE IF NOT EXISTS subscription (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    site TEXT,
    price INTEGER,
    nextdate TEXT,
    note TEXT,
    account TEXT,
    currency TEXT,
    "continue" INTEGER DEFAULT 1,
    deleted_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS food (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    amount INTEGER,
    price INTEGER,
    shop TEXT,
    todate TEXT,
    photo TEXT,
    photohash TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS article (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    content TEXT,
    category TEXT,
    ref TEXT,
    newDate TEXT,
    url1 TEXT,
    url2 TEXT,
    url3 TEXT,
    file1 TEXT,
    file1name TEXT,
    file1type TEXT,
    file2 TEXT,
    file2name TEXT,
    file2type TEXT,
    file3 TEXT,
    file3name TEXT,
    file3type TEXT,
    deleted_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS image (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    file TEXT,
    filetype TEXT,
    note TEXT,
    ref TEXT,
    category TEXT,
    hash TEXT,
    cover TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS music (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    file TEXT,
    filetype TEXT,
    lyrics TEXT,
    note TEXT,
    ref TEXT,
    category TEXT,
    hash TEXT,
    language TEXT,
    cover TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS podcast (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    file TEXT,
    filetype TEXT,
    note TEXT,
    ref TEXT,
    category TEXT,
    hash TEXT,
    cover TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS video (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    file TEXT,
    filetype TEXT,
    note TEXT,
    ref TEXT,
    category TEXT,
    hash TEXT,
    cover TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS bank (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    deposit INTEGER,
    site TEXT,
    address TEXT,
    withdrawals INTEGER,
    transfer INTEGER,
    activity TEXT,
    card TEXT,
    account TEXT,
    category TEXT,
    note TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS routine (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    note TEXT,
    lastdate1 TEXT,
    lastdate2 TEXT,
    lastdate3 TEXT,
    link TEXT,
    photo TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS commondocument (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    file TEXT,
    filetype TEXT,
    note TEXT,
    ref TEXT,
    category TEXT,
    hash TEXT,
    cover TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS commonaccount (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    site01 TEXT,
    note01 TEXT,
    photohash TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    setting_key TEXT NOT NULL,
    setting_value TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS resend_notification_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_key TEXT NOT NULL,
    event_type TEXT NOT NULL,
    table_name TEXT NOT NULL,
    record_id TEXT NOT NULL,
    target_date TEXT NOT NULL,
    recipient_email TEXT NOT NULL,
    sent_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS trialpurchase (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    eventDate TEXT,
    firstPurchasePrice INTEGER DEFAULT 0,
    regularPrice INTEGER DEFAULT 0,
    account TEXT,
    note TEXT,
    trialStatus TEXT DEFAULT 'untried',
    purchaseStatus TEXT DEFAULT 'not_purchased',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS reinstall (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    "system" TEXT DEFAULT 'win',
    softwareType TEXT DEFAULT 'free',
    licenseType TEXT DEFAULT 'none',
    serial TEXT,
    viewPassword TEXT,
    subscriptionSoftware INTEGER DEFAULT 0,
    subscriptionPeriod TEXT DEFAULT '',
    subscriptionPrice INTEGER DEFAULT 0,
    subscriptionCurrency TEXT DEFAULT 'TWD',
    site TEXT,
    note TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS quota (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    serviceType TEXT DEFAULT 'general',
    account TEXT,
    quotaRemaining INTEGER DEFAULT 0,
    quotaRatio INTEGER DEFAULT 0,
    quotaExpiry TEXT,
    ratio5h INTEGER DEFAULT 0,
    expiry5h TEXT DEFAULT '',
    ratioWeek INTEGER DEFAULT 0,
    expiryWeek TEXT DEFAULT '',
    ratioMonth INTEGER DEFAULT 0,
    expiryMonth TEXT DEFAULT '',
    note TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS shoppinglist (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    plannedDate TEXT,
    price INTEGER DEFAULT 0,
    currency TEXT DEFAULT 'TWD',
    quantity INTEGER DEFAULT 1,
    shop TEXT,
    pickupMethod TEXT,
    imageUrl TEXT DEFAULT '',
    account TEXT,
    note TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS udemy (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    instructor TEXT,
    "language" TEXT,
    framework TEXT,
    technology TEXT,
    watchedLectures INTEGER DEFAULT 0,
    totalLectures INTEGER DEFAULT 0,
    courseUpdatedAt TEXT,
    totalHours REAL DEFAULT 0,
    completed INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS manualprice (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    currency TEXT DEFAULT 'TWD',
    recordsJson TEXT,
    localId TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS sitevisit (
    id TEXT PRIMARY KEY,
    count INTEGER NOT NULL DEFAULT 0,
    lastVisitAt TEXT,
    currentStreak INTEGER NOT NULL DEFAULT 0,
    lastVisitDate TEXT DEFAULT '',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS menuusage (
    id TEXT PRIMARY KEY,
    moduleId TEXT NOT NULL,
    count INTEGER NOT NULL DEFAULT 0,
    lastUsedAt TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
