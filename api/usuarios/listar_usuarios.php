<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

$sql = "SELECT u.id, u.nombre, u.usuario, u.rol, u.activo, u.sede_id, s.nombre AS sede_nombre
        FROM usuarios u
        LEFT JOIN sedes s ON s.id = u.sede_id
        ORDER BY u.nombre";
$result = $conn->query($sql);
if (!$result) {
    error('Error al obtener usuarios: ' . $conn->error);
}
$usuarios = [];
while ($row = $result->fetch_assoc()) {
    $row['activo'] = (int) $row['activo'];
    $usuarios[] = $row;
}
echo json_encode([
    'success' => true,
    'mensaje' => 'Usuarios obtenidos',
    'usuarios' => $usuarios
]);
?>
