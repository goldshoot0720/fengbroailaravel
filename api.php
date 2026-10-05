<?php
require_once 'includes/functions.php';
require_once 'includes/fengbro_record_action.php';

$rawInput = file_get_contents('php://input');
$decoded = $rawInput ? json_decode($rawInput, true) : null;
$input = is_array($decoded) ? $decoded : (is_array($_POST) ? $_POST : []);

$result = fengbroPerformRecordAction($_GET, $input);
jsonResponse($result['body'], $result['status']);
