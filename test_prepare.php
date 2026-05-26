<?php
require_once 'dbconnect.php';
$sql = "SELECT id, username, email, password, role, first_name, last_name, status FROM users WHERE (username = ? OR email = ?) AND role IN ('admin', 'teacher', 'staff', 'landlord') LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "prepare failed: " . $conn->error . PHP_EOL;
} else {
    echo "prepare succeeded" . PHP_EOL;
    $stmt->close();
}
?>


