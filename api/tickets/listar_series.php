<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$result = $conn->query("SELECT id, descripcion FROM catalogo_folios ORDER BY descripcion");
if (!$result) {
    error('Error al obtener series: ' . $conn->error);
}
$series = [];
while ($row = $result->fetch_assoc()) {
    $series[] = [
        'id' => (int)$row['id'],
        'descripcion' => $row['descripcion']
    ];
}

success($series);
?>
