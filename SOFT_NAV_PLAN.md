# 軟導覽（Soft Navigation）實作計畫

> **目標**：讓站內導覽不再整頁重載，取得接近 SPA 的體感——不白屏、不重載 CSS/JS、不跳捲動、保留外殼——但**維持伺服器渲染架構，零重寫**。
>
> **範圍**：只改前端（新增一個 JS 模組 + 少量掛載點），不動 PHP 頁面邏輯、不動資料庫、不動路由。

---

## 1. 背景與結論

現有專案是**伺服器渲染 MPA**（詳見 `CONTEXT.md`）。已具備 `assets/js/optimistic-ui.js` 的「同頁局部更新」能力，但**跨頁導覽仍是整頁重載**。

本計畫把既有的局部更新體驗**延伸到導覽**：點選單 → 抓目標頁 → 只換 `#mainContent` → 更新網址。這是 Livewire `wire:navigate` / Turbo / htmx 在解的同一個問題，但用專案現有的零件自己接，貼合度最高、風險最低。

**不做的事**：不引入前端框架、不引入 Inertia、不重寫任何頁面。

---

## 2. 現況盤點（已實測）

| 項目 | 位置 | 說明 |
|---|---|---|
| 換頁目標 | `#mainContent` | 頁面內容容器。側邊欄、行動選單、footer **都在它之外** |
| 版面 | `resources/views/layouts/app.blade.php` | include `includes/sidebar.php`、`mobile-nav.php`、`footer.php` |
| 桌面導覽 | `includes/sidebar.php` → `handleMenuNav(link)` | 每個選單連結都有 `onclick` 與 `data-menu-url` |
| 行動導覽 | `includes/mobile-nav.php` | 另一組連結，需一併處理 |
| 同頁局部更新 | `assets/js/optimistic-ui.js` → `fengbroReload()` | **只支援同一網址** |
| 伺服器基準快照 | `includes/footer.php` 首行 `fengbroSnapshotMain()` | 解析期建立 |
| 進度條 | `assets/js/ux-boost.js` | 自動掛 `window.fetch`，同源請求 >120ms 顯示 |
| 預先轉譯 | `assets/js/optimistic-ui.js`（Speculation Rules） | 已對 `?page=` 連結生效 |
| ETag / 304 | `includes/page_cache.php` | **僅 legacy 路徑生效**（見下） |

### 已發現的兩個關鍵事實（直接影響設計）

**事實 1 — `fengbroReload()` 不能直接用於導覽。**
它會比對頁面內嵌 `<script>` 的簽章（`scriptSignature`），跨頁必然不同 → 必定走 fallback 整頁重載。因此軟導覽**必須是獨立模組**，不共用 `softReload()`。

**事實 2 — ETag／304 在 Laravel 路徑上沒有生效。**
`fengbroServePageWithEtag()` 只被 `legacy_index.php` 呼叫；`fengbroTrackDataWrites()` 也沒有接上任何進入點。也就是說「資料沒變就回 304」這個加速，目前**只在 vendor 不存在時的 legacy fallback 路徑有效**。Laravel 路徑每次都回完整 HTML。

> 這其實對軟導覽有利：不必處理 304（304 沒有 body）。軟導覽一律用 `cache: 'no-store'` 拿完整 HTML 即可。
> 若日後想恢復 304 加速，需另外在 Laravel 路徑補上 `fengbroServePageWithEtag()` 與 `fengbroTrackDataWrites()`，屬獨立議題，不在本計畫範圍。

---

## 3. 目標與範圍（MVP）

### 攔截條件（全部成立才攔截）
- 同源、`GET`
- 網址符合 `index.php?page=...`（含 `&tool=...`）
- 未按修飾鍵（⌘ / Ctrl / Shift / Alt）
- 連結無 `target`、無 `download`、無 `data-no-soft-nav`
- 目標頁在**白名單**內

### 不攔截（維持整頁導覽）
- 任何表單送出、任何 `POST`
- `tools`、`settings`（含外部即時資料與診斷）
- 媒體播放相關頁面
- 外部連結、下載連結、`mailto:`、`tel:`、純 `#` 錨點

### 白名單（Phase 1 建議從 2 頁起步）
先與既有 `SOFT_PAGES` 對齊，但**分批開啟**：

```
Phase 1 試點：notes、bank
Phase 2 擴大：subscription、trialpurchase、reinstall、quota、shoppinglist、food、favorites、images、documents、routine
永遠排除：tools、settings、music、videos、podcast、home（首頁含較多即時區塊，最後再評估）
```

---

## 4. 架構

新增一個獨立模組：**`assets/js/soft-nav.js`**

- 載入位置：`includes/header.php` 中 `optimistic-ui.js` **之後**（同步載入）。
- 同步更新 `resources/views/layouts/app.blade.php`（Laravel 版 layout 目前是自己列 `<script>`，需一併加入）。

### 對外介面

```js
window.fengbroSoftNav = {
  enabled: true,
  go: function (url, options) {},   // options: { push: true|false }
  pages: { notes: 1, bank: 1 }      // 白名單
};
```

### 事件

- `fengbro:soft-nav` — 換頁完成後派發（`detail: { url, page }`），供其他模組（如 `ux-boost.js`）協調。

---

## 5. 核心流程

### 5.1 `navigate(url, { push })`

1. 若目標已在飛行中 → 忽略（防連點）。
2. `fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Fengbro-Soft': '1' } })`
   - 進度條由 `ux-boost.js` 自動顯示，無需額外處理。
3. 檢查回應：`response.ok`、未被重導到別的 `?page=`；否則 → fallback。
4. `new DOMParser().parseFromString(html, 'text/html')`，取出：
   - `#mainContent`
   - `<title>`
   - `body[data-page]`、`body[data-tool]`
5. **換掉 `#mainContent`**：`replaceWith`（或 `replaceChildren`）整塊替換。
6. **重跑新 `#mainContent` 內的所有 `<script>`**（見 §6 R1）。
7. 更新 `document.title`、`document.body.dataset.page` / `.dataset.tool`。
8. 更新側邊欄 active：移除舊的 `.active` / `aria-current="page"`，依 `data-menu-url` 比對設定新的。
9. `history.pushState({ fengbroSoftNav: true, url: url }, '', url)`（若 `push` 為 true）。
10. 捲動：換頁回頂端。
11. 重新建立伺服器基準快照（見 §6 R2）。
12. 派發 `fengbro:soft-nav`。

### 5.2 `popstate`（上一頁 / 下一頁）

- 從 HTML 快取（`Map<url, html>`，上限約 5 筆）取出目標頁 → 直接走 5.1 的步驟 5–12。
- 快取沒有 → 重新 `fetch`。
- 還原捲動位置（與 `ux-boost.js` 的 `SCROLL_KEY` 協調，見 §6 R4）。

### 5.3 失敗一律 fallback

任何例外（非 2xx、解析失敗、找不到 `#mainContent`、腳本重跑例外）→ `location.href = url`。**寧可整頁重載，不可白屏或半殘**。

---

## 6. 關鍵技術點與風險

### R1 — 腳本重跑（最高風險）

> ⚠️ **實作後修正**：本節原本規劃「把頂層 `const`/`let` 改寫成 `var`」——**實測行不通**。
> 各頁（subscription / notes / bank…）都在全域宣告同一批名稱（`batchDeleteTable`、`TABLE`…），
> 而 `var` 不能重新宣告既有的 `let`/`const` 綁定，跨頁仍會 `SyntaxError`。
> 最終改用 **IIFE 作用域隔離**，詳見 §12。

**問題**：
- 22 個頁面中有 **11 個**把初始化包在 `document.addEventListener('DOMContentLoaded', ...)`。換頁後 `DOMContentLoaded` **不會再觸發** → 這些頁面會「看起來正常但完全不能操作」。
- `assets/js/main.js` 有約 **20 處** `querySelectorAll` 直接綁事件（非全部委派），換頁後新元素不會有 handler。

**對策**：
1. 換頁時，把新 `#mainContent` 內的 `<script>` 逐一以 `document.createElement('script')` 重建並插入（`innerHTML` 插入的 script 不會執行，必須重建）。
2. 重跑前**臨時包裝** `document.addEventListener`，攔截 `DOMContentLoaded`：

```js
var realAdd = document.addEventListener;
document.addEventListener = function (type, fn, opts) {
  if (type === 'DOMContentLoaded' && document.readyState !== 'loading') {
    try { fn.call(document, new Event('DOMContentLoaded')); } catch (_) {}
    return;
  }
  return realAdd.call(document, type, fn, opts);
};
// …重跑 script…
document.addEventListener = realAdd;   // 立刻還原
```

3. **逐頁驗證**（見 §8）。驗證不過的頁面 → 從白名單移除，該頁回到整頁導覽。

> 這是整個計畫最花時間的部分，也是唯一真正需要「一頁一頁看」的部分。

### R2 — 快照失效（會污染後續的寫入後更新）

**問題**：軟導覽後 `#mainContent` 全換，但 `optimistic-ui.js` 內部的 `base` / `baseMap` 仍是**舊頁面**的快照。之後任何寫入觸發 `fengbroReload()` 時，會拿舊 base 與新頁面合併 → **畫面錯亂**。

**對策**：軟導覽完成後必須重新建立快照。目前 `snapshot()` 有 `if (!softEnabled || base) return;` 的守衛，需在 `optimistic-ui.js` 補一個重設介面：

```js
window.fengbroResetSnapshot = function () { base = null; baseMap = null; /* 重新觀察 */ };
```

軟導覽在步驟 11 呼叫 `fengbroResetSnapshot()` 後再 `fengbroSnapshotMain()`。

> **這是必做項，不是加分項。** 漏掉會造成「換頁後編輯資料，畫面自己亂掉」這種最難查的 bug。

### R3 — 監聽器與計時器洩漏

**問題**：舊頁面的 `setInterval`（例如 `footer.php` 的睡眠提示、各頁輪詢）與 `document` 級監聽在換頁後可能殘留，累積後造成重複請求或效能下降。

**對策**：
- 短期：約定頁面內註冊的計時器集中管理，軟導覽時統一清除。
- 驗證清單中明列「連續導覽 10 次後，檢查 Network 是否有重複輪詢」。

### R4 — 捲動與無障礙

- 換頁 → 捲到頂端；返回上一頁 → 還原原位置。
- `ux-boost.js` 已有捲動記憶（`SCROLL_KEY`），且 `window.fengbroHasPendingFocus()` 會影響它 → 軟導覽需與其協調，避免「回到頂端又被還原」。
- 換頁後：更新 `document.title`、對 `#mainContent` 呼叫 `focus()`（容器已有 `tabindex="-1"`），讓螢幕閱讀器察覺換頁。

### R5 — 與既有機制並存

- **進度條**：`ux-boost.js` 已自動掛 `window.fetch`，軟導覽免費獲得。
- **Speculation Rules**：`optimistic-ui.js` 已裝 prerender 規則。軟導覽的 `fetch` 用 `cache: 'no-store'`，避免拿到預取但已過期的內容。
- **fetch 攔截順序**：`ux-boost.js` 與 `optimistic-ui.js` 都會包 `window.fetch`，載入順序為 ux-boost → optimistic-ui → soft-nav，保持不變即可。
- **`handleMenuNav`**：目前負責記錄「上次選單」到 localStorage。軟導覽**不要移除它**，讓它照常執行，只在其後附加攔截。

---

## 7. 分階段實作

| 階段 | 內容 | 完成標準 |
|---|---|---|
| **Phase 1** 骨架 + 最小可用 | 新增 `soft-nav.js`：點擊攔截、fetch、換 `#mainContent`、`pushState` / `popstate`、失敗 fallback。白名單只開 `notes`、`bank` | 這兩頁導覽不白屏、網址正確、上一頁可用 |
| **Phase 2** 腳本重跑契約 | 實作 §6 R1 的 script 重跑與 `DOMContentLoaded` 攔截；實作 §6 R2 的快照重設 | 兩頁的所有互動（行內編輯、新增、刪除、篩選）在軟導覽後仍可用 |
| **Phase 3** 擴大 + 打磨 | 逐頁開啟白名單；HTML 快取、標題與 active 同步、捲動與無障礙、洩漏清理 | 白名單內所有頁面通過 §8 驗證清單 |
| **Phase 4** 回歸與收斂 | 全站逐頁回歸；決定首頁與媒體頁是否納入；撰寫 `CONTEXT.md` 補充說明 | 無 regression；文件更新 |

**每個 Phase 結束都必須維持「網站可正常運作」**（與 `CONTEXT.md` 的 Refactor Notes 一致）。

---

## 8. 驗證清單（每一頁都要跑）

- [ ] 從側邊欄導覽進該頁：內容正確、無白屏
- [ ] 網址列正確；重新整理（F5）後仍正常
- [ ] 上一頁 / 下一頁（含瀏覽器手勢）正確還原
- [ ] 行內編輯（`.inline-edit`）可用
- [ ] 新增 / 編輯 / 刪除後，`fengbroReload()` 更新正確、**畫面沒有錯亂**（R2 迴歸）
- [ ] 篩選、檢視模式切換、群組展開/收合可用
- [ ] 深色模式切換保持
- [ ] 行動版（< 768px）選單導覽正常
- [ ] 表單送出、檔案上傳正常（不應被攔截）
- [ ] 連續導覽 10 次後，Network 無重複輪詢（R3 迴歸）
- [ ] 鍵盤 Tab 順序與焦點正確（R4）

---

## 9. 回退策略

1. **逐頁白名單**：任何一頁驗證不過 → 從白名單移除，該頁立即回到整頁導覽，不影響其他頁。
2. **全域 kill switch**：`localStorage.setItem('fengbroSoftNav', '0')` 即整站停用軟導覽（模組開頭檢查）。
3. **執行期自癒**：任何例外一律 `location.href = url`，最差情況等同現在的行為。

---

## 10. 涉及檔案

**新增**
- `assets/js/soft-nav.js`

**修改**
- `includes/header.php` — 加入 `<script src="assets/js/soft-nav.js?v=...">`
- `resources/views/layouts/app.blade.php` — 同上（Laravel 版 layout）
- `assets/js/optimistic-ui.js` — 新增 `window.fengbroResetSnapshot()`（§6 R2）
- `assets/js/ux-boost.js` — 與捲動記憶協調（§6 R4）
- `CONTEXT.md` — 補充軟導覽說明

**不修改**
- 任何 `resources/views/fengbro/*.php` 頁面檔（Phase 1–3 期間）
- `includes/sidebar.php` 的既有邏輯（只依賴現有 `data-menu-url`）
- 任何 PHP 後端 / 資料庫 / 路由

---

## 11. 預期效益與非目標

**效益**
- 導覽不白屏、不重載 CSS/JS/字型，切頁近乎瞬開
- 保留外殼（側邊欄、播放器、PWA 提示）狀態，媒體不中斷
- 與既有局部更新、樂觀刪除、toast 形成一致的體驗

**非目標（明確不做）**
- 不改為 SPA、不引入前端框架或 Inertia
- 不做離線路由（PWA 已有 Service Worker，屬另一議題）
- 不追求「完全無網路請求」的導覽

---

## 12. 實作結果與驗證（已上線）

### 實際做法：IIFE 作用域隔離（取代原規劃的 const→var 改寫）

換頁時把該頁**所有 inline `<script>` 串成「一個 IIFE」**執行，再把頂層宣告的名稱選擇性發佈到 `window`。

為什麼必須這樣做（實測證據）：

- 20 個有內嵌 script 的頁面中，**17 個**直接重跑會丟 `SyntaxError: Identifier '…' has already been declared`，且整個 script 因此**完全不執行**（頁面看似正常但完全不能操作）。
- 起因是各頁共用同一批全域名稱：`subscription`、`notes`、`bank` 都在頂層宣告 `batchDeleteTable`、`batchDeleteIds`、`isSelectMode`、`TABLE`。整頁載入時每頁都是全新 document 所以沒事；軟導覽是同一個 document，就會撞名。
- 改成 `var` 也救不了：`var` 對既有的 `let`/`const` 綁定仍是重複宣告。

配套設計：

- **`DOMContentLoaded` 攔截**：22 頁中有 11 頁把初始化掛在 `DOMContentLoaded`，換頁後不會再觸發。重跑期間臨時包裝 `addEventListener`，讓回呼立即執行一次。
- **名稱發佈**：以正則收集頂層 `function` / `const` / `let` / `var` / `class` 與解構宣告的名稱，於 IIFE 結尾用 `if (typeof N !== "undefined") window["N"] = N` 發佈，供 HTML 上的 inline 事件屬性（`onclick="…"`）呼叫。過度取樣無害（`typeof` 守衛會略過不在作用域內的名稱）。
- **監聽器洩漏（§6 R3，實測確實會發生）**：頁面 inline script 常對 `document`/`window` 註冊事件。換頁後監聽仍在，會因找不到舊頁元素而每次點擊都丟 `Cannot read properties of null (reading 'style')`。對策：在 header 載入時包裝 `addEventListener`，以呼叫堆疊判斷來源（含 `assets/js/` 的共用腳本不追蹤），記錄頁面自己的監聽器，換頁前 `removeEventListener` 全部移除。
- **安全網**：內含 `src` 外部 script 的頁面一律不軟導覽（執行順序無法保證），直接退回整頁載入。

### 一併修掉的既有 bug

- **側邊欄永遠不標示目前頁面**（`includes/sidebar.php`）：`fengbroSidebarIsActive()` 用 `global $currentPage`，但 Laravel 路徑是由 Blade 樣板 `include` 本檔，include 進來的變數屬於樣板函式的區域變數而非全域，`global` 取到 `null` → 所有頁面的選單都不會 active、群組也不會 `is-open`。改為直接讀 `$_GET`（超全域），兩種路徑都正確。

### 驗證

- **腳本安全性（Node + vm）**：21 個頁面、56 段內嵌 script，連續執行 3 次；修正後 **0 個 SyntaxError**（修正前 17 頁會壞）。
- **端對端（Playwright + 真實 Chrome）**：`subscription → notes → bank → notes`、瀏覽器上一頁、以及非白名單頁退回整頁載入，共 **16/16 檢查通過**，**0 個 JS 錯誤**；以 `window` 標記驗證軟導覽期間確實沒有整頁重載。

### 已知殘留（非本次改動造成）

- `stats_api.php?action=site_visit` / `?action=menu_usage` 回 500，**整頁載入時同樣會發生**，是既有問題（fire-and-forget，不影響功能）。
- `index.php?page=home` 回 500，既有問題，與軟導覽無關。
