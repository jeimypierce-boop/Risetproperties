<?php
// delete.php - secure hard-delete endpoint for admin users
require_once 'dbconnect.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: admin only']);
    exit;
}

$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

$map = [
    'property' => 'properties',
    'tenant' => 'tenants',
    'enquiry' => 'enquiries',
    'viewing' => 'viewings',
    'maintenance' => 'maintenance_tasks',
    'user' => 'users',
    'lease' => 'leases',
    'payment' => 'payments'
];

if (!isset($map[$type])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid delete type']);
    exit;
}

$table = $map[$type];

// Prevent deleting the currently logged-in admin
if ($type === 'user' && isset($_SESSION['user_id']) && intval($_SESSION['user_id']) === $id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot delete current user']);
    exit;
}

// Perform delete
$sql = "DELETE FROM `" . $table . "` WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Deleted']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
}
$stmt->close();
$conn->close();

?>
