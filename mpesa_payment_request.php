<?php
require_once 'dbconnect.php';
require_once 'integrations.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Tenant login required.']);
    exit;
}

$tenant_id = intval($_SESSION['tenant_id']);
$lease_id = intval($_POST['lease_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$phone = format_mpesa_phone($_POST['phone'] ?? '');
$accountReference = trim($_POST['account_reference'] ?? 'RENT');
$transactionDesc = trim($_POST['transaction_desc'] ?? 'Rent payment');

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payment amount must be greater than zero.']);
    exit;
}

if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid phone number is required.']);
    exit;
}

$lease = null;
if ($lease_id > 0) {
    $leaseResult = $conn->query("SELECT id, tenant_id, property_id FROM leases WHERE id = " . intval($lease_id) . " LIMIT 1");
    if ($leaseResult && $leaseResult->num_rows > 0) {
        $lease = $leaseResult->fetch_assoc();
    }
}

if (!$lease) {
    $leaseResult = $conn->query("SELECT id, tenant_id, property_id FROM leases WHERE tenant_id = {$tenant_id} AND status = 'Active' ORDER BY lease_start_date DESC LIMIT 1");
    if ($leaseResult && $leaseResult->num_rows > 0) {
        $lease = $leaseResult->fetch_assoc();
    }
}

$lease_id = intval($lease['id'] ?? 0);
$property_id = intval($lease['property_id'] ?? 0);

$callbackUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://'
    . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
    . '/mpesa_callback.php';

$response = mpesa_stk_push_request($phone, $amount, $accountReference, $transactionDesc, $callbackUrl);

if (empty($response) || !is_array($response)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to connect to M-PESA.']);
    exit;
}

if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
    $checkoutRequestID = $response['CheckoutRequestID'] ?? '';
    $merchantRequestID = $response['MerchantRequestID'] ?? '';

    $stmt = $conn->prepare("INSERT INTO rent_payments (tenant_id, lease_id, property_id, amount_paid, payment_date, payment_method, transaction_id, reference, status, created_at) VALUES (?, ?, ?, ?, NOW(), 'mpesa', ?, ?, 'pending', NOW())");
    if ($stmt) {
        $stmt->bind_param('iiidss', $tenant_id, $lease_id, $property_id, $amount, $checkoutRequestID, $phone);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'M-PESA payment request sent. Enter your PIN on your phone to complete the transaction.',
        'data' => [
            'CheckoutRequestID' => $checkoutRequestID,
            'MerchantRequestID' => $merchantRequestID,
            'amount' => $amount,
            'phone' => $phone,
        ]
    ]);
    exit;
}

$errorMessage = $response['errorMessage'] ?? ($response['error'] ?? 'The payment request was not accepted by M-PESA.');

http_response_code(500);
echo json_encode(['status' => 'error', 'message' => $errorMessage, 'response' => $response]);
exit;
