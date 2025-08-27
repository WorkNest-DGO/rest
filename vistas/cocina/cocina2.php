<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
$title = 'Cocina (Kanban)';
$rol_usuario = $_SESSION['rol'] ?? ($_SESSION['usuario']['rol'] ?? '');
ob_start();
?>
<div id="user-info" data-rol="<?= htmlspecialchars($rol_usuario, ENT_QUOTES); ?>" hidden></div>
<div class="page-header mb-0">
  <div class="container">
    <div class="row"><div class="col-12"><h2>Módulo de Cocina (Kanban)</h2></div></div>
  </div>
</div>

<div class="container my-3">
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <input id="txtFiltro" type="text" class="form-control" placeholder="Filtrar por producto/destino" style="max-width:280px">
    <select id="selTipoEntrega" class="form-control" style="max-width:180px">
      <option value="">Todos</option>
      <option value="mesa">Mesa</option>
      <option value="domicilio">Domicilio</option>
      <option value="rapido">Rápido</option>
    </select>
    <button id="btnRefrescar" class="btn custom-btn">Refrescar</button>
  </div>
</div>



<div id="kanban" class="kanban-container">
  <div class="kanban-board board-pendiente" data-status="pendiente">
    <h3>Pendiente</h3>
    <div class="kanban-dropzone" id="col-pendiente"></div>
  </div>
  <div class="kanban-board board-preparacion" data-status="en_preparacion">
    <h3>En preparación</h3>
    <div class="kanban-dropzone" id="col-preparacion"></div>
  </div>
  <div class="kanban-board board-listo" data-status="listo">
    <h3>Listo</h3>
    <div class="kanban-dropzone" id="col-listo"></div>
  </div>
  <div class="kanban-board board-entregado" data-status="entregado">
    <h3>Entregado</h3>
    <div class="kanban-dropzone" id="col-entregado"></div>
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
<script src="cocina2.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
