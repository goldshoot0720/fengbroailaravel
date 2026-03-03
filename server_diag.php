<?php
// Quick server diagnostic - check ZipArchive, temp dir, PHP config
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'php_version' => PHP_VERSION,
    'ziparchive_available' => class_exists('ZipArchive'),
    'sys_temp_dir' => sys_get_temp_dir(),
    'temp_dir_writable' => is_writable(sys_get_temp_dir()),
    'temp_dir_free_space_mb' => round(disk_free_space(sys_get_temp_dir()) / 1024 / 1024, 1),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'extensions' => array_values(array_filter(get_loaded_extensions(), fn($e) => in_array($e, ['zip', 'zlib', 'curl', 'mbstring', 'pdo_mysql', 'gd']))),
    'disable_functions' => ini_get('disable_functions'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
