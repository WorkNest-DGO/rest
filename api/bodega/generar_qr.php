<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/pdf_simple.php';
require_once __DIR__ . '/../../utils/phpqrcode/qrlib.php';

// Base de la URL donde se alojará el sistema para los códigos QR
if (!defined('URL_BASE_QR')) {
    define('URL_BASE_QR', 'http://192.168.100.48:80');
}

// Constante utilizada por la librería de QR
if (!defined('QR_ECLEVEL_H')) {
    define('QR_ECLEVEL_H', 'H');
}

if (!function_exists('generarToken')) {
    function generarToken() {
        return bin2hex(random_bytes(16));
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('No autenticado');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['insumos']) || !is_array($input['insumos'])) {
    error('Datos inválidos');
}

$usuario_id = (int)$_SESSION['usuario_id'];
$seleccionados = [];
foreach ($input['insumos'] as $d) {
    $id = isset($d['id']) ? (int)$d['id'] : 0;
    $cant = isset($d['cantidad']) ? (float)$d['cantidad'] : 0;
    if ($id > 0 && $cant > 0) {
        $q = $conn->prepare('SELECT nombre, unidad FROM insumo_bodega WHERE id = ?');
        if ($q) {
            $q->bind_param('i', $id);
            $q->execute();
            $res = $q->get_result();
            if ($row = $res->fetch_assoc()) {
                $seleccionados[] = ['id' => $id, 'nombre' => $row['nombre'], 'unidad' => $row['unidad'], 'cantidad' => $cant];
            }
            $q->close();
        }
    }
}
if (count($seleccionados) === 0) {
    error('No se seleccionaron insumos');
}

$stmtU = $conn->prepare('SELECT nombre FROM usuarios WHERE id = ?');
$stmtU->bind_param('i', $usuario_id);
$stmtU->execute();
$stmtU->bind_result($usuario_nombre);
$stmtU->fetch();
$stmtU->close();

$token = generarToken();
$urlQR = URL_BASE_QR . '/rest/vistas/bodega/recepcion_qr.php?token=' . $token;
$json = json_encode($seleccionados, JSON_UNESCAPED_UNICODE);
$ins = $conn->prepare('INSERT INTO qrs_insumo (token, json_data, creado_por) VALUES (?, ?, ?)');
$ins->bind_param('ssi', $token, $json, $usuario_id);
if (!$ins->execute()) {
    $ins->close();
    error('Error al guardar');
}
$idqr = $ins->insert_id;
$ins->close();

$dirPdf = __DIR__ . '/../../archivos/bodega/pdfs';
if (!is_dir($dirPdf)) {
    mkdir($dirPdf, 0777, true);
}
$dirQrPublic = __DIR__ . '/../../archivos/qr';
if (!is_dir($dirQrPublic)) {
    mkdir($dirQrPublic, 0777, true);
}


$pdf_rel = 'archivos/bodega/pdfs/qr_' . $token . '.pdf';
$pdf_path = __DIR__ . '/../../' . $pdf_rel;
$public_qr_rel = 'archivos/qr/' . $token . '.png';
$public_qr_path = __DIR__ . '/../../' . $public_qr_rel;

QRcode::png($urlQR, $public_qr_path, QR_ECLEVEL_H, 8, 2);
if (!file_exists($public_qr_path)) {
    error('No se pudo generar el código QR');
}

$lineas = [];
$lineas[] = 'Fecha: ' . date('Y-m-d H:i');
$lineas[] = 'Entregado por: ' . $usuario_nombre;
foreach ($seleccionados as $s) {
    $lineas[] = $s['nombre'] . ' - ' . $s['cantidad'] . ' ' . $s['unidad'];
}

generar_pdf_con_imagen($pdf_path, 'Salida de insumos', $lineas, $public_qr_path, 150, 10, 40, 40);

$up = $conn->prepare('UPDATE qrs_insumo SET pdf_envio = ? WHERE id = ?');
$up->bind_param('si', $pdf_rel, $idqr);
$up->execute();
$up->close();

$mov = $conn->prepare("INSERT INTO movimientos_insumos (tipo, usuario_id, insumo_id, cantidad, qr_token) VALUES ('salida', ?, ?, ?, ?)");
foreach ($seleccionados as $s) {
    $mov->bind_param('iids', $usuario_id, $s['id'], $s['cantidad'], $token);
    $mov->execute();
}
$mov->close();

$log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
if ($log) {
    $mod = 'bodega';
    $accion = 'Generacion QR';
    $log->bind_param('issi', $usuario_id, $mod, $accion, $idqr);
    $log->execute();
    $log->close();
}

$base_url = defined('BASE_URL') ? BASE_URL : '/rest';
$url = $base_url . '/vistas/bodega/recepcion_qr.php?token=' . $token;

success([
    'pdf_url' => $pdf_rel,
    'qr_url'  => $public_qr_rel,
    'url'     => $url
]);
?>

