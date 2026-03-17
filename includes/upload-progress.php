<!-- Upload Progress Modal -->
<div id="uploadProgressModal"
    style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: #fff; padding: 30px; border-radius: 10px; min-width: 350px; text-align: center;">
        <h3 id="uploadProgressTitle" style="margin: 0 0 20px 0;">上傳中...</h3>
        <div style="background: #e0e0e0; border-radius: 10px; height: 20px; overflow: hidden; margin-bottom: 15px;">
            <div id="uploadProgressBar"
                style="background: linear-gradient(90deg, #4CAF50, #8BC34A); height: 100%; width: 0%; transition: width 0.3s;">
            </div>
        </div>
        <div id="uploadProgressText" style="color: #666;">0%</div>
        <div id="uploadFileName" style="color: #999; font-size: 0.85rem; margin-top: 10px;"></div>
    </div>
</div>

<script>
    function showUploadProgressModal(percent, text, fileLabel, title) {
        const modal = document.getElementById('uploadProgressModal');
        const progressTitle = document.getElementById('uploadProgressTitle');
        const progressBar = document.getElementById('uploadProgressBar');
        const progressText = document.getElementById('uploadProgressText');
        const fileName = document.getElementById('uploadFileName');

        modal.style.display = 'flex';
        if (progressTitle) progressTitle.textContent = title || '上傳中...';
        progressBar.style.width = Math.max(0, Math.min(100, percent || 0)) + '%';
        progressText.textContent = text || '0%';
        fileName.textContent = fileLabel || '';
    }

    function hideUploadProgressModal() {
        const modal = document.getElementById('uploadProgressModal');
        modal.style.display = 'none';
    }

    function uploadFileWithProgress(file, onSuccess, onError, options) {
        options = options || {};
        const shouldManageModal = options.showModal !== false;

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
                onError('回應格式錯誤 (HTTP ' + xhr.status + '): ' + xhr.responseText.substring(0, 200));
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

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
</script>
