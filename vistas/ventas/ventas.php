<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
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
          <select id="usuario_id" name="usuario_id" class="form-control" required></select>
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

<div class="container mt-5">
  <h2 class="section-header">Historial de Ventas</h2>
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

<!-- Modales -->
<div id="modal-detalles" class="custom-modal" style="display:none;"></div>
<div id="modalDesglose" class="custom-modal" style="display:none;"></div>


<?php require_once __DIR__ . '/../footer.php'; ?>
<script>
  // ID de usuario proveniente de la sesión para operaciones en JS
  window.usuarioId = <?php echo json_encode($_SESSION['usuario_id']); ?>;
  // ID de la venta actualmente consultada en detalle
  window.ventaIdActual = null;
</script>
<script src="ventas.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>