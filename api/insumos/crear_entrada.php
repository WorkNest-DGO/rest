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
    if (!isset($p['insumo_id'], $p['cantidad'], $p['precio_unitario'])) {
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
$upd = $conn->prepare('UPDATE insumos SET existencia = existencia + ? WHERE id = ?');
$tipoStmt = $conn->prepare('SELECT tipo_control FROM insumos WHERE id = ?');
if (!$det || !$upd || !$tipoStmt) {
    $conn->rollback();
    error('Error al preparar detalles: ' . $conn->error);
}

foreach ($productos as $p) {
    $insumo_id = (int)$p['insumo_id'];
    $cantidad  = (int)$p['cantidad'];
    $precio    = (float)$p['precio_unitario'];

    // obtener tipo de control del insumo
    $tipoStmt->bind_param('i', $insumo_id);
    if (!$tipoStmt->execute()) {
        $conn->rollback();
        error('Error al obtener tipo de insumo: ' . $tipoStmt->error);
    }
    $tipoStmt->bind_result($tipo);
    $tipoStmt->fetch();
    $tipoStmt->free_result();

    $unidades = isset($p['unidades']) ? (float)$p['unidades'] : 1;
    $cantidad_final = ($tipo === 'desempaquetado') ? $cantidad * $unidades : $cantidad;

    $det->bind_param('iiid', $entrada_id, $insumo_id, $cantidad, $precio);
    if (!$det->execute()) {
        $conn->rollback();
        $det->close();
        $upd->close();
        error('Error al insertar detalle: ' . $det->error);
    }

    $upd->bind_param('di', $cantidad_final, $insumo_id);
    if (!$upd->execute()) {
        $conn->rollback();
        $det->close();
        $upd->close();
        error('Error al actualizar existencia: ' . $upd->error);
    }
}
$det->close();
$upd->close();
$tipoStmt->close();

$conn->commit();

success(['mensaje' => 'Entrada registrada']);
