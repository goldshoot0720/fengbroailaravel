# 功能對齊清單（fengbroaiappwrite → fengbroailaravel）

最後更新：對齊 Appwrite 重灌訂閱週期／費用與試用日期標籤。對照 [fengbroaiappwrite](https://github.com/goldshoot0720/fengbroaiappwrite)。

## 核心業務模組

| Appwrite 模組 | PHP 頁面 | 狀態 |
|---|---|---|
| Home | `pages/home.php` | 完成（含 tube/金融今日關閉） |
| Dashboard | `pages/dashboard.php` | 完成（離線快取／uploads 統計） |
| Subscription | `pages/subscription.php` | 完成 |
| Trial / first purchase | `pages/trialpurchase.php` | 完成（服務分組、狀態篩選、CSV） |
| Reinstall | `pages/reinstall.php` | 完成（Win／Mac、序號遮罩、查看密碼、訂閱週期與費用、CSV） |
| Food | `pages/food.php` | 完成 |
| Notes / Article | `pages/notes.php` | 完成 |
| Common accounts | `pages/favorites.php` | 完成 |
| Images | `pages/images.php` | 完成（批次快取） |
| Videos | `pages/videos.php` | 完成（批次快取、播放器截圖） |
| Music + lyrics | `pages/music.php` | 完成 |
| Documents | `pages/documents.php` | 完成（批次快取） |
| Podcast | `pages/podcast.php` | 完成 |
| Bank | `pages/bank.php` | 完成（刪除確認字串） |
| Routine | `pages/routine.php` | 完成 |
| Settings | `pages/settings.php` | 完成（通知自檢、離線快取） |
| About | `pages/about.php` | 完成 |

## 工具模組（Tools）

| Appwrite 工具 | PHP 入口 `?tool=` | 狀態 |
|---|---|---|
| 鋒兄比價 BigGo | `price` | 完成 |
| ManualPriceTracker | `manual` | 完成（localStorage + CSV） |
| 手機比價 landtop+jyes | `phone` | 完成（獨立分頁、快照、歷史 CSV 匯出/匯入） |
| 鋒兄Tube | `tube` | 完成（頻道 CSV） |
| 鋒兄金融 | `finance` | 完成（1Y/5Y/10Y、自訂 CSV、解析名稱、**精選焦點最多 9**、連結圖片、YouTube/Bilibili/自訂網址） |
| 鋒兄新聞 + 便當 | `news` | 完成 |
| PNG/JPEG 轉換 | `image-convert` | 完成 |
| ImageVoiceVideo | `image-voice` | 完成（多語 TTS、翻譯、人臉選聲、一鍵 MP4） |
| VideoMerge + Whisper | `video-merge` | 完成（ffmpeg 合併、抽音、Whisper 多語、字幕燒錄） |
| YT/Bilibili 轉檔 | `yt-bili` | 完成（yt-dlp + ffmpeg） |

## 通知 / PWA / 語音

| 功能 | 狀態 |
|---|---|
| Resend 到期通知 | 完成 |
| Web Push | 完成 |
| 通知自檢 notif_diag | 完成 |
| PWA / Service Worker | 完成 |
| 語音導航與表單填寫 | 完成（含工具子頁導向） |
| 主題 system/light/dark | 完成 |

## 刻意不移植（架構專屬）

- Appwrite 帳號動態切換、create-table SSE
- PlumberTycoon / CatShowcase / CEOProfile
- Appwrite Storage SDK / multipart 雲端影片管線
- React/shadcn 一比一 UI
- Vercel 上自動下載 yt-dlp 的 serverless 特化

## 執行環境依賴

| 能力 | 需求 |
|---|---|
| YT 轉檔 / 合併 / 抽音 / 圖+語音合成 | `ffmpeg`（`FFMPEG_PATH`） |
| YT/B 站下載 | `yt-dlp`（`YT_DLP_PATH`） |
| Google 多語 TTS / 翻譯 | 外網 |
| SAPI 備援 TTS | Windows 語音套件 |
| Whisper | 瀏覽器 WASM + 可選伺服器抽音 |
| 人臉選聲 | 瀏覽器 CDN face-api |

## 結論

**狀態：完成（DONE）**

在「PHP + MySQL 個人作業中樞」範圍內，可移植的 Appwrite 功能模組已對齊完成，含日常工作台新增的試用／首購與重灌。  
刻意不移植項目見上表；其餘缺口屬執行環境或第三方服務限制，不屬功能未做。
