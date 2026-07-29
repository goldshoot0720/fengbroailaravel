<?php $pageTitle = '鋒兄關於'; ?>

<div class="content-header">
    <h1>鋒兄關於</h1>
</div>

<div class="content-body">
    <div class="card">
        <h3 class="card-title">系統資訊</h3>
        <table class="table">
            <tr>
                <th style="width: 200px;">系統名稱</th>
                <td>鋒兄 AI</td>
            </tr>
            <tr>
                <th>版本</th>
                <td>1.0.0</td>
            </tr>
            <tr>
                <th>技術架構</th>
                <td>PHP + MySQL</td>
            </tr>
            <tr>
                <th>PHP 版本</th>
                <td><?php echo phpversion(); ?></td>
            </tr>
            <tr>
                <th>執行環境</th>
                <td><?php echo strtoupper($GLOBALS['ENV']); ?></td>
            </tr>
            <tr>
                <th>freehostia</th>
                <td>hsihua19</td>
            </tr>
            <tr>
                <th>byethost</th>
                <td>b13_41820842</td>
            </tr>
            <tr>
                <th>程式碼統計</th>
                <td>
                    <?php
                    $codeStats = [
                        'php' => 0, 'css' => 0, 'js' => 0, 'sql' => 0, 'files' => 0,
                    ];
                    $root = dirname(__DIR__);
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
                    );
                    $skipDirs = ['uploads', 'vendor', 'node_modules', '.git'];
                    foreach ($iterator as $file) {
                        if (!$file->isFile()) {
                            continue;
                        }
                        $path = str_replace('\\', '/', $file->getPathname());
                        $skip = false;
                        foreach ($skipDirs as $dir) {
                            if (str_contains($path, '/' . $dir . '/')) {
                                $skip = true;
                                break;
                            }
                        }
                        if ($skip) {
                            continue;
                        }
                        $ext = strtolower($file->getExtension());
                        if (!isset($codeStats[$ext])) {
                            continue;
                        }
                        $lines = @count(file($file->getPathname()) ?: []);
                        $codeStats[$ext] += $lines;
                        $codeStats['files']++;
                    }
                    $totalLines = $codeStats['php'] + $codeStats['css'] + $codeStats['js'] + $codeStats['sql'];
                    ?>
                    <strong><?php echo number_format($totalLines); ?></strong> 行
                    <span style="color:#888;font-size:0.85rem;margin-left:8px;">
                        (<?php echo (int) $codeStats['files']; ?> 個檔案
                        .php: <?php echo number_format($codeStats['php']); ?>
                        &nbsp;|&nbsp; .css: <?php echo number_format($codeStats['css']); ?>
                        &nbsp;|&nbsp; .js: <?php echo number_format($codeStats['js']); ?>
                        &nbsp;|&nbsp; .sql: <?php echo number_format($codeStats['sql']); ?>)
                    </span>
                    <br><small style="color:#aaa;">統計日期：<?php echo date('Y-m-d'); ?>（執行時即時計算）</small>
                </td>
            </tr>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">功能模組</h3>
        <table class="table">
            <tr>
                <th style="width: 150px;">首頁</th>
                <td>個人作業中樞，快速掌握重要狀態與提醒。</td>
            </tr>
            <tr>
                <th>儀表板</th>
                <td>整合訂閱、食品、工具與近期狀態摘要。</td>
            </tr>
            <tr>
                <th>訂閱管理</th>
                <td>管理服務名稱、費用、付款日期、續訂狀態與重複訂閱提醒。</td>
            </tr>
            <tr>
                <th>食品管理</th>
                <td>追蹤食品、庫存、到期日與快速新增常用項目。</td>
            </tr>
            <tr>
                <th>筆記資料</th>
                <td>整理筆記、文章與日常資料內容。</td>
            </tr>
            <tr>
                <th>常用帳號</th>
                <td>管理常用帳號資料，支援快速查找與整理。</td>
            </tr>
            <tr>
                <th>圖片庫</th>
                <td>管理圖片檔案與圖片資料；支援 IndexedDB 離線快取與燈箱預覽（上限 500MB）。</td>
            </tr>
            <tr>
                <th>影片庫</th>
                <td>管理影片檔案、封面與播放；支援 IndexedDB 離線快取（上限 500MB）。</td>
            </tr>
            <tr>
                <th>音樂庫</th>
                <td>管理音樂檔案、封面、歌詞與播放器；支援 IndexedDB 離線快取（上限 500MB）。</td>
            </tr>
            <tr>
                <th>文件庫</th>
                <td>管理文件檔案，支援多選上傳、預覽與 IndexedDB 離線快取（上限 500MB）。</td>
            </tr>
            <tr>
                <th>播客庫</th>
                <td>管理播客集數與音訊資料；支援 IndexedDB 離線快取（上限 500MB）。</td>
            </tr>
            <tr>
                <th>銀行資料</th>
                <td>整理銀行、帳戶與金融相關資料。</td>
            </tr>
            <tr>
                <th>例行事項</th>
                <td>管理日常例行任務、提醒與狀態。</td>
            </tr>
            <tr>
                <th>工具模組</th>
                <td>鋒兄比價（BigGo）、手動價格、手機比價（地標網通 + 傑昇通信）、鋒兄tube、鋒兄金融、鋒兄新聞（含台鐵便當門市）、PNG/JPEG 轉換。</td>
            </tr>
            <tr>
                <th>系統設定</th>
                <td>管理系統設定、儲存空間與維護工具。</td>
            </tr>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">近期對齊更新（PHP 版）</h3>
        <ul style="line-height: 1.9; padding-left: 20px; margin: 8px 0 0;">
            <li>工具：手機比價（地標+傑昇）、手動價格紀錄、鋒兄新聞、PNG/JPEG 轉換、金融 1Y/5Y/10Y、tube 倒台指數</li>
            <li>媒體：影片/音樂/播客/文件/圖片 IndexedDB 離線快取（500MB/類型）與批次快取</li>
            <li>設定/儀表：離線快取管理、uploads 分類統計、Offline cache 用量</li>
            <li>匯入：CSV 遮罩進度；食品/訂閱/銀行/常用與大檔分批寫入</li>
            <li>體驗：主題 system/light/dark 三態、首頁提醒可今日關閉、銀行刪除確認字串</li>
        </ul>
        <p style="margin-top: 12px; color: var(--muted-text); font-size: 0.9rem;">
            刻意不移植的 Appwrite 專屬內容：Appwrite 帳號切換、PlumberTycoon / CatShowcase / CEO 展示模組、Appwrite Storage SDK。
        </p>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">資料表結構</h3>
        <p style="line-height: 1.8;">
            目前系統主要使用下列資料表：
        </p>
        <ul style="line-height: 2; padding-left: 20px; margin-top: 10px;">
            <li><code>subscription</code> - 訂閱管理</li>
            <li><code>food</code> - 食品庫存</li>
            <li><code>notes</code> / <code>article</code> - 筆記與文章</li>
            <li><code>commonaccount</code> - 常用帳號</li>
            <li><code>image</code> - 圖片資料</li>
            <li><code>music</code> - 音樂資料</li>
            <li><code>podcast</code> - 播客資料</li>
            <li><code>commondocument</code> - 文件資料</li>
            <li><code>video</code> - 影片資料</li>
            <li><code>bank</code> - 銀行資料</li>
            <li><code>routine</code> - 例行事項</li>
            <li><code>settings</code> - 系統設定</li>
            <li><code>tool_price_history</code> - 比價歷史</li>
            <li><code>tool_phone_product_history</code> - 手機比價商品每日快照</li>
        </ul>
    </div>
</div>
