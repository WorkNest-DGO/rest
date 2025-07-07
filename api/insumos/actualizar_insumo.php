<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nombre    = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$unidad    = isset($_POST['unidad']) ? trim($_POST['unidad']) : '';
$existencia = isset($_POST['existencia']) ? (float)$_POST['existencia'] : 0;
$tipo      = isset($_POST['tipo_control']) ? trim($_POST['tipo_control']) : '';

if ($id <= 0 || $nombre === '' || $unidad === '' || $tipo === '') {
    error('Datos incompletos');
}

// obtener imagen actual
$sel = $conn->prepare('SELECT imagen FROM insumos WHERE id = ?');
if (!$sel) {
    error('Error al preparar consulta: ' . $conn->error);
}
$sel->bind_param('i', $id);
$sel->execute();
$res = $sel->get_result();
if (!$res || $res->num_rows === 0) {
    $sel->close();
    error('Insumo no encontrado');
}
$actual = $res->fetch_assoc()['imagen'];
$sel->close();

$aliasImagen = $actual;
if (!empty($_FILES['imagen']['name'])) {
    $dir = __DIR__ . '/../../uploads/';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    $aliasImagen = uniqid('ins_') . ($ext ? ".{$ext}" : '');
    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $dir . $aliasImagen)) {
        error('Error al subir imagen');
    }
    if ($actual && file_exists($dir . $actual)) {
        @unlink($dir . $actual);
    }
}

$stmt = $conn->prepare('UPDATE insumos SET nombre = ?, unidad = ?, existencia = ?, tipo_control = ?, imagen = ? WHERE id = ?');
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('ssdssi', $nombre, $unidad, $existencia, $tipo, $aliasImagen, $id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar insumo: ' . $stmt->error);
}
$stmt->close();

success(true);
?>
