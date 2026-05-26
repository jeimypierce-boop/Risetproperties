<?php
require_once '../dbconnect.php';
require_once '../auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['q']) || empty($_GET['q'])) {
    echo json_encode([]);
    exit;
}

$query = $conn->real_escape_string($_GET['q']);
$limit = intval($_GET['limit'] ?? 10);

// Search in users and tenants tables
$sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name, email, 'user' as type
        FROM users
        WHERE (CONCAT(first_name, ' ', last_name) LIKE '%$query%' 
               OR email LIKE '%$query%'
               OR phone LIKE '%$query%')
        AND id != " . intval($_SESSION['user_id']) . "
        UNION ALL
        SELECT id, CONCAT(first_name, ' ', last_name) as name, email, 'tenant' as type
        FROM tenants
        WHERE (CONCAT(first_name, ' ', last_name) LIKE '%$query%' 
               OR email LIKE '%$query%'
               OR phone LIKE '%$query%')
        LIMIT $limit";

$result = $conn->query($sql);
$recipients = [];

while ($row = $result->fetch_assoc()) {
    $recipients[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'type' => $row['type']
    ];
}

echo json_encode($recipients);
$conn->close();
?>
