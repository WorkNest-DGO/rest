<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/sedes.php';

// Lista registros de la tabla cortes_almacen_detalle
// Parámetros opcionales (GET):
// - corte_id: filtra por corte específico
// - insumo_id: filtra por insumo
// - usuario_id/user_id: se usa para filtrar por sede del usuario

try {
    $corteId  = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : 0;
    $insumoId = isset($_GET['insumo_id']) ? (int)$_GET['insumo_id'] : 0;
    $usuario  = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0);

    $sedeFiltro = sede_resolver_usuario($conn, $usuario);
    $corteSedeCol = sede_column_name($conn, 'cortes_almacen');
    if ($usuario && $corteSedeCol && $sedeFiltro === null) {
        error('No se pudo determinar la sede del usuario');
    }

    $sql = "SELECT d.id, d.corte_id, d.insumo_id, d.existencia_inicial, d.entradas, d.salidas, d.mermas, d.existencia_final
            FROM cortes_almacen_detalle d";
    $joins = '';
    $conds = [];
    $types = '';
    $vals  = [];

    if ($corteId > 0) {
        $conds[] = 'd.corte_id = ?';
        $types  .= 'i';
        $vals[]   = $corteId;
    }
    if ($insumoId > 0) {
        $conds[] = 'd.insumo_id = ?';
        $types  .= 'i';
        $vals[]   = $insumoId;
    }
    if ($corteSedeCol && $sedeFiltro !== null) {
        $joins   = ' INNER JOIN cortes_almacen c ON c.id = d.corte_id';
        $conds[] = "c.{$corteSedeCol} = ?";
        $types  .= 'i';
        $vals[]   = $sedeFiltro;
    }

    if ($joins) {
        $sql .= $joins;
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
