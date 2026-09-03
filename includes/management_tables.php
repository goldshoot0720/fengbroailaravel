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
            system VARCHAR(10) DEFAULT 'win',
            softwareType VARCHAR(20) DEFAULT 'free',
            licenseType VARCHAR(20) DEFAULT 'none',
            serial VARCHAR(500),
            viewPassword VARCHAR(100),
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
        "system VARCHAR(10) DEFAULT 'win'",
        "softwareType VARCHAR(20) DEFAULT 'free'",
        "licenseType VARCHAR(20) DEFAULT 'none'",
        "serial VARCHAR(500)",
        "viewPassword VARCHAR(100)",
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
           AND LOWER(TRIM(IFNULL(system, 'win'))) = LOWER(?)
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
        '軟體網站' => 'site',
        '網站' => 'site',
    ];
}
