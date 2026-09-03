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
if ($table === 'trialpurchase') {
    fengbroEnsureTrialPurchaseTable($pdo);
}
if ($table === 'reinstall') {
    fengbroEnsureReinstallTable($pdo);
}
if ($table === 'quota') {
    fengbroEnsureQuotaTable($pdo);
}
if ($table === 'shoppinglist') {
    fengbroEnsureShoppingListTable($pdo);
}

if (in_array($table, ['article', 'subscription'], true)) {
    try {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `deleted_at` DATETIME NULL");
    } catch (PDOException $e) {
        if ((string) $e->getCode() !== '42S21' && stripos($e->getMessage(), 'Duplicate column') === false) {
            jsonResponse(['error' => 'Unable to initialize trash: ' . $e->getMessage()], 500);
        }
    }
}

switch ($action) {
    case 'list':
        if (in_array($table, ['article', 'subscription'], true)) {
            $where = ($_GET['trash'] ?? '') === '1' ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
            $data = $pdo->query("SELECT * FROM `{$table}` WHERE {$where} ORDER BY created_at DESC")->fetchAll();
        } else {
            $data = getAll($table);
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

        $input['id'] = generateUUID();
        $columns = array_map(function ($col) {
            return "`{$col}`"; }, array_keys($input));
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute(array_values($input));
            jsonResponse(['success' => true, 'id' => $input['id']]);
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

        $sets = [];
        foreach (array_keys($input) as $col) {
            $sets[] = "`{$col}` = ?";
        }

        $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);

        try {
            $values = array_values($input);
            $values[] = $id;
            $stmt->execute($values);
            jsonResponse(['success' => true]);
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
