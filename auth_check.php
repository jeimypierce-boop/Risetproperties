<?php
// Authentication check file - include this in pages that need login protection
// Include this in PHP files to check if user is logged in

session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// User info
$logged_in_user = array(
    'id' => $is_logged_in ? $_SESSION['user_id'] : null,
    'name' => $is_logged_in ? $_SESSION['user_name'] : null,
    'email' => $is_logged_in ? $_SESSION['user_email'] : null,
    'role' => $is_logged_in ? $_SESSION['user_role'] : null,
    'login_type' => $is_logged_in ? $_SESSION['login_type'] : null
);

// Function to require login - redirect to login page if not logged in
function require_login() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Function to require admin login
function require_admin() {
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], array('admin', 'teacher', 'staff', 'landlord'))) {
        header('Location: login.php');
        exit;
    }
}

// Function to get user display info
function get_user_info() {
    global $logged_in_user;
    return $logged_in_user;
}

function get_user_initials() {
    if (!is_user_logged_in()) {
        return '';
    }
    $name = trim($_SESSION['user_name'] ?? $_SESSION['tenant_name'] ?? '');
    if ($name === '') {
        return '';
    }
    $parts = preg_split('/\s+/', $name);
    if (count($parts) === 1) {
        return strtoupper(substr($parts[0], 0, 1));
    }
    return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
}

function get_user_role_label() {
    if (!is_user_logged_in()) {
        return 'Guest';
    }
    $role = strtolower(trim($_SESSION['user_role'] ?? $_SESSION['login_type'] ?? ''));
    $labels = array(
        'admin' => 'Administrator',
        'teacher' => 'Teacher',
        'staff' => 'Staff',
        'landlord' => 'Landlord',
        'tenant' => 'Tenant',
        'student' => 'Student'
    );
    return isset($labels[$role]) ? $labels[$role] : ucfirst($role);
}

function get_user_display_email() {
    if (!is_user_logged_in()) {
        return '';
    }
    return htmlspecialchars($_SESSION['user_email'] ?? '');
}

// Function to check if user is logged in
function is_user_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to get user info by role
function is_admin() {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], array('admin', 'teacher', 'staff', 'landlord'));
}

function is_student() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
}

function is_landlord() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'landlord';
}

function get_landlord_id() {
    return is_landlord() ? intval($_SESSION['user_id']) : null;
}

function require_landlord() {
    if (!is_landlord()) {
        header('Location: login.php');
        exit;
    }
}

// Function to check if user is a true administrator (not just staff/teacher)
function is_true_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Function to require true admin access (for admin-only features)
function require_true_admin() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

// Function to safely output user name in HTML
function get_user_display_name() {
    if (is_user_logged_in()) {
        return htmlspecialchars($_SESSION['user_name']);
    }
    return 'Guest';
}
?>



