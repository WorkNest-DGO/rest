<?php
header('Content-Type: application/json');
require_once '../../config/db.php'; // Ajusta la ruta a tu archivo de conexión

// Unificar obtención del id (GET, POST clásico o JSON)
$id = 0;
$input = json_decode(file_get_contents('php://input'), true);
if (is_array($input)) {
    if (isset($input['id'])) {
        $id = (int)$input['id'];
    } elseif (isset($input['venta_id'])) { // compatibilidad
        $id = (int)$input['venta_id'];
    } elseif (isset($input['corte_id'])) {
        $id = (int)$input['corte_id'];
    }
}
if ($id <= 0 && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
} elseif ($id <= 0 && isset($_GET['venta_id'])) {
    $id = (int)$_GET['venta_id'];
} elseif ($id <= 0 && isset($_GET['corte_id'])) {
    $id = (int)$_GET['corte_id'];
}
if ($id <= 0 && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
} elseif ($id <= 0 && isset($_POST['venta_id'])) {
    $id = (int)$_POST['venta_id'];
} elseif ($id <= 0 && isset($_POST['corte_id'])) {
    $id = (int)$_POST['corte_id'];
}

if ($id <= 0) {
    echo json_encode(["success" => false, "mensaje" => "ID no válido"]);
    exit;
}

try {
    // 1️⃣ Intentar obtener desglose de corte
    $sqlDesglose = "SELECT dc.id, cd.descripcion, cd.valor, dc.cantidad, dc.tipo_pago,
                           (cd.valor * dc.cantidad) AS subtotal
                    FROM desglose_corte dc
                    JOIN catalogo_denominaciones cd ON cd.id = dc.denominacion_id
                    WHERE dc.corte_id = ?";
    $stmt = $conn->prepare($sqlDesglose);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resDesglose = $stmt->get_result();

    if ($resDesglose && $resDesglose->num_rows > 0) {
        $detalles = [];
        while ($row = $resDesglose->fetch_assoc()) {
            $detalles[] = [
                'id' => (int)$row['id'],
                'descripcion' => $row['descripcion'],
                'valor' => (float)$row['valor'],
                'cantidad' => (int)$row['cantidad'],
                'tipo_pago' => $row['tipo_pago'],
                'subtotal' => round((float)$row['subtotal'], 2)
            ];
        }

        echo json_encode([
            'success' => true,
            'corte_id' => $id,
            'detalles' => $detalles
        ]);
        exit;
    }

    // 2️⃣ Verificar si corresponde a una venta
    $sqlCheck = "SELECT COUNT(*) AS total FROM venta_detalles WHERE venta_id = ?";
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resCheck = $stmt->get_result()->fetch_assoc();

    $ventaId = $id;

    // 3️⃣ Si no hay registros, buscar en tickets
    if ($resCheck['total'] == 0) {
        $sqlTicket = "SELECT venta_id FROM tickets WHERE id = ?";
        $stmt = $conn->prepare($sqlTicket);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $resTicket = $stmt->get_result()->fetch_assoc();

        if ($resTicket && isset($resTicket['venta_id'])) {
            $ventaId = (int)$resTicket['venta_id'];
        } else {
            echo json_encode(["success" => false, "mensaje" => "No se encontraron registros"]);
            exit;
        }
    }

    // 4️⃣ Obtener datos de la venta
    $sqlVenta = "SELECT v.id, v.tipo_entrega, m.nombre AS mesa, u.nombre AS mesero
                 FROM ventas v
                 LEFT JOIN mesas m ON v.mesa_id = m.id
                 LEFT JOIN usuarios u ON v.usuario_id = u.id
                 WHERE v.id = ?";
    $stmt = $conn->prepare($sqlVenta);
    $stmt->bind_param('i', $ventaId);
    $stmt->execute();
    $venta = $stmt->get_result()->fetch_assoc();

    // 5️⃣ Obtener productos de la venta
    $sqlProductos = "SELECT vd.id, p.nombre, vd.cantidad, vd.precio_unitario,
                            (vd.cantidad * vd.precio_unitario) AS subtotal,
                            vd.estado_producto
                     FROM venta_detalles vd
                     LEFT JOIN productos p ON vd.producto_id = p.id
                     WHERE vd.venta_id = ?";
    $stmt = $conn->prepare($sqlProductos);
    $stmt->bind_param('i', $ventaId);
    $stmt->execute();
    $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!$venta && !$productos) {
        echo json_encode(["success" => false, "mensaje" => "No se encontraron registros"]);
        exit;
    }

    foreach ($productos as &$p) {
        $p['cantidad'] = (int)$p['cantidad'];
        $p['precio_unitario'] = round((float)$p['precio_unitario'], 2);
        $p['subtotal'] = round((float)$p['subtotal'], 2);
    }

    echo json_encode([
        'success' => true,
        'venta_id' => $ventaId,
        'tipo_entrega' => $venta['tipo_entrega'] ?? '',
        'mesa' => $venta['mesa'] ?? '',
        'mesero' => $venta['mesero'] ?? '',
        'productos' => $productos
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
}
