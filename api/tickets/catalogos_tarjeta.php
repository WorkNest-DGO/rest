<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$bancos = [];
$resBancos = $conn->query("SELECT id, nombre FROM catalogo_bancos ORDER BY nombre");
if (!$resBancos) {
    error('Error al obtener bancos: ' . $conn->error);
}
while ($row = $resBancos->fetch_assoc()) {
    $bancos[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre']
    ];
}

$tarjetas = [];
$resTarjetas = $conn->query("SELECT id, nombre FROM catalogo_tarjetas ORDER BY nombre");
if (!$resTarjetas) {
    error('Error al obtener tarjetas: ' . $conn->error);
}
while ($row = $resTarjetas->fetch_assoc()) {
    $tarjetas[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre']
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'bancos' => $bancos,
    'tarjetas' => $tarjetas
]);
