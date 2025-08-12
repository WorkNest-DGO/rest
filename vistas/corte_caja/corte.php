<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

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

<!-- Modal Corte Temporal -->
<div id="modalCorteTemporal" class="custom-modal" style="display:none;">
  <div class="modal-content">
    <span id="closeCorteTemporal" class="close">&times;</span>
    <h2>Corte Temporal</h2>
    <div id="corteTemporalDatos" style="max-height:300px;overflow:auto;"></div>
    <div>
      <label>Observaciones:</label>
      <textarea id="corteTemporalObservaciones" class="form-control"></textarea>
    </div>
    <br>
    <button id="guardarCorteTemporal" class="btn custom-btn btn-success">Guardar Corte Temporal</button>
  </div>
</div>

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
      <div class="modal-body" id="modalDetalleContenido"><!-- Se llena dinámicamente con detalles de efectivo, boucher y cheque --></div>
    </div>
  </div>
</div>

<h2 class="section-header">Historial de Cortes</h2>
<div class="mb-2">
  <label for="fechaInicio">Inicio:</label>
  <input type="date" id="fechaInicio" class="form-control form-control-sm d-inline-block">
  <label for="fechaFin" class="ml-2">Fin:</label>
  <input type="date" id="fechaFin" class="form-control form-control-sm d-inline-block">
  <button id="btnFiltrar" class="btn custom-btn-sm ml-2">Filtrar</button>
</div>
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

  <table id="tablaCortes" class="styled-table">
    <thead>
      <tr>
        <th style="color:#fff">ID</th>
        <th style="color:#fff">Fecha inicio</th>
        <th style="color:#fff">Fecha fin</th>
        <th style="color:#fff">Usuario</th>
        <th style="color:#fff">Efectivo</th>
        <th style="color:#fff">Boucher</th>
        <th style="color:#fff">Cheque</th>
        <th style="color:#fff">Fondo inicial</th>
        <th style="color:#fff">Total</th>
        <th style="color:#fff">Observaciones</th>
        <th style="color:#fff">Detalle</th>
      </tr>
    </thead>
    <tbody>
      <!-- Se llena dinámicamente -->
    </tbody>
  </table>

<div id="paginacion" class="mt-2"></div>

</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="corte.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';

