<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Obtener corte_id desde GET o sesión, como en resumen_corte_actual.php
$corte_id = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : ($_SESSION['corte_id'] ?? null);
if (!$corte_id) {
    die('corte_id requerido');
}

// Consulta idéntica a resumen_corte_actual.php
$sql = "SELECT
    t.tipo_pago,
    SUM(t.total)   AS total,
    SUM(t.propina) AS propina
FROM ventas v
JOIN tickets t ON t.venta_id = v.id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?
GROUP BY t.tipo_pago";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$res = $stmt->get_result();

$totalProductos = 0;
$totalPropinas  = 0;
$rows = [];
while ($row = $res->fetch_assoc()) {
    $total   = (float)$row['total'];
    $propina = (float)$row['propina'];
    $productos = $total - $propina;
    $rows[] = [
        'tipo_pago'        => $row['tipo_pago'],
        'productos'        => $productos,
        'propina'          => $propina,
        'total_con_propina'=> $total
    ];
    $totalProductos += $productos;
    $totalPropinas  += $propina;
}
$stmt->close();

// Fondo inicial y total final
$stmtFondo = $conn->prepare('SELECT fondo_inicial FROM corte_caja WHERE id = ?');
$stmtFondo->bind_param('i', $corte_id);
$stmtFondo->execute();
$rowFondo = $stmtFondo->get_result()->fetch_assoc();
$fondo = (float)($rowFondo['fondo_inicial'] ?? 0);
$stmtFondo->close();

$totalEsperado = $totalProductos + $totalPropinas;
$totalFinal    = $totalEsperado + $fondo;

// Configuración de encabezados para descarga CSV con BOM UTF-8
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="corte_' . $corte_id . '.csv"');
echo "\xEF\xBB\xBF"; // BOM

// Encabezados
echo "tipo_pago,total_productos,total_propina,total_con_propina\n";

// Filas de datos
foreach ($rows as $r) {
    echo $r['tipo_pago'] . ','
       . number_format($r['productos'], 2, '.', '') . ','
       . number_format($r['propina'], 2, '.', '') . ','
       . number_format($r['total_con_propina'], 2, '.', '') . "\n";
}

// Totales
echo 'TOTAL,'
   . number_format($totalProductos, 2, '.', '') . ','
   . number_format($totalPropinas, 2, '.', '') . ','
   . number_format($totalEsperado, 2, '.', '') . "\n";

echo 'FONDO,,,'. number_format($fondo, 2, '.', '') . "\n";

echo 'TOTAL_FINAL,,,'. number_format($totalFinal, 2, '.', '') . "\n";
?>
