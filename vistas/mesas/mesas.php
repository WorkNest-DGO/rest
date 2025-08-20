<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
/*$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}*/
$title = 'Mesas';
ob_start();
?>
<!-- Dragula -->
<link rel="stylesheet" href="../../utils/css/dragula.min.css">

<script src="../../utils/js/dragula.min.js"></script>

<link href="../../utils/css/style2.css" rel="stylesheet">
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Meseros</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Catálogo de Mesas</a>
            </div>
        </div>
    </div>
</div>

<h1>Mesas</h1>
<div hidden>
    <button class="btn custom-btn" id="btn-unir">Unir mesas</button>
    <select id="filtro-area"></select>
</div>
<div id="tablero"></div>
<div id="modal-detalle" style="display:none;"></div>

<div>
<section class="section">
	<h1>Distribución de mesas</h1>
	<h4>Tokyo Sushy Prime </h4>
</section>

<div class="drag-container">
        <ul class="drag-list" id="kanban-list"></ul>
</div>

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

<?php require_once __DIR__ . '/../footer.php'; ?>

<link href="../../utils/css/select2.min.css" rel="stylesheet" />
<script src="../../utils/js/select2.min.js"></script>

<script>
window.usuarioActual = {
    id: <?= (int)($_SESSION['usuario_id'] ?? 0); ?>,
    rol: <?= json_encode($_SESSION['rol'] ?? ''); ?>
};
</script>
<script src="kanbanMesas.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
