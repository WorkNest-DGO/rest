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

if (!isset($data['id']) || !isset($data['nombre'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Datos incompletos'
    ]);
    exit;
}

$id = intval($data['id']);
$nombre = trim($data['nombre']);

try {
    $stmt = $conn->prepare('UPDATE catalogo_tarjetas SET nombre = ? WHERE id = ?');
    $stmt->bind_param('si', $nombre, $id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'mensaje' => 'Tarjeta actualizado correctamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'mensaje' => 'No se pudo actualizar la tarjeta'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}