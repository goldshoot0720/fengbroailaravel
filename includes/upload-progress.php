<!-- Upload Progress Modal -->
<div id="uploadProgressModal"
    style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: #fff; padding: 30px; border-radius: 10px; min-width: 350px; max-width: min(92vw, 520px); text-align: center;">
        <h3 id="uploadProgressTitle" style="margin: 0 0 20px 0;">上傳中...</h3>
        <div style="background: #e0e0e0; border-radius: 10px; height: 20px; overflow: hidden; margin-bottom: 15px;">
            <div id="uploadProgressBar"
                style="background: linear-gradient(90deg, #4CAF50, #8BC34A); height: 100%; width: 0%; transition: width 0.3s;">
            </div>
        </div>
        <div id="uploadProgressText" style="color: #666;">0%</div>
        <div id="uploadFileName" style="color: #999; font-size: 0.85rem; margin-top: 10px; word-break: break-all;"></div>
    </div>
</div>

<script>
    const LARGE_UPLOAD_THRESHOLD = 8 * 1024 * 1024;
    const LARGE_UPLOAD_CHUNK_SIZE = 4 * 1024 * 1024;

    function showUploadProgressModal(percent, text, fileLabel, title) {
        const modal = document.getElementById('uploadProgressModal');
        const progressTitle = document.getElementById('uploadProgressTitle');
        const progressBar = document.getElementById('uploadProgressBar');
        const progressText = document.getElementById('uploadProgressText');
        const fileName = document.getElementById('uploadFileName');

        if (!modal || !progressBar || !progressText || !fileName) return;

        modal.style.display = 'flex';
        if (progressTitle) progressTitle.textContent = title || '上傳中...';
        progressBar.style.width = Math.max(0, Math.min(100, percent || 0)) + '%';
        progressText.textContent = text || '0%';
        fileName.textContent = fileLabel || '';
    }

    function hideUploadProgressModal() {
        const modal = document.getElementById('uploadProgressModal');
        if (modal) modal.style.display = 'none';
    }

    function uploadFileWithProgress(file, onSuccess, onError, options) {
        options = options || {};
        const shouldManageModal = options.showModal !== false;
        const shouldUseChunked = options.chunked !== false && file && file.size > LARGE_UPLOAD_THRESHOLD;

        if (shouldUseChunked) {
            uploadFileWithChunkProgress(file, onSuccess, onError, options, shouldManageModal);
            return;
        }

        if (shouldManageModal) {
            showUploadProgressModal(0, '0%', file.name, options.title || '上傳中...');
        }

        const xhr = new XMLHttpRequest();
        const formData = new FormData();
        formData.append('file', file);

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                const loaded = formatFileSize(e.loaded);
                const total = formatFileSize(e.total);
                if (shouldManageModal) {
                    showUploadProgressModal(
                        percent,
                        percent + '%',
                        file.name + ' (' + loaded + ' / ' + total + ')',
                        options.title || '上傳中...'
                    );
                }
                if (typeof options.onProgress === 'function') {
                    options.onProgress({
                        percent: percent,
                        loaded: e.loaded,
                        total: e.total,
                        loadedText: loaded,
                        totalText: total,
                        file: file
                    });
                }
            }
        });

        xhr.addEventListener('load', function () {
            if (shouldManageModal) hideUploadProgressModal();
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    onSuccess(res);
                } else {
                    onError(res.error || '上傳失敗');
                }
            } catch (e) {
                onError('伺服器回應錯誤 (HTTP ' + xhr.status + '): ' + xhr.responseText.substring(0, 200));
            }
        });

        xhr.addEventListener('error', function () {
            if (shouldManageModal) hideUploadProgressModal();
            onError('網路錯誤 (status=' + xhr.status + ', readyState=' + xhr.readyState + ')');
        });

        xhr.addEventListener('abort', function () {
            if (shouldManageModal) hideUploadProgressModal();
            onError('上傳已取消');
        });

        xhr.open('POST', 'upload.php');
        xhr.send(formData);
    }

    function uploadFileWithChunkProgress(file, onSuccess, onError, options, shouldManageModal) {
        if (typeof uploadChunked !== 'function') {
            onError('分段上傳功能尚未載入，請重新整理頁面');
            return;
        }

        if (shouldManageModal) {
            showUploadProgressModal(0, '0%', file.name, options.title || '分段上傳中...');
        }

        uploadChunked(
            file,
            function (done, total, percent) {
                const uploadedBytes = Math.min(file.size, done * LARGE_UPLOAD_CHUNK_SIZE);
                const label = file.name + ' (' + formatFileSize(uploadedBytes) + ' / ' + formatFileSize(file.size) + ')';

                if (shouldManageModal) {
                    showUploadProgressModal(
                        percent,
                        percent + '% (' + done + '/' + total + ')',
                        label,
                        options.title || '分段上傳中...'
                    );
                }

                if (typeof options.onProgress === 'function') {
                    options.onProgress({
                        percent: percent,
                        loaded: uploadedBytes,
                        total: file.size,
                        loadedText: formatFileSize(uploadedBytes),
                        totalText: formatFileSize(file.size),
                        file: file
                    });
                }
            },
            function (_path, res) {
                if (shouldManageModal) hideUploadProgressModal();
                if (res && res.success && res.file) {
                    onSuccess(res);
                } else {
                    onError('分段合併失敗：未取得檔案路徑');
                }
            },
            function (message) {
                if (shouldManageModal) hideUploadProgressModal();
                onError(message || '分段上傳失敗');
            },
            LARGE_UPLOAD_CHUNK_SIZE,
            { target: 'file' }
        );
    }

    function formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.min(sizes.length - 1, Math.floor(Math.log(bytes) / Math.log(k)));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
</script>
