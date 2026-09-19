<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $conn = get_db();
    $stmt = $conn->query('SELECT id, customer_name, email, phone, service, appointment_date, appointment_time, message, style_image, bring_hairpieces, status, created_at FROM appointments ORDER BY created_at DESC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'bookings' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load bookings: ' . $e->getMessage()]);
}
