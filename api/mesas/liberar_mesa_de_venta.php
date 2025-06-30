<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$venta_id = $input['venta_id'] ?? null;
if (!$venta_id) {
    error('Datos inválidos');
}
$venta_id = (int)$venta_id;

$stmt = $conn->prepare('SELECT mesa_id FROM ventas WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $venta_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al obtener venta: ' . $stmt->error);
}
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row || !$row['mesa_id']) {
    success(true); // nothing to liberar
}
$mesa_id = (int)$row['mesa_id'];

$upd = $conn->prepare("UPDATE mesas SET estado = 'libre' WHERE id = ?");
if (!$upd) {
    error('Error al preparar actualización: ' . $conn->error);
}
$upd->bind_param('i', $mesa_id);
if (!$upd->execute()) {
    $upd->close();
    error('Error al liberar mesa: ' . $upd->error);
}
$upd->close();

success(true);
?>
