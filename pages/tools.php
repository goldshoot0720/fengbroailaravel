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
if ($toolSubpage === 'finance' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['finance_action'] ?? '') !== '') {
    $action = (string) ($_POST['finance_action'] ?? '');
    $config = fengbroFinanceReadConfig();

    if ($action === 'reset') {
        fengbroFinanceResetConfig();
    } elseif ($action === 'save_defaults') {
        $ids = isset($_POST['default_ids']) && is_array($_POST['default_ids']) ? $_POST['default_ids'] : [];
        fengbroFinanceSaveDefaultIds($ids);
    } elseif ($action === 'remove_default') {
        $removeId = trim((string) ($_POST['instrument_id'] ?? ''));
        $ids = array_values(array_filter($config['defaultIds'], static fn($id) => $id !== $removeId));
        fengbroFinanceSaveDefaultIds($ids);
    } elseif ($action === 'add_default') {
        $addId = trim((string) ($_POST['instrument_id'] ?? ''));
        $ids = $config['defaultIds'];
        if ($addId !== '' && !in_array($addId, $ids, true)) {
            $ids[] = $addId;
        }
        fengbroFinanceSaveDefaultIds($ids);
    } elseif ($action === 'save_custom') {
        $custom = $config['custom'];
        $instrument = fengbroFinanceNormalizeCustomInstrument([
            'name' => $_POST['custom_name'] ?? '',
            'symbol' => $_POST['custom_symbol'] ?? '',
            'provider' => $_POST['custom_provider'] ?? 'yahoo',
            'group' => $_POST['custom_group'] ?? 'US',
        ], count($custom));
        if ($instrument) {
            $replaced = false;
            foreach ($custom as $i => $row) {
                if (($row['id'] ?? '') === ($instrument['id'] ?? '')) {
                    $custom[$i] = $instrument;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                $custom[] = $instrument;
            }
            fengbroFinanceSaveCustomInstruments($custom);
        }
    } elseif ($action === 'delete_custom') {
        $deleteId = trim((string) ($_POST['instrument_id'] ?? ''));
        $custom = array_values(array_filter(
            $config['custom'],
            static fn($row) => ($row['id'] ?? '') !== $deleteId
        ));
        fengbroFinanceSaveCustomInstruments($custom);
    }

    header('Location: index.php?page=tools&tool=finance&refresh=1#finance-instrument-manager');
    exit;
}
@set_time_limit(120);
$tubeData = $toolSubpage === 'tube' ? fengbroTubeGetData(isset($_GET['refresh'])) : null;
$tubeChannels = $toolSubpage === 'tube' ? fengbroTubeChannels() : [];
$financeData = $toolSubpage === 'finance' ? fengbroFinanceGetData(isset($_GET['refresh'])) : null;
$financeConfig = $toolSubpage === 'finance' ? fengbroFinanceReadConfig() : null;
$financeCatalog = $toolSubpage === 'finance' ? fengbroFinanceDefaultItems() : [];
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
                    <input type="hidden" name="tube_action" value="reset">
                    <button type="submit" class="btn btn-ghost">
                        <i class="fa-solid fa-rotate-left"></i> 還原預設
                    </button>
                </form>
            </div>
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
        </section>

        <?php
        $downfallUpdate = $tubeData['downfallIndexUpdate'] ?? null;
        $downfallHistory = $tubeData['downfallHistory'] ?? [];
        $downfallPrices = array_values(array_filter(array_map(static function ($p) {
            return isset($p['price']) && is_numeric($p['price']) ? (float) $p['price'] : null;
        }, $downfallHistory)));
        ?>
        <?php if ($downfallUpdate || count($downfallPrices) >= 2): ?>
            <section class="card tube-downfall-panel">
                <div class="tube-downfall-head">
                    <div>
                        <h3 class="card-title"><i class="fa-solid fa-chart-area"></i> 倒台指數</h3>
                        <p style="margin:6px 0 0;color:var(--muted-text);">追蹤「一個狠人」頻道標題中的倒台指數，並合併固定歷史節點與最新影片解析（對齊 Appwrite）。</p>
                    </div>
                    <?php if (!empty($downfallUpdate['value'])): ?>
                        <?php if (!empty($downfallUpdate['url'])): ?>
                            <a class="tube-update-badge" href="<?php echo htmlspecialchars($downfallUpdate['url']); ?>" target="_blank" rel="noopener" title="<?php echo htmlspecialchars($downfallUpdate['title'] ?? ''); ?>">
                                更新：倒台指數 <?php echo htmlspecialchars($downfallUpdate['value']); ?>
                            </a>
                        <?php else: ?>
                            <span class="tube-update-badge" title="<?php echo htmlspecialchars($downfallUpdate['title'] ?? ''); ?>">
                                更新：倒台指數 <?php echo htmlspecialchars($downfallUpdate['value']); ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (count($downfallPrices) >= 2): ?>
                    <div class="finance-history-chart tube-downfall-chart" data-points="<?php echo htmlspecialchars(json_encode($downfallPrices), ENT_QUOTES, 'UTF-8'); ?>" style="height:120px;margin-top:12px;"></div>
                    <div style="margin-top:8px;color:var(--muted-text);font-size:0.82rem;font-weight:700;">
                        共 <?php echo count($downfallHistory); ?> 個節點
                        （<?php echo htmlspecialchars(number_format(min($downfallPrices), 2)); ?> → <?php echo htmlspecialchars(number_format(max($downfallPrices), 2)); ?>）
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

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
        <?php
        $selectedDefaultIds = $financeConfig['defaultIds'] ?? fengbroFinanceDefaultIds();
        $selectedDefaultSet = array_flip($selectedDefaultIds);
        $customInstruments = $financeConfig['custom'] ?? [];
        $availableDefaults = array_values(array_filter(
            $financeCatalog,
            static fn($item) => !isset($selectedDefaultSet[$item['id']])
        ));
        ?>
        <section class="card finance-overview">
            <div class="finance-overview-copy">
                <h3 class="card-title"><i class="fa-solid fa-chart-line"></i> 鋒兄金融</h3>
                <p>集中追蹤 CNBC、Yahoo 股市與 Multpl 參考來源。可管理預設標的與自訂標的，並顯示 1 年走勢（對齊 Appwrite 版）。</p>
                <span>最後檢查：<?php echo htmlspecialchars($financeData['checkedAt'] ?? '-'); ?> · 來源：<?php echo htmlspecialchars($financeData['source'] ?? 'CNBC / Yahoo股市 / Multpl'); ?> · 追蹤 <?php echo count($financeData['quotes'] ?? []); ?> 項</span>
            </div>
            <a class="btn btn-ghost" href="index.php?page=tools&tool=finance&refresh=1">
                <i class="fa-solid fa-rotate-right"></i> 重新檢查
            </a>
        </section>

        <section id="finance-instrument-manager" class="card finance-manager">
            <div class="finance-manager-head">
                <div>
                    <h3 class="card-title">標的管理</h3>
                    <p>可開關預設標的、新增 Yahoo/CNBC 自訂標的。設定會保存在伺服器本機設定檔。</p>
                </div>
                <form method="post" onsubmit="return confirm('還原全部預設標的並清除自訂標的？');">
                    <input type="hidden" name="finance_action" value="reset">
                    <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-rotate-left"></i> 還原預設</button>
                </form>
            </div>

            <div class="finance-manager-grid">
                <div>
                    <h4 style="margin:0 0 10px;">目前預設標的（<?php echo count($selectedDefaultIds); ?>）</h4>
                    <div class="finance-chip-list">
                        <?php foreach ($financeCatalog as $item): ?>
                            <?php if (!isset($selectedDefaultSet[$item['id']])) continue; ?>
                            <form method="post" class="finance-chip">
                                <input type="hidden" name="finance_action" value="remove_default">
                                <input type="hidden" name="instrument_id" value="<?php echo htmlspecialchars($item['id']); ?>">
                                <span><?php echo htmlspecialchars($item['name']); ?> <small><?php echo htmlspecialchars($item['symbol']); ?></small></span>
                                <button type="submit" class="btn btn-sm btn-ghost" title="移除"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($availableDefaults): ?>
                        <form method="post" class="finance-add-default" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                            <input type="hidden" name="finance_action" value="add_default">
                            <select name="instrument_id" class="form-control" style="max-width:280px;">
                                <?php foreach ($availableDefaults as $item): ?>
                                    <option value="<?php echo htmlspecialchars($item['id']); ?>">
                                        <?php echo htmlspecialchars($item['name'] . ' (' . $item['symbol'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> 加回預設標的</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div>
                    <h4 style="margin:0 0 10px;">自訂標的</h4>
                    <form method="post" class="finance-custom-form">
                        <input type="hidden" name="finance_action" value="save_custom">
                        <input class="form-control" type="text" name="custom_name" placeholder="名稱（可留空）">
                        <input class="form-control" type="text" name="custom_symbol" placeholder="代碼，例如 NVDA / 2330.TW" required>
                        <select class="form-control" name="custom_provider">
                            <option value="yahoo">Yahoo</option>
                            <option value="cnbc">CNBC</option>
                        </select>
                        <select class="form-control" name="custom_group">
                            <?php foreach (['Taiwan','Asia','Korea','FX','Commodities','Rates','US','Crypto'] as $g): ?>
                                <option value="<?php echo $g; ?>" <?php echo $g === 'US' ? 'selected' : ''; ?>><?php echo $g; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-plus"></i> 儲存自訂標的</button>
                    </form>
                    <div class="finance-chip-list" style="margin-top:12px;">
                        <?php if (!$customInstruments): ?>
                            <p style="color:var(--muted-text);margin:0;">尚未新增自訂標的。</p>
                        <?php endif; ?>
                        <?php foreach ($customInstruments as $custom): ?>
                            <form method="post" class="finance-chip">
                                <input type="hidden" name="finance_action" value="delete_custom">
                                <input type="hidden" name="instrument_id" value="<?php echo htmlspecialchars($custom['id']); ?>">
                                <span><?php echo htmlspecialchars($custom['name']); ?> <small><?php echo htmlspecialchars($custom['symbol']); ?></small></span>
                                <button type="submit" class="btn btn-sm btn-ghost" title="刪除"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="finance-grid">
            <?php foreach (($financeData['quotes'] ?? []) as $quote): ?>
                <?php
                $changeText = trim(($quote['change'] ?? '') . ' ' . ($quote['changePercent'] ?? ''));
                $changeNumber = isset($quote['change']) ? (float) str_replace(',', '', (string) $quote['change']) : 0;
                $tone = $changeNumber > 0 ? 'up' : ($changeNumber < 0 ? 'down' : 'flat');
                $statusClass = ($quote['status'] ?? '') === '創新高' ? 'high' : ((($quote['status'] ?? '') === '創新低') ? 'low' : 'breakout');
                $history1y = $quote['historyRanges']['1y'] ?? [];
                $historyPoints = array_values(array_filter(array_map(static function ($p) {
                    return isset($p['price']) && is_numeric($p['price']) ? (float) $p['price'] : null;
                }, $history1y)));
                ?>
                <section class="finance-card <?php echo $tone; ?>">
                    <div class="finance-card-head">
                        <div>
                            <span class="finance-group"><?php echo htmlspecialchars($quote['group']); ?><?php echo !empty($quote['isCustom']) ? ' · 自訂' : ''; ?></span>
                            <h3><?php echo htmlspecialchars($quote['name']); ?></h3>
                            <?php if (!empty($quote['localLabel'])): ?>
                                <div style="color:var(--muted-text);font-size:0.82rem;margin-bottom:4px;"><?php echo htmlspecialchars($quote['localLabel']); ?></div>
                            <?php endif; ?>
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
                        <?php if (!empty($quote['historySymbol']) || count($historyPoints) >= 2): ?>
                            <div class="finance-range-tabs"
                                 data-symbol="<?php echo htmlspecialchars($quote['historySymbol'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                 data-initial="<?php echo htmlspecialchars(json_encode(['1y' => $historyPoints], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="finance-range-buttons">
                                    <button type="button" class="btn btn-sm finance-range-btn active" data-range="1y">近一年</button>
                                    <button type="button" class="btn btn-sm finance-range-btn" data-range="5y">近五年</button>
                                    <button type="button" class="btn btn-sm finance-range-btn" data-range="10y">近十年</button>
                                </div>
                                <div class="finance-history-chart" data-points="<?php echo htmlspecialchars(json_encode($historyPoints), ENT_QUOTES, 'UTF-8'); ?>"></div>
                                <div class="finance-range-meta" style="color:var(--muted-text);font-size:0.78rem;font-weight:700;">
                                    <?php echo count($historyPoints) >= 2 ? ('近一年 · ' . count($historyPoints) . ' 點') : '點選區間載入走勢'; ?>
                                </div>
                            </div>
                        <?php endif; ?>
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
                    <p style="color: var(--muted-text); line-height: 1.6;">貼上商品關鍵字或網址；API 可用時抓取價格，否則保留 BigGo 查詢連結與歷史快照。</p>
                </div>
            </div>

            <div style="display: grid; gap: 12px;">
                <label for="priceQuery" style="font-weight: 700;">商品關鍵字或網址</label>
                <input id="priceQuery" class="form-control" type="text" placeholder="例如 iPhone 17 256GB">
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <button class="btn btn-primary" type="button" onclick="runBigGoLookup()">
                        <i class="fa-solid fa-search"></i> 建立比價快照
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
                    <p style="color: var(--muted-text); line-height: 1.6;">自動抓取地標網通與傑昇通信價格，合併比對最佳通路（對齊 Appwrite 版）。</p>
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
            <p style="color: var(--muted-text);">查詢後會在這裡顯示 API 價格、外部來源連結與歷史快照。</p>
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

    .finance-manager {
        margin-bottom: 20px;
    }

    .finance-manager-head {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .finance-manager-head p {
        margin: 6px 0 0;
        color: var(--muted-text);
        line-height: 1.5;
    }

    .finance-manager-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 18px;
    }

    .finance-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .finance-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 999px;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        margin: 0;
    }

    .finance-chip span {
        font-size: 0.86rem;
        font-weight: 700;
    }

    .finance-chip small {
        color: var(--muted-text);
        font-weight: 600;
    }

    .finance-custom-form {
        display: grid;
        gap: 8px;
    }

    .finance-history-chart {
        width: 100%;
        min-height: 72px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--input-bg);
        overflow: hidden;
        padding-bottom: 4px;
    }

    .finance-history-chart svg {
        display: block;
        width: 100%;
        height: 72px;
    }

    .finance-spark-meta {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        padding: 4px 10px 8px;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--muted-text);
    }

    .finance-range-tabs {
        display: grid;
        gap: 8px;
    }

    .finance-range-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .finance-range-btn.active {
        background: var(--accent);
        color: #fff;
        border-color: transparent;
    }

    .tube-downfall-panel {
        margin-bottom: 18px;
    }

    .tube-downfall-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        align-items: flex-start;
    }

    @media (max-width: 560px) {
        .tube-overview,
        .tube-channel-head,
        .tube-new-alert,
        .finance-overview,
        .finance-manager-head,
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
        runPhoneCompare(query);
    }

    function escapeToolHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderPhoneProducts(products) {
        if (!products || !products.length) {
            return '<p style="color:var(--muted-text);">沒有可比對的商品結果。</p>';
        }
        const rows = products.slice(0, 40).map(product => {
            const name = escapeToolHtml(product.name || '商品');
            const brand = escapeToolHtml(product.brand || '');
            const landtopLabel = escapeToolHtml(product.landtopPriceLabel || formatToolMoney(product.landtopPrice));
            const jyesLabel = escapeToolHtml(product.jyesPriceLabel || formatToolMoney(product.jyesPrice));
            const bestLabel = product.bestPrice != null
                ? formatToolMoney(product.bestPrice) + (product.bestSourceLabel ? ' · ' + escapeToolHtml(product.bestSourceLabel) : '')
                : '--';
            const landtopLink = product.sourceUrl
                ? `<a href="${escapeToolHtml(product.sourceUrl)}" target="_blank" rel="noopener">${landtopLabel}</a>`
                : landtopLabel;
            const jyesLink = product.jyesUrl
                ? `<a href="${escapeToolHtml(product.jyesUrl)}" target="_blank" rel="noopener">${jyesLabel}</a>`
                : jyesLabel;
            return `
                <tr>
                    <td>
                        <strong>${name}</strong>
                        <div style="color:var(--muted-text);font-size:0.82rem;margin-top:4px;">${brand}</div>
                    </td>
                    <td>${formatToolMoney(product.suggestedPrice)}</td>
                    <td>${landtopLink}</td>
                    <td>${jyesLink}</td>
                    <td><strong>${bestLabel}</strong></td>
                </tr>
            `;
        }).join('');
        return `
            <div style="overflow-x:auto;">
                <table class="table" style="margin-top:8px; min-width:720px;">
                    <thead>
                        <tr>
                            <th>商品</th>
                            <th>建議售價</th>
                            <th>地標網通</th>
                            <th>傑昇通信</th>
                            <th>最佳價</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    function renderPhonePriceChart(products) {
        const chartProducts = (products || [])
            .filter(p => p.landtopPrice || p.jyesPrice)
            .slice(0, 8);
        if (!chartProducts.length) return '';
        const maxPrice = Math.max(
            ...chartProducts.flatMap(p => [p.landtopPrice || 0, p.jyesPrice || 0]),
            1
        );
        const bars = chartProducts.map(product => {
            const landtopWidth = Math.max(4, ((product.landtopPrice || 0) / maxPrice) * 100);
            const jyesWidth = Math.max(4, ((product.jyesPrice || 0) / maxPrice) * 100);
            return `
                <div style="display:grid;gap:6px;margin-bottom:12px;">
                    <div style="font-size:0.86rem;font-weight:600;">${escapeToolHtml(product.name || '')}</div>
                    <div style="display:grid;gap:4px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="width:56px;color:var(--muted-text);font-size:0.78rem;">地標</span>
                            <div style="flex:1;background:var(--table-header-bg);border-radius:999px;height:10px;overflow:hidden;">
                                <div style="width:${product.landtopPrice ? landtopWidth : 4}%;height:100%;background:var(--accent);"></div>
                            </div>
                            <span style="min-width:88px;text-align:right;font-size:0.82rem;">${product.landtopPrice ? formatToolMoney(product.landtopPrice) : '最低價'}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="width:56px;color:var(--muted-text);font-size:0.78rem;">傑昇</span>
                            <div style="flex:1;background:var(--table-header-bg);border-radius:999px;height:10px;overflow:hidden;">
                                <div style="width:${product.jyesPrice ? jyesWidth : 4}%;height:100%;background:#0ea5e9;"></div>
                            </div>
                            <span style="min-width:88px;text-align:right;font-size:0.82rem;">${product.jyesPrice ? formatToolMoney(product.jyesPrice) : '--'}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        return `
            <div class="card" style="padding:14px 16px;box-shadow:none;">
                <div style="font-size:0.78rem;font-weight:700;letter-spacing:0.08em;color:var(--muted-text);margin-bottom:10px;">LANDTOP / JYES CHART</div>
                ${bars}
            </div>
        `;
    }

    function renderPhoneProductHistories(histories) {
        if (!histories || !histories.length) {
            return '<p style="color:var(--muted-text);">目前還沒有商品級歷史價格。每次查詢會寫入當日快照，累積後即可看走勢。</p>';
        }
        const cards = histories.slice(0, 8).map(series => {
            const landtopPoints = (series.points || [])
                .filter(p => p.landtopPrice != null)
                .map(p => Number(p.landtopPrice));
            const jyesPoints = (series.points || [])
                .filter(p => p.jyesPrice != null)
                .map(p => Number(p.jyesPrice));
            const chart = landtopPoints.length >= 2
                ? renderSparkline(landtopPoints)
                : (jyesPoints.length >= 2 ? renderSparkline(jyesPoints) : '<p style="color:var(--muted-text);font-size:0.86rem;">至少 2 個快照日後顯示走勢。</p>');
            return `
                <div class="card" style="padding:12px 14px;box-shadow:none;">
                    <strong style="display:block;margin-bottom:6px;">${escapeToolHtml(series.name || series.id || '商品')}</strong>
                    <div style="color:var(--muted-text);font-size:0.8rem;margin-bottom:8px;">快照 ${Number((series.points || []).length)} 筆</div>
                    ${chart}
                </div>
            `;
        }).join('');
        return `
            <div style="display:grid;gap:12px;">
                <h4 style="margin:0;">商品歷史價格（每日快照）</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                    ${cards}
                </div>
            </div>
        `;
    }

    function runPhoneCompareFor(brand) {
        const query = getPhoneQueryForBrand(brand);
        runPhoneCompare(query);
    }

    function runPhoneCompare(queryOverride) {
        const query = queryOverride || getTrimmedValue('phoneQuery') || getDefaultSamsungPhone();
        setToolResult('<p>正在抓取地標網通與傑昇通信價格，請稍候...</p>');
        fetch('tools_api.php?action=phone_lookup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query })
        })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || '查詢失敗');
                const s = res.snapshot || {};
                const links = Object.entries(res.targets || {}).map(([name, url]) =>
                    `<a class="btn btn-ghost" href="${escapeToolHtml(url)}" target="_blank" rel="noopener">${escapeToolHtml(name)}</a>`
                ).join('');
                const warningHtml = (res.warnings || []).length
                    ? `<p style="color:#b45309;">${res.warnings.map(escapeToolHtml).join('；')}</p>`
                    : '';
                setToolResult(`
                    <div style="display:grid;gap:12px;">
                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <div>
                                <h4>${escapeToolHtml(s.title || (query + ' 手機比價'))}</h4>
                                <p style="color:var(--muted-text);">${escapeToolHtml(s.notice || '')}</p>
                                <p style="color:var(--muted-text);font-size:0.86rem;">共 ${Number(res.total || (res.products || []).length)} 筆 · 來源：地標網通 + 傑昇通信${res.snapshotStored ? ' · 今日寫入 ' + Number(res.snapshotStored) + ' 筆商品快照' : ''}</p>
                            </div>
                        </div>
                        ${warningHtml}
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
                            <div class="food-stat-card"><span>最佳價</span><strong>${formatToolMoney(s.current_price ?? s.low_price)}</strong></div>
                            <div class="food-stat-card"><span>最低</span><strong>${formatToolMoney(s.low_price)}</strong></div>
                            <div class="food-stat-card"><span>最高</span><strong>${formatToolMoney(s.high_price)}</strong></div>
                        </div>
                        ${renderPhonePriceChart(res.products || [])}
                        ${renderPhoneProducts(res.products || [])}
                        ${renderPhoneProductHistories(res.histories || [])}
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">${links}</div>
                        ${renderToolHistory(res.history)}
                    </div>
                `);
            })
            .catch(err => setToolResult('<p style="color:#e74c3c;">' + escapeToolHtml(err.message) + '</p>'));
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

    function editTubeChannel(index, name, url) {
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

    function renderInlineSparkline(container, points) {
        if (!container || !points || points.length < 2) return;
        const width = 320;
        const height = 72;
        const min = Math.min(...points);
        const max = Math.max(...points);
        const last = points[points.length - 1];
        const span = Math.max(1e-9, max - min);
        const coords = points.map((value, index) => {
            const x = (index / (points.length - 1)) * width;
            const y = height - ((value - min) / span) * (height - 18) - 10;
            return x.toFixed(1) + ',' + y.toFixed(1);
        }).join(' ');
        const up = last >= points[0];
        const color = up ? '#059669' : '#dc2626';
        const areaCoords = '0,' + height + ' ' + coords + ' ' + width + ',' + height;
        const fmt = function (n) {
            return Number(n).toLocaleString('zh-TW', { maximumFractionDigits: 2 });
        };
        const gradientId = 'sparkFill_' + Math.random().toString(36).slice(2, 8);
        container.innerHTML = `<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" style="display:block;width:100%;height:100%;">
            <defs>
                <linearGradient id="${gradientId}" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="${color}" stop-opacity="0.28"></stop>
                    <stop offset="100%" stop-color="${color}" stop-opacity="0.02"></stop>
                </linearGradient>
            </defs>
            <polygon points="${areaCoords}" fill="url(#${gradientId})"></polygon>
            <polyline points="${coords}" fill="none" stroke="${color}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>
        </svg>
        <div class="finance-spark-meta">
            <span>低 ${fmt(min)}</span>
            <span>現 ${fmt(last)}</span>
            <span>高 ${fmt(max)}</span>
        </div>`;
    }

    document.querySelectorAll('.finance-history-chart[data-points]').forEach(function (el) {
        try {
            const points = JSON.parse(el.getAttribute('data-points') || '[]');
            renderInlineSparkline(el, points);
        } catch (e) {}
    });

    document.querySelectorAll('.finance-range-tabs').forEach(function (tabs) {
        const symbol = tabs.getAttribute('data-symbol') || '';
        const chartEl = tabs.querySelector('.finance-history-chart');
        const metaEl = tabs.querySelector('.finance-range-meta');
        let cache = {};
        try {
            cache = JSON.parse(tabs.getAttribute('data-initial') || '{}') || {};
        } catch (e) {
            cache = {};
        }

        function setActive(range) {
            tabs.querySelectorAll('.finance-range-btn').forEach(function (btn) {
                btn.classList.toggle('active', btn.getAttribute('data-range') === range);
            });
        }

        function showPoints(range, points, label) {
            const safePoints = (points || []).filter(function (n) { return typeof n === 'number' && !isNaN(n); });
            if (chartEl) {
                if (safePoints.length >= 2) {
                    renderInlineSparkline(chartEl, safePoints);
                } else {
                    chartEl.innerHTML = '<div style="padding:18px;color:var(--muted-text);font-size:0.86rem;">此區間暫無資料</div>';
                }
            }
            if (metaEl) {
                metaEl.textContent = (label || range) + ' · ' + safePoints.length + ' 點';
            }
        }

        tabs.querySelectorAll('.finance-range-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const range = btn.getAttribute('data-range') || '1y';
                setActive(range);
                if (cache[range] && cache[range].length) {
                    showPoints(range, cache[range], btn.textContent.trim());
                    return;
                }
                if (!symbol) {
                    if (metaEl) metaEl.textContent = '此標的無 Yahoo 歷史代碼';
                    return;
                }
                if (metaEl) metaEl.textContent = '載入 ' + btn.textContent.trim() + '…';
                fetch('tools_api.php?action=finance_history', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ symbol: symbol, range: range })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        const points = (res.points || []).map(function (p) { return Number(p.price); }).filter(function (n) { return !isNaN(n); });
                        cache[range] = points;
                        showPoints(range, points, res.label || btn.textContent.trim());
                        if (!res.success && metaEl && res.error) {
                            metaEl.textContent = res.error;
                        }
                    })
                    .catch(function (err) {
                        if (metaEl) metaEl.textContent = err.message || '載入失敗';
                    });
            });
        });
    });
</script>
