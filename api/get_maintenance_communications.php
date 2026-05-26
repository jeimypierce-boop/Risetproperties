<?php
require_once '../dbconnect.php';
require_once '../auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode([]);
    exit;
}

$maintenance_id = intval($_GET['id']);

$sql = "SELECT mc.*, 
        CONCAT(u.first_name, ' ', u.last_name) as sender_name,
        mc.created_at
        FROM maintenance_communications mc
        LEFT JOIN users u ON mc.sender_id = u.id
        WHERE mc.maintenance_id = $maintenance_id
        ORDER BY mc.created_at ASC";

$result = $conn->query($sql);
$communications = [];

while ($row = $result->fetch_assoc()) {
    $communications[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'sender_type' => $row['sender_type'],
        'sender_name' => $row['sender_name'] ?? 'Unknown',
        'message' => htmlspecialchars($row['message']),
        'created_at' => date('M d, Y H:i', strtotime($row['created_at']))
    ];
}

echo json_encode($communications);
$conn->close();
?>
