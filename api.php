<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$table = $_GET['table'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$allowedTables = ['subscription', 'food', 'notes', 'favorites', 'image', 'music', 'podcast', 'video', 'bank', 'routine', 'commondocument', 'commonaccount', 'article', 'trialpurchase', 'reinstall', 'quota', 'shoppinglist'];

if (!in_array($table, $allowedTables)) {
    jsonResponse(['error' => '無效的資料表'], 400);
}

$pdo = getConnection();

/** 只保留合法欄位名稱（英數、底線），避免欄位名稱被拿來注入 SQL。 */
function fengbroApiCleanColumns(array $input): array
{
    $clean = [];
    foreach ($input as $key => $value) {
        if (is_string($key) && preg_match('/^[A-Za-z0-9_]{1,64}$/', $key)) {
            $clean[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        }
    }
    return $clean;
}

/**
 * 確保資料表結構（有 schema 快取，正常情況 0 次查詢）。
 * $force = true 時清快取重做：用在查詢遇到「資料表／欄位不存在」時自我修復。
 */
$fengbroApiEnsureSchema = static function (bool $force = false) use ($pdo, $table): void {
    if ($force) {
        fengbroSchemaForget();
    }
    if ($table === 'trialpurchase') {
        fengbroEnsureTrialPurchaseTable($pdo);
    } elseif ($table === 'reinstall') {
        fengbroEnsureReinstallTable($pdo);
    } elseif ($table === 'quota') {
        fengbroEnsureQuotaTable($pdo);
    } elseif ($table === 'shoppinglist') {
        fengbroEnsureShoppingListTable($pdo);
    } elseif ($table === 'bank') {
        require_once __DIR__ . '/includes/bank_helpers.php';
        fengbroEnsureBankColumns($pdo);
    }
    if (in_array($table, ['article', 'subscription'], true)) {
        fengbroEnsureSoftDeleteColumn($pdo, $table);
    }
    fengbroEnsurePerformanceIndexes($pdo, [$table]);
};

/** 執行一段資料庫操作；若因結構缺漏失敗，修復後重試一次。 */
$fengbroApiRun = static function (callable $work) use ($fengbroApiEnsureSchema) {
    try {
        return $work();
    } catch (PDOException $e) {
        if (!fengbroIsMissingSchemaError($e)) {
            throw $e;
        }
        $fengbroApiEnsureSchema(true);
        return $work();
    }
};

try {
    $fengbroApiEnsureSchema();
} catch (Throwable $e) {
    jsonResponse(['error' => 'Unable to initialize table: ' . $e->getMessage()], 500);
}

switch ($action) {
    case 'list':
        if (in_array($table, ['article', 'subscription'], true)) {
            $where = ($_GET['trash'] ?? '') === '1' ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
            $data = $fengbroApiRun(static function () use ($pdo, $table, $where) {
                return $pdo->query("SELECT * FROM `{$table}` WHERE {$where} ORDER BY created_at DESC")->fetchAll();
            });
        } else {
            $data = $fengbroApiRun(static function () use ($table) {
                return getAll($table);
            });
        }
        jsonResponse(['success' => true, 'data' => $data]);
        break;

    case 'get':
        $id = $_GET['id'] ?? '';
        $data = getById($table, $id);
        jsonResponse(['success' => true, 'data' => $data]);
        break;

    case 'create':
        $rawInput = file_get_contents('php://input');
        $input = $rawInput ? json_decode($rawInput, true) : null;
        if (!$input || !is_array($input))
            $input = $_POST;

        if (empty($input)) {
            jsonResponse(['error' => '未收到資料，請確認表單已填寫'], 400);
        }

        try {
            if ($table === 'trialpurchase') {
                $input = fengbroSanitizeTrialPurchaseRow($input);
            } elseif ($table === 'reinstall') {
                $input = fengbroSanitizeReinstallRow($input);
            } elseif ($table === 'quota') {
                $input = fengbroSanitizeQuotaRow($input);
            } elseif ($table === 'shoppinglist') {
                $input = fengbroSanitizeShoppingItemRow($input);
            }
        } catch (InvalidArgumentException $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }

        $input = fengbroApiCleanColumns($input);
        $input['id'] = generateUUID();
        $columns = array_map(function ($col) {
            return "`{$col}`"; }, array_keys($input));
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        try {
            $fengbroApiRun(static function () use ($pdo, $sql, $input) {
                return $pdo->prepare($sql)->execute(array_values($input));
            });
            $row = getById($table, $input['id']);
            jsonResponse(['success' => true, 'id' => $input['id'], 'data' => $row ?: null]);
        } catch (PDOException $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;

    case 'update':
        $id = $_GET['id'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input)
            $input = $_POST;

        unset($input['id']);
        unset($input['created_at']);

        try {
            if ($table === 'trialpurchase') {
                $input = fengbroSanitizeTrialPurchaseRow($input);
            } elseif ($table === 'reinstall') {
                $input = fengbroSanitizeReinstallRow($input);
            } elseif ($table === 'quota') {
                $input = fengbroSanitizeQuotaRow($input);
            } elseif ($table === 'shoppinglist') {
                $input = fengbroSanitizeShoppingItemRow($input);
            }
        } catch (InvalidArgumentException $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }

        $input = is_array($input) ? fengbroApiCleanColumns($input) : [];
        if (!$input) {
            jsonResponse(['error' => '未收到要更新的欄位'], 400);
        }

        $sets = [];
        foreach (array_keys($input) as $col) {
            $sets[] = "`{$col}` = ?";
        }

        $sql = "UPDATE `{$table}` SET " . implode(',', $sets) . " WHERE id = ?";
        try {
            $values = array_values($input);
            $values[] = $id;
            $fengbroApiRun(static function () use ($pdo, $sql, $values) {
                return $pdo->prepare($sql)->execute($values);
            });
            // 回傳最新一筆，前端可直接就地更新畫面而不必整頁重新載入。
            $row = getById($table, $id);
            jsonResponse(['success' => true, 'data' => $row ?: null]);
        } catch (PDOException $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;

    case 'delete':
        $id = $_GET['id'] ?? '';
        try {
            if (in_array($table, ['article', 'subscription'], true) && ($_GET['permanent'] ?? '') !== '1') {
                $stmt = $pdo->prepare("UPDATE `{$table}` SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                deleteById($table, $id);
            }
            jsonResponse(['success' => true]);
        } catch (PDOException $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;

    case 'restore':
        if (!in_array($table, ['article', 'subscription'], true)) {
            jsonResponse(['error' => 'This table does not support trash'], 400);
        }
        $stmt = $pdo->prepare("UPDATE `{$table}` SET deleted_at = NULL WHERE id = ?");
        $stmt->execute([$_GET['id'] ?? '']);
        jsonResponse(['success' => true]);
        break;

    case 'empty_trash':
        if (!in_array($table, ['article', 'subscription'], true)) {
            jsonResponse(['error' => 'This table does not support trash'], 400);
        }
        $deleted = $pdo->exec("DELETE FROM `{$table}` WHERE deleted_at IS NOT NULL");
        jsonResponse(['success' => true, 'deleted' => (int) $deleted]);
        break;

    default:
        jsonResponse(['error' => '無效的操作'], 400);
}
