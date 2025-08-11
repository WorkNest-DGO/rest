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
    // Usa tu zona horaria real
    date_default_timezone_set('America/Monterrey');

    // Día en minúsculas, sin acentos (como están en tu tabla)
    $dias = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
    $dia  = $dias[(int)date('N') - 1];
    $hora = date('H:i:s');

    // Si no encuentra nada, por defecto la serie 2 (Domicilio)
    $serie_id = 2;

    // Nada de "\" y sin COLLATE sobre el parámetro
    $sql = "SELECT serie_id
            FROM horarios
            WHERE hora_inicio <= ? AND ? <= hora_fin
              AND LOWER(dia_semana) = LOWER(?)
            ORDER BY id DESC
            LIMIT 1";

    if ($stmt = $conn->prepare($sql)) {
        // Misma hora para ambos placeholders
        $stmt->bind_param('sss', $hora, $hora, $dia);
        $stmt->execute();
        if ($res = $stmt->get_result()) {
            if ($row = $res->fetch_assoc()) {
                $serie_id = (int)$row['serie_id'];
            }
        }
        $stmt->close();
    } else {
        // Debug opcional si vuelve a fallar el prepare:
        // error_log("getSerieActiva prepare error: " . $conn->error);
        // error_log("SQL: " . $sql);
    }

    return $serie_id;
}

function getFolioActualSerie(mysqli $conn, int $serie_id): int
{
    $folio = 0;
    if ($stmt = $conn->prepare("SELECT IFNULL(folio_actual,0) AS folio_actual FROM catalogo_folios WHERE id = ?")) {
        $stmt->bind_param('i', $serie_id);
        $stmt->execute();
        if ($res = $stmt->get_result()) {
            if ($row = $res->fetch_assoc()) {
                $folio = (int)$row['folio_actual'];
            }
        }
        $stmt->close();
    }
    return $folio;
}


?>

