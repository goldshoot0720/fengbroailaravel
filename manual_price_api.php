<?php
/**
 * 手動價格紀錄伺服器 API — 對齊 Appwrite /api/manualprice。
 * 實作在 includes/fengbro_manual_price_action.php，頁面改經 Laravel 的 index.php?fengbro_manual=1。
 */
require_once 'includes/functions.php';
require_once 'includes/fengbro_manual_price_action.php';

$rawInput = file_get_contents('php://input');
$decoded = $rawInput ? json_decode($rawInput, true) : null;
$input = is_array($decoded) ? $decoded : (is_array($_POST) ? $_POST : []);

$result = fengbroPerformManualPriceAction((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), $_GET, $input);
jsonResponse($result['body'], $result['status']);
