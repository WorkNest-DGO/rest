<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$limite = isset($_GET['limite']) ? max(1, intval($_GET['limite'])) : 15;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$orden = $_GET['orden'] ?? 'fecha DESC';
$orden = trim($orden);
if ($orden !== 'fecha ASC' && $orden !== 'fecha DESC') {
    $orden = 'fecha DESC';
}
$offset = ($pagina - 1) * $limite;

$countQuery = "SELECT COUNT(*) AS total FROM vw_ventas_detalladas vw JOIN ventas v ON v.id = vw.venta_id";
$countResult = $conn->query($countQuery);
if (!$countResult) {
    error('Error al contar ventas: ' . $conn->error);
}
$totalRegistros = (int)$countResult->fetch_assoc()['total'];
$totalPaginas = (int)ceil($totalRegistros / $limite);

$query = "SELECT vw.*, v.tipo_entrega, v.usuario_id, v.entregado, v.sede_id
          FROM vw_ventas_detalladas vw
          JOIN ventas v ON v.id = vw.venta_id
          ORDER BY $orden
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('ii', $limite, $offset);
$stmt->execute();
$result = $stmt->get_result();

$ventas = [];
while ($row = $result->fetch_assoc()) {
    $ventas[] = $row;
}

$data = [
    'ventas' => $ventas,
    'total_registros' => $totalRegistros,
    'pagina_actual' => $pagina,
    'total_paginas' => $totalPaginas
];

success($data);
?>
