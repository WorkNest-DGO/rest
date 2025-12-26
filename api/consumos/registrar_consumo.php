<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
$conn->set_charset('utf8mb4');

function respond($success, $mensaje, $data = null) {
    echo json_encode(['success' => $success, 'mensaje' => $mensaje, 'data' => $data]);
    exit;
}

function column_exists(mysqli $db, string $table, string $column): bool {
    $tableSafe = $db->real_escape_string($table);
    $colSafe = $db->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$colSafe}'";
    $res = $db->query($sql);
    if (!$res) return false;
    $ok = $res->num_rows > 0;
    $res->close();
    return $ok;
}

function bind_params(mysqli_stmt $stmt, string $types, array $params): void {
    $bind = [];
    $bind[] = $types;
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    respond(false, 'No autorizado');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    respond(false, 'JSON invalido');
}

$usuarioId = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 0;
$sedeId = isset($input['sede_id']) ? (int)$input['sede_id'] : 0;
$fechaInput = trim((string)($input['fecha_consumo'] ?? ''));
$observacion = trim((string)($input['observacion'] ?? ''));
$items = $input['items'] ?? [];

if ($usuarioId <= 0 || !is_array($items) || !count($items)) {
    respond(false, 'Datos incompletos');
}

$fechaTs = $fechaInput !== '' ? strtotime($fechaInput) : time();
if ($fechaTs === false) {
    respond(false, 'Fecha invalida');
}
$fechaConsumo = date('Y-m-d H:i:s', $fechaTs);
$fechaDia = date('Y-m-d', $fechaTs);
$diaSemana = (int)date('N', $fechaTs);

$hasSede = column_exists($conn, 'consumos_empleado', 'sede_id');
$hasObs = column_exists($conn, 'consumos_empleado', 'observacion');
$hasGratis = column_exists($conn, 'consumos_empleado', 'es_gratis');
$hasMotivo = column_exists($conn, 'consumos_empleado', 'motivo');
$hasDescuento = column_exists($conn, 'consumos_empleado', 'descuento_nomina');
$hasMonto = column_exists($conn, 'consumos_empleado', 'monto_nomina');
$hasFecha = column_exists($conn, 'consumos_empleado', 'fecha_consumo');

if (!$hasDescuento || !$hasMonto || !$hasFecha) {
    respond(false, 'Estructura de consumos_empleado no valida');
}

if ($hasSede && $sedeId <= 0) {
    $stmtSede = $conn->prepare("SELECT sede_id FROM usuarios WHERE id = ? LIMIT 1");
    if (!$stmtSede) {
        respond(false, 'Error al preparar sede');
    }
    $stmtSede->bind_param('i', $usuarioId);
    if ($stmtSede->execute()) {
        $rs = $stmtSede->get_result();
        if ($row = $rs->fetch_assoc()) {
            $sedeId = (int)($row['sede_id'] ?? 0);
        }
    }
    $stmtSede->close();
    if ($sedeId <= 0) {
        respond(false, 'Sede requerida');
    }
}

$colsConsumo = ['usuario_id', 'producto_id', 'cantidad', 'precio_unitario', 'monto_nomina', 'descuento_nomina', 'fecha_consumo'];
$typesConsumo = 'iiiddss';
if ($hasSede) {
    $colsConsumo[] = 'sede_id';
    $typesConsumo .= 'i';
}
if ($hasGratis) {
    $colsConsumo[] = 'es_gratis';
    $typesConsumo .= 'i';
}
if ($hasObs) {
    $colsConsumo[] = 'observacion';
    $typesConsumo .= 's';
}
if ($hasMotivo) {
    $colsConsumo[] = 'motivo';
    $typesConsumo .= 's';
}
$placeholders = implode(',', array_fill(0, count($colsConsumo), '?'));
$sqlInsertConsumo = "INSERT INTO consumos_empleado (" . implode(',', $colsConsumo) . ") VALUES ({$placeholders})";
$stmtInsertConsumo = $conn->prepare($sqlInsertConsumo);
if (!$stmtInsertConsumo) {
    respond(false, 'Error al preparar consumo');
}

$stmtProd = $conn->prepare("SELECT nombre, precio, existencia, activo FROM productos WHERE id = ? LIMIT 1");
$stmtReglaFecha = $conn->prepare("SELECT cantidad_max FROM consumos_beneficios WHERE usuario_id = ? AND producto_id = ? AND activo = 1 AND tipo_regla = 'fecha' AND fecha = ? LIMIT 1");
$stmtReglaSemana = $conn->prepare("SELECT cantidad_max FROM consumos_beneficios WHERE usuario_id = ? AND producto_id = ? AND activo = 1 AND tipo_regla = 'semana' AND dia_semana = ? LIMIT 1");
$stmtSumGratis = $conn->prepare("SELECT COALESCE(SUM(cantidad),0) AS total FROM consumos_empleado WHERE usuario_id = ? AND producto_id = ? AND DATE(fecha_consumo) = ? AND es_gratis = 1");
$stmtUpdProd = $conn->prepare("UPDATE productos SET existencia = existencia - ? WHERE id = ?");
$stmtRecetas = $conn->prepare("SELECT r.insumo_id, r.cantidad, i.nombre, i.unidad FROM recetas r JOIN insumos i ON i.id = r.insumo_id WHERE r.producto_id = ?");
$stmtUpdInsumo = $conn->prepare("UPDATE insumos SET existencia = existencia - ? WHERE id = ?");

if (!$stmtProd || !$stmtReglaFecha || !$stmtReglaSemana || !$stmtSumGratis || !$stmtUpdProd || !$stmtRecetas || !$stmtUpdInsumo) {
    respond(false, 'Error al preparar operaciones');
}

$hasConsumoInsumo = [
    'consumo_empleado_id' => column_exists($conn, 'consumos_empleado_insumos', 'consumo_empleado_id'),
    'insumo_id' => column_exists($conn, 'consumos_empleado_insumos', 'insumo_id'),
    'cantidad' => column_exists($conn, 'consumos_empleado_insumos', 'cantidad'),
    'unidad' => column_exists($conn, 'consumos_empleado_insumos', 'unidad'),
    'nombre_insumo' => column_exists($conn, 'consumos_empleado_insumos', 'nombre_insumo'),
    'producto_id' => column_exists($conn, 'consumos_empleado_insumos', 'producto_id')
];

$colsInsumo = [];
$typesInsumo = '';
if ($hasConsumoInsumo['consumo_empleado_id']) { $colsInsumo[] = 'consumo_empleado_id'; $typesInsumo .= 'i'; }
if ($hasConsumoInsumo['insumo_id']) { $colsInsumo[] = 'insumo_id'; $typesInsumo .= 'i'; }
if ($hasConsumoInsumo['cantidad']) { $colsInsumo[] = 'cantidad'; $typesInsumo .= 'd'; }
if ($hasConsumoInsumo['unidad']) { $colsInsumo[] = 'unidad'; $typesInsumo .= 's'; }
if ($hasConsumoInsumo['nombre_insumo']) { $colsInsumo[] = 'nombre_insumo'; $typesInsumo .= 's'; }
if ($hasConsumoInsumo['producto_id']) { $colsInsumo[] = 'producto_id'; $typesInsumo .= 'i'; }
$stmtInsertInsumo = null;
if (count($colsInsumo) >= 3) {
    $phInsumo = implode(',', array_fill(0, count($colsInsumo), '?'));
    $stmtInsertInsumo = $conn->prepare("INSERT INTO consumos_empleado_insumos (" . implode(',', $colsInsumo) . ") VALUES ({$phInsumo})");
}

$hasMov = [
    'tipo' => column_exists($conn, 'movimientos_insumos', 'tipo'),
    'usuario_id' => column_exists($conn, 'movimientos_insumos', 'usuario_id'),
    'insumo_id' => column_exists($conn, 'movimientos_insumos', 'insumo_id'),
    'cantidad' => column_exists($conn, 'movimientos_insumos', 'cantidad'),
    'observacion' => column_exists($conn, 'movimientos_insumos', 'observacion'),
    'fecha' => column_exists($conn, 'movimientos_insumos', 'fecha'),
    'qr_token' => column_exists($conn, 'movimientos_insumos', 'qr_token'),
    'consumo_empleado_id' => column_exists($conn, 'movimientos_insumos', 'consumo_empleado_id')
];

$colsMov = [];
$typesMov = '';
if ($hasMov['tipo']) { $colsMov[] = 'tipo'; $typesMov .= 's'; }
if ($hasMov['usuario_id']) { $colsMov[] = 'usuario_id'; $typesMov .= 'i'; }
if ($hasMov['insumo_id']) { $colsMov[] = 'insumo_id'; $typesMov .= 'i'; }
if ($hasMov['cantidad']) { $colsMov[] = 'cantidad'; $typesMov .= 'd'; }
if ($hasMov['observacion']) { $colsMov[] = 'observacion'; $typesMov .= 's'; }
if ($hasMov['fecha']) { $colsMov[] = 'fecha'; $typesMov .= 's'; }
if ($hasMov['qr_token']) { $colsMov[] = 'qr_token'; $typesMov .= 's'; }
if ($hasMov['consumo_empleado_id']) { $colsMov[] = 'consumo_empleado_id'; $typesMov .= 'i'; }
$stmtInsertMov = null;
if (count($colsMov) >= 4) {
    $phMov = implode(',', array_fill(0, count($colsMov), '?'));
    $stmtInsertMov = $conn->prepare("INSERT INTO movimientos_insumos (" . implode(',', $colsMov) . ") VALUES ({$phMov})");
}

$resultado = [];

try {
    $conn->begin_transaction();

    foreach ($items as $item) {
        $productoId = isset($item['producto_id']) ? (int)$item['producto_id'] : 0;
        $cantidad = isset($item['cantidad']) ? (int)$item['cantidad'] : 0;
        if ($productoId <= 0 || $cantidad <= 0) {
            throw new Exception('Item invalido');
        }

        $stmtProd->bind_param('i', $productoId);
        if (!$stmtProd->execute()) {
            throw new Exception('Error al consultar producto');
        }
        $prodRes = $stmtProd->get_result();
        $prod = $prodRes ? $prodRes->fetch_assoc() : null;
        if (!$prod || (int)$prod['activo'] !== 1) {
            throw new Exception('Producto no disponible');
        }

        $precio = (float)$prod['precio'];

        $cantidadMax = null;
        $motivo = 'sin_regla';
        $esGratis = 0;

        $stmtReglaFecha->bind_param('iis', $usuarioId, $productoId, $fechaDia);
        if ($stmtReglaFecha->execute()) {
            $regRes = $stmtReglaFecha->get_result();
            if ($row = $regRes->fetch_assoc()) {
                $cantidadMax = (int)$row['cantidad_max'];
                $motivo = 'fecha';
            }
        }
        if ($cantidadMax === null) {
            $stmtReglaSemana->bind_param('iii', $usuarioId, $productoId, $diaSemana);
            if ($stmtReglaSemana->execute()) {
                $regRes = $stmtReglaSemana->get_result();
                if ($row = $regRes->fetch_assoc()) {
                    $cantidadMax = (int)$row['cantidad_max'];
                    $motivo = 'semana';
                }
            }
        }

        if ($cantidadMax !== null) {
            $stmtSumGratis->bind_param('iis', $usuarioId, $productoId, $fechaDia);
            if ($stmtSumGratis->execute()) {
                $sumRes = $stmtSumGratis->get_result();
                $sumRow = $sumRes ? $sumRes->fetch_assoc() : null;
                $consumidos = (int)($sumRow['total'] ?? 0);
                if ($consumidos + $cantidad <= $cantidadMax) {
                    $esGratis = 1;
                } else {
                    $motivo = 'excedio_limite';
                }
            }
        }

        $montoNomina = $esGratis ? 0 : ($precio * $cantidad);
        $descuentoNomina = $esGratis ? 'exento' : 'pendiente';

        $paramsConsumo = [$usuarioId, $productoId, $cantidad, $precio, $montoNomina, $descuentoNomina, $fechaConsumo];
        if ($hasSede) { $paramsConsumo[] = $sedeId; }
        if ($hasGratis) { $paramsConsumo[] = $esGratis; }
        if ($hasObs) { $paramsConsumo[] = $observacion; }
        if ($hasMotivo) { $paramsConsumo[] = $motivo; }

        bind_params($stmtInsertConsumo, $typesConsumo, $paramsConsumo);
        if (!$stmtInsertConsumo->execute()) {
            throw new Exception('Error al insertar consumo');
        }
        $consumoId = $stmtInsertConsumo->insert_id;

        $stmtUpdProd->bind_param('di', $cantidad, $productoId);
        if (!$stmtUpdProd->execute()) {
            throw new Exception('Error al actualizar producto');
        }

        $insumosDetalle = [];
        $stmtRecetas->bind_param('i', $productoId);
        if (!$stmtRecetas->execute()) {
            throw new Exception('Error al consultar recetas');
        }
        $recRes = $stmtRecetas->get_result();
        while ($rec = $recRes->fetch_assoc()) {
            $insumoId = (int)$rec['insumo_id'];
            $cantidadReceta = (float)$rec['cantidad'];
            $cantidadInsumo = $cantidadReceta * $cantidad;

            $stmtUpdInsumo->bind_param('di', $cantidadInsumo, $insumoId);
            if (!$stmtUpdInsumo->execute()) {
                throw new Exception('Error al actualizar insumo');
            }

            if ($stmtInsertInsumo) {
                $paramsInsumo = [];
                if ($hasConsumoInsumo['consumo_empleado_id']) $paramsInsumo[] = $consumoId;
                if ($hasConsumoInsumo['insumo_id']) $paramsInsumo[] = $insumoId;
                if ($hasConsumoInsumo['cantidad']) $paramsInsumo[] = $cantidadInsumo;
                if ($hasConsumoInsumo['unidad']) $paramsInsumo[] = $rec['unidad'];
                if ($hasConsumoInsumo['nombre_insumo']) $paramsInsumo[] = $rec['nombre'];
                if ($hasConsumoInsumo['producto_id']) $paramsInsumo[] = $productoId;
                bind_params($stmtInsertInsumo, $typesInsumo, $paramsInsumo);
                if (!$stmtInsertInsumo->execute()) {
                    throw new Exception('Error al insertar insumo');
                }
            }

            if ($stmtInsertMov) {
                $paramsMov = [];
                if ($hasMov['tipo']) $paramsMov[] = 'salida';
                if ($hasMov['usuario_id']) $paramsMov[] = (int)$_SESSION['usuario_id'];
                if ($hasMov['insumo_id']) $paramsMov[] = $insumoId;
                if ($hasMov['cantidad']) $paramsMov[] = $cantidadInsumo;
                if ($hasMov['observacion']) $paramsMov[] = 'Consumo empleado #' . $consumoId;
                if ($hasMov['fecha']) $paramsMov[] = $fechaConsumo;
                if ($hasMov['qr_token']) $paramsMov[] = null;
                if ($hasMov['consumo_empleado_id']) $paramsMov[] = $consumoId;
                bind_params($stmtInsertMov, $typesMov, $paramsMov);
                if (!$stmtInsertMov->execute()) {
                    throw new Exception('Error al insertar movimiento');
                }
            }

            $insumosDetalle[] = [
                'insumo_id' => $insumoId,
                'cantidad' => $cantidadInsumo,
                'unidad' => $rec['unidad'],
                'nombre' => $rec['nombre']
            ];
        }

        $resultado[] = [
            'consumo_id' => $consumoId,
            'producto_id' => $productoId,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'es_gratis' => $esGratis,
            'monto_nomina' => round($montoNomina, 2),
            'motivo' => $motivo,
            'insumos' => $insumosDetalle
        ];
    }

    $conn->commit();
    respond(true, 'Consumo registrado', ['items' => $resultado]);
} catch (Exception $e) {
    $conn->rollback();
    respond(false, $e->getMessage());
}
