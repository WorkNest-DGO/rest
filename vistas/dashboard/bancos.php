<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
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

$title = 'Bancos';
ob_start();
?>
<!-- Page Header Start -->
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <h2>Bancos</h2>
      </div>
      <div class="col-12">
        <a href="../dash.php">Panel de Administrador</a>
        <a>Bancos</a>
      </div>
    </div>
  </div>
</div>

<div class="container mt-5 mb-5">

  <div class="mb-2">
    <a href="../dash.php">
      <button class="btn custom-btn">&#x1F814;</button>
    </a>
  </div>

  <h1 class="section-header">Catálogo de Bancos</h1>

  <div class="mb-3 text-end">
    <button class="btn custom-btn" id="agregarBanco">Agregar Banco</button>
  </div>

  <div class="table-responsive">
    <table id="tablaBancos" class="styled-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- Form-->
<div class="modal fade" id="modalAgregar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form id="formAgregar">
        <input type="hidden" id="bancoId">
        <!-- disabled -->
        <div class="modal-header">
          <h5 class="modal-title" id="modalTituloForm">Completa los campos</h5>
          <button type="button" class="close" onclick="cerrarModalAgregar()" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="nombreBanco">Nombre:</label>
            <input type="text" id="nombreBanco" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn custom-btn" type="submit">Guardar</button>
          <button class="btn custom-btn" type="button" onclick="cerrarModalAgregar()">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal global de mensajes -->
<div class="modal fade" id="appMsgModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
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
<script src="/../utils/js/modal-lite.js"></script>
<script src="bancos.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
