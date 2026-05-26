<?php
$mysqli = new mysqli('localhost', 'root', '', 'riset_properties');
if ($mysqli->connect_errno) {
    echo "CONNECT_ERROR: " . $mysqli->connect_error;
    exit(1);
}
$names = ['rent_payments', 'maintenance_requests', 'tenant_documents'];
foreach ($names as $name) {
    $res = $mysqli->query("SHOW TABLES LIKE '$name'");
    echo $name . ': ' . ($res && $res->num_rows ? 'FOUND' : 'MISSING') . "\n";
}
$mysqli->close();
