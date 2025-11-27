<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/db.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\GdEscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class item
{
    private $name;
    private $price;
    private $dollarSign;

    public function __construct($name = '', $price = '', $dollarSign = false)
    {
        $this->name = $name;
        $this->price = $price;
        $this->dollarSign = $dollarSign;
    }

    public function getAsString($width = 48)
    {
        $rightCols = 10;
        $leftCols = $width - $rightCols;
        if ($this->dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $left = str_pad($this->name, $leftCols);

        $sign = ($this->dollarSign ? '$ ' : '');
        $right = str_pad($sign . $this->price, $rightCols, ' ', STR_PAD_LEFT);
        return "$left$right\n";
    }

    public function __toString()
    {
        return $this->getAsString();
    }
}

$rawDatos = $_GET['datos'] ?? '';
$jsonDatos = is_string($rawDatos) ? rawurldecode($rawDatos) : '';
$datosArr = json_decode($jsonDatos, true);
if (!is_array($datosArr)) {
    $datosArr = [];
}

// Normaliza: algunas APIs devuelven el payload dentro de "resultado"
$r = $datosArr['resultado'] ?? $datosArr;
$metodosPago = ['efectivo', 'boucher', 'cheque', 'tarjeta', 'transferencia'];
$fmt = function ($n) {
    return number_format((float)$n, 2, '.', '');
};
$num = function ($v) {
    return is_numeric($v) ? (float)$v : 0.0;
};

// Totales principales
if (!isset($r['total_productos']) && isset($r['productos']['total'])) {
    $r['total_productos'] = $r['productos']['total'];
}
if (!isset($r['total_descuento_promos']) && isset($r['promociones_aplicadas']['total_descuento'])) {
    $r['total_descuento_promos'] = $r['promociones_aplicadas']['total_descuento'];
}
$totalProductos = $num($r['total_productos'] ?? 0);
$totalBruto = $num($r['total_bruto'] ?? $totalProductos);
$totalDescuentos = $num($r['total_descuentos'] ?? 0);
$totalPromos = $num($r['total_descuento_promos'] ?? 0);
$totalEsperado = $num($r['total_esperado'] ?? ($r['totalEsperado'] ?? ($totalBruto - $totalDescuentos)));
$totalEsperadoVisible = $totalPromos > 0 ? ($totalEsperado - $totalPromos) : $totalEsperado;

// Caja y movimientos
$fondo = $num($r['fondo'] ?? $r['fondo_inicial'] ?? 0);
$totalDepositos = $num($r['total_depositos'] ?? ($r['movimientos_caja']['depositos'] ?? 0));
$totalRetiros = $num($r['total_retiros'] ?? ($r['movimientos_caja']['retiros'] ?? 0));
$totalFinalEf = $num($r['totalFinalEfectivo'] ?? $r['efectivo_caja'] ?? 0);
$totalFinalGral = $num($r['totalFinalGeneral'] ?? $r['saldo_final'] ?? 0);
$totalIngresado = $totalFinalEf + $totalDepositos - $totalRetiros;

// Propinas
$propEf = $num($r['total_propina_efectivo'] ?? ($r['propinas']['efectivo'] ?? 0));
$propCh = $num($r['total_propina_cheque'] ?? ($r['propinas']['cheque'] ?? 0));
$propTa = $num($r['total_propina_tarjeta'] ?? ($r['propinas']['tarjeta'] ?? 0));
$totalPropinas = $num($r['total_propinas'] ?? ($r['propinas']['total'] ?? ($propEf + $propCh + $propTa)));

// Totales por tipo de pago y esperados
$totalesPago = [];
$esperadoPago = [];
foreach ($metodosPago as $m) {
    $totalesPago[$m] = $num($r[$m]['total'] ?? 0);
    $esperadoPago[$m] = $num($r['esperado_' . $m] ?? 0);
}
// Si vienen solo en formas_pago_resumen, mapearlos
if (isset($r['formas_pago_resumen']) && is_array($r['formas_pago_resumen'])) {
    $fp = $r['formas_pago_resumen'];
    if (!$totalesPago['efectivo'] && isset($fp['efectivo']['total_neto'])) {
        $totalesPago['efectivo'] = $num($fp['efectivo']['total_neto']);
    }
    if (!$totalesPago['tarjeta'] && isset($fp['tarjeta']['total_neto'])) {
        $totalesPago['tarjeta'] = $num($fp['tarjeta']['total_neto']);
    }
    if (!$totalesPago['boucher'] && isset($fp['otros']['total_neto'])) {
        $totalesPago['boucher'] = $num($fp['otros']['total_neto']);
    }
}

// Cuentas y folios
$cuentas = $r['cuentas_por_estatus'] ?? [];
$cuentasAbiertas = $cuentas['abiertas']['cantidad'] ?? ($r['cuentas_activas'] ?? 0);
$totalAbiertas = $cuentas['abiertas']['total'] ?? ($r['total_cuentas_activas'] ?? 0);
$cuentasCanc = $cuentas['cerradas']['cantidad'] ?? ($r['cuentas_canceladas'] ?? 0);
$totalCanc = $cuentas['cerradas']['total'] ?? ($r['total_cuentas_canceladas'] ?? 0);

$folios = $r['folios'] ?? [];
$folioInicio = $folios['inicio'] ?? ($r['folio_inicio'] ?? null);
$folioFin = $folios['fin'] ?? ($r['folio_fin'] ?? null);
$folioTotal = $folios['total'] ?? ($r['total_folios'] ?? null);

$meseros = $r['total_meseros'] ?? $r['totales_mesero'] ?? [];
$repartidores = $r['total_repartidor'] ?? $r['totales_repartidor'] ?? [];

//$connector = new WindowsPrintConnector("smb://ip_maquina/nombre_impresora");
$connector = new WindowsPrintConnector("smb://FUED/pos58");
//$connector = new WindowsPrintConnector("smb://DESKTOP-O4CO4GV/58");
//$connector = new FilePrintConnector("php://stdout");
$printer = new Printer($connector);
$printer->initialize();

// Cabecera
$printer->setJustification(Printer::JUSTIFY_LEFT);
$filename = "../../archivos/logo_login2.png";
$logo = EscposImage::load($filename, true);
$printer->bitImage($logo);
$printer->feed();
$printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer->text("Corte de Caja Temporal \n");
$printer->selectPrintMode();
$printer->setJustification(Printer::JUSTIFY_LEFT);

$printer->text((new item('Corte', $r['corte_id'] ?? '-', false))->getAsString(32));
$printer->text((new item('Inicio', $r['fecha_inicio'] ?? '-', false))->getAsString(32));
$printer->text((new item('Fin', $r['fecha_fin'] ?? '-', false))->getAsString(32));
$folioTxt = trim(($folioInicio !== null ? $folioInicio : '-') . " - " . ($folioFin !== null ? $folioFin : '-') . " (" . ($folioTotal ?? 0) . ")");
$printer->text((new item('Folios', $folioTxt, false))->getAsString(48));
$printer->feed();

// Totales de venta
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer->text("Totales de venta\n");
$printer->selectPrintMode();
$printer->setJustification(Printer::JUSTIFY_LEFT);
$printer->text((new item('Total bruto', $fmt($totalBruto), true))->getAsString(32));
$printer->text((new item('Descuentos', $fmt($totalDescuentos), true))->getAsString(32));
if ($totalPromos > 0) {
    $printer->text((new item('Promociones', $fmt($totalPromos), true))->getAsString(32));
}
$printer->text((new item('Total esperado', $fmt($totalEsperadoVisible), true))->getAsString(32));
$printer->feed();

// Caja y movimientos
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer->text("Caja y movimientos\n");
$printer->selectPrintMode();
$printer->setJustification(Printer::JUSTIFY_LEFT);
$printer->text((new item('Fondo inicial', $fmt($fondo), true))->getAsString(32));
$printer->text((new item('Depositos', $fmt($totalDepositos), true))->getAsString(32));
$printer->text((new item('Retiros', $fmt($totalRetiros), true))->getAsString(32));
$printer->text((new item('Total propinas', $fmt($totalPropinas), true))->getAsString(32));
$printer->text((new item('Efectivo en caja', $fmt($totalFinalEf), true))->getAsString(32));
$printer->text((new item('Total final', $fmt($totalFinalGral), true))->getAsString(32));
$printer->text((new item('Total ingresado', $fmt($totalIngresado), true))->getAsString(32));
$printer->feed();

// Totales por tipo de pago
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer->text("Totales por tipo de pago\n");
$printer->selectPrintMode();
$printer->setJustification(Printer::JUSTIFY_LEFT);
foreach ($metodosPago as $m) {
    $label = ucfirst($m);
    $printer->text((new item($label, $fmt($totalesPago[$m]), true))->getAsString(32));
}
$printer->feed();

// Esperados por tipo de pago
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer->text("Esperado por tipo de pago\n");
$printer->selectPrintMode();
$printer->setJustification(Printer::JUSTIFY_LEFT);
foreach ($metodosPago as $m) {
    $label = ucfirst($m);
    $printer->text((new item($label, $fmt($esperadoPago[$m]), true))->getAsString(32));
}
$printer->feed();

// Propinas
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer->text("Propinas por tipo de pago\n");
$printer->selectPrintMode();
$printer->setJustification(Printer::JUSTIFY_LEFT);
$printer->text((new item('Efectivo', $fmt($propEf), true))->getAsString(32));
$printer->text((new item('Transferencia', $fmt($propCh), true))->getAsString(32));
$printer->text((new item('Tarjeta', $fmt($propTa), true))->getAsString(32));
$printer->feed();

// Cuentas por estatus
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer->text("Cuentas por estatus\n");
$printer->selectPrintMode();
$printer->setJustification(Printer::JUSTIFY_LEFT);
$printer->text((new item('Abiertas', ($cuentasAbiertas ?? 0) . ' / ' . $fmt($totalAbiertas), false))->getAsString(48));
$printer->text((new item('Canceladas', ($cuentasCanc ?? 0) . ' / ' . $fmt($totalCanc), false))->getAsString(48));
$printer->feed();

// Totales por mesero
if (!empty($meseros)) {
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
    $printer->text("Totales por mesero\n");
    $printer->selectPrintMode();
    $printer->setJustification(Printer::JUSTIFY_LEFT);
    foreach ($meseros as $m) {
        $nombre = $m['nombre'] ?? '';
        $total = $fmt($m['total'] ?? $m['total_neto'] ?? 0);
        $printer->text((new item($nombre, $total, true))->getAsString(32));
    }
    $printer->feed();
}

// Totales por repartidor
if (!empty($repartidores)) {
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
    $printer->text("Totales por repartidor\n");
    $printer->selectPrintMode();
    $printer->setJustification(Printer::JUSTIFY_LEFT);
    foreach ($repartidores as $rep) {
        $nombre = $rep['nombre'] ?? '';
        $total = $fmt($rep['total'] ?? $rep['total_neto'] ?? 0);
        $printer->text((new item($nombre, $total, true))->getAsString(32));
    }
    $printer->feed();
}

// Folios finales
$printer->text("Fecha inicio: " . ($r['fecha_inicio'] ?? '-') . "\n");
$printer->text("Folio inicio: " . ($folioInicio ?? '-') . "\n");
$printer->text("Folio final: " . ($folioFin ?? '-') . "\n");
$printer->text("Total folios: " . ($folioTotal ?? '-') . "\n");

// Corta
$printer->cut();
$printer->close();
echo "enviado";
