<?php
session_start();
// Clear both unit-specific and root login session values
unset($_SESSION['tenant_id'], $_SESSION['tenant_name'], $_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['login_type'], $_SESSION['user_email']);
session_unset();
session_destroy();
header('Location: tenant-login.php');
exit;
?>
