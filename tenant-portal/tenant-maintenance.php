<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';
require_once __DIR__ . '/../integrations.php';

if (empty($_SESSION['tenant_id'])) { header('Location: tenant-login.php'); exit; }
$tenant_id = intval($_SESSION['tenant_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_maintenance') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $lease_id = $_POST['lease_id'] ?? null;

    $stmt = $conn->prepare("INSERT INTO maintenance_requests (tenant_id, lease_id, title, description, status, created_at) VALUES (?, ?, ?, ?, 'new', NOW())");
    $stmt->bind_param('iiss', $tenant_id, $lease_id, $title, $description);
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        // handle optional photo upload
        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/maintenance/' . $tenant_id;
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $tmp = $_FILES['photo']['tmp_name'];
            $orig = basename($_FILES['photo']['name']);
            $target = $uploadDir . '/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_', $orig);
            if (move_uploaded_file($tmp, $target)) {
                // attach file path to description or a separate document table
                $note = 'Photo uploaded: ' . $target;
                $conn->query("UPDATE maintenance_requests SET description = CONCAT(description, '\n', '" . $conn->real_escape_string($note) . "') WHERE id = " . intval($newId));
            }
        }
        $success = 'Maintenance request submitted.';
    } else {
        $error = 'Could not submit request: ' . $stmt->error;
    }
    $stmt->close();
}

$requests = $conn->query("SELECT * FROM maintenance_requests WHERE tenant_id = {$tenant_id} ORDER BY created_at DESC");

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Requests</title>
    <link href="../css/bootstrap.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link href="../css/style-mob.css" rel="stylesheet">
</head>
<body class="container-fluid" style="min-width: 0; word-wrap: break-word; padding: 15px;">
  <nav class="mb-4">
    <a class="btn btn-sm btn-outline-secondary" href="tenant-dashboard.php">Dashboard</a>
    <a class="btn btn-sm btn-outline-primary" href="tenant-payments.php">Payments</a>
    <a class="btn btn-sm btn-outline-primary" href="tenant-maintenance.php">Maintenance</a>
    <a class="btn btn-sm btn-outline-primary" href="tenant-documents.php">Documents</a>
    <a class="btn btn-sm btn-outline-secondary" href="tenant-logout.php">Logout</a>
  </nav>
  <h3>Maintenance Requests</h3>
  <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="submit_maintenance">
    <div class="form-group"><label>Title</label><input name="title" class="form-control" required></div>
    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" required></textarea></div>
    <div class="form-group"><label>Photo (optional)</label><input type="file" name="photo" accept="image/*" class="form-control"></div>
    <button class="btn btn-primary">Submit Request</button>
  </form>
  <hr>
  <h4>Your Requests</h4>
  <table class="table">
    <thead><tr><th>#</th><th>Title</th><th>Status</th><th>Created</th></tr></thead>
    <tbody>
    <?php while($r = $requests->fetch_assoc()): ?>
      <tr><td><?php echo $r['id']; ?></td><td><?php echo htmlspecialchars($r['title']); ?></td><td><?php echo $r['status']; ?></td><td><?php echo $r['created_at']; ?></td></tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</body></html>
