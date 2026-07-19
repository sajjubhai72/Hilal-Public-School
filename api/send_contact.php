<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$name    = sanitize($conn, $_POST['sender_name'] ?? '');
$email   = sanitize($conn, $_POST['sender_email'] ?? '');
$phone   = sanitize($conn, $_POST['sender_phone'] ?? '');
$subject = sanitize($conn, $_POST['subject'] ?? '');
$message = sanitize($conn, $_POST['message'] ?? '');

if (!$name || !$email || !$subject || !$message) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit();
}

if (strlen($message) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Message is too long.']);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO contact_messages (sender_name, sender_email, sender_phone, subject, message)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again.']);
}
$stmt->close();
