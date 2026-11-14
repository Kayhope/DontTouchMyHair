<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

start_admin_session();
if (empty($_SESSION['admin_authenticated'])) {
    http_response_code(401);
    exit;
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit;
}

try {
    $stmt = get_db()->prepare('SELECT style_image FROM appointments WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $storedPath = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Could not load appointment image: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

if (!is_string($storedPath)
    || !preg_match('/^uploads\/(?:[a-f0-9]{32}|style_[A-Za-z0-9._-]+)\.(?:jpg|jpeg|png|gif|webp)$/', $storedPath)) {
    http_response_code(404);
    exit;
}

$uploadsDir = realpath(__DIR__ . '/uploads');
$imagePath = realpath(__DIR__ . '/' . $storedPath);
if (!$uploadsDir || !$imagePath || !str_starts_with($imagePath, $uploadsDir . DIRECTORY_SEPARATOR) || !is_file($imagePath)) {
    http_response_code(404);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($imagePath);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($imagePath));
readfile($imagePath);
