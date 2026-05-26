<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';

if (empty($_SESSION['tenant_id'])) { header('Location: tenant-login.php'); exit; }
$tenant_id = intval($_SESSION['tenant_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $uploadDir = __DIR__ . '/../uploads/documents/' . $tenant_id;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $tmp = $_FILES['document']['tmp_name'];
    $orig = basename($_FILES['document']['name']);
    $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_', $orig);
    $target = $uploadDir . '/' . $safe;
    if (move_uploaded_file($tmp, $target)) {
        $stmt = $conn->prepare("INSERT INTO tenant_documents (tenant_id, lease_id, property_id, file_path, original_name, mime_type, file_size, description, uploaded_at) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, NOW())");
        $mime = $_FILES['document']['type'];
        $size = intval($_FILES['document']['size']);
        $desc = $_POST['description'] ?? '';
        $stmt->bind_param('isssis', $tenant_id, $target, $orig, $mime, $size, $desc);
        $stmt->execute();
        $stmt->close();
        $success = 'Document uploaded.';
    } else {
        $error = 'Upload failed.';
    }
}

$docs = $conn->query("SELECT * FROM tenant_documents WHERE tenant_id = {$tenant_id} ORDER BY uploaded_at DESC");

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Your Documents</title><link href="../css/bootstrap.css" rel="stylesheet"></head>
<body class="container mt-4">
  <nav class="mb-4">
    <a class="btn btn-sm btn-outline-secondary" href="tenant-dashboard.php">Dashboard</a>
    <a class="btn btn-sm btn-outline-primary" href="tenant-payments.php">Payments</a>
    <a class="btn btn-sm btn-outline-primary" href="tenant-maintenance.php">Maintenance</a>
    <a class="btn btn-sm btn-outline-primary" href="tenant-documents.php">Documents</a>
    <a class="btn btn-sm btn-outline-secondary" href="tenant-logout.php">Logout</a>
  </nav>
  <h3>Your Documents</h3>
  <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <div class="form-group"><label>File</label><input type="file" name="document" class="form-control" required></div>
    <div class="form-group"><label>Description</label><input name="description" class="form-control"></div>
    <button class="btn btn-primary">Upload</button>
  </form>
  <hr>
  <h4>Uploaded</h4>
  <ul>
  <?php while($d = $docs->fetch_assoc()): ?>
    <li><a href="download-document.php?id=<?php echo intval($d['id']); ?>" target="_blank"><?php echo htmlspecialchars($d['original_name']); ?></a> — <?php echo $d['uploaded_at']; ?></li>
  <?php endwhile; ?>
  </ul>
</body></html>
