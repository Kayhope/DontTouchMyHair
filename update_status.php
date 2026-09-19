<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$status = $data['status'] ?? null;

$allowedStatuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];

if (!$id || !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid id/status.']);
    exit;
}

try {
    $conn = get_db();
    $stmt = $conn->prepare('UPDATE appointments SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update status: ' . $e->getMessage()]);
}
