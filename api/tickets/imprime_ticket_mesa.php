<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../impresoras/helpers.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\EscposImage;

class ItemMesa
{
    private $name;
    private $price;
    private $dollarSign;
    private $cantidad;
    private $subtotal;

    public function __construct($name = '', $price = '', $dollarSign = false, $cantidad = '', $subtotal = '')
    {
        $this->name = $name;
        $this->price = $price;
        $this->dollarSign = $dollarSign;
        $this->cantidad = $cantidad;
        $this->subtotal = $subtotal;
    }

    public function getAsString($width = 32)
    {
        $rightCols = 10;
        $leftCols = $width - $rightCols;
        if ($this->dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $left = str_pad($this->cantidad . 'x ' . $this->name, $leftCols);
        $sign = $this->dollarSign ? '$ ' : '';
        $right = str_pad($sign . $this->price, $rightCols, ' ', STR_PAD_LEFT);
        $final = str_pad($sign . $this->subtotal, $rightCols, ' ', STR_PAD_LEFT);
        return $left . $right . $final . "\n";
    }

    public function __toString()
    {
        return $this->getAsString();
    }
}

$ventaId = isset($_GET['venta_id']) ? (int)$_GET['venta_id'] : 0;
if ($ventaId <= 0) {
    error('venta_id requerido');
}

function obtenerPrecuenta(mysqli $db, int $ventaId): ?array
{
    $info = [
        'venta_id' => $ventaId,
        'fecha' => null,
        'fecha_inicio' => null,
        'tipo_entrega' => '',
        'mesa_nombre' => '',
        'mesero_nombre' => '',
        'observacion' => '',
        'sede_id' => 1,
        'sede' => [
            'id' => 1,
            'nombre' => '',
            'direccion' => '',
            'rfc' => '',
            'telefono' => '',
        ],
        'promocion_id' => null,
        'promocion_nombre' => null,
        'promocion_descuento' => 0.0,
        'promociones' => [],
        'promociones_total_descuento' => 0.0,
        'productos' => [],
        'total_bruto' => 0.0,
        'descuento_total' => 0.0,
        'total_a_pagar' => 0.0,
        'tiempo_servicio' => '',
    ];

    $ventaStmt = $db->prepare('SELECT v.fecha, v.fecha_inicio, v.tipo_entrega, v.sede_id, v.mesa_id, v.usuario_id, v.observacion, v.promocion_descuento, v.promocion_id, m.nombre AS mesa_nombre, u.nombre AS mesero_nombre FROM ventas v LEFT JOIN mesas m ON m.id = v.mesa_id LEFT JOIN usuarios u ON u.id = v.usuario_id WHERE v.id = ? LIMIT 1');
    if (!$ventaStmt) {
        throw new RuntimeException('No se pudo preparar datos de venta: ' . $db->error);
    }
    $ventaStmt->bind_param('i', $ventaId);
    if (!$ventaStmt->execute()) {
        $ventaStmt->close();
        throw new RuntimeException('No se pudo obtener datos de venta: ' . $ventaStmt->error);
    }
    $ventaRow = $ventaStmt->get_result()->fetch_assoc();
    $ventaStmt->close();
    if (!$ventaRow) {
        return null;
    }

    $info['fecha'] = $ventaRow['fecha'] ?? null;
    $info['fecha_inicio'] = $ventaRow['fecha_inicio'] ?? null;
    $info['tipo_entrega'] = $ventaRow['tipo_entrega'] ?? '';
    $info['mesa_nombre'] = $ventaRow['mesa_nombre'] ?? '';
    $info['mesero_nombre'] = $ventaRow['mesero_nombre'] ?? '';
    $info['observacion'] = $ventaRow['observacion'] ?? '';
    $info['promocion_descuento'] = isset($ventaRow['promocion_descuento']) ? (float)$ventaRow['promocion_descuento'] : 0.0;
    $info['promocion_id'] = isset($ventaRow['promocion_id']) ? (int)$ventaRow['promocion_id'] : null;

    $sedeId = isset($ventaRow['sede_id']) && (int)$ventaRow['sede_id'] > 0 ? (int)$ventaRow['sede_id'] : 1;
    $info['sede_id'] = $sedeId;
    $info['sede']['id'] = $sedeId;
    $sedeStmt = $db->prepare('SELECT nombre, direccion, rfc, telefono FROM sedes WHERE id = ?');
    if ($sedeStmt) {
        $sedeStmt->bind_param('i', $sedeId);
        if ($sedeStmt->execute()) {
            $sedeRow = $sedeStmt->get_result()->fetch_assoc();
            if ($sedeRow) {
                $info['sede']['nombre'] = $sedeRow['nombre'] ?? '';
                $info['sede']['direccion'] = $sedeRow['direccion'] ?? '';
                $info['sede']['rfc'] = $sedeRow['rfc'] ?? '';
                $info['sede']['telefono'] = $sedeRow['telefono'] ?? '';
            }
        }
        $sedeStmt->close();
    }

    if (!empty($info['promocion_id'])) {
        $promoNombreStmt = $db->prepare('SELECT nombre FROM catalogo_promos WHERE id = ? LIMIT 1');
        if ($promoNombreStmt) {
            $promoNombreStmt->bind_param('i', $info['promocion_id']);
            if ($promoNombreStmt->execute()) {
                $rowP = $promoNombreStmt->get_result()->fetch_assoc();
                if ($rowP && !empty($rowP['nombre'])) {
                    $info['promocion_nombre'] = $rowP['nombre'];
                }
            }
            $promoNombreStmt->close();
        }
    }

    $promoStmt = $db->prepare('SELECT vp.promo_id, cp.nombre, COALESCE(vp.descuento_aplicado,0) AS descuento_aplicado FROM venta_promos vp JOIN catalogo_promos cp ON cp.id = vp.promo_id WHERE vp.venta_id = ? ORDER BY vp.id');
    if ($promoStmt) {
        $promoStmt->bind_param('i', $ventaId);
        if ($promoStmt->execute()) {
            $resPromo = $promoStmt->get_result();
            while ($rowPromo = $resPromo->fetch_assoc()) {
                $rowPromo['promo_id'] = (int)($rowPromo['promo_id'] ?? 0);
                $rowPromo['descuento_aplicado'] = isset($rowPromo['descuento_aplicado']) ? (float)$rowPromo['descuento_aplicado'] : 0.0;
                $info['promociones'][] = $rowPromo;
                $info['promociones_total_descuento'] += $rowPromo['descuento_aplicado'];
            }
        }
        $promoStmt->close();
    }

    $detalleStmt = $db->prepare('SELECT p.nombre, d.cantidad, d.precio_unitario, (d.cantidad * d.precio_unitario) AS subtotal FROM venta_detalles d JOIN productos p ON p.id = d.producto_id WHERE d.venta_id = ? ORDER BY d.id ASC');
    if ($detalleStmt) {
        $detalleStmt->bind_param('i', $ventaId);
        if ($detalleStmt->execute()) {
            $res = $detalleStmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $cantidad = isset($row['cantidad']) ? (float)$row['cantidad'] : 0;
                $precio = isset($row['precio_unitario']) ? (float)$row['precio_unitario'] : 0;
                $subtotal = isset($row['subtotal']) ? (float)$row['subtotal'] : ($cantidad * $precio);
                $info['productos'][] = [
                    'nombre' => $row['nombre'] ?? '',
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $subtotal,
                ];
                $info['total_bruto'] += $subtotal;
            }
        }
        $detalleStmt->close();
    }

    $info['descuento_total'] = max(0.0, $info['promocion_descuento'] + $info['promociones_total_descuento']);
    $info['total_a_pagar'] = max(0.0, $info['total_bruto'] - $info['descuento_total']);

    if (!empty($info['fecha_inicio'])) {
        $minutos = max(0, (int)floor((time() - strtotime($info['fecha_inicio'])) / 60));
        $horas = (int)floor($minutos / 60);
        $minsRestantes = $minutos % 60;
        $info['tiempo_servicio'] = $horas > 0 ? sprintf('%dh %02dm', $horas, $minsRestantes) : sprintf('%d min', $minsRestantes);
    }

    return $info;
}

$precuenta = obtenerPrecuenta($conn, $ventaId);
if (!$precuenta) {
    error('Venta no encontrada');
}

$printId = isset($_GET['print_id']) ? (int)$_GET['print_id'] : null;
$printer = null;

try {
    $printerIp = obtener_impresora_ip($conn, $printId);
    $connector = new WindowsPrintConnector($printerIp);
    $printer = new Printer($connector);
    $printer->initialize();

    $fmt = function ($n) {
        return number_format((float)$n, 2, '.', ',');
    };

    try {
        $logo = EscposImage::load('../../archivos/logo_login.png', true);
        $printer->bitImage($logo);
    } catch (Throwable $e) {
        // ignorar si no hay logo
    }

    $printer->setJustification(Printer::JUSTIFY_LEFT);
    $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
    $printer->text(($precuenta['sede']['nombre'] ?: 'Ticket de mesa') . "\n");
    $printer->selectPrintMode();
    if (!empty($precuenta['sede']['direccion'])) {
        $printer->text($precuenta['sede']['direccion'] . "\n");
    }
    if (!empty($precuenta['sede']['rfc'])) {
        $printer->text($precuenta['sede']['rfc'] . "\n");
    }
    if (!empty($precuenta['sede']['telefono'])) {
        $printer->text($precuenta['sede']['telefono'] . "\n");
    }
    $printer->text(date('Y-m-d H:i:s') . "\n");
    $printer->feed();

    $printer->setEmphasis(true);
    $printer->text('Venta: ' . $precuenta['venta_id'] . "\n");
    $printer->text('Sede: ' . $precuenta['sede']['id'] . "\n");
    $printer->text('Mesa: ' . ($precuenta['mesa_nombre'] ?: '-') . "\n");
    $printer->text('Mesero: ' . ($precuenta['mesero_nombre'] ?: '-') . "\n");
    $printer->text('Tipo entrega: ' . ($precuenta['tipo_entrega'] ?: '-') . "\n");
    if (!empty($precuenta['fecha_inicio'])) {
        $printer->text('Inicio: ' . $precuenta['fecha_inicio'] . "\n");
    }
    if (!empty($precuenta['tiempo_servicio'])) {
        $printer->text('Tiempo: ' . $precuenta['tiempo_servicio'] . "\n");
    }
    if (!empty($precuenta['observacion'])) {
        $printer->text('Obs: ' . $precuenta['observacion'] . "\n");
    }
    $printer->setEmphasis(false);

    foreach ($precuenta['productos'] as $prod) {
        $item = new ItemMesa(
            $prod['nombre'],
            $fmt($prod['precio_unitario']),
            true,
            rtrim(rtrim(number_format($prod['cantidad'], 2, '.', ''), '0'), '.'),
            $fmt($prod['subtotal'])
        );
        $printer->text($item->getAsString(32));
    }

    $printer->feed();
    $printer->setEmphasis(true);
    $printer->text(str_pad('Subtotal:', 20) . '$ ' . $fmt($precuenta['total_bruto']) . "\n");

    if ($precuenta['descuento_total'] > 0) {
        $printer->text(str_pad('Descuentos:', 20) . '-$ ' . $fmt($precuenta['descuento_total']) . "\n");
        foreach ($precuenta['promociones'] as $promo) {
            $nombrePromo = $promo['nombre'] ?? 'Promocion';
            $montoPromo = isset($promo['descuento_aplicado']) ? (float)$promo['descuento_aplicado'] : 0.0;
            $printer->text('- ' . $nombrePromo . ' $' . $fmt($montoPromo) . "\n");
        }
        if ($precuenta['promocion_descuento'] > 0 && empty($precuenta['promociones'])) {
            $printer->text('- Descuento venta $' . $fmt($precuenta['promocion_descuento']) . "\n");
        } elseif ($precuenta['promocion_descuento'] > 0 && !empty($precuenta['promocion_nombre'])) {
            $printer->text('- ' . $precuenta['promocion_nombre'] . ' $' . $fmt($precuenta['promocion_descuento']) . "\n");
        }
    }

    $printer->text(str_pad('Total:', 20) . '$ ' . $fmt($precuenta['total_a_pagar']) . "\n");
    $printer->setEmphasis(false);
    $printer->feed();
    $printer->text("Ticket de mesa \n");
    $printer->feed(2);
    $printer->cut();
    $printer->close();

    success(['mensaje' => 'Ticket enviado a impresora']);
} catch (Throwable $e) {
    if ($printer) {
        try {
            $printer->close();
        } catch (Throwable $ignored) {
        }
    }
    error('No se pudo imprimir: ' . $e->getMessage());
}
