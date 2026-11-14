<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$required = ['name', 'email', 'phone', 'service', 'bringHairpieces', 'slotId'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || !is_string($_POST[$field]) || trim($_POST[$field]) === '') {
        json_response(['success' => false, 'message' => 'Please complete all required fields.'], 400);
    }
}

$name = trim($_POST['name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$service = trim($_POST['service']);
$bringHairpieces = (string)$_POST['bringHairpieces'];
$slotId = filter_var($_POST['slotId'], FILTER_VALIDATE_INT);
$messageInput = $_POST['message'] ?? '';
$message = is_string($messageInput) ? trim($messageInput) : '';
$allowedServices = ['Braiding', 'Hair Treatment', 'Hair Oil Consultation'];

if (!is_string($_POST['slotId']) || !is_string($_POST['bringHairpieces'])
    || !is_string($messageInput) || mb_strlen($name) > 100 || mb_strlen($email) > 100
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || mb_strlen($phone) > 30 || !in_array($service, $allowedServices, true)
    || !in_array($bringHairpieces, ['Yes', 'No'], true) || !$slotId
    || mb_strlen($message) > 250) {
    json_response(['success' => false, 'message' => 'Check your details and choose an available time slot.'], 400);
}

$uploadPath = null;
$storedImagePath = null;
$conn = null;

try {
    $conn = get_db();
    $conn->beginTransaction();

    $slotQuery = $conn->prepare(
        "SELECT id, appointment_date, start_time, end_time
         FROM availability_slots
        WHERE id = :id AND status = 'Available'
          AND (appointment_date > :today OR (appointment_date = :today AND start_time >= :now_time))"
    );
    $slotQuery->execute([
        ':id' => $slotId,
        ':today' => date('Y-m-d'),
        ':now_time' => date('H:i'),
    ]);
    $slot = $slotQuery->fetch();
    if (!$slot) {
        throw new RuntimeException('That time was just taken or is no longer available. Please choose another slot.', 409);
    }

    $hold = $conn->prepare(
        "UPDATE availability_slots SET status = 'Held'
         WHERE id = :id AND status = 'Available'"
    );
    $hold->execute([':id' => $slotId]);
    if ($hold->rowCount() !== 1) {
        throw new RuntimeException('That time was just taken. Please choose another slot.', 409);
    }

    if (isset($_FILES['styleImage']) && $_FILES['styleImage']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['styleImage']['error'] !== UPLOAD_ERR_OK || $_FILES['styleImage']['size'] > 5_000_000) {
            throw new RuntimeException('The style image could not be uploaded. Choose an image under 5 MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['styleImage']['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Style images must be JPG, PNG, GIF, or WebP files.');
        }

        $uploadsDir = __DIR__ . '/uploads';
        if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
            throw new RuntimeException('Style image uploads are not available right now.', 500);
        }
        $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $uploadPath = $uploadsDir . '/' . $fileName;
        if (!move_uploaded_file($_FILES['styleImage']['tmp_name'], $uploadPath)) {
            throw new RuntimeException('The style image could not be saved.', 500);
        }
        $storedImagePath = 'uploads/' . $fileName;
    }

    $feedbackToken = bin2hex(random_bytes(32));
    $insert = $conn->prepare(
        'INSERT INTO appointments
            (customer_name, email, phone, service, appointment_date, appointment_time, message,
             style_image, bring_hairpieces, status, slot_id, feedback_token)
         VALUES
            (:customer_name, :email, :phone, :service, :appointment_date, :appointment_time, :message,
             :style_image, :bring_hairpieces, :status, :slot_id, :feedback_token)'
    );
    $insert->execute([
        ':customer_name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':service' => $service,
        ':appointment_date' => $slot['appointment_date'],
        ':appointment_time' => $slot['start_time'],
        ':message' => $message,
        ':style_image' => $storedImagePath,
        ':bring_hairpieces' => $bringHairpieces,
        ':status' => 'Pending',
        ':slot_id' => $slotId,
        ':feedback_token' => $feedbackToken,
    ]);

    $conn->commit();
    $when = $slot['appointment_date'] . ' from ' . substr($slot['start_time'], 0, 5)
        . ' to ' . substr($slot['end_time'], 0, 5);
    $mailSent = send_customer_email(
        $email,
        'Appointment request received',
        "Hi $name,\n\nWe received your request for $service on $when. It is pending approval. "
        . "We will email you when it is approved or declined.\n\nDon't Touch My Hair"
    );
    $ownerNotified = send_customer_email(
        app_setting('BOOKING_NOTIFY_EMAIL', 'kagisohopem@gmail.com'),
        'New appointment request',
        "A new appointment request needs your review.\n\n"
        . "Name: $name\nEmail: $email\nPhone: $phone\nService: $service\n"
        . "Date and time: $when\nBring hairpieces: $bringHairpieces\n"
        . "Message: " . ($message !== '' ? $message : 'None')
    );
    $notificationsSent = $mailSent && $ownerNotified;

    json_response([
        'success' => true,
        'message' => $notificationsSent
            ? 'Your request is pending approval. We emailed you a receipt.'
            : 'Your request is saved and pending approval, but an email notification could not be sent. Please contact us if you do not hear back.',
        'emailSent' => $notificationsSent,
    ]);
} catch (RuntimeException $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($uploadPath && is_file($uploadPath)) {
        unlink($uploadPath);
    }
    $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
    if ($status >= 500) {
        error_log('Could not process appointment request: ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'We could not save your request. Please try again later.'], 500);
    }
    json_response(['success' => false, 'message' => $e->getMessage()], $status);
} catch (PDOException $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($uploadPath && is_file($uploadPath)) {
        unlink($uploadPath);
    }
    error_log('Could not process appointment request: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'We could not save your request. Please try again later.'], 500);
}
