<?php
require_once 'dbconnect.php';
require_once 'integrations.php';

// Read raw body (M-PESA typically posts JSON)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST;
}

log_integration('Received M-PESA callback: ' . ($raw ?: json_encode($data)));

$checkoutRequestID = $data['Body']['stkCallback']['CheckoutRequestID'] ?? $data['CheckoutRequestID'] ?? $data['checkout_request_id'] ?? $data['checkoutRequestID'] ?? '';
$resultCode = $data['Body']['stkCallback']['ResultCode'] ?? $data['ResultCode'] ?? null;
$resultDesc = $data['Body']['stkCallback']['ResultDesc'] ?? $data['ResultDesc'] ?? '';
$metadata = $data['Body']['stkCallback']['CallbackMetadata']['Item'] ?? $data['CallbackMetadata']['Item'] ?? [];
$parsed = parse_mpesa_callback_metadata($metadata);

$amount = floatval($parsed['Amount'] ?? $data['Amount'] ?? 0);
$phone = format_mpesa_phone($parsed['PhoneNumber'] ?? $parsed['Phone'] ?? $data['PhoneNumber'] ?? '');
$receipt = $parsed['MpesaReceiptNumber'] ?? $parsed['ReceiptNumber'] ?? $data['MpesaReceiptNumber'] ?? $data['ReceiptNumber'] ?? '';
$accountReference = $parsed['AccountReference'] ?? $data['AccountReference'] ?? $parsed['Account'] ?? '';
$status = ($resultCode === 0 || $resultCode === '0') ? 'paid' : 'failed';
$transaction_id = $receipt ?: ($checkoutRequestID ?: ($data['transaction_id'] ?? $data['TransactionID'] ?? ''));
$reference = $checkoutRequestID ?: $accountReference ?: ($parsed['Reference'] ?? '');
$payment_method = 'mpesa';

$tenant_id = null;
$lease_id = null;
$property_id = null;

if ($phone) {
    $tenant = find_tenant_by_phone($phone);
    if ($tenant) {
        $tenant_id = intval($tenant['id']);
        $lease = find_active_lease_for_tenant($tenant_id);
        if ($lease) {
            $lease_id = intval($lease['id']);
            $property_id = intval($lease['property_id']);
        }
    }
}

$recordFound = false;
if (!empty($checkoutRequestID)) {
    $lookup = $checkoutRequestID;
    $stmt = $conn->prepare('SELECT id, status FROM rent_payments WHERE transaction_id = ? OR reference = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ss', $lookup, $lookup);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $recordFound = true;
            $paymentId = intval($row['id']);
        }
        $stmt->close();
    }
}

if ($recordFound) {
    $updateStmt = $conn->prepare('UPDATE rent_payments SET tenant_id = ?, lease_id = ?, property_id = ?, amount_paid = ?, payment_date = NOW(), payment_method = ?, transaction_id = ?, reference = ?, status = ?, updated_at = NOW() WHERE id = ?');
    if ($updateStmt) {
        $updateStmt->bind_param('iiidssssi', $tenant_id, $lease_id, $property_id, $amount, $payment_method, $transaction_id, $reference, $status, $paymentId);
        $updateStmt->execute();
        $updateStmt->close();
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        $conn->close();
        exit;
    }
}

$insertStmt = $conn->prepare('INSERT INTO rent_payments (tenant_id, lease_id, property_id, amount_paid, payment_date, payment_method, transaction_id, reference, status, created_at) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, NOW())');
if ($insertStmt) {
    $insertStmt->bind_param('iiidssss', $tenant_id, $lease_id, $property_id, $amount, $payment_method, $transaction_id, $reference, $status);
    if ($insertStmt->execute()) {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    } else {
        log_integration('rent_payments insert failed: ' . $insertStmt->error);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Could not record payment.']);
    }
    $insertStmt->close();
} else {
    log_integration('Unable to prepare rent_payments insert statement.');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not record payment.']);
}

$conn->close();
?>
