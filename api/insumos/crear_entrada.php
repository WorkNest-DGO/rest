<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error('JSON inválido');
}

$proveedor_id = isset($input['proveedor_id']) ? (int)$input['proveedor_id'] : 0;
$productos = isset($input['productos']) && is_array($input['productos']) ? $input['productos'] : null;

if (!$proveedor_id || !$productos) {
    error('Datos incompletos');
}

$total = 0;
foreach ($productos as $p) {
    if (!isset($p['producto_id'], $p['cantidad'], $p['precio_unitario'])) {
        error('Formato de producto incorrecto');
    }
    $total += $p['cantidad'] * $p['precio_unitario'];
}

$conn->begin_transaction();

$stmtEntrada = $conn->prepare('INSERT INTO entradas_insumo (proveedor_id, total) VALUES (?, ?)');
if (!$stmtEntrada) {
    $conn->rollback();
    error('Error al preparar entrada: ' . $conn->error);
}
$stmtEntrada->bind_param('id', $proveedor_id, $total);
if (!$stmtEntrada->execute()) {
    $stmtEntrada->close();
    $conn->rollback();
    error('Error al registrar entrada: ' . $stmtEntrada->error);
}
$entrada_id = $stmtEntrada->insert_id;
$stmtEntrada->close();

$det = $conn->prepare('INSERT INTO entradas_detalle (entrada_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
$upd = $conn->prepare('UPDATE productos SET existencia = existencia + ? WHERE id = ?');
if (!$det || !$upd) {
    $conn->rollback();
    error('Error al preparar detalles: ' . $conn->error);
}

foreach ($productos as $p) {
    $producto_id = (int)$p['producto_id'];
    $cantidad = (int)$p['cantidad'];
    $precio = (float)$p['precio_unitario'];
    $det->bind_param('iiid', $entrada_id, $producto_id, $cantidad, $precio);
    if (!$det->execute()) {
        $conn->rollback();
        $det->close();
        $upd->close();
        error('Error al insertar detalle: ' . $det->error);
    }
    $upd->bind_param('ii', $cantidad, $producto_id);
    if (!$upd->execute()) {
        $conn->rollback();
        $det->close();
        $upd->close();
        error('Error al actualizar existencia: ' . $upd->error);
    }
}
$det->close();
$upd->close();

$conn->commit();

success(['mensaje' => 'Entrada registrada']);
