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

function column_exists(mysqli $db, string $table, string $column): bool {
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1";
    $st = $db->prepare($sql);
    if (!$st) {
        return false;
    }
    $st->bind_param('ss', $table, $column);
    $ok = false;
    if ($st->execute()) {
        $res = $st->get_result();
        $ok = $res && $res->num_rows > 0;
    }
    $st->close();
    return $ok;
}

function obtenerComandaAgrupada(mysqli $db, int $ventaId, int $detalleId = 0, ?int $printIdFallback = null): array {
    $info = [
        'venta_id' => $ventaId,
        'mesa' => '-',
        'mesero' => '-',
        'tipo_entrega' => '-',
        'observacion' => '',
        'grupos' => [],
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

    $catCol = column_exists($db, 'productos', 'categoria_id') ? 'categoria_id' : (column_exists($db, 'productos', 'categoria') ? 'categoria' : null);
    $impCol = column_exists($db, 'catalogo_categorias', 'impresoras_id') ? 'impresoras_id' : (column_exists($db, 'catalogo_categorias', 'impresora_id') ? 'impresora_id' : null);

    $selectImp = $impCol ? "cc.`$impCol` AS impresora_id" : "NULL AS impresora_id";
    $joinCat = $catCol ? "LEFT JOIN catalogo_categorias cc ON cc.id = p.`$catCol`" : "LEFT JOIN catalogo_categorias cc ON 1=0";
    $baseSql = "SELECT p.nombre, d.cantidad, d.precio_unitario, $selectImp FROM venta_detalles d JOIN productos p ON p.id = d.producto_id $joinCat WHERE d.venta_id = ?";

    if ($detalleId > 0) {
        $det = $db->prepare($baseSql . ' AND d.id = ? ORDER BY d.id ASC');
        if ($det) $det->bind_param('ii', $ventaId, $detalleId);
    } else {
        $det = $db->prepare($baseSql . ' ORDER BY d.id ASC');
        if ($det) $det->bind_param('i', $ventaId);
    }
    if ($det) {
        if ($det->execute()) {
            $res = $det->get_result();
            while ($row = $res->fetch_assoc()) {
                $impId = isset($row['impresora_id']) ? (int)$row['impresora_id'] : 0;
                if ($impId <= 0 && $printIdFallback) {
                    $impId = (int)$printIdFallback;
                }
                if (!isset($info['grupos'][$impId])) {
                    $info['grupos'][$impId] = [];
                }
                $info['grupos'][$impId][] = [
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

$printIdFallback = isset($_GET['print_id']) ? (int)$_GET['print_id'] : null;
$comanda = obtenerComandaAgrupada($conn, $ventaId, $detalleId, $printIdFallback);
$grupos = $comanda['grupos'] ?? [];
if (!empty($grupos)) {
    ksort($grupos, SORT_NUMERIC);
}

foreach ($grupos as $impId => $productos) {
    if (empty($productos)) {
        continue;
    }
    $printerIp = obtener_impresora_ip($conn, ($impId > 0 ? (int)$impId : $printIdFallback));
    $connector = new WindowsPrintConnector($printerIp);
    $printer = new Printer($connector);
    $printer->initialize();

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

    foreach ($productos as $p) {
        $linea = sprintf("%sx %s", rtrim(rtrim(number_format($p['cantidad'], 2), '0'), '.'), $p['nombre']);
        $printer->text($linea . "\n");
    }

    $printer->feed(2);
    $printer->cut();
    $printer->close();
}

echo 'OK';
