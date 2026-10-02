<?php
$pageTitle = '鋒兄 Udemy';
$pdo = getConnection();
fengbroEnsureUdemyTable($pdo);

$rows = $pdo->query("SELECT * FROM udemy ORDER BY name ASC, created_at ASC")->fetchAll();

$courses = [];
foreach ($rows as $row) {
    $updatedAt = trim((string) ($row['courseUpdatedAt'] ?? ''));
    $courses[] = [
        'id' => (string) $row['id'],
        'name' => (string) ($row['name'] ?? ''),
        'instructor' => trim((string) ($row['instructor'] ?? '')),
        'language' => trim((string) ($row['language'] ?? '')),
        'framework' => trim((string) ($row['framework'] ?? '')),
        'technology' => trim((string) ($row['technology'] ?? '')),
        'watchedLectures' => (int) ($row['watchedLectures'] ?? 0),
        'totalLectures' => (int) ($row['totalLectures'] ?? 0),
        'courseUpdatedAt' => $updatedAt !== '' ? substr($updatedAt, 0, 10) : '',
        'totalHours' => round((float) ($row['totalHours'] ?? 0), 2),
        'completed' => !empty($row['completed']),
    ];
}

$totalCount = count($courses);
$overall = fengbroUdemySummary($courses);
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<div class="content-header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="margin:0;">鋒兄 Udemy</h1>
        <p class="muted-copy">記錄 Udemy 課程、講師、程式語言／框架／技術與觀看進度。可依講師、課程名稱、程式語言、框架、技術名稱或收看狀態分類，一眼看出已觀看堂數與觀看比重。</p>
    </div>
    <span class="count-pill count-pill-udemy"><?php echo $totalCount; ?> 門課程</span>
</div>

<div class="content-body">
    <?php if ($totalCount > 0): ?>
        <div class="mgmt-stat-grid">
            <div class="food-stat-card">
                <span>課程數</span>
                <strong><?php echo $totalCount; ?> 門</strong>
            </div>
            <div class="food-stat-card food-stat-success">
                <span>已經完整收看</span>
                <strong><?php echo $overall['completedCount']; ?> / <?php echo $totalCount; ?> 門</strong>
            </div>
            <div class="food-stat-card">
                <span>已觀看堂數 / 課程總堂數</span>
                <strong><?php echo number_format($overall['watched']); ?> / <?php echo number_format($overall['total']); ?> 堂</strong>
                <?php if ($overall['hours'] > 0): ?><small>課程總時長 <?php echo rtrim(rtrim(number_format($overall['hours'], 1, '.', ''), '0'), '.'); ?> 小時</small><?php endif; ?>
            </div>
            <div class="food-stat-card food-stat-highlight">
                <span>已經觀看比重</span>
                <strong><?php echo $overall['percent']; ?>%</strong>
                <div class="udemy-progress" role="progressbar" aria-label="全部課程觀看比重" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $overall['percent']; ?>">
                    <div class="udemy-progress-fill<?php echo $overall['percent'] >= 100 ? ' is-done' : ''; ?>" style="width:<?php echo $overall['percent']; ?>%"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="action-buttons-bar">
        <button class="btn btn-primary" type="button" onclick="openUdemyForm()"><i class="fas fa-plus"></i> 新增課程</button>
        <button class="btn btn-success" type="button" onclick="exportUdemyCsv()" title="匯出目前全部課程為 CSV"><i class="fa-solid fa-download"></i> 匯出 CSV</button>
        <button class="btn" type="button" onclick="document.getElementById('udemyCsvFile').click()" title="從 CSV 匯入課程（相同課程名稱會更新）"><i class="fa-solid fa-upload"></i> 匯入 CSV</button>
        <input type="file" id="udemyCsvFile" accept=".csv,text/csv" style="display:none;" onchange="handleUdemyCsvFile(this)">
        <?php include 'includes/batch-delete.php'; ?>
    </div>

    <form id="udemyForm" class="card mgmt-form" style="display:none;" onsubmit="return saveUdemyCourse(event)">
        <h3 class="card-title" id="udemyFormTitle">新增課程</h3>
        <p class="muted-copy">程式語言／框架／技術名稱可填多個，以「,」或「、」分隔。已觀看堂數到達課程總堂數時，會自動勾選「課程已經完整收看」。</p>
        <input type="hidden" id="udemyId" value="">
        <div class="mgmt-form-grid">
            <label class="mgmt-span-2">課程名稱 <span class="req">*</span>
                <input class="form-control" id="udemyName" name="name" maxlength="200" required placeholder="例如 The Complete JavaScript Course">
            </label>
            <label>講師名稱
                <input class="form-control" id="udemyInstructor" name="instructor" maxlength="200" list="udemyInstructorList" placeholder="例如 Jonas Schmedtmann" autocomplete="off">
                <datalist id="udemyInstructorList"></datalist>
            </label>
            <label>程式語言
                <input class="form-control udemy-tag-input" id="udemyLanguage" name="language" data-tag-field="language" maxlength="200" list="udemyLanguageList" placeholder="例如 JavaScript、Python" autocomplete="off">
                <datalist id="udemyLanguageList"></datalist>
            </label>
            <label>框架
                <input class="form-control udemy-tag-input" id="udemyFramework" name="framework" data-tag-field="framework" maxlength="200" list="udemyFrameworkList" placeholder="例如 React、Next.js" autocomplete="off">
                <datalist id="udemyFrameworkList"></datalist>
            </label>
            <label>技術名稱
                <input class="form-control udemy-tag-input" id="udemyTechnology" name="technology" data-tag-field="technology" maxlength="200" list="udemyTechnologyList" placeholder="例如 Docker、AWS" autocomplete="off">
                <datalist id="udemyTechnologyList"></datalist>
            </label>
            <label>已觀看堂數
                <input class="form-control" id="udemyWatched" name="watchedLectures" type="number" inputmode="numeric" min="0" step="1" value="0">
            </label>
            <label>課程總堂數
                <input class="form-control" id="udemyTotal" name="totalLectures" type="number" inputmode="numeric" min="0" step="1" value="0">
            </label>
            <label>課程總時長（小時）
                <input class="form-control" id="udemyHours" name="totalHours" type="number" inputmode="decimal" min="0" step="0.1" value="0">
            </label>
            <label>課程上次更新時間
                <input class="form-control" id="udemyUpdatedAt" name="courseUpdatedAt" type="date">
            </label>
            <label class="udemy-check-label">
                <span><input type="checkbox" id="udemyCompleted" name="completed"> 課程已經完整收看</span>
            </label>
            <div class="mgmt-span-2">
                <p class="udemy-form-percent">已經觀看比重 <strong id="udemyFormPercent">0%</strong></p>
                <div class="udemy-progress" role="progressbar" aria-label="此課程觀看比重" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="udemyFormProgress">
                    <div class="udemy-progress-fill" id="udemyFormProgressFill" style="width:0%"></div>
                </div>
            </div>
        </div>
        <div class="inline-actions" style="margin-top:16px;">
            <button type="submit" class="btn btn-primary" id="udemySaveBtn">新增課程</button>
            <button type="button" class="btn" onclick="closeUdemyForm()">取消</button>
        </div>
    </form>

    <div class="mgmt-filter-bar">
        <label class="food-search-box">
            <i class="fas fa-search"></i>
            <input type="search" id="udemySearchInput" class="form-control" placeholder="搜尋課程、講師、程式語言、框架或技術名稱" oninput="renderUdemy()">
        </label>
        <label class="udemy-filter-label">依此分類
            <select id="udemyGroupMode" class="form-control" onchange="renderUdemy(true)">
                <option value="instructor">講師</option>
                <option value="name">課程名稱</option>
                <option value="language">程式語言</option>
                <option value="framework">框架</option>
                <option value="technology">技術名稱</option>
                <option value="status">收看狀態</option>
            </select>
        </label>
        <select id="udemyStatusFilter" class="form-control" onchange="renderUdemy(true)" aria-label="收看狀態篩選">
            <option value="all">全部</option>
            <option value="completed">已完整收看</option>
            <option value="incomplete">尚未完整收看</option>
        </select>
        <span class="food-result-count" id="udemyVisibleCount"><?php echo $totalCount; ?> 門</span>
    </div>

    <?php if ($totalCount === 0): ?>
        <div class="card" style="text-align:center;color:var(--muted-text);padding:40px;">
            <p style="margin:0 0 12px;"><i class="fa-solid fa-graduation-cap" style="font-size:1.6rem;"></i></p>
            尚無課程。先新增第一門 Udemy 課程與觀看進度。
        </div>
    <?php endif; ?>
    <div id="udemyGroups"></div>

    <div id="udemyImportOverlay" class="quota-import-overlay" style="display:none;">
        <div class="quota-import-panel" role="dialog" aria-modal="true" aria-labelledby="udemyImportTitle">
            <h3 class="card-title" id="udemyImportTitle">匯入 CSV 預覽</h3>
            <p class="muted-copy">以「課程名稱」對應：相同名稱（忽略大小寫與前後空白）更新既有課程，其餘新增。有任何格式錯誤時不會寫入。</p>
            <div id="udemyImportResult" class="quota-import-result" style="display:none;"></div>
            <div id="udemyImportErrors" class="quota-import-errors" style="display:none;"></div>
            <div id="udemyImportRows" class="quota-import-rows"></div>
            <div class="inline-actions" style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn" id="udemyImportCancelBtn" onclick="closeUdemyImport()">取消</button>
                <button type="button" class="btn btn-primary" id="udemyImportConfirmBtn" onclick="executeUdemyImport()">確認匯入</button>
            </div>
        </div>
    </div>
</div>

<style>
    .muted-copy { margin: 8px 0 0; color: var(--muted-text); line-height: 1.6; }
    .count-pill { color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; white-space: nowrap; }
    .count-pill-udemy { background: #8a4fbf; }
    .mgmt-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .food-stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; padding: 14px 16px; box-shadow: 0 12px 26px var(--shadow); }
    .food-stat-card span { display: block; color: var(--muted-text); font-size: 0.82rem; margin-bottom: 6px; }
    .food-stat-card strong { font-size: 1.35rem; font-variant-numeric: tabular-nums; }
    .food-stat-card small { display: block; color: var(--muted-text); font-size: 0.78rem; margin-top: 2px; }
    .food-stat-card .udemy-progress { margin-top: 8px; }
    .food-stat-success strong { color: var(--success, #2b5c40); }
    .food-stat-highlight strong { color: var(--accent, #b06a3f); }
    .action-buttons-bar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .mgmt-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
    .mgmt-form-grid label { display: grid; gap: 6px; font-weight: 600; }
    .mgmt-span-2 { grid-column: 1 / -1; }
    .req { color: #c1554a; }
    .udemy-check-label { align-content: end; }
    .udemy-check-label span { display: inline-flex; align-items: center; gap: 8px; min-height: 40px; cursor: pointer; }
    .udemy-check-label input { width: 18px; height: 18px; accent-color: var(--accent, #c1613d); }
    .udemy-form-percent { margin: 0 0 6px; color: var(--muted-text); font-size: 0.9rem; }
    .udemy-form-percent strong { color: var(--text-color); font-variant-numeric: tabular-nums; }
    .mgmt-filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 8px 0 18px; }
    .food-search-box { position: relative; flex: 1 1 260px; }
    .food-search-box i { position: absolute; top: 50%; left: 12px; transform: translateY(-50%); color: var(--muted-text); }
    .food-search-box input { padding-left: 38px; }
    .mgmt-filter-bar select { min-width: 150px; width: auto; }
    .udemy-filter-label { display: inline-flex; align-items: center; gap: 8px; color: var(--muted-text); font-size: 0.9rem; white-space: nowrap; }

    .udemy-progress { height: 8px; width: 100%; border-radius: 999px; background: var(--surface-2, #f0eee6); overflow: hidden; }
    .udemy-progress-fill { height: 100%; border-radius: 999px; background: var(--accent, #c1613d); transition: width .2s ease; }
    .udemy-progress-fill.is-done { background: var(--success, #3f8a5f); }

    .udemy-group { margin-bottom: 22px; }
    .udemy-group-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; margin: 0 0 10px; }
    .udemy-group-title { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--text-color); min-width: 0; }
    .udemy-group-title i { color: var(--muted-text); }
    .udemy-group-title small { font-weight: 400; color: var(--muted-text); font-size: 0.85rem; }
    .udemy-group-stats { display: flex; align-items: center; gap: 10px; width: min(320px, 100%); color: var(--muted-text); font-size: 0.85rem; font-variant-numeric: tabular-nums; }
    .udemy-group-stats .udemy-progress { flex: 1; }
    .udemy-group-stats strong { color: var(--text-color); min-width: 2.6em; text-align: right; }

    .udemy-table-wrap { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 18px; overflow: hidden; }
    /* 勾選欄只在全選模式顯示；其餘時間寬度為 0，避免隱藏的 checkbox 讓其他欄位錯位 */
    .udemy-table-wrap { --udemy-select-col: 0px; }
    .select-mode .udemy-table-wrap { --udemy-select-col: 28px; }
    .udemy-select-cell { display: flex; align-items: center; min-width: 0; overflow: hidden; }
    .udemy-row { display: grid; grid-template-columns: var(--udemy-select-col) minmax(13rem, 1.6fr) minmax(7rem, 0.8fr) minmax(9rem, 1fr) minmax(10rem, 1fr) minmax(5rem, 0.5fr) minmax(5.5rem, 0.55fr) 116px; gap: 12px; align-items: center; padding: 12px 16px; }
    .udemy-head { color: var(--muted-text); font-size: 0.78rem; font-weight: 700; border-bottom: 1px solid var(--border-color); background: var(--table-header-bg, #faf9f5); }
    .udemy-item { border-bottom: 1px solid var(--border-color); }
    .udemy-item:last-child { border-bottom: 0; }
    .udemy-cell-main { min-width: 0; }
    .udemy-cell-main strong { display: block; word-break: break-word; }
    .udemy-lectures { display: flex; justify-content: space-between; gap: 8px; font-size: 0.88rem; margin-bottom: 6px; font-variant-numeric: tabular-nums; }
    .udemy-lectures strong { font-weight: 700; }
    .udemy-muted { color: var(--muted-text); font-size: 0.88rem; font-variant-numeric: tabular-nums; }
    .udemy-tags { display: flex; flex-wrap: wrap; gap: 4px; margin: 0; padding: 0; list-style: none; }
    .udemy-tag { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-radius: 6px; padding: 2px 7px; font-size: 0.75rem; font-weight: 600; }
    .udemy-tag-language { background: #e3ecf6; color: #2c5282; }
    .udemy-tag-framework { background: var(--accent-soft, #f6e5db); color: #9c4726; }
    .udemy-tag-technology { background: var(--surface-2, #f0eee6); color: var(--muted-text); }
    [data-theme="dark"] .udemy-tag-language { background: rgba(120, 165, 220, 0.16); color: #9cc2ec; }
    [data-theme="dark"] .udemy-tag-framework { color: var(--accent, #e08a68); }
    .status-chip { display: inline-block; margin: 4px 4px 0 0; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
    .chip-success { background: var(--success-soft, #e3efe5); color: var(--success, #2b5c40); }
    .chip-muted { background: var(--surface-2, #f0eee6); color: var(--muted-text); }
    .muted-dash { color: var(--muted-text); }
    .mgmt-mobile-label { display: none; }
    .mgmt-row-actions { display: flex; gap: 6px; }

    .quota-import-overlay { position: fixed; inset: 0; z-index: 120; background: rgba(30, 26, 20, 0.55); display: flex; align-items: center; justify-content: center; padding: 16px; }
    .quota-import-panel { width: min(560px, 100%); max-height: 85vh; overflow-y: auto; background: var(--card-bg); border-radius: 18px; padding: 22px 24px; box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28); }
    .quota-import-result { margin-top: 10px; padding: 8px 12px; border-radius: 10px; background: #e3efe5; color: #2b5c40; font-weight: 600; }
    .quota-import-errors { margin-top: 10px; padding: 8px 12px; border-radius: 10px; background: #f6e0dd; color: #6e2a23; max-height: 140px; overflow-y: auto; font-size: 0.85rem; }
    .quota-import-rows { margin-top: 10px; }
    .quota-import-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 7px 10px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; }
    .quota-import-row:last-child { border-bottom: 0; }
    .quota-import-row .qname { font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .quota-import-row .qmeta { display: block; color: var(--muted-text); font-size: 0.78rem; font-weight: 400; }
    .quota-import-row .qstatus-new { color: #2b5c40; font-size: 0.75rem; font-weight: 700; background: #e3efe5; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
    .quota-import-row .qstatus-update { color: #6f5518; font-size: 0.75rem; font-weight: 700; background: #f7ecd9; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }

    @media (max-width: 1100px) {
        .udemy-row { grid-template-columns: var(--udemy-select-col) minmax(12rem, 1.5fr) minmax(9rem, 1fr) minmax(10rem, 1fr) minmax(5.5rem, 0.6fr) 116px; }
        .udemy-head { display: none; }
        .udemy-item > .udemy-cell-instructor, .udemy-item > .udemy-cell-hours { display: none; }
    }
    @media (max-width: 860px) {
        .udemy-item { grid-template-columns: var(--udemy-select-col) 1fr; gap: 10px 12px; }
        .udemy-item > * { grid-column: 2; }
        .udemy-item > .udemy-select-cell { grid-column: 1; grid-row: 1; }
        .udemy-item > .udemy-cell-main { grid-column: 2; grid-row: 1; }
        .udemy-item > .udemy-cell-instructor, .udemy-item > .udemy-cell-hours { display: block; }
        .mgmt-mobile-label { display: block; font-size: 0.72rem; color: var(--muted-text); margin-bottom: 4px; }
        .udemy-group-stats { width: 100%; }
    }
</style>

<script>
    const TABLE = 'udemy';
    initBatchDelete(TABLE);

    const UDEMY_COURSES = <?php echo json_encode($courses, $jsonFlags); ?>;
    const UDEMY_CSV_HEADERS = ['name', 'instructor', 'language', 'framework', 'technology', 'watchedLectures', 'totalLectures', 'courseUpdatedAt', 'totalHours', 'completed'];
    const UDEMY_HEADER_ALIASES = {
        'name': 'name', '課程名稱': 'name', '課程': 'name', '名稱': 'name',
        'instructor': 'instructor', '講師名稱': 'instructor', '講師': 'instructor',
        'language': 'language', '程式語言': 'language', '語言': 'language',
        'framework': 'framework', '框架': 'framework',
        'technology': 'technology', '技術名稱': 'technology', '技術': 'technology',
        'watchedlectures': 'watchedLectures', '已觀看堂數': 'watchedLectures', '已看堂數': 'watchedLectures',
        'totallectures': 'totalLectures', '課程總堂數': 'totalLectures', '總堂數': 'totalLectures',
        'courseupdatedat': 'courseUpdatedAt', '課程上次更新時間': 'courseUpdatedAt', '上次更新': 'courseUpdatedAt',
        'totalhours': 'totalHours', '課程總時長小時': 'totalHours', '課程總時長（小時）': 'totalHours', '課程總時長(小時)': 'totalHours', '總時長': 'totalHours',
        'completed': 'completed', '課程已經完整收看': 'completed', '已看完': 'completed'
    };
    const UDEMY_TRUE_VALUES = ['true', 'yes', '1', '是', '已看完', 'v', '✓'];
    const UDEMY_FALSE_VALUES = ['', 'false', 'no', '0', '否', '未看完'];
    const UDEMY_UNSET_LABEL = { instructor: '未填講師', language: '未填程式語言', framework: '未填框架', technology: '未填技術名稱' };
    const UDEMY_GROUP_ICONS = { instructor: 'fa-user', language: 'fa-code', framework: 'fa-tag', technology: 'fa-tag', status: 'fa-circle-check' };
    const UDEMY_TAG_LABELS = { language: '語言', framework: '框架', technology: '技術' };
    const UDEMY_PREF_KEY = 'fengbro_udemy_view';

    // ---- 共用小工具 ----
    function udemyEscape(value) {
        return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function udemySplitTags(value) {
        const seen = [];
        String(value || '').split(/[,、，]/).forEach(function (tag) {
            const t = tag.trim();
            if (t && seen.indexOf(t) === -1) seen.push(t);
        });
        return seen;
    }

    function udemyCompareText(a, b) {
        return String(a || '').localeCompare(String(b || ''), 'zh-Hant');
    }

    function udemyNameKey(name) {
        return String(name || '').trim().toLowerCase();
    }

    /** 已觀看比重（0–100）；完整收看一律算 100%，未填總堂數算 0%。 */
    function udemyWatchedPercent(course) {
        if (course.completed) return 100;
        const total = Number(course.totalLectures) || 0;
        if (total <= 0) return 0;
        return Math.min(100, Math.round(((Number(course.watchedLectures) || 0) / total) * 100));
    }

    /** 群組合計：已觀看／總堂數與比重（已完整收看的課程以總堂數計）。 */
    function udemySummarize(courses) {
        let watched = 0;
        let total = 0;
        courses.forEach(function (course) {
            const courseTotal = Number(course.totalLectures) || 0;
            const courseWatched = Number(course.watchedLectures) || 0;
            total += courseTotal;
            watched += course.completed ? courseTotal : (courseTotal > 0 ? Math.min(courseWatched, courseTotal) : courseWatched);
        });
        return { watched: watched, total: total, percent: total > 0 ? Math.round((watched / total) * 100) : 0 };
    }

    function udemyFormatHours(value) {
        const hours = Number(value) || 0;
        return hours ? (Number.isInteger(hours) ? hours : hours.toFixed(1)) + ' 小時' : '—';
    }

    function udemyFormatUpdated(value) {
        return value ? String(value).slice(0, 7).replace('-', '/') : '未填';
    }

    function udemyProgressHtml(percent, label) {
        return '<div class="udemy-progress" role="progressbar" aria-label="' + udemyEscape(label) + '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + percent + '">' +
            '<div class="udemy-progress-fill' + (percent >= 100 ? ' is-done' : '') + '" style="width:' + percent + '%"></div></div>';
    }

    function udemyGroupValues(course, field) {
        if (field === 'instructor') return course.instructor ? [course.instructor] : [];
        return udemySplitTags(course[field]);
    }

    function udemyFindCourse(id) {
        return UDEMY_COURSES.find(function (course) { return course.id === id; }) || null;
    }

    function udemyLoadPrefs() {
        try {
            const saved = JSON.parse(localStorage.getItem(UDEMY_PREF_KEY) || '{}');
            const groupSel = document.getElementById('udemyGroupMode');
            const statusSel = document.getElementById('udemyStatusFilter');
            if (saved.group && groupSel.querySelector('option[value="' + saved.group + '"]')) groupSel.value = saved.group;
            if (saved.status && statusSel.querySelector('option[value="' + saved.status + '"]')) statusSel.value = saved.status;
        } catch (e) { /* 沒有儲存空間時用預設值 */ }
    }

    function udemySavePrefs() {
        try {
            localStorage.setItem(UDEMY_PREF_KEY, JSON.stringify({
                group: document.getElementById('udemyGroupMode').value,
                status: document.getElementById('udemyStatusFilter').value
            }));
        } catch (e) { /* 忽略 */ }
    }

    // ---- 分類與列表 ----
    function udemyGroupCourses(courses, mode) {
        const sorted = courses.slice().sort(function (a, b) { return udemyCompareText(a.name, b.name); });
        if (mode === 'name') return [{ key: 'all', title: '', courses: sorted }];
        if (mode === 'status') {
            return [
                { key: 'incomplete', title: '課程尚未完整收看', courses: sorted.filter(function (c) { return !c.completed; }) },
                { key: 'completed', title: '課程已經完整收看', courses: sorted.filter(function (c) { return c.completed; }) }
            ].filter(function (group) { return group.courses.length > 0; });
        }
        const unset = UDEMY_UNSET_LABEL[mode];
        const byValue = new Map();
        sorted.forEach(function (course) {
            const values = udemyGroupValues(course, mode);
            (values.length ? values : [unset]).forEach(function (value) {
                if (!byValue.has(value)) byValue.set(value, []);
                byValue.get(value).push(course);
            });
        });
        return Array.from(byValue.entries())
            .sort(function (a, b) { return a[0] === unset ? 1 : b[0] === unset ? -1 : udemyCompareText(a[0], b[0]); })
            .map(function (entry) { return { key: entry[0], title: entry[0], courses: entry[1] }; });
    }

    function udemyTagsHtml(course) {
        const tags = [];
        ['language', 'framework', 'technology'].forEach(function (field) {
            udemySplitTags(course[field]).forEach(function (value) {
                tags.push('<li class="udemy-tag udemy-tag-' + field + '" title="' + udemyEscape(UDEMY_TAG_LABELS[field] + '：' + value) + '">' + udemyEscape(value) + '</li>');
            });
        });
        return tags.length ? '<ul class="udemy-tags" aria-label="程式語言、框架與技術名稱">' + tags.join('') + '</ul>' : '<span class="muted-dash">—</span>';
    }

    function udemyRowHtml(course) {
        const id = udemyEscape(course.id);
        const percent = udemyWatchedPercent(course);
        const watched = Number(course.watchedLectures) || 0;
        const total = Number(course.totalLectures) || 0;
        const checked = (typeof batchDeleteIds !== 'undefined' && batchDeleteIds.has(course.id)) ? ' checked' : '';
        return '<article class="udemy-row udemy-item" data-id="' + id + '">' +
            '<span class="udemy-select-cell"><input type="checkbox" class="select-checkbox item-checkbox" data-id="' + id + '" aria-label="選取 ' + udemyEscape(course.name) + '" onchange="udemyToggleSelect(this)"' + checked + '></span>' +
            '<div class="udemy-cell-main"><span class="mgmt-mobile-label">課程名稱</span><strong>' + udemyEscape(course.name) + '</strong>' +
            '<span class="status-chip ' + (course.completed ? 'chip-success' : 'chip-muted') + '">' + (course.completed ? '已完整收看' : '尚未完整收看') + '</span></div>' +
            '<div class="udemy-cell-instructor"><span class="mgmt-mobile-label">講師名稱</span>' + (course.instructor ? udemyEscape(course.instructor) : '<span class="muted-dash">—</span>') + '</div>' +
            '<div><span class="mgmt-mobile-label">程式語言／框架／技術</span>' + udemyTagsHtml(course) + '</div>' +
            '<div><span class="mgmt-mobile-label">已觀看堂數 / 總堂數</span><div class="udemy-lectures"><span>' + watched + ' / ' + total + ' 堂</span><strong>' + percent + '%</strong></div>' +
            udemyProgressHtml(percent, course.name + ' 觀看比重') + '</div>' +
            '<div class="udemy-cell-hours"><span class="mgmt-mobile-label">總時長</span><span class="udemy-muted">' + udemyFormatHours(course.totalHours) + '</span></div>' +
            '<div><span class="mgmt-mobile-label">課程上次更新</span><span class="udemy-muted">' + udemyFormatUpdated(course.courseUpdatedAt) + '</span></div>' +
            '<div class="mgmt-row-actions">' +
            '<button type="button" class="btn btn-sm" onclick="copyUdemyCourse(\'' + id + '\')" title="複製此課程（預先填好欄位，供你確認後新增）" aria-label="複製 ' + udemyEscape(course.name) + '"><i class="fa-solid fa-copy"></i></button>' +
            '<button type="button" class="btn btn-sm btn-primary" onclick="openUdemyForm(\'' + id + '\')" title="編輯" aria-label="編輯 ' + udemyEscape(course.name) + '"><i class="fas fa-pen"></i></button>' +
            '<button type="button" class="btn btn-sm btn-danger" onclick="deleteUdemyCourse(\'' + id + '\')" title="刪除" aria-label="刪除 ' + udemyEscape(course.name) + '"><i class="fas fa-trash"></i></button>' +
            '</div></article>';
    }

    function renderUdemy(savePrefs) {
        if (savePrefs) udemySavePrefs();
        const container = document.getElementById('udemyGroups');
        const counter = document.getElementById('udemyVisibleCount');
        if (UDEMY_COURSES.length === 0) {
            container.innerHTML = '';
            if (counter) counter.textContent = '0 門';
            return;
        }
        const query = (document.getElementById('udemySearchInput').value || '').trim().toLowerCase();
        const mode = document.getElementById('udemyGroupMode').value;
        const status = document.getElementById('udemyStatusFilter').value;
        const filtered = UDEMY_COURSES.filter(function (course) {
            if (status === 'completed' && !course.completed) return false;
            if (status === 'incomplete' && course.completed) return false;
            return !query || [course.name, course.instructor, course.language, course.framework, course.technology].some(function (value) {
                return String(value || '').toLowerCase().indexOf(query) !== -1;
            });
        });
        if (counter) counter.textContent = filtered.length + ' 門';
        if (filtered.length === 0) {
            container.innerHTML = '<div class="card" style="text-align:center;color:var(--muted-text);padding:32px;">沒有符合條件的課程。調整搜尋文字或收看狀態篩選後再試一次。</div>';
        } else {
            const head = '<div class="udemy-row udemy-head"><span></span><span>課程名稱</span><span>講師名稱</span><span>程式語言／框架／技術</span><span>已觀看堂數 / 總堂數</span><span>總時長</span><span>課程上次更新</span><span>操作</span></div>';
            container.innerHTML = udemyGroupCourses(filtered, mode).map(function (group) {
                let header = '';
                if (group.title) {
                    const stats = udemySummarize(group.courses);
                    header = '<div class="udemy-group-head"><h2 class="udemy-group-title"><i class="fa-solid ' + (UDEMY_GROUP_ICONS[mode] || 'fa-tag') + '" aria-hidden="true"></i>' +
                        udemyEscape(group.title) + ' <small>' + group.courses.length + ' 門</small></h2>' +
                        '<div class="udemy-group-stats"><span>' + stats.watched + ' / ' + stats.total + ' 堂</span>' + udemyProgressHtml(stats.percent, group.title + ' 觀看比重') +
                        '<strong>' + stats.percent + '%</strong></div></div>';
                }
                return '<section class="udemy-group" aria-label="' + udemyEscape(group.title || '全部課程') + '">' + header +
                    '<div class="udemy-table-wrap">' + head + group.courses.map(udemyRowHtml).join('') + '</div></section>';
            }).join('');
        }
        if (typeof reconcileSelectionWithVisibleItems === 'function') reconcileSelectionWithVisibleItems();
    }

    /** 多值分類時同一門課會出現在多個群組，勾選要同步到每一份。 */
    function udemyToggleSelect(checkbox) {
        document.querySelectorAll('.item-checkbox[data-id="' + checkbox.dataset.id + '"]').forEach(function (cb) {
            cb.checked = checkbox.checked;
        });
        toggleSelectItem(checkbox);
    }

    // ---- 表單 ----
    function udemyFormEls() {
        return {
            form: document.getElementById('udemyForm'),
            id: document.getElementById('udemyId'),
            title: document.getElementById('udemyFormTitle'),
            save: document.getElementById('udemySaveBtn'),
            name: document.getElementById('udemyName'),
            instructor: document.getElementById('udemyInstructor'),
            language: document.getElementById('udemyLanguage'),
            framework: document.getElementById('udemyFramework'),
            technology: document.getElementById('udemyTechnology'),
            watched: document.getElementById('udemyWatched'),
            total: document.getElementById('udemyTotal'),
            hours: document.getElementById('udemyHours'),
            updatedAt: document.getElementById('udemyUpdatedAt'),
            completed: document.getElementById('udemyCompleted')
        };
    }

    function udemyFillForm(course, mode) {
        const els = udemyFormEls();
        const editing = mode === 'edit';
        els.form.style.display = '';
        els.id.value = editing && course ? course.id : '';
        els.title.textContent = editing ? '編輯課程' : (mode === 'copy' ? '新增課程（複製）' : '新增課程');
        els.save.textContent = editing ? '儲存變更' : '新增課程';
        els.name.value = course ? (mode === 'copy' ? (course.name || '未命名') + ' (複製)' : course.name) : '';
        els.instructor.value = course ? course.instructor : '';
        els.language.value = course ? course.language : '';
        els.framework.value = course ? course.framework : '';
        els.technology.value = course ? course.technology : '';
        els.watched.value = course ? (Number(course.watchedLectures) || 0) : 0;
        els.total.value = course ? (Number(course.totalLectures) || 0) : 0;
        els.hours.value = course ? (Number(course.totalHours) || 0) : 0;
        els.updatedAt.value = course ? (course.courseUpdatedAt || '') : '';
        els.completed.checked = course ? !!course.completed : false;
        udemyRefreshSuggestions();
        udemyUpdateFormPercent();
        els.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        els.name.focus();
    }

    function openUdemyForm(id) {
        const course = id ? udemyFindCourse(id) : null;
        udemyFillForm(course, course ? 'edit' : 'new');
    }

    function copyUdemyCourse(id) {
        const course = udemyFindCourse(id);
        if (course) udemyFillForm(course, 'copy');
    }

    function closeUdemyForm() {
        document.getElementById('udemyForm').style.display = 'none';
        document.getElementById('udemyId').value = '';
    }

    function udemyFormIntValue(input) {
        return Math.max(0, Math.floor(Number(input.value) || 0));
    }

    function udemyUpdateFormPercent() {
        const els = udemyFormEls();
        const percent = udemyWatchedPercent({
            watchedLectures: udemyFormIntValue(els.watched),
            totalLectures: udemyFormIntValue(els.total),
            completed: els.completed.checked
        });
        document.getElementById('udemyFormPercent').textContent = percent + '%';
        document.getElementById('udemyFormProgress').setAttribute('aria-valuenow', String(percent));
        const fill = document.getElementById('udemyFormProgressFill');
        fill.style.width = percent + '%';
        fill.classList.toggle('is-done', percent >= 100);
    }

    /** 堂數改動時：看到最後一堂就自動勾選「已完整收看」，往回改則取消。 */
    function udemyOnLecturesInput() {
        const els = udemyFormEls();
        const total = udemyFormIntValue(els.total);
        const watched = udemyFormIntValue(els.watched);
        els.watched.max = total > 0 ? String(total) : '';
        if (total > 0) els.completed.checked = watched >= total;
        udemyUpdateFormPercent();
    }

    /** 勾選已完整收看時，已觀看堂數補滿到總堂數。 */
    function udemyOnCompletedChange() {
        const els = udemyFormEls();
        const total = udemyFormIntValue(els.total);
        if (els.completed.checked && total > 0) els.watched.value = total;
        udemyUpdateFormPercent();
    }

    function udemyFillDatalist(listId, options, prefix) {
        const list = document.getElementById(listId);
        if (!list) return;
        list.innerHTML = options.map(function (option) {
            return '<option value="' + udemyEscape(prefix + option) + '"></option>';
        }).join('');
    }

    function udemyCollect(field) {
        const values = [];
        UDEMY_COURSES.forEach(function (course) {
            udemyGroupValues(course, field).forEach(function (value) {
                if (values.indexOf(value) === -1) values.push(value);
            });
        });
        return values.sort(udemyCompareText);
    }

    /** datalist 只能補整格；多值時以最後一個分隔符號後的片段比對建議。 */
    function udemyRefreshTagSuggestions(input) {
        const field = input.dataset.tagField;
        const value = input.value || '';
        const prefix = /[,、，]/.test(value) ? value.replace(/[^,、，]*$/, '') : '';
        const used = udemySplitTags(value);
        const options = udemyCollect(field).filter(function (option) { return used.indexOf(option) === -1; });
        udemyFillDatalist(input.getAttribute('list'), options, prefix);
    }

    function udemyRefreshSuggestions() {
        udemyFillDatalist('udemyInstructorList', udemyCollect('instructor'), '');
        document.querySelectorAll('.udemy-tag-input').forEach(udemyRefreshTagSuggestions);
    }

    function saveUdemyCourse(event) {
        event.preventDefault();
        const els = udemyFormEls();
        const payload = {
            name: els.name.value.trim(),
            instructor: els.instructor.value.trim(),
            language: els.language.value.trim(),
            framework: els.framework.value.trim(),
            technology: els.technology.value.trim(),
            watchedLectures: udemyFormIntValue(els.watched),
            totalLectures: udemyFormIntValue(els.total),
            totalHours: Math.max(0, Number(els.hours.value) || 0),
            courseUpdatedAt: els.updatedAt.value || '',
            completed: els.completed.checked
        };
        if (!payload.name) {
            alert('請填寫課程名稱');
            return false;
        }
        if (payload.totalLectures > 0 && payload.watchedLectures > payload.totalLectures) {
            alert('已觀看堂數不能超過課程總堂數');
            return false;
        }
        const id = els.id.value;
        const url = id
            ? 'api.php?action=update&table=' + encodeURIComponent(TABLE) + '&id=' + encodeURIComponent(id)
            : 'api.php?action=create&table=' + encodeURIComponent(TABLE);
        els.save.disabled = true;
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.success) {
                location.reload();
            } else {
                els.save.disabled = false;
                alert(res.error || '儲存失敗');
            }
        }).catch(function (err) {
            els.save.disabled = false;
            alert('儲存失敗: ' + (err.message || err));
        });
        return false;
    }

    function deleteUdemyCourse(id) {
        const course = udemyFindCourse(id);
        const label = course ? course.name + (course.instructor ? '（' + course.instructor + '）' : '') : '這門課程';
        if (!confirm('確定要刪除「' + label + '」嗎？刪除不能復原。')) return;
        fetch('api.php?action=delete&table=' + encodeURIComponent(TABLE) + '&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) location.reload();
                else alert(res.error || '刪除失敗');
            })
            .catch(function (err) { alert('刪除失敗: ' + (err.message || err)); });
    }

    // ---- CSV 匯出 ----
    function udemyCsvEscape(value) {
        const s = value == null ? '' : String(value);
        if (/[",\n\r]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
        return s;
    }

    function exportUdemyCsv() {
        if (UDEMY_COURSES.length === 0) {
            alert('尚無可匯出的課程');
            return;
        }
        const lines = [UDEMY_CSV_HEADERS.join(',')];
        UDEMY_COURSES.slice().sort(function (a, b) { return udemyCompareText(a.name, b.name); }).forEach(function (course) {
            lines.push([
                course.name, course.instructor, course.language, course.framework, course.technology,
                Number(course.watchedLectures) || 0, Number(course.totalLectures) || 0,
                course.courseUpdatedAt || '', Number(course.totalHours) || 0, course.completed ? 'true' : 'false'
            ].map(udemyCsvEscape).join(','));
        });
        const blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'fengbro-udemy-' + new Date().toISOString().slice(0, 10) + '.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    }

    // ---- CSV 匯入 ----
    let udemyImportData = [];
    let udemyImportErrors = [];

    /** 完整 CSV 解析（支援雙引號內的逗號、換行與 "" 跳脫）。 */
    function udemyParseCsvRows(text) {
        const src = String(text).replace(/^﻿/, '');
        const rows = [];
        let row = [];
        let cell = '';
        let inQuotes = false;
        for (let i = 0; i < src.length; i++) {
            const ch = src[i];
            if (inQuotes) {
                if (ch === '"') {
                    if (src[i + 1] === '"') { cell += '"'; i++; } else inQuotes = false;
                } else cell += ch;
            } else if (ch === '"') {
                inQuotes = true;
            } else if (ch === ',') {
                row.push(cell); cell = '';
            } else if (ch === '\n' || ch === '\r') {
                if (ch === '\r' && src[i + 1] === '\n') i++;
                row.push(cell); cell = '';
                rows.push(row); row = [];
            } else {
                cell += ch;
            }
        }
        if (cell !== '' || row.length > 0) { row.push(cell); rows.push(row); }
        return rows.filter(function (r) { return r.some(function (c) { return c.trim() !== ''; }); });
    }

    function udemyMapHeader(raw) {
        const trimmed = String(raw || '').trim();
        const key = trimmed.toLowerCase().replace(/[\s_]+/g, '');
        return UDEMY_HEADER_ALIASES[key] || UDEMY_HEADER_ALIASES[trimmed] || UDEMY_HEADER_ALIASES[trimmed.replace(/\s+/g, '')] || null;
    }

    /** 日期接受 2025-08-01、2025/8/1，或只有年月的 2025/8（補成當月 1 日）。 */
    function udemyNormalizeCsvDate(value) {
        const s = String(value || '').trim();
        if (!s) return '';
        const m = s.match(/^(\d{4})[-/.](\d{1,2})(?:[-/.](\d{1,2}))?(?:[T ]\d{2}:\d{2}.*)?$/);
        if (!m) return null;
        const iso = m[1] + '-' + m[2].padStart(2, '0') + '-' + (m[3] || '1').padStart(2, '0');
        const d = new Date(iso + 'T00:00:00.000Z');
        if (isNaN(d.getTime()) || d.toISOString().slice(0, 10) !== iso) return null;
        return iso;
    }

    function parseUdemyCsvText(text) {
        const data = [];
        const errors = [];
        const rows = udemyParseCsvRows(text);
        if (rows.length < 2) return { data: data, errors: ['CSV 檔案至少需要表頭和一行資料'] };
        const colIndex = {};
        rows[0].forEach(function (header, i) {
            const mapped = udemyMapHeader(header);
            if (mapped && colIndex[mapped] == null) colIndex[mapped] = i;
        });
        if (colIndex.name == null) return { data: data, errors: ['表頭缺少必要欄位 name（課程名稱）'] };

        for (let i = 1; i < rows.length; i++) {
            const lineNo = i + 1;
            const cell = function (field) {
                const idx = colIndex[field];
                return idx == null ? '' : String(rows[i][idx] == null ? '' : rows[i][idx]).trim();
            };
            const fail = function (msg) { errors.push('第 ' + lineNo + ' 行: ' + msg); };

            const name = cell('name');
            if (!name) { fail('課程名稱不能為空'); continue; }
            if (name.length > 200) { fail('課程名稱最多 200 個字元'); continue; }
            const instructor = cell('instructor');
            if (instructor.length > 200) { fail('講師名稱最多 200 個字元'); continue; }
            const language = cell('language');
            const framework = cell('framework');
            const technology = cell('technology');
            if ([language, framework, technology].some(function (v) { return v.length > 200; })) {
                fail('程式語言／框架／技術名稱各最多 200 個字元');
                continue;
            }

            const watchedRaw = cell('watchedLectures');
            const totalRaw = cell('totalLectures');
            if (!/^\d*$/.test(watchedRaw) || !/^\d*$/.test(totalRaw)) { fail('堂數必須是 0 以上的整數'); continue; }
            const watchedLectures = Number(watchedRaw || 0);
            const totalLectures = Number(totalRaw || 0);
            if (totalLectures > 0 && watchedLectures > totalLectures) { fail('已觀看堂數不能超過課程總堂數'); continue; }

            const hoursRaw = cell('totalHours');
            const totalHours = Number(hoursRaw || 0);
            if (!/^\d*(?:\.\d+)?$/.test(hoursRaw) || !Number.isFinite(totalHours)) { fail('課程總時長必須是 0 以上的數字'); continue; }

            const courseUpdatedAt = udemyNormalizeCsvDate(cell('courseUpdatedAt'));
            if (courseUpdatedAt === null) { fail('課程上次更新時間格式不正確（例如 2025-08-01 或 2025/8）'); continue; }

            const completedRaw = cell('completed').toLowerCase();
            if (UDEMY_TRUE_VALUES.indexOf(completedRaw) === -1 && UDEMY_FALSE_VALUES.indexOf(completedRaw) === -1) {
                fail('課程已經完整收看需為 true／false（或 是／否）');
                continue;
            }

            data.push({
                name: name,
                instructor: instructor,
                language: language,
                framework: framework,
                technology: technology,
                watchedLectures: watchedLectures,
                totalLectures: totalLectures,
                courseUpdatedAt: courseUpdatedAt,
                totalHours: Math.round(totalHours * 100) / 100,
                completed: UDEMY_TRUE_VALUES.indexOf(completedRaw) !== -1
            });
        }
        return { data: data, errors: errors };
    }

    function handleUdemyCsvFile(input) {
        const file = input.files && input.files[0];
        input.value = '';
        if (!file) return;
        if (!/\.csv$/i.test(file.name)) {
            alert('請選擇 CSV 檔案');
            return;
        }
        const reader = new FileReader();
        reader.onload = function () {
            const preview = parseUdemyCsvText(String(reader.result || ''));
            udemyImportData = preview.data;
            udemyImportErrors = preview.errors;
            renderUdemyImportPreview();
        };
        reader.onerror = function () { alert('讀取 CSV 檔案失敗'); };
        reader.readAsText(file, 'UTF-8');
    }

    function udemyExistingIndex() {
        const index = {};
        UDEMY_COURSES.forEach(function (course) { index[udemyNameKey(course.name)] = course.id; });
        return index;
    }

    function renderUdemyImportPreview() {
        document.getElementById('udemyImportOverlay').style.display = 'flex';
        document.getElementById('udemyImportResult').style.display = 'none';
        const errorsEl = document.getElementById('udemyImportErrors');
        if (udemyImportErrors.length > 0) {
            errorsEl.style.display = '';
            errorsEl.innerHTML = '<strong>格式錯誤（不會寫入）</strong><br>' + udemyImportErrors.map(function (e) { return '• ' + udemyEscape(e); }).join('<br>');
        } else {
            errorsEl.style.display = 'none';
        }
        const rowsEl = document.getElementById('udemyImportRows');
        const existing = udemyExistingIndex();
        if (udemyImportData.length === 0) {
            rowsEl.innerHTML = '<p style="color:var(--muted-text);margin:10px 0;">沒有可匯入的資料列。</p>';
        } else {
            rowsEl.innerHTML = '<p style="font-weight:600;margin:10px 0 4px;">將匯入 ' + udemyImportData.length + ' 筆</p>' +
                udemyImportData.map(function (row) {
                    const isUpdate = existing[udemyNameKey(row.name)] != null;
                    return '<div class="quota-import-row"><span class="qname">' + udemyEscape(row.name) +
                        '<span class="qmeta">' + udemyEscape(row.instructor || UDEMY_UNSET_LABEL.instructor) + ' · ' + row.watchedLectures + ' / ' + row.totalLectures + ' 堂 · ' + udemyWatchedPercent(row) + '%</span></span>' +
                        '<span class="' + (isUpdate ? 'qstatus-update' : 'qstatus-new') + '">' + (isUpdate ? '更新' : '新增') + '</span></div>';
                }).join('');
        }
        document.getElementById('udemyImportCancelBtn').style.display = '';
        const confirmBtn = document.getElementById('udemyImportConfirmBtn');
        confirmBtn.style.display = udemyImportErrors.length === 0 && udemyImportData.length > 0 ? '' : 'none';
        confirmBtn.textContent = '確認匯入（' + udemyImportData.length + ' 筆）';
    }

    function closeUdemyImport() {
        document.getElementById('udemyImportOverlay').style.display = 'none';
        udemyImportData = [];
        udemyImportErrors = [];
    }

    async function executeUdemyImport() {
        if (!udemyImportData.length || udemyImportErrors.length) return;
        document.getElementById('udemyImportCancelBtn').style.display = 'none';
        document.getElementById('udemyImportConfirmBtn').style.display = 'none';
        const resultEl = document.getElementById('udemyImportResult');
        resultEl.style.display = '';
        let successCount = 0;
        let failCount = 0;
        const index = udemyExistingIndex();
        for (let i = 0; i < udemyImportData.length; i++) {
            const row = udemyImportData[i];
            resultEl.textContent = '匯入中… ' + (i + 1) + ' / ' + udemyImportData.length;
            const key = udemyNameKey(row.name);
            const existingId = index[key];
            try {
                const url = existingId
                    ? 'api.php?action=update&table=' + encodeURIComponent(TABLE) + '&id=' + encodeURIComponent(existingId)
                    : 'api.php?action=create&table=' + encodeURIComponent(TABLE);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(row)
                }).then(function (r) { return r.json(); });
                if (res.success) {
                    successCount++;
                    if (!existingId && res.id) index[key] = res.id;
                } else {
                    failCount++;
                }
            } catch (e) {
                failCount++;
            }
        }
        resultEl.textContent = '匯入完成：成功 ' + successCount + ' 筆 · 失敗 ' + failCount + ' 筆';
        if (failCount === 0) {
            setTimeout(function () { location.reload(); }, 1200);
        } else {
            const cancelBtn = document.getElementById('udemyImportCancelBtn');
            cancelBtn.textContent = '完成';
            cancelBtn.style.display = '';
            cancelBtn.onclick = function () { location.reload(); };
        }
    }

    // ---- 初始化 ----
    (function initUdemyPage() {
        const els = udemyFormEls();
        els.watched.addEventListener('input', udemyOnLecturesInput);
        els.total.addEventListener('input', udemyOnLecturesInput);
        els.completed.addEventListener('change', udemyOnCompletedChange);
        document.querySelectorAll('.udemy-tag-input').forEach(function (input) {
            input.addEventListener('input', function () { udemyRefreshTagSuggestions(input); });
        });
        udemyLoadPrefs();
        renderUdemy();
    })();
</script>
