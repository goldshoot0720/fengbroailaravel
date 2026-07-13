# 鋒兄 AI（PHP + MySQL）

個人作業中樞，對齊 [fengbroaiappwrite](https://github.com/goldshoot0720/fengbroaiappwrite) 功能模組，以 PHP + MySQL 實作。

## 主要功能

- 訂閱 / 食品 / 筆記 / 常用帳號 / 銀行 / 例行事項
- 圖片、影片、音樂（含歌詞）、播客、文件
- 工具：BigGo 比價、**手機比價（地標網通 + 傑昇通信）**、鋒兄tube、鋒兄金融
- 通知：Resend 郵件、Web Push、語音操作

## 技術棧

- PHP（無框架）+ MySQL
- 前端：原生 JS + CSS，支援 PWA / 深色模式

## 對齊 Appwrite 版的近期補齊

- 手機比價改為實際抓取地標網通與傑昇通信並合併最佳價（不再只開 Google 站內搜尋）
- 手機比價商品級每日快照（`tool_phone_product_history`）與歷史走勢
- 鋒兄金融：擴充預設標的、可開關預設/新增自訂標的、1Y/5Y/10Y 走勢（後兩者 AJAX 懶載入）
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

## 不再繼續移植的範圍

下列為 Appwrite/Next 專屬或與 PHP 架構無關的內容，**刻意不移植**：

- Appwrite 帳號動態切換、create-table SSE
- PlumberTycoon / CatShowcase / CEOProfile 展示模組
- Appwrite Storage SDK 與 multipart video 雲端管線
- React 專屬 UI 庫（shadcn）一比一還原

## 程式碼行數

開啟「鋒兄關於」頁可查看即時統計。
