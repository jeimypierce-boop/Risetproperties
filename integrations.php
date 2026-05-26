<?php
// Integration configuration and helper functions for external services
// Fill in credentials in the arrays below when ready.

$MPESA_CONFIG = [
    'consumer_key' => '',
    'consumer_secret' => '',
    'shortcode' => '',
    'passkey' => '',
    'environment' => 'sandbox' // or 'production'
];

$SMS_CONFIG = [
    'provider' => '', // e.g., 'twilio' or 'africastalking'
    'api_key' => '',
    'api_secret' => '',
    'from' => ''
];

function log_integration($msg) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $file = $logDir . '/integrations.log';
    $entry = date('Y-m-d H:i:s') . " - " . $msg . "\n";
    file_put_contents($file, $entry, FILE_APPEND);
}

function send_sms($to, $message) {
    global $SMS_CONFIG;
    // Placeholder: if provider configured, implement provider API call here.
    // For now we log the message for manual processing.
    log_integration("SMS to {$to}: {$message}");
    return true;
}

function send_email($to, $subject, $body) {
    // Basic PHP mail wrapper - can be replaced with PHPMailer / SMTP when available
    $headers = "From: no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $result = mail($to, $subject, $body, $headers);
    log_integration("Email to {$to}: subject={$subject} result=" . ($result ? 'sent' : 'failed'));
    return $result;
}

function format_mpesa_phone($phone) {
    $clean = preg_replace('/[^0-9+]/', '', trim($phone));
    if ($clean === '') {
        return '';
    }
    if (strpos($clean, '+') === 0) {
        $clean = substr($clean, 1);
    }
    if (strlen($clean) === 9 && strpos($clean, '7') === 0) {
        return '+254' . $clean;
    }
    if (strlen($clean) === 10 && strpos($clean, '0') === 0) {
        return '+254' . substr($clean, 1);
    }
    if (strlen($clean) === 12 && strpos($clean, '254') === 0) {
        return '+' . $clean;
    }
    if (strlen($clean) === 13 && strpos($clean, '254') === 0) {
        return '+' . $clean;
    }
    return '+' . $clean;
}

function get_mpesa_base_url() {
    global $MPESA_CONFIG;
    return !empty($MPESA_CONFIG['environment']) && $MPESA_CONFIG['environment'] === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';
}

function mpesa_http_post($url, $body, $headers = []) {
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            log_integration('M-PESA HTTP POST failed: ' . $error);
            return false;
        }
        return $response;
    }

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", array_merge($headers, ['Content-Type: application/json'])),
            'content' => $body,
            'ignore_errors' => true,
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        log_integration('M-PESA HTTP POST file_get_contents failed for URL: ' . $url);
        return false;
    }
    return $response;
}

function get_mpesa_access_token() {
    global $MPESA_CONFIG;
    static $cachedToken = null;
    static $tokenExpiresAt = 0;

    if ($cachedToken && time() < $tokenExpiresAt) {
        return $cachedToken;
    }

    if (empty($MPESA_CONFIG['consumer_key']) || empty($MPESA_CONFIG['consumer_secret'])) {
        log_integration('M-PESA configuration is incomplete.');
        return false;
    }

    $url = get_mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
    $headers = [
        'Authorization: Basic ' . base64_encode($MPESA_CONFIG['consumer_key'] . ':' . $MPESA_CONFIG['consumer_secret'])
    ];
    $response = mpesa_http_post($url, '', $headers);
    if (!$response) {
        return false;
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        log_integration('M-PESA token response missing access_token: ' . $response);
        return false;
    }

    $cachedToken = $data['access_token'];
    $tokenExpiresAt = time() + intval($data['expires_in'] ?? 3500);
    return $cachedToken;
}

function mpesa_stk_push_request($phone, $amount, $accountReference, $transactionDesc, $callbackUrl) {
    global $MPESA_CONFIG;

    $phone = format_mpesa_phone($phone);
    if (empty($phone) || $amount <= 0) {
        return ['error' => 'Invalid phone or amount'];
    }

    $accessToken = get_mpesa_access_token();
    if (!$accessToken) {
        return ['error' => 'Could not obtain M-PESA access token'];
    }

    $timestamp = date('YmdHis');
    $password = base64_encode($MPESA_CONFIG['shortcode'] . $MPESA_CONFIG['passkey'] . $timestamp);
    $url = get_mpesa_base_url() . '/mpesa/stkpush/v1/processrequest';
    $payload = [
        'BusinessShortCode' => $MPESA_CONFIG['shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => $MPESA_CONFIG['shortcode'],
        'PhoneNumber' => $phone,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => $accountReference,
        'TransactionDesc' => $transactionDesc,
    ];

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $response = mpesa_http_post($url, json_encode($payload), $headers);
    if (!$response) {
        return ['error' => 'M-PESA request failed to send.'];
    }

    $result = json_decode($response, true);
    if (!$result) {
        return ['error' => 'Invalid response from M-PESA'];
    }

    log_integration('M-PESA STK push request response: ' . json_encode($result));
    return $result;
}

function find_tenant_by_phone($phone) {
    global $conn;
    $phone = format_mpesa_phone($phone);
    if (empty($phone)) {
        return null;
    }
    $stmt = $conn->prepare('SELECT id, first_name, last_name, phone FROM tenants WHERE REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "") = REPLACE(REPLACE(REPLACE(?, " ", ""), "-", ""), "+", "") LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $tenant = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $tenant;
}

function find_active_lease_for_tenant($tenant_id) {
    global $conn;
    $tenant_id = intval($tenant_id);
    if ($tenant_id <= 0) {
        return null;
    }
    $stmt = $conn->prepare('SELECT id, property_id FROM leases WHERE tenant_id = ? AND status = "Active" ORDER BY lease_start_date DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lease = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $lease;
}

function parse_mpesa_callback_metadata($items) {
    $parsed = [];
    if (!is_array($items)) {
        return $parsed;
    }
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['Name'])) {
            continue;
        }
        $name = $item['Name'];
        if (isset($item['Value'])) {
            $parsed[$name] = $item['Value'];
        }
    }
    return $parsed;
}
?>
