<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$res = $conn->query('SELECT id, nombre, unidad, existencia FROM insumos');
$insumos = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$title = 'Generar QR';
ob_start();
?>
<!-- Page Header Start -->
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de CDI</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Catálogo de almacen CDIs</a>
            </div>
        </div>
    </div>
</div>
<div class="container mt-4">
    <h2 class="text-white">Generar QR para salida de insumos</h2>
    <div id="resultado" class="mb-3"></div>
    <div id="resultado2" class="mb-3"></div>
    <form id="formQR">
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
                            <td><input type="number" step="0.01" min="0" data-id="<?= $i['id'] ?>" class="form-control"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" id="btnGenerar" class="btn custom-btn mt-3">Generar QR</button>
    </form>
</div>
<!-- Modal global de mensajes -->
<div class="modal fade" id="appMsgModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Mensaje</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<script>
    function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;
document.getElementById('btnGenerar').addEventListener('click', async function(e){
    e.preventDefault();
    const insumos = [];
    document.querySelectorAll('#formQR input[data-id]').forEach(inp => {
        const cantidad = parseFloat(inp.value);
        if(cantidad > 0){
            insumos.push({id: parseInt(inp.getAttribute('data-id')), cantidad});
        }
    });
    if(insumos.length === 0){
        alert('Ingresa cantidades válidas');
        return;
    }
    try {
        const resp = await fetch('../../api/bodega/generar_qr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ insumos })
        });
        const data = await resp.json();
        if(data.success){
            const url = data.resultado.url;
            const pdf = '../../' + data.resultado.pdf_url;
            const img = '../../' + data.resultado.qr_url;
            document.getElementById('resultado').innerHTML =
                '<p class="text-white">Escanea el código para recibir:</p>'+
                '<img src="'+img+'" alt="QR" width="200" height="200">'+
                '<p class="mt-2"><a class="btn custom-btn" href="'+pdf+'" target="_blank">Ver PDF</a></p>'+
                '<p class="mt-2"><a class="btn custom-btn" href="../../api/bodega/imprimir_qr.php?qrName='+img+'"  target="_blank">Imprimirs PDF</a></p>';
           
        } else {
            alert(data.mensaje || 'Error');
        }
    } catch(err){
        console.error(err);
        alert('Error de comunicación');
    }
});
</script>
<?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>

