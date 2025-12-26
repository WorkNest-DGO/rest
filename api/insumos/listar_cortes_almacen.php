<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/sedes.php';

// Lista registros de la tabla cortes_almacen
// Parámetros opcionales (GET):
// - id: filtra por id exacto
// - fecha: filtra por DATE(fecha_inicio) = YYYY-MM-DD
// - abiertos: 1 para solo abiertos (fecha_fin IS NULL)
// - usuario_id/user_id: determina la sede a filtrar

try {
    $id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $fecha    = isset($_GET['fecha']) ? trim($_GET['fecha']) : null; // compat
    $desde    = isset($_GET['desde']) ? trim($_GET['desde']) : null;
    $hasta    = isset($_GET['hasta']) ? trim($_GET['hasta']) : null;
    $abiertos = isset($_GET['abiertos']) ? (int)$_GET['abiertos'] : 0;
    $usuario  = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0);

    $sedeFiltro = sede_resolver_usuario($conn, $usuario);
    $corteSedeCol = sede_column_name($conn, 'cortes_almacen');
    if ($usuario && $corteSedeCol && $sedeFiltro === null) {
        error('No se pudo determinar la sede del usuario');
    }

    $sql = "SELECT id, fecha_inicio, fecha_fin, usuario_abre_id, usuario_cierra_id, observaciones
            FROM cortes_almacen";
    $conds = [];
    $types = '';
    $vals  = [];

    if ($id > 0) {
        $conds[] = 'id = ?';
        $types  .= 'i';
        $vals[]   = $id;
    }
    // Filtro por fecha exacta (compat)
    if ($fecha) {
        $conds[] = 'DATE(fecha_inicio) = ?';
        $types  .= 's';
        $vals[]   = $fecha;
    }
    // Filtro por rango
    if (!$fecha) { // solo aplicar rango si no se usa "fecha" exacta
        if ($desde && $hasta) {
            $conds[] = 'DATE(fecha_inicio) BETWEEN ? AND ?';
            $types  .= 'ss';
            $vals[]   = $desde;
            $vals[]   = $hasta;
        } elseif ($desde) {
            $conds[] = 'DATE(fecha_inicio) >= ?';
            $types  .= 's';
            $vals[]   = $desde;
        } elseif ($hasta) {
            $conds[] = 'DATE(fecha_inicio) <= ?';
            $types  .= 's';
            $vals[]   = $hasta;
        }
    }
    if ($abiertos === 1) {
        $conds[] = 'fecha_fin IS NULL';
    }

    if ($corteSedeCol && $sedeFiltro !== null) {
        $conds[] = "{$corteSedeCol} = ?";
        $types  .= 'i';
        $vals[]   = $sedeFiltro;
    }

    if ($conds) {
        $sql .= ' WHERE ' . implode(' AND ', $conds);
    }
    $sql .= ' ORDER BY id DESC';

    if ($types) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error('Error al preparar consulta: ' . $conn->error);
        }
        $params = [$types];
        foreach ($vals as $k => $v) {
            $params[] = &$vals[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
        if (!$res) {
            error('Error al consultar: ' . $conn->error);
        }
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    if (isset($stmt)) $stmt->close();
    success($rows);
} catch (Throwable $e) {
    error('Error: ' . $e->getMessage());
}

?>
