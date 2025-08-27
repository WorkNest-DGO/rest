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
$orden = 'v.' . $orden;
$offset = ($pagina - 1) * $limite;

$baseFrom = "FROM vw_ventas_detalladas vw JOIN ventas v ON v.id = vw.venta_id LEFT JOIN tickets t ON t.venta_id = v.id";
$where = '';
$params = [];
$types = '';
if ($busqueda !== '') {
    $like = "%{$busqueda}%";
    $where = " WHERE CONCAT_WS(' ', v.id, t.folio, v.fecha, v.total, v.estatus, v.tipo_entrega, t.tipo_pago, vw.usuario, t.mesa_nombre, t.mesero_nombre) LIKE ?";
    $params = [$like];
    $types = 's';
}

$countSql = "SELECT COUNT(DISTINCT v.id) AS total $baseFrom$where";
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

$query = "SELECT v.id AS venta_id, v.fecha, v.estatus, vw.usuario, vw.mesa, vw.repartidor,
                 v.tipo_entrega, v.usuario_id, v.entregado, v.sede_id,
                 GROUP_CONCAT(t.folio ORDER BY t.folio) AS folio,
                 MAX(t.mesa_nombre) AS mesa_nombre,
                 MAX(t.mesero_nombre) AS mesero_nombre,
                 GROUP_CONCAT(t.tipo_pago ORDER BY t.id) AS tipo_pago,
                 MIN(t.fecha) AS ticket_fecha,
                 COALESCE(SUM(t.total), v.total) AS total,
                 COALESCE(SUM(t.propina),0) AS propina,
                 COUNT(t.id) AS num_tickets
          $baseFrom$where
          GROUP BY v.id
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
