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

$stmt = $conn->prepare("SELECT id, nombre, precio, existencia FROM productos WHERE activo = 1 ORDER BY nombre");
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar productos', 'data' => null]);
    exit;
}
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'mensaje' => 'Error al obtener productos', 'data' => null]);
    exit;
}
$res = $stmt->get_result();
$productos = [];
while ($row = $res->fetch_assoc()) {
    $productos[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre'],
        'precio' => (float)$row['precio'],
        'existencia' => $row['existencia'] !== null ? (float)$row['existencia'] : null
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'mensaje' => 'OK', 'data' => $productos]);
