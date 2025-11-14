<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$promociones = [];
// Exponer campos adicionales (monto y tipo_venta) para reglas más flexibles
$resPromociones = $conn->query("SELECT id, nombre, regla, tipo, monto, tipo_venta FROM catalogo_promos ORDER BY id ASC");
if (!$resPromociones) {
    error('Error al obtener promociones: ' . $conn->error);
}
while ($row = $resPromociones->fetch_assoc()) {
    $promociones[] = [
        'id'         => (int)$row['id'],
        'nombre'     => $row['nombre'],
        'regla'      => $row['regla'],
        'tipo'       => $row['tipo'],
        'monto'      => isset($row['monto']) ? (float)$row['monto'] : 0.0,
        'tipo_venta' => $row['tipo_venta'] ?? null,
    ];
}


header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'promociones' => $promociones
]);
