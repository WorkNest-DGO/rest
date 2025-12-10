<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$sql = "SELECT c.id, c.colonia_id, c.`Nombre del Cliente` AS nombre, c.`Telefono` AS telefono,
               c.`Calle` AS calle, c.`Numero Exterior` AS numero_exterior, c.`Colonia` AS colonia_texto,
               c.`Delegacion/Municipio` AS municipio, c.`Entre Calle 1` AS entre_calle_1,
               c.`Entre Calle 2` AS entre_calle_2, c.`Referencias` AS referencias,
               col.colonia AS colonia_nombre, col.dist_km_la_forestal, col.dist_km_el_naranjal,
               col.costo_fore, col.costo_madero
        FROM clientes c
        LEFT JOIN colonias col ON col.id = c.colonia_id
        ORDER BY nombre ASC";

$result = $conn->query($sql);
if (!$result) {
    error('Error al obtener clientes: ' . $conn->error);
}

$clientes = [];
while ($row = $result->fetch_assoc()) {
    $clientes[] = [
        'id' => (int)$row['id'],
        'colonia_id' => $row['colonia_id'] !== null ? (int)$row['colonia_id'] : null,
        'nombre' => $row['nombre'],
        'telefono' => $row['telefono'],
        'calle' => $row['calle'],
        'numero_exterior' => $row['numero_exterior'],
        'colonia_texto' => $row['colonia_texto'],
        'municipio' => $row['municipio'],
        'entre_calle_1' => $row['entre_calle_1'],
        'entre_calle_2' => $row['entre_calle_2'],
        'referencias' => $row['referencias'],
        'colonia_nombre' => $row['colonia_nombre'],
        'dist_km_la_forestal' => $row['dist_km_la_forestal'] !== null ? (float)$row['dist_km_la_forestal'] : null,
        'dist_km_el_naranjal' => $row['dist_km_el_naranjal'] !== null ? (float)$row['dist_km_el_naranjal'] : null,
        'costo_fore' => $row['costo_fore'] !== null ? (float)$row['costo_fore'] : null,
        'costo_madero' => $row['costo_madero'] !== null ? (float)$row['costo_madero'] : null,
    ];
}

success($clientes);
