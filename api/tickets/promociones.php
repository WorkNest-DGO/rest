<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

/**
 * Devuelve el catálogo de promociones.
 * Además de los campos básicos, expone:
 *  - monto, tipo_venta
 *  - productos_regla: ids y nombres de productos referenciados en la regla
 */

$promociones = [];
// Exponer campos adicionales (monto y tipo_venta) para reglas más flexibles
$resPromociones = $conn->query("SELECT id, nombre, regla, tipo, monto, tipo_venta FROM catalogo_promos ORDER BY id ASC");
if (!$resPromociones) {
    error('Error al obtener promociones: ' . $conn->error);
}

while ($row = $resPromociones->fetch_assoc()) {
    $productosRegla = [];
    $reglaRaw = $row['regla'];
    $reglaJson = json_decode($reglaRaw, true);

    $idsProductos = [];
    if (is_array($reglaJson)) {
        // Puede ser un objeto o un arreglo de objetos
        $esAsociativo = array_keys($reglaJson) !== range(0, count($reglaJson) - 1);
        if ($esAsociativo) {
            if (isset($reglaJson['id_producto'])) {
                $idsProductos[] = (int)$reglaJson['id_producto'];
            }
        } else {
            foreach ($reglaJson as $r) {
                if (is_array($r) && isset($r['id_producto'])) {
                    $idsProductos[] = (int)$r['id_producto'];
                }
            }
        }
    }

    $idsProductos = array_values(array_unique(array_filter($idsProductos)));

    if (!empty($idsProductos)) {
        $idsList = implode(',', array_map('intval', $idsProductos));
        $resProd = $conn->query("SELECT id, nombre FROM productos WHERE id IN ($idsList)");
        if ($resProd) {
            while ($p = $resProd->fetch_assoc()) {
                $productosRegla[] = [
                    'id'     => (int)$p['id'],
                    'nombre' => $p['nombre'],
                ];
            }
        }
    }

    $promociones[] = [
        'id'              => (int)$row['id'],
        'nombre'          => $row['nombre'],
        'regla'           => $row['regla'],
        'tipo'            => $row['tipo'],
        'monto'           => isset($row['monto']) ? (float)$row['monto'] : 0.0,
        'tipo_venta'      => $row['tipo_venta'] ?? null,
        'productos_regla' => $productosRegla,
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'success'     => true,
    'promociones' => $promociones,
]);

