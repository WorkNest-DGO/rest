<?php
/**
 * Vista para consultar vistas SQL dinámicamente.
 * Para agregar nuevas vistas, actualiza el mapa viewLabels en vistas_db.js
 * y la lista $whitelist en /api/reportes/vistas_db.php.
 */
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
$title = 'Reportes';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Reportes</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Reporteria del sistema</a>
            </div>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container mt-5 mb-5">
    <h1 class="titulo-seccion">Consultas de Vistas</h1>

    <div class="filtros-container">
        <label for="selectVista">Vista:</label>
        <select id="selectVista" class="form-control-sm"></select>

        <label for="searchInput">Buscar:</label>
        <input type="text" id="searchInput" class="form-control-sm" placeholder="Buscar...">

        <label for="perPage">Filas por página:</label>
        <select id="perPage" class="form-control-sm">
            <option value="15">15</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </select>
    </div>

    <div id="loader" style="display:none;">Cargando...</div>
    <div id="error" class="text-danger mt-3" style="display:none;"></div>

    <table id="tablaVista" class="mt-3">
        <thead></thead>
        <tbody></tbody>
    </table>

    <div id="paginacion" class="mt-3">
        <button id="prevPage" class="btn custom-btn-sm">Anterior</button>
        <span id="paginaInfo" class="mx-2"></span>
        <button id="nextPage" class="btn custom-btn-sm">Siguiente</button>
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
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>

<script src="vistas_db.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';