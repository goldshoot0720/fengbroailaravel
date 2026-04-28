<?php $pageTitle = '鋒兄工具'; ?>

<div class="content-header">
    <div>
        <h1>鋒兄工具</h1>
        <p style="margin-top: 8px; color: var(--muted-text);">比價、手機通路查詢與常用工具入口。</p>
    </div>
</div>

<div class="content-body">
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
