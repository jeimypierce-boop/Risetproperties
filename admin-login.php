<?php
// Admin login page - redirects to login.php with admin option
session_start();

// If already logged in, redirect to admin dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], array('admin', 'teacher', 'staff', 'landlord'))) {
    header('Location: admin-dashboard-modern.php');
    exit;
}

// Redirect to login page with admin option
header('Location: login.php');
exit;
?>







