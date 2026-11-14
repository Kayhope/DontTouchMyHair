<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$type = $_POST['feedbackType'] ?? 'Website';
$nameInput = $_POST['name'] ?? '';
$emailInput = $_POST['email'] ?? '';
$messageInput = $_POST['message'] ?? '';
$name = is_string($nameInput) ? trim($nameInput) : '';
$email = is_string($emailInput) ? trim($emailInput) : '';
$message = is_string($messageInput) ? trim($messageInput) : '';
$rating = $_POST['rating'] ?? '';
$token = $_POST['token'] ?? '';

if (!is_string($type) || !is_string($nameInput) || !is_string($emailInput)
    || !is_string($messageInput) || !in_array($type, ['Website', 'Appointment'], true) || mb_strlen($message) < 5
    || mb_strlen($message) > 2000 || mb_strlen($name) > 100
    || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    json_response(['success' => false, 'message' => 'Please check the feedback form and try again.'], 400);
}

if ($type === 'Appointment') {
    $rating = filter_var($rating, FILTER_VALIDATE_INT);
    if (!$rating || $rating < 1 || $rating > 5 || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        json_response(['success' => false, 'message' => 'Use the feedback link from your completed appointment email.'], 400);
    }
} else {
    $rating = null;
}

try {
    $conn = get_db();
    $conn->beginTransaction();
    $appointmentId = null;

    if ($type === 'Appointment') {
        $appointment = $conn->prepare(
            "SELECT id FROM appointments
             WHERE feedback_token = :token AND status = 'Completed'"
        );
        $appointment->execute([':token' => $token]);
        $appointmentId = $appointment->fetchColumn();
        if (!$appointmentId) {
            $conn->rollBack();
            json_response(['success' => false, 'message' => 'This feedback link is invalid or has expired.'], 400);
        }

        $existing = $conn->prepare('SELECT id FROM customer_feedback WHERE appointment_id = :id');
        $existing->execute([':id' => $appointmentId]);
        if ($existing->fetchColumn()) {
            $conn->rollBack();
            json_response(['success' => false, 'message' => 'Feedback has already been submitted for this appointment.'], 409);
        }
    }

    $insert = $conn->prepare(
        'INSERT INTO customer_feedback (appointment_id, name, email, rating, message, feedback_type)
         VALUES (:appointment_id, :name, :email, :rating, :message, :feedback_type)'
    );
    $insert->execute([
        ':appointment_id' => $appointmentId,
        ':name' => $name !== '' ? $name : null,
        ':email' => $email !== '' ? $email : null,
        ':rating' => $rating,
        ':message' => $message,
        ':feedback_type' => $type,
    ]);
    $conn->commit();
} catch (PDOException $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Could not save customer feedback: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'We could not save your feedback. Please try again later.'], 500);
}

$notifyEmail = app_setting('FEEDBACK_NOTIFY_EMAIL', 'kagisohopem@gmail.com');
$ratingText = $rating === null ? '' : "\nRating: $rating/5";
$emailSent = send_customer_email(
    $notifyEmail,
    'New private customer feedback',
    "Feedback type: $type\nName: " . ($name !== '' ? $name : 'Not provided')
    . "\nEmail: " . ($email !== '' ? $email : 'Not provided') . $ratingText . "\n\n$message"
);

json_response([
    'success' => true,
    'message' => $emailSent
        ? 'Thank you. Your feedback was sent privately.'
        : 'Thank you. Your feedback was saved privately, but the email notification could not be sent.',
    'emailSent' => $emailSent,
]);
