<?php
// Read Railway variables, fallback to local settings if empty
$host     = getenv('DB_HOST')     ?: 'localhost';
$port     = getenv('DB_PORT')     ?: '3306';
$user     = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') ?: ''; 
$dbname   = getenv('DB_NAME')     ?: 'riset_properties';

// Establish connection
$conn = new mysqli($host, $user, $password, $dbname, (int)$port);

if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// >>> ADD THIS EXACT LINE BELOW TO FIX THE ERROR <<<
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
?>
