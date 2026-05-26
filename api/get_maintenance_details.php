<?php
require_once '../dbconnect.php';
require_once '../auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Invalid maintenance ID']);
    exit;
}

$maintenance_id = intval($_GET['id']);

$sql = "SELECT m.*, 
        p.title as property_title,
        t.first_name, t.last_name, t.email as tenant_email
        FROM maintenance_tasks m
        LEFT JOIN properties p ON m.property_id = p.id
        LEFT JOIN tenants t ON m.tenant_id = t.id
        WHERE m.id = $maintenance_id
        LIMIT 1";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo json_encode(['error' => 'Maintenance task not found']);
    exit;
}

$maintenance = $result->fetch_assoc();
$maintenance['created_at'] = date('M d, Y H:i', strtotime($maintenance['created_at']));

echo json_encode($maintenance);
$conn->close();
?>
