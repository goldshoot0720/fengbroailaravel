<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
$pdo->exec("ALTER TABLE article
    MODIFY file1type VARCHAR(100),
    MODIFY file2type VARCHAR(100),
    MODIFY file3type VARCHAR(100)");
// 確認結果
$stmt = $pdo->query("SHOW COLUMNS FROM article");
foreach ($stmt->fetchAll() as $col) {
    if (strpos($col['Field'], 'type') !== false) {
        echo $col['Field'] . ' => ' . $col['Type'] . "\n";
    }
}
echo "DONE\n";
