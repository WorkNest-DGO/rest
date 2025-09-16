<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$promociones = [];
$resPromociones = $conn->query("SELECT id, nombre,regla,tipo FROM catalogo_promos ORDER BY id ASC");
if (!$resPromociones) {
    error('Error al obtener promociones: ' . $conn->error);
}
while ($row = $resPromociones->fetch_assoc()) {
    $promociones[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre'],
        'regla' => $row['regla'],
        'tipo' => $row['tipo']
    ];
}


header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'promociones' => $promociones
]);
