<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getAll($table, $orderBy = 'created_at DESC') {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$orderBy}");
    return $stmt->fetchAll();
}

function getById($table, $id) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function deleteById($table, $id) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    return $stmt->execute([$id]);
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('Y-m-d', strtotime($date));
}

function formatDateTime($date) {
    if (empty($date)) return '-';
    return date('Y-m-d H:i', strtotime($date));
}

function formatMoney($amount) {
    if (empty($amount)) return '$0';
    return '$' . number_format($amount);
}

function findExistingImportRecordId(PDO $pdo, string $table, array $data, array $identityColumns = []): ?string {
    $ignored = ['id', 'created_at', 'updated_at'];
    if (!$identityColumns && !empty($data['hash'])) {
        $identityColumns = ['hash'];
    }
    $columns = $identityColumns ?: array_values(array_diff(array_keys($data), $ignored));
    $where = [];
    $values = [];

    foreach ($columns as $column) {
        if (in_array($column, $ignored, true) || !array_key_exists($column, $data)) {
            continue;
        }
        $value = $data[$column];
        if ($value === '') {
            $value = null;
        }
        if ($value === null) {
            $where[] = "`{$column}` IS NULL";
        } else {
            $where[] = "`{$column}` = ?";
            $values[] = $value;
        }
    }

    if (!$where) {
        return null;
    }

    $sql = "SELECT id FROM `{$table}` WHERE " . implode(' AND ', $where) . " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    $id = $stmt->fetchColumn();
    return $id ? (string)$id : null;
}

function importRecordExists(PDO $pdo, string $table, array $identity): bool {
    return findExistingImportRecordId($pdo, $table, $identity, array_keys($identity)) !== null;
}
