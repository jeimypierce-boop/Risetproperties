<?php
if ($argc < 2) { echo "USAGE: php get_unit_by_number.php <unit_number>\n"; exit(1);} 
$u = $argv[1];
$h='localhost'; $user='root'; $p=''; $db='riset_properties';
$c=new mysqli($h,$user,$p,$db);
if($c->connect_error){echo 'DBERR: '.$c->connect_error; exit(1);} 
$stmt = $c->prepare("SELECT id, unit_number, unit_type, status, monthly_rent FROM units WHERE unit_number = ? LIMIT 1");
$stmt->bind_param('s', $u);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $row = $res->fetch_assoc()){
    echo 'FOUND|'. $row['id'] . '|' . $row['unit_number'] . '|' . $row['unit_type'] . '|' . $row['status'] . '|' . $row['monthly_rent'];
} else {
    echo 'NOTFOUND';
}
$stmt->close(); $c->close();
?>