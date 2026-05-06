<?php
$pageTitle = '鋒兄工具';
require_once __DIR__ . '/../includes/fengbro_tube.php';

$toolSubpage = $_GET['tool'] ?? 'price';
$toolSubpage = in_array($toolSubpage, ['price', 'tube'], true) ? $toolSubpage : 'price';
$tubeData = $toolSubpage === 'tube' ? fengbroTubeGetData(isset($_GET['refresh'])) : null;
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

        <div class="tube-channel-grid">
            <?php foreach (($tubeData['channels'] ?? []) as $channel): ?>
                <section class="card tube-channel-card">
                    <div class="tube-channel-head">
                        <div>
                            <h3 class="card-title"><?php echo $channel['name']; ?></h3>
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

            <div style="display: grid; gap: 12px;">
                <label for="phoneQuery" style="font-weight: 700;">手機型號</label>
                <input id="phoneQuery" class="form-control" type="text" placeholder="例如 Samsung S26 或 iPhone 17">
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <button class="btn btn-primary" type="button" onclick="runPhoneCompare()">
                        <i class="fa-solid fa-mobile-screen"></i> 查詢通路
                    </button>
                    <button class="btn btn-ghost" type="button" onclick="fillPhoneQuery('Samsung S26')">Samsung S26</button>
                    <button class="btn btn-ghost" type="button" onclick="fillPhoneQuery('iPhone 17')">iPhone 17</button>
                </div>
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

    .tube-channel-head span {
        flex: 0 0 auto;
        padding: 6px 10px;
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font-weight: 800;
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

    @media (max-width: 560px) {
        .tube-overview,
        .tube-channel-head,
        .tube-new-alert {
            align-items: flex-start;
            flex-direction: column;
        }

        .tube-video-item {
            grid-template-columns: 1fr;
        }

        .tube-video-item img {
            width: 100%;
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
                        ${renderToolHistory(res.history)}
                    </div>
                `);
            })
            .catch(err => setToolResult('<p style="color:#e74c3c;">' + err.message + '</p>'));
    }

    function fillPhoneQuery(value) {
        const input = document.getElementById('phoneQuery');
        if (input) {
            input.value = value;
            input.focus();
        }
    }

    function openPhoneCompare() {
        const query = getTrimmedValue('phoneQuery') || 'Samsung S26';
        const landtopUrl = 'https://www.google.com/search?q=' + encodeURIComponent('site:landtop.com.tw ' + query);
        const jyesUrl = 'https://www.google.com/search?q=' + encodeURIComponent('site:jyes.com.tw ' + query);

        window.open(landtopUrl, '_blank', 'noopener');
        window.open(jyesUrl, '_blank', 'noopener');
    }

    function runPhoneCompare() {
        const query = getTrimmedValue('phoneQuery') || 'Samsung S26';
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

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') return;
        if (event.target && event.target.id === 'priceQuery') runBigGoLookup();
        if (event.target && event.target.id === 'phoneQuery') runPhoneCompare();
    });
</script>
