<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Lista usuarios cuyo rol es 'repartidor' y activos
$sql = "SELECT id, nombre FROM usuarios WHERE rol = 'repartidor' AND (activo IS NULL OR activo = 1) ORDER BY nombre";
$res = $conn->query($sql);
if (!$res) {
    error('Error al obtener usuarios repartidores: ' . $conn->error);
}
$out = [];
while ($row = $res->fetch_assoc()) {
    $out[] = [ 'id' => (int)$row['id'], 'nombre' => $row['nombre'] ];
}
success($out);
?>

