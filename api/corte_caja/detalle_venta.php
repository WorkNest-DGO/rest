<?php
header('Content-Type: application/json');
require_once '../../config/db.php'; // Ajusta la ruta a tu archivo de conexión

$id = 0;

// Intentar desde JSON
$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['id'])) {
    $id = intval($input['id']);
}

// Intentar desde GET
if ($id <= 0 && isset($_GET['id'])) {
    $id = intval($_GET['id']);
}

// Intentar desde POST clásico
if ($id <= 0 && isset($_POST['id'])) {
    $id = intval($_POST['id']);
}

if ($id <= 0) {
    echo json_encode(["success" => false, "mensaje" => "ID no válido"]);
    exit;
}

try {
    // 1️⃣ Verificar si es venta_id válido
    $sqlCheck = "SELECT COUNT(*) AS total FROM venta_detalles WHERE venta_id = ?";
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resCheck = $stmt->get_result()->fetch_assoc();

    $ventaId = $id;

    // 2️⃣ Si no hay registros, buscar en tickets
    if ($resCheck['total'] == 0) {
        $sqlTicket = "SELECT venta_id FROM tickets WHERE id = ?";
        $stmt = $conn->prepare($sqlTicket);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resTicket = $stmt->get_result()->fetch_assoc();

        if ($resTicket && isset($resTicket['venta_id'])) {
            $ventaId = intval($resTicket['venta_id']);
        } else {
            echo json_encode(["success" => false, "mensaje" => "No se encontró venta para el ID proporcionado"]);
            exit;
        }
    }

    // 3️⃣ Obtener datos de la venta
    $sqlVenta = "SELECT v.id, v.tipo_entrega, m.nombre AS mesa, u.nombre AS mesero
                 FROM ventas v
                 LEFT JOIN mesas m ON v.mesa_id = m.id
                 LEFT JOIN usuarios u ON v.usuario_id = u.id
                 WHERE v.id = ?";
    $stmt = $conn->prepare($sqlVenta);
    $stmt->bind_param("i", $ventaId);
    $stmt->execute();
    $venta = $stmt->get_result()->fetch_assoc();

    // 4️⃣ Obtener productos de la venta
    $sqlProductos = "SELECT vd.id, p.nombre, vd.cantidad, vd.precio_unitario, 
                            (vd.cantidad * vd.precio_unitario) AS subtotal,
                            vd.estado_producto
                     FROM venta_detalles vd
                     LEFT JOIN productos p ON vd.producto_id = p.id
                     WHERE vd.venta_id = ?";
    $stmt = $conn->prepare($sqlProductos);
    $stmt->bind_param("i", $ventaId);
    $stmt->execute();
    $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 5️⃣ Respuesta
    echo json_encode([
        "success" => true,
        "venta_id" => $ventaId,
        "tipo_entrega" => $venta['tipo_entrega'] ?? "",
        "mesa" => $venta['mesa'] ?? "",
        "mesero" => $venta['mesero'] ?? "",
        "productos" => $productos
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
}
