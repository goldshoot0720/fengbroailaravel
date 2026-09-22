<?php
// 驗證 schema 快取：第一次會建表／補欄位，之後同請求與跨請求（標記檔）都不再碰資料庫。
define('DB_NAME', 'schema_cache_test_' . getmypid());
require __DIR__ . '/../includes/schema_cache.php';

final class FakeSchemaPdo extends PDO
{
    public array $log = [];
    public array $columns = ['demo' => ['id', 'name']];

    public function __construct() { parent::__construct('sqlite::memory:'); }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        $this->log[] = $statement;
        if (preg_match('/^ALTER TABLE `(\w+)` (.*)$/s', $statement, $m)) {
            preg_match_all('/ADD COLUMN\s+`?(\w+)`?/', $m[2], $cols);
            foreach ($cols[1] as $col) {
                if (in_array($col, $this->columns[$m[1]] ?? [], true)) {
                    throw new PDOException('Duplicate column');
                }
            }
            foreach ($cols[1] as $col) {
                $this->columns[$m[1]][] = $col;
            }
        }
        return 0;
    }

    #[\ReturnTypeWillChange]
    public function query($statement, $mode = null, ...$args)
    {
        $this->log[] = $statement;
        if (preg_match('/^SHOW COLUMNS FROM `(\w+)`/', $statement, $m)) {
            $sql = [];
            foreach ($this->columns[$m[1]] ?? [] as $c) {
                $sql[] = "SELECT " . parent::quote($c) . " AS Field";
            }
            return parent::query($sql ? implode(' UNION ALL ', $sql) : "SELECT 'x' AS Field WHERE 0");
        }
        return parent::query($statement);
    }
}

$fail = 0;
function check(string $label, bool $ok): void
{
    global $fail;
    echo ($ok ? '✓ ' : '✗ ') . $label . PHP_EOL;
    if (!$ok) $fail++;
}

fengbroSchemaForget();
$pdo = new FakeSchemaPdo();
$create = 'CREATE TABLE IF NOT EXISTS demo (id INT)';
$cols = ['name VARCHAR(10)', 'note TEXT', '`system` VARCHAR(10)'];

fengbroEnsureTableSchema($pdo, 'demo', $create, $cols);
check('第一次會執行 CREATE', in_array($create, $pdo->log, true));
$alters = array_values(array_filter($pdo->log, fn($s) => str_starts_with($s, 'ALTER')));
check('缺少的兩個欄位合併成一句 ALTER', count($alters) === 1 && str_contains($alters[0], 'note') && str_contains($alters[0], '`system`') && !str_contains($alters[0], 'name VARCHAR'));

$pdo->log = [];
fengbroEnsureTableSchema($pdo, 'demo', $create, $cols);
check('同請求第二次 0 次查詢', $pdo->log === []);

// 模擬新請求：清掉記憶，但保留標記檔
$memo = &fengbroSchemaMemo();
$memo = [];
fengbroEnsureTableSchema($pdo, 'demo', $create, $cols);
check('跨請求靠標記檔 0 次查詢', $pdo->log === [] || fengbroSchemaCacheDir() === null);

fengbroEnsureTableSchema($pdo, 'demo', $create, array_merge($cols, ['extra INT']));
check('定義改變會重新檢查並只補新欄位', end($pdo->log) === 'ALTER TABLE `demo` ADD COLUMN extra INT');

$pdo->log = [];
fengbroSchemaForget();
fengbroEnsureTableSchema($pdo, 'demo', $create, $cols);
check('forget 之後重新檢查，但不重複 ALTER', in_array($create, $pdo->log, true) && !array_filter($pdo->log, fn($s) => str_starts_with($s, 'ALTER')));

check('欄位名稱解析', fengbroColumnNameFromDefinition('`group` VARCHAR(20)') === 'group' && fengbroColumnNameFromDefinition('price INT') === 'price');
check('缺表錯誤判斷', fengbroIsMissingSchemaError(new PDOException("Table 'x.y' doesn't exist")));

fengbroSchemaForget();
echo $fail ? "\n{$fail} 項失敗\n" : "\n全部通過\n";
exit($fail ? 1 : 0);
