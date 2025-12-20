<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$printId = isset($input['print_id']) ? (int)$input['print_id'] : 0;
$nombreLogico = trim($input['nombre_logico'] ?? '');
$lugar = trim($input['lugar'] ?? '');
$ip = trim($input['ip'] ?? '');
$activo = isset($input['activo']) ? (int)$input['activo'] : 1;
$sede = isset($input['sede']) ? (int)$input['sede'] : 0;

if ($printId <= 0 || $nombreLogico === '' || $lugar === '' || $ip === '') {
    error('Datos incompletos');
}

$stmt = $conn->prepare("UPDATE impresoras SET nombre_logico = ?, lugar = ?, ip = ?, activo = ?, sede = ? WHERE print_id = ?");
if (!$stmt) {
    error('Error en la preparacion: ' . $conn->error);
}
$stmt->bind_param('sssiii', $nombreLogico, $lugar, $ip, $activo, $sede, $printId);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'mensaje' => 'Impresora actualizada']);
} else {
    error('Error al actualizar impresora: ' . $stmt->error);
}
