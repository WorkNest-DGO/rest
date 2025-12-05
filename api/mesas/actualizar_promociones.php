<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Metodo no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'])) {
    error('Datos invalidos');
}

$venta_id = (int)$input['venta_id'];
$promociones_ids = [];
if (isset($input['promociones_ids']) && is_array($input['promociones_ids'])) {
    foreach ($input['promociones_ids'] as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
            $promociones_ids[] = $pid;
        }
    }
    $promociones_ids = array_values(array_unique($promociones_ids));
}

$promocion_id = isset($input['promocion_id']) ? (int)$input['promocion_id'] : null;
if ($promocion_id === 0) {
    $promocion_id = null;
}
if ($promocion_id === null && count($promociones_ids) > 0) {
    $promocion_id = $promociones_ids[0];
}

try {
    $conn->begin_transaction();

    $upd = $conn->prepare('UPDATE ventas SET promocion_id = ?, promocion_descuento = COALESCE(promocion_descuento, 0) WHERE id = ?');
    if (!$upd) {
        throw new RuntimeException('No se pudo preparar actualizacion: ' . $conn->error);
    }
    $upd->bind_param('ii', $promocion_id, $venta_id);
    if (!$upd->execute()) {
        $upd->close();
        throw new RuntimeException('No se pudo actualizar la venta: ' . $upd->error);
    }
    $upd->close();

    $del = $conn->prepare('DELETE FROM venta_promos WHERE venta_id = ?');
    if (!$del) {
        throw new RuntimeException('No se pudo preparar eliminacion: ' . $conn->error);
    }
    $del->bind_param('i', $venta_id);
    if (!$del->execute()) {
        $del->close();
        throw new RuntimeException('No se pudo limpiar promociones previas: ' . $del->error);
    }
    $del->close();

    if (count($promociones_ids) > 0) {
        $ins = $conn->prepare('INSERT INTO venta_promos (venta_id, promo_id, descuento_aplicado, created_at) VALUES (?, ?, NULL, NOW())');
        if (!$ins) {
            throw new RuntimeException('No se pudo preparar insercion: ' . $conn->error);
        }
        $ventaRef = $venta_id;
        $promoRef = 0;
        $ins->bind_param('ii', $ventaRef, $promoRef);
        foreach ($promociones_ids as $pid) {
            $promoRef = (int)$pid;
            if ($promoRef <= 0) {
                continue;
            }
            if (!$ins->execute()) {
                $ins->close();
                throw new RuntimeException('No se pudo insertar promoción: ' . $ins->error);
            }
        }
        $ins->close();
    }

    $conn->commit();
    success([
        'venta_id' => $venta_id,
        'promocion_id' => $promocion_id,
        'promociones_ids' => $promociones_ids
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error('No se pudieron guardar las promociones: ' . $e->getMessage());
}




