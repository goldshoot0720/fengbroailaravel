<?php
/**
 * 手動價格紀錄伺服器 API — 對齊 Appwrite /api/manualprice。
 *
 *   GET    manual_price_api.php            → 列出全部商品（含 records）
 *   POST   manual_price_api.php            → 新增商品（body: name/currency/records|recordsJson/localId）
 *   POST   manual_price_api.php?id=xxx     → 更新（body 可只給部分欄位）
 *   GET    manual_price_api.php?action=delete&id=xxx → 刪除
 *
 * localStorage 改為「離線快取」；資料以本表為準（跨瀏覽器同步）。
 */
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getConnection();
fengbroEnsureManualPriceTable($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$id = trim((string) ($_GET['id'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));

if ($method === 'GET' && $action === 'delete') {
    if ($id === '') {
        jsonResponse(['error' => '缺少 id'], 400);
    }
    $stmt = $pdo->prepare('DELETE FROM manualprice WHERE id = ?');
    $stmt->execute([$id]);
    jsonResponse(['success' => true]);
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM manualprice ORDER BY updated_at DESC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $products = array_map('fengbroManualPriceToClientProduct', $rows);
    jsonResponse($products);
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = $raw ? json_decode($raw, true) : null;
    if (!is_array($input)) {
        $input = $_POST;
    }

    if ($id !== '') {
        // 更新：只處理有提供的欄位
        $existingStmt = $pdo->prepare('SELECT * FROM manualprice WHERE id = ?');
        $existingStmt->execute([$id]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            jsonResponse(['error' => '找不到該商品'], 404);
        }
        $merged = $existing;
        foreach (['name', 'currency', 'records', 'recordsJson', 'localId'] as $field) {
            if (array_key_exists($field, $input)) {
                $merged[$field] = $input[$field];
            }
        }
        if (array_key_exists('records', $input)) {
            unset($merged['recordsJson']);
        }
        try {
            $clean = fengbroSanitizeManualPriceRow($merged);
        } catch (InvalidArgumentException $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
        $clean['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        $values = [];
        foreach ($clean as $col => $value) {
            $sets[] = "`{$col}` = ?";
            $values[] = $value;
        }
        $values[] = $id;
        $stmt = $pdo->prepare('UPDATE manualprice SET ' . implode(',', $sets) . ' WHERE id = ?');
        $stmt->execute($values);

        $fetch = $pdo->prepare('SELECT * FROM manualprice WHERE id = ?');
        $fetch->execute([$id]);
        jsonResponse(fengbroManualPriceToClientProduct($fetch->fetch(PDO::FETCH_ASSOC)));
    }

    try {
        $clean = fengbroSanitizeManualPriceRow($input);
    } catch (InvalidArgumentException $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }

    // localId 重複時改用既有列（同瀏覽器離線快取遷移）
    $localId = trim((string) ($clean['localId'] ?? ''));
    if ($localId !== '') {
        $dup = $pdo->prepare('SELECT id FROM manualprice WHERE localId = ? LIMIT 1');
        $dup->execute([$localId]);
        $existingId = $dup->fetchColumn();
        if ($existingId) {
            $clean['updated_at'] = date('Y-m-d H:i:s');
            $sets = [];
            $values = [];
            foreach ($clean as $col => $value) {
                $sets[] = "`{$col}` = ?";
                $values[] = $value;
            }
            $values[] = $existingId;
            $stmt = $pdo->prepare('UPDATE manualprice SET ' . implode(',', $sets) . ' WHERE id = ?');
            $stmt->execute($values);
            $fetch = $pdo->prepare('SELECT * FROM manualprice WHERE id = ?');
            $fetch->execute([$existingId]);
            jsonResponse(fengbroManualPriceToClientProduct($fetch->fetch(PDO::FETCH_ASSOC)));
        }
    }

    $clean['id'] = generateUUID();
    $columns = array_map(static fn($col) => "`{$col}`", array_keys($clean));
    $placeholders = array_fill(0, count($clean), '?');
    $stmt = $pdo->prepare('INSERT INTO manualprice (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')');
    $stmt->execute(array_values($clean));
    jsonResponse(fengbroManualPriceToClientProduct($clean));
}

jsonResponse(['error' => 'Method Not Allowed'], 405);
