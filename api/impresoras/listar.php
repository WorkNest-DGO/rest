<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $rows = [];
    $res = $conn->query('SELECT print_id, lugar, ip FROM impresoras ORDER BY print_id ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) $rows[] = $row;
    }
    echo json_encode(['success' => true, 'resultado' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'No se pudieron cargar impresoras', 'error' => $e->getMessage()]);
}
