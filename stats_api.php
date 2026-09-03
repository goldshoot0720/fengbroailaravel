<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 對齊 fengbroaiappwrite 的 /api/site-visit 與 /api/menu-usage：
//   stats_api.php?action=site_visit         GET  讀取進站人次與連續天數
//   stats_api.php?action=site_visit         POST 記錄一次進站（每 session 一次）
//   stats_api.php?action=menu_usage         GET  讀取選單使用 Top N
//   stats_api.php?action=menu_usage         POST 記錄一次選單點擊（body: moduleId）
//   stats_api.php?action=init_site_tables   POST 建立／補齊 sitevisit + menuusage 表

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getConnection();

function siteVisitEmptyStats(array $extra = []): array
{
    return array_merge([
        'count' => 0,
        'currentStreak' => 0,
        'lastVisitAt' => null,
        'lastVisitDate' => null,
        'exists' => false,
    ], $extra);
}

function siteVisitStatsFromRow(array $row): array
{
    return [
        'count' => (int) ($row['count'] ?? 0),
        'currentStreak' => fengbroDisplaySiteVisitStreak($row),
        'lastVisitAt' => ($row['lastVisitAt'] ?? null) ? date('c', strtotime($row['lastVisitAt'])) : null,
        'lastVisitDate' => $row['lastVisitDate'] ?? null,
        'exists' => true,
    ];
}

switch ($action) {
    case 'site_visit':
        if ($method === 'POST') {
            try {
                $result = fengbroRecordSiteVisit($pdo);
                jsonResponse($result);
            } catch (Throwable $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }
        $row = fengbroGetSiteVisitRow($pdo);
        jsonResponse($row ? siteVisitStatsFromRow($row) : siteVisitEmptyStats());
        break;

    case 'menu_usage':
        if ($method === 'POST') {
            $rawInput = file_get_contents('php://input');
            $input = $rawInput ? json_decode($rawInput, true) : null;
            if (!is_array($input)) {
                $input = $_POST;
            }
            $moduleId = trim((string) ($input['moduleId'] ?? ''));
            if ($moduleId === '') {
                jsonResponse(['success' => false, 'error' => '缺少 moduleId'], 400);
            }
            try {
                fengbroRecordMenuUsage($pdo, $moduleId);
                jsonResponse(['success' => true]);
            } catch (Throwable $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }
        $items = array_map(function (array $row) {
            return [
                'moduleId' => $row['moduleId'],
                'count' => (int) ($row['count'] ?? 0),
                'lastUsedAt' => ($row['lastUsedAt'] ?? null) ? date('c', strtotime($row['lastUsedAt'])) : null,
            ];
        }, fengbroGetMenuUsageItems($pdo, (int) ($_GET['limit'] ?? 100)));
        jsonResponse(['items' => $items, 'exists' => true]);
        break;

    case 'init_site_tables':
        if ($method !== 'POST') {
            jsonResponse(['error' => 'Method Not Allowed'], 405);
        }
        try {
            fengbroEnsureSiteVisitTable($pdo);
            fengbroEnsureMenuUsageTable($pdo);
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['error' => '無效的操作'], 400);
}
