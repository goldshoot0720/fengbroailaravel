<?php $pageTitle = '鋒兄關於'; ?>

<?php
// ── 網站統計（對齊 Appwrite：進站人次／連續進站天數／營運天數）───────────────
require_once __DIR__ . '/../includes/site_stats.php';
$sitePdo = getConnection();
$siteVisitRow = fengbroGetSiteVisitRow($sitePdo);
$siteVisitExists = $siteVisitRow !== null;
$siteVisitCount = $siteVisitRow ? (int) $siteVisitRow['count'] : 0;
$siteStreak = $siteVisitRow ? fengbroDisplaySiteVisitStreak($siteVisitRow) : 0;
$siteDays = fengbroSiteDaysSinceOrigin();
$menuUsageItems = array_slice(fengbroGetMenuUsageItems($sitePdo, 100), 0, 5);
?>

<div class="content-header">
    <h1>鋒兄關於</h1>
</div>

<div class="content-body">
    <div class="about-stats-banner">
        <div class="about-stats-banner-copy">
            <span class="eyebrow">Fengbro System Docs</span>
            <h2>鋒兄系統文件中心</h2>
            <p>這裡不是品牌介紹頁，而是專案現況入口。你可以直接看到最近改了什麼、系統怎麼分層、各可導覽葉模組目前各自負責什麼。</p>
        </div>
        <div class="about-stats-banner-cards">
            <div class="about-stat-card">
                <span class="about-stat-label">網站營運天數</span>
                <strong><?php echo $siteDays > 0 ? $siteDays . ' 天' : '—'; ?></strong>
                <small>自起源日 2025-09-28（承繼 nextshadcn20250928）起算</small>
            </div>
            <div class="about-stat-card">
                <span class="about-stat-label">進站人次</span>
                <strong><?php echo $siteVisitExists ? number_format($siteVisitCount) : '—'; ?></strong>
                <small><?php echo $siteVisitExists ? '每個瀏覽器 session 計一次' : '尚未有 sitevisit 紀錄，進站時自動累計'; ?></small>
            </div>
            <div class="about-stat-card">
                <span class="about-stat-label">連續進站天數</span>
                <strong><?php echo $siteVisitExists ? $siteStreak . ' 天' : '—'; ?></strong>
                <small><?php
                    if (!$siteVisitExists) {
                        echo '進站後開始累計';
                    } elseif ($siteStreak > 0) {
                        echo '以台北時間日曆日連續進站';
                    } else {
                        echo '中斷一日後會從 1 重新計算';
                    }
                ?></small>
            </div>
        </div>
    </div>

    <div class="card about-usage-card" style="margin-top: 20px;">
        <h3 class="card-title"><i class="fa-solid fa-chart-simple"></i> 選單使用與銀行存款統計</h3>
        <p style="color: var(--muted-text); font-size: 0.9rem; margin-bottom: 14px;">選單點擊次數跟銀行存款現況都依實際使用與資料自動更新。銀行最高／最低存款指「總存款」的歷史極值，會跟上次使用網站的紀錄比對。</p>
        <div class="about-usage-grid">
            <div>
                <h4 style="font-size: 0.95rem; margin-bottom: 10px;">選單使用次數與頻率（Top 5）</h4>
                <?php if (empty($menuUsageItems)): ?>
                    <p style="color: var(--muted-text); font-size: 0.9rem;">尚沒有選單使用紀錄，可能是還沒切換過其他頁面。</p>
                <?php else: ?>
                    <ol class="about-menu-top-list">
                        <?php foreach ($menuUsageItems as $index => $item): ?>
                            <li>
                                <span><?php echo $index + 1; ?>. <?php echo htmlspecialchars(fengbroMenuModuleLabel($item['moduleId'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                <small><?php echo number_format((int) ($item['count'] ?? 0)); ?> 次</small>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
            <div id="aboutBankStats" class="about-bank-tiles" data-loaded="false">
                <div class="about-stat-tile"><span>目前總存款</span><strong>載入中…</strong><small>讀取銀行資料</small></div>
                <div class="about-stat-tile"><span>與上次比對</span><strong>—</strong><small>—</small></div>
                <div class="about-stat-tile"><span>銀行最高存款（總存款歷史高點）</span><strong>—</strong><small>—</small></div>
                <div class="about-stat-tile"><span>銀行最低存款（總存款歷史低點）</span><strong>—</strong><small>—</small></div>
            </div>
        </div>
    </div>
    <div class="card service-directory">
        <h3 class="card-title"><i class="fa-solid fa-diagram-project"></i> &#26381;&#21209;&#36039;&#35338;</h3>
        <p>&#20102;&#35299; Bilibili &#33287; AutoSign &#22810;&#23186;&#39636;&#24037;&#20855;&#30340;&#21151;&#33021;&#12289;&#20351;&#29992;&#27969;&#31243;&#33287;&#30456;&#38364;&#36039;&#28304;&#12290;</p>
        <div class="service-directory-grid">
            <?php foreach ([
                'bilibili' => ['Bilibili', 'fa-brands fa-bilibili'],
                'autosign' => ['AutoSign', 'fa-solid fa-wand-magic-sparkles'],
                'digen' => ['AutoSign Digen', 'fa-solid fa-pen-ruler'],
                'litvideo' => ['AutoSign LitVideo', 'fa-solid fa-video'],
                'mindvideo' => ['AutoSign MindVideo', 'fa-solid fa-brain'],
                'musicful' => ['AutoSign Musicful', 'fa-solid fa-music'],
                'oiioii' => ['AutoSign OiiOii', 'fa-solid fa-photo-film'],
            ] as $key => $service): ?>
                <a class="service-directory-link" href="index.php?page=service&service=<?php echo $key; ?>">
                    <i class="<?php echo $service[1]; ?>"></i><span><?php echo htmlspecialchars($service[0]); ?></span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card">
        <h3 class="card-title">系統資訊</h3>
        <table class="table">
            <tr>
                <th style="width: 200px;">系統名稱</th>
                <td>鋒兄 AI</td>
            </tr>
            <tr>
                <th>版本</th>
                <td>1.0.0</td>
            </tr>
            <tr>
                <th>技術架構</th>
                <td>PHP + MySQL</td>
            </tr>
            <tr>
                <th>PHP 版本</th>
                <td><?php echo htmlspecialchars(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION); ?> 系列</td>
            </tr>
            <tr>
                <th>執行環境</th>
                <td><?php echo strtoupper($GLOBALS['ENV']); ?></td>
            </tr>
            <tr>
                <th>freehostia</th>
                <td>hsihua19</td>
            </tr>
            <tr>
                <th>byethost</th>
                <td>b13_41820842</td>
            </tr>
            <tr>
                <th>程式碼統計</th>
                <td>
                    <?php
                    $codeStats = [
                        'php' => 0, 'css' => 0, 'js' => 0, 'sql' => 0, 'files' => 0,
                    ];
                    $root = dirname(__DIR__);
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
                    );
                    $skipDirs = ['uploads', 'vendor', 'node_modules', '.git'];
                    foreach ($iterator as $file) {
                        if (!$file->isFile()) {
                            continue;
                        }
                        $path = str_replace('\\', '/', $file->getPathname());
                        $skip = false;
                        foreach ($skipDirs as $dir) {
                            if (str_contains($path, '/' . $dir . '/')) {
                                $skip = true;
                                break;
                            }
                        }
                        if ($skip) {
                            continue;
                        }
                        $ext = strtolower($file->getExtension());
                        if (!isset($codeStats[$ext])) {
                            continue;
                        }
                        $lines = @count(file($file->getPathname()) ?: []);
                        $codeStats[$ext] += $lines;
                        $codeStats['files']++;
                    }
                    $totalLines = $codeStats['php'] + $codeStats['css'] + $codeStats['js'] + $codeStats['sql'];
                    ?>
                    <strong><?php echo number_format($totalLines); ?></strong> 行
                    <span style="color:#888;font-size:0.85rem;margin-left:8px;">
                        (<?php echo (int) $codeStats['files']; ?> 個檔案
                        .php: <?php echo number_format($codeStats['php']); ?>
                        &nbsp;|&nbsp; .css: <?php echo number_format($codeStats['css']); ?>
                        &nbsp;|&nbsp; .js: <?php echo number_format($codeStats['js']); ?>
                        &nbsp;|&nbsp; .sql: <?php echo number_format($codeStats['sql']); ?>)
                    </span>
                    <br><small style="color:#aaa;">統計日期：<?php echo date('Y-m-d'); ?>（執行時即時計算）</small>
                </td>
            </tr>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">功能模組</h3>
        <table class="table">
            <tr>
                <th style="width: 150px;">首頁</th>
                <td>個人作業中樞，同一頁可切換「精簡」與「完整儀表」；完整儀表整合訂閱、食品、工具與近期狀態摘要。</td>
            </tr>
            <tr>
                <th>訂閱管理</th>
                <td>管理服務名稱、費用、付款日期、續訂狀態與重複訂閱提醒。</td>
            </tr>
            <tr>
                <th>試用／首購</th>
                <td>依服務展開多帳號，追蹤試用、首購狀態與試用／首購／到期日（扣款日），支援 CSV。</td>
            </tr>
            <tr>
                <th>重灌軟體</th>
                <td>整理 Windows／Mac 重灌軟體清單；付費序號預設隱藏，可設查看密碼。</td>
            </tr>
            <tr>
                <th>鋒兄額度</th>
                <td>依服務追蹤每個帳號的剩餘額度、比例與到期日；AI 服務可記錄 5 小時／一週／一月方案的比例與到期。</td>
            </tr>
            <tr>
                <th>鋒兄購物清單</th>
                <td>記錄「想買的商品 × 一次預定購買」；有預定購買日的項目，到期前 3 天進入提醒窗口，支援 CSV 匯入匯出。</td>
            </tr>
            <tr>
                <th>食品管理</th>
                <td>追蹤食品、庫存、到期日與快速新增常用項目。</td>
            </tr>
            <tr>
                <th>筆記資料</th>
                <td>整理筆記、文章與日常資料內容。</td>
            </tr>
            <tr>
                <th>常用帳號</th>
                <td>管理常用帳號資料，支援快速查找與整理。</td>
            </tr>
            <tr>
                <th>圖片庫</th>
                <td>管理圖片檔案與圖片資料；支援 IndexedDB 離線快取與燈箱預覽（上限 500MB）。</td>
            </tr>
            <tr>
                <th>影片庫</th>
                <td>管理影片檔案、封面與播放；支援 IndexedDB 離線快取（上限 500MB）。</td>
            </tr>
            <tr>
                <th>音樂庫</th>
                <td>管理音樂檔案、封面、歌詞與播放器；支援 IndexedDB 離線快取（上限 500MB）。</td>
            </tr>
            <tr>
                <th>文件庫</th>
                <td>管理文件檔案，支援多選上傳、預覽與 IndexedDB 離線快取（上限 500MB）。</td>
            </tr>
            <tr>
                <th>播客庫</th>
                <td>管理播客集數與音訊資料；支援 IndexedDB 離線快取（上限 500MB）。</td>
            </tr>
            <tr>
                <th>銀行資料</th>
                <td>整理銀行、帳戶與金融相關資料。</td>
            </tr>
            <tr>
                <th>例行事項</th>
                <td>管理日常例行任務、提醒與狀態。</td>
            </tr>
            <tr>
                <th>工具模組</th>
                <td>鋒兄比價、手動價格、手機比價、tube、金融、新聞、PNG/JPEG、圖+語音=影片、影片合併、YouTube/Bilibili 轉檔。</td>
            </tr>
            <tr>
                <th>系統設定</th>
                <td>管理系統設定、儲存空間與維護工具。</td>
            </tr>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">近期對齊更新（PHP 版）</h3>
        <ul style="line-height: 1.9; padding-left: 20px; margin: 8px 0 0;">
            <li>工具：比價/手動價格/手機歷史 CSV、新聞、PNG/JPEG、圖+語音、合併+Whisper、YT/B站、tube/金融 CSV 與解析名稱；語音可導向各工具子頁</li>
            <li>新增鋒兄試用／首購、鋒兄重灌、鋒兄額度（獨立 trialpurchase、reinstall、quota 表；重灌含訂閱週期、費用與幣別；額度追蹤剩餘次數／比例／到期與 AI 5小時・一週・一月方案，對齊 Appwrite）</li>
            <li>儀表板合併進首頁：精簡／完整儀表同一頁切換並記住上次選擇（移除獨立「儀表」選單）</li>
            <li>網站統計：進站人次、連續進站天數（台北日曆日）、選單使用 Top 5 與銀行總存款歷史極值（sitevisit、menuusage 表，對齊 Appwrite /api/site-visit 與 /api/menu-usage）</li>
            <li>網站營運天數自起源日 2025-09-28 起算</li>
            <li>新增鋒兄購物清單（shoppinglist 表；CRUD／複製／刪除確認／CSV 匯入匯出）並統一五模組到期提醒窗口：訂閱、試用首購、購物清單、額度非 AI 剩 0~3 天、食品 0~7 天、額度 AI 只提醒前一天與當天</li>
            <li>工具個人清單雲端同步：手動價格改存伺服器 manualprice 表（跨瀏覽器同步）、Tube 頻道存 tubechannel 表、金融自訂標的同步寫入 financeinstrument 表</li>
            <li>通知設定密碼：設定頁可建立通知密碼，儲存／變更 RESEND／BigGo API 金鑰需驗證</li>
            <li>對齊清單：見專案 <code>FEATURE_ALIGNMENT.md</code></li>
            <li>媒體：影片/音樂/播客/文件/圖片 IndexedDB 離線快取（500MB/類型）與批次快取</li>
            <li>設定/儀表：離線快取管理、uploads 分類統計、Offline cache 用量</li>
            <li>匯入：CSV 遮罩進度；食品/訂閱/銀行/常用與大檔分批寫入</li>
            <li>體驗：主題 system/light/dark 三態、首頁提醒可今日關閉、銀行刪除確認字串</li>
        </ul>
        <p style="margin-top: 12px; color: var(--muted-text); font-size: 0.9rem;">
            刻意不移植的 Appwrite 專屬內容：Appwrite 帳號切換、PlumberTycoon / CatShowcase / CEO 展示模組、Appwrite Storage SDK。
        </p>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">資料表結構</h3>
        <p style="line-height: 1.8;">
            目前系統主要使用下列資料表：
        </p>
        <ul style="line-height: 2; padding-left: 20px; margin-top: 10px;">
            <li><code>subscription</code> - 訂閱管理</li>
            <li><code>trialpurchase</code> - 試用／首購</li>
            <li><code>reinstall</code> - 重灌軟體</li>
            <li><code>quota</code> - 鋒兄額度</li>
            <li><code>shoppinglist</code> - 鋒兄購物清單</li>
            <li><code>manualprice</code> - 手動價格紀錄</li>
            <li><code>tubechannel</code> - 鋒兄tube 頻道</li>
            <li><code>financeinstrument</code> - 鋒兄金融自訂標的</li>
            <li><code>food</code> - 食品庫存</li>
            <li><code>notes</code> / <code>article</code> - 筆記與文章</li>
            <li><code>commonaccount</code> - 常用帳號</li>
            <li><code>image</code> - 圖片資料</li>
            <li><code>music</code> - 音樂資料</li>
            <li><code>podcast</code> - 播客資料</li>
            <li><code>commondocument</code> - 文件資料</li>
            <li><code>video</code> - 影片資料</li>
            <li><code>bank</code> - 銀行資料</li>
            <li><code>routine</code> - 例行事項</li>
            <li><code>settings</code> - 系統設定</li>
            <li><code>tool_price_history</code> - 比價歷史</li>
            <li><code>tool_phone_product_history</code> - 手機比價商品每日快照</li>
            <li><code>sitevisit</code> - 進站人次與連續進站天數</li>
            <li><code>menuusage</code> - 選單使用次數與頻率</li>
        </ul>
    </div>
</div>

<style>
    .about-stats-banner {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: stretch;
        justify-content: space-between;
        padding: 26px 28px;
        border-radius: 22px;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #064e3b 120%);
        color: #fff;
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.18);
    }

    .about-stats-banner-copy {
        flex: 1 1 320px;
        min-width: 0;
    }

    .about-stats-banner-copy .eyebrow {
        color: #6ee7b7;
        letter-spacing: 0.3em;
    }

    .about-stats-banner-copy h2 {
        margin: 8px 0 6px;
        font-size: 1.6rem;
        font-weight: 800;
        color: #fff;
    }

    .about-stats-banner-copy p {
        margin: 0;
        max-width: 46rem;
        font-size: 0.92rem;
        line-height: 1.7;
        color: #cbd5e1;
    }

    .about-stats-banner-cards {
        display: grid;
        grid-template-columns: repeat(3, minmax(130px, 1fr));
        gap: 12px;
        flex: 0 1 auto;
        min-width: min(100%, 460px);
    }

    .about-stat-card {
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.06);
        backdrop-filter: blur(4px);
        display: grid;
        gap: 6px;
        align-content: start;
    }

    .about-stat-card .about-stat-label {
        font-size: 0.78rem;
        color: #a8b3c4;
        white-space: nowrap;
    }

    .about-stat-card strong {
        font-size: 1.7rem;
        font-weight: 800;
        color: #fff;
        line-height: 1.1;
    }

    .about-stat-card small {
        font-size: 0.75rem;
        line-height: 1.5;
        color: #94a3b8;
    }

    .about-usage-grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 24px;
    }

    .about-menu-top-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 8px;
    }

    .about-menu-top-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--table-header-bg);
        font-size: 0.9rem;
    }

    .about-menu-top-list li small {
        color: var(--muted-text, #667);
    }

    .about-bank-tiles {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .about-stat-tile {
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid var(--border-color);
        background: var(--table-header-bg);
        display: grid;
        gap: 4px;
        align-content: start;
    }

    .about-stat-tile span {
        font-size: 0.78rem;
        color: var(--muted-text, #667);
    }

    .about-stat-tile strong {
        font-size: 1.2rem;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    .about-stat-tile small {
        font-size: 0.75rem;
        color: var(--muted-text, #667);
        line-height: 1.45;
    }

    .about-stat-tile .delta-up {
        color: #059669;
    }

    .about-stat-tile .delta-down {
        color: #e11d48;
    }

    @media (max-width: 900px) {
        .about-usage-grid {
            grid-template-columns: 1fr;
        }

        .about-stats-banner-cards {
            grid-template-columns: repeat(3, minmax(120px, 1fr));
            width: 100%;
        }
    }

    @media (max-width: 620px) {
        .about-stats-banner {
            padding: 20px 18px;
        }

        .about-stats-banner-cards {
            grid-template-columns: 1fr;
        }

        .about-bank-tiles {
            grid-template-columns: 1fr;
        }
    }
</style>
<script>
    (function () {
        var SNAPSHOT_KEY = 'fengbro:bank-deposit-snapshot';
        var money = function (n) {
            n = Math.round(Number(n) || 0);
            return 'NT$ ' + n.toLocaleString('en-US');
        };
        var dateKey = function (d) {
            var p = function (x) { return x < 10 ? '0' + x : String(x); };
            return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
        };

        function loadBankStats() {
            var box = document.getElementById('aboutBankStats');
            if (!box || box.dataset.loaded === 'true') return;

            fetch('api.php?action=list&table=bank')
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    var banks = (res && res.data) ? res.data : [];
                    var withDeposit = banks.filter(function (b) {
                        return (Number(b.deposit) || 0) > 0;
                    });
                    var sorted = banks.slice().sort(function (a, b) {
                        return (Number(b.deposit) || 0) - (Number(a.deposit) || 0);
                    });
                    var currentTotal = banks.reduce(function (sum, b) {
                        return sum + (Number(b.deposit) || 0);
                    }, 0);
                    var highest = sorted[0] && (Number(sorted[0].deposit) || 0) > 0 ? sorted[0] : null;
                    var lowest = withDeposit.length > 0 ? withDeposit[withDeposit.length - 1] : null;

                    var baseline = null;
                    try {
                        var raw = localStorage.getItem(SNAPSHOT_KEY);
                        if (raw) baseline = JSON.parse(raw);
                    } catch (e) {}

                    var prev = baseline;
                    var maxTotal = prev ? Math.max(Number(prev.maxTotal) || 0, currentTotal) : currentTotal;
                    var minTotal = prev ? Math.min(Number(prev.minTotal) || 0, currentTotal) : currentTotal;
                    var lastTotal = prev ? Number(prev.lastTotal) || 0 : null;
                    var delta = prev ? currentTotal - lastTotal : null;

                    if (banks.length > 0) {
                        var next = {
                            maxTotal: maxTotal,
                            minTotal: minTotal,
                            lastTotal: currentTotal,
                            lastCapturedAt: new Date().toISOString()
                        };
                        try { localStorage.setItem(SNAPSHOT_KEY, JSON.stringify(next)); } catch (e) {}
                    }

                    box.dataset.loaded = 'true';

                    var tiles = box.querySelectorAll('.about-stat-tile');
                    var setTile = function (idx, value, detail, extraClass) {
                        var tile = tiles[idx];
                        if (!tile) return;
                        var strong = tile.querySelector('strong');
                        var small = tile.querySelector('small');
                        if (strong) {
                            strong.textContent = value;
                            strong.className = extraClass || '';
                        }
                        if (small) small.textContent = detail || '';
                    };

                    setTile(0, money(currentTotal),
                        banks.length === 0 ? '尚無銀行資料' :
                        (lastTotal == null ? '第一次使用，尚無上次紀錄' : '上次 ' + money(lastTotal)));
                    setTile(1,
                        delta == null ? '—' : (delta === 0 ? '與上次相同' : (delta > 0 ? '比上次多 ' + money(delta) : '比上次少 ' + money(-delta))),
                        lastTotal == null ? '第一次使用，尚無上次紀錄' : '上次 ' + money(lastTotal),
                        delta == null || delta === 0 ? '' : (delta > 0 ? 'delta-up' : 'delta-down'));
                    setTile(2, money(maxTotal),
                        highest ? '目前最高帳戶 ' + (highest.name || '') + ' · ' + money(highest.deposit) : '尚無銀行資料');
                    setTile(3, money(minTotal),
                        banks.length === 0 ? '尚無銀行資料' : (prev ? '上次 ' + money(lastTotal) : '第一次使用，尚無上次紀錄'));
                })
                .catch(function () {
                    var box = document.getElementById('aboutBankStats');
                    if (!box) return;
                    var strong = box.querySelector('strong');
                    if (strong) strong.textContent = '讀取失敗';
                });
        }

        document.addEventListener('DOMContentLoaded', loadBankStats);
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(loadBankStats, 0);
        }
    })();
</script>
