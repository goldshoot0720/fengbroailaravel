<?php

/**
 * 資料列的建立、更新、刪除。api.php 與 Laravel 進入點共用這支，回傳狀態與內容，不結束程序。
 *
 * @param  array<string, mixed>  $query
 * @param  array<string, mixed>  $input
 * @return array{status: int, body: mixed}
 */
function fengbroPerformRecordAction(array $query, array $input): array
{
    $action = (string) ($query['action'] ?? '');
    $table = (string) ($query['table'] ?? '');
    $allowedTables = ['subscription', 'food', 'notes', 'favorites', 'image', 'music', 'podcast', 'video', 'bank', 'routine', 'commondocument', 'commonaccount', 'article', 'trialpurchase', 'reinstall', 'quota', 'shoppinglist', 'udemy'];

    if (! in_array($table, $allowedTables, true)) {
        return ['status' => 400, 'body' => ['error' => '無效的資料表']];
    }

    $pdo = getConnection();
    $nowSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "datetime('now')" : 'NOW()';

    $ensureSchema = static function (bool $force = false) use ($pdo, $table): void {
        if ($force && function_exists('fengbroSchemaForget')) {
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
        } elseif ($table === 'udemy') {
            fengbroEnsureUdemyTable($pdo);
        } elseif ($table === 'bank') {
            require_once __DIR__.'/bank_helpers.php';
            fengbroEnsureBankColumns($pdo);
        }
        if (in_array($table, ['article', 'subscription'], true)) {
            fengbroEnsureSoftDeleteColumn($pdo, $table);
        }
        if (function_exists('fengbroEnsurePerformanceIndexes')) {
            fengbroEnsurePerformanceIndexes($pdo, [$table]);
        }
    };

    $run = static function (callable $work) use ($ensureSchema) {
        try {
            return $work();
        } catch (PDOException $e) {
            if (! fengbroIsMissingSchemaError($e)) {
                throw $e;
            }
            $ensureSchema(true);

            return $work();
        }
    };

    try {
        $ensureSchema();
    } catch (Throwable $e) {
        return ['status' => 500, 'body' => ['error' => 'Unable to initialize table: '.$e->getMessage()]];
    }

    $cleanColumns = static function (array $raw): array {
        $clean = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && preg_match('/^[A-Za-z0-9_]{1,64}$/', $key)) {
                $clean[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            }
        }

        return $clean;
    };

    try {
        switch ($action) {
            case 'list':
                if (in_array($table, ['article', 'subscription'], true)) {
                    $where = (($query['trash'] ?? '') === '1') ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
                    $data = $run(static function () use ($pdo, $table, $where) {
                        return $pdo->query("SELECT * FROM `{$table}` WHERE {$where} ORDER BY created_at DESC")->fetchAll();
                    });
                } else {
                    $data = $run(static function () use ($table) {
                        return getAll($table);
                    });
                }

                return ['status' => 200, 'body' => ['success' => true, 'data' => $data]];

            case 'get':
                return ['status' => 200, 'body' => ['success' => true, 'data' => getById($table, (string) ($query['id'] ?? ''))]];

            case 'create':
                if ($input === []) {
                    return ['status' => 400, 'body' => ['error' => '未收到資料，請確認表單已填寫']];
                }
                try {
                    $input = fengbroSanitizeRecordInput($table, $input);
                } catch (InvalidArgumentException $e) {
                    return ['status' => 400, 'body' => ['error' => $e->getMessage()]];
                }
                $input = $cleanColumns($input);
                $input['id'] = generateUUID();
                $columns = array_map(static fn ($col) => "`{$col}`", array_keys($input));
                $placeholders = array_fill(0, count($columns), '?');
                $sql = "INSERT INTO `{$table}` (".implode(',', $columns).') VALUES ('.implode(',', $placeholders).')';
                try {
                    $run(static function () use ($pdo, $sql, $input) {
                        return $pdo->prepare($sql)->execute(array_values($input));
                    });
                    $row = getById($table, $input['id']);

                    return ['status' => 200, 'body' => ['success' => true, 'id' => $input['id'], 'data' => $row ?: null]];
                } catch (PDOException $e) {
                    return ['status' => 500, 'body' => ['error' => $e->getMessage()]];
                }

            case 'update':
                $id = (string) ($query['id'] ?? '');
                unset($input['id'], $input['created_at']);
                try {
                    $input = fengbroSanitizeRecordInput($table, $input);
                } catch (InvalidArgumentException $e) {
                    return ['status' => 400, 'body' => ['error' => $e->getMessage()]];
                }
                $input = $cleanColumns($input);
                if ($input === []) {
                    return ['status' => 400, 'body' => ['error' => '未收到要更新的欄位']];
                }
                $sets = [];
                foreach (array_keys($input) as $col) {
                    $sets[] = "`{$col}` = ?";
                }
                $sql = "UPDATE `{$table}` SET ".implode(',', $sets).' WHERE id = ?';
                try {
                    $values = array_values($input);
                    $values[] = $id;
                    $run(static function () use ($pdo, $sql, $values) {
                        return $pdo->prepare($sql)->execute($values);
                    });

                    return ['status' => 200, 'body' => ['success' => true, 'data' => getById($table, $id) ?: null]];
                } catch (PDOException $e) {
                    return ['status' => 500, 'body' => ['error' => $e->getMessage()]];
                }

            case 'delete':
                $id = (string) ($query['id'] ?? '');
                try {
                    if (in_array($table, ['article', 'subscription'], true) && (($query['permanent'] ?? '') !== '1')) {
                        $stmt = $pdo->prepare("UPDATE `{$table}` SET deleted_at = {$nowSql} WHERE id = ?");
                        $stmt->execute([$id]);
                    } else {
                        deleteById($table, $id);
                    }

                    return ['status' => 200, 'body' => ['success' => true]];
                } catch (PDOException $e) {
                    return ['status' => 500, 'body' => ['error' => $e->getMessage()]];
                }

            case 'restore':
                if (! in_array($table, ['article', 'subscription'], true)) {
                    return ['status' => 400, 'body' => ['error' => 'This table does not support trash']];
                }
                $stmt = $pdo->prepare("UPDATE `{$table}` SET deleted_at = NULL WHERE id = ?");
                $stmt->execute([(string) ($query['id'] ?? '')]);

                return ['status' => 200, 'body' => ['success' => true]];

            case 'empty_trash':
                if (! in_array($table, ['article', 'subscription'], true)) {
                    return ['status' => 400, 'body' => ['error' => 'This table does not support trash']];
                }
                $deleted = $pdo->exec("DELETE FROM `{$table}` WHERE deleted_at IS NOT NULL");

                return ['status' => 200, 'body' => ['success' => true, 'deleted' => (int) $deleted]];

            default:
                return ['status' => 400, 'body' => ['error' => '無效的操作']];
        }
    } catch (Throwable $e) {
        return ['status' => 500, 'body' => ['error' => $e->getMessage()]];
    }
}

/**
 * @param  array<string, mixed>  $input
 * @return array<string, mixed>
 */
function fengbroSanitizeRecordInput(string $table, array $input): array
{
    if ($table === 'trialpurchase') {
        return fengbroSanitizeTrialPurchaseRow($input);
    }
    if ($table === 'reinstall') {
        return fengbroSanitizeReinstallRow($input);
    }
    if ($table === 'quota') {
        return fengbroSanitizeQuotaRow($input);
    }
    if ($table === 'shoppinglist') {
        return fengbroSanitizeShoppingItemRow($input);
    }
    if ($table === 'udemy') {
        return fengbroSanitizeUdemyRow($input);
    }

    return $input;
}
