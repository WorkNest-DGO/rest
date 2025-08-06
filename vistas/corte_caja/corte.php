<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

$denominaciones = $conn->query("SELECT id, descripcion, valor FROM catalogo_denominaciones ORDER BY valor ASC")->fetch_all(MYSQLI_ASSOC);

$title = 'Corte de Caja';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <h2>Modulo de Ventas</h2>
      </div>
      <div class="col-12">
        <a href="">Inicio</a>
        <a href="">Ventas</a>
      </div>
    </div>
  </div>
</div>
<!-- Page Header End -->

<div class="container mt-5 mb-5">
<h1 class="section-header">Corte de Caja</h1>

<div id="corteActual" class="mb-3">
  <button class="btn custom-btn btn-primary" id="btnIniciar">Iniciar Corte</button>
</div>

<!-- Modales -->
<div id="resumenModal" class="custom-modal" style="display:none;"></div>
<div id="modalDesglose" class="custom-modal" style="display:none;"></div>

<!-- Modal para detalle de corte -->
<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de corte</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="modalDetalleContenido">
        <table id="tablaDetalleCorte" class="table table-bordered">
          <thead>
            <tr>
              <th>Denominación</th>
              <th>Cantidad</th>
              <th>Tipo de pago</th>
              <th>Subtotal</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<h2 class="section-header">Historial de Cortes</h2>
<div class="d-flex justify-content-between mb-2">
  <div>
    <label for="selectRegistros">Mostrar</label>
    <select id="selectRegistros" class="custom-select custom-select-sm">
      <option value="15">15</option>
      <option value="25">25</option>
      <option value="50">50</option>
    </select>
    <span>registros</span>
  </div>
  <div>
    <input type="text" id="buscarCorte" class="form-control form-control-sm" placeholder="Buscar...">
  </div>
</div>
<div class="table-responsive">
  <table id="tablaCortes" class="styled-table">
    <thead>
      <tr>
        <th style="color:#fff">ID</th>
        <th style="color:#fff">Cajero</th>
        <th style="color:#fff">Fecha inicio</th>
        <th style="color:#fff">Fecha fin</th>
        <th style="color:#fff">Total</th>
        <th style="color:#fff">Detalle</th>
      </tr>
    </thead>
    <tbody>
      <!-- Se llena dinámicamente -->
    </tbody>
  </table>
</div>
<div id="paginacion" class="mt-2"></div>

</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script>
    const catalogoDenominaciones = <?php echo json_encode($denominaciones); ?>;
</script>
<script src="corte.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';

