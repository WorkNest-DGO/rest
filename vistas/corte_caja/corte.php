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
<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="resumenModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ventas del corte</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"><!-- contenido dinámico --></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalDesglose" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Desglose de caja</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"><!-- contenido dinámico --></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de corte</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" id="modalDetalleContenido"><!-- Se llena dinámicamente con detalles de efectivo, boucher y cheque --></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
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

<script src="corte.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';

