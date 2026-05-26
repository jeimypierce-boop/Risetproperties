<?php
if ($argc < 2) { echo "USAGE: php get_unit.php <id>\n"; exit(1);} 
$id = intval($argv[1]);
$h='localhost'; $u='root'; $p=''; $db='riset_properties';
$c=new mysqli($h,$u,$p,$db);
if($c->connect_error){echo 'DBERR: '.$c->connect_error; exit(1);} 
$stmt = $c->prepare("SELECT id, unit_number, unit_type, status, monthly_rent FROM units WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $row = $res->fetch_assoc()){
    echo 'FOUND|'. $row['id'] . '|' . $row['unit_number'] . '|' . $row['unit_type'] . '|' . $row['status'] . '|' . $row['monthly_rent'];
} else {
    echo 'NOTFOUND';
}
$stmt->close(); $c->close();
?>