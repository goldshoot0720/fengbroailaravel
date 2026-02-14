<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$table = $_GET['table'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$allowedTables = ['subscription', 'food', 'notes', 'favorites', 'image', 'music', 'podcast', 'bank', 'routine', 'commondocument', 'commonaccount', 'article'];

if (!in_array($table, $allowedTables)) {
    jsonResponse(['error' => '無效的資料表'], 400);
}

$pdo = getConnection();

switch ($action) {
    case 'list':
        $data = getAll($table);
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
        if (!$input || !is_array($input)) $input = $_POST;

        if (empty($input)) {
            jsonResponse(['error' => '未收到資料，請確認表單已填寫'], 400);
        }

        $input['id'] = generateUUID();
        $columns = array_map(function($col) { return "`{$col}`"; }, array_keys($input));
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
        if (!$input) $input = $_POST;

        unset($input['id']);
        unset($input['created_at']);

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
            deleteById($table, $id);
            jsonResponse(['success' => true]);
        } catch (PDOException $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['error' => '無效的操作'], 400);
}
