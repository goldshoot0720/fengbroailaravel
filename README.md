# 鋒兄 AI（PHP + MySQL）

個人作業中樞，對齊 [fengbroaiappwrite](https://github.com/goldshoot0720/fengbroaiappwrite) 功能模組，以 PHP + MySQL 實作。

## 主要功能

- 訂閱 / 食品 / 筆記 / 常用帳號 / 銀行 / 例行事項
- 圖片、影片、音樂（含歌詞）、播客、文件
- 工具：BigGo 比價、手動價格、**手機比價**、鋒兄tube、鋒兄金融、鋒兄新聞、PNG/JPEG、圖+語音=影片、影片合併、YT/B站轉檔
- 通知：Resend 郵件、Web Push、語音操作

## 技術棧

- PHP（無框架）+ MySQL
- 前端：原生 JS + CSS，支援 PWA / 深色模式

## 安全登入設定

系統預設保護所有管理頁、API、匯入匯出與媒體端點。可使用 `users` 資料表中由 `password_hash()` 建立的密碼，或在伺服器設定：

- `FENGBRO_ADMIN_USER`：管理員帳號（預設 `admin`）
- `FENGBRO_ADMIN_PASSWORD_HASH`：PHP `password_hash()` 產生的密碼雜湊
- `FENGBRO_SESSION_IDLE_SECONDS`：閒置登出秒數（預設 3600）

請勿將明文密碼、資料庫憑證或 API Key 提交至 Git。網站會送出 `noindex`、安全 Session Cookie、CSRF 與常用安全標頭；Service Worker 不會快取已登入頁面或 API 回應。

## 對齊 Appwrite 版的近期補齊

- 手機比價改為實際抓取地標網通與傑昇通信並合併最佳價（不再只開 Google 站內搜尋）
- 手機比價商品級每日快照（`tool_phone_product_history`）與歷史走勢
- **手動價格紀錄**（localStorage + CSV 匯出／匯入，對齊 ManualPriceTracker）
- **鋒兄新聞**：多來源關鍵字搜尋 + 焦點來源開關 + **台鐵便當門市**據點
- **PNG / JPEG 批次轉換**（瀏覽器 Canvas；網址圖經 `media_proxy.php`）
- **圖片 + 語音 = 影片**（Google 多語 TTS + 翻譯軌；單一人臉自動選聲；SAPI 備援；ffmpeg 嵌音軌 + 燒錄字幕）
- **影片合併**（ffmpeg concat、可選字幕腳本燒錄、Whisper tiny 自動字幕；影片先 **伺服器 ffmpeg 抽音**）
- **YouTube / Bilibili 轉檔**（伺服器 yt-dlp + ffmpeg → MP3/MP4，可選 cookies）
- Tube / 金融自訂標的 **CSV 匯出／匯入**；手機比價 **歷史價格 CSV**
- 鋒兄金融：擴充預設標的、可開關預設/新增自訂標的、1Y/5Y/10Y 走勢（後兩者 AJAX 懶載入）、連結圖片、自訂標的 YouTube/Bilibili/自訂網址、CSV 含 imageUrls/youtubeUrl/bilibiliUrl/relatedLinks
- 鋒兄tube 過濾 Shorts、強化倒台指數解析與歷史走勢
- 媒體離線快取：影片 / 音樂 / 播客 / 文件 / 圖片（IndexedDB，各類型上限 500MB）
- 圖片/影片/音樂/播客/文件支援全選模式 **批次快取**
- 設定頁可查看用量、分類型清除或一鍵清除全部離線快取
- 儀表板：Offline cache 用量 + uploads 分類統計（圖片/影片/音樂…）
- 影片數改讀 `video` 表；文件數排除舊 video 分類
- CSV 匯入遮罩進度；食品/訂閱/銀行/常用與大檔 **前端分批寫入**（`import_chunk.php`）
- 訂閱幣別下拉擴充至 GBP/KRW/SGD/AUD 等（對齊 Appwrite）
- 金融走勢圖顯示區間低/現/高
- 主題三態：跟隨系統 / 淺色 / 深色
- 首頁 tube/金融提醒可「今日關閉」
- 銀行刪除需輸入 `DELETE 名稱` 確認
- 關於頁程式碼統計即時計算 + 對齊更新說明

## 媒體轉檔需求

`media_tools_api.php` 會偵測本機：

- `yt-dlp`（可用 `YT_DLP_PATH`）
- `ffmpeg`（可用 `FFMPEG_PATH`）

Windows 範例：`winget install yt-dlp.yt-dlp Gyan.FFmpeg`  
圖+語音一鍵生成需 **ffmpeg** + 可連外網（Google TTS／翻譯）；Windows 可備援 SAPI。  
`tools/edge-tts` 為可選 Edge TTS 實驗目錄（WebSocket 不穩時不強制）。  
共用主機若禁用 `proc_open`，伺服器轉檔會顯示「缺少工具」；瀏覽器端工具仍可用。  
Whisper 自動字幕：瀏覽器跑模型；影片音訊優先由伺服器 `extract_audio` 抽成 16k mono WAV，較能處理 mp4。  
人臉自動選聲：瀏覽器載入 face-api tiny 模型（CDN），僅在封面為「剛好一張臉」時採用預測性別，否則預設男聲。

## 對齊狀態

詳細對照表見 [FEATURE_ALIGNMENT.md](FEATURE_ALIGNMENT.md)。  
**可移植功能模組已標記完成**（2026）。

近期補完：金融標的「解析名稱」、手機歷史 CSV 匯入、Whisper 語言選擇、語音導向各工具子頁。

## 不再繼續移植的範圍

下列為 Appwrite/Next 專屬或與 PHP 架構無關的內容，**刻意不移植**：

- Appwrite 帳號動態切換、create-table SSE
- PlumberTycoon / CatShowcase / CEOProfile 展示模組
- Appwrite Storage SDK 與 multipart video 雲端管線
- React 專屬 UI 庫（shadcn）一比一還原

## 程式碼行數

開啟「鋒兄關於」頁可查看即時統計。
