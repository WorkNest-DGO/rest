<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset(
        $input['venta_id'],
        $input['producto_id'],
        $input['cantidad'],
        $input['precio_unitario']
    )) {
    error('Datos inválidos');
}

$venta_id       = (int) $input['venta_id'];
$producto_id    = (int) $input['producto_id'];
$cantidad       = (int) $input['cantidad'];
$precio_unitario = (float) $input['precio_unitario'];
$total          = $cantidad * $precio_unitario;

$stmt = $conn->prepare(
    'INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario)
     VALUES (?, ?, ?, ?)'
);
if (!$stmt) {
    error('Error al preparar inserción: ' . $conn->error);
}
$stmt->bind_param('iiid', $venta_id, $producto_id, $cantidad, $precio_unitario);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al agregar producto: ' . $stmt->error);
}
$stmt->close();

$up = $conn->prepare('UPDATE ventas SET total = total + ? WHERE id = ?');
if ($up) {
    $up->bind_param('di', $total, $venta_id);
    $up->execute();
    $up->close();
}

success(true);
?>
