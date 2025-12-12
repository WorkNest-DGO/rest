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
    // La columna serie_id ya no existe en horarios; devolvemos una serie válida existente.
    $serie_id = 0;
    if ($stmt = $conn->prepare("SELECT id FROM catalogo_folios ORDER BY id ASC LIMIT 1")) {
        $stmt->execute();
        if ($res = $stmt->get_result()) {
            if ($row = $res->fetch_assoc()) {
                $serie_id = (int)$row['id'];
            }
        }
        $stmt->close();
    }
    if ($serie_id <= 0) {
        $serie_id = 1;
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

