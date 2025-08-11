<?php
/**
 * Helpers para manejo de series y folios de tickets.
 */

/**
 * Obtiene la serie activa según el día y la hora actuales.
 *
 * La comparación de día con horarios.dia_semana es
 * insensible a mayúsculas/minúsculas y acentos. Si no se
 * encuentra coincidencia se regresa la serie con ID 2.
 */
function getSerieActiva(mysqli $conn): int
{
    date_default_timezone_set('America/Mexico_City');

    $dias = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
    $dia  = $dias[(int)date('N') - 1];
    $hora = date('H:i:s');

    $serie_id = 2; // Fallback

    $sql = "SELECT serie_id FROM horarios \
            WHERE ? BETWEEN hora_inicio AND hora_fin \
              AND dia_semana COLLATE utf8mb4_general_ci = ? COLLATE utf8mb4_general_ci \
            LIMIT 1";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $hora, $dia);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $serie_id = (int)$row['serie_id'];
            }
        }
        $stmt->close();
    }

    return $serie_id;
}

/**
 * Obtiene el folio_actual para la serie dada.
 */
function getFolioActualSerie(mysqli $conn, int $serie_id): int
{
    $folio = 0;
    if ($stmt = $conn->prepare('SELECT folio_actual FROM catalogo_folios WHERE id = ?')) {
        $stmt->bind_param('i', $serie_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $folio = (int)$row['folio_actual'];
            }
        }
        $stmt->close();
    }
    return $folio;
}

?>

