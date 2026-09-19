<?php
require_once __DIR__ . '/config.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<p>Invalid form submission.</p>";
    exit;
}

// Capture the form data
$name = htmlspecialchars(trim($_POST['name'] ?? ''));
$email = htmlspecialchars(trim($_POST['email'] ?? ''));
$phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
$quantity = htmlspecialchars(trim($_POST['quantity'] ?? ''));
$collection_method = htmlspecialchars(trim($_POST['collection-method'] ?? ''));
$address = isset($_POST['address']) ? htmlspecialchars(trim($_POST['address'])) : 'N/A';
$paxi_location = isset($_POST['paxi-location']) ? htmlspecialchars(trim($_POST['paxi-location'])) : 'N/A';

header('Content-Type: text/html; charset=UTF-8');

// Validate required fields
if (empty($name) || empty($email) || empty($phone) || empty($quantity) || empty($collection_method)) {
    die("<p>Please fill in all required fields. <a href='buy.html'>Go back</a></p>");
}

// Always save the order to the database first, so nothing is ever lost
// even if the email below fails to send (which is common on localhost —
// see the note in README.md about mail() needing a configured mail server).
$savedToDb = false;
try {
    $conn = get_db();
    $stmt = $conn->prepare(
        'INSERT INTO orders (customer_name, email, phone, quantity, collection_method, address, paxi_location, email_sent)
         VALUES (:name, :email, :phone, :quantity, :collection_method, :address, :paxi_location, 0)'
    );
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':quantity' => $quantity,
        ':collection_method' => $collection_method,
        ':address' => $address,
        ':paxi_location' => $paxi_location,
    ]);
    $savedToDb = true;
    $orderId = $conn->lastInsertId();
} catch (PDOException $e) {
    // Fall back to a text file if the database write somehow fails,
    // so the order is still captured somewhere.
    $orderDetails = "Name: $name\nEmail: $email\nPhone: $phone\nQuantity: $quantity\nCollection Method: $collection_method\nAddress: $address\nPaxi Location: $paxi_location\n\n";
    file_put_contents(__DIR__ . '/orders.txt', $orderDetails, FILE_APPEND);
}

// Prepare the email content
$subject = "New Order from " . $name;
$message = "
    <h3>Order Details:</h3>
    <p><strong>Name:</strong> $name</p>
    <p><strong>Email:</strong> $email</p>
    <p><strong>Phone Number:</strong> $phone</p>
    <p><strong>Quantity:</strong> $quantity</p>
    <p><strong>Collection Method:</strong> $collection_method</p>
    <p><strong>Delivery Address (if applicable):</strong> $address</p>
    <p><strong>Paxi Location (if applicable):</strong> $paxi_location</p>
";

// Set the recipient email (this is where the order details will be sent)
$to = "orders@donttouchmyhair.com";
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
$headers .= "From: $email" . "\r\n";

// Attempt to send the email. On localhost / most dev environments this
// will simply fail silently (no mail server configured) — that's expected.
// The order is already safely stored above regardless of what mail() does.
$mailSent = @mail($to, $subject, $message, $headers);

if ($savedToDb && isset($orderId)) {
    try {
        $conn->prepare('UPDATE orders SET email_sent = :sent WHERE id = :id')
             ->execute([':sent' => $mailSent ? 1 : 0, ':id' => $orderId]);
    } catch (PDOException $e) {
        // Non-critical — the order itself is already saved.
    }
}

if ($mailSent) {
    echo "<p>Thank you for your order, $name! We will contact you shortly.</p>";
} else {
    echo "<p>Thank you for your order, $name! Your order has been received and saved. "
       . "(We couldn't send the automatic email confirmation — see README.md for how to enable that once the site is live — but your order is safely on file.)</p>";
}
