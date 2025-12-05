<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../impresoras/helpers.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

$ventaId = isset($_GET['venta_id']) ? (int)$_GET['venta_id'] : 0;
$detalleId = isset($_GET['detalle_id']) ? (int)$_GET['detalle_id'] : 0;
if ($ventaId <= 0) {
    http_response_code(400);
    echo 'venta_id requerido';
    exit;
}

function obtenerComanda(mysqli $db, int $ventaId, int $detalleId = 0): array {
    $info = [
        'venta_id' => $ventaId,
        'mesa' => '-',
        'mesero' => '-',
        'tipo_entrega' => '-',
        'observacion' => '',
        'productos' => [],
    ];

    if ($st = $db->prepare('SELECT v.id, v.observacion, v.tipo_entrega, v.mesa_id, m.nombre AS mesa_nombre, u.nombre AS mesero_nombre FROM ventas v LEFT JOIN mesas m ON m.id = v.mesa_id LEFT JOIN usuarios u ON u.id = v.usuario_id WHERE v.id = ? LIMIT 1')) {
        $st->bind_param('i', $ventaId);
        if ($st->execute()) {
            $r = $st->get_result()->fetch_assoc();
            if ($r) {
                $info['observacion'] = (string)($r['observacion'] ?? '');
                $info['tipo_entrega'] = (string)($r['tipo_entrega'] ?? '-');
                $info['mesa'] = $r['mesa_nombre'] ?? ($r['mesa_id'] ?? '-');
                $info['mesero'] = $r['mesero_nombre'] ?? '-';
            }
        }
        $st->close();
    }

    if ($detalleId > 0) {
        $det = $db->prepare('SELECT p.nombre, d.cantidad, d.precio_unitario FROM venta_detalles d JOIN productos p ON p.id = d.producto_id WHERE d.venta_id = ? AND d.id = ? ORDER BY d.id ASC');
        if ($det) $det->bind_param('ii', $ventaId, $detalleId);
    } else {
        $det = $db->prepare('SELECT p.nombre, d.cantidad, d.precio_unitario FROM venta_detalles d JOIN productos p ON p.id = d.producto_id WHERE d.venta_id = ? ORDER BY d.id ASC');
        if ($det) $det->bind_param('i', $ventaId);
    }
    if ($det) {
        if ($det->execute()) {
            $res = $det->get_result();
            while ($row = $res->fetch_assoc()) {
                $info['productos'][] = [
                    'nombre' => $row['nombre'] ?? '',
                    'cantidad' => (float)($row['cantidad'] ?? 0),
                    'precio_unitario' => (float)($row['precio_unitario'] ?? 0),
                ];
            }
        }
        $det->close();
    }

    return $info;
}

$printId = isset($_GET['print_id']) ? (int)$_GET['print_id'] : null;
$printerIp = obtener_impresora_ip($conn, $printId);
$connector = new WindowsPrintConnector($printerIp);
$printer = new Printer($connector);
$printer->initialize();

$comanda = obtenerComanda($conn, $ventaId, $detalleId);

// Encabezado simple
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer->text("COMANDA\n");
$printer->selectPrintMode();
$printer->text("Venta #" . $comanda['venta_id'] . "\n");
$printer->text("Fecha: " . date('Y-m-d H:i:s') . "\n");
$printer->feed();

$printer->setJustification(Printer::JUSTIFY_LEFT);
$printer->text("Mesa: " . ($comanda['mesa'] ?: '-') . "\n");
$printer->text("Mesero: " . ($comanda['mesero'] ?: '-') . "\n");
$printer->text("Tipo: " . ($comanda['tipo_entrega'] ?: '-') . "\n");
if (!empty($comanda['observacion'])) {
    $printer->text("Obs: " . $comanda['observacion'] . "\n");
}
$printer->text(str_repeat('-', 32) . "\n");

foreach ($comanda['productos'] as $p) {
    $linea = sprintf("%sx %s", rtrim(rtrim(number_format($p['cantidad'], 2), '0'), '.'), $p['nombre']);
    $printer->text($linea . "\n");
}

$printer->feed(2);
$printer->cut();
$printer->close();

echo 'OK';
