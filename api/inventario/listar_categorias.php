<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Metodo no permitido');
}

$categorias = [];
if ($res = $conn->query('SELECT id, nombre FROM catalogo_categorias ORDER BY nombre ASC')) {
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)($row['id'] ?? 0);
        $row['nombre'] = $row['nombre'] ?? '';
        $categorias[] = $row;
    }
    $res->close();
}

success(['categorias' => $categorias]);
