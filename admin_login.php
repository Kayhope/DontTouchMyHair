<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$passwordHash = app_setting('ADMIN_PASSWORD_HASH');
if ($passwordHash === '') {
    json_response(['success' => false, 'message' => 'Admin sign-in has not been configured on the host.'], 503);
}

$data = read_json_request();
$password = $data['password'] ?? '';
if (!is_string($password) || !password_verify($password, $passwordHash)) {
    usleep(300000);
    json_response(['success' => false, 'message' => 'Incorrect password.'], 401);
}

start_admin_session();
session_regenerate_id(true);
$_SESSION['admin_authenticated'] = true;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

json_response([
    'success' => true,
    'csrfToken' => $_SESSION['csrf_token'],
]);
