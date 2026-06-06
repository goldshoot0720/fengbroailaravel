<?php
$pageTitle = '鋒兄工具';
require_once __DIR__ . '/../includes/fengbro_tube.php';
require_once __DIR__ . '/../includes/fengbro_finance.php';

$toolSubpage = $_GET['tool'] ?? 'price';
$toolSubpage = in_array($toolSubpage, ['price', 'tube', 'finance'], true) ? $toolSubpage : 'price';
if ($toolSubpage === 'tube' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tube_action'] ?? '') !== '') {
    $channels = fengbroTubeChannels();
    $action = (string) ($_POST['tube_action'] ?? '');
    $index = isset($_POST['channel_index']) ? (int) $_POST['channel_index'] : -1;
    $channel = [
        'name' => trim((string) ($_POST['channel_name'] ?? '')),
        'url' => trim((string) ($_POST['channel_url'] ?? '')),
    ];

    if ($action === 'reset') {
        fengbroTubeResetChannels();
    } elseif ($action === 'delete' && isset($channels[$index])) {
        array_splice($channels, $index, 1);
        fengbroTubeSaveChannels($channels);
    } elseif ($action === 'save' && $channel['url'] !== '') {
        if ($index >= 0 && isset($channels[$index])) {
            $channels[$index] = $channel;
        } else {
            $channels[] = $channel;
        }
        fengbroTubeSaveChannels($channels);
    }

    header('Location: index.php?page=tools&tool=tube&refresh=1#tube-channel-manager');
    exit;
}
$tubeData = $toolSubpage === 'tube' ? fengbroTubeGetData(isset($_GET['refresh'])) : null;
$tubeChannels = $toolSubpage === 'tube' ? fengbroTubeChannels() : [];
$financeData = $toolSubpage === 'finance' ? fengbroFinanceGetData(isset($_GET['refresh'])) : null;
?>

<div class="content-header">
    <div>
        <h1>鋒兄工具</h1>
        <p style="margin-top: 8px; color: var(--muted-text);">比價、手機通路查詢與常用工具入口。</p>
    </div>
</div>

<div class="content-body">
    <div class="tools-subnav">
        <a class="tools-subnav-link <?php echo $toolSubpage === 'price' ? 'active' : ''; ?>" href="index.php?page=tools&tool=price">
            <i class="fa-solid fa-tags"></i> 鋒兄比價
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'tube' ? 'active' : ''; ?>" href="index.php?page=tools&tool=tube">
            <i class="fa-brands fa-youtube"></i> 鋒兄tube
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'finance' ? 'active' : ''; ?>" href="index.php?page=tools&tool=finance">
            <i class="fa-solid fa-chart-line"></i> 鋒兄金融
        </a>
    </div>

    <?php if ($toolSubpage === 'tube'): ?>
        <section class="card tube-overview">
            <div class="tube-overview-copy">
                <h3 class="card-title"><i class="fa-brands fa-youtube"></i> 鋒兄tube</h3>
                <p>集中查看指定 YouTube 頻道最新影片，每個頻道最多顯示 10 部。首頁會提示 3 天內的新影片。</p>
                <span>最後檢查：<?php echo htmlspecialchars($tubeData['checkedAt'] ?? '-'); ?></span>
            </div>
            <a class="btn btn-ghost" href="index.php?page=tools&tool=tube&refresh=1">
                <i class="fa-solid fa-rotate-right"></i> 重新檢查
            </a>
        </section>

        <?php if (!empty($tubeData['newVideos'])): ?>
            <section class="tube-new-alert">
                <i class="fa-solid fa-bell"></i>
                <div>
                    <strong>3 天內有 <?php echo count($tubeData['newVideos']); ?> 部新影片</strong>
                    <p>首頁也會顯示這個提醒。</p>
                </div>
            </section>
        <?php endif; ?>

        <section id="tube-channel-manager" class="card tube-channel-manager">
            <div class="tube-manager-head">
                <div>
                    <h3 class="card-title">頻道管理</h3>
                    <p>可編輯頻道別名與網址。別名留空時，預設使用 YouTube 原頻道名稱。</p>
                </div>
                <form method="post" onsubmit="return confirm('還原預設頻道？目前自訂清單會被清除。');">
                    <button id="tubeChannelManagerToggle" type="button" class="btn btn-ghost tube-manager-toggle" aria-expanded="true" aria-controls="tubeChannelManagerBody" onclick="toggleTubeChannelManager()">
                        <i class="fa-solid fa-chevron-up"></i> 收合
                    </button>
                    <input type="hidden" name="tube_action" value="reset">
                    <button type="submit" class="btn btn-ghost">
                        <i class="fa-solid fa-rotate-left"></i> 還原預設
                    </button>
                </form>
            </div>
            <div id="tubeChannelManagerBody" class="tube-channel-manager-body">
            <form method="post" class="tube-channel-form">
                <input type="hidden" name="tube_action" value="save">
                <input type="hidden" id="tubeChannelIndex" name="channel_index" value="-1">
                <input id="tubeChannelName" type="text" name="channel_name" class="form-control" placeholder="頻道別名（留空使用原頻道名稱）">
                <input id="tubeChannelUrl" type="text" name="channel_url" class="form-control" placeholder="頻道網址 / @handle" required>
                <button id="tubeChannelSubmit" type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-plus"></i> 儲存頻道
                </button>
                <button id="tubeChannelCancel" type="button" class="btn btn-ghost" style="display:none;" onclick="cancelTubeChannelEdit()">取消編輯</button>
            </form>
            <div class="tube-channel-admin-list">
                <?php foreach ($tubeChannels as $idx => $adminChannel): ?>
                    <?php $displayName = trim((string) ($adminChannel['name'] ?? '')); ?>
                    <div class="tube-channel-admin-item">
                        <div>
                            <strong><?php echo htmlspecialchars($displayName !== '' ? $displayName : '使用原頻道名稱'); ?></strong>
                            <span><?php echo htmlspecialchars($adminChannel['url'] ?? ''); ?></span>
                        </div>
                        <div class="tube-channel-admin-actions">
                            <button type="button" class="btn btn-sm" onclick="editTubeChannel(<?php echo (int) $idx; ?>, <?php echo json_encode($displayName, JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode($adminChannel['url'] ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>)">編輯</button>
                            <form method="post" onsubmit="return confirm('刪除此頻道？');">
                                <input type="hidden" name="tube_action" value="delete">
                                <input type="hidden" name="channel_index" value="<?php echo (int) $idx; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
        </section>

        <div class="tube-channel-grid">
            <?php foreach (($tubeData['channels'] ?? []) as $channel): ?>
                <section class="card tube-channel-card">
                    <div class="tube-channel-head">
                        <div>
                            <h3 class="card-title tube-channel-title">
                                <span><?php echo $channel['name']; ?></span>
                                <?php if (!empty($channel['updateBadge']['label'])): ?>
                                    <span class="tube-update-badge" title="<?php echo htmlspecialchars($channel['updateBadge']['title'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($channel['updateBadge']['label']); ?>
                                        <?php if (!empty($channel['updateBadge']['value'])): ?>
                                            <?php echo htmlspecialchars($channel['updateBadge']['value']); ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <a href="<?php echo htmlspecialchars($channel['url']); ?>" target="_blank" rel="noopener">開啟頻道</a>
                        </div>
                        <span><?php echo count($channel['videos'] ?? []); ?> 部</span>
                    </div>
                    <?php if (!empty($channel['error'])): ?>
                        <p class="tube-empty"><?php echo htmlspecialchars($channel['error']); ?></p>
                    <?php elseif (empty($channel['videos'])): ?>
                        <p class="tube-empty">暫時抓不到影片，稍後可重新檢查。</p>
                    <?php else: ?>
                        <div class="tube-video-list">
                            <?php foreach ($channel['videos'] as $video): ?>
                                <a class="tube-video-item <?php echo !empty($video['isNew']) ? 'is-new' : ''; ?>" href="<?php echo htmlspecialchars($video['url']); ?>" target="_blank" rel="noopener">
                                    <?php if (!empty($video['thumbnail'])): ?>
                                        <img src="<?php echo htmlspecialchars($video['thumbnail']); ?>" alt="">
                                    <?php endif; ?>
                                    <span>
                                        <strong><?php echo htmlspecialchars($video['title']); ?></strong>
                                        <small><?php echo htmlspecialchars($video['publishedText']); ?><?php echo !empty($video['isNew']) ? ' · 新影片' : ''; ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php elseif ($toolSubpage === 'finance'): ?>
        <section class="card finance-overview">
            <div class="finance-overview-copy">
                <h3 class="card-title"><i class="fa-solid fa-chart-line"></i> 鋒兄金融</h3>
                <p>集中追蹤 CNBC、Yahoo 股市與 Multpl 參考來源的主要市場指標，若目前值觸及高低點會標註創新高或創新低。</p>
                <span>最後檢查：<?php echo htmlspecialchars($financeData['checkedAt'] ?? '-'); ?> · 來源：<?php echo htmlspecialchars($financeData['source'] ?? 'CNBC / Yahoo股市 / Multpl'); ?></span>
            </div>
            <a class="btn btn-ghost" href="index.php?page=tools&tool=finance&refresh=1">
                <i class="fa-solid fa-rotate-right"></i> 重新檢查
            </a>
        </section>

        <div class="finance-grid">
            <?php foreach (($financeData['quotes'] ?? []) as $quote): ?>
                <?php
                $changeText = trim(($quote['change'] ?? '') . ' ' . ($quote['changePercent'] ?? ''));
                $changeNumber = isset($quote['change']) ? (float) str_replace(',', '', (string) $quote['change']) : 0;
                $tone = $changeNumber > 0 ? 'up' : ($changeNumber < 0 ? 'down' : 'flat');
                $statusClass = ($quote['status'] ?? '') === '創新高' ? 'high' : ((($quote['status'] ?? '') === '創新低') ? 'low' : 'breakout');
                ?>
                <section class="finance-card <?php echo $tone; ?>">
                    <div class="finance-card-head">
                        <div>
                            <span class="finance-group"><?php echo htmlspecialchars($quote['group']); ?></span>
                            <h3><?php echo htmlspecialchars($quote['name']); ?></h3>
                            <a href="<?php echo htmlspecialchars($quote['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($quote['symbol']); ?> · <?php echo htmlspecialchars($quote['source'] ?? ''); ?></a>
                        </div>
                        <?php if (!empty($quote['status'])): ?>
                            <strong class="finance-status <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($quote['status']); ?>
                            </strong>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($quote['error'])): ?>
                        <p class="finance-empty"><?php echo htmlspecialchars($quote['error']); ?></p>
                    <?php else: ?>
                        <div class="finance-value-row">
                            <span><?php echo htmlspecialchars($quote['valueLabel']); ?></span>
                            <strong><?php echo htmlspecialchars($quote['value']); ?></strong>
                        </div>
                        <div class="finance-change <?php echo $tone; ?>">
                            <?php echo $changeText !== '' ? htmlspecialchars($changeText) : '變動暫無資料'; ?>
                        </div>
                        <div class="finance-stats">
                            <span>Open <b><?php echo htmlspecialchars($quote['open'] ?: '-'); ?></b></span>
                            <span>Day High <b><?php echo htmlspecialchars($quote['dayHigh'] ?: '-'); ?></b></span>
                            <span>Day Low <b><?php echo htmlspecialchars($quote['dayLow'] ?: '-'); ?></b></span>
                            <span>Prev Close <b><?php echo htmlspecialchars($quote['prevClose'] ?: '-'); ?></b></span>
                            <span>52W High <b><?php echo htmlspecialchars($quote['high52'] ?: '-'); ?></b></span>
                            <span>52W Low <b><?php echo htmlspecialchars($quote['low52'] ?: '-'); ?></b></span>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        <section class="card">
            <div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px;">
                <div style="width: 46px; height: 46px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: var(--warning-soft); color: #b45309;">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </div>
                <div>
                    <h3 class="card-title" style="margin-bottom: 4px;">鋒兄比價</h3>
                    <p style="color: var(--muted-text); line-height: 1.6;">貼上商品關鍵字或網址，快速開啟 BigGo 查詢。</p>
                </div>
            </div>

            <div style="display: grid; gap: 12px;">
                <label for="priceQuery" style="font-weight: 700;">商品關鍵字或網址</label>
                <input id="priceQuery" class="form-control" type="text" placeholder="例如 iPhone 17 256GB">
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <button class="btn btn-primary" type="button" onclick="runBigGoLookup()">
                        <i class="fa-solid fa-search"></i> 查詢價格
                    </button>
                    <a class="btn btn-ghost" href="https://biggo.com.tw/" target="_blank" rel="noopener">
                        <i class="fa-solid fa-up-right-from-square"></i> BigGo 首頁
                    </a>
                </div>
            </div>
        </section>

        <section class="card">
            <div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px;">
                <div style="width: 46px; height: 46px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: var(--accent-soft); color: var(--accent);">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <div>
                    <h3 class="card-title" style="margin-bottom: 4px;">手機比價</h3>
                    <p style="color: var(--muted-text); line-height: 1.6;">依機型開啟手機通路查詢，對照地標網通與傑昇通信。</p>
                </div>
            </div>

            <div class="phone-compare-panel">
                <input id="phoneQuery" class="form-control" type="hidden">
                <details class="phone-compare-section" open>
                    <summary>
                        <span><i class="fa-brands fa-apple"></i> 蘋果手機區塊</span>
                        <small id="applePhoneDefaultText">預設 iPhone</small>
                    </summary>
                    <div class="phone-compare-body">
                        <label for="applePhoneQuery">蘋果手機型號</label>
                        <input id="applePhoneQuery" class="form-control" type="text" placeholder="例如 iPhone 17">
                        <div class="phone-compare-actions">
                            <button class="btn btn-primary" type="button" onclick="runPhoneCompareFor('apple')">
                                <i class="fa-solid fa-mobile-screen"></i> 查詢蘋果通路
                            </button>
                            <button class="btn btn-ghost" type="button" onclick="fillPhoneQuery(getDefaultApplePhone(), 'apple')">預設 iPhone</button>
                            <button class="btn btn-ghost" type="button" onclick="fillPhoneQuery('iPhone 17', 'apple')">iPhone 17</button>
                            <button class="btn btn-ghost" type="button" onclick="fillPhoneQuery('iPhone 16', 'apple')">iPhone 16</button>
                        </div>
                    </div>
                </details>
                <details class="phone-compare-section">
                    <summary>
                        <span><i class="fa-brands fa-android"></i> 三星手機區塊</span>
                        <small id="samsungPhoneDefaultText">預設 Samsung</small>
                    </summary>
                    <div class="phone-compare-body">
                        <label for="samsungPhoneQuery">三星手機型號</label>
                        <input id="samsungPhoneQuery" class="form-control" type="text" placeholder="例如 Samsung S26">
                        <div class="phone-compare-actions">
                            <button class="btn btn-primary" type="button" onclick="runPhoneCompareFor('samsung')">
                                <i class="fa-solid fa-mobile-screen"></i> 查詢三星通路
                            </button>
                            <button class="btn btn-ghost" type="button" onclick="fillPhoneQuery(getDefaultSamsungPhone(), 'samsung')">預設 Samsung</button>
                            <button class="btn btn-ghost" type="button" onclick="fillPhoneQuery('Samsung S26', 'samsung')">Samsung S26</button>
                            <button class="btn btn-ghost" type="button" onclick="fillPhoneQuery('Samsung S25', 'samsung')">Samsung S25</button>
                        </div>
                    </div>
                </details>
            </div>
        </section>
    </div>

    <section class="card" style="margin-top: 20px;">
        <h3 class="card-title">快速入口</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-top: 14px;">
            <a class="btn btn-ghost" href="https://www.landtop.com.tw/" target="_blank" rel="noopener">
                <i class="fa-solid fa-store"></i> 地標網通
            </a>
            <a class="btn btn-ghost" href="https://www.jyes.com.tw/" target="_blank" rel="noopener">
                <i class="fa-solid fa-store"></i> 傑昇通信
            </a>
            <a class="btn btn-ghost" href="https://biggo.com.tw/" target="_blank" rel="noopener">
                <i class="fa-solid fa-tags"></i> BigGo 比價
            </a>
        </div>
    </section>

    <section class="card" style="margin-top: 20px;">
        <h3 class="card-title">查詢結果與歷史快照</h3>
        <div id="toolResult" class="tool-result-box">
            <p style="color: var(--muted-text);">查詢後會在這裡顯示目前解析到的價格、外部來源與歷史快照。</p>
        </div>
    </section>
    <?php endif; ?>
</div>

<style>
    .tool-result-box {
        margin-top: 14px;
    }

    .food-stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 14px 16px;
        box-shadow: 0 12px 26px var(--shadow);
    }

    .food-stat-card span {
        display: block;
        color: var(--muted-text);
        font-size: 0.82rem;
        margin-bottom: 6px;
    }

    .food-stat-card strong {
        font-size: 1.25rem;
    }

    .tools-subnav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 18px;
    }

    .tools-subnav-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border: 1px solid var(--border-color);
        border-radius: 999px;
        background: var(--input-bg);
        color: var(--text-color);
        font-weight: 800;
        text-decoration: none;
    }

    .tools-subnav-link.active {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
    }

    .phone-compare-panel {
        display: grid;
        gap: 12px;
    }

    .phone-compare-section {
        border: 1px solid var(--border-color);
        border-radius: 16px;
        background: var(--input-bg);
        overflow: hidden;
    }

    .phone-compare-section summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        cursor: pointer;
        padding: 14px 16px;
        font-weight: 900;
        list-style: none;
    }

    .phone-compare-section summary::-webkit-details-marker {
        display: none;
    }

    .phone-compare-section summary span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .phone-compare-section summary small {
        color: var(--muted-text);
        font-weight: 800;
    }

    .phone-compare-body {
        display: grid;
        gap: 12px;
        padding: 0 16px 16px;
    }

    .phone-compare-body label {
        font-weight: 800;
    }

    .phone-compare-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .tube-overview,
    .tube-channel-head,
    .tube-new-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }

    .tube-overview-copy {
        display: grid;
        gap: 6px;
    }

    .tube-overview-copy p,
    .tube-overview-copy span,
    .tube-empty,
    .tube-new-alert p {
        color: var(--muted-text);
    }

    .tube-new-alert {
        margin-top: 16px;
        padding: 14px 16px;
        border: 1px solid rgba(245, 158, 11, 0.38);
        border-radius: 18px;
        background: rgba(254, 243, 199, 0.68);
        color: #92400e;
    }

    .tube-new-alert i {
        font-size: 1.25rem;
    }

    .tube-channel-manager {
        display: grid;
        gap: 16px;
        margin-top: 18px;
    }

    .tube-manager-head,
    .tube-channel-form,
    .tube-channel-admin-item,
    .tube-channel-admin-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .tube-manager-head,
    .tube-channel-admin-item {
        justify-content: space-between;
    }

    .tube-manager-head p,
    .tube-channel-admin-item span {
        color: var(--muted-text);
    }

    .tube-channel-form {
        flex-wrap: wrap;
    }

    .tube-channel-form input:first-of-type {
        flex: 1 1 260px;
    }

    .tube-channel-form input:nth-of-type(2) {
        flex: 2 1 420px;
    }

    .tube-channel-admin-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 10px;
    }

    .tube-channel-manager-body {
        display: grid;
        gap: 14px;
    }

    .tube-channel-manager.is-collapsed .tube-channel-manager-body {
        display: none;
    }

    .tube-manager-toggle i {
        transition: transform 0.18s ease;
    }

    .tube-channel-manager.is-collapsed .tube-manager-toggle i {
        transform: rotate(180deg);
    }

    .tube-channel-admin-item {
        padding: 12px 14px;
        border: 1px solid rgba(239, 68, 68, 0.18);
        border-radius: 16px;
        background: rgba(254, 242, 242, 0.58);
    }

    .tube-channel-admin-item > div:first-child {
        min-width: 0;
    }

    .tube-channel-admin-item strong,
    .tube-channel-admin-item span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tube-channel-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 18px;
        margin-top: 18px;
    }

    .tube-channel-card {
        display: grid;
        gap: 14px;
    }

    .tube-channel-head a {
        color: var(--accent);
        font-weight: 700;
        text-decoration: none;
    }

    .tube-channel-title {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .tube-channel-head span {
        flex: 0 0 auto;
        padding: 6px 10px;
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font-weight: 800;
    }

    .tube-update-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(239, 68, 68, 0.12);
        color: #b91c1c;
        font-size: 0.8rem;
        font-weight: 900;
        line-height: 1;
    }

    [data-theme="dark"] .tube-update-badge {
        background: rgba(248, 113, 113, 0.18);
        color: #fecaca;
    }

    .tube-video-list {
        display: grid;
        gap: 10px;
    }

    .tube-video-item {
        display: grid;
        grid-template-columns: 112px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: 16px;
        color: var(--text-color);
        text-decoration: none;
        background: var(--input-bg);
    }

    .tube-video-item.is-new {
        border-color: rgba(245, 158, 11, 0.48);
        background: rgba(254, 243, 199, 0.44);
    }

    .tube-video-item img {
        width: 112px;
        aspect-ratio: 16 / 9;
        object-fit: cover;
        border-radius: 10px;
        background: var(--border-color);
    }

    .tube-video-item strong {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.35;
    }

    .tube-video-item small {
        display: block;
        margin-top: 6px;
        color: var(--muted-text);
        font-weight: 700;
    }

    .finance-overview {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }

    .finance-overview-copy {
        display: grid;
        gap: 6px;
    }

    .finance-overview-copy p,
    .finance-overview-copy span,
    .finance-empty {
        color: var(--muted-text);
    }

    .finance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
        margin-top: 18px;
    }

    .finance-card {
        display: grid;
        gap: 14px;
        padding: 18px;
        border: 1px solid var(--border-color);
        border-radius: 18px;
        background: var(--card-bg);
        box-shadow: 0 12px 26px var(--shadow);
    }

    .finance-card.up {
        border-color: rgba(16, 185, 129, 0.26);
    }

    .finance-card.down {
        border-color: rgba(239, 68, 68, 0.24);
    }

    .finance-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .finance-card-head h3 {
        margin: 4px 0;
        font-size: 1rem;
        line-height: 1.35;
    }

    .finance-card-head a {
        color: var(--accent);
        font-weight: 800;
        text-decoration: none;
    }

    .finance-group {
        display: inline-flex;
        color: var(--muted-text);
        font-size: 0.78rem;
        font-weight: 900;
    }

    .finance-status {
        flex: 0 0 auto;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 0.78rem;
        line-height: 1;
        white-space: nowrap;
    }

    .finance-status.high {
        background: rgba(16, 185, 129, 0.14);
        color: #047857;
    }

    .finance-status.low {
        background: rgba(239, 68, 68, 0.14);
        color: #b91c1c;
    }

    .finance-status.breakout {
        background: rgba(245, 158, 11, 0.16);
        color: #b45309;
    }

    .finance-value-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 14px;
        background: var(--input-bg);
    }

    .finance-value-row span {
        color: var(--muted-text);
        font-weight: 800;
    }

    .finance-value-row strong {
        font-size: 1.65rem;
        line-height: 1;
    }

    .finance-change {
        font-weight: 900;
    }

    .finance-change.up {
        color: #059669;
    }

    .finance-change.down {
        color: #dc2626;
    }

    .finance-change.flat {
        color: var(--muted-text);
    }

    .finance-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .finance-stats span {
        display: grid;
        gap: 3px;
        padding: 8px 10px;
        border-radius: 12px;
        background: var(--input-bg);
        color: var(--muted-text);
        font-size: 0.78rem;
        font-weight: 800;
    }

    .finance-stats b {
        color: var(--text-color);
        font-size: 0.9rem;
    }

    @media (max-width: 560px) {
        .tube-overview,
        .tube-channel-head,
        .tube-new-alert,
        .finance-overview,
        .finance-card-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .tube-video-item {
            grid-template-columns: 1fr;
        }

        .tube-video-item img {
            width: 100%;
        }

        .finance-value-row {
            align-items: flex-start;
            flex-direction: column;
        }

        .finance-stats {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    function getTrimmedValue(id) {
        const input = document.getElementById(id);
        return input ? input.value.trim() : '';
    }

    function openBigGoSearch() {
        const query = getTrimmedValue('priceQuery');
        const url = query
            ? 'https://biggo.com.tw/s/' + encodeURIComponent(query) + '/'
            : 'https://biggo.com.tw/';
        window.open(url, '_blank', 'noopener');
    }

    function formatToolMoney(value) {
        return value === null || value === undefined || value === ''
            ? '--'
            : 'NT$ ' + Number(value).toLocaleString('zh-TW');
    }

    function renderToolHistory(history) {
        if (!history || history.length === 0) return '<p style="color: var(--muted-text);">尚無歷史快照。</p>';
        const points = history
            .filter(item => item.current_price)
            .map(item => Number(item.current_price));
        const list = history.slice(-8).reverse().map(item => `
            <tr>
                <td>${item.created_at || ''}</td>
                <td>${item.source || ''}</td>
                <td>${formatToolMoney(item.current_price)}</td>
                <td>${formatToolMoney(item.low_price)}</td>
                <td>${formatToolMoney(item.high_price)}</td>
            </tr>
        `).join('');
        const chart = points.length >= 2 ? renderSparkline(points) : '<p style="color: var(--muted-text);">至少 2 筆價格快照後顯示走勢。</p>';
        return `
            ${chart}
            <table class="table" style="margin-top: 12px;">
                <thead><tr><th>時間</th><th>來源</th><th>目前</th><th>最低</th><th>最高</th></tr></thead>
                <tbody>${list}</tbody>
            </table>
        `;
    }

    function renderSparkline(points) {
        const width = 680;
        const height = 180;
        const min = Math.min(...points);
        const max = Math.max(...points);
        const span = Math.max(1, max - min);
        const coords = points.map((value, index) => {
            const x = points.length === 1 ? width / 2 : (index / (points.length - 1)) * width;
            const y = height - ((value - min) / span) * (height - 24) - 12;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        }).join(' ');
        return `<svg viewBox="0 0 ${width} ${height}" style="width:100%;height:180px;border:1px solid var(--border-color);border-radius:16px;background:var(--input-bg);">
            <polyline points="${coords}" fill="none" stroke="var(--accent)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></polyline>
        </svg>`;
    }

    function setToolResult(html) {
        const box = document.getElementById('toolResult');
        if (box) box.innerHTML = html;
    }

    function runBigGoLookup() {
        const query = getTrimmedValue('priceQuery');
        if (!query) {
            alert('請先輸入商品關鍵字或網址');
            return;
        }
        setToolResult('<p>查詢中...</p>');
        fetch('tools_api.php?action=price_lookup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query })
        })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || '查詢失敗');
                const s = res.snapshot;
                const itemRows = (res.items || []).map(item => `
                    <tr>
                        <td>${item.url ? `<a href="${item.url}" target="_blank" rel="noopener">${item.title || '商品'}</a>` : (item.title || '商品')}</td>
                        <td>${item.source || 'BigGo API'}</td>
                        <td>${formatToolMoney(item.price)}</td>
                    </tr>
                `).join('');
                const itemTable = itemRows ? `
                    <table class="table" style="margin-top: 12px;">
                        <thead><tr><th>商品</th><th>來源</th><th>價格</th></tr></thead>
                        <tbody>${itemRows}</tbody>
                    </table>
                ` : '';
                setToolResult(`
                    <div style="display:grid;gap:12px;">
                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <div>
                                <h4>${s.title || query}</h4>
                                <p style="color:var(--muted-text);">${s.notice || '已儲存本次查詢快照。'}</p>
                            </div>
                            <a class="btn btn-ghost" href="${s.result_url}" target="_blank" rel="noopener">開啟來源</a>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
                            <div class="food-stat-card"><span>目前價格</span><strong>${formatToolMoney(s.current_price)}</strong></div>
                            <div class="food-stat-card"><span>最低</span><strong>${formatToolMoney(s.low_price)}</strong></div>
                            <div class="food-stat-card"><span>最高</span><strong>${formatToolMoney(s.high_price)}</strong></div>
                        </div>
                        ${itemTable}
                        ${renderToolHistory(res.history)}
                    </div>
                `);
            })
            .catch(err => setToolResult('<p style="color:#e74c3c;">' + err.message + '</p>'));
    }

    function getDefaultSamsungPhone() {
        const now = new Date();
        const modelYear = now.getMonth() < 2 ? now.getFullYear() - 1 : now.getFullYear();
        return 'Samsung S' + String(modelYear).slice(-2);
    }

    function getDefaultApplePhone() {
        const now = new Date();
        const releaseYear = now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1;
        const modelNumber = 17 + (releaseYear - 2025);
        return 'iPhone ' + Math.max(1, modelNumber);
    }

    function fillPhoneQuery(value, brand) {
        const input = document.getElementById('phoneQuery');
        const brandInput = brand === 'samsung'
            ? document.getElementById('samsungPhoneQuery')
            : document.getElementById('applePhoneQuery');
        if (input) {
            input.value = value;
        }
        if (brandInput) {
            brandInput.value = value;
            brandInput.focus();
        }
    }

    function getPhoneQueryForBrand(brand) {
        const fieldId = brand === 'samsung' ? 'samsungPhoneQuery' : 'applePhoneQuery';
        const fallback = brand === 'samsung' ? getDefaultSamsungPhone() : getDefaultApplePhone();
        const field = document.getElementById(fieldId);
        const value = field && field.value.trim() ? field.value.trim() : fallback;
        fillPhoneQuery(value, brand);
        return value;
    }

    function openPhoneCompare() {
        const query = getTrimmedValue('phoneQuery') || getDefaultSamsungPhone();
        const landtopUrl = 'https://www.google.com/search?q=' + encodeURIComponent('site:landtop.com.tw ' + query);
        const jyesUrl = 'https://www.google.com/search?q=' + encodeURIComponent('site:jyes.com.tw ' + query);

        window.open(landtopUrl, '_blank', 'noopener');
        window.open(jyesUrl, '_blank', 'noopener');
    }

    function runPhoneCompareFor(brand) {
        const query = getPhoneQueryForBrand(brand);
        runPhoneCompare(query);
    }

    function runPhoneCompare(queryOverride) {
        const query = queryOverride || getTrimmedValue('phoneQuery') || getDefaultSamsungPhone();
        setToolResult('<p>查詢中...</p>');
        fetch('tools_api.php?action=phone_lookup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query })
        })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || '查詢失敗');
                const links = Object.entries(res.targets || {}).map(([name, url]) =>
                    `<a class="btn btn-ghost" href="${url}" target="_blank" rel="noopener">${name}</a>`
                ).join('');
                setToolResult(`
                    <div style="display:grid;gap:12px;">
                        <h4>${res.snapshot.title}</h4>
                        <p style="color:var(--muted-text);">${res.snapshot.notice}</p>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">${links}</div>
                        ${renderToolHistory(res.history)}
                    </div>
                `);
            })
            .catch(err => setToolResult('<p style="color:#e74c3c;">' + err.message + '</p>'));
    }

    (function initPhoneCompareDefaults() {
        const apple = getDefaultApplePhone();
        const samsung = getDefaultSamsungPhone();
        const appleInput = document.getElementById('applePhoneQuery');
        const samsungInput = document.getElementById('samsungPhoneQuery');
        const appleText = document.getElementById('applePhoneDefaultText');
        const samsungText = document.getElementById('samsungPhoneDefaultText');
        if (appleInput && !appleInput.value) appleInput.value = apple;
        if (samsungInput && !samsungInput.value) samsungInput.value = samsung;
        if (appleText) appleText.textContent = '預設 ' + apple;
        if (samsungText) samsungText.textContent = '預設 ' + samsung;
        fillPhoneQuery(apple, 'apple');
    })();

    const TUBE_MANAGER_COLLAPSED_KEY = 'fengbro.tubeChannelManager.collapsed';

    function setTubeChannelManagerCollapsed(collapsed) {
        const manager = document.getElementById('tube-channel-manager');
        const toggle = document.getElementById('tubeChannelManagerToggle');
        if (!manager || !toggle) return;
        manager.classList.toggle('is-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.innerHTML = '<i class="fa-solid fa-chevron-up"></i> ' + (collapsed ? '展開' : '收合');
        localStorage.setItem(TUBE_MANAGER_COLLAPSED_KEY, collapsed ? '1' : '0');
    }

    function toggleTubeChannelManager() {
        const manager = document.getElementById('tube-channel-manager');
        setTubeChannelManagerCollapsed(!(manager && manager.classList.contains('is-collapsed')));
    }

    (function initTubeChannelManagerCollapse() {
        setTubeChannelManagerCollapsed(localStorage.getItem(TUBE_MANAGER_COLLAPSED_KEY) === '1');
    })();

    function editTubeChannel(index, name, url) {
        setTubeChannelManagerCollapsed(false);
        const indexInput = document.getElementById('tubeChannelIndex');
        const nameInput = document.getElementById('tubeChannelName');
        const urlInput = document.getElementById('tubeChannelUrl');
        const submit = document.getElementById('tubeChannelSubmit');
        const cancel = document.getElementById('tubeChannelCancel');
        if (!indexInput || !nameInput || !urlInput) return;
        indexInput.value = String(index);
        nameInput.value = name || '';
        urlInput.value = url || '';
        if (submit) submit.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> 儲存頻道';
        if (cancel) cancel.style.display = '';
        nameInput.focus();
        document.getElementById('tube-channel-manager')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    function cancelTubeChannelEdit() {
        const indexInput = document.getElementById('tubeChannelIndex');
        const nameInput = document.getElementById('tubeChannelName');
        const urlInput = document.getElementById('tubeChannelUrl');
        const submit = document.getElementById('tubeChannelSubmit');
        const cancel = document.getElementById('tubeChannelCancel');
        if (indexInput) indexInput.value = '-1';
        if (nameInput) nameInput.value = '';
        if (urlInput) urlInput.value = '';
        if (submit) submit.innerHTML = '<i class="fa-solid fa-plus"></i> 儲存頻道';
        if (cancel) cancel.style.display = 'none';
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') return;
        if (event.target && event.target.id === 'priceQuery') runBigGoLookup();
        if (event.target && event.target.id === 'phoneQuery') runPhoneCompare();
    });
</script>
