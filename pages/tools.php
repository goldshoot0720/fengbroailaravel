<?php
$pageTitle = '鋒兄工具';
require_once __DIR__ . '/../includes/fengbro_tube.php';
require_once __DIR__ . '/../includes/fengbro_finance.php';

$toolSubpage = $_GET['tool'] ?? 'price';
$toolSubpage = in_array($toolSubpage, [
    'price', 'phone', 'manual', 'tube', 'finance', 'news',
    'image-convert', 'image-voice', 'video-merge', 'yt-bili',
], true) ? $toolSubpage : 'price';
if ($toolSubpage === 'manual') {
    // 手動價格紀錄以伺服器表為主（跨瀏覽器同步），頁面載入時確保表存在。
    try {
        fengbroEnsureManualPriceTable(getConnection());
    } catch (Throwable $e) {
        // 表建立失敗時前端仍可離線運作（localStorage 快取）
    }
}
if ($toolSubpage === 'finance') {
    // 金融自訂標的同步到 financeinstrument 表（JSON 仍為行情主要來源）。
    try {
        fengbroEnsureFinanceInstrumentTable(getConnection());
    } catch (Throwable $e) {
        // ignore
    }
}
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
    } elseif ($action === 'bulk_delete') {
        $indexes = array_map('intval', (array) ($_POST['channel_indexes'] ?? []));
        rsort($indexes);
        foreach ($indexes as $bulkIndex) {
            if (isset($channels[$bulkIndex])) {
                array_splice($channels, $bulkIndex, 1);
            }
        }
        fengbroTubeSaveChannels($channels);
    } elseif ($action === 'save' && $channel['url'] !== '') {
        if ($index >= 0 && isset($channels[$index])) {
            $channels[$index] = $channel;
        } else {
            $channels[] = $channel;
        }
        fengbroTubeSaveChannels($channels);
    } elseif ($action === 'export_csv') {
        $channels = fengbroTubeChannels();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fengbro-tube-channels.csv"');
        echo "\xEF\xBB\xBF";
        echo "alias,sourceUrl\n";
        foreach ($channels as $ch) {
            $alias = str_replace('"', '""', (string) ($ch['name'] ?? ''));
            $url = str_replace('"', '""', (string) ($ch['url'] ?? ''));
            echo '"' . $alias . '","' . $url . "\"\n";
        }
        exit;
    } elseif ($action === 'import_csv' && !empty($_FILES['tube_csv']['tmp_name'])) {
        $raw = (string) file_get_contents($_FILES['tube_csv']['tmp_name']);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $imported = [];
        $start = 0;
        if ($lines && preg_match('/alias|sourceurl|網址|名稱/i', $lines[0])) {
            $start = 1;
        }
        for ($i = $start; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }
            // simple CSV split respecting quotes
            if (preg_match('/^"(.*)"\s*,\s*"(.*)"\s*$/u', $line, $m)) {
                $alias = str_replace('""', '"', $m[1]);
                $url = str_replace('""', '"', $m[2]);
            } else {
                $parts = str_getcsv($line);
                $alias = trim((string) ($parts[0] ?? ''));
                $url = trim((string) ($parts[1] ?? $parts[0] ?? ''));
                if (count($parts) < 2) {
                    $alias = '';
                }
            }
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            $imported[] = ['name' => trim($alias), 'url' => $url];
            if (count($imported) >= 80) {
                break;
            }
        }
        if ($imported) {
            // merge by URL
            $map = [];
            foreach (fengbroTubeChannels() as $ch) {
                $u = trim((string) ($ch['url'] ?? ''));
                if ($u !== '') {
                    $map[$u] = $ch;
                }
            }
            foreach ($imported as $ch) {
                $map[$ch['url']] = $ch;
            }
            fengbroTubeSaveChannels(array_values($map));
            header('Location: index.php?page=tools&tool=tube&refresh=1#tube-channel-manager');
            exit;
        }
        // CSV 有內容但沒有任何可匯入的頻道（對齊 Appwrite：已下架預設頻道明確報錯，不靜默略過）
        header('Location: index.php?page=tools&tool=tube&tube_import_error=1#tube-channel-manager');
        exit;
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
            'imageUrlsText' => $_POST['custom_image_urls'] ?? '',
            'youtubeUrl' => $_POST['custom_youtube_url'] ?? '',
            'bilibiliUrl' => $_POST['custom_bilibili_url'] ?? '',
            'relatedLinksText' => $_POST['custom_related_links'] ?? '',
        ], count($custom));
        if ($instrument) {
            $replaced = false;
            foreach ($custom as $i => $row) {
                $sameId = ($row['id'] ?? '') === ($instrument['id'] ?? '');
                $sameSym = strtoupper((string) ($row['symbol'] ?? '')) === strtoupper((string) ($instrument['symbol'] ?? ''))
                    && ($instrument['symbol'] ?? '') !== '';
                if ($sameId || $sameSym) {
                    // Keep stable custom id when updating by symbol
                    if (!empty($row['id'])) {
                        $instrument['id'] = $row['id'];
                    }
                    $custom[$i] = $instrument;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                $custom[] = $instrument;
            }
            fengbroFinanceSaveCustomInstruments($custom);
            // Persist image map for card display / overrides
            if (!empty($instrument['id'])) {
                fengbroFinanceSaveImagesForId(
                    (string) $instrument['id'],
                    $instrument['imageUrls'] ?? []
                );
            }
        }
    } elseif ($action === 'delete_custom' || $action === 'bulk_delete_custom') {
        $deleteIds = $action === 'bulk_delete_custom'
            ? array_values(array_filter(array_map('trim', (array) ($_POST['instrument_ids'] ?? []))))
            : [trim((string) ($_POST['instrument_id'] ?? ''))];
        $deleteSet = array_flip(array_filter($deleteIds, static fn($id) => $id !== ''));
        $custom = array_values(array_filter(
            $config['custom'],
            static fn($row) => !isset($deleteSet[(string) ($row['id'] ?? '')])
        ));
        fengbroFinanceSaveCustomInstruments($custom);
        if ($deleteSet) {
            $cfg = fengbroFinanceReadConfig();
            foreach (array_keys($deleteSet) as $deleteId) {
                unset($cfg['imageById'][$deleteId]);
            }
            fengbroFinanceWriteConfig($cfg);
        }
    } elseif ($action === 'set_images') {
        $imgId = trim((string) ($_POST['instrument_id'] ?? ''));
        $imgText = (string) ($_POST['image_urls'] ?? '');
        if ($imgId !== '') {
            fengbroFinanceSaveImagesForId($imgId, $imgText);
        }
    } elseif ($action === 'export_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fengbro-finance.csv"');
        echo "\xEF\xBB\xBF";
        // id,name,symbol,provider,group,imageUrls,youtubeUrl,bilibiliUrl,relatedLinks,featured
        echo fengbroFinanceBuildCsv();
        exit;
    } elseif ($action === 'toggle_featured') {
        $fid = trim((string) ($_POST['instrument_id'] ?? ''));
        if ($fid !== '') {
            fengbroFinanceToggleFeatured($fid);
        }
    } elseif ($action === 'import_csv' && !empty($_FILES['finance_csv']['tmp_name'])) {
        $raw = (string) file_get_contents($_FILES['finance_csv']['tmp_name']);
        fengbroFinanceImportCsv($raw);
    }

    $redirectHash = ($action === 'set_images') ? '' : '#finance-instrument-manager';
    header('Location: index.php?page=tools&tool=finance&refresh=1' . $redirectHash);
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
        <p style="margin-top: 8px; color: var(--muted-text);">比價、新聞、媒體轉檔與常用工具（對齊 Appwrite 版）。</p>
    </div>
</div>

<div class="content-body">
    <div class="tools-subnav">
        <a class="tools-subnav-link <?php echo $toolSubpage === 'price' ? 'active' : ''; ?>" href="index.php?page=tools&tool=price">
            <i class="fa-solid fa-tags"></i> 鋒兄比價
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'phone' ? 'active' : ''; ?>" href="index.php?page=tools&tool=phone">
            <i class="fa-solid fa-mobile-screen-button"></i> 手機比價
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'manual' ? 'active' : ''; ?>" href="index.php?page=tools&tool=manual">
            <i class="fa-solid fa-clipboard-list"></i> 手動價格
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'tube' ? 'active' : ''; ?>" href="index.php?page=tools&tool=tube">
            <i class="fa-brands fa-youtube"></i> 鋒兄tube
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'finance' ? 'active' : ''; ?>" href="index.php?page=tools&tool=finance">
            <i class="fa-solid fa-chart-line"></i> 鋒兄金融
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'news' ? 'active' : ''; ?>" href="index.php?page=tools&tool=news">
            <i class="fa-solid fa-newspaper"></i> 鋒兄新聞
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'image-convert' ? 'active' : ''; ?>" href="index.php?page=tools&tool=image-convert">
            <i class="fa-solid fa-image"></i> PNG / JPEG
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'image-voice' ? 'active' : ''; ?>" href="index.php?page=tools&tool=image-voice">
            <i class="fa-solid fa-clapperboard"></i> 圖+語音
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'video-merge' ? 'active' : ''; ?>" href="index.php?page=tools&tool=video-merge">
            <i class="fa-solid fa-film"></i> 影片合併
        </a>
        <a class="tools-subnav-link <?php echo $toolSubpage === 'yt-bili' ? 'active' : ''; ?>" href="index.php?page=tools&tool=yt-bili">
            <i class="fa-brands fa-youtube"></i> YT / B站
        </a>
    </div>

    <?php if ($toolSubpage === 'manual'): ?>
        <section id="manualPriceTool" class="card">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px;">
                <div>
                    <h3 class="card-title" style="margin-bottom:4px;"><i class="fa-solid fa-clipboard-list"></i> 手動價格紀錄</h3>
                    <p style="color:var(--muted-text);line-height:1.6;margin:0;">自行登錄商品價格與日期；資料存於伺服器（跨瀏覽器同步，對齊 Appwrite ManualPriceTracker），本機僅作離線快取與首次遷移來源。支援 CSV 匯出／匯入合併。</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-ghost" data-mp-export><i class="fa-solid fa-download"></i> 匯出 CSV</button>
                    <button type="button" class="btn btn-ghost" data-mp-import><i class="fa-solid fa-upload"></i> 匯入 CSV</button>
                    <input type="file" data-mp-file accept=".csv,text/csv" hidden>
                </div>
            </div>
            <div class="mp-layout">
                <div class="mp-sidebar">
                    <label style="font-weight:700;">新增商品</label>
                    <input class="form-control" type="text" data-mp-name placeholder="商品名稱">
                    <select class="form-control" data-mp-currency style="margin-top:8px;">
                        <option value="TWD">TWD</option>
                        <option value="USD">USD</option>
                        <option value="JPY">JPY</option>
                    </select>
                    <button type="button" class="btn btn-primary" style="margin-top:10px;width:100%;" data-mp-add-product>
                        <i class="fa-solid fa-plus"></i> 新增商品
                    </button>
                    <div data-mp-list class="mp-product-list" style="margin-top:14px;"></div>
                </div>
                <div class="mp-main">
                    <div class="mp-form-row">
                        <label>價錢</label>
                        <input class="form-control" type="number" min="0" step="any" data-mp-price placeholder="0">
                        <label>日期</label>
                        <input class="form-control" type="date" data-mp-date>
                        <label>備註</label>
                        <input class="form-control" type="text" data-mp-note placeholder="可選">
                        <button type="button" class="btn btn-primary" data-mp-add-record>
                            <i class="fa-solid fa-check"></i> 登錄價格
                        </button>
                    </div>
                    <p data-mp-error style="color:#dc2626;min-height:1.2em;"></p>
                    <div data-mp-detail></div>
                </div>
            </div>
        </section>
        <script src="assets/js/tool-manual-price.js" defer></script>
    <?php elseif ($toolSubpage === 'news'): ?>
        <section id="fengbroNewsTool">
            <div class="card" style="margin-bottom:16px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;">
                    <div>
                        <h3 class="card-title" style="margin-bottom:4px;"><i class="fa-solid fa-newspaper"></i> 鋒兄新聞</h3>
                        <p style="color:var(--muted-text);line-height:1.6;margin:0;">多來源關鍵字搜尋（Google News RSS + 站內掃描 + YouTube 頻道標題）。可開關焦點來源，並查看台鐵便當門市據點。</p>
                    </div>
                </div>
                <form role="search" class="fengbro-search-form" data-news-search-form style="margin-top:14px;">
                    <input class="form-control" style="flex:1;min-width:220px;" type="search" data-news-query placeholder="例如 捷運、桃園、房價" enterkeyhint="search">
                    <button type="submit" class="btn btn-primary" data-news-search aria-label="提交搜尋">
                        <i class="fa-solid fa-search"></i> <span>搜尋</span>
                    </button>
                </form>
                <p data-news-status class="tool-muted" style="margin:10px 0 0;"></p>
                <p data-news-error style="color:#dc2626;margin:6px 0 0;"></p>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
                    <h3 class="card-title" style="margin:0;">焦點來源</h3>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-ghost btn-sm" data-news-lock-all>全選</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-news-unlock-all>全不選</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-news-reset-sites>還原預設</button>
                    </div>
                </div>
                <div data-news-sites></div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <h3 class="card-title">搜尋結果</h3>
                <div data-news-results class="tool-muted" style="margin-top:10px;">輸入關鍵字後開始搜尋。</div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <h3 class="card-title">台鐵便當門市</h3>
                <div data-news-bento style="margin-top:10px;"></div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                    <div>
                        <h3 class="card-title" style="margin-bottom:4px;"><i class="fa-solid fa-chart-line"></i> 桃園人口統計</h3>
                        <p class="tool-muted" style="margin:0;">桃園市最近三個月人口數、新增人口數，以及近十年人口走勢。</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" data-news-pop-refresh><i class="fa-solid fa-rotate"></i> 更新</button>
                </div>
                <div data-news-pop-taoyuan style="margin-top:12px;"><p class="tool-muted">載入人口統計…</p></div>
            </div>

            <div class="card">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                    <div>
                        <h3 class="card-title" style="margin-bottom:4px;"><i class="fa-solid fa-chart-area"></i> 中壢人口統計</h3>
                        <p class="tool-muted" style="margin:0;">中壢區最近三個月人口數、新增人口數，以及近十年人口走勢。</p>
                    </div>
                </div>
                <div data-news-pop-zhongli style="margin-top:12px;"><p class="tool-muted">載入人口統計…</p></div>
            </div>
        </section>
        <script src="assets/js/tool-news.js" defer></script>
    <?php elseif ($toolSubpage === 'image-convert'): ?>
        <section id="imageConvertTool" class="card">
            <div style="margin-bottom:16px;">
                <h3 class="card-title" style="margin-bottom:4px;"><i class="fa-solid fa-image"></i> PNG / JPEG 轉換</h3>
                <p style="color:var(--muted-text);line-height:1.6;margin:0;">
                    本機 Canvas 批次轉換，不上傳伺服器。參考
                    <a href="https://github.com/huang1988pioneer/PNGJPEGConverter" target="_blank" rel="noopener">PNGJPEGConverter</a>
                    · 網址圖片經 <code>media_proxy.php</code> 避開 CORS。
                </p>
            </div>
            <div class="ic-layout">
                <div class="ic-card">
                    <div class="ic-card-head"><span class="ic-step">1</span><strong>加入圖片</strong>
                        <button type="button" class="btn btn-ghost btn-sm" data-ic-clear>清除全部</button>
                    </div>
                    <div class="ic-dropzone" data-ic-drop role="button" tabindex="0" aria-label="上傳圖片">
                        <div>拖放或點選多張圖片</div>
                        <small class="tool-muted">PNG / JPEG / WebP / GIF / BMP / AVIF（依瀏覽器）</small>
                    </div>
                    <input type="file" data-ic-file multiple hidden>
                    <input type="file" data-ic-folder multiple hidden>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                        <button type="button" class="btn btn-ghost" data-ic-pick>選擇檔案</button>
                        <button type="button" class="btn btn-ghost" data-ic-folder-btn>選擇資料夾</button>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:flex-end;">
                        <label style="flex:1;min-width:200px;">
                            <span style="display:block;font-weight:700;margin-bottom:6px;">圖片網址</span>
                            <input class="form-control" type="url" data-ic-url placeholder="https://example.com/photo.png">
                        </label>
                        <button type="button" class="btn btn-ghost" data-ic-add-url>加入網址</button>
                    </div>
                </div>
                <div class="ic-card">
                    <div class="ic-card-head"><span class="ic-step">2</span><strong>輸出設定</strong></div>
                    <div style="display:flex;gap:8px;margin:10px 0;">
                        <button type="button" class="btn btn-ghost ic-format-btn active" data-ic-target="jpg">JPEG</button>
                        <button type="button" class="btn btn-ghost ic-format-btn" data-ic-target="png">PNG</button>
                    </div>
                    <div data-ic-jpg-only>
                        <label style="display:block;font-weight:700;margin-bottom:6px;">
                            JPEG 品質 <span data-ic-quality-label>100%</span>
                        </label>
                        <input type="range" min="1" max="100" value="100" data-ic-quality style="width:100%;">
                        <label style="display:block;font-weight:700;margin:12px 0 6px;">透明底色（轉 JPEG）</label>
                        <select class="form-control" data-ic-bg>
                            <option value="#ffffff">白色（預設）</option>
                            <option value="#000000">黑色</option>
                            <option value="#f3f4f6">淺灰</option>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
                        <button type="button" class="btn btn-primary" data-ic-convert><i class="fa-solid fa-wand-magic-sparkles"></i> 開始轉換</button>
                        <button type="button" class="btn btn-ghost" data-ic-download>全部下載</button>
                        <button type="button" class="btn btn-ghost" data-ic-zip>下載 ZIP</button>
                    </div>
                    <p data-ic-status class="tool-muted" style="margin-top:10px;"></p>
                    <p data-ic-error style="color:#dc2626;"></p>
                </div>
            </div>
            <div style="margin-top:16px;">
                <h4 style="margin:0 0 10px;">佇列</h4>
                <div data-ic-list></div>
            </div>
        </section>
        <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js" defer></script>
        <script src="assets/js/tool-image-convert.js" defer></script>
    <?php elseif ($toolSubpage === 'image-voice'): ?>
        <section id="ivvTool" class="card">
            <div style="margin-bottom:16px;">
                <h3 class="card-title" style="margin-bottom:4px;"><i class="fa-solid fa-clapperboard"></i> 圖片 + 語音 = 影片</h3>
                <p style="color:var(--muted-text);line-height:1.6;margin:0;">
                    對齊 Appwrite ImageVoiceVideo。推薦<strong>伺服器一鍵生成</strong>（Google 多語 TTS，失敗時 Windows SAPI 備援；ffmpeg 嵌音軌並燒錄字幕）。
                    支援稿件語言／朗讀翻譯、瀏覽器預覽錄製、自備音訊合成。
                    參考 <a href="https://github.com/huang1988pioneer/ImageVoiceVideo" target="_blank" rel="noopener">ImageVoiceVideo</a>。
                </p>
            </div>
            <div data-ivv-env class="ybc-status-card" style="margin-bottom:14px;padding:12px 14px;border-radius:12px;border:1px solid var(--border-color);">檢查環境中…</div>
            <div class="ic-layout">
                <div class="ic-card">
                    <div class="ic-card-head"><span class="ic-step">1</span><strong>封面圖片</strong>
                        <button type="button" class="btn btn-ghost btn-sm" data-ivv-clear>清除</button>
                    </div>
                    <div class="ic-dropzone" data-ivv-drop>
                        <img data-ivv-preview alt="" style="display:none;max-width:100%;max-height:220px;border-radius:10px;">
                        <div data-ivv-drop-hint>拖放或點選圖片</div>
                    </div>
                    <input type="file" data-ivv-file accept="image/*" hidden>
                    <canvas data-ivv-canvas style="display:none;"></canvas>
                </div>
                <div class="ic-card">
                    <div class="ic-card-head"><span class="ic-step">2</span><strong>語音稿與設定</strong></div>
                    <label style="font-weight:700;display:block;margin-bottom:6px;">語音稿（每行一句）</label>
                    <textarea class="form-control" data-ivv-script rows="6" placeholder="第一句&#10;第二句"></textarea>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-top:12px;">
                        <label>
                            <span style="font-weight:700;">語速 <span data-ivv-rate-label>0</span></span>
                            <input type="range" min="-2" max="2" step="1" value="0" data-ivv-rate style="width:100%;">
                        </label>
                        <label>
                            <span style="font-weight:700;">稿件語言</span>
                            <select class="form-control" data-ivv-lang>
                                <option value="zh-TW">繁體中文</option>
                                <option value="zh-CN">簡體中文</option>
                                <option value="en-US">English</option>
                                <option value="ja-JP">日本語</option>
                                <option value="ko-KR">한국어</option>
                                <option value="yue-HK">廣東話</option>
                            </select>
                        </label>
                        <label>
                            <span style="font-weight:700;">朗讀語言（可翻譯）</span>
                            <select class="form-control" data-ivv-translate>
                                <option value="">與稿件相同</option>
                                <option value="zh-TW">→ 繁中</option>
                                <option value="zh-CN">→ 簡中</option>
                                <option value="en-US">→ English</option>
                                <option value="ja-JP">→ 日本語</option>
                                <option value="ko-KR">→ 한국어</option>
                                <option value="yue-HK">→ 廣東話</option>
                            </select>
                        </label>
                        <label>
                            <span style="font-weight:700;">聲線</span>
                            <select class="form-control" data-ivv-gender>
                                <option value="auto">自動（單一人臉）</option>
                                <option value="female">女聲</option>
                                <option value="male">男聲</option>
                            </select>
                        </label>
                        <label>
                            <span style="font-weight:700;">畫面方向</span>
                            <select class="form-control" data-ivv-orient>
                                <option value="auto">自動（依圖片）</option>
                                <option value="portrait">直式 9:16</option>
                                <option value="landscape">橫式 16:9</option>
                            </select>
                        </label>
                    </div>
                    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button type="button" class="btn btn-ghost btn-sm" data-ivv-do-translate><i class="fa-solid fa-language"></i> 預覽翻譯到朗讀語言</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-ivv-detect-gender><i class="fa-solid fa-user"></i> 偵測封面人臉聲線</button>
                        <span data-ivv-gender-hint class="tool-muted" style="font-size:0.86rem;"></span>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
                        <button type="button" class="btn btn-primary" data-ivv-generate><i class="fa-solid fa-wand-magic-sparkles"></i> 伺服器一鍵生成 MP4</button>
                        <button type="button" class="btn btn-ghost" data-ivv-record><i class="fa-solid fa-circle"></i> 瀏覽器預覽錄製</button>
                        <button type="button" class="btn btn-ghost" data-ivv-stop>停止</button>
                        <button type="button" class="btn btn-ghost" data-ivv-download disabled>下載結果</button>
                        <button type="button" class="btn btn-ghost" data-ivv-server><i class="fa-solid fa-file-audio"></i> 自備音訊合成</button>
                    </div>
                    <p data-ivv-status class="tool-muted" style="margin-top:10px;"></p>
                    <p data-ivv-error style="color:#dc2626;"></p>
                </div>
            </div>
            <video data-ivv-result controls style="display:none;width:100%;max-height:420px;margin-top:16px;border-radius:14px;background:#000;"></video>
        </section>
        <script src="assets/js/tool-ivv.js" defer></script>
    <?php elseif ($toolSubpage === 'video-merge'): ?>
        <section id="videoMergeTool" class="card">
            <div style="margin-bottom:16px;">
                <h3 class="card-title" style="margin-bottom:4px;"><i class="fa-solid fa-film"></i> 影片合併</h3>
                <p style="color:var(--muted-text);line-height:1.6;margin:0;">
                    上傳 2～12 段影片／音訊，伺服器以 ffmpeg 依序合併（對齊 Appwrite VideoMerge 的伺服器簡化版）。需本機安裝 ffmpeg。
                </p>
            </div>
            <div class="ybc-status-card" data-vm-env style="margin-bottom:14px;padding:12px 14px;border-radius:12px;border:1px solid var(--border-color);">檢查環境中…</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                <button type="button" class="btn btn-primary" data-vm-pick><i class="fa-solid fa-upload"></i> 選擇片段</button>
                <button type="button" class="btn btn-ghost" data-vm-clear>清除</button>
                <input type="file" data-vm-file accept="video/*,audio/*,.mp4,.webm,.mov,.mkv,.mp3,.m4a" multiple hidden>
                <label style="display:inline-flex;align-items:center;gap:8px;font-weight:700;">
                    輸出
                    <select class="form-control" data-vm-format style="width:auto;">
                        <option value="mp4">MP4</option>
                        <option value="mp3">MP3（僅音訊）</option>
                    </select>
                </label>
                <button type="button" class="btn btn-primary" data-vm-merge><i class="fa-solid fa-scissors"></i> 開始合併</button>
            </div>
            <label style="display:block;font-weight:700;margin-bottom:6px;">可選字幕腳本（每行一句，合併後均分時間燒錄；MP4 專用）</label>
            <textarea class="form-control" data-vm-subtitle rows="3" placeholder="可留空&#10;第一句字幕&#10;第二句字幕" style="margin-bottom:8px;"></textarea>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:center;">
                <label style="display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:0.86rem;">
                    Whisper 語言
                    <select class="form-control" data-vm-whisper-lang style="width:auto;display:inline-block;">
                        <option value="chinese">中文</option>
                        <option value="english">English</option>
                        <option value="japanese">日本語</option>
                        <option value="korean">한국어</option>
                        <option value="auto">自動</option>
                    </select>
                </label>
                <button type="button" class="btn btn-ghost btn-sm" data-vm-whisper><i class="fa-solid fa-microphone-lines"></i> Whisper 自動字幕（第一段；影片先 ffmpeg 抽音）</button>
                <span data-vm-whisper-status class="tool-muted" style="font-size:0.86rem;"></span>
            </div>
            <div data-vm-list></div>
            <p data-vm-status class="tool-muted" style="margin-top:10px;"></p>
            <p data-vm-error style="color:#dc2626;"></p>
        </section>
        <script src="assets/js/tool-video-merge.js" defer></script>
    <?php elseif ($toolSubpage === 'yt-bili'): ?>
        <section id="ytbiliTool" class="card">
            <div style="margin-bottom:16px;">
                <h3 class="card-title" style="margin-bottom:4px;"><i class="fa-brands fa-youtube"></i> YouTube / Bilibili 轉檔</h3>
                <p style="color:var(--muted-text);line-height:1.6;margin:0;">
                    伺服器端 yt-dlp + ffmpeg 轉成 MP3 或 MP4（對齊 Appwrite YoutubeBilibiliConvert）。
                    參考 <a href="https://github.com/huang1988pioneer/YoutubeBilibiliMP4MP3Converter" target="_blank" rel="noopener">YoutubeBilibiliMP4MP3Converter</a>。
                    YouTube 若遇驗證可貼上 Netscape cookies.txt。
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;padding:12px 14px;border-radius:12px;border:1px solid var(--border-color);background:var(--input-bg);">
                <span data-yb-status class="badge">檢查中</span>
                <span data-yb-status-note class="tool-muted" style="flex:1;"></span>
                <button type="button" class="btn btn-ghost btn-sm" data-yb-refresh>重新檢查</button>
            </div>
            <div class="ic-layout">
                <div class="ic-card">
                    <div class="ic-card-head"><span class="ic-step">1</span><strong>影片網址</strong></div>
                    <textarea class="form-control" data-yb-urls rows="7" placeholder="每行一個網址，最多 7 個&#10;https://www.youtube.com/watch?v=…&#10;https://www.bilibili.com/video/BV…"></textarea>
                </div>
                <div class="ic-card">
                    <div class="ic-card-head"><span class="ic-step">2</span><strong>輸出與 Cookies</strong></div>
                    <label style="font-weight:700;">格式</label>
                    <select class="form-control" data-yb-format style="margin:6px 0 12px;">
                        <option value="mp3">MP3</option>
                        <option value="mp4">MP4</option>
                    </select>
                    <div data-yb-quality-wrap style="display:none;">
                        <label style="font-weight:700;">MP4 畫質</label>
                        <select class="form-control" data-yb-quality style="margin:6px 0 12px;">
                            <option value="1080p">1080p</option>
                            <option value="720p">720p</option>
                        </select>
                    </div>
                    <label style="font-weight:700;">Cookies（選填，YouTube 驗證用）</label>
                    <textarea class="form-control" data-yb-cookies rows="5" placeholder="# Netscape HTTP Cookie File …" style="margin-top:6px;"></textarea>
                    <button type="button" class="btn btn-primary" style="margin-top:14px;" data-yb-convert>
                        <i class="fa-solid fa-download"></i> 開始轉檔並下載
                    </button>
                    <p data-yb-log class="tool-muted" style="margin-top:10px;"></p>
                    <p data-yb-error style="color:#dc2626;"></p>
                </div>
            </div>
        </section>
        <script src="assets/js/tool-ytbili.js" defer></script>
    <?php elseif ($toolSubpage === 'tube'): ?>
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

        <?php if (($_GET['tube_import_error'] ?? '') === '1'): ?>
            <div class="tube-import-error" role="alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>CSV 中沒有任何可匯入的頻道：可能是檔案格式不符，或所包含的都是已下架的預設頻道。請確認每列至少含有頻道網址（alias,sourceUrl）。</span>
            </div>
        <?php endif; ?>

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
                    <p>可編輯頻道別名與網址。別名留空時，預設使用 YouTube 原頻道名稱。支援 CSV 匯出／匯入（alias,sourceUrl）。</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <form method="post">
                        <input type="hidden" name="tube_action" value="export_csv">
                        <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-download"></i> 匯出 CSV</button>
                    </form>
                    <form method="post" enctype="multipart/form-data" style="display:inline-flex;gap:6px;align-items:center;">
                        <input type="hidden" name="tube_action" value="import_csv">
                        <input type="file" name="tube_csv" accept=".csv,text/csv" required style="max-width:180px;">
                        <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-upload"></i> 匯入</button>
                    </form>
                    <form method="post" onsubmit="return confirm('還原預設頻道？目前自訂清單會被清除。');">
                        <input type="hidden" name="tube_action" value="reset">
                        <button type="submit" class="btn btn-ghost">
                            <i class="fa-solid fa-rotate-left"></i> 還原預設
                        </button>
                    </form>
                </div>
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
            <form method="post" id="tubeBulkDeleteForm" onsubmit="return confirmTubeBulkDelete();">
                <input type="hidden" name="tube_action" value="bulk_delete">
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0;">
                    <label style="display:inline-flex;gap:6px;align-items:center;font-size:0.88rem;">
                        <input type="checkbox" id="tubeSelectAll" onchange="toggleTubeSelectAll(this)"> 全選
                    </label>
                    <button type="submit" class="btn btn-sm btn-danger" id="tubeBulkDeleteBtn" disabled>
                        <i class="fa-solid fa-trash"></i> 刪除選取
                    </button>
                    <span id="tubeBulkCount" class="tool-muted" style="font-size:0.82rem;"></span>
                </div>
                <div class="tube-channel-admin-list">
                    <?php foreach ($tubeChannels as $idx => $adminChannel): ?>
                        <?php $displayName = trim((string) ($adminChannel['name'] ?? '')); ?>
                        <div class="tube-channel-admin-item">
                            <label style="display:flex;align-items:flex-start;gap:10px;flex:1;min-width:0;margin:0;cursor:pointer;">
                                <input type="checkbox" name="channel_indexes[]" value="<?php echo (int) $idx; ?>" class="tube-item-cb" onchange="syncTubeBulkState()">
                                <span>
                                    <strong><?php echo htmlspecialchars($displayName !== '' ? $displayName : '使用原頻道名稱'); ?></strong>
                                    <span><?php echo htmlspecialchars($adminChannel['url'] ?? ''); ?></span>
                                </span>
                            </label>
                            <div class="tube-channel-admin-actions">
                                <button type="button" class="btn btn-sm" onclick="editTubeChannel(<?php echo (int) $idx; ?>, <?php echo json_encode($displayName, JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode($adminChannel['url'] ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>)">編輯</button>
                                <button type="submit" class="btn btn-sm btn-danger" form="tubeSingleDelete<?php echo (int) $idx; ?>" onclick="return confirm('刪除此頻道？');"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
            <?php foreach ($tubeChannels as $idx => $adminChannel): ?>
                <form method="post" id="tubeSingleDelete<?php echo (int) $idx; ?>">
                    <input type="hidden" name="tube_action" value="delete">
                    <input type="hidden" name="channel_index" value="<?php echo (int) $idx; ?>">
                </form>
            <?php endforeach; ?>
        </section>

        <?php
        $downfallUpdate = $tubeData['downfallIndexUpdate'] ?? null;
        $downfallHistory = $tubeData['downfallHistory'] ?? [];
        $downfallPrices = array_values(array_filter(array_map(static function ($p) {
            return isset($p['price']) && is_numeric($p['price']) ? (float) $p['price'] : null;
        }, $downfallHistory)));
        $downfallIntervalDays = $tubeData['downfallPublishIntervalDays'] ?? null;
        if ($downfallIntervalDays === null && count($downfallHistory) >= 2) {
            $downfallIntervalDays = fengbroTubeDownfallPublishIntervalDays($downfallHistory);
        }
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
                <?php if ($downfallIntervalDays !== null): ?>
                    <div class="tube-downfall-interval" role="status">
                        <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                        最近兩次發布間隔：<strong><?php echo (int) $downfallIntervalDays; ?></strong> 天
                    </div>
                <?php endif; ?>
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
        $featuredIds = $financeConfig['featuredIds'] ?? [];
        $featuredSet = array_flip($featuredIds);
        $imageById = $financeConfig['imageById'] ?? [];
        $availableDefaults = array_values(array_filter(
            $financeCatalog,
            static fn($item) => !isset($selectedDefaultSet[$item['id']])
        ));
        // 精選排在報價列表前
        $quotes = $financeData['quotes'] ?? [];
        if ($featuredIds && $quotes) {
            usort($quotes, static function ($a, $b) use ($featuredSet) {
                $ai = isset($featuredSet[$a['id'] ?? '']) ? 0 : 1;
                $bi = isset($featuredSet[$b['id'] ?? '']) ? 0 : 1;
                return $ai <=> $bi;
            });
            $financeData['quotes'] = $quotes;
        }
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
                    <p>可開關預設標的、新增 Yahoo/CNBC 自訂標的。指數／股票可設定<strong>連結圖片</strong>；自訂標的另可填 YouTube、Bilibili、自訂網址（對齊 Appwrite）。CSV 欄位：<code>id,name,symbol,provider,group,imageUrls,youtubeUrl,bilibiliUrl,relatedLinks,featured</code>（多值以 <code>;</code> 分隔；自訂網址可用 <code>標籤|網址</code>）。</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <form method="post">
                        <input type="hidden" name="finance_action" value="export_csv">
                        <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-download"></i> 匯出 CSV（含圖片／連結）</button>
                    </form>
                    <form method="post" enctype="multipart/form-data" style="display:inline-flex;gap:6px;align-items:center;">
                        <input type="hidden" name="finance_action" value="import_csv">
                        <input type="file" name="finance_csv" accept=".csv,text/csv" required style="max-width:180px;">
                        <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-upload"></i> 匯入 CSV</button>
                    </form>
                    <form method="post" onsubmit="return confirm('還原全部預設標的並清除自訂標的？');">
                        <input type="hidden" name="finance_action" value="reset">
                        <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-rotate-left"></i> 還原預設</button>
                    </form>
                </div>
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
                    <form method="post" class="finance-custom-form" id="financeCustomForm">
                        <input type="hidden" name="finance_action" value="save_custom">
                        <input class="form-control" type="text" name="custom_name" id="financeCustomName" placeholder="名稱（可留空，可自動解析）">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <input class="form-control" style="flex:1;min-width:140px;" type="text" name="custom_symbol" id="financeCustomSymbol" placeholder="代碼，例如 NVDA / 2330.TW" required>
                            <button type="button" class="btn btn-ghost" id="financeResolveNameBtn" title="從 Yahoo 解析顯示名稱">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> 解析名稱
                            </button>
                        </div>
                        <select class="form-control" name="custom_provider" id="financeCustomProvider">
                            <option value="yahoo">Yahoo</option>
                            <option value="cnbc">CNBC</option>
                        </select>
                        <select class="form-control" name="custom_group">
                            <?php foreach (['Taiwan','Asia','Korea','FX','Commodities','Rates','US','Crypto'] as $g): ?>
                                <option value="<?php echo $g; ?>" <?php echo $g === 'US' ? 'selected' : ''; ?>><?php echo $g; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label style="font-size:0.82rem;font-weight:700;color:var(--muted-text);">圖片網址（可選，每行一張，最多 9 張）</label>
                        <textarea class="form-control" name="custom_image_urls" id="financeCustomImageUrls" rows="3" placeholder="https://example.com/logo.png&#10;https://…/另一張圖.jpg"></textarea>
                        <label style="font-size:0.82rem;font-weight:700;color:var(--muted-text);">YouTube（可選）</label>
                        <input class="form-control" type="url" name="custom_youtube_url" id="financeCustomYoutube" placeholder="https://www.youtube.com/…">
                        <label style="font-size:0.82rem;font-weight:700;color:var(--muted-text);">Bilibili（可選）</label>
                        <input class="form-control" type="url" name="custom_bilibili_url" id="financeCustomBilibili" placeholder="https://www.bilibili.com/…">
                        <label style="font-size:0.82rem;font-weight:700;color:var(--muted-text);">自訂網址（可選，每行一個；可用 標籤|網址）</label>
                        <textarea class="form-control" name="custom_related_links" id="financeCustomRelatedLinks" rows="2" placeholder="https://www.ptt.cc/bbs/Stock/index.html&#10;PTT 股板|https://www.ptt.cc/bbs/Stock/index.html"></textarea>
                        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-plus"></i> 儲存自訂標的</button>
                        <p id="financeResolveHint" class="tool-muted" style="margin:6px 0 0;font-size:0.86rem;"></p>
                    </form>
                    <form method="post" id="financeBulkDeleteForm" onsubmit="return confirmFinanceBulkDelete();" style="margin-top:12px;">
                        <input type="hidden" name="finance_action" value="bulk_delete_custom">
                        <?php if ($customInstruments): ?>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px;">
                                <label style="display:inline-flex;gap:6px;align-items:center;font-size:0.88rem;">
                                    <input type="checkbox" id="financeSelectAll" onchange="toggleFinanceSelectAll(this)"> 全選
                                </label>
                                <button type="submit" class="btn btn-sm btn-danger" id="financeBulkDeleteBtn" disabled>
                                    <i class="fa-solid fa-trash"></i> 刪除選取
                                </button>
                            </div>
                        <?php endif; ?>
                        <div class="finance-chip-list">
                            <?php if (!$customInstruments): ?>
                                <p style="color:var(--muted-text);margin:0;">尚未新增自訂標的。</p>
                            <?php endif; ?>
                            <?php foreach ($customInstruments as $custom): ?>
                                <label class="finance-chip" style="cursor:pointer;">
                                    <input type="checkbox" name="instrument_ids[]" value="<?php echo htmlspecialchars($custom['id']); ?>" class="finance-item-cb" onchange="syncFinanceBulkState()">
                                    <span><?php echo htmlspecialchars($custom['name']); ?> <small><?php echo htmlspecialchars($custom['symbol']); ?></small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </form>
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
                $qid = (string) ($quote['id'] ?? '');
                $isFeatured = $qid !== '' && isset($featuredSet[$qid]);
                $cardImages = [];
                if (!empty($quote['imageUrls']) && is_array($quote['imageUrls'])) {
                    $cardImages = fengbroFinanceNormalizeImageUrls($quote['imageUrls']);
                } elseif (!empty($quote['imageUrl'])) {
                    $cardImages = fengbroFinanceNormalizeImageUrls([$quote['imageUrl']]);
                } elseif ($qid !== '' && !empty($imageById[$qid])) {
                    $cardImages = fengbroFinanceNormalizeImageUrls($imageById[$qid]);
                }
                $imageEditText = $cardImages ? implode("\n", $cardImages) : '';
                ?>
                <section class="finance-card <?php echo $tone; ?><?php echo $isFeatured ? ' is-featured' : ''; ?>">
                    <div class="finance-card-head">
                        <div>
                            <span class="finance-group"><?php echo htmlspecialchars($quote['group']); ?><?php echo !empty($quote['isCustom']) ? ' · 自訂' : ''; ?><?php echo $isFeatured ? ' · 精選' : ''; ?></span>
                            <h3><?php echo htmlspecialchars($quote['name']); ?></h3>
                            <?php if (!empty($quote['localLabel'])): ?>
                                <div style="color:var(--muted-text);font-size:0.82rem;margin-bottom:4px;"><?php echo htmlspecialchars($quote['localLabel']); ?></div>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars($quote['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($quote['symbol']); ?> · <?php echo htmlspecialchars($quote['source'] ?? ''); ?></a>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                            <?php if ($qid !== ''): ?>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="finance_action" value="toggle_featured">
                                <input type="hidden" name="instrument_id" value="<?php echo htmlspecialchars($qid); ?>">
                                <button type="submit" class="btn btn-sm btn-ghost" title="<?php echo $isFeatured ? '取消精選' : '加入精選（最多9）'; ?>">
                                    <i class="fa-<?php echo $isFeatured ? 'solid' : 'regular'; ?> fa-star"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (!empty($quote['status'])): ?>
                                <strong class="finance-status <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($quote['status']); ?>
                                </strong>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($cardImages): ?>
                        <div class="finance-carousel" data-finance-carousel>
                            <img
                                class="finance-card-image"
                                src="<?php echo htmlspecialchars($cardImages[0]); ?>"
                                alt="<?php echo htmlspecialchars(($quote['name'] ?? '') . ' image'); ?>"
                                loading="lazy"
                                data-finance-carousel-img
                                data-urls="<?php echo htmlspecialchars(json_encode(array_values($cardImages), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                data-index="0"
                                title="<?php echo count($cardImages) > 1 ? '點擊切換下一張' : ''; ?>"
                            >
                            <?php if (count($cardImages) > 1): ?>
                                <div class="finance-carousel-dots" role="tablist">
                                    <?php foreach ($cardImages as $dotIndex => $_url): ?>
                                        <button type="button" class="finance-carousel-dot<?php echo $dotIndex === 0 ? ' is-active' : ''; ?>" data-finance-dot="<?php echo (int) $dotIndex; ?>" aria-label="第 <?php echo (int) ($dotIndex + 1); ?> 張"></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($qid !== ''): ?>
                        <details class="finance-image-edit">
                            <summary><i class="fa-regular fa-image"></i> 圖片網址<?php echo $cardImages ? '（' . count($cardImages) . '）' : ''; ?></summary>
                            <form method="post" class="finance-image-edit-form">
                                <input type="hidden" name="finance_action" value="set_images">
                                <input type="hidden" name="instrument_id" value="<?php echo htmlspecialchars($qid); ?>">
                                <textarea class="form-control" name="image_urls" rows="3" placeholder="每行一張圖片網址（http/https），最多 9 張。清空後儲存可移除。"><?php echo htmlspecialchars($imageEditText); ?></textarea>
                                <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-check"></i> 儲存圖片</button>
                            </form>
                        </details>
                    <?php endif; ?>

                    <?php
                    $ytUrl = trim((string) ($quote['youtubeUrl'] ?? ''));
                    $biliUrl = trim((string) ($quote['bilibiliUrl'] ?? ''));
                    $relatedLinks = is_array($quote['relatedLinks'] ?? null) ? $quote['relatedLinks'] : [];
                    $hasMediaLinks = $ytUrl !== '' || $biliUrl !== '' || $relatedLinks;
                    ?>
                    <?php if ($hasMediaLinks): ?>
                        <div class="finance-link-chips">
                            <?php if ($ytUrl !== ''): ?>
                                <a class="finance-link-chip yt" href="<?php echo htmlspecialchars($ytUrl); ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-brands fa-youtube"></i> YouTube
                                </a>
                            <?php endif; ?>
                            <?php if ($biliUrl !== ''): ?>
                                <a class="finance-link-chip bili" href="<?php echo htmlspecialchars($biliUrl); ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-solid fa-play"></i> Bilibili
                                </a>
                            <?php endif; ?>
                            <?php foreach ($relatedLinks as $rel): ?>
                                <?php if (empty($rel['url'])) continue; ?>
                                <a class="finance-link-chip ext" href="<?php echo htmlspecialchars((string) $rel['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars((string) $rel['url']); ?>">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    <?php echo htmlspecialchars((string) ($rel['label'] ?? '連結')); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

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
    <?php elseif ($toolSubpage === 'phone'): ?>
    <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
        <section class="card">
            <div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px;">
                <div style="width: 46px; height: 46px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: var(--accent-soft); color: var(--accent);">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <div>
                    <h3 class="card-title" style="margin-bottom: 4px;">手機比價</h3>
                    <p style="color: var(--muted-text); line-height: 1.6;">自動抓取地標網通與傑昇通信價格，合併比對最佳通路（對齊 Appwrite landtop 分頁）。</p>
                    <p style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <a class="btn btn-ghost btn-sm" href="media_tools_api.php?action=phone_history_csv">
                            <i class="fa-solid fa-download"></i> 匯出歷史價格 CSV
                        </a>
                        <label class="btn btn-ghost btn-sm" style="cursor:pointer;margin:0;">
                            <i class="fa-solid fa-upload"></i> 匯入歷史 CSV
                            <input type="file" id="phoneHistoryCsvFile" accept=".csv,text/csv" hidden>
                        </label>
                        <span id="phoneHistoryCsvHint" class="tool-muted" style="font-size:0.82rem;"></span>
                    </p>
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
            <a class="btn btn-ghost" href="index.php?page=tools&tool=price">
                <i class="fa-solid fa-tags"></i> 鋒兄比價（BigGo）
            </a>
        </div>
    </section>

    <section class="card" style="margin-top: 20px;">
        <h3 class="card-title">查詢結果與歷史快照</h3>
        <div id="toolResult" class="tool-result-box">
            <p style="color: var(--muted-text);">查詢後會在這裡顯示通路比價結果與歷史快照。</p>
        </div>
    </section>
    <?php else: ?>
    <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
        <section class="card">
            <div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px;">
                <div style="width: 46px; height: 46px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: var(--warning-soft); color: #b45309;">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </div>
                <div>
                    <h3 class="card-title" style="margin-bottom: 4px;">鋒兄比價</h3>
                    <p style="color: var(--muted-text); line-height: 1.6;">貼上商品關鍵字或網址；API 可用時抓取價格，否則保留 BigGo 查詢連結與歷史快照。</p>
                    <p style="margin-top:8px;">
                        <a class="btn btn-ghost btn-sm" href="index.php?page=tools&tool=phone"><i class="fa-solid fa-mobile-screen"></i> 手機通路比價</a>
                    </p>
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
    </div>

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

    .tool-muted { color: var(--muted-text); }

    .mp-layout {
        display: grid;
        grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
        gap: 18px;
    }
    @media (max-width: 800px) {
        .mp-layout { grid-template-columns: 1fr; }
    }
    .mp-product-list { display: grid; gap: 8px; }
    .mp-product-item {
        display: grid; gap: 4px; text-align: left; width: 100%;
        padding: 10px 12px; border-radius: 12px;
        border: 1px solid var(--border-color); background: var(--input-bg);
        color: var(--text-color); cursor: pointer;
    }
    .mp-product-item strong { font-size: 0.95rem; }
    .mp-product-item span { color: var(--muted-text); font-size: 0.8rem; }
    .mp-product-item.is-active {
        border-color: var(--accent); background: var(--accent-soft);
    }
    .mp-form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px; align-items: end; margin-bottom: 8px;
    }
    .mp-form-row label { font-weight: 700; font-size: 0.86rem; }
    .mp-detail-head {
        display: flex; justify-content: space-between; gap: 12px;
        flex-wrap: wrap; align-items: flex-start; margin-bottom: 12px;
    }

    .news-sites-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 8px; margin-top: 10px;
    }
    .news-site-item {
        display: flex; gap: 10px; align-items: flex-start;
        padding: 10px 12px; border: 1px solid var(--border-color);
        border-radius: 12px; background: var(--input-bg); cursor: pointer;
    }
    .news-site-item strong { display: block; }
    .news-site-item small { color: var(--muted-text); }
    .news-article-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 12px; margin-top: 12px;
    }
    .news-article-card {
        display: grid; gap: 8px; padding: 14px;
        border: 1px solid var(--border-color); border-radius: 16px;
        background: var(--input-bg); color: var(--text-color); text-decoration: none;
    }
    .news-article-card:hover { border-color: var(--accent); }
    .news-article-meta {
        display: flex; justify-content: space-between; gap: 8px;
        color: var(--muted-text); font-size: 0.78rem; font-weight: 700;
    }
    .news-article-card p {
        margin: 0; color: var(--muted-text); font-size: 0.86rem; line-height: 1.45;
    }
    .bento-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 12px; margin-top: 12px;
    }
    .bento-store-card {
        padding: 14px; border: 1px solid var(--border-color);
        border-radius: 14px; background: var(--input-bg);
    }
    .bento-store-card p { margin: 8px 0 0; color: var(--muted-text); font-size: 0.88rem; line-height: 1.5; }
    .bento-hint {
        display: inline-flex; margin-left: 8px; padding: 2px 8px;
        border-radius: 999px; background: var(--accent-soft); color: var(--accent);
        font-size: 0.75rem; font-weight: 800;
    }
    .bento-head {
        display: flex; justify-content: space-between; gap: 12px;
        flex-wrap: wrap; align-items: flex-start;
    }
    .bento-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    .pop-meta {
        display: flex; justify-content: space-between; gap: 10px;
        flex-wrap: wrap; align-items: center; margin-bottom: 12px;
    }
    .pop-meta strong { font-size: 1.05rem; }
    .pop-table-wrap { overflow-x: auto; margin-bottom: 14px; }
    .pop-table {
        width: 100%; border-collapse: collapse; min-width: 320px;
        font-size: 0.92rem;
    }
    .pop-table th, .pop-table td {
        padding: 10px 12px; border-bottom: 1px solid var(--border-color);
        text-align: right; white-space: nowrap;
    }
    .pop-table th:first-child, .pop-table td:first-child { text-align: left; }
    .pop-table th {
        color: var(--muted-text); font-weight: 800; font-size: 0.8rem;
        letter-spacing: 0.02em;
    }
    .pop-table tbody tr:last-child td { border-bottom: none; }
    .pop-delta-up { color: #2563eb; font-weight: 800; }
    .pop-delta-down { color: #dc2626; font-weight: 800; }
    .pop-delta-flat { color: #059669; font-weight: 800; }
    .pop-chart-card {
        padding: 12px 12px 8px; border: 1px solid var(--border-color);
        border-radius: 14px; background: var(--input-bg);
    }
    .pop-chart-card h4 {
        margin: 0 0 8px; font-size: 0.92rem; font-weight: 800;
    }
    .pop-chart-svg { width: 100%; height: auto; display: block; }
    .pop-chart-footnote {
        margin: 8px 0 0; color: var(--muted-text); font-size: 0.78rem; line-height: 1.45;
    }
    .pop-sources {
        margin: 10px 0 0; display: flex; gap: 8px; flex-wrap: wrap;
    }
    .pop-sources a {
        font-size: 0.8rem; color: var(--accent); text-decoration: none; font-weight: 700;
    }
    .pop-sources a:hover { text-decoration: underline; }

    .ic-layout {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
    }
    .ic-card {
        padding: 14px; border: 1px solid var(--border-color);
        border-radius: 16px; background: var(--input-bg);
    }
    .ic-card-head {
        display: flex; align-items: center; gap: 10px; margin-bottom: 12px;
    }
    .ic-card-head .btn { margin-left: auto; }
    .ic-step {
        width: 28px; height: 28px; border-radius: 999px;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--accent); color: #fff; font-weight: 900; font-size: 0.85rem;
    }
    .ic-dropzone {
        border: 2px dashed var(--border-color); border-radius: 14px;
        padding: 28px 16px; text-align: center; cursor: pointer;
        background: var(--card-bg);
    }
    .ic-dropzone.is-dragover { border-color: var(--accent); background: var(--accent-soft); }
    .ic-format-btn.active {
        background: var(--accent) !important; border-color: var(--accent) !important; color: #fff !important;
    }
    .ic-item {
        display: grid; grid-template-columns: 64px minmax(0, 1fr) auto;
        gap: 12px; align-items: center; padding: 10px;
        border: 1px solid var(--border-color); border-radius: 14px;
        margin-bottom: 8px; background: var(--input-bg);
    }
    .ic-thumb {
        width: 64px; height: 64px; object-fit: cover; border-radius: 10px; background: var(--border-color);
    }
    .ic-thumb-empty { background: var(--table-header-bg); }
    .ic-item-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; justify-content: flex-end; }
    .badge {
        display: inline-flex; padding: 3px 8px; border-radius: 999px;
        font-size: 0.75rem; font-weight: 800; background: var(--table-header-bg);
    }
    .badge-success { background: rgba(16,185,129,.14); color: #047857; }
    .badge-danger { background: rgba(239,68,68,.14); color: #b91c1c; }
    .badge-warning { background: rgba(245,158,11,.16); color: #b45309; }

    [data-yb-status].is-ready,
    [data-vm-env].is-ready { color: #047857; font-weight: 800; }
    [data-yb-status].is-missing,
    [data-vm-env].is-missing { color: #b91c1c; font-weight: 800; }

    .vm-item {
        display: grid; grid-template-columns: 36px minmax(0,1fr) auto;
        gap: 10px; align-items: center; padding: 10px 12px;
        border: 1px solid var(--border-color); border-radius: 12px;
        background: var(--input-bg); margin-bottom: 8px;
    }
    .vm-idx {
        width: 28px; height: 28px; border-radius: 999px;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--accent); color: #fff; font-weight: 900; font-size: 0.85rem;
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

    .tube-import-error {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-top: 16px;
        padding: 14px 16px;
        border: 1px solid rgba(220, 38, 38, 0.3);
        border-radius: 18px;
        background: rgba(254, 226, 226, 0.6);
        color: #991b1b;
        font-size: 0.92rem;
        line-height: 1.6;
    }

    .tube-import-error i {
        margin-top: 2px;
        color: #dc2626;
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

    .finance-card.is-featured {
        box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.35), 0 12px 26px var(--shadow);
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

    .finance-carousel {
        display: grid;
        gap: 8px;
    }

    .finance-card-image {
        display: block;
        width: 100%;
        aspect-ratio: 16 / 9;
        object-fit: cover;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        background: var(--input-bg);
        cursor: pointer;
    }

    .finance-carousel-dots {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        justify-content: center;
    }

    .finance-carousel-dot {
        width: 8px;
        height: 8px;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: var(--border-color);
        cursor: pointer;
    }

    .finance-carousel-dot.is-active {
        background: var(--accent);
        transform: scale(1.15);
    }

    .finance-image-edit {
        border: 1px dashed var(--border-color);
        border-radius: 12px;
        padding: 8px 10px;
        background: var(--input-bg);
    }

    .finance-image-edit summary {
        cursor: pointer;
        font-size: 0.82rem;
        font-weight: 800;
        color: var(--muted-text);
        list-style: none;
    }

    .finance-image-edit summary::-webkit-details-marker {
        display: none;
    }

    .finance-image-edit-form {
        display: grid;
        gap: 8px;
        margin-top: 10px;
    }

    .finance-link-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .finance-link-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px solid var(--border-color);
        font-size: 0.78rem;
        font-weight: 800;
        text-decoration: none;
        line-height: 1.2;
        background: var(--input-bg);
        color: var(--text-color);
    }

    .finance-link-chip:hover {
        filter: brightness(0.97);
    }

    .finance-link-chip.yt {
        border-color: rgba(220, 38, 38, 0.25);
        background: rgba(254, 226, 226, 0.65);
        color: #b91c1c;
    }

    .finance-link-chip.bili {
        border-color: rgba(14, 165, 233, 0.28);
        background: rgba(224, 242, 254, 0.7);
        color: #0369a1;
    }

    .finance-link-chip.ext {
        border-color: rgba(217, 119, 6, 0.28);
        background: rgba(255, 251, 235, 0.8);
        color: #b45309;
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

    .tube-downfall-interval {
        margin-top: 12px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--accent) 12%, transparent);
        border: 1px solid color-mix(in srgb, var(--accent) 28%, transparent);
        color: var(--text);
        font-size: 0.9rem;
        font-weight: 600;
    }

    .tube-downfall-interval i {
        color: var(--accent);
    }

    .tube-downfall-interval strong {
        font-size: 1.05rem;
        font-variant-numeric: tabular-nums;
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

    function syncTubeBulkState() {
        const boxes = Array.from(document.querySelectorAll('.tube-item-cb'));
        const selected = boxes.filter(function (cb) { return cb.checked; }).length;
        const all = document.getElementById('tubeSelectAll');
        const btn = document.getElementById('tubeBulkDeleteBtn');
        const count = document.getElementById('tubeBulkCount');
        if (all) {
            all.checked = boxes.length > 0 && selected === boxes.length;
            all.indeterminate = selected > 0 && selected < boxes.length;
        }
        if (btn) btn.disabled = selected === 0;
        if (count) count.textContent = selected ? ('已選 ' + selected + ' 個') : '';
    }
    function toggleTubeSelectAll(source) {
        document.querySelectorAll('.tube-item-cb').forEach(function (cb) { cb.checked = !!source.checked; });
        syncTubeBulkState();
    }
    function confirmTubeBulkDelete() {
        const selected = document.querySelectorAll('.tube-item-cb:checked').length;
        if (!selected) return false;
        return confirm('確定刪除選取的 ' + selected + ' 個頻道？');
    }
    function syncFinanceBulkState() {
        const boxes = Array.from(document.querySelectorAll('.finance-item-cb'));
        const selected = boxes.filter(function (cb) { return cb.checked; }).length;
        const all = document.getElementById('financeSelectAll');
        const btn = document.getElementById('financeBulkDeleteBtn');
        if (all) {
            all.checked = boxes.length > 0 && selected === boxes.length;
            all.indeterminate = selected > 0 && selected < boxes.length;
        }
        if (btn) btn.disabled = selected === 0;
    }
    function toggleFinanceSelectAll(source) {
        document.querySelectorAll('.finance-item-cb').forEach(function (cb) { cb.checked = !!source.checked; });
        syncFinanceBulkState();
    }
    function confirmFinanceBulkDelete() {
        const selected = document.querySelectorAll('.finance-item-cb:checked').length;
        if (!selected) return false;
        return confirm('確定刪除選取的 ' + selected + ' 個自訂標的？');
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') return;
        if (event.target && event.target.id === 'priceQuery') runBigGoLookup();
        if (event.target && event.target.id === 'phoneQuery') runPhoneCompare();
    });

    // 金融自訂標的：解析顯示名稱
    (function initFinanceResolveName() {
        const btn = document.getElementById('financeResolveNameBtn');
        const symbolInput = document.getElementById('financeCustomSymbol');
        const nameInput = document.getElementById('financeCustomName');
        const providerSel = document.getElementById('financeCustomProvider');
        const hint = document.getElementById('financeResolveHint');
        if (!btn || !symbolInput) return;
        btn.addEventListener('click', function () {
            const symbol = (symbolInput.value || '').trim();
            if (!symbol) {
                if (hint) hint.textContent = '請先輸入代碼';
                return;
            }
            if (hint) hint.textContent = '解析中…';
            btn.disabled = true;
            fetch('tools_api.php?action=finance_resolve_name', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    symbol: symbol,
                    provider: providerSel ? providerSel.value : 'yahoo'
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success || !data.name) throw new Error(data.error || '無法解析');
                    if (nameInput) nameInput.value = data.name;
                    if (hint) hint.textContent = '已解析：' + data.name + (data.source ? '（' + data.source + '）' : '');
                })
                .catch(function (err) {
                    if (hint) hint.textContent = err.message || '解析失敗';
                })
                .finally(function () { btn.disabled = false; });
        });
    })();

    // 手機歷史 CSV 匯入
    (function initPhoneHistoryCsvImport() {
        const input = document.getElementById('phoneHistoryCsvFile');
        const hint = document.getElementById('phoneHistoryCsvHint');
        if (!input) return;
        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) return;
            if (hint) hint.textContent = '匯入中…';
            const fd = new FormData();
            fd.append('csv', file, file.name);
            fetch('tools_api.php?action=phone_history_import', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success) throw new Error(data.error || '匯入失敗');
                    if (hint) hint.textContent = '已匯入 ' + Number(data.imported || 0) + ' 筆快照';
                })
                .catch(function (err) {
                    if (hint) hint.textContent = err.message || '匯入失敗';
                })
                .finally(function () { input.value = ''; });
        });
    })();

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

    // Finance image carousel: click image or dots to cycle linked images
    document.querySelectorAll('[data-finance-carousel]').forEach(function (carousel) {
        const img = carousel.querySelector('[data-finance-carousel-img]');
        if (!img) return;
        let urls = [];
        try {
            urls = JSON.parse(img.getAttribute('data-urls') || '[]') || [];
        } catch (e) {
            urls = [];
        }
        if (!urls.length) return;

        function setIndex(next) {
            const idx = ((next % urls.length) + urls.length) % urls.length;
            img.setAttribute('data-index', String(idx));
            img.src = urls[idx];
            carousel.querySelectorAll('[data-finance-dot]').forEach(function (dot) {
                const di = Number(dot.getAttribute('data-finance-dot') || 0);
                dot.classList.toggle('is-active', di === idx);
            });
        }

        img.addEventListener('click', function () {
            if (urls.length < 2) return;
            setIndex(Number(img.getAttribute('data-index') || 0) + 1);
        });
        carousel.querySelectorAll('[data-finance-dot]').forEach(function (dot) {
            dot.addEventListener('click', function () {
                setIndex(Number(dot.getAttribute('data-finance-dot') || 0));
            });
        });
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
