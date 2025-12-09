<?php
/**
 * Vista para consultar vistas SQL dinámicamente.
 * Para agregar nuevas vistas, actualiza el mapa viewLabels en vistas_db.js
 * y la lista $whitelist en /api/reportes/vistas_db.php.
 */
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
$title = 'Reportes';
ob_start();
?>

<style>
  /* Estilos locales para la vista de reportes */
  .filtros-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
    align-items: center;
    background: #0c0c0cff;
    border: 1px solid #ff0000ff;
    border-radius: 8px;
    padding: 12px;
  }
  #loader { font-size: 14px; color: #ce1111ff; }
  #error { font-weight: 600; }
  .table-responsive { max-width: 100%; overflow: auto; border: 1px solid #92230aff; border-radius: 8px; }
  #tablaVista { width: 100%; }
  #tablaVista thead th {
    position: sticky; top: 0; z-index: 2;
    background: #7f0f0fff; /* en modo claro */
    border-bottom: 2px solid #0f0f0fff;
    white-space: nowrap;
    cursor: pointer;
  }
  #tablaVista thead th.ordenado.asc::after { content: ' \2191'; font-size: 0.9em; color: #888; }
  #tablaVista thead th.ordenado.desc::after { content: ' \2193'; font-size: 0.9em; color: #888; }
  #tablaVista tbody td { vertical-align: middle; }
  /* Zebra ya la aplica table-striped, reforzamos para muchas columnas */
  #tablaVista tbody tr:nth-child(even) { background-color: #000000ff; }
  /* Ajustes para celdas muy largas */
  #tablaVista td, #tablaVista th { padding: .5rem .6rem; }
  #tablaVista td { max-width: 420px; overflow: hidden; text-overflow: ellipsis; }
</style>
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

    <div class="table-responsive mt-3">
      <table id="tablaVista" class="table table-striped table-hover table-sm table-bordered align-middle">
          <thead></thead>
          <tbody></tbody>
      </table>
    </div>

    <div id="paginacion" class="mt-3">
        <button id="prevPage" class="btn custom-btn-sm">Anterior</button>
        <span id="paginaInfo" class="mx-2"></span>
        <button id="nextPage" class="btn custom-btn-sm">Siguiente</button>
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

<script src="vistas_db.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
