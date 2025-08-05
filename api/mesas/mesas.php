<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT m.id, m.nombre, m.estado, m.usuario_id, u.nombre AS mesero_nombre
          FROM mesas m
          LEFT JOIN usuarios u ON m.usuario_id = u.id
          ORDER BY m.id";
$result = $conn->query($query);
if (!$result) {
    error('Error al obtener mesas: ' . $conn->error);
}
$mesas = [];
while ($row = $result->fetch_assoc()) {
    $mesas[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre'],
        'usuario_id' => $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null,
        'mesero_nombre' => $row['mesero_nombre'],
        'estado' => $row['estado']
    ];
}
success($mesas);
