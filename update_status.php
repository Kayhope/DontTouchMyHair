<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

require_admin();
$data = read_json_request();
$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$requestedStatus = $data['status'] ?? '';
$allowedStatuses = ['Confirmed', 'Declined', 'Cancelled', 'Completed'];
if (!$id || !in_array($requestedStatus, $allowedStatuses, true)) {
    json_response(['success' => false, 'message' => 'Choose a valid booking and status.'], 400);
}

$conn = null;
try {
    $conn = get_db();
    $conn->beginTransaction();
    $lookup = $conn->prepare(
        'SELECT a.id, a.customer_name, a.email, a.service, a.appointment_date,
                a.appointment_time, a.status, a.slot_id, a.feedback_token,
                s.end_time, s.status AS slot_status
         FROM appointments a
         LEFT JOIN availability_slots s ON s.id = a.slot_id
         WHERE a.id = :id'
    );
    $lookup->execute([':id' => $id]);
    $booking = $lookup->fetch();
    if (!$booking) {
        $conn->rollBack();
        json_response(['success' => false, 'message' => 'Booking not found.'], 404);
    }

    $newStatus = $requestedStatus === 'Declined' ? 'Cancelled' : $requestedStatus;
    $currentStatus = $booking['status'];
    $validTransition = match ($requestedStatus) {
        'Confirmed', 'Declined' => $currentStatus === 'Pending',
        'Completed' => $currentStatus === 'Confirmed',
        'Cancelled' => in_array($currentStatus, ['Pending', 'Confirmed'], true),
        default => false,
    };
    if (!$validTransition) {
        $conn->rollBack();
        json_response(['success' => false, 'message' => 'That booking has already changed. Refresh the page and try again.'], 409);
    }

    if ($requestedStatus === 'Confirmed' && $booking['slot_status'] !== 'Held') {
        $conn->rollBack();
        json_response(['success' => false, 'message' => 'The requested time is no longer being held.'], 409);
    }

    $update = $conn->prepare(
        'UPDATE appointments SET status = :new_status WHERE id = :id AND status = :current_status'
    );
    $update->execute([
        ':new_status' => $newStatus,
        ':id' => $id,
        ':current_status' => $currentStatus,
    ]);
    if ($update->rowCount() !== 1) {
        $conn->rollBack();
        json_response(['success' => false, 'message' => 'That booking has already changed. Refresh the page and try again.'], 409);
    }

    if ($booking['slot_id'] !== null) {
        if ($requestedStatus === 'Confirmed') {
            $slotUpdate = $conn->prepare(
                "UPDATE availability_slots SET status = 'Booked'
                 WHERE id = :id AND status = 'Held'"
            );
            $slotUpdate->execute([':id' => $booking['slot_id']]);
            if ($slotUpdate->rowCount() !== 1) {
                throw new RuntimeException('The slot could not be confirmed because it is no longer held.');
            }
        } elseif (in_array($requestedStatus, ['Declined', 'Cancelled'], true)) {
            $slotUpdate = $conn->prepare(
                "UPDATE availability_slots SET status = 'Available'
                 WHERE id = :id AND status IN ('Held', 'Booked')"
            );
            $slotUpdate->execute([':id' => $booking['slot_id']]);
        }
    }
    $conn->commit();
} catch (RuntimeException $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    json_response(['success' => false, 'message' => $e->getMessage()], 409);
} catch (PDOException $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Could not update appointment status: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Could not update this booking. Please try again.'], 500);
}

$when = $booking['appointment_date'] . ' at ' . substr($booking['appointment_time'], 0, 5);
if ($booking['end_time']) {
    $when .= ' to ' . substr($booking['end_time'], 0, 5);
}
$feedbackLinkSent = true;

if ($requestedStatus === 'Confirmed') {
    $subject = 'Your appointment is approved';
    $message = "Hi {$booking['customer_name']},\n\nYour {$booking['service']} appointment on $when has been approved.\n\nDon't Touch My Hair";
} elseif ($requestedStatus === 'Declined') {
    $subject = 'Appointment request update';
    $message = "Hi {$booking['customer_name']},\n\nUnfortunately, we could not approve your {$booking['service']} appointment request for $when. That time is now available again. Please choose another time or contact us.\n\nDon't Touch My Hair";
} elseif ($requestedStatus === 'Completed') {
    $subject = 'Thank you for visiting';
    $siteUrl = rtrim(app_setting('PUBLIC_SITE_URL'), '/');
    $feedbackToken = $booking['feedback_token'] ?? '';
    $feedbackLinkSent = $siteUrl !== '' && preg_match('/^[a-f0-9]{64}$/', $feedbackToken);
    $feedbackUrl = $feedbackLinkSent
        ? "\n\nShare private feedback: $siteUrl/feedback.html?token=" . rawurlencode($feedbackToken)
        : '';
    $message = "Hi {$booking['customer_name']},\n\nThank you for visiting us. We hope you love your look.$feedbackUrl\n\nDon't Touch My Hair";
} else {
    $subject = 'Appointment update';
    $message = "Hi {$booking['customer_name']},\n\nYour appointment for $when has been cancelled. You may choose another available time.\n\nDon't Touch My Hair";
}

$emailSent = send_customer_email($booking['email'], $subject, $message);
if (!$emailSent) {
    $responseMessage = 'Booking updated, but email could not be sent. Please contact the customer directly.';
} elseif ($requestedStatus === 'Completed' && !$feedbackLinkSent) {
    $responseMessage = 'Booking completed and the customer was emailed, but the feedback link was omitted. Configure PUBLIC_SITE_URL on the host.';
} else {
    $responseMessage = 'Booking updated and the customer was emailed.';
}
json_response([
    'success' => true,
    'message' => $responseMessage,
    'emailSent' => $emailSent,
    'feedbackLinkSent' => $feedbackLinkSent,
]);
