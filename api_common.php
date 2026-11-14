<?php
require_once __DIR__ . '/config.php';

function json_response(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

function read_json_request(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function start_admin_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('dtmh_admin');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Strict',
        'path' => '/',
    ]);
    session_start();
}

function admin_csrf_token(): string {
    start_admin_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_admin(): void {
    start_admin_session();
    if (empty($_SESSION['admin_authenticated'])) {
        json_response(['success' => false, 'message' => 'Please sign in first.'], 401);
    }

    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || !hash_equals(admin_csrf_token(), $provided)) {
        json_response(['success' => false, 'message' => 'Your session expired. Refresh and try again.'], 403);
    }
}

function send_customer_email(string $to, string $subject, string $message): bool {
    $from = app_setting('BOOKING_FROM_EMAIL');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $safeSubject = str_replace(["\r", "\n"], '', $subject);
    $headers = [
        'From: Don\'t Touch My Hair <' . $from . '>',
        'Reply-To: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    return mail($to, $safeSubject, $message, implode("\r\n", $headers));
}
