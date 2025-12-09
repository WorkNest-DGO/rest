<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error('JSON inválido');
}

$nombre = trim($input['nombre'] ?? '');
$colonia_id = isset($input['colonia_id']) ? (int)$input['colonia_id'] : null;
$telefono = $input['telefono'] ?? null;
$calle = $input['calle'] ?? null;
$numero_ext = $input['numero_exterior'] ?? null;
$entre1 = $input['entre_calle_1'] ?? null;
$entre2 = $input['entre_calle_2'] ?? null;
$referencias = $input['referencias'] ?? null;
$costo_fore = isset($input['costo_fore']) ? $input['costo_fore'] : null;
$costo_madero = isset($input['costo_madero']) ? $input['costo_madero'] : null;

if ($nombre === '' || !$colonia_id) {
    error('Nombre y colonia son obligatorios');
}

$colonia_nombre = null;
$municipio = null;
$coloniaStmt = $conn->prepare('SELECT colonia FROM colonias WHERE id = ?');
if ($coloniaStmt) {
    $coloniaStmt->bind_param('i', $colonia_id);
    $coloniaStmt->execute();
    $res = $coloniaStmt->get_result();
    $coloniaRow = $res->fetch_assoc();
    $coloniaStmt->close();
    if (!$coloniaRow) {
        error('Colonia no encontrada');
    }
    $colonia_nombre = $coloniaRow['colonia'];
}

$stmt = $conn->prepare('INSERT INTO clientes (colonia_id, `Nombre del Cliente`, `Telefono`, `Calle`, `Numero Exterior`, `Colonia`, `Delegacion/Municipio`, `Entre Calle 1`, `Entre Calle 2`, `Referencias`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    error('No se pudo preparar inserción de cliente: ' . $conn->error);
}

$stmt->bind_param(
    'isssssssss',
    $colonia_id,
    $nombre,
    $telefono,
    $calle,
    $numero_ext,
    $colonia_nombre,
    $municipio,
    $entre1,
    $entre2,
    $referencias
);

if (!$stmt->execute()) {
    $stmt->close();
    error('No se pudo crear el cliente: ' . $stmt->error);
}

$nuevoId = $stmt->insert_id;
$stmt->close();

if ($colonia_id) {
    if ($costo_fore !== null) {
        $costo = (float)$costo_fore;
        $upd = $conn->prepare('UPDATE colonias SET costo_fore = ? WHERE id = ?');
        if ($upd) {
            $upd->bind_param('di', $costo, $colonia_id);
            $upd->execute();
            $upd->close();
        }
    }
    if ($costo_madero !== null) {
        $costoMad = (float)$costo_madero;
        $updMad = $conn->prepare('UPDATE colonias SET costo_madero = ? WHERE id = ?');
        if ($updMad) {
            $updMad->bind_param('di', $costoMad, $colonia_id);
            $updMad->execute();
            $updMad->close();
        }
    }
}

// Devolver el cliente recién creado con datos de colonia
$select = $conn->prepare('SELECT c.id, c.colonia_id, c.`Nombre del Cliente` AS nombre, c.`Telefono` AS telefono, c.`Calle` AS calle, c.`Numero Exterior` AS numero_exterior, c.`Colonia` AS colonia_texto, c.`Delegacion/Municipio` AS municipio, c.`Entre Calle 1` AS entre_calle_1, c.`Entre Calle 2` AS entre_calle_2, c.`Referencias` AS referencias, col.colonia AS colonia_nombre, col.dist_km_la_forestal, col.costo_fore, col.costo_madero FROM clientes c LEFT JOIN colonias col ON col.id = c.colonia_id WHERE c.id = ?');
if ($select) {
    $select->bind_param('i', $nuevoId);
    $select->execute();
    $res = $select->get_result();
    $row = $res->fetch_assoc();
    $select->close();
    if ($row) {
        $cliente = [
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
            'costo_fore' => $row['costo_fore'] !== null ? (float)$row['costo_fore'] : null,
            'costo_madero' => $row['costo_madero'] !== null ? (float)$row['costo_madero'] : null,
        ];
        success($cliente);
    }
}

success(['id' => $nuevoId]);
