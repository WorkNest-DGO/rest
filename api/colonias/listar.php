<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$sql = "SELECT id, colonia, dist_km_la_forestal, costo_fore, cp, tipo_asenta FROM colonias ORDER BY colonia ASC";
$result = $conn->query($sql);
if (!$result) {
    error('Error al obtener colonias: ' . $conn->error);
}

$colonias = [];
while ($row = $result->fetch_assoc()) {
    $colonias[] = [
        'id' => (int)$row['id'],
        'colonia' => $row['colonia'],
        'dist_km_la_forestal' => $row['dist_km_la_forestal'] !== null ? (float)$row['dist_km_la_forestal'] : null,
        'costo_fore' => $row['costo_fore'] !== null ? (float)$row['costo_fore'] : null,
        'cp' => $row['cp'],
        'tipo_asenta' => $row['tipo_asenta'],
    ];
}

success($colonias);
