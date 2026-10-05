<?php

/**
 * 手動價格的讀寫。manual_price_api.php 與 Laravel 進入點共用。
 *
 * @param  array<string, mixed>  $query
 * @param  array<string, mixed>  $input
 * @return array{status: int, body: mixed}
 */
function fengbroPerformManualPriceAction(string $method, array $query, array $input): array
{
    $pdo = getConnection();
    fengbroEnsureManualPriceTable($pdo);

    $id = trim((string) ($query['id'] ?? ''));
    $action = trim((string) ($query['action'] ?? ''));
    $method = strtoupper($method);

    if ($method === 'GET' && $action === 'delete') {
        if ($id === '') {
            return ['status' => 400, 'body' => ['error' => '缺少 id']];
        }
        $stmt = $pdo->prepare('DELETE FROM manualprice WHERE id = ?');
        $stmt->execute([$id]);

        return ['status' => 200, 'body' => ['success' => true]];
    }

    if ($method === 'GET') {
        $rows = $pdo->query('SELECT * FROM manualprice ORDER BY updated_at DESC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ['status' => 200, 'body' => array_map('fengbroManualPriceToClientProduct', $rows)];
    }

    if ($method !== 'POST') {
        return ['status' => 405, 'body' => ['error' => 'Method Not Allowed']];
    }

    if ($id !== '') {
        $existingStmt = $pdo->prepare('SELECT * FROM manualprice WHERE id = ?');
        $existingStmt->execute([$id]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if (! $existing) {
            return ['status' => 404, 'body' => ['error' => '找不到該商品']];
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
            return ['status' => 400, 'body' => ['error' => $e->getMessage()]];
        }
        $clean['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        $values = [];
        foreach ($clean as $col => $value) {
            $sets[] = "`{$col}` = ?";
            $values[] = $value;
        }
        $values[] = $id;
        $stmt = $pdo->prepare('UPDATE manualprice SET '.implode(',', $sets).' WHERE id = ?');
        $stmt->execute($values);
        $fetch = $pdo->prepare('SELECT * FROM manualprice WHERE id = ?');
        $fetch->execute([$id]);

        return ['status' => 200, 'body' => fengbroManualPriceToClientProduct($fetch->fetch(PDO::FETCH_ASSOC) ?: [])];
    }

    try {
        $clean = fengbroSanitizeManualPriceRow($input);
    } catch (InvalidArgumentException $e) {
        return ['status' => 400, 'body' => ['error' => $e->getMessage()]];
    }

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
            $stmt = $pdo->prepare('UPDATE manualprice SET '.implode(',', $sets).' WHERE id = ?');
            $stmt->execute($values);
            $fetch = $pdo->prepare('SELECT * FROM manualprice WHERE id = ?');
            $fetch->execute([$existingId]);

            return ['status' => 200, 'body' => fengbroManualPriceToClientProduct($fetch->fetch(PDO::FETCH_ASSOC) ?: [])];
        }
    }

    $clean['id'] = generateUUID();
    $columns = array_map(static fn ($col) => "`{$col}`", array_keys($clean));
    $placeholders = array_fill(0, count($clean), '?');
    $stmt = $pdo->prepare('INSERT INTO manualprice ('.implode(',', $columns).') VALUES ('.implode(',', $placeholders).')');
    $stmt->execute(array_values($clean));

    return ['status' => 200, 'body' => fengbroManualPriceToClientProduct($clean)];
}
