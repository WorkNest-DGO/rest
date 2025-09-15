<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Lista cortes de almacén y detalle para un corte seleccionado

function inv_table_exists(string $name): bool {
    global $conn;
    $name = $conn->real_escape_string($name);
    $q = $conn->query("SHOW TABLES LIKE '$name'");
    return $q && $q->num_rows > 0;
}

function inv_column_exists(string $table, string $column): bool {
    global $conn;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $q = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $q && $q->num_rows > 0;
}

function inv_obtenerCortes($fecha = null) {
    global $conn;

    // Elegir tabla dinámica: cortes_inventario si existe, si no cortes_almacen
    $tabla = inv_table_exists('cortes_inventario') ? 'cortes_inventario' : 'cortes_almacen';

    // Resolver columnas dinámicas
    $colAbre = inv_column_exists($tabla, 'usuario_abre_id') ? 'usuario_abre_id' : (inv_column_exists($tabla, 'usuario_id') ? 'usuario_id' : null);
    $colCierra = inv_column_exists($tabla, 'usuario_cierra_id') ? 'usuario_cierra_id' : null;
    $colFini = inv_column_exists($tabla, 'fecha_inicio') ? 'fecha_inicio' : (inv_column_exists($tabla, 'inicio') ? 'inicio' : (inv_column_exists($tabla, 'created_at') ? 'created_at' : null));
    $colFfin = inv_column_exists($tabla, 'fecha_fin') ? 'fecha_fin' : (inv_column_exists($tabla, 'fin') ? 'fin' : (inv_column_exists($tabla, 'updated_at') ? 'updated_at' : null));

    // Construir SQL con joins condicionales
    $select = "SELECT c.id";
    $joins = "";
    if ($colAbre) {
        $select .= ", ui.nombre AS abierto_por";
        $joins  .= " LEFT JOIN usuarios ui ON c.$colAbre = ui.id";
    } else {
        $select .= ", '' AS abierto_por";
    }
    if ($colFini) {
        $select .= ", c.$colFini AS fecha_inicio";
    } else {
        // fallback estable para UI que hace split() de fecha
        $select .= ", NOW() AS fecha_inicio";
    }
    if ($colCierra) {
        $select .= ", uc.nombre AS cerrado_por";
        $joins  .= " LEFT JOIN usuarios uc ON c.$colCierra = uc.id";
    } else {
        $select .= ", '' AS cerrado_por";
    }
    if ($colFfin) {
        $select .= ", c.$colFfin AS fecha_fin";
    } else {
        $select .= ", NULL AS fecha_fin";
    }

    $sql = "$select FROM $tabla c$joins";
    $bindFecha = false;
    if ($fecha && $colFini) {
        $sql .= " WHERE DATE(c.$colFini) = ?";
        $bindFecha = true;
    }
    $sql .= " ORDER BY c.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error('Error al preparar listado: ' . $conn->error);
    }
    if ($bindFecha) {
        $stmt->bind_param('s', $fecha);
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

function inv_obtenerDetalleCorte($corteId) {
    global $conn;
    if (!$corteId) {
        error('corte_id requerido');
    }

    $tablaDet = inv_table_exists('cortes_inventario_detalle') ? 'cortes_inventario_detalle' : 'cortes_almacen_detalle';

    $stmt = $conn->prepare("SELECT COALESCE(i.nombre,'Insumo eliminado') AS insumo,
            COALESCE(i.unidad,'') AS unidad,
            d.existencia_inicial, d.entradas, d.salidas, d.mermas, d.existencia_final
        FROM $tablaDet d
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

$accion = $_GET['accion'] ?? $_POST['accion'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

switch ($accion) {
    case 'listar':
        $fecha = $_GET['fecha'] ?? null;
        inv_obtenerCortes($fecha);
        break;
    case 'detalle':
        $cid = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : 0;
        inv_obtenerDetalleCorte($cid);
        break;
    default:
        error('Acción no válida');
}

?>
