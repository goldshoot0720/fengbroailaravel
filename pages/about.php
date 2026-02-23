<?php $pageTitle = '關於系統'; ?>

<div class="content-header">
    <h1>鋒兄關於</h1>
</div>

<div class="content-body">
    <div class="card">
        <h3 class="card-title">系統資訊</h3>
        <table class="table">
            <tr>
                <th style="width: 200px;">系統名稱</th>
                <td>鋒兄系統</td>
            </tr>
            <tr>
                <th>版本</th>
                <td>1.0.0</td>
            </tr>
            <tr>
                <th>開發語言</th>
                <td>PHP + MySQL</td>
            </tr>
            <tr>
                <th>PHP 版本</th>
                <td><?php echo phpversion(); ?></td>
            </tr>
            <tr>
                <th>目前環境</th>
                <td><?php echo strtoupper($GLOBALS['ENV']); ?></td>
            </tr>
            <tr>
                <th>程式碼行數</th>
                <td>
                    <strong>13,610</strong> 行
                    <span style="color:#888;font-size:0.85rem;margin-left:8px;">
                        (.php: 11,625 &nbsp;|&nbsp; .css: 968 &nbsp;|&nbsp; .js: 726 &nbsp;|&nbsp; .sql: 291)
                    </span>
                    <br><small style="color:#aaa;">統計日期：2026-02-24</small>
                </td>
            </tr>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">功能說明</h3>
        <table class="table">
            <tr>
                <th style="width: 150px;">首頁</th>
                <td>系統總覽，顯示各項目數量統計</td>
            </tr>
            <tr>
                <th>儀表板</th>
                <td>詳細統計資訊與最近記錄</td>
            </tr>
            <tr>
                <th>訂閱管理</th>
                <td>管理各類訂閱服務，追蹤付款日期與費用</td>
            </tr>
            <tr>
                <th>食品管理</th>
                <td>追蹤食品存貨、價格與有效期限</td>
            </tr>
            <tr>
                <th>筆記本</th>
                <td>記錄重要筆記與文章，支援分類與連結</td>
            </tr>
            <tr>
                <th>常用項目</th>
                <td>儲存常用帳號資訊，最多支援 37 組欄位</td>
            </tr>
            <tr>
                <th>圖片管理</th>
                <td>管理圖片檔案與封面</td>
            </tr>
            <tr>
                <th>影片管理</th>
                <td>管理影片檔案</td>
            </tr>
            <tr>
                <th>音樂管理</th>
                <td>管理音樂檔案，支援歌詞儲存</td>
            </tr>
            <tr>
                <th>文件管理</th>
                <td>管理各類文件檔案</td>
            </tr>
            <tr>
                <th>播客管理</th>
                <td>管理播客節目</td>
            </tr>
            <tr>
                <th>銀行管理</th>
                <td>追蹤銀行帳戶、存款、提款與轉帳</td>
            </tr>
            <tr>
                <th>例行事項</th>
                <td>管理週期性任務，記錄最近三次執行時間</td>
            </tr>
            <tr>
                <th>系統設定</th>
                <td>查看系統配置與資料庫統計</td>
            </tr>
        </table>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h3 class="card-title">資料表結構</h3>
        <p style="line-height: 1.8;">
            本系統使用以下資料表：
        </p>
        <ul style="line-height: 2; padding-left: 20px; margin-top: 10px;">
            <li><code>subscription</code> - 訂閱服務</li>
            <li><code>food</code> - 食品紀錄</li>
            <li><code>article</code> - 筆記/文章</li>
            <li><code>commonaccount</code> - 常用帳號 (37 組欄位)</li>
            <li><code>image</code> - 圖片</li>
            <li><code>music</code> - 音樂 (含歌詞)</li>
            <li><code>podcast</code> - 播客</li>
            <li><code>commondocument</code> - 文件/影片</li>
            <li><code>bank</code> - 銀行帳戶</li>
            <li><code>routine</code> - 例行事項</li>
        </ul>
    </div>
</div>