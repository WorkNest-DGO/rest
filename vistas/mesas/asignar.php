<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
// Base app dinámica y ruta relativa para validación
$__sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$__pos = strpos($__sn, '/vistas/');
$__app_base = $__pos !== false ? substr($__sn, 0, $__pos) : rtrim(dirname($__sn), '/');
$path_actual = preg_replace('#^' . preg_quote($__app_base, '#') . '#', '', ($__sn ?: $_SERVER['PHP_SELF']));
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
$title = 'Asignar Mesas';
ob_start();
?>
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Meseros</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Catálogo de Mesas por mesero</a>
            </div>
        </div>
    </div>
</div>


<h1 class="section-header">Asignar meseros a mesas</h1>
<div class="filtros-container mb-2">
  <label for="buscarAsignacion" class="me-2">Buscar:</label>
  <input type="text" id="buscarAsignacion" class="form-control" placeholder="Mesa o mesero">
  </div>
  <div class="row mt-2">
  <div class="col-12">
    <ul id="paginadorAsignar" class="pagination justify-content-center"></ul>
  </div>
</div>
<div class="table-responsive">
<table id="tablaAsignacion" class="styled-table">
    <thead>
        <tr><th>Mesa</th><th>Mesero</th></tr>
    </thead>
    <tbody></tbody>
</table>
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
<script>
  window.usuarioId = <?php echo json_encode($_SESSION['usuario_id']); ?>;
</script>
<script src="asignar.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
