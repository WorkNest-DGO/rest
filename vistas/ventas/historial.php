<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$title = 'Historial de Ventas';
ob_start();
?>
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12"><h2>Historial de Ventas</h2></div>
      <div class="col-12"><a href="ventas.php">Ventas</a> <a href="historial.php">Historial</a></div>
    </div>
  </div>
  </div>

<div class="container mt-4">
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
          <th>Folio</th>
          <th>Fecha</th>
          <th>Total</th>
          <th>Tipo</th>
          <th>Destino</th>
          <th>Estatus</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
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
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="historial.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>

