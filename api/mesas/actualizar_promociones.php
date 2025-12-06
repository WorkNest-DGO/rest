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
$descuento_total_input = array_key_exists('descuento_total', $input) ? (float)$input['descuento_total'] : null;
$promosDetalle = [];
if (isset($input['promociones_detalle']) && is_array($input['promociones_detalle'])) {
    foreach ($input['promociones_detalle'] as $det) {
        if (!is_array($det)) {
            continue;
        }
        $pid = isset($det['promo_id']) ? (int)$det['promo_id'] : 0;
        $desc = isset($det['descuento_aplicado']) ? (float)$det['descuento_aplicado'] : 0.0;
        if ($pid > 0) {
            $promosDetalle[] = ['promo_id' => $pid, 'descuento_aplicado' => max(0.0, $desc)];
        }
    }
}

try {
    $montosPromos = [];
    $totalDescuento = 0.0;
    $catalogMontos = [];
    if (count($promociones_ids) > 0) {
        $place = implode(',', array_fill(0, count($promociones_ids), '?'));
        $typesP = str_repeat('i', count($promociones_ids));
        $sqlPromo = "SELECT id, COALESCE(monto,0) AS monto FROM catalogo_promos WHERE id IN ($place)";
        $stmtP = $conn->prepare($sqlPromo);
        if (!$stmtP) {
            throw new RuntimeException('No se pudo preparar validacion de promociones: ' . $conn->error);
        }
        $stmtP->bind_param($typesP, ...$promociones_ids);
        if (!$stmtP->execute()) {
            $stmtP->close();
            throw new RuntimeException('No se pudieron validar promociones: ' . $stmtP->error);
        }
        $resP = $stmtP->get_result();
        while ($rowP = $resP->fetch_assoc()) {
            $pid = (int)($rowP['id'] ?? 0);
            $monto = (float)($rowP['monto'] ?? 0);
            $catalogMontos[$pid] = $monto;
        }
        $stmtP->close();
        if (count($catalogMontos) !== count($promociones_ids)) {
            throw new RuntimeException('Alguna promocion seleccionada no existe');
        }
    }

    // Preferir el descuento calculado en el frontend (detallado o total)
    if (count($promosDetalle) > 0) {
        foreach ($promosDetalle as $det) {
            if (!in_array($det['promo_id'], $promociones_ids, true)) {
                continue;
            }
            $montosPromos[$det['promo_id']] = max(0.0, (float)$det['descuento_aplicado']);
        }
        $totalDescuento = array_sum($montosPromos);
    }

    if (empty($montosPromos) && $descuento_total_input !== null) {
        $totalDescuento = max(0.0, round((float)$descuento_total_input, 2));
        if ($totalDescuento > 0 && count($promociones_ids) > 0) {
            $perPromo = round($totalDescuento / count($promociones_ids), 2);
            $acumulado = 0.0;
            foreach ($promociones_ids as $idx => $pid) {
                $promoMonto = ($idx === count($promociones_ids) - 1)
                    ? round($totalDescuento - $acumulado, 2)
                    : $perPromo;
                $acumulado += ($idx === count($promociones_ids) - 1) ? 0 : $perPromo;
                $montosPromos[$pid] = max(0.0, $promoMonto);
            }
        }
    }

    if (empty($montosPromos) && !empty($catalogMontos)) {
        $montosPromos = $catalogMontos;
    }
    if ($totalDescuento <= 0) {
        $totalDescuento = array_sum($montosPromos);
    }

    $conn->begin_transaction();

    $upd = $conn->prepare('UPDATE ventas SET promocion_id = ?, promocion_descuento = ? WHERE id = ?');
    if (!$upd) {
        throw new RuntimeException('No se pudo preparar actualizacion: ' . $conn->error);
    }
    $upd->bind_param('idi', $promocion_id, $totalDescuento, $venta_id);
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
        $ins = $conn->prepare('INSERT INTO venta_promos (venta_id, promo_id, descuento_aplicado, created_at) VALUES (?, ?, ?, NOW())');
        if (!$ins) {
            throw new RuntimeException('No se pudo preparar insercion: ' . $conn->error);
        }
        $ventaRef = $venta_id;
        $promoRef = 0;
        $descRef = 0.0;
        $ins->bind_param('iid', $ventaRef, $promoRef, $descRef);
        foreach ($promociones_ids as $pid) {
            $promoRef = (int)$pid;
            if ($promoRef <= 0) {
                continue;
            }
            $descRef = isset($montosPromos[$promoRef]) ? (float)$montosPromos[$promoRef] : 0.0;
            if (!$ins->execute()) {
                $ins->close();
                throw new RuntimeException('No se pudo insertar promocion: ' . $ins->error);
            }
        }
        $ins->close();
    }

    $conn->commit();
    success([
        'venta_id' => $venta_id,
        'promocion_id' => $promocion_id,
        'promociones_ids' => $promociones_ids,
        'total_descuento' => $totalDescuento
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error('No se pudieron guardar las promociones: ' . $e->getMessage());
}

