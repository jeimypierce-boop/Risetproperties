<?php
$h='localhost'; $u='root'; $p=''; $db='riset_properties';
$c=new mysqli($h,$u,$p,$db);
if($c->connect_error){echo 'DBERR: '.$c->connect_error; exit(1);} 
$property_id = 4;
$unit_name = 'UI Test Unit';
$unit_number = 'UI_AUTOTEST_'.time();
$unit_type = 'Studio';
$monthly_rent = 12345.67;
$deposit = 1000.00;
$currency = 'KES';
$status = 'Available';
$description = 'Automated test unit';
$features = '';
$amenities = '';
$notes = '';
$availability_date = null;
$furnished = 0;
$parking = 0;
$utilities_included = '';
$tenant_id = null;

$insert = $c->prepare("INSERT INTO units (property_id, unit_name, unit_number, unit_type, monthly_rent, deposit, currency, status, description, features, amenities, notes, availability_date, furnished, parking, utilities_included, tenant_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$insert->bind_param('isssddsssssssiisi', $property_id, $unit_name, $unit_number, $unit_type, $monthly_rent, $deposit, $currency, $status, $description, $features, $amenities, $notes, $availability_date, $furnished, $parking, $utilities_included, $tenant_id);
if ($insert->execute()){
    $newid = $insert->insert_id;
    echo "OK|$newid|$unit_number|$unit_type|$status";
} else {
    echo "ERR|".$insert->error;
}
$insert->close();
$c->close();
?>