<?php
session_start();
if (!empty($_SESSION['tenant_id'])) {
    header('Location: tenant-dashboard.php');
} else {
    header('Location: ../login.php?target=tenant');
}
exit;
