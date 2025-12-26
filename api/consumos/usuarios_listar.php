<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
$conn->set_charset('utf8mb4');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado', 'data' => null]);
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre, rol, sede_id FROM usuarios WHERE activo = 1 ORDER BY nombre");
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar usuarios', 'data' => null]);
    exit;
}
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'mensaje' => 'Error al obtener usuarios', 'data' => null]);
    exit;
}
$res = $stmt->get_result();
$usuarios = [];
while ($row = $res->fetch_assoc()) {
    $usuarios[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre'],
        'rol' => $row['rol'],
        'sede_id' => $row['sede_id'] !== null ? (int)$row['sede_id'] : null
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'mensaje' => 'OK', 'data' => $usuarios]);
