<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

require_admin();
$_SESSION = [];
session_destroy();
json_response(['success' => true]);
