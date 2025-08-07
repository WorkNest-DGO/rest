<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$limite = isset($_GET['limite']) ? max(1, intval($_GET['limite'])) : 15;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$orden = $_GET['orden'] ?? 'fecha DESC';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$orden = trim($orden);
if ($orden !== 'fecha ASC' && $orden !== 'fecha DESC') {
    $orden = 'fecha DESC';
}
$offset = ($pagina - 1) * $limite;

$baseFrom = "FROM vw_ventas_detalladas vw JOIN ventas v ON v.id = vw.venta_id LEFT JOIN tickets t ON t.venta_id = v.id";
$where = '';
$params = [];
$types = '';
if ($busqueda !== '') {
    $like = "%{$busqueda}%";
    $where = " WHERE t.folio LIKE ? OR t.mesa_nombre LIKE ? OR t.mesero_nombre LIKE ? OR t.tipo_pago LIKE ? OR DATE(t.fecha) LIKE ?";
    $params = [$like, $like, $like, $like, $like];
    $types = str_repeat('s', 5);
}

$countSql = "SELECT COUNT(*) AS total $baseFrom$where";
$countStmt = $conn->prepare($countSql);
if ($busqueda !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
if (!$countResult) {
    error('Error al contar ventas: ' . $conn->error);
}
$totalRegistros = (int)$countResult->fetch_assoc()['total'];
$totalPaginas = (int)ceil($totalRegistros / $limite);

$query = "SELECT vw.*, v.tipo_entrega, v.usuario_id, v.entregado, v.sede_id, t.folio, t.mesa_nombre, t.mesero_nombre, t.tipo_pago, t.fecha as ticket_fecha
          $baseFrom$where
          ORDER BY $orden
          LIMIT ? OFFSET ?"; 
$stmt = $conn->prepare($query);
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
if ($busqueda !== '') {
    $typesMain = $types . 'ii';
    $paramsMain = array_merge($params, [$limite, $offset]);
    $stmt->bind_param($typesMain, ...$paramsMain);
} else {
    $stmt->bind_param('ii', $limite, $offset);
}
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
