<!-- ZIP 預覽 Modal -->
<div id="zipPreviewModal" class="modal" onclick="if(event.target===this)closeZipPreview()">
    <div class="modal-content" style="max-width:700px;width:95%;">
        <span class="modal-close" onclick="closeZipPreview()">&times;</span>
        <h2 id="zipPreviewTitle">ZIP 檔案預覽</h2>
        <div id="zipPreviewBody" style="margin-top:15px;">
            <div style="text-align:center;padding:30px;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>正在分析 ZIP 檔案...
            </div>
        </div>
        <div id="zipPreviewActions" style="margin-top:20px;text-align:right;display:none;">
            <button class="btn" onclick="closeZipPreview()" style="margin-right:10px;">取消</button>
            <button class="btn btn-primary" id="zipConfirmImportBtn" onclick="confirmZipImport()">
                <i class="fa-solid fa-check"></i> 確認匯入
            </button>
        </div>
    </div>
</div>

<script>
let _zipPreviewTempFile = null;
let _zipPreviewImportUrl = null;
let _zipPreviewType = null;
let _zipPreviewLabel = null;
let _zipPreviewInput = null;

function previewAndImportZIP(input, type, importUrl, label) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    _zipPreviewInput = input;
    _zipPreviewImportUrl = importUrl;
    _zipPreviewType = type;
    _zipPreviewLabel = label;
    _zipPreviewTempFile = null;

    // Show modal with loading
    const modal = document.getElementById('zipPreviewModal');
    const title = document.getElementById('zipPreviewTitle');
    const body = document.getElementById('zipPreviewBody');
    const actions = document.getElementById('zipPreviewActions');

    title.textContent = 'ZIP 檔案預覽 - ' + file.name;
    body.innerHTML = '<div style="text-align:center;padding:30px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>正在上傳並分析 ZIP 檔案...</div>';
    actions.style.display = 'none';
    modal.style.display = 'flex';

    // Upload and preview
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);

    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            body.innerHTML = '<div style="text-align:center;padding:30px;">' +
                '<i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>' +
                '上傳中... ' + percent + '%' +
                '<div style="margin-top:10px;background:#333;border-radius:5px;overflow:hidden;height:6px;">' +
                '<div style="width:' + percent + '%;background:#4CAF50;height:100%;transition:width 0.3s;"></div></div></div>';
        }
    });

    xhr.addEventListener('load', function() {
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.success) {
                _zipPreviewTempFile = res.tempFile;
                renderZipPreview(res, label);
            } else {
                body.innerHTML = '<div style="text-align:center;padding:30px;color:#e74c3c;"><i class="fa-solid fa-exclamation-circle fa-2x"></i><br>' + (res.error || '分析失敗') + '</div>';
            }
        } catch(e) {
            body.innerHTML = '<div style="text-align:center;padding:30px;color:#e74c3c;"><i class="fa-solid fa-exclamation-circle fa-2x"></i><br>回應格式錯誤</div>';
        }
    });

    xhr.addEventListener('error', function() {
        body.innerHTML = '<div style="text-align:center;padding:30px;color:#e74c3c;"><i class="fa-solid fa-exclamation-circle fa-2x"></i><br>網路錯誤</div>';
    });

    xhr.open('POST', 'preview_zip.php');
    xhr.send(formData);
    input.value = '';
}

function renderZipPreview(data, label) {
    const body = document.getElementById('zipPreviewBody');
    const actions = document.getElementById('zipPreviewActions');

    let html = '<div style="margin-bottom:15px;padding:10px;background:#2d2d2d;border-radius:8px;display:flex;gap:20px;justify-content:center;">';
    html += '<span><i class="fa-solid fa-file"></i> 總共 <strong>' + data.totalFiles + '</strong> 個檔案</span>';
    html += '<span style="color:#27ae60;"><i class="fa-solid fa-check-circle"></i> 可匯入 <strong>' + data.validFiles + '</strong> 個' + label + '</span>';
    if (data.totalFiles - data.validFiles > 0) {
        html += '<span style="color:#e67e22;"><i class="fa-solid fa-exclamation-triangle"></i> 略過 <strong>' + (data.totalFiles - data.validFiles) + '</strong> 個不支援檔案</span>';
    }
    html += '</div>';

    html += '<div style="max-height:400px;overflow-y:auto;">';
    html += '<table class="table" style="margin:0;">';
    html += '<thead><tr><th style="width:40px;"></th><th>檔案名稱</th><th style="width:80px;">類型</th><th style="width:80px;">狀態</th></tr></thead>';
    html += '<tbody>';

    data.files.forEach(function(file) {
        const icon = getFileIcon(file.ext);
        const statusIcon = file.valid
            ? '<span style="color:#27ae60;"><i class="fa-solid fa-check-circle"></i> 匯入</span>'
            : '<span style="color:#999;"><i class="fa-solid fa-ban"></i> 略過</span>';

        html += '<tr style="opacity:' + (file.valid ? '1' : '0.5') + ';">';
        html += '<td style="text-align:center;">' + icon + '</td>';
        html += '<td>' + escapeHtmlZip(file.name) + '</td>';
        html += '<td><span style="background:#555;padding:2px 8px;border-radius:10px;font-size:0.8rem;">' + file.ext.toUpperCase() + '</span></td>';
        html += '<td>' + statusIcon + '</td>';
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    body.innerHTML = html;

    if (data.validFiles > 0) {
        document.getElementById('zipConfirmImportBtn').textContent = ' 確認匯入 (' + data.validFiles + ' 個' + label + ')';
        actions.style.display = 'block';
    } else {
        html += '<div style="text-align:center;padding:15px;color:#e67e22;">ZIP 中沒有可匯入的' + label + '</div>';
        body.innerHTML = html;
        actions.style.display = 'none';
    }
}

function getFileIcon(ext) {
    const icons = {
        'jpg': '<i class="fa-solid fa-image" style="color:#e74c3c;"></i>',
        'jpeg': '<i class="fa-solid fa-image" style="color:#e74c3c;"></i>',
        'png': '<i class="fa-solid fa-image" style="color:#3498db;"></i>',
        'gif': '<i class="fa-solid fa-image" style="color:#2ecc71;"></i>',
        'webp': '<i class="fa-solid fa-image" style="color:#9b59b6;"></i>',
        'bmp': '<i class="fa-solid fa-image" style="color:#f39c12;"></i>',
        'mp3': '<i class="fa-solid fa-music" style="color:#e74c3c;"></i>',
        'wav': '<i class="fa-solid fa-music" style="color:#3498db;"></i>',
        'ogg': '<i class="fa-solid fa-music" style="color:#2ecc71;"></i>',
        'flac': '<i class="fa-solid fa-music" style="color:#9b59b6;"></i>',
        'm4a': '<i class="fa-solid fa-music" style="color:#f39c12;"></i>',
        'aac': '<i class="fa-solid fa-music" style="color:#e67e22;"></i>',
        'mp4': '<i class="fa-solid fa-video" style="color:#e74c3c;"></i>',
        'webm': '<i class="fa-solid fa-video" style="color:#3498db;"></i>',
        'mov': '<i class="fa-solid fa-video" style="color:#2ecc71;"></i>',
        'avi': '<i class="fa-solid fa-video" style="color:#f39c12;"></i>',
        'mkv': '<i class="fa-solid fa-video" style="color:#9b59b6;"></i>',
        'pdf': '<i class="fa-solid fa-file-pdf" style="color:#e74c3c;"></i>',
        'doc': '<i class="fa-solid fa-file-word" style="color:#3498db;"></i>',
        'docx': '<i class="fa-solid fa-file-word" style="color:#3498db;"></i>',
        'xls': '<i class="fa-solid fa-file-excel" style="color:#27ae60;"></i>',
        'xlsx': '<i class="fa-solid fa-file-excel" style="color:#27ae60;"></i>',
        'ppt': '<i class="fa-solid fa-file-powerpoint" style="color:#e67e22;"></i>',
        'pptx': '<i class="fa-solid fa-file-powerpoint" style="color:#e67e22;"></i>',
        'txt': '<i class="fa-solid fa-file-lines" style="color:#999;"></i>'
    };
    return icons[ext] || '<i class="fa-solid fa-file" style="color:#999;"></i>';
}

function escapeHtmlZip(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function confirmZipImport() {
    if (!_zipPreviewTempFile || !_zipPreviewImportUrl) return;

    const body = document.getElementById('zipPreviewBody');
    const actions = document.getElementById('zipPreviewActions');
    actions.style.display = 'none';

    body.innerHTML = '<div style="text-align:center;padding:30px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>正在匯入中...</div>';

    const formData = new FormData();
    formData.append('tempFile', _zipPreviewTempFile);

    const xhr = new XMLHttpRequest();

    xhr.addEventListener('load', function() {
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.success) {
                let debugHtml = '';
                if (res.imported === 0 && res.debug) {
                    debugHtml = '<div style="text-align:left;margin-top:15px;padding:10px;background:#2d2d2d;border-radius:8px;font-size:0.8rem;color:#aaa;">' +
                        '<strong>除錯資訊:</strong><br>' +
                        '模式: ' + (res.debug.mode || 'unknown') + '<br>' +
                        'CSV: ' + (res.debug.csvFound ? '找到 (' + (res.debug.csvFile || '') + ')' : '未找到') + '<br>' +
                        (res.debug.headerCount ? '欄位數: ' + res.debug.headerCount + '<br>' : '') +
                        (res.debug.rowsProcessed !== undefined ? '處理行數: ' + res.debug.rowsProcessed + '<br>' : '') +
                        (res.debug.mappedHeaders ? '欄位: ' + res.debug.mappedHeaders.join(', ') + '<br>' : '') +
                        '</div>';
                }
                let errorHtml = '';
                if (res.errors && res.errors.length > 0) {
                    errorHtml = '<p style="color:#e67e22;font-size:0.85rem;">' + res.errors.length + ' 個錯誤</p>' +
                        '<div style="text-align:left;max-height:150px;overflow-y:auto;margin-top:10px;padding:8px;background:#2d2d2d;border-radius:8px;font-size:0.8rem;color:#e67e22;">' +
                        res.errors.map(function(e) { return '• ' + e; }).join('<br>') +
                        '</div>';
                }
                body.innerHTML = '<div style="text-align:center;padding:30px;color:#27ae60;">' +
                    '<i class="fa-solid fa-check-circle fa-3x"></i><br><br>' +
                    '<h3>匯入完成！</h3>' +
                    '<p>成功匯入 <strong>' + res.imported + '</strong> 個' + _zipPreviewLabel + '</p>' +
                    errorHtml + debugHtml +
                    '</div>';
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                body.innerHTML = '<div style="text-align:center;padding:30px;color:#e74c3c;">' +
                    '<i class="fa-solid fa-exclamation-circle fa-2x"></i><br>' +
                    '匯入失敗: ' + (res.error || '未知錯誤') + '</div>';
                actions.style.display = 'none';
            }
        } catch(e) {
            body.innerHTML = '<div style="text-align:center;padding:30px;color:#e74c3c;"><i class="fa-solid fa-exclamation-circle fa-2x"></i><br>回應格式錯誤</div>';
        }
    });

    xhr.addEventListener('error', function() {
        body.innerHTML = '<div style="text-align:center;padding:30px;color:#e74c3c;"><i class="fa-solid fa-exclamation-circle fa-2x"></i><br>網路錯誤</div>';
    });

    xhr.open('POST', _zipPreviewImportUrl);
    xhr.send(formData);
}

function closeZipPreview() {
    document.getElementById('zipPreviewModal').style.display = 'none';

    // Cleanup temp file if not imported
    if (_zipPreviewTempFile) {
        fetch('cleanup_temp_zip.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({tempFile: _zipPreviewTempFile})
        }).catch(function() {});
        _zipPreviewTempFile = null;
    }
}
</script>
