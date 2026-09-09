<?php
$root = sys_get_temp_dir() . '/fengbro-tube-test-' . bin2hex(random_bytes(6));
mkdir($root . '/includes', 0755, true);
copy(__DIR__ . '/../includes/fengbro_tube.php', $root . '/includes/fengbro_tube.php');
require $root . '/includes/fengbro_tube.php';
try {
    fengbroTubeWriteCache([
        'tube_data_v6' => ['checkedAt' => time(), 'value' => ['channels' => [['name' => 'SJdiao']]]],
        'channel_metadata' => ['keep' => true],
    ]);
    fengbroTubeClearDataCache();
    $cache = fengbroTubeReadCache();
    if (isset($cache['tube_data_v6'])) {
        throw new RuntimeException('FAIL: removed channel remains in active Tube data cache');
    }
    if (($cache['channel_metadata']['keep'] ?? false) !== true) {
        throw new RuntimeException('FAIL: unrelated channel metadata was removed');
    }
    echo "PASS: active Tube data cleared; channel metadata preserved\n";
} finally {
    @unlink(fengbroTubeCachePath());
    @rmdir($root . '/uploads/temp');
    @rmdir($root . '/uploads');
    @unlink($root . '/includes/fengbro_tube.php');
    @rmdir($root . '/includes');
    @rmdir($root);
}
