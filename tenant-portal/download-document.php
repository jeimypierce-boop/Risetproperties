<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';

if (empty($_SESSION['tenant_id'])) {
    header('Location: tenant-login.php');
    exit;
}
$tenant_id = intval($_SESSION['tenant_id']);
$document_id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT file_path, original_name, mime_type FROM tenant_documents WHERE id = ? AND tenant_id = ? LIMIT 1");
$stmt->bind_param('ii', $document_id, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}
$row = $result->fetch_assoc();
$stmt->close();
$file = $row['file_path'];
if (!is_file($file)) {
    http_response_code(404);
    echo 'File unavailable.';
    exit;
}
$mime = $row['mime_type'] ?: 'application/octet-stream';
$filename = basename($row['original_name'] ?: $file);
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
