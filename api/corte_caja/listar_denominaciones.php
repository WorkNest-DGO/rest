<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    $res = $conn->query('SELECT id, valor, descripcion FROM catalogo_denominaciones ORDER BY valor ASC');
    if (!$res) {
        error('Error al obtener denominaciones: ' . $conn->error);
    }
    $denoms = [];
    while ($row = $res->fetch_assoc()) {
        $denoms[] = [
            'id' => (int)$row['id'],
            'valor' => (float)$row['valor'],
            'descripcion' => $row['descripcion']
        ];
    }
    success($denoms);
} catch (Throwable $e) {
    error('Error al obtener denominaciones: ' . $e->getMessage());
}
?>
