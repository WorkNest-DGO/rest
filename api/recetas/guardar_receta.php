<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['producto_id'], $input['receta']) || !is_array($input['receta'])) {
    error('Datos incompletos');
}

$producto_id = (int) $input['producto_id'];
$receta = $input['receta'];

$ids = [];
foreach ($receta as $r) {
    if (!isset($r['insumo_id'], $r['cantidad'])) {
        error('Formato de receta incorrecto');
    }
    $insumo_id = (int) $r['insumo_id'];
    if (in_array($insumo_id, $ids)) {
        error('Insumo repetido');
    }
    $ids[] = $insumo_id;
}

$conn->begin_transaction();

$del = $conn->prepare('DELETE FROM recetas WHERE producto_id = ?');
if (!$del) {
    $conn->rollback();
    error('Error al preparar eliminación: ' . $conn->error);
}
$del->bind_param('i', $producto_id);
if (!$del->execute()) {
    $del->close();
    $conn->rollback();
    error('Error al eliminar receta anterior: ' . $del->error);
}
$del->close();

$ins = $conn->prepare('INSERT INTO recetas (producto_id, insumo_id, cantidad) VALUES (?, ?, ?)');
if (!$ins) {
    $conn->rollback();
    error('Error al preparar inserción: ' . $conn->error);
}
foreach ($receta as $r) {
    $insumo_id = (int) $r['insumo_id'];
    $cantidad  = (float) $r['cantidad'];
    $ins->bind_param('iid', $producto_id, $insumo_id, $cantidad);
    if (!$ins->execute()) {
        $ins->close();
        $conn->rollback();
        error('Error al guardar receta: ' . $ins->error);
    }
}
$ins->close();
$conn->commit();

success(true);
