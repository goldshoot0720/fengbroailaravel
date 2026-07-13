<?php if (isset($csvTable)):
    // 有專屬 ZIP 匯出腳本的資料表
    $zipDedicatedTables = ['article', 'image', 'music', 'podcast', 'commondocument', 'video'];
    // 純資料表（用通用 export_zip.php?table=xxx）
    $zipGenericTables = ['subscription', 'food', 'commonaccount', 'bank', 'routine'];

    if (in_array($csvTable, $zipDedicatedTables)) {
        $zipUrl = "export_zip_{$csvTable}.php";
    } elseif (in_array($csvTable, $zipGenericTables)) {
        $zipUrl = "export_zip.php?table={$csvTable}";
    } else {
        $zipUrl = '';
    }
    ?>
    <div class="csv-buttons" style="display: inline-block; margin-left: 10px;">
        <?php if ($zipUrl): ?>
            <a href="<?php echo $zipUrl; ?>" class="btn btn-success">
                <i class="fa-solid fa-file-zipper"></i> 匯出 ZIP
            </a>
        <?php endif; ?>
        <button type="button" class="btn" onclick="document.getElementById('importFile_<?php echo $csvTable; ?>').click()">
            <i class="fa-solid fa-upload"></i> 匯入 CSV
        </button>
        <input type="file" id="importFile_<?php echo $csvTable; ?>" accept=".csv" style="display: none;"
            onchange="importCSV_<?php echo $csvTable; ?>(this)">
    </div>

    <div id="csvImportOverlay_<?php echo $csvTable; ?>" class="csv-import-overlay" style="display:none;">
        <div class="csv-import-panel">
            <div class="csv-import-spinner"><i class="fa-solid fa-spinner fa-spin"></i></div>
            <h3 id="csvImportTitle_<?php echo $csvTable; ?>">匯入 CSV 中</h3>
            <p id="csvImportStatus_<?php echo $csvTable; ?>">準備中…</p>
            <div class="csv-import-bar-track">
                <div id="csvImportBar_<?php echo $csvTable; ?>" class="csv-import-bar-fill" style="width:0%;"></div>
            </div>
            <div id="csvImportDebug_<?php echo $csvTable; ?>" class="csv-import-debug"></div>
        </div>
    </div>

    <style>
        .csv-import-overlay {
            position: fixed;
            inset: 0;
            z-index: 100000;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(2px);
        }
        .csv-import-overlay.show { display: flex !important; }
        .csv-import-panel {
            width: min(480px, 100%);
            background: var(--card-bg, #fff);
            color: var(--text-color, #111);
            border-radius: 18px;
            padding: 28px 24px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.28);
            text-align: center;
        }
        .csv-import-spinner {
            font-size: 2rem;
            color: var(--accent, #3498db);
            margin-bottom: 12px;
        }
        .csv-import-panel h3 { margin: 0 0 8px; }
        .csv-import-panel p { margin: 0 0 16px; color: var(--muted-text, #666); }
        .csv-import-bar-track {
            height: 10px;
            border-radius: 999px;
            background: var(--table-header-bg, #eef2f7);
            overflow: hidden;
            margin-bottom: 12px;
        }
        .csv-import-bar-fill {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #3498db, #2ecc71);
            transition: width 0.18s ease;
        }
        .csv-import-debug {
            max-height: 120px;
            overflow: auto;
            text-align: left;
            font-size: 0.78rem;
            color: var(--muted-text, #888);
            white-space: pre-wrap;
        }
        body.csv-importing-heavy .table,
        body.csv-importing-heavy .card-grid,
        body.csv-importing-heavy .media-browser,
        body.csv-importing-heavy .food-mobile-list,
        body.csv-importing-heavy .desktop-only.table {
            visibility: hidden !important;
            pointer-events: none !important;
        }
    </style>

    <script>
        (function () {
            const TABLE = <?php echo json_encode($csvTable, JSON_UNESCAPED_UNICODE); ?>;
            let lastUiFlush = 0;
            let pendingUi = null;

            function getEls() {
                return {
                    overlay: document.getElementById('csvImportOverlay_' + TABLE),
                    title: document.getElementById('csvImportTitle_' + TABLE),
                    status: document.getElementById('csvImportStatus_' + TABLE),
                    bar: document.getElementById('csvImportBar_' + TABLE),
                    debug: document.getElementById('csvImportDebug_' + TABLE)
                };
            }

            function flushImportUi(force) {
                const now = Date.now();
                if (!force && now - lastUiFlush < 120) {
                    if (!pendingUi) {
                        pendingUi = setTimeout(function () {
                            pendingUi = null;
                            flushImportUi(true);
                        }, 120);
                    }
                    return;
                }
                lastUiFlush = now;
                if (pendingUi) {
                    clearTimeout(pendingUi);
                    pendingUi = null;
                }
                const state = window.__csvImportUiState && window.__csvImportUiState[TABLE];
                if (!state) return;
                const els = getEls();
                if (els.status) els.status.textContent = state.status || '';
                if (els.bar) els.bar.style.width = Math.max(0, Math.min(100, state.percent || 0)) + '%';
                if (els.debug && state.debug) {
                    els.debug.textContent = state.debug.slice(-12).join('\n');
                }
            }

            function setImportUi(partial, force) {
                if (!window.__csvImportUiState) window.__csvImportUiState = {};
                const prev = window.__csvImportUiState[TABLE] || { status: '', percent: 0, debug: [] };
                window.__csvImportUiState[TABLE] = Object.assign({}, prev, partial || {});
                if (partial && partial.debugLine) {
                    const lines = (window.__csvImportUiState[TABLE].debug || []).slice();
                    lines.push(partial.debugLine);
                    window.__csvImportUiState[TABLE].debug = lines.slice(-40);
                    delete window.__csvImportUiState[TABLE].debugLine;
                }
                flushImportUi(!!force);
            }

            function showImportOverlay(message) {
                const els = getEls();
                if (!els.overlay) return;
                document.body.classList.add('csv-importing-heavy');
                els.overlay.classList.add('show');
                els.overlay.style.display = 'flex';
                if (els.title) els.title.textContent = '匯入 CSV 中';
                setImportUi({ status: message || '上傳並寫入中…', percent: 4, debug: [] }, true);
            }

            function hideImportOverlay() {
                const els = getEls();
                document.body.classList.remove('csv-importing-heavy');
                if (!els.overlay) return;
                els.overlay.classList.remove('show');
                els.overlay.style.display = 'none';
            }

            function parseCsvText(text) {
                const clean = String(text || '').replace(/^\uFEFF/, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
                const rows = [];
                let row = [];
                let field = '';
                let inQuotes = false;
                for (let i = 0; i < clean.length; i++) {
                    const ch = clean[i];
                    if (inQuotes) {
                        if (ch === '"') {
                            if (clean[i + 1] === '"') { field += '"'; i++; }
                            else { inQuotes = false; }
                        } else {
                            field += ch;
                        }
                    } else if (ch === '"') {
                        inQuotes = true;
                    } else if (ch === ',' || ch === '\t' || ch === ';') {
                        // 第一列偵測分隔符：用逗號為主，若表頭沒逗號再退回 tab/分號
                        row.push(field);
                        field = '';
                    } else if (ch === '\n') {
                        row.push(field);
                        if (row.some(function (c) { return String(c).trim() !== ''; })) rows.push(row);
                        row = [];
                        field = '';
                    } else {
                        field += ch;
                    }
                }
                if (field || row.length) {
                    row.push(field);
                    if (row.some(function (c) { return String(c).trim() !== ''; })) rows.push(row);
                }
                return rows;
            }

            function detectDelimiter(headerLine) {
                const counts = {
                    ',': (headerLine.match(/,/g) || []).length,
                    '\t': (headerLine.match(/\t/g) || []).length,
                    ';': (headerLine.match(/;/g) || []).length
                };
                let best = ',';
                let bestCount = -1;
                Object.keys(counts).forEach(function (d) {
                    if (counts[d] > bestCount) {
                        best = d;
                        bestCount = counts[d];
                    }
                });
                return best;
            }

            function parseCsvWithDelimiter(text, delimiter) {
                const clean = String(text || '').replace(/^\uFEFF/, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
                const rows = [];
                let row = [];
                let field = '';
                let inQuotes = false;
                for (let i = 0; i < clean.length; i++) {
                    const ch = clean[i];
                    if (inQuotes) {
                        if (ch === '"') {
                            if (clean[i + 1] === '"') { field += '"'; i++; }
                            else { inQuotes = false; }
                        } else {
                            field += ch;
                        }
                    } else if (ch === '"') {
                        inQuotes = true;
                    } else if (ch === delimiter) {
                        row.push(field);
                        field = '';
                    } else if (ch === '\n') {
                        row.push(field);
                        if (row.some(function (c) { return String(c).trim() !== ''; })) rows.push(row);
                        row = [];
                        field = '';
                    } else {
                        field += ch;
                    }
                }
                if (field || row.length) {
                    row.push(field);
                    if (row.some(function (c) { return String(c).trim() !== ''; })) rows.push(row);
                }
                return rows;
            }

            function rowsToObjects(matrix) {
                if (!matrix || matrix.length < 2) return [];
                const headers = matrix[0].map(function (h) { return String(h || '').trim(); });
                const out = [];
                for (let i = 1; i < matrix.length; i++) {
                    const values = matrix[i];
                    const obj = {};
                    let hasValue = false;
                    headers.forEach(function (h, idx) {
                        if (!h) return;
                        const v = values[idx] != null ? String(values[idx]).trim() : '';
                        obj[h] = v;
                        if (v !== '') hasValue = true;
                    });
                    if (hasValue) out.push(obj);
                }
                return out;
            }

            async function importByChunks(file) {
                const text = await file.text();
                const firstLine = String(text).split(/\r\n|\n|\r/)[0] || '';
                const delimiter = detectDelimiter(firstLine);
                const matrix = parseCsvWithDelimiter(text, delimiter);
                const objects = rowsToObjects(matrix);
                if (!objects.length) {
                    throw new Error('CSV 沒有可匯入資料列');
                }

                const chunkSize = 40;
                let imported = 0;
                let skipped = 0;
                const errors = [];
                const total = objects.length;
                setImportUi({
                    percent: 5,
                    status: '已解析 ' + total + ' 筆，開始分批寫入…',
                    debugLine: 'delimiter=' + JSON.stringify(delimiter) + ' rows=' + total
                }, true);

                for (let i = 0; i < objects.length; i += chunkSize) {
                    const chunk = objects.slice(i, i + chunkSize);
                    const batchNo = Math.floor(i / chunkSize) + 1;
                    const batchTotal = Math.ceil(objects.length / chunkSize);
                    setImportUi({
                        percent: Math.min(96, Math.round(((i + chunk.length) / total) * 100)),
                        status: '寫入批次 ' + batchNo + ' / ' + batchTotal + '（本批 ' + chunk.length + ' 筆）',
                        debugLine: 'batch ' + batchNo + '/' + batchTotal
                    });

                    const res = await fetch('import_chunk.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ table: TABLE, rows: chunk })
                    }).then(function (r) { return r.json(); });

                    if (!res.success && res.error) {
                        throw new Error(res.error);
                    }
                    imported += Number(res.imported || 0);
                    skipped += Number(res.skipped || 0);
                    if (res.errors && res.errors.length) {
                        res.errors.forEach(function (e) { errors.push(e); });
                    }
                    // 讓出主執行緒，避免 UI 凍結/閃爍
                    await new Promise(function (resolve) { setTimeout(resolve, 30); });
                }

                return { imported: imported, skipped: skipped, errors: errors };
            }

            function importByServerUpload(file) {
                const formData = new FormData();
                formData.append('table', TABLE);
                formData.append('file', file);

                showImportOverlay('上傳 ' + file.name + '…');
                setImportUi({ percent: 8, debugLine: '開始上傳 ' + file.name }, true);

                let fake = 8;
                const tick = setInterval(function () {
                    if (fake < 88) {
                        fake += Math.max(0.4, (90 - fake) * 0.04);
                        setImportUi({
                            percent: fake,
                            status: '伺服器處理中… ' + Math.round(fake) + '%'
                        });
                    }
                }, 220);

                return fetch('import.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        clearInterval(tick);
                        return res;
                    })
                    .catch(function (err) {
                        clearInterval(tick);
                        throw err;
                    });
            }

            function finishImportResult(res) {
                if (res.success || typeof res.imported !== 'undefined') {
                    setImportUi({
                        percent: 100,
                        status: '完成！成功 ' + (res.imported || 0) + ' 筆' + (res.skipped ? '，跳過 ' + res.skipped + ' 筆' : ''),
                        debugLine: 'imported=' + (res.imported || 0) + ' skipped=' + (res.skipped || 0)
                    }, true);
                    let msg = '匯入完成！\n成功: ' + (res.imported || 0) + ' 筆';
                    if (res.skipped > 0) msg += '\n跳過: ' + res.skipped + ' 筆';
                    if (res.errors && res.errors.length > 0) {
                        msg += '\n\n錯誤明細:\n' + res.errors.slice(0, 20).join('\n');
                        if (res.errors.length > 20) msg += '\n…共 ' + res.errors.length + ' 筆錯誤';
                    }
                    setTimeout(function () {
                        alert(msg);
                        location.reload();
                    }, 280);
                } else {
                    hideImportOverlay();
                    alert('匯入失敗: ' + (res.error || '未知錯誤'));
                }
            }

            window['importCSV_' + TABLE] = function (input) {
                if (!input.files || !input.files[0]) return;

                if (!confirm('確定要匯入 CSV 嗎？\n支援 LaravelMySQL 和 Appwrite 雙格式。\n已存在的資料將會被更新。')) {
                    input.value = '';
                    return;
                }

                const file = input.files[0];
                // 食品/訂閱/銀行/常用與超過 200KB 的 CSV 走前端分批寫入
                const useChunked = ['food', 'subscription', 'bank', 'commonaccount'].indexOf(TABLE) !== -1
                    || file.size > 200 * 1024;
                showImportOverlay(useChunked ? ('解析 ' + file.name + '…') : ('上傳 ' + file.name + '…'));

                const job = useChunked
                    ? importByChunks(file).then(function (stats) {
                        return { success: true, imported: stats.imported, skipped: stats.skipped, errors: stats.errors };
                    })
                    : importByServerUpload(file);

                job.then(finishImportResult).catch(function (err) {
                    hideImportOverlay();
                    alert('匯入失敗: ' + (err.message || err));
                });

                input.value = '';
            };
        })();
    </script>
<?php endif; ?>
