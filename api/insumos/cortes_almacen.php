<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
// require_once __DIR__ . '/../../utils/SimpleXLSXGen.php'; // Migrado a CSV
require_once __DIR__ . '/../../utils/pdf_simple.php';
require_once __DIR__ . '/../../utils/sedes.php';

function corte_sede_column(): ?string {
    global $conn;
    return sede_column_name($conn, 'cortes_almacen');
}

function movimientos_sede_column(): ?string {
    global $conn;
    return sede_column_name($conn, 'movimientos_insumos');
}

function mermas_sede_column(): ?string {
    global $conn;
    return sede_column_name($conn, 'mermas_insumo');
}

function insumos_sede_column(): ?string {
    global $conn;
    return sede_column_name($conn, 'insumos');
}

function obtener_insumos_para_sede(array $campos, ?int $sedeId) {
    global $conn;
    $sel = implode(', ', $campos);
    $sql = "SELECT {$sel} FROM insumos";
    $insumoSedeCol = insumos_sede_column();
    if ($insumoSedeCol && $sedeId !== null) {
        $sql .= " WHERE {$insumoSedeCol} = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error('Error al obtener insumos: ' . $conn->error);
        }
        $stmt->bind_param('i', $sedeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }
    $res = $conn->query($sql);
    if (!$res) {
        error('Error al obtener insumos: ' . $conn->error);
    }
    return $res;
}

function validar_corte_por_sede(int $corteId, ?int $sedeId, ?string $corteSedeCol): void {
    global $conn;
    if (!$corteSedeCol || $sedeId === null) {
        return;
    }
    $stmt = $conn->prepare("SELECT id FROM cortes_almacen WHERE id = ? AND {$corteSedeCol} = ? LIMIT 1");
    if (!$stmt) {
        error('Error al validar corte: ' . $conn->error);
    }
    $stmt->bind_param('ii', $corteId, $sedeId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        error('El corte no pertenece a la sede del usuario');
    }
    $stmt->close();
}

function abrirCorte($usuarioId) {
    global $conn;

    if (!$usuarioId) {
        error('Usuario requerido');
    }

    $sedeId = sede_resolver_usuario($conn, $usuarioId);
    $corteSedeCol = corte_sede_column();
    if ($corteSedeCol && $sedeId === null) {
        error('No se pudo determinar la sede del usuario');
    }

    // validar si existe un corte abierto para este usuario/sede
    if ($corteSedeCol) {
        $check = $conn->prepare("SELECT id FROM cortes_almacen WHERE {$corteSedeCol} = ? AND fecha_fin IS NULL LIMIT 1");
    } else {
        $check = $conn->prepare('SELECT id FROM cortes_almacen WHERE usuario_abre_id = ? AND fecha_fin IS NULL LIMIT 1');
    }
    if (!$check) {
        error('Error al verificar corte: ' . $conn->error);
    }
    if ($corteSedeCol) {
        $check->bind_param('i', $sedeId);
    } else {
        $check->bind_param('i', $usuarioId);
    }
    $check->execute();
    $res = $check->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $check->close();
        // ya hay un corte abierto, devolver id existente
        success(['corte_id' => (int)$row['id']]);
    }
    $check->close();

    $conn->begin_transaction();

    if ($corteSedeCol) {
        $stmt = $conn->prepare("INSERT INTO cortes_almacen ({$corteSedeCol}, usuario_abre_id, fecha_inicio) VALUES (?, ?, NOW())");
    } else {
        $stmt = $conn->prepare('INSERT INTO cortes_almacen (usuario_abre_id, fecha_inicio) VALUES (?, NOW())');
    }
    if (!$stmt) {
        $conn->rollback();
        error('Error al preparar: ' . $conn->error);
    }
    if ($corteSedeCol) {
        $stmt->bind_param('ii', $sedeId, $usuarioId);
    } else {
        $stmt->bind_param('i', $usuarioId);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        error('Error al abrir corte: ' . $stmt->error);
    }
    $corteId = $stmt->insert_id;
    $stmt->close();

    // registrar inventario inicial por insumo
    $resIns = obtener_insumos_para_sede(['id', 'existencia', 'nombre', 'unidad'], $sedeId);

    $hasDetalle = $conn->query("SHOW TABLES LIKE 'cortes_almacen_detalle'")->num_rows > 0;
    if ($hasDetalle) {
        $insStmt = $conn->prepare('INSERT INTO cortes_almacen_detalle (corte_id, insumo_id, existencia_inicial, entradas, salidas, mermas, existencia_final) VALUES (?, ?, ?, 0, 0, 0, NULL)');
        if (!$insStmt) {
            $conn->rollback();
            error('Error al preparar detalles: ' . $conn->error);
        }

        while ($ins = $resIns->fetch_assoc()) {
            $insumoId = (int)$ins['id'];
            $existencia = (float)$ins['existencia'];
            $insStmt->bind_param('iid', $corteId, $insumoId, $existencia);
            if (!$insStmt->execute()) {
                $insStmt->close();
                $conn->rollback();
                error('Error al insertar detalle: ' . $insStmt->error);
            }
        }
        $insStmt->close();
    }

    $conn->commit();
    success(['corte_id' => $corteId]);
}

function cerrarCorte($corteId, $usuarioId, $observaciones) {
    global $conn;

    if (!$corteId || !$usuarioId) {
        error('Datos incompletos');
    }

    $corteSedeCol = corte_sede_column();
    $sedeId = sede_resolver_usuario($conn, $usuarioId);
    if ($corteSedeCol && $sedeId === null) {
        error('No se pudo determinar la sede del usuario');
    }

    $conn->begin_transaction();

    // obtener inicio del corte y validar que no esté cerrado
    if ($corteSedeCol) {
        $stmt = $conn->prepare("SELECT fecha_inicio FROM cortes_almacen WHERE id = ? AND {$corteSedeCol} = ? AND fecha_fin IS NULL");
    } else {
        $stmt = $conn->prepare('SELECT fecha_inicio FROM cortes_almacen WHERE id = ? AND fecha_fin IS NULL');
    }
    if (!$stmt) {
        $conn->rollback();
        error('Error al obtener corte: ' . $conn->error);
    }
    if ($corteSedeCol) {
        $stmt->bind_param('ii', $corteId, $sedeId);
    } else {
        $stmt->bind_param('i', $corteId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        error('Corte no encontrado o ya cerrado');
    }
    $row    = $res->fetch_assoc();
    $inicio = $row['fecha_inicio'];
    $stmt->close();

    $fin = date('Y-m-d H:i:s');

    // registrar cierre del corte para obtener fecha_fin fija
    if ($corteSedeCol) {
        $updCorte = $conn->prepare("UPDATE cortes_almacen SET fecha_fin = ?, usuario_cierra_id = ?, observaciones = ? WHERE id = ? AND {$corteSedeCol} = ? AND fecha_fin IS NULL");
    } else {
        $updCorte = $conn->prepare('UPDATE cortes_almacen SET fecha_fin = ?, usuario_cierra_id = ?, observaciones = ? WHERE id = ? AND fecha_fin IS NULL');
    }
    if (!$updCorte) {
        $conn->rollback();
        error('Error al preparar cierre: ' . $conn->error);
    }
    if ($corteSedeCol) {
        $updCorte->bind_param('sisii', $fin, $usuarioId, $observaciones, $corteId, $sedeId);
    } else {
        $updCorte->bind_param('sisi', $fin, $usuarioId, $observaciones, $corteId);
    }
    if (!$updCorte->execute()) {
        $updCorte->close();
        $conn->rollback();
        error('Error al cerrar corte: ' . $updCorte->error);
    }
    $updCorte->close();

    // obtener insumos con existencia actual
    $resIns = obtener_insumos_para_sede(['id', 'existencia', 'nombre', 'unidad'], $sedeId);

    // obtener detalles existentes y validar duplicados
    $det = $conn->prepare('SELECT insumo_id, existencia_inicial FROM cortes_almacen_detalle WHERE corte_id = ?');
    if (!$det) {
        $conn->rollback();
        error('Error al obtener detalles: ' . $conn->error);
    }
    $det->bind_param('i', $corteId);
    $det->execute();
    $resDet = $det->get_result();
    $detalleMap = [];
    while ($d = $resDet->fetch_assoc()) {
        $detalleMap[$d['insumo_id']] = (float)$d['existencia_inicial'];
    }
    $det->close();

    $dupCheck = $conn->prepare('SELECT COUNT(*) c FROM cortes_almacen_detalle WHERE corte_id = ? GROUP BY insumo_id HAVING c > 1 LIMIT 1');
    if ($dupCheck) {
        $dupCheck->bind_param('i', $corteId);
        $dupCheck->execute();
        $dupRes = $dupCheck->get_result();
        if ($dupRes && $dupRes->num_rows > 0) {
            $dupCheck->close();
            $conn->rollback();
            error('Registros duplicados en detalle');
        }
        $dupCheck->close();
    }

    // movimientos y mermas en el rango
    $movSedeCol = movimientos_sede_column();
    $sqlMov = "SELECT insumo_id,
            SUM(CASE WHEN tipo='entrada' OR (tipo='ajuste' AND cantidad>0) THEN cantidad ELSE 0 END) AS entradas,
            SUM(CASE WHEN tipo IN ('salida','traspaso') THEN cantidad ELSE 0 END) AS salidas,
            SUM(CASE WHEN tipo='ajuste' AND cantidad<0 THEN ABS(cantidad) ELSE 0 END) AS mermas
        FROM movimientos_insumos
        WHERE fecha BETWEEN ? AND ?";
    if ($movSedeCol && $sedeId !== null) {
        $sqlMov .= " AND {$movSedeCol} = ?";
    }
    $sqlMov .= " GROUP BY insumo_id";
    $mov = $conn->prepare($sqlMov);
    if (!$mov) {
        $conn->rollback();
        error('Error al calcular movimientos: ' . $conn->error);
    }
    if ($movSedeCol && $sedeId !== null) {
        $mov->bind_param('ssi', $inicio, $fin, $sedeId);
    } else {
        $mov->bind_param('ss', $inicio, $fin);
    }
    $mov->execute();
    $rMov = $mov->get_result();
    $datosMov = [];
    while ($m = $rMov->fetch_assoc()) {
        $datosMov[$m['insumo_id']] = [
            'entradas' => (float)$m['entradas'],
            'salidas'  => (float)$m['salidas'],
            'mermas'   => (float)$m['mermas']
        ];
    }
    $mov->close();

    $hasMerma = $conn->query("SHOW TABLES LIKE 'mermas_insumo'")->num_rows > 0;
    if ($hasMerma) {
        $mermaSedeCol = mermas_sede_column();
        $sqlMerma = 'SELECT insumo_id, SUM(cantidad) AS merma FROM mermas_insumo WHERE fecha BETWEEN ? AND ?';
        if ($mermaSedeCol && $sedeId !== null) {
            $sqlMerma .= " AND {$mermaSedeCol} = ?";
        }
        $sqlMerma .= ' GROUP BY insumo_id';
        $mm = $conn->prepare($sqlMerma);
        if ($mm) {
            if ($mermaSedeCol && $sedeId !== null) {
                $mm->bind_param('ssi', $inicio, $fin, $sedeId);
            } else {
                $mm->bind_param('ss', $inicio, $fin);
            }
            $mm->execute();
            $rm = $mm->get_result();
            while ($m = $rm->fetch_assoc()) {
                $id = $m['insumo_id'];
                $cantidad = (float)$m['merma'];
                if (!isset($datosMov[$id])) {
                    $datosMov[$id] = ['entradas' => 0, 'salidas' => 0, 'mermas' => 0];
                }
                $datosMov[$id]['mermas'] += $cantidad;
            }
            $mm->close();
        }
    }

    $updDet = $conn->prepare('UPDATE cortes_almacen_detalle SET entradas = ?, salidas = ?, mermas = ?, existencia_final = ? WHERE corte_id = ? AND insumo_id = ?');
    if (!$updDet) {
        $conn->rollback();
        error('Error al preparar actualización de detalle: ' . $conn->error);
    }

    $insDet = $conn->prepare('INSERT INTO cortes_almacen_detalle (corte_id, insumo_id, existencia_inicial, entradas, salidas, mermas, existencia_final) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$insDet) {
        $updDet->close();
        $conn->rollback();
        error('Error al preparar inserción de detalle: ' . $conn->error);
    }

    $detalles = [];
    while ($ins = $resIns->fetch_assoc()) {
        $insumoId = (int)$ins['id'];
        $existenciaActual = (float)$ins['existencia'];
        $entradas = $datosMov[$insumoId]['entradas'] ?? 0;
        $salidas  = $datosMov[$insumoId]['salidas'] ?? 0;
        $mermas   = $datosMov[$insumoId]['mermas'] ?? 0;

        if (isset($detalleMap[$insumoId])) {
            $updDet->bind_param('ddddii', $entradas, $salidas, $mermas, $existenciaActual, $corteId, $insumoId);
            if (!$updDet->execute()) {
                $insDet->close();
                $updDet->close();
                $conn->rollback();
                error('Error al actualizar detalle: ' . $updDet->error);
            }
            $inicial = $detalleMap[$insumoId];
        } else {
            $insDet->bind_param('iiddddd', $corteId, $insumoId, $existenciaActual, $entradas, $salidas, $mermas, $existenciaActual);
            if (!$insDet->execute()) {
                $insDet->close();
                $updDet->close();
                $conn->rollback();
                error('Error al insertar detalle: ' . $insDet->error);
            }
            $inicial = $existenciaActual;
        }

        $detalles[] = [
            'insumo'    => $ins['nombre'] ?? 'Insumo eliminado',
            'unidad'    => $ins['unidad'] ?? '',
            'existencia_inicial' => $inicial,
            'entradas'  => $entradas,
            'salidas'   => $salidas,
            'mermas'    => $mermas,
            'existencia_final' => $existenciaActual
        ];
    }
    $insDet->close();
    $updDet->close();

    $conn->commit();
    success(['corte_id' => $corteId, 'detalles' => $detalles]);
}

function obtenerCortes($fecha = null, ?int $usuarioId = null) {
    global $conn;
    $corteSedeCol = corte_sede_column();
    $sedeId = sede_resolver_usuario($conn, $usuarioId);
    if ($usuarioId && $corteSedeCol && $sedeId === null) {
        error('No se pudo determinar la sede del usuario');
    }
    $sql = "SELECT c.id, ui.nombre AS abierto_por, c.fecha_inicio, uc.nombre AS cerrado_por, c.fecha_fin
            FROM cortes_almacen c
            LEFT JOIN usuarios ui ON c.usuario_abre_id = ui.id
            LEFT JOIN usuarios uc ON c.usuario_cierra_id = uc.id";
    $params = [];
    $types = '';
    if ($fecha) {
        $sql .= " WHERE DATE(c.fecha_inicio) = ?";
        $types .= 's';
        $params[] = $fecha;
    }
    if ($corteSedeCol && $sedeId !== null) {
        $sql .= $fecha ? " AND c.{$corteSedeCol} = ?" : " WHERE c.{$corteSedeCol} = ?";
        $types .= 'i';
        $params[] = $sedeId;
    }
    $sql .= " ORDER BY c.id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error('Error al preparar listado: ' . $conn->error);
    }
    if ($types === 's') {
        $stmt->bind_param('s', $params[0]);
    } elseif ($types === 'i') {
        $stmt->bind_param('i', $params[0]);
    } elseif ($types === 'si') {
        $stmt->bind_param('si', $params[0], $params[1]);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    success($rows);
}

function obtenerDetalleCorte($corteId, ?int $usuarioId = null) {
    global $conn;
    $corteSedeCol = corte_sede_column();
    $sedeId = sede_resolver_usuario($conn, $usuarioId);
    if ($usuarioId && $corteSedeCol && $sedeId === null) {
        error('No se pudo determinar la sede del usuario');
    }
    validar_corte_por_sede($corteId, $sedeId, $corteSedeCol);
    $stmt = $conn->prepare("SELECT COALESCE(i.nombre,'Insumo eliminado') AS insumo,
            COALESCE(i.unidad,'') AS unidad,
            d.existencia_inicial, d.entradas, d.salidas, d.mermas, d.existencia_final
        FROM cortes_almacen_detalle d
        LEFT JOIN insumos i ON d.insumo_id = i.id
        WHERE d.corte_id = ?");
    if (!$stmt) {
        error('Error al preparar detalle: ' . $conn->error);
    }
    $stmt->bind_param('i', $corteId);
    $stmt->execute();
    $res = $stmt->get_result();
    $detalles = [];
    while ($row = $res->fetch_assoc()) {
        $detalles[] = $row;
    }
    $stmt->close();
    success($detalles);
}


function exportarExcel($corte_id, ?int $usuarioId = null) {
    // Exportación CSV (compat con endpoints existentes)
    global $conn;
    if (!$corte_id) { error('corte_id requerido'); }
    $corteSedeCol = corte_sede_column();
    $sedeId = sede_resolver_usuario($conn, $usuarioId);
    if ($usuarioId && $corteSedeCol && $sedeId === null) {
        error('No se pudo determinar la sede del usuario');
    }
    validar_corte_por_sede($corte_id, $sedeId, $corteSedeCol);
    $query = "SELECT i.nombre AS insumo, i.unidad, d.existencia_inicial, d.entradas, d.salidas, d.mermas, d.existencia_final
              FROM cortes_almacen_detalle d
              JOIN insumos i ON i.id = d.insumo_id
              WHERE d.corte_id = ?";
    $stmt = $conn->prepare($query);
    if(!$stmt){ error('Error al preparar consulta'); }
    $stmt->bind_param("i", $corte_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Ruta CSV en uploads/reportes
    $dir = realpath(__DIR__ . '/../../uploads/reportes');
    if (!$dir) { $dir = __DIR__ . '/../../uploads/reportes'; @mkdir($dir, 0777, true); }
    $filename = "corte_almacen_{$corte_id}.csv";
    $ruta = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    $out = fopen($ruta, 'w');
    // BOM UTF-8 para compatibilidad con Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Insumo', 'Unidad', 'Inicial', 'Entradas', 'Salidas', 'Mermas', 'Final']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['insumo'], $row['unidad'], $row['existencia_inicial'],
            $row['entradas'], $row['salidas'], $row['mermas'], $row['existencia_final']
        ]);
    }
    fclose($out);
    echo json_encode(["success" => true, "resultado" => ["archivo" => str_replace(__DIR__ . '/..' . '/..', '', $ruta)]]);
}

function exportarPdf($corteId, ?int $usuarioId = null) {
    global $conn;
    if (!$corteId) {
        error('Corte inválido');
    }
    $corteSedeCol = corte_sede_column();
    $sedeId = sede_resolver_usuario($conn, $usuarioId);
    if ($usuarioId && $corteSedeCol && $sedeId === null) {
        error('No se pudo determinar la sede del usuario');
    }
    validar_corte_por_sede($corteId, $sedeId, $corteSedeCol);
    $stmt = $conn->prepare("SELECT COALESCE(i.nombre,'Insumo eliminado') AS insumo,
            COALESCE(i.unidad,'') AS unidad,
            d.existencia_inicial, d.entradas, d.salidas, d.mermas, d.existencia_final
        FROM cortes_almacen_detalle d
        LEFT JOIN insumos i ON d.insumo_id = i.id
        WHERE d.corte_id = ?");
    if (!$stmt) {
        error('Error al obtener datos: ' . $conn->error);
    }
    $stmt->bind_param('i', $corteId);
    $stmt->execute();
    $res = $stmt->get_result();
    $lineas = [];
    $lineas[] = 'Insumo | Unidad | Inicial | Entradas | Salidas | Mermas | Final';
    while ($d = $res->fetch_assoc()) {
        $lineas[] = $d['insumo'] . ' | ' . $d['unidad'] . ' | ' .
            $d['existencia_inicial'] . ' | ' . $d['entradas'] . ' | ' .
            $d['salidas'] . ' | ' . $d['mermas'] . ' | ' . $d['existencia_final'];
    }
    $stmt->close();
    $fileName = '/uploads/reportes/corte_almacen_' . $corteId . '.pdf';
    $path = __DIR__ . '/..' . '/..' . $fileName;
    generar_pdf_simple($path, 'Corte de Almacen', $lineas);
    success(['archivo' => $fileName]);
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? $_GET['action'] ?? $_POST['action'] ?? '';
$usuarioParam = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : (isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0));

switch ($accion) {
    case 'abrir':
        $user = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : $usuarioParam;
        abrirCorte($user);
        break;
    case 'cerrar':
        $corteId = isset($_POST['corte_id']) ? (int)$_POST['corte_id'] : 0;
        $user = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : $usuarioParam;
        $obs = $_POST['observaciones'] ?? '';
        cerrarCorte($corteId, $user, $obs);
        break;
    case 'listar':
        $fecha = $_GET['fecha'] ?? null;
        obtenerCortes($fecha, $usuarioParam);
        break;
    case 'detalle':
        $cid = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : 0;
        obtenerDetalleCorte($cid, $usuarioParam);
        break;
    case 'exportar_excel':
    case 'exportarExcel':
        $cid = isset($_POST['corte_id']) ? (int)$_POST['corte_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        exportarExcel($cid, $usuarioParam);
        break;
    case 'exportar_pdf':
        $cid = isset($_POST['corte_id']) ? (int)$_POST['corte_id'] : 0;
        exportarPdf($cid, $usuarioParam);
        break;
    default:
        error('Acción no válida');
}
