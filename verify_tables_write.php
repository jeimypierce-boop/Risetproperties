<?php
$mysqli = new mysqli('localhost', 'root', '', 'riset_properties');
$output = [];
if ($mysqli->connect_errno) {
    $output[] = 'CONNECT_ERROR: ' . $mysqli->connect_error;
} else {
    foreach (['rent_payments', 'maintenance_requests', 'tenant_documents'] as $name) {
        $res = $mysqli->query("SHOW TABLES LIKE '$name'");
        $output[] = $name . ': ' . ($res && $res->num_rows ? 'FOUND' : 'MISSING');
    }
    $mysqli->close();
}
file_put_contents('verify_tables_result.txt', implode("\n", $output));
