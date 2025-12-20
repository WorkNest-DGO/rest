<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

$sql = "SELECT print_id, nombre_logico, lugar, ip, activo, sede FROM impresoras ORDER BY print_id ASC";
$result = $conn->query($sql);
if (!$result) {
    error('Error al obtener impresoras: ' . $conn->error);
}
$impresoras = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'mensaje' => 'Impresoras obtenidas',
    'resultado' => $impresoras
]);
