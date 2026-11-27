<?php
$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Taipei'));
$weekStart = $today->modify('monday this week');
$history = ['lastdate1' => '最近一次', 'lastdate2' => '前一次', 'lastdate3' => '再前一次'];
$week = [];
for ($day = 0; $day < 7; $day++) {
    $week[$weekStart->modify("+$day days")->format('Y-m-d')] = 0;
}
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$items = [];
$loadError = false;
try {
    $items = getConnection()->query('SELECT id, name, note, link, photo, lastdate1, lastdate2, lastdate3
        FROM routine ORDER BY lastdate1 DESC, created_at DESC, id')->fetchAll();
} catch (PDOException $exception) {
    error_log('Unable to load routine list: '.$exception->getMessage());
    $loadError = true;
}
$calendarDate = static function ($value) use ($today) {
    if (!$value || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $parts)
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) return null;
    try {
        return (new DateTimeImmutable($value, $today->getTimezone()))->setTimezone($today->getTimezone())->setTime(0, 0);
    } catch (Exception $e) {
        return null;
    }
};
$counts = ['all' => count($items), 'dated' => 0, 'linked' => 0, 'photo' => 0];
foreach ($items as &$item) {
    $item['dates'] = [];
    foreach ($history as $field => $label) {
        $date = $calendarDate($item[$field]);
        $item['dates'][$field] = $date;
        $key = $date?->format('Y-m-d');
        if ($key !== null && isset($week[$key])) $week[$key]++;
    }
    $counts['dated'] += (int) ($item['dates']['lastdate1'] !== null);
    $counts['linked'] += (int) (trim((string) $item['link']) !== '');
    $counts['photo'] += (int) (trim((string) $item['photo']) !== '');
}
unset($item);
?>
<div class="routine-workspace" id="routineWorkspace">
    <header class="routine-header">
        <div><h1>鋒兄例行</h1><time datetime="<?= $today->format('Y-m-d') ?>"><?= $today->format('Y 年 n 月 j 日') ?></time></div>
        <?php if (!$loadError): ?>
        <section class="routine-week" aria-labelledby="routineWeekHeading">
            <div><h2 id="routineWeekHeading">本週完成紀錄</h2><span><?= array_sum($week) ?> 筆</span></div>
            <ol><?php foreach (array_keys($week) as $i => $key): ?>
                <li <?= $key === $today->format('Y-m-d') ? 'aria-current="date"' : '' ?>>
                    <time datetime="<?= $key ?>" title="<?= $key ?>"><?= ['一','二','三','四','五','六','日'][$i] ?></time>
                    <strong aria-label="<?= $key ?>，<?= $week[$key] ?> 筆"><?= $week[$key] ?></strong>
                </li>
            <?php endforeach; ?></ol>
        </section>
        <?php endif; ?>
    </header>
    <section class="routine-index" aria-labelledby="routineListHeading">
        <header class="routine-heading">
            <h2 id="routineListHeading">例行清單</h2>
            <span id="routineCount" role="status"><?= $loadError ? '' : count($items).' 項' ?></span>
            <button type="button" class="btn btn-primary" data-routine-action="add"><i class="fa-solid fa-plus" aria-hidden="true"></i> 新增例行事項</button>
        </header>
        <?php if ($loadError): ?>
            <p role="alert">例行事項載入失敗。<a href="index.php?page=routine">重新載入</a></p>
        <?php else: ?>
        <div class="routine-tools">
            <label class="routine-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" data-live-filter id="routineSearch" aria-label="搜尋名稱或備註" placeholder="搜尋名稱或備註">
            </label>
            <div class="routine-switch" role="group" aria-label="顯示方式">
                <button type="button" data-view="list" aria-pressed="true">列表</button>
                <button type="button" data-view="cards" aria-pressed="false">卡片</button>
            </div>
        </div>
        <div class="routine-filters" role="group" aria-label="篩選例行事項">
            <?php foreach (['all' => '全部', 'dated' => '有最近日期', 'linked' => '有連結', 'photo' => '有圖片'] as $key => $label): ?>
                <button type="button" data-filter="<?= $key ?>" aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"><?= $label ?> <span><?= $counts[$key] ?></span></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <details class="routine-management"><summary>管理工具</summary><div class="routine-management-actions">
            <a class="btn" href="export.php?table=routine">匯出 CSV</a>
            <?php $csvTable = 'routine'; include 'includes/csv_buttons.php'; ?>
            <?php include 'includes/batch-delete.php'; ?>
        </div></details>
        <?php if (!$loadError): ?>
        <ul class="routine-records" id="routineRecords">
            <?php foreach ($items as $item):
                $data = array_intersect_key($item, array_flip(['name','note','link','photo','lastdate1','lastdate2','lastdate3']));
                $rawLink = trim((string) $item['link']);
                if (preg_match('~^(?:[[:alnum:]-]+\.)+[[:alnum:]-]+(?::[0-9]+)?(?:[/#?][^\s]*)?$~uD', $rawLink)) {
                    $rawLink = 'https://'.$rawLink;
                }
                $url = fengbroSafeHttpUrl($rawLink);
                $photo = trim((string) $item['photo']);
                $name = $escape($item['name']);
                ?>
                <li class="routine-record" data-id="<?= $escape($item['id']) ?>" data-record="<?= $escape(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?>">
                    <div class="routine-identity">
                        <input type="checkbox" class="select-checkbox item-checkbox" data-id="<?= $escape($item['id']) ?>" aria-label="選取 <?= $name ?>" onchange="toggleSelectItem(this)">
                        <?php if ($photo !== ''): ?><img src="<?= $escape($photo) ?>" alt="" loading="lazy" width="48" height="48"><?php endif; ?>
                        <div><h3><?php if ($url): ?><a href="<?= $escape($url) ?>" target="_blank" rel="noopener noreferrer"><?= $name ?> <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a><?php else: ?><?= $name ?><?php endif; ?></h3>
                        <?php if ((string) $item['note'] !== ''): ?><p><?= $escape($item['note']) ?></p><?php endif; ?></div>
                    </div>
                    <dl class="routine-history">
                        <?php $previous = $today; foreach ($history as $field => $label):
                            $date = $item['dates'][$field];
                            $days = $date && $previous ? $date->diff($previous)->days : null;
                            ?>
                            <div><dt><?= $label ?></dt><dd>
                                <?php if ($date): ?><time datetime="<?= $date->format('Y-m-d') ?>"><?= $date->format('Y-m-d') ?></time>
                                <?php if ($days !== null): ?><small><?= $field !== 'lastdate1' ? "間隔 $days 天" : ($days === 0 ? '今天' : ($date > $today ? "$days 天後" : "距今 $days 天")) ?></small><?php endif; ?>
                                <?php else: ?><span>未記錄</span><?php endif; ?>
                            </dd></div>
                        <?php $previous = $date; endforeach; ?>
                    </dl>
                    <div class="routine-actions">
                        <button type="button" data-routine-action="shift" aria-label="日期遞移 <?= $name ?>" <?= $item['dates']['lastdate1'] ? '' : 'disabled' ?>>日期遞移</button>
                        <button type="button" data-routine-action="edit" aria-label="編輯 <?= $name ?>">編輯</button>
                        <button type="button" data-routine-action="delete" aria-label="刪除 <?= $name ?>">刪除</button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="routine-empty" id="routineEmpty" <?= $items ? 'hidden' : '' ?>><span><?= $items ? '' : '尚無例行事項。' ?></span>
            <button type="button" data-routine-action="<?= $items ? 'clear' : 'add' ?>"><?= $items ? '清除篩選' : '新增第一筆事項' ?></button>
        </p>
        <?php endif; ?>
    </section>
    <dialog id="routineEditor" aria-labelledby="routineEditorTitle">
        <form id="routineForm">
            <header><h2 id="routineEditorTitle">新增例行事項</h2><button type="button" data-routine-action="close" aria-label="關閉表單">關閉</button></header>
            <div class="routine-fields">
                <label>事項名稱（必填）<input name="name" required maxlength="100" list="routineNames" autofocus></label>
                <datalist id="routineNames"><?php foreach (array_unique(array_column($items, 'name')) as $name): ?><option value="<?= $escape($name) ?>"></option><?php endforeach; ?></datalist>
                <label>備註<textarea name="note" rows="2" maxlength="100"></textarea></label>
                <fieldset><legend>完成紀錄</legend><div class="routine-date-fields">
                    <?php foreach ($history as $field => $label): ?>
                        <div class="routine-date"><label><?= $label ?><input name="<?= $field ?>" type="date"></label>
                            <button type="button" data-clear-date="<?= $field ?>" aria-label="清空<?= $label ?>">清空</button>
                        </div>
                    <?php endforeach; ?>
                </div></fieldset>
                <label>相關連結<input name="link" inputmode="url" maxlength="500" placeholder="https://…"></label>
                <details class="routine-attachment"><summary>圖片附件</summary>
                    <label>圖片網址或路徑<input name="photo" maxlength="500"></label>
                    <label>上傳圖片<input type="file" name="attachment" accept="image/*"></label>
                    <img id="routinePreview" alt="圖片預覽" width="64" height="64" hidden>
                </details>
                <p id="routineError" role="alert"></p>
            </div>
            <footer><button type="button" data-routine-action="close">取消</button><button type="submit" class="btn btn-primary">儲存</button></footer>
        </form>
    </dialog>
</div>
<?php include 'includes/upload-progress.php'; ?>
