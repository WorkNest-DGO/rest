<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$venta_id = null;
$accion = null;
if ($input && isset($input['venta_id'])) {
    $venta_id = (int)$input['venta_id'];
    $accion = $input['accion'] ?? null;
} elseif (isset($_POST['venta_id'])) {
    $venta_id = (int)$_POST['venta_id'];
    $accion = $_POST['accion'] ?? null;
}

if (!$venta_id) {
    error('Datos inválidos');
}

if ($accion === 'en_camino') {
    $stmt = $conn->prepare("UPDATE ventas SET estado_entrega='en_camino', fecha_inicio = NOW() WHERE id = ?");
    if (!$stmt) {
        error('Error al preparar actualización: ' . $conn->error);
    }
    $stmt->bind_param('i', $venta_id);
    if (!$stmt->execute()) {
        $stmt->close();
        error('Error al actualizar venta: ' . $stmt->error);
    }
    $stmt->close();
    success(true);
    exit;
}

// verificar que todos los productos estén listos
$check = $conn->prepare("SELECT COUNT(*) AS faltan FROM venta_detalles WHERE venta_id = ? AND estatus_preparacion <> 'listo'");
if (!$check) {
    error('Error al preparar verificación: ' . $conn->error);
}
$check->bind_param('i', $venta_id);
if (!$check->execute()) {
    $check->close();
    error('Error al ejecutar verificación: ' . $check->error);
}
$res = $check->get_result();
$row = $res->fetch_assoc();
$check->close();
if ($row && (int)$row['faltan'] > 0) {
    error('Aún hay productos sin preparar');
}

$seudonimo = isset($_POST['seudonimo']) ? $_POST['seudonimo'] : ($input['seudonimo'] ?? null);
$nombreArchivo = null;
if (!empty($_FILES['foto']['name'])) {
    $dir = __DIR__ . '/../../uploads/evidencias/';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $nombreArchivo = uniqid('evid_') . ($ext ? ".{$ext}" : '');
    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $nombreArchivo)) {
        error('Error al subir imagen');
    }
}

$stmt = $conn->prepare("UPDATE ventas SET estatus = 'cerrada', entregado = 1, estado_entrega='entregado', fecha_entrega=NOW(), seudonimo_entrega=?, foto_entrega=? WHERE id = ?");
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('ssi', $seudonimo, $nombreArchivo, $venta_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar venta: ' . $stmt->error);
}

if ($stmt->affected_rows === 0) {
    $stmt->close();
    error('Venta no encontrada');
}
$stmt->close();

success(true);
?>
