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

$title = 'Promociones';
ob_start();
?>

<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12"><h2>Promociones</h2></div>
      <div class="col-12">
        <a href="">Inicio</a>
        <a href="">Promociones</a>
      </div>
    </div>
  </div>
  </div>

<div class="container mt-4 mb-5">
  <div class="d-flex align-items-center mb-3 gap-2" style="gap:8px;">
    <input id="buscar" type="text" class="form-control" placeholder="Buscar por motivo" style="max-width:320px;">
    <select id="filtroTipo" class="form-select" style="max-width:220px;">
      <option value="">Todos los tipos</option>
      <option value="monto_fijo">Monto fijo</option>
      <option value="porcentaje">Porcentaje</option>
      <option value="buy_x_get_y">Buy X Get Y</option>
      <option value="bundle_price">Bundle price</option>
    </select>
    <select id="filtroActivo" class="form-select" style="max-width:160px;">
      <option value="">Activas y no activas</option>
      <option value="1">Solo activas</option>
      <option value="0">Solo inactivas</option>
    </select>
    <button id="btnBuscar" class="btn custom-btn">Buscar</button>
    <button id="btnNueva" class="btn custom-btn">Nueva promoción</button>
  </div>

  <div class="table-responsive">
    <table class="styled-table" id="tablaPromos">
      <thead>
        <tr>
          <th>ID</th>
          <th>Motivo</th>
          <th>Tipo</th>
          <th>Monto</th>
          <th>Activo</th>
          <th>Visible</th>
          <th>Prioridad</th>
          <th>Combinable</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <div class="d-flex justify-content-center mt-3" id="paginador"></div>
</div>

<!-- Modal CRUD -->
<div class="modal fade" id="promoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="promoModalTitle">Nueva promoción</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="formPromo">
          <input type="hidden" id="promoId">
          <div class="row g-3">
            <div class="col-md-6">
              <label>Motivo</label>
              <input type="text" id="motivo" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label>Monto</label>
              <input type="number" step="0.01" id="monto" class="form-control" value="0">
            </div>
            <div class="col-md-3">
              <label>Tipo</label>
              <select id="tipo" class="form-select" required>
                <option value="monto_fijo">Monto fijo</option>
                <option value="porcentaje">Porcentaje</option>
                <option value="buy_x_get_y">Buy X Get Y</option>
                <option value="bundle_price">Bundle price</option>
              </select>
            </div>
            <div class="col-md-3">
              <label>Prioridad</label>
              <input type="number" id="prioridad" class="form-control" value="10">
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="activo" checked>
                <label class="form-check-label" for="activo"> Activo</label>
              </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="visible_en_ticket" checked>
                <label class="form-check-label" for="visible_en_ticket"> Visible en ticket</label>
              </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="combinable" checked>
                <label class="form-check-label" for="combinable"> Combinable</label>
              </div>
            </div>
          </div>

          <div class="row mt-3">
            <div class="col-md-7">
              <label>Regla (JSON)</label>
              <textarea id="regla" class="form-control" rows="12" placeholder='{"producto_ids": [1,2]}'></textarea>
            </div>
            <div class="col-md-5">
              <div class="d-flex align-items-center mb-2" style="gap:8px;">
                <input id="buscarProducto" type="text" class="form-control" placeholder="Buscar producto...">
                <button type="button" id="btnBuscarProd" class="btn btn-secondary">Buscar</button>
              </div>
              <div style="max-height:360px; overflow:auto;" id="listaProductos"></div>
              <small>Tip: haz clic en un producto para insertar su ID en el JSON.</small>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
        <button type="button" class="btn custom-btn" id="btnGuardarPromo">Guardar</button>
      </div>
    </div>
  </div>
  </div>

<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="promociones.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>

