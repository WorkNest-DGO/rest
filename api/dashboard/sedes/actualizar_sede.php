<?php
require_once __DIR__ . '../../../../config/db.php';
require_once __DIR__ . '/../../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    error('JSON inválido');
}

if (!isset($data['id']) || !isset($data['nombre']) || !isset($data['direccion']) || !isset($data['rfc']) || !isset($data['telefono']) || !isset($data['correo']) || !isset($data['web']) || !isset($data['activo'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Datos incompletos'
    ]);
    exit;
}

$id = intval($data['id']);
$nombre = trim($data['nombre']);
$direccion = trim($data['direccion']);
$rfc = trim($data['rfc']);
$telefono = trim($data['telefono']);
$correo = trim($data['correo']);
$web = trim($data['web']);
$activo = intval($data['activo']);

try {
    $stmt = $conn->prepare('UPDATE sedes SET nombre = ?, direccion = ?, rfc = ?, telefono = ?, correo = ?, web = ?, activo = ? WHERE id = ?');
    $stmt->bind_param('ssssssii', $nombre, $direccion, $rfc, $telefono, $correo, $web, $activo, $id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'mensaje' => 'Sede actualizado correctamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'mensaje' => 'No se pudo actualizar la sede'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}