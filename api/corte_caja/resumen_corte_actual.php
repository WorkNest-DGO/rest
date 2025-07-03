<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$usuario_id = null;
if (isset($_GET['usuario_id'])) {
    $usuario_id = (int)$_GET['usuario_id'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['usuario_id'])) {
        $usuario_id = (int)$input['usuario_id'];
    }
}
if (!$usuario_id) {
    error('usuario_id requerido');
}

$stmt = $conn->prepare('SELECT id, fecha_inicio FROM corte_caja WHERE usuario_id = ? AND fecha_fin IS NULL');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
if (!($row = $res->fetch_assoc())) {
    $stmt->close();
    success(['abierto' => false]);
}
$corte_id = (int)$row['id'];
$fecha_inicio = $row['fecha_inicio'];
$stmt->close();

$queryTotal = $conn->prepare('SELECT SUM(t.total) AS total, SUM(t.propina) AS propinas, COUNT(DISTINCT v.id) AS num_ventas
                              FROM ventas v
                              JOIN tickets t ON t.venta_id = v.id
                              WHERE v.usuario_id = ? AND v.estatus = "cerrada" AND v.fecha >= ?');
if (!$queryTotal) {
    error('Error al preparar resumen: ' . $conn->error);
}
$queryTotal->bind_param('is', $usuario_id, $fecha_inicio);
$queryTotal->execute();
$info = $queryTotal->get_result()->fetch_assoc();
$queryTotal->close();

// Obtener montos por método de pago si existe la columna
$metodos = [];
$check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'metodo_pago'");
if ($check && $check->num_rows > 0) {
    $metQuery = $conn->prepare('SELECT t.metodo_pago, SUM(t.total) AS total
                                FROM tickets t
                                JOIN ventas v ON v.id = t.venta_id
                                WHERE v.usuario_id = ? AND v.estatus = "cerrada" AND v.fecha >= ?
                                GROUP BY t.metodo_pago');
    if ($metQuery) {
        $metQuery->bind_param('is', $usuario_id, $fecha_inicio);
        if ($metQuery->execute()) {
            $resMet = $metQuery->get_result();
            while ($row = $resMet->fetch_assoc()) {
                $metodos[] = [
                    'metodo' => $row['metodo_pago'],
                    'total'  => (float)($row['total'] ?? 0)
                ];
            }
        }
        $metQuery->close();
    }
}

$resultado = [
    'abierto'     => true,
    'corte_id'    => $corte_id,
    'total'       => (float)($info['total'] ?? 0),
    'num_ventas'  => (int)($info['num_ventas'] ?? 0),
    'propinas'    => (float)($info['propinas'] ?? 0),
    'metodos_pago'=> $metodos
];

success($resultado);
?>
