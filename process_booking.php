<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

function fail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method.', 405);
}

$required = ['name', 'email', 'phone', 'service', 'bringHairpieces', 'date', 'time'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        fail("Missing required field: $field");
    }
}

$name = trim($_POST['name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$service = trim($_POST['service']);
$bringHairpieces = $_POST['bringHairpieces'];
$date = $_POST['date'];
$time = $_POST['time'];
$message = trim($_POST['message'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Please enter a valid email address.');
}

try {
    $conn = get_db();

    // Handle optional style image upload
    $styleImagePath = null;
    if (isset($_FILES['styleImage']) && $_FILES['styleImage']['error'] === UPLOAD_ERR_OK) {
        $uploadsDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0775, true);
        }

        $originalName = basename($_FILES['styleImage']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowedExt, true)) {
            fail('Style image must be a jpg, png, gif, or webp file.');
        }

        // Unique filename so different customers never overwrite each other
        $safeName = uniqid('style_', true) . '.' . $ext;
        $destination = $uploadsDir . $safeName;

        if (!move_uploaded_file($_FILES['styleImage']['tmp_name'], $destination)) {
            fail('There was an error uploading the style image.', 500);
        }

        $styleImagePath = 'uploads/' . $safeName;
    }

    $stmt = $conn->prepare(
        'INSERT INTO appointments
            (customer_name, email, phone, service, appointment_date, appointment_time, message, style_image, bring_hairpieces, status)
         VALUES
            (:customer_name, :email, :phone, :service, :appointment_date, :appointment_time, :message, :style_image, :bring_hairpieces, :status)'
    );

    $stmt->execute([
        ':customer_name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':service' => $service,
        ':appointment_date' => $date,
        ':appointment_time' => $time,
        ':message' => $message,
        ':style_image' => $styleImagePath,
        ':bring_hairpieces' => $bringHairpieces,
        ':status' => 'Pending',
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Your appointment has been successfully booked!',
        'id' => $conn->lastInsertId(),
    ]);
} catch (PDOException $e) {
    fail('Database error: ' . $e->getMessage(), 500);
}
