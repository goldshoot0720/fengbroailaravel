<?php
require_once fengbroRootPath('includes/notification_helpers.php');

$nowTaipei = new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei'));
$weekdays = ['日', '一', '二', '三', '四', '五', '六'];
$hour = (int) $nowTaipei->format('G');
$showFull = !empty($homeInitialFullView);
$pdo = getConnection();
fengbroEnsureSoftDeleteColumn($pdo, 'subscription');
$homeAlerts = notifBuildUnifiedDashboardAlerts($pdo);
$shortcuts = [
    ['subscription', 'fa-credit-card', '訂閱管理', '續訂與付款日期'],
    ['food', 'fa-boxes-stacked', '食品庫存', '數量與保存期限'],
    ['shoppinglist', 'fa-cart-shopping', '購物清單', '預計購買的品項'],
    ['notes', 'fa-note-sticky', '筆記', '記錄與查找內容'],
    ['bank', 'fa-building-columns', '銀行與票證', '帳戶、餘額與點數'],
    ['routine', 'fa-clock-rotate-left', '例行事項', '查看日常安排'],
];
$reminderGroups = [
    'expiredFoods' => ['food', '食品已過期'],
    'subscriptions' => ['subscription', '訂閱到期'],
    'foods' => ['food', '食品即將到期'],
    'trialPurchases' => ['trialpurchase', '試用／首購到期'],
    'quotas' => ['quota', '額度到期'],
    'shoppingItems' => ['shoppinglist', '預計購買'],
];
?>
<div class="home-workspace" data-initial-view="<?= $showFull ? 'full' : 'compact' ?>">
    <div class="content-header home-header">
        <div>
            <time datetime="<?= $nowTaipei->format('Y-m-d') ?>"><?= $nowTaipei->format('Y 年 n 月 j 日') ?> 星期<?= $weekdays[$nowTaipei->format('w')] ?></time>
            <h1>我的工作台</h1>
        </div>
        <nav class="home-view-toggle" aria-label="首頁顯示模式">
            <a href="index.php?page=home" data-home-view="compact" <?= $showFull ? '' : 'aria-current="page"' ?>>精簡首頁</a>
            <a href="index.php?page=dashboard" data-home-view="full" <?= $showFull ? 'aria-current="page"' : '' ?>>完整儀表</a>
        </nav>
    </div>

    <?php if ($hour < 7): ?>
        <p class="home-sleep-warning<?= $hour >= 3 ? ' is-late' : '' ?>" role="alert"><i class="fa-solid fa-moon" aria-hidden="true"></i> 請入睡</p>
    <?php endif; ?>

    <div id="homeViewCompact" <?= $showFull ? 'hidden' : '' ?>>
        <div class="home-panels">
            <nav class="home-panel" aria-labelledby="homeShortcutsTitle">
                <h2 id="homeShortcutsTitle">常用功能</h2>
                <div class="home-shortcuts">
                    <?php foreach ($shortcuts as [$target, $icon, $label, $hint]): ?>
                        <a class="home-shortcut" href="index.php?page=<?= $target ?>">
                            <i class="home-shortcut-icon fa-solid <?= $icon ?>" aria-hidden="true"></i>
                            <span><strong><?= $label ?></strong><small><?= $hint ?></small></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </nav>
            <section class="home-panel home-reminders" aria-labelledby="homeRemindersTitle">
                <h2 id="homeRemindersTitle">近期提醒</h2>
                <?php if (!array_filter($homeAlerts)): ?>
                    <p class="home-empty"><i class="fa-regular fa-circle-check" aria-hidden="true"></i> 目前沒有待處理的提醒</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($reminderGroups as $key => [$target, $label]): ?>
                            <?php
                            $items = $homeAlerts[$key];
                            if (!$items) continue;
                            $first = $items[0];
                            $date = $first['date'] ?? $first['expiryDate'] ?? '';
                            ?>
                            <li><a href="index.php?page=<?= $target ?>">
                                <span>
                                    <strong><?= $label ?></strong>
                                    <small><?= htmlspecialchars((string) $first['name']) ?>
                                        <?php if ($date !== ''): ?> <time><?= htmlspecialchars($date) ?></time><?php endif; ?>
                                        <?php if (count($items) > 1): ?> 等 <?= count($items) ?> 則提醒<?php endif; ?>
                                    </small>
                                </span>
                                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <section id="homeViewFull" aria-label="完整儀表" <?= $showFull ? '' : 'hidden' ?>>
        <div class="home-load-state" <?= $showFull ? 'hidden' : '' ?>>
            <span role="status">正在載入完整儀表…</span>
            <a href="index.php?page=dashboard" hidden>重新開啟完整儀表</a>
        </div>
        <div class="home-dashboard-content">
            <?php if ($showFull) {
                require __DIR__ . '/dashboard.php';
            } ?>
        </div>
    </section>
    <aside class="home-notices" aria-label="工具動態">
        <?php foreach (['tube' => '影片', 'finance' => '金融'] as $kind => $label): ?>
            <div class="home-notice" data-notice="<?= $kind ?>" hidden>
                <a href="index.php?page=tools&amp;tool=<?= $kind ?>"><strong></strong><span></span></a>
                <button type="button" aria-label="今天隱藏<?= $label ?>提醒" title="今日不再顯示">×</button>
            </div>
        <?php endforeach; ?>
    </aside>
    <script type="application/json" id="homeAlerts"><?= json_encode($homeAlerts, JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) ?></script>
</div>
<script defer src="assets/js/home.js?v=20261125"></script>
