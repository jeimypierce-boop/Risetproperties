<?php
header('Content-Type: application/json');
require_once '../dbconnect.php';
require_once '../auth_check.php';

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Invalid request');
    $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
    if (!$image_id) throw new Exception('Image ID required');

    // verify ownership
    $stmt = $conn->prepare("SELECT ui.image_path, u.property_id, p.landlord_id FROM unit_images ui JOIN units u ON ui.unit_id = u.id JOIN properties p ON u.property_id = p.id WHERE ui.id = ?");
    $stmt->bind_param('i', $image_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new Exception('Image not found');
    $row = $res->fetch_assoc();
    $stmt->close();

    $landlord_id = get_landlord_id();
    if ($landlord_id && $row['landlord_id'] != $landlord_id) throw new Exception('Permission denied');

    // delete file
    $path = __DIR__ . '/../' . $row['image_path'];
    if (file_exists($path)) @unlink($path);

    $del = $conn->prepare("DELETE FROM unit_images WHERE id = ?");
    $del->bind_param('i', $image_id);
    if (!$del->execute()) throw new Exception('Error deleting image');
    $del->close();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
if (isset($conn)) $conn->close();

?>
