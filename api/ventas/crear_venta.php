<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Config de envÃ­o automÃ¡tico (Repartidor casa)
if (!defined('ENVIO_CASA_PRODUCT_ID')) define('ENVIO_CASA_PRODUCT_ID', 9001);
if (!defined('ENVIO_CASA_NOMBRE'))    define('ENVIO_CASA_NOMBRE', 'ENVÃO â€“ Repartidor casa');
if (!defined('ENVIO_CASA_DEFAULT_PRECIO')) define('ENVIO_CASA_DEFAULT_PRECIO', 30.00);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('MÃ©todo no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error('JSON invÃ¡lido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('SesiÃ³n no iniciada');
}

$cajero_id = (int)$_SESSION['usuario_id'];

$tipo          = isset($input['tipo']) ? $input['tipo'] : null;
$mesa_id       = isset($input['mesa_id']) ? (int) $input['mesa_id'] : null;
$repartidor_id = isset($input['repartidor_id']) ? (int) $input['repartidor_id'] : null;
$usuario_id    = isset($input['usuario_id']) ? (int) $input['usuario_id'] : null;
$corte_id      = isset($input['corte_id']) ? (int) $input['corte_id'] : null;
$productos     = isset($input['productos']) && is_array($input['productos']) ? $input['productos'] : null;
$observacion   = isset($input['observacion']) ? $input['observacion'] : null;
$sede_id       = isset($input['sede_id']) && !empty($input['sede_id']) ? (int)$input['sede_id'] : 1;
// Promoci&oacute;n seleccionada (opcional)
$promocion_id  = isset($input['promocion_id']) ? (int)$input['promocion_id'] : null;
if ($promocion_id !== null && $promocion_id <= 0) {
    $promocion_id = null;
}
// De momento el descuento por promoci&oacute;n se calcular&aacute; al cerrar el ticket
$promocion_descuento = 0.0;
// Propinas opcionales (si no llegan, 0 por defecto)
$propina_efectivo = isset($input['propina_efectivo']) ? (float)$input['propina_efectivo'] : 0.0;
$propina_cheque   = isset($input['propina_cheque'])   ? (float)$input['propina_cheque']   : 0.0;
$propina_tarjeta  = isset($input['propina_tarjeta'])  ? (float)$input['propina_tarjeta']  : 0.0;
// Precio de envÃ­o opcional desde el front (si la lÃ­nea no vino por productos)
$precio_envio  = isset($input['precio_envio']) ? (float)$input['precio_envio'] : null;
// Cantidad de envÃ­o opcional
$envio_cantidad = isset($input['envio_cantidad']) ? (int)$input['envio_cantidad'] : null;

if (!$tipo || !$productos) {
    error('Datos incompletos para crear la venta');
}

if ($tipo === 'mesa') {
    if (!$mesa_id || $repartidor_id) {
        error('Venta en mesa requiere mesa_id y sin repartidor_id');
    }
    $estado = $conn->prepare('SELECT estado, usuario_id FROM mesas WHERE id = ?');
    if (!$estado) {
        error('Error al preparar consulta de mesa: ' . $conn->error);
    }
    $estado->bind_param('i', $mesa_id);
    if (!$estado->execute()) {
        $estado->close();
        error('Error al obtener estado de mesa: ' . $estado->error);
    }
    $resEstado = $estado->get_result();
    $rowEstado = $resEstado->fetch_assoc();
    $estado->close();
    if (!$rowEstado) {
        error('Mesa no encontrada');
    }
    if (((int)($rowEstado['usuario_id'] ?? 0) !== 0) && ((int)($rowEstado['usuario_id'] ?? 0) !== $usuario_id)) {
        http_response_code(400);
        error('La mesa seleccionada pertenece a otro mesero. Actualiza la pantalla e intÃ©ntalo de nuevo.');
    }
    if ($rowEstado['estado'] !== 'libre') {
        error('La mesa seleccionada no estÃ¡ libre');
    }
} elseif ($tipo === 'domicilio') {
    if (!$repartidor_id || $mesa_id) {
        error('Venta a domicilio requiere repartidor_id y sin mesa_id');
    }
} elseif ($tipo === 'rapido') {
    if ($mesa_id || $repartidor_id) {
        error('Venta rÃ¡pida no debe incluir mesa ni repartidor');
    }
    if (!$usuario_id) { $usuario_id = $cajero_id; }
} else {
    error('Tipo de venta invÃ¡lido');
}

$total = 0;
foreach ($productos as $p) {
    if (!isset($p['producto_id'], $p['cantidad'], $p['precio_unitario'])) {
        error('Formato de producto incorrecto');
    }
    $total += $p['cantidad'] * $p['precio_unitario'];
}

// Si la venta es en mesa, revisar si ya existe una venta activa para esa mesa
if ($tipo === 'mesa') {
    $check = $conn->prepare("SELECT id, usuario_id FROM ventas WHERE mesa_id = ? AND estatus = 'activa' LIMIT 1");
    if (!$check) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $check->bind_param('i', $mesa_id);
    if (!$check->execute()) {
        $check->close();
        error('Error al ejecutar consulta: ' . $check->error);
    }
    $res = $check->get_result();
    $existing = $res->fetch_assoc();
    $check->close();
    if ($existing) {
        $venta_id = (int)$existing['id'];
        // Si se especificÃ³ un usuario distinto, actualizarlo
        if ($usuario_id && $usuario_id !== (int)$existing['usuario_id']) {
            $upUser = $conn->prepare('UPDATE ventas SET usuario_id = ? WHERE id = ?');
            if ($upUser) {
                $upUser->bind_param('ii', $usuario_id, $venta_id);
                $upUser->execute();
                $upUser->close();
            }
        }
        // Actualizar total acumulado y asignar corte si no tiene
        $upTotal = $conn->prepare('UPDATE ventas SET total = total + ?, corte_id = IFNULL(corte_id, ?), cajero_id = IFNULL(cajero_id, ?) WHERE id = ?');
        if ($upTotal) {
            $upTotal->bind_param('diii', $total, $corte_id, $cajero_id, $venta_id);
            $upTotal->execute();
            $upTotal->close();
        }
        // Si se env&iacute;a una promoci&oacute;n nueva, actualizarla
        if ($promocion_id !== null) {
            $upPromo = $conn->prepare('UPDATE ventas SET promocion_id = ? WHERE id = ?');
            if ($upPromo) {
                $upPromo->bind_param('ii', $promocion_id, $venta_id);
                $upPromo->execute();
                $upPromo->close();
            }
        }
    }
}

$nueva_venta = false;

if (!isset($venta_id)) {
    if ($tipo === 'domicilio') {
        $stmt = $conn->prepare('INSERT INTO ventas (mesa_id, repartidor_id, usuario_id, tipo_entrega, total, observacion, corte_id, cajero_id, fecha_asignacion, sede_id, propina_efectivo, propina_cheque, propina_tarjeta, promocion_id, promocion_descuento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)');
    } else {
        $stmt = $conn->prepare('INSERT INTO ventas (mesa_id, repartidor_id, usuario_id, tipo_entrega, total, observacion, corte_id, cajero_id, sede_id, propina_efectivo, propina_cheque, propina_tarjeta, promocion_id, promocion_descuento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    }
    if (!$stmt) {
        error('Error al preparar venta: ' . $conn->error);
    }
    if ($tipo === 'domicilio') {
        $stmt->bind_param('iiisdsiiidddid', $mesa_id, $repartidor_id, $usuario_id, $tipo, $total, $observacion, $corte_id, $cajero_id, $sede_id, $propina_efectivo, $propina_cheque, $propina_tarjeta, $promocion_id, $promocion_descuento);
    } else {
        $stmt->bind_param('iiisdsiiidddid', $mesa_id, $repartidor_id, $usuario_id, $tipo, $total, $observacion, $corte_id, $cajero_id, $sede_id, $propina_efectivo, $propina_cheque, $propina_tarjeta, $promocion_id, $promocion_descuento);
    }
    if (!$stmt->execute()) {
        error('Error al crear venta: ' . $stmt->error);
    }
    $venta_id = $stmt->insert_id;
    $stmt->close();
    $nueva_venta = true;
    if ($tipo === 'mesa') {
        $updMesa = $conn->prepare("UPDATE mesas SET usuario_id = IFNULL(usuario_id, ?), estado = 'ocupada', tiempo_ocupacion_inicio = IF(tiempo_ocupacion_inicio IS NULL, NOW(), tiempo_ocupacion_inicio) WHERE id = ?");
        if ($updMesa) {
            $updMesa->bind_param('ii', $usuario_id, $mesa_id);
            $updMesa->execute();
            $updMesa->close();
        }
    }
}

// Insertar detalles de la venta y conservar el Ãºltimo ID generado
// === Paso 3: Pre-chequeo de existencia de productos y creaciÃ³n del "envÃ­o" on-the-fly ===
$CHK_SQL = 'SELECT COUNT(*) AS c FROM productos WHERE id = ?';
$INS_ENVIO_SQL = "
  INSERT IGNORE INTO productos (id, nombre, precio, descripcion, existencia, activo, imagen, categoria_id)
  VALUES (?, ?, ?, 'Cargo por envÃ­o a domicilio (repartidor casa)', 99999, 1, NULL, ?)
";

$chk = $conn->prepare($CHK_SQL);
if (!$chk) {
    error('No se pudo preparar CHK productos: ' . $conn->error);
}

$insEnvio = $conn->prepare($INS_ENVIO_SQL);
if (!$insEnvio) {
    if ($chk) $chk->close();
    error('No se pudo preparar INS_ENVIO: ' . $conn->error);
}

$detalles = isset($productos) ? $productos : ([]);
foreach ($detalles as $p) {
    $producto_id     = (int)($p['producto_id'] ?? 0);
    $precio_unitario = (float)($p['precio_unitario'] ?? 0);
    if ($producto_id <= 0) {
        $chk->close();
        $insEnvio->close();
        error('Producto invÃ¡lido en detalles');
    }

    $chk->bind_param('i', $producto_id);
    if (!$chk->execute()) {
        $chk->close();
        $insEnvio->close();
        error('Fallo al verificar producto en catÃ¡logo (producto_id=' . $producto_id . '): ' . $chk->error);
    }
    $res = $chk->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $count = (int)($row['c'] ?? 0);

    if ($count === 0) {
        if ($producto_id === (int)ENVIO_CASA_PRODUCT_ID) {
            $precioCrear = $precio_unitario > 0 ? $precio_unitario : (float)ENVIO_CASA_DEFAULT_PRECIO;
            $categoriaId = 6; // categorÃ­a genÃ©rica existente
            $nombreEnvio = (string)ENVIO_CASA_NOMBRE;
            $insEnvio->bind_param('isdi', $producto_id, $nombreEnvio, $precioCrear, $categoriaId);
            if (!$insEnvio->execute()) {
                $chk->close();
                $insEnvio->close();
                error('No se pudo crear producto de envÃ­o (producto_id=' . $producto_id . '): ' . $insEnvio->error);
            }
        } else {
            $chk->close();
            $insEnvio->close();
            http_response_code(400);
            error('Producto inexistente en catÃ¡logo: ' . $producto_id);
        }
    }
}
$chk->close();
$insEnvio->close();

$detalle = $conn->prepare('INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
if (!$detalle) {
    error('Error al preparar detalle: ' . $conn->error);
}

$ultimo_detalle_id = 0;
$detalles_creados = [];
foreach ($productos as $p) {
    $producto_id     = (int) $p['producto_id'];
    $cantidad        = (int) $p['cantidad'];
    $precio_unitario = (float) $p['precio_unitario'];

    $detalle->bind_param('iiid', $venta_id, $producto_id, $cantidad, $precio_unitario);
    if (!$detalle->execute()) {
        $err = $detalle->error;
        $detalle->close();
        error('Error al insertar detalle (producto_id=' . $producto_id . '): ' . $err);
    }
    $ultimo_detalle_id = $detalle->insert_id;
    if ($ultimo_detalle_id) { $detalles_creados[] = (int)$ultimo_detalle_id; }
}
$detalle->close();

// EnvÃ­o automÃ¡tico si aplica (domicilio + "Repartidor casa") e idempotente
if ($tipo === 'domicilio' && $repartidor_id) {
    try {
        $stmt = $conn->prepare('SELECT LOWER(TRIM(nombre)) AS nombre FROM repartidores WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $repartidor_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            $esCasa = $row && ($row['nombre'] === 'repartidor casa');
            if ($esCasa) {
                // Â¿ya existe la lÃ­nea de envÃ­o?
                $chk = $conn->prepare('SELECT id FROM venta_detalles WHERE venta_id = ? AND producto_id = ? LIMIT 1');
                if ($chk) {
                    $pid = (int)ENVIO_CASA_PRODUCT_ID;
                    $chk->bind_param('ii', $venta_id, $pid);
                    $chk->execute();
                    $c = $chk->get_result()->fetch_assoc();
                    $chk->close();
                    if ($c && isset($c['id'])) {
                        // Ya existe: actualizar cantidad/precio si nos llegaron, y marcar entregado + sin descargar
                        $cantidadEnv = isset($input['envio_cantidad']) ? max(1, (int)$input['envio_cantidad']) : 1;
                        $precio = $precio_envio !== null ? (float)$precio_envio : (float)ENVIO_CASA_DEFAULT_PRECIO;
                        $upd = $conn->prepare("UPDATE venta_detalles SET cantidad = ?, precio_unitario = ?, insumos_descargados = 0, estado_producto = 'entregado' WHERE id = ?");
                        if ($upd) {
                            $detId = (int)$c['id'];
                            $upd->bind_param('idi', $cantidadEnv, $precio, $detId);
                            $upd->execute();
                            $upd->close();
                        }
                    } else {
                        $cantidadEnv = isset($input['envio_cantidad']) ? max(1, (int)$input['envio_cantidad']) : 1;
                        $precio = $precio_envio !== null ? (float)$precio_envio : (float)ENVIO_CASA_DEFAULT_PRECIO;
                        if ($cantidadEnv > 0) {
                            $ins = $conn->prepare("INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario, insumos_descargados, estado_producto) VALUES (?, ?, ?, ?, 0, 'entregado')");
                            if ($ins) {
                                $ins->bind_param('iiid', $venta_id, $pid, $cantidadEnv, $precio);
                                $ins->execute();
                                $nuevoId = (int)$ins->insert_id;
                                if ($nuevoId) { $detalles_creados[] = $nuevoId; }
                                $ins->close();
                            }
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // No interrumpir si envÃ­o falla
    }
}

// Recalcular el total a partir de venta_detalles (incluye envÃ­o si aplica)
$recalc = $conn->prepare("UPDATE ventas v JOIN (SELECT venta_id, SUM(cantidad * precio_unitario) AS total FROM venta_detalles WHERE venta_id = ? GROUP BY venta_id) x ON x.venta_id = v.id SET v.total = x.total WHERE v.id = ?");
if ($recalc) {
    $recalc->bind_param('ii', $venta_id, $venta_id);
    $recalc->execute();
    $recalc->close();
}

// LÃ³gica reemplazada por base de datos: ver bd.sql (Logs)
// Registrar acciÃ³n en logs
$log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
if ($log) {
    $mod = 'ventas';
    $accion = $nueva_venta ? 'Alta de venta' : 'ActualizaciÃ³n de venta';
    $log->bind_param('issi', $usuario_id, $mod, $accion, $venta_id);
    $log->execute();
    $log->close();
}

// Notificar cambio a historial de ventas (long-poll) - silencioso si no hay permisos
try {
    $dir = __DIR__ . '/runtime';
    $okDir = is_dir($dir) || @mkdir($dir, 0775, true);
    if ($okDir && @is_writable($dir)) {
        $verFile   = $dir . '/ventas_version.txt';
        $eventsLog = $dir . '/ventas_events.jsonl';
        $fp = @fopen($verFile, 'c+');
        if ($fp) {
            flock($fp, LOCK_EX);
            rewind($fp);
            $txt  = stream_get_contents($fp);
            $cur  = intval(trim($txt ?? '0'));
            $next = $cur + 1;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string)$next);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            $evt = json_encode(['v'=>$next,'ids'=>[$venta_id],'ts'=>time()]);
            @file_put_contents($eventsLog, $evt . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
} catch (Throwable $e) { /* no interrumpir */ }

// Notificar a pantallas de cocina sobre nuevos detalles creados (sin HTTP interno)
if (!empty($detalles_creados)) {
    try {
        require_once __DIR__ . '/../cocina/notify_lib.php';
        @cocina_notify(array_values(array_unique($detalles_creados)));
    } catch (\Throwable $e) { /* noop */ }
}

success(['venta_id' => $venta_id, 'ultimo_detalle_id' => $ultimo_detalle_id]);




