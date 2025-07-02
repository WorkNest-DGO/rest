<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$corte_id = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : null;
if (!$corte_id) {
    error('corte_id requerido');
}

$query = $conn->prepare('SELECT v.id, v.fecha, SUM(t.total) AS total, u.nombre AS usuario, SUM(t.propina) AS propina
                         FROM ventas v
                         LEFT JOIN tickets t ON t.venta_id = v.id
                         JOIN usuarios u ON v.usuario_id = u.id
                         WHERE v.corte_id = ?
                         GROUP BY v.id
                         ORDER BY v.fecha');
if (!$query) {
    error('Error al preparar consulta: ' . $conn->error);
}
$query->bind_param('i', $corte_id);
$query->execute();
$res = $query->get_result();
$ventas = [];
while ($row = $res->fetch_assoc()) {
    $ventas[] = [
        'id'       => (int)$row['id'],
        'fecha'    => $row['fecha'],
        'total'    => (float)($row['total'] ?? 0),
        'usuario'  => $row['usuario'],
        'propina'  => (float)($row['propina'] ?? 0)
    ];
}
$query->close();

success($ventas);
?>
