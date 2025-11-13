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

$title = 'Mesas CRUD';
ob_start();
?>
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12"><h2>Catálogo de Mesas</h2></div>
      <div class="col-12">
        <a href="">Inicio</a>
        <a href="">Mesas</a>
      </div>
    </div>
  </div>
  </div>

<div class="container mt-4 mb-5">
  <div class="section-header text-center">
    <p>Administración</p>
    <h2>Mesas (CRUD)</h2>
  </div>

  <div class="card p-3 mb-3">
    <h5 id="formTitle">Nueva mesa</h5>
    <form id="mesaForm">
      <input type="hidden" id="mesa_id">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Nombre</label>
          <input type="text" id="nombre" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <select id="estado" class="form-control">
            <option value="libre">libre</option>
            <option value="ocupada">ocupada</option>
            <option value="reservada">reservada</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Capacidad</label>
          <input type="number" id="capacidad" class="form-control" min="1" value="4">
        </div>
        <div class="col-md-3">
          <label class="form-label">Mesa principal ID</label>
          <input type="number" id="mesa_principal_id" class="form-control" min="1">
        </div>
        <div class="col-md-4">
          <label class="form-label">Área (texto)</label>
          <input type="text" id="area" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Usuario ID</label>
          <input type="number" id="usuario_id" class="form-control" min="1">
        </div>
        <div class="col-md-2">
          <label class="form-label">Área ID</label>
          <input type="number" id="area_id" class="form-control" min="1">
        </div>
        <div class="col-md-2">
          <label class="form-label">Alineación ID</label>
          <input type="number" id="alineacion_id" class="form-control" min="1">
        </div>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn custom-btn" id="btnGuardar">Guardar</button>
        <button type="button" class="btn btn-secondary" id="btnCancelar">Cancelar</button>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="styled-table" id="tablaMesas">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Estado</th>
          <th>Capacidad</th>
          <th>Mesa principal</th>
          <th>Área</th>
          <th>Usuario ID</th>
          <th>Área ID</th>
          <th>Alineación ID</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
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
<script src="../../utils/js/modal-lite.js"></script>
<script src="crud.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
