<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Permite opcionalmente recibir ?corte_id=, por defecto usa el de sesión o fecha actual
$corte_id = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : (isset($_SESSION['corte_id']) ? (int)$_SESSION['corte_id'] : null);

if ($corte_id) {
    $sql = "SELECT 
                v.usuario_id,
                COALESCE(u.nombre, CONCAT('Usuario ', v.usuario_id)) AS usuario,
                SUM(COALESCE(v.propina_efectivo,0)) AS propina_efectivo,
                SUM(COALESCE(v.propina_cheque,0))   AS propina_cheque,
                SUM(COALESCE(v.propina_tarjeta,0))  AS propina_tarjeta,
                SUM(COALESCE(v.propina_efectivo,0)+COALESCE(v.propina_cheque,0)+COALESCE(v.propina_tarjeta,0)) AS total_propinas
            FROM ventas v
            LEFT JOIN usuarios u ON u.id = v.usuario_id
            WHERE v.estatus = 'cerrada'
              AND v.corte_id = ?
            GROUP BY v.usuario_id, u.nombre
            HAVING SUM(COALESCE(v.propina_efectivo,0)+COALESCE(v.propina_cheque,0)+COALESCE(v.propina_tarjeta,0)) > 0
            ORDER BY total_propinas DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error('Error al preparar consulta: ' . $conn->error); }
    $stmt->bind_param('i', $corte_id);
} else {
    // Fallback: día actual si no hay corte en sesión
    $sql = "SELECT 
                v.usuario_id,
                COALESCE(u.nombre, CONCAT('Usuario ', v.usuario_id)) AS usuario,
                SUM(COALESCE(v.propina_efectivo,0)) AS propina_efectivo,
                SUM(COALESCE(v.propina_cheque,0))   AS propina_cheque,
                SUM(COALESCE(v.propina_tarjeta,0))  AS propina_tarjeta,
                SUM(COALESCE(v.propina_efectivo,0)+COALESCE(v.propina_cheque,0)+COALESCE(v.propina_tarjeta,0)) AS total_propinas
            FROM ventas v
            LEFT JOIN usuarios u ON u.id = v.usuario_id
            WHERE v.estatus = 'cerrada'
              AND DATE(v.fecha) = CURDATE()
            GROUP BY v.usuario_id, u.nombre
            HAVING SUM(COALESCE(v.propina_efectivo,0)+COALESCE(v.propina_cheque,0)+COALESCE(v.propina_tarjeta,0)) > 0
            ORDER BY total_propinas DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error('Error al preparar consulta: ' . $conn->error); }
}

if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar consulta: ' . $stmt->error);
}
$res = $stmt->get_result();
$rows = [];
$totEf = 0; $totCh = 0; $totTa = 0; $tot = 0;
while ($r = $res->fetch_assoc()) {
    $ef = (float)($r['propina_efectivo'] ?? 0);
    $ch = (float)($r['propina_cheque'] ?? 0);
    $ta = (float)($r['propina_tarjeta'] ?? 0);
    $tt = (float)($r['total_propinas'] ?? ($ef + $ch + $ta));
    $rows[] = [
        'usuario_id' => (int)$r['usuario_id'],
        'usuario'    => $r['usuario'],
        'propina_efectivo' => $ef,
        'propina_cheque'   => $ch,
        'propina_tarjeta'  => $ta,
        'total'            => $tt,
    ];
    $totEf += $ef; $totCh += $ch; $totTa += $ta; $tot += $tt;
}
$stmt->close();

success([
    'corte_id' => $corte_id,
    'totales'  => [
        'efectivo' => $totEf,
        'cheque'   => $totCh,
        'tarjeta'  => $totTa,
        'total'    => $tot
    ],
    'detalle'  => $rows
]);
?>
