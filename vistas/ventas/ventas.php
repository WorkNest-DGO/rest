<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

// Cargar todas las denominaciones disponibles para usarlas en el JS
$denominaciones = $conn->query("SELECT id, descripcion, valor FROM catalogo_denominaciones ORDER BY valor ASC")->fetch_all(MYSQLI_ASSOC);

$title = 'Ventas';
ob_start();
?>
<style>
  .ticket-mono {
    font-family: "Courier New", ui-monospace, Menlo, Consolas, monospace;
    white-space: pre; line-height: 1.25; font-size: 13px;
    width: 80mm; max-width: 100%; margin: 0 auto; background: #fff; padding: 8px;
    border: 1px dashed #ddd; border-radius: 6px;
  }
  @media print {
    body * { visibility: hidden !important; }
    #modalCortePreview, #modalCortePreview * { visibility: visible !important; }
    #modalCortePreview { position: static; inset: auto; background: none; }
    #modalCortePreview .modal-dialog { box-shadow: none; }
    #modalCortePreview .modal-header, #modalCortePreview .modal-footer { display: none; }
    .ticket-mono { border: none; }
  }
</style>
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
  <div class="section-header text-center">
    <p>Control de ventas</p>
    <h2>Registro de Venta</h2>
  </div>
<div class="container mt-5">
  <h2 class="section-header">Solicitudes de Ticket</h2>
  <div class="table-responsive">
    <table id="solicitudes" class="styled-table">
      <thead>
        <tr>
          <th style="color:#fff">Mesa</th>
          <th style="color:#fff">Imprimir</th>
        </tr>
      </thead>
      <tbody>
        <!-- Las solicitudes se insertarán aquí dinámicamente -->
      </tbody>
    </table>
  </div>
</div>
<br>
  <div class="row justify-content-center">
    <div class="col-md-10 bg-dark p-4 rounded">
           <div id="controlCaja" class="mb-3 text-white">
        <button id="btnCerrarCorte" type="button" class="btn custom-btn" title="" style="display:none">
          Cerrar corte
        </button>
      </div>
      <form id="formVenta">
        <div class="form-group">
          <label for="tipo_entrega" class="text-white">Tipo de venta:</label>
          <select id="tipo_entrega" name="tipo_entrega" class="form-control">
            <option value="mesa">En restaurante</option>
            <option value="domicilio">A domicilio</option>
            <option value="rapido">Rapido</option>
          </select>
        </div>

        <div id="campoObservacion" class="form-group" style="display:none;">
          <label for="observacion" class="text-white">Observación:</label>
          <textarea id="observacion" name="observacion" class="form-control"></textarea>
        </div>

        <div id="campoMesa" class="form-group">
          <label for="mesa_id" class="text-white">Mesa:</label>
          <select id="mesa_id" name="mesa_id" class="form-control">
            <option value="">Seleccione</option>
            <option value="1">Mesa 1</option>
            <option value="2">Mesa 2</option>
            <option value="3">Mesa 3</option>
          </select>
        </div>

        <div id="campoRepartidor" class="form-group" style="display:none;">
          <label for="repartidor_id" class="text-white">Repartidor:</label>
          <select id="repartidor_id" name="repartidor_id" class="form-control"></select>
        </div>

        <div class="form-group">
          <label for="usuario_id" class="text-white">Mesero:</label>
          <select id="usuario_id" name="usuario_id" class="form-control"></select>
        </div>

        <!-- Sección que se muestra al elegir una mesa -->
        <div id="seccionProductos" style="display:none;" class="mt-4">
          <h3 class="text-white">Productos</h3>
          <table class="table table-bordered bg-white text-dark" id="productos">
            <thead>
              <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <select class="form-control select-producto">
                    <option value="">--Selecciona--</option>
                  </select>
                </td>
                <td><input type="number" class="form-control cantidad"></td>
                <td><input type="number" step="0.01" class="form-control precio" readonly></td>
              </tr>
            </tbody>
          </table>
          <div class="text-center">
            <button type="button" class="btn custom-btn" id="agregarProducto">Agregar Producto</button>
            <button type="button" class="btn custom-btn" id="registrarVenta">Registrar Venta</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Botones de movimientos de caja -->
<div class="container mt-3 mb-3 text-center">
  <button id="btnDeposito" type="button" class="btn custom-btn" data-toggle="modal" data-target="#modalMovimientoCaja">Depósito a caja</button>
  <button id="btnRetiro" type="button" class="btn custom-btn" data-toggle="modal" data-target="#modalMovimientoCaja">Retiro de caja</button>
</div>

<div class="container mt-5">
  <h2 class="section-header">Historial de Ventas</h2>
  <div class="mb-2 d-flex justify-content-between">
    <input type="search" id="buscadorVentas" class="form-control w-50" placeholder="Buscar...">
    <div class="d-flex align-items-center">
      <label for="recordsPerPage" class="me-2">Registros por página:</label>
      <select id="recordsPerPage" class="form-select w-auto">
        <option value="15">15</option>
        <option value="25">25</option>
        <option value="50">50</option>
      </select>
    </div>
  </div>
  <div class="table-responsive">
    <table id="historial" class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha</th>
          <th>Total</th>
          <th>Tipo</th>
          <th>Destino</th>
          <th>Estatus</th>
          <th>Entregado</th>
          <th>Ver detalles</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <!-- Se insertan filas dinámicamente aquí -->
      </tbody>
    </table>
  </div>
  <div id="paginacion" class="mt-2"></div>
</div>





<!-- Modales -->
<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modal-detalles" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de venta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"><!-- contenido dinámico --></div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalDesglose" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Desglose de caja</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"><!-- contenido dinámico --></div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalCorteTemporal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Corte Temporal</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="corteTemporalDatos"></div>
        <label for="observacionesCorteTemp">Observaciones:</label>
        <textarea id="observacionesCorteTemp" class="form-control" rows="3"></textarea>
      </div>
      <div class="modal-footer">
        <button id="guardarCorteTemporal" class="btn btn-success">Guardar Corte Temporal</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalMovimientoCaja" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Movimiento de caja</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="formMovimientoCaja">
          <div class="mb-3">
            <label for="tipoMovimiento" class="form-label">Tipo de movimiento</label>
            <select id="tipoMovimiento" class="form-select">
              <option value="deposito">Depósito</option>
              <option value="retiro">Retiro</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="montoMovimiento" class="form-label">Monto</label>
            <input type="number" step="0.01" class="form-control" id="montoMovimiento" required>
          </div>
          <div class="mb-3">
            <label for="motivoMovimiento" class="form-label">Motivo</label>
            <textarea id="motivoMovimiento" class="form-control" required></textarea>
          </div>
          <div class="mb-3">
            <label for="fechaMovimiento" class="form-label">Fecha</label>
            <input type="text" class="form-control" id="fechaMovimiento" readonly>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn custom-btn" id="guardarMovimiento">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalCortePreview" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Previsualización – Corte / Cierre de caja</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <pre id="corteTicketText" class="ticket-mono"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnImprimirCorte" class="btn custom-btn">Imprimir</button>
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de confirmación de cancelación -->
<div class="modal fade" id="cancelVentaModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">¿Deseas cancelar la venta?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="confirmCancelVenta">Cancelar venta</button>
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
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
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

  <?php require_once __DIR__ . '/../footer.php'; ?>

  <link href="../../utils/css/select2.min.css" rel="stylesheet" />
  <script src="../../utils/js/select2.min.js"></script>
  <script>
    // ID de usuario proveniente de la sesión para operaciones en JS
    window.usuarioId = <?php echo json_encode($_SESSION['usuario_id']); ?>;
    // ID de la venta actualmente consultada en detalle
    window.ventaIdActual = null;
    // ID de corte actual si existe en la sesión
    window.corteId = <?php echo json_encode($_SESSION['corte_id'] ?? null); ?>;
    // Catálogo de denominaciones cargado desde la base de datos
    const catalogoDenominaciones = <?php echo json_encode($denominaciones); ?>;
    // Último ID de detalle enviado a cocina almacenado
    window.ultimoDetalleCocina = parseInt(localStorage.getItem('ultimoDetalleCocina') || '0', 10);
  </script>
  <script src="ventas.js"></script>
  </body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>