<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'restaurante';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_errno) {
    die('Error de conexión: ' . $conn->connect_error);
}
?>
