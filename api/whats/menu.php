<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

if ($conn->connect_error) {
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$query = "SELECT nombre, precio FROM menu_dia WHERE fecha = CURDATE()";
$resultado = $conn->query($query);

$menu = [];
while ($fila = $resultado->fetch_assoc()) {
    $menu[] = $fila;
}

echo json_encode(["menu" => $menu]);
?>

