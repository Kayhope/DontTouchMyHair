<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

require_admin();

try {
    $stmt = get_db()->query(
        'SELECT id, name, email, rating, message, feedback_type, created_at
         FROM customer_feedback
         ORDER BY created_at DESC'
    );
    json_response(['success' => true, 'feedback' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    error_log('Could not load customer feedback: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Could not load customer feedback. Please try again.'], 500);
}
