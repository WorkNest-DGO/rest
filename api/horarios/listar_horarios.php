<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT h.id, h.dia_semana, h.hora_inicio, h.hora_fin, h.serie_id, c.descripcion AS serie
          FROM horarios h
          JOIN catalogo_folios c ON h.serie_id = c.id
          ORDER BY FIELD(h.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'), h.hora_inicio";
$result = $conn->query($query);
if (!$result) {
    error('Error al obtener horarios: ' . $conn->error);
}
$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['serie_id'] = (int)$row['serie_id'];
    $rows[] = $row;
}
success($rows);
?>
