<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $stmt = get_db()->prepare(
        "SELECT id, appointment_date, start_time, end_time
         FROM availability_slots
         WHERE status = 'Available'
           AND (appointment_date > :today OR (appointment_date = :today AND start_time >= :now_time))
         ORDER BY appointment_date, start_time"
    );
    $stmt->execute([':today' => date('Y-m-d'), ':now_time' => date('H:i')]);
    json_response(['success' => true, 'slots' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    error_log('Could not load appointment availability: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Availability is temporarily unavailable. Please try again later.'], 500);
}
