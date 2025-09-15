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

$title = 'Áreas';
ob_start();
?>
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12"><h2>Catálogo de Áreas</h2></div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Catálogo de Áreas</a>
            </div>
        </div>
    </div>
</div>

<div class="container mt-5 mb-5">
  <h1 class="section-header">Áreas</h1>
  <div class="mb-3 text-end">
    <button class="btn custom-btn" id="agregarArea">Agregar área</button>
  </div>
  <div class="table-responsive">
    <table id="tablaAreas" class="styled-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalAgregarArea" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formAgregarArea">
        <div class="modal-header">
          <h5 class="modal-title">Agregar Área</h5>
          <button type="button" class="close" onclick="cerrarModalAgregarArea()" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="nombreArea">Nombre:</label>
            <input type="text" id="nombreArea" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn custom-btn" type="submit">Guardar</button>
          <button class="btn custom-btn" type="button" onclick="cerrarModalAgregarArea()">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEditarArea" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formEditarArea">
        <div class="modal-header">
          <h5 class="modal-title">Editar Área</h5>
          <button type="button" class="close" onclick="cerrarModalEditarArea()" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="editarAreaId">
          <div class="form-group">
            <label for="editarNombreArea">Nombre:</label>
            <input type="text" id="editarNombreArea" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn custom-btn" type="submit">Actualizar</button>
          <button class="btn custom-btn" type="button" onclick="cerrarModalEditarArea()">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalMsgArea" tabindex="-1" role="dialog" aria-hidden="true">
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
<script src="catalogo_areas.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
echo $content;
?>