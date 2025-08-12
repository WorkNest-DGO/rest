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
      <div id="controlCaja" class="mb-3 text-white"></div>
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
                <td><select class="form-control producto"></select></td>
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
  <button id="btnDeposito" class="btn btn-success me-2">Depósito a caja</button>
  <button id="btnRetiro" class="btn btn-danger">Retiro de caja</button>
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
<div id="modal-detalles" class="custom-modal" style="display:none;"></div>
<div id="modalDesglose" class="custom-modal" style="display:none;"></div>

<!-- Modal Movimiento de Caja -->
<div class="modal fade" id="modalMovimientoCaja" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Movimiento de caja</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="guardarMovimiento">Guardar</button>
      </div>
    </div>
  </div>
</div>


  <?php require_once __DIR__ . '/../footer.php'; ?>
  <script>
    // ID de usuario proveniente de la sesión para operaciones en JS
    window.usuarioId = <?php echo json_encode($_SESSION['usuario_id']); ?>;
    // ID de la venta actualmente consultada en detalle
    window.ventaIdActual = null;
    // ID de corte actual si existe en la sesión
    window.corteId = <?php echo json_encode($_SESSION['corte_id'] ?? null); ?>;
    // Catálogo de denominaciones cargado desde la base de datos
    const catalogoDenominaciones = <?php echo json_encode($denominaciones); ?>;
  </script>
  <script src="ventas.js"></script>
  </body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>