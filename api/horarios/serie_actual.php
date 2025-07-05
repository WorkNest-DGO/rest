<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$dias = ['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'];
$dia = $dias[(int)date('N') - 1];
$hora = date('H:i:s');

$stmt = $conn->prepare('SELECT h.serie_id, c.descripcion FROM horarios h JOIN catalogo_folios c ON h.serie_id=c.id WHERE h.dia_semana=? AND ? BETWEEN h.hora_inicio AND h.hora_fin LIMIT 1');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('ss', $dia, $hora);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al obtener serie: ' . $stmt->error);
}
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    success(['id' => (int)$row['serie_id'], 'descripcion' => $row['descripcion']]);
}
$stmt->close();
error('No hay serie configurada para este horario');
?>
