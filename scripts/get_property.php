<?php
$h='localhost'; $u='root'; $p=''; $db='riset_properties';
$c=new mysqli($h,$u,$p,$db);
if($c->connect_error){echo 'DBERR: '.$c->connect_error; exit(1);} 
$r=$c->query("SELECT id,title FROM properties ORDER BY id LIMIT 1");
if($r && ($row=$r->fetch_assoc())){echo $row['id'].'|'.addslashes($row['title']);} else {echo 'NOPROP';}
?>