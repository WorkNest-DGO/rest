<?php
require_once __DIR__ . '/../../config/db.php';

$corte_id = null;
if (isset($_GET['corte_id'])) {
    $corte_id = (int)$_GET['corte_id'];
} elseif (isset($_GET['id'])) {
    $corte_id = (int)$_GET['id'];
}
if (!$corte_id) {
    die('corte_id requerido');
}

$query = $conn->prepare('SELECT v.id, v.fecha, SUM(t.total) AS total, u.nombre AS usuario, SUM(t.propina) AS propina
                         FROM ventas v
                         LEFT JOIN tickets t ON t.venta_id = v.id
                         JOIN usuarios u ON v.usuario_id = u.id
                         WHERE v.corte_id = ?
                         GROUP BY v.id
                         ORDER BY v.fecha');
if (!$query) {
    die('Error');
}
$query->bind_param('i', $corte_id);
$query->execute();
$res = $query->get_result();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="corte_' . $corte_id . '.csv"');

echo "ID,Fecha,Total,Usuario,Propina\n";
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . ',' . $row['fecha'] . ',' . $row['total'] . ',' . $row['usuario'] . ',' . $row['propina'] . "\n";
}
$query->close();
?>
