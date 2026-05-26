<?php
require_once '../dbconnect.php';
require_once '../auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Invalid message ID']);
    exit;
}

$message_id = intval($_GET['id']);

$sql = "SELECT c.*, 
        CONCAT(u.first_name, ' ', u.last_name) as sender_name,
        u.email as sender_email,
        CASE WHEN COALESCE(c.recipient_type, 'user') = 'tenant' THEN (
            SELECT CONCAT(first_name, ' ', last_name) FROM tenants WHERE id = c.recipient_id
        ) ELSE (
            SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = c.recipient_id
        ) END as recipient_name,
        CASE WHEN COALESCE(c.recipient_type, 'user') = 'tenant' THEN (
            SELECT email FROM tenants WHERE id = c.recipient_id
        ) ELSE (
            SELECT email FROM users WHERE id = c.recipient_id
        ) END as recipient_email
        FROM communications c
        LEFT JOIN users u ON c.sender_id = u.id
        WHERE c.id = $message_id
        LIMIT 1";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo json_encode(['error' => 'Message not found']);
    exit;
}

$message = $result->fetch_assoc();
echo json_encode($message);
$conn->close();
?>
