<?php
require_once __DIR__ . '/api_common.php';

start_admin_session();
json_response([
    'success' => true,
    'authenticated' => !empty($_SESSION['admin_authenticated']),
    'csrfToken' => admin_csrf_token(),
]);
