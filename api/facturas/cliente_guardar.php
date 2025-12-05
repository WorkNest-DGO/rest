<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

function json_out(bool $ok, $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($ok ? ['success' => true, 'resultado' => $payload] : ['success' => false, 'mensaje' => (string)$payload], JSON_UNESCAPED_UNICODE);
    exit;
}
function table_exists(mysqli $db, string $t): bool {
    $q = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $q->bind_param('s', $t); $q->execute(); $q->store_result();
    $ok = $q->num_rows > 0; $q->close(); return $ok;
}
function column_exists(mysqli $db, string $t, string $c): bool {
    $q = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $q->bind_param('ss',$t,$c); $q->execute(); $q->store_result();
    $ok = $q->num_rows > 0; $q->close(); return $ok;
}

try {
    $db = get_db();
    $db->set_charset('utf8mb4');
    if (!table_exists($db, 'clientes_facturacion')) {
        json_out(false, 'Tabla clientes_facturacion no existe', 404);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;

    $accion = strtolower(trim((string)($data['accion'] ?? 'crear')));
    $clienteId = isset($data['id']) ? (int)$data['id'] : 0;
    if (!in_array($accion, ['crear','editar'], true)) {
        json_out(false, 'Acci�n inv�lida', 422);
    }
    if ($accion === 'editar' && $clienteId <= 0) {
        json_out(false, 'id requerido para editar', 422);
    }

    $camposPermitidos = [
        'rfc','razon_social','correo','telefono','calle','numero_ext','numero_int',
        'colonia','municipio','estado','pais','cp','regimen','uso_cfdi'
    ];
    $valores = [];
    foreach ($camposPermitidos as $c) {
        $valores[$c] = isset($data[$c]) ? trim((string)$data[$c]) : null;
    }

    $colsDisponibles = array_filter($camposPermitidos, fn($c) => column_exists($db, 'clientes_facturacion', $c));

    if ($accion === 'crear') {
        $cols = [];
        $marks = [];
        $types = '';
        $params = [];
        foreach ($colsDisponibles as $c) {
            $cols[] = $c;
            $marks[] = '?';
            $types .= 's';
            $params[] = $valores[$c];
        }
        if (empty($cols)) json_out(false, 'No hay columnas para guardar', 400);
        $sql = "INSERT INTO clientes_facturacion (" . implode(',', $cols) . ") VALUES (" . implode(',', $marks) . ")";
        $st = $db->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        if ($st->errno === 1062 || $db->errno === 1062) {
            $msgDup = 'Registro duplicado (RFC/correo ya existe)';
            $st->close();
            json_out(false, $msgDup, 409);
        }
        $newId = $st->insert_id;
        $st->close();
        json_out(true, ['id' => $newId]);
    } else {
        $sets = [];
        $types = '';
        $params = [];
        foreach ($colsDisponibles as $c) {
            $sets[] = "$c = ?";
            $types .= 's';
            $params[] = $valores[$c];
        }
        if (empty($sets)) json_out(false, 'No hay columnas para actualizar', 400);
        $types .= 'i';
        $params[] = $clienteId;
        $sql = "UPDATE clientes_facturacion SET " . implode(',', $sets) . " WHERE id = ? LIMIT 1";
        $st = $db->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        if ($st->errno === 1062 || $db->errno === 1062) {
            $msgDup = 'Registro duplicado (RFC/correo ya existe)';
            $st->close();
            json_out(false, $msgDup, 409);
        }
        $st->close();
        json_out(true, ['id' => $clienteId]);
    }
} catch (Throwable $e) {
    json_out(false, 'Error: ' . $e->getMessage(), 500);
}
