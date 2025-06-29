<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['detalle_id'], $input['nuevo_estado'])) {
    error('Datos inválidos');
}

$detalle_id = (int)$input['detalle_id'];
$nuevo_estado = $input['nuevo_estado'];
$permitidos = ['pendiente', 'en preparación', 'listo', 'entregado'];
if (!in_array($nuevo_estado, $permitidos, true)) {
    error('Estado no válido');
}

$stmt = $conn->prepare('SELECT estatus_preparacion, producto_id, cantidad, insumos_descargados FROM venta_detalles WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $detalle_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    $stmt->close();
    error('Detalle no encontrado');
}
$detalle = $result->fetch_assoc();
$actual   = $detalle['estatus_preparacion'];
$stmt->close();

if (in_array($actual, ['listo', 'entregado'], true)) {
    error('No se puede modificar este producto');
}

$transiciones = [
    'pendiente'      => 'en preparación',
    'en preparación' => 'listo'
];

if (!isset($transiciones[$actual]) || $transiciones[$actual] !== $nuevo_estado) {
    error('Transición no permitida');
}

// Lógica reemplazada por base de datos: ver bd.sql (Trigger/SP)
$upd = $conn->prepare('UPDATE venta_detalles SET estatus_preparacion = ? WHERE id = ?');
if (!$upd) {
    error('Error al preparar actualización: ' . $conn->error);
}
$upd->bind_param('si', $nuevo_estado, $detalle_id);
if (!$upd->execute()) {
    $upd->close();
    error('Error al actualizar: ' . $upd->error);
}
$upd->close();

if ($nuevo_estado === 'listo') {
    $log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
    if ($log) {
        $usuario_id = $input['usuario_id'] ?? null;
        $mod = 'cocina';
        $accion = 'Producto marcado como listo';
        $log->bind_param('issi', $usuario_id, $mod, $accion, $detalle_id);
        $log->execute();
        $log->close();
    }
}

success(true);
?>
