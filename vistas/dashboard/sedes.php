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

$title = 'sedes';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <h2>Sedes</h2>
      </div>
      <div class="col-12">
        <a href="../dash.php">Dashboard ADMIN</a>
        <a>Sedes</a>
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
  
  <h1 class="section-header">Catálogo de Sedes</h1>

  <div class="mb-3 text-end">
    <button class="btn custom-btn" id="agregarSede">Agregar Sede</button>
  </div>

  <div class="table-responsive">
    <table id="tablaSedes" class="styled-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Direccion</th>
          <th>RFC</th>
          <th>Telefono</th>
          <th>Correo</th>
          <th>Web</th>
          <th>Activo</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- Form-->
<div class="modal fade" id="modalAgregar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formAgregar">
        <input type="hidden" id="sedeId">
        <!-- disabled -->
        <div class="modal-header">
          <h5 class="modal-title" id="modalTituloForm">Completa los campos</h5>
          <button type="button" class="close" onclick="cerrarModalAgregar()" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label for="nombreSede">Nombre:</label>
            <input type="text" id="nombreSede" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="direccionSede">Direccion:</label>
            <input type="text" id="direccionSede" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="rfcSede">RFC:</label>
            <input type="text" id="rfcSede" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="telefonoSede">Telefono:</label>
            <input type="text" id="telefonoSede" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="correoSede">Correo:</label>
            <input type="email" id="correoSede" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="webSede">Web:</label>
            <input type="text" id="webSede" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="activoSede">Activo:</label>
            <select id="activoSede" class="form-control">
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
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
<script src="/../utils/js/modal-lite.js"></script>
<script src="sedes.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
