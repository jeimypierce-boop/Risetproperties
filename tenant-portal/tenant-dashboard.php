<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';

if (empty($_SESSION['tenant_id'])) { header('Location: tenant-login.php'); exit; }
$tenant_id = intval($_SESSION['tenant_id']);

$tenant = $conn->query("SELECT id, first_name, last_name, phone, email FROM tenants WHERE id = {$tenant_id}")->fetch_assoc();

// Get active lease
$lease = $conn->query("SELECT l.*, p.title as property_title FROM leases l LEFT JOIN properties p ON l.property_id = p.id WHERE l.tenant_id = {$tenant_id} AND l.status = 'Active' LIMIT 1")->fetch_assoc();

// Calculate this month's paid amount
$paidRow = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as paid FROM rent_payments WHERE lease_id = " . intval($lease['id']) . " AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetch_assoc();
$paid = $paidRow['paid'] ?? 0;
$tenant_name = htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']);
$tenant_email = htmlspecialchars($tenant['email']);

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant Dashboard</title>
    <link href="../css/bootstrap.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link href="../css/style-mob.css" rel="stylesheet">
</head>
<body class="container-fluid" style="min-width: 0; word-wrap: break-word; padding: 15px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3>Welcome, <?php echo $tenant_name; ?></h3>
      <p class="mb-0 text-muted">Logged in as <?php echo $tenant_email; ?></p>
    </div>
    <a href="tenant-logout.php" class="btn btn-secondary">Logout</a>
  </div>
  <hr>
  <h5>Lease</h5>
  <?php if ($lease): ?>
    <p><strong>Property:</strong> <?php echo htmlspecialchars($lease['property_title']); ?></p>
    <p><strong>Rent:</strong> KES <?php echo number_format($lease['monthly_rent']); ?></p>
    <p><strong>Paid this month:</strong> KES <?php echo number_format($paid); ?></p>
    <p><a class="btn btn-primary" href="tenant-payments.php">Payments</a>
    <a class="btn btn-outline-primary" href="tenant-maintenance.php">Maintenance</a>
    <a class="btn btn-outline-secondary" href="tenant-documents.php">Documents</a></p>
  <?php else: ?>
    <p>No active lease found.</p>
  <?php endif; ?>
</body></html>
