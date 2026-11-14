<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

require_admin();

try {
    $stmt = get_db()->query(
        'SELECT a.id, a.customer_name, a.email, a.phone, a.service, a.appointment_date,
                a.appointment_time, a.message, a.style_image, a.bring_hairpieces,
                a.status, a.created_at, s.end_time
         FROM appointments a
         LEFT JOIN availability_slots s ON s.id = a.slot_id
         ORDER BY a.created_at DESC'
    );
    json_response(['success' => true, 'bookings' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    error_log('Could not load appointment bookings: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Could not load bookings. Please try again.'], 500);
}
