<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/pdf_simple.php';

$usuario_id = $_SESSION['usuario_id'];
$stmtU = $conn->prepare('SELECT nombre FROM usuarios WHERE id = ?');
$stmtU->bind_param('i', $usuario_id);
$stmtU->execute();
$stmtU->bind_result($usuario_nombre);
$stmtU->fetch();
$stmtU->close();

$mensaje = '';
$qr_url = '';
$pdf_envio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cantidades = $_POST['cantidad'] ?? [];
    $seleccionados = [];
    foreach ($cantidades as $id => $cant) {
        $cant = (float)$cant;
        if ($cant > 0) {
            $id = (int)$id;
            $q = $conn->prepare('SELECT nombre, unidad FROM insumo_bodega WHERE id = ?');
            $q->bind_param('i', $id);
            $q->execute();
            $res = $q->get_result();
            if ($row = $res->fetch_assoc()) {
                $seleccionados[] = ['id' => $id, 'nombre' => $row['nombre'], 'unidad' => $row['unidad'], 'cantidad' => $cant];
            }
            $q->close();
        }
    }

    if (count($seleccionados) === 0) {
        $mensaje = 'No se seleccionaron insumos.';
    } else {
        $token = bin2hex(random_bytes(16));
        $json = json_encode($seleccionados, JSON_UNESCAPED_UNICODE);
        $ins = $conn->prepare('INSERT INTO qrs_insumo (token, json_data, creado_por) VALUES (?, ?, ?)');
        $ins->bind_param('ssi', $token, $json, $usuario_id);
        if ($ins->execute()) {
            $idqr = $ins->insert_id;
            $dirPdf = __DIR__ . '/../../uploads/qrs';
            if (!is_dir($dirPdf)) {
                mkdir($dirPdf, 0777, true);
            }
            $pdf_envio = 'uploads/qrs/envio_' . $token . '.pdf';
            $lineas = [];
            $lineas[] = 'Fecha: ' . date('Y-m-d H:i');
            $lineas[] = 'Entregado por: ' . $usuario_nombre;
            foreach ($seleccionados as $s) {
                $lineas[] = $s['nombre'] . ' - ' . $s['cantidad'] . ' ' . $s['unidad'];
            }
            generar_pdf_simple(__DIR__ . '/../../' . $pdf_envio, 'Salida de insumos', $lineas);
            $up = $conn->prepare('UPDATE qrs_insumo SET pdf_envio = ? WHERE id = ?');
            $up->bind_param('si', $pdf_envio, $idqr);
            $up->execute();
            $up->close();

            $mov = $conn->prepare("INSERT INTO movimientos_insumos (tipo, usuario_id, insumo_id, cantidad, qr_token) VALUES ('salida', ?, ?, ?, ?)");
            foreach ($seleccionados as $s) {
                $mov->bind_param('iids', $usuario_id, $s['id'], $s['cantidad'], $token);
                $mov->execute();
            }
            $mov->close();

            $qr_url = (defined('BASE_URL') ? BASE_URL : '/rest') . '/vistas/bodega/recepcion_qr.php?token=' . $token;
            $mensaje = 'QR generado correctamente';
        } else {
            $mensaje = 'Error al guardar';
        }
        $ins->close();
    }
}

$res = $conn->query('SELECT id, nombre, unidad, existencia FROM insumo_bodega');
$insumos = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$title = 'Generar QR';
ob_start();
?>

<div class="container mt-4">
    <h2 class="text-white">Generar QR para salida de insumos</h2>
    <?php if ($mensaje): ?>
        <p class="text-white"><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($qr_url): ?>
        <div class="mb-3">
            <p class="text-white">Escanea el código para recibir:</p>
            <img src="https://chart.googleapis.com/chart?chs=200x200&amp;cht=qr&amp;chl=<?= urlencode($qr_url) ?>" alt="QR">
            <p class="mt-2"><a class="btn custom-btn" href="../../<?= $pdf_envio ?>" target="_blank">Ver PDF</a></p>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="table-responsive">
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>Insumo</th>
                        <th>Existencia</th>
                        <th>Unidad</th>
                        <th>Cantidad a enviar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insumos as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['nombre']) ?></td>
                            <td><?= $i['existencia'] ?></td>
                            <td><?= htmlspecialchars($i['unidad']) ?></td>
                            <td><input type="number" step="0.01" min="0" name="cantidad[<?= $i['id'] ?>]" class="form-control"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn custom-btn mt-3">Generar QR</button>
    </form>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>

