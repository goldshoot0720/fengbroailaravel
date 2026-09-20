<?php
// Use real SQLite SQL for persistence; adapt only the MySQL table declaration.
class TubeTestDatabase extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        parent::exec('CREATE TABLE tubechannel (id TEXT PRIMARY KEY, sourceUrl TEXT UNIQUE, alias TEXT, created_at TEXT)');
    }
    public function exec(string $statement): int|false
    {
        return str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS tubechannel') ? 0 : parent::exec($statement);
    }
}
function getConnection() { return $GLOBALS['tubeTestDatabase']; }
function generateUUID() { return bin2hex(random_bytes(16)); }
function checkTube($condition, $message)
{
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}
$root = sys_get_temp_dir() . '/fengbro-tube-test-' . bin2hex(random_bytes(6));
mkdir($root . '/includes', 0755, true);
copy(__DIR__ . '/../includes/fengbro_tube.php', $root . '/includes/fengbro_tube.php');
require $root . '/includes/fengbro_tube.php';
$GLOBALS['tubeTestDatabase'] = new TubeTestDatabase();
$db = getConnection();
$removed = ['name' => 'SJdiao', 'url' => 'https://www.youtube.com/@SJdiao/videos'];
$kept = ['name' => 'Custom', 'url' => 'https://www.youtube.com/@my-custom-channel/videos'];
try {
    checkTube(!fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/channel/UCsjdiaoOther']), 'unrelated channel IDs must not be matched by substring');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@%53Jdiao/videos']), 'encoded handles must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@NeixianZhang/videos']), '張内咸脫口秀 must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@%E4%BF%AE%E4%BB%99%E8%80%85%E5%B0%8F%E7%83%A8/videos']), '修仙者小烨 must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@修炼者小烨/videos']), '修炼者小烨 must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@xiaoye1757/videos']), '修炼者小烨 current handle must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@StorytellerHK/videos']), 'StorytellerHK 說書客 must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@sunlao/videos']), '政經孫老師 must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@JunYuLan/videos']), '君語瀾 JunYuLan must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@quedaren/videos']), '雀大人 | Miss. Mi must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@ma-siku/videos']), '马司库 must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@informant510/videos']), '线人频道 Informant must be removed');
    checkTube(fengbroTubeIsRemovedChannel(['url' => 'https://www.youtube.com/@jilixiaoshimei/videos']), '吉利小师妹 must be removed');
    checkTube(
        !in_array('https://www.youtube.com/@NeixianZhang/videos', array_column(fengbroTubeDefaultChannels(), 'url'), true),
        '張内咸脫口秀 must not remain in defaults'
    );
    checkTube(
        !in_array('https://www.youtube.com/@%E4%BF%AE%E4%BB%99%E8%80%85%E5%B0%8F%E7%83%A8/videos', array_column(fengbroTubeDefaultChannels(), 'url'), true),
        '修仙者小烨 must not remain in defaults'
    );
    checkTube(
        !in_array('https://www.youtube.com/@xiaoye1757/videos', array_column(fengbroTubeDefaultChannels(), 'url'), true),
        '修炼者小烨 must not remain in defaults'
    );
    checkTube(
        !in_array('https://www.youtube.com/@StorytellerHK/videos', array_column(fengbroTubeDefaultChannels(), 'url'), true),
        'StorytellerHK 說書客 must not remain in defaults'
    );
    foreach ([
        'https://www.youtube.com/@sunlao/videos' => '政經孫老師',
        'https://www.youtube.com/@junyulan/videos' => '君語瀾 JunYuLan',
        'https://www.youtube.com/@quedaren/videos' => '雀大人 | Miss. Mi',
        'https://www.youtube.com/@ma-siku/videos' => '马司库',
        'https://www.youtube.com/@informant510/videos' => '线人频道 Informant',
        'https://www.youtube.com/@jilixiaoshimei/videos' => '吉利小师妹',
    ] as $url => $name) {
        checkTube(!in_array($url, array_column(fengbroTubeDefaultChannels(), 'url'), true), $name . ' must not remain in defaults');
    }
    fengbroTubeWriteCache(['tube_data_v6' => ['checkedAt' => time(), 'value' => ['channels' => [$removed]]]]);
    file_put_contents(fengbroTubeChannelsPath(), json_encode([$removed, $kept]));
    $insert = $db->prepare('INSERT INTO tubechannel (id, sourceUrl, alias) VALUES (?, ?, ?)');
    $insert->execute(['removed', $removed['url'], $removed['name']]);
    $insert->execute(['kept', $kept['url'], $kept['name']]);
    $channels = fengbroTubeChannels();
    checkTube(count($channels) === 1 && $channels[0]['name'] === 'Custom', 'retired channel must disappear from saved list');
    checkTube((int) $db->query("SELECT COUNT(*) FROM tubechannel WHERE id = 'removed'")->fetchColumn() === 0, 'retired row must be deleted from database');
    checkTube($db->query("SELECT id FROM tubechannel WHERE id = 'kept'")->fetchColumn() === 'kept', 'unrelated row must keep its identity');
    checkTube(!isset(fengbroTubeReadCache()['tube_data_v6']), 'removal must invalidate cached cards');
    checkTube(count(json_decode(file_get_contents(fengbroTubeChannelsPath()), true)) === 1, 'legacy JSON must be cleaned');
    fengbroTubeSaveChannels([]);
    checkTube(fengbroTubeChannels() === [], 'deleting last channel must remain empty');
    checkTube((int) $db->query('SELECT COUNT(*) FROM tubechannel')->fetchColumn() === 0, 'empty list must persist to database');
    fengbroTubeSaveChannels([$removed, $kept]);
    checkTube(count(fengbroTubeChannels()) === 1, 'imports must not resurrect retired channels');
    // A failed save must roll back, rather than partially deleting the list.
    $db->exec("CREATE TRIGGER reject_insert BEFORE INSERT ON tubechannel BEGIN SELECT RAISE(ABORT, 'test failure'); END");
    $failed = false;
    try { fengbroTubeSaveChannels([$kept]); } catch (Throwable $e) { $failed = true; }
    checkTube($failed, 'database failure must be reported');
    checkTube((int) $db->query('SELECT COUNT(*) FROM tubechannel')->fetchColumn() === 1, 'failed save must preserve database rows');
    $db->exec('DROP TRIGGER reject_insert');
    // Legacy-only installations must migrate the remaining channels and rewrite JSON.
    $db->exec('DELETE FROM tubechannel');
    file_put_contents(fengbroTubeChannelsPath(), json_encode([$removed, $kept]));
    checkTube(count(fengbroTubeChannels()) === 1, 'legacy import must exclude retired channels');
    checkTube((int) $db->query('SELECT COUNT(*) FROM tubechannel')->fetchColumn() === 1, 'legacy import must persist remaining channel');
    $db->exec('DELETE FROM tubechannel');
    file_put_contents(fengbroTubeChannelsPath(), json_encode([$removed]));
    checkTube(fengbroTubeChannels() === [], 'retired-only legacy list must stay empty');
    checkTube(fengbroTubeChannels() === [], 'retired-only legacy list must stay empty on next request');
    fengbroTubeResetChannels();
    checkTube(count(fengbroTubeChannels()) === count(fengbroTubeDefaultChannels()), 'reset must explicitly restore defaults');
    echo "PASS: database removal, legacy cleanup, empty list, import filtering, rollback, reset\n";
} finally {
    @unlink(fengbroTubeCachePath());
    @unlink(fengbroTubeChannelsPath());
    @rmdir($root . '/uploads/temp');
    @rmdir($root . '/uploads');
    @unlink($root . '/includes/fengbro_tube.php');
    @rmdir($root . '/includes');
    @rmdir($root);
}
