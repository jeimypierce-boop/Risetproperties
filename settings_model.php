<?php
function ensure_settings_tables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS service_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS payment_modes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS bank_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bank_name VARCHAR(255) NOT NULL,
        account_number VARCHAR(255) NOT NULL,
        account_name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS invoice_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_type ENUM('generate','regenerate') NOT NULL,
        target_month VARCHAR(7) NOT NULL,
        created_by INT DEFAULT NULL,
        status ENUM('queued','completed','failed') DEFAULT 'queued',
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function get_setting($conn, $key, $default = null) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($value);
    if ($stmt->fetch()) {
        $stmt->close();
        return $value;
    }
    $stmt->close();
    return $default;
}

function set_setting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param('ss', $key, $value);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function get_business_profile($conn) {
    return [
        'business_name' => get_setting($conn, 'business_name', 'Riset Property Ltd'),
        'business_email' => get_setting($conn, 'business_email', 'info@risetproperties.co.ke'),
        'business_phone' => get_setting($conn, 'business_phone', '+254 700 000 000'),
        'business_address' => get_setting($conn, 'business_address', 'Nairobi, Kenya'),
        'business_about' => get_setting($conn, 'business_about', 'Riset Property Ltd is a property management agency focused on tenant services, listings, and maintenance across Kenya.'),
    ];
}

function save_business_profile($conn, $data) {
    $success = true;
    foreach ($data as $key => $value) {
        if (!set_setting($conn, $key, $value)) {
            $success = false;
        }
    }
    return $success;
}

function get_service_types($conn) {
    $services = [];
    $result = $conn->query("SELECT id, name FROM service_types ORDER BY name ASC");
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    return $services;
}

function add_service_type($conn, $name) {
    if (trim($name) === '') {
        return false;
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO service_types (name) VALUES (?)");
    $stmt->bind_param('s', $name);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function get_payment_modes($conn) {
    $modes = [];
    $result = $conn->query("SELECT id, name FROM payment_modes ORDER BY name ASC");
    while ($row = $result->fetch_assoc()) {
        $modes[] = $row;
    }
    return $modes;
}

function add_payment_mode($conn, $name) {
    if (trim($name) === '') {
        return false;
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO payment_modes (name) VALUES (?)");
    $stmt->bind_param('s', $name);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function get_bank_accounts($conn) {
    $banks = [];
    $result = $conn->query("SELECT id, bank_name, account_number, account_name FROM bank_accounts ORDER BY bank_name ASC");
    while ($row = $result->fetch_assoc()) {
        $banks[] = $row;
    }
    return $banks;
}

function add_bank_account($conn, $bankName, $accountNumber, $accountName) {
    if (trim($bankName) === '' || trim($accountNumber) === '' || trim($accountName) === '') {
        return false;
    }
    $stmt = $conn->prepare("INSERT INTO bank_accounts (bank_name, account_number, account_name) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $bankName, $accountNumber, $accountName);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function get_subscription_plan($conn) {
    return [
        'plan_name' => get_setting($conn, 'subscription_plan_name', 'Starter Plan'),
        'amount' => get_setting($conn, 'subscription_plan_amount', '5000'),
        'status' => get_setting($conn, 'subscription_plan_status', 'trial'),
        'valid_until' => get_setting($conn, 'subscription_plan_valid_until', date('Y-m-d', strtotime('+30 days'))),
    ];
}

function save_subscription_plan($conn, $planName, $amount, $status, $validUntil) {
    $success = true;
    $success &= set_setting($conn, 'subscription_plan_name', $planName);
    $success &= set_setting($conn, 'subscription_plan_amount', $amount);
    $success &= set_setting($conn, 'subscription_plan_status', $status);
    $success &= set_setting($conn, 'subscription_plan_valid_until', $validUntil);
    return (bool)$success;
}

function record_invoice_job($conn, $jobType, $month, $createdBy = null) {
    if (!in_array($jobType, ['generate', 'regenerate'], true) || trim($month) === '') {
        return false;
    }
    $stmt = $conn->prepare("INSERT INTO invoice_jobs (job_type, target_month, created_by, status, message) VALUES (?, ?, ?, 'completed', 'Recorded from admin UI')");
    $stmt->bind_param('ssi', $jobType, $month, $createdBy);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function get_latest_invoice_jobs($conn, $limit = 5) {
    $jobs = [];
    $stmt = $conn->prepare("SELECT id, job_type, target_month, status, created_at FROM invoice_jobs ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    $stmt->close();
    return $jobs;
}
