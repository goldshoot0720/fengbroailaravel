<?php
$services = [
    'bilibili' => ['Bilibili', 'fa-brands fa-bilibili', 'Bilibili 影音工具', '提供 Bilibili 連結辨識、影音資訊整理，並與 YouTube/Bilibili 下載及媒體轉檔工具整合。', ['貼上 Bilibili 影音連結', '選擇 MP3 或 MP4 輸出', '使用 yt-dlp 與 ffmpeg 處理媒體']],
    'autosign' => ['AutoSign', 'fa-solid fa-wand-magic-sparkles', 'AutoSign 多媒體服務', '將文字、圖片、音樂與影片生產流程整合為可重複的自動化工具。', ['從內容或素材開始', '選擇適合的 AutoSign 子服務', '產出可發佈的多媒體成品']],
    'digen' => ['AutoSign Digen', 'fa-solid fa-pen-ruler', '數位內容產生', '專注於將結構化資料與文案轉換為可使用的數位素材。', ['準備主題與資料', '調整版型與輸出規格', '匯出素材']],
    'litvideo' => ['AutoSign LitVideo', 'fa-solid fa-video', '輕量影片製作', '以簡化的素材組合流程生產短影片與說明影片。', ['加入圖片與文字', '選擇旁白或背景音樂', '合成影片']],
    'mindvideo' => ['AutoSign MindVideo', 'fa-solid fa-brain', '思維與知識影片', '將大綱、心智圖與知識節點轉成可觀看的影片內容。', ['建立主題大綱', '組織知識節點', '生產觀念影片']],
    'musicful' => ['AutoSign Musicful', 'fa-solid fa-music', '音樂導向媒體', '整合音樂、歌詞、封面與播放清單的內容工作流。', ['匯入音樂與歌詞', '編排播放順序', '產生音樂影片或合集']],
    'oiioii' => ['AutoSign OiiOii', 'fa-solid fa-photo-film', '圖像與社群內容', '將圖像、標題與社群文案組合成一致的發佈素材。', ['選擇圖像', '編輯標題與說明', '匯出社群用素材']],
];
$key = strtolower((string) ($_GET['service'] ?? 'autosign'));
$service = $services[$key] ?? $services['autosign'];
$pageTitle = $service[0];
?>
<div class="content-header"><div class="page-intro"><span class="eyebrow">Service information</span><h1><?php echo htmlspecialchars($service[0]); ?></h1><p><?php echo htmlspecialchars($service[2]); ?></p></div></div>
<div class="content-body">
    <a class="btn btn-sm btn-ghost" href="index.php?page=about"><i class="fa-solid fa-arrow-left"></i> &#22238;&#21040;&#38364;&#26044;</a>
    <section class="card service-info-card">
        <div class="service-info-icon"><i class="<?php echo $service[1]; ?>"></i></div>
        <div><h2><?php echo htmlspecialchars($service[2]); ?></h2><p><?php echo htmlspecialchars($service[3]); ?></p></div>
        <ol><?php foreach ($service[4] as $step): ?><li><?php echo htmlspecialchars($step); ?></li><?php endforeach; ?></ol>
    </section>
</div>
