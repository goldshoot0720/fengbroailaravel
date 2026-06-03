<?php
require_once 'includes/functions.php';

jsonResponse([
    'success' => true,
    'health' => databaseHealth(),
]);
