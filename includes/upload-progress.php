<!-- Upload Progress Modal -->
<div id="uploadProgressModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: #fff; padding: 30px; border-radius: 10px; min-width: 350px; text-align: center;">
        <h3 style="margin: 0 0 20px 0;">上傳中...</h3>
        <div style="background: #e0e0e0; border-radius: 10px; height: 20px; overflow: hidden; margin-bottom: 15px;">
            <div id="uploadProgressBar" style="background: linear-gradient(90deg, #4CAF50, #8BC34A); height: 100%; width: 0%; transition: width 0.3s;"></div>
        </div>
        <div id="uploadProgressText" style="color: #666;">0%</div>
        <div id="uploadFileName" style="color: #999; font-size: 0.85rem; margin-top: 10px;"></div>
    </div>
</div>

<script>
function uploadFileWithProgress(file, onSuccess, onError) {
    const modal = document.getElementById('uploadProgressModal');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    const fileName = document.getElementById('uploadFileName');

    modal.style.display = 'flex';
    progressBar.style.width = '0%';
    progressText.textContent = '0%';
    fileName.textContent = file.name;

    const xhr = new XMLHttpRequest();
    const formData = new FormData();
    formData.append('file', file);

    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            progressText.textContent = percent + '%';

            // Show file size info
            const loaded = formatFileSize(e.loaded);
            const total = formatFileSize(e.total);
            fileName.textContent = file.name + ' (' + loaded + ' / ' + total + ')';
        }
    });

    xhr.addEventListener('load', function() {
        modal.style.display = 'none';
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.success) {
                onSuccess(res);
            } else {
                onError(res.error || '上傳失敗');
            }
        } catch (e) {
            onError('回應格式錯誤');
        }
    });

    xhr.addEventListener('error', function() {
        modal.style.display = 'none';
        onError('網路錯誤');
    });

    xhr.addEventListener('abort', function() {
        modal.style.display = 'none';
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
