<?php
header('Content-Type: application/json');
require_once '../dbconnect.php';
require_once '../auth_check.php';

try {
    require_login();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $unit_id = isset($_POST['unit_id']) ? intval($_POST['unit_id']) : 0;

    if (!$unit_id) {
        throw new Exception('Unit ID is required');
    }

    // Get unit and property info to verify ownership
    $verify_stmt = $conn->prepare("
        SELECT u.id, p.landlord_id 
        FROM units u 
        JOIN properties p ON u.property_id = p.id 
        WHERE u.id = ?
    ");
    $verify_stmt->bind_param('i', $unit_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        throw new Exception('Unit not found');
    }

    $unit_info = $verify_result->fetch_assoc();
    $verify_stmt->close();

    // Check if user has access to this unit
    $landlord_id = get_landlord_id();
    if ($landlord_id && $unit_info['landlord_id'] != $landlord_id) {
        throw new Exception('You do not have permission to delete this unit');
    }

    // Check if unit has active leases
    $lease_check_stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM leases WHERE unit_id = ? AND status = 'Active'");
    $lease_check_stmt->bind_param('i', $unit_id);
    $lease_check_stmt->execute();
    $lease_result = $lease_check_stmt->get_result()->fetch_assoc();
    $lease_check_stmt->close();

    if ($lease_result['active_count'] > 0) {
        throw new Exception('Cannot delete unit with active leases. Please end or terminate all active leases first.');
    }

    // Delete unit images first
    $delete_images_stmt = $conn->prepare("DELETE FROM unit_images WHERE unit_id = ?");
    $delete_images_stmt->bind_param('i', $unit_id);
    $delete_images_stmt->execute();
    $delete_images_stmt->close();

    // Delete the unit
    $delete_stmt = $conn->prepare("DELETE FROM units WHERE id = ?");
    $delete_stmt->bind_param('i', $unit_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception('Error deleting unit: ' . $delete_stmt->error);
    }

    $delete_stmt->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Unit deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
