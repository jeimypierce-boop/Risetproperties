<?php
// Read Railway variables, fallback to local settings if empty
$host     = getenv('DB_HOST')     ?: 'localhost';
$port     = getenv('DB_PORT')     ?: '3306';
$user     = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') ?: ''; // Leaves empty for local dev
$dbname   = getenv('DB_NAME')     ?: 'riset_properties';

// Establish connection with the Port included
$conn = new mysqli($host, $user, $password, $dbname, (int)$port);

// Check connection
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}
?>
