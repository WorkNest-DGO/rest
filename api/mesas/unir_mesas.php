<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['principal_id']) || !isset($input['mesas']) || !is_array($input['mesas'])) {
    error('Datos inválidos');
}

$principal_id = (int)$input['principal_id'];
$mesas = array_filter(array_map('intval', $input['mesas']));
if (empty($mesas)) {
    error('No hay mesas para unir');
}

$stmt = $conn->prepare('UPDATE mesas SET mesa_principal_id = ? WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}

foreach ($mesas as $mesa_id) {
    $stmt->bind_param('ii', $principal_id, $mesa_id);
    if (!$stmt->execute()) {
        $stmt->close();
        error('Error al unir mesa: ' . $stmt->error);
    }
}
$stmt->close();

success(true);
?>
