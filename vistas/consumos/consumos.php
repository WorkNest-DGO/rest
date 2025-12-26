<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
// Base app dinamica y ruta relativa para validacion
$__sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$__pos = strpos($__sn, '/vistas/');
$__app_base = $__pos !== false ? substr($__sn, 0, $__pos) : rtrim(dirname($__sn), '/');
$path_actual = preg_replace('#^' . preg_quote($__app_base, '#') . '#', '', ($__sn ?: $_SERVER['PHP_SELF']));
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

$title = 'Consumos';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <h2>Consumos de Empleados</h2>
      </div>
      <div class="col-12">
        <a href="../index.php">Inicio</a>
        <a>Consumos</a>
      </div>
    </div>
  </div>
</div>

<div class="container mt-5 mb-5">
  <h1 class="section-header">Modulo de Consumos</h1>

  <ul class="nav nav-tabs" id="consumosTabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" id="tab-beneficios-link" data-toggle="tab" href="#tab-beneficios" role="tab">Beneficios</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="tab-consumo-link" data-toggle="tab" href="#tab-consumo" role="tab">Registrar consumo</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="tab-reporte-link" data-toggle="tab" href="#tab-reporte" role="tab">Reporte nomina</a>
    </li>
  </ul>

  <div class="tab-content pt-4" id="consumosTabsContent">
    <div class="tab-pane fade show active" id="tab-beneficios" role="tabpanel">
      <form id="formBeneficio" class="mb-4">
        <input type="hidden" id="beneficio_id">
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label for="beneficio_usuario">Usuario:</label>
              <select id="beneficio_usuario" class="form-control" required></select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="beneficio_producto">Producto:</label>
              <select id="beneficio_producto" class="form-control" required></select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="beneficio_tipo">Tipo de regla:</label>
              <select id="beneficio_tipo" class="form-control" required>
                <option value="semana">Dia de semana</option>
                <option value="fecha">Fecha especifica</option>
              </select>
            </div>
          </div>
          <div class="col-md-4" id="wrapDiaSemana">
            <div class="form-group">
              <label for="beneficio_dia">Dia de semana:</label>
              <select id="beneficio_dia" class="form-control"></select>
            </div>
          </div>
          <div class="col-md-4 d-none" id="wrapFecha">
            <div class="form-group">
              <label for="beneficio_fecha">Fecha:</label>
              <input type="date" id="beneficio_fecha" class="form-control">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="beneficio_cantidad">Cantidad maxima:</label>
              <input type="number" id="beneficio_cantidad" class="form-control" min="1" step="1" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="beneficio_activo">Activo:</label>
              <select id="beneficio_activo" class="form-control">
                <option value="1">Si</option>
                <option value="0">No</option>
              </select>
            </div>
          </div>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn custom-btn">Guardar regla</button>
          <button type="button" class="btn custom-btn" id="btnLimpiarBeneficio">Limpiar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="styled-table" id="tablaBeneficios">
          <thead>
            <tr>
              <th>ID</th>
              <th>Usuario</th>
              <th>Producto</th>
              <th>Regla</th>
              <th>Dia/Fecha</th>
              <th>Cantidad max</th>
              <th>Activo</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-consumo" role="tabpanel">
      <form id="formConsumo">
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label for="consumo_usuario">Usuario consumidor:</label>
              <select id="consumo_usuario" class="form-control" required></select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="consumo_fecha">Fecha consumo:</label>
              <input type="datetime-local" id="consumo_fecha" class="form-control">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="consumo_observacion">Observacion:</label>
              <input type="text" id="consumo_observacion" class="form-control">
            </div>
          </div>
        </div>

        <div class="row align-items-end">
          <div class="col-md-6">
            <div class="form-group">
              <label for="consumo_producto">Producto:</label>
              <select id="consumo_producto" class="form-control"></select>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="consumo_cantidad">Cantidad:</label>
              <input type="number" id="consumo_cantidad" class="form-control" min="1" step="1" value="1">
            </div>
          </div>
          <div class="col-md-3">
            <button type="button" class="btn custom-btn w-100" id="btnAgregarItem">Agregar</button>
          </div>
        </div>

        <div class="table-responsive mt-3">
          <table class="styled-table" id="tablaCarrito">
            <thead>
              <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Subtotal</th>
                <th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="fw-bold">Total estimado: <span id="totalCarrito">$0.00</span></div>
          <button type="submit" class="btn custom-btn">Guardar consumo</button>
        </div>
      </form>
    </div>

    <div class="tab-pane fade" id="tab-reporte" role="tabpanel">
      <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn custom-btn" id="btnExportCsv">Exportar CSV</button>
        <small class="text-muted">Exporta lo filtrado en la tabla.</small>
      </div>
      <form id="formReporte" class="mb-3">
        <div class="row align-items-end">
          <div class="col-md-3">
            <label for="filtro_fecha_inicio">Fecha inicio:</label>
            <input type="date" id="filtro_fecha_inicio" class="form-control">
          </div>
          <div class="col-md-3">
            <label for="filtro_fecha_fin">Fecha fin:</label>
            <input type="date" id="filtro_fecha_fin" class="form-control">
          </div>
          <div class="col-md-3">
            <label for="filtro_usuario">Usuario:</label>
            <select id="filtro_usuario" class="form-control"></select>
          </div>
          <div class="col-md-2">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="filtro_solo_cobrables">
              <label class="form-check-label" for="filtro_solo_cobrables">Solo cobrables</label>
            </div>
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn custom-btn w-100">Buscar</button>
          </div>
        </div>
      </form>

      <div class="table-responsive mb-4">
        <table class="styled-table" id="tablaResumenNomina">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Total consumos</th>
              <th>Total cobrable</th>
              <th>Total exento</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="table-responsive">
        <table class="styled-table" id="tablaConsumos">
          <thead>
            <tr>
              <th></th>
              <th>ID</th>
              <th>Fecha</th>
              <th>Usuario</th>
              <th>Producto</th>
              <th>Cantidad</th>
              <th>Precio</th>
              <th>Subtotal</th>
              <th>Gratis</th>
              <th>Descuento</th>
              <th>Monto nomina</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="text-end mt-3">
        <button type="button" class="btn custom-btn" id="btnMarcarAplicado">Marcar aplicado</button>
      </div>
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
<script>
  window.sedeId = <?php echo json_encode($_SESSION['sede_id'] ?? null); ?>;
</script>
<script src="../../utils/js/modal-lite.js"></script>
<script src="../../../assets/js/consumos.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
