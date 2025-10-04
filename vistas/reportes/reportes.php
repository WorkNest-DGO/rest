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

<div class="container mt-5 mb-5 custom-modal">
    <h1 class="titulo-seccion">Reportes de Cortes</h1>

    <div class="filtros-container">
        <label for="filtroUsuario">Usuario:</label>
        <select id="filtroUsuario" class="form-control-sm"></select>

        <label for="filtroInicio">Inicio:</label>
        <input type="date" id="filtroInicio" class="form-control-sm">

        <label for="filtroFin">Fin:</label>
        <input type="date" id="filtroFin" class="form-control-sm">

        <button id="aplicarFiltros" class="btn custom-btn-sm">Buscar</button>
        <button id="btnImprimir" class="btn custom-btn-sm">Imprimir</button>
    </div>

    <div class="acciones-corte mt-3">
        <button id="btnResumen" class="btn custom-btn">Resumen de corte actual</button>
    </div>

    <div id="modal" class="custom-modal" style="display:none;"></div>

</div>

<div class="container mt-5 mb-5">
    <h2 class="section-header">Historial de Cortes</h2>
    <table id="tablaCortes">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Fecha inicio</th>
                <th>Fecha cierre</th>
                <th>Total</th>
                <th>Efectivo</th>
                <th>Tarjeta</th>
                <th>Cheque</th>
                <th>Fondo</th>
                <th>Observaciones</th>
                <th>Desglose</th>
            </tr>
        </thead>
        <tbody>

        </tbody>
    </table>

</div>
<div class="container mt-5 mb-5">
    <h2 class="section-header">Consulta de Vistas y Tablas</h2>

    <div class="filtros-container">
        <label for="selectFuente">Fuente:</label>
        <select id="selectFuente" class="form-control-sm"></select>

        <label for="buscarFuente">Buscar:</label>
        <input type="text" id="buscarFuente" class="form-control-sm" placeholder="Buscar...">

        <label for="tamPagina">Filas por página:</label>
        <select id="tamPagina" class="form-control-sm">
            <option value="15">15</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </select>
        <button id="btnExportCSV" class="btn custom-btn-sm" style="margin-left:8px;">Exportar CSV</button>
    </div>

    <div id="reportesLoader" style="display:none;">Cargando...</div>

    <table id="tablaReportes" class="styled-table mt-3">
        <thead></thead>
        <tbody></tbody>
    </table>

    <div id="paginacionReportes" class="mt-3">
        <button id="prevReportes" class="btn custom-btn-sm">Anterior</button>
        <span id="infoReportes" class="mx-2"></span>
        <button id="nextReportes" class="btn custom-btn-sm">Siguiente</button>
    </div>
</div>

<!-- Sección de gráficas (D3) -->
<div class="container mt-5 mb-5">
  <h2 class="section-header">Gráficas</h2>
  <div class="filtros-container" style="margin-bottom:12px;">
    <label for="chartType">Tipo:</label>
    <select id="chartType" class="form-control-sm">
      <option value="bar">Barras</option>
      <option value="bar_stacked">Barras apiladas</option>
      <option value="bar_pareto">Barras ordenadas (Pareto)</option>
      <option value="line">Lí­nea</option>
      <option value="scatter">Dispersión (Scatter)</option>
      <option value="pie">Pastel</option>
      <option value="donut">Dona</option>
      <option value="waffle">Waffle</option>
      <option value="heatmap">Heatmap</option>
      <option value="box">Box plot</option>
      <option value="violin">Violin</option>
      <option value="bullet">Bullet chart</option>
      <option value="treemap">Treemap</option>
      <option value="sunburst">Sunburst</option>
      <option value="gantt">Gantt simple</option>
      <option value="funnel">Funnel</option>
      <option value="cohort">Cohort</option>
    </select>

    <label for="chartAgg">Agregación:</label>
    <select id="chartAgg" class="form-control-sm">
      <option value="sum" selected>Suma</option>
      <option value="count">Conteo</option>
    </select>

    <label for="chartXField">Campo X:</label>
    <select id="chartXField" class="form-control-sm"></select>

    <label for="chartYField" id="lblChartYField">Campo Y (valor):</label>
    <select id="chartYField" class="form-control-sm"></select>

    <label for="chartSeriesField" id="lblChartSeriesField">Serie (opcional):</label>
    <select id="chartSeriesField" class="form-control-sm"></select>

    <label for="chartYCatField" id="lblChartYCatField">Categorí­a Y (heatmap):</label>
    <select id="chartYCatField" class="form-control-sm"></select>

    <label for="chartStartField" id="lblChartStartField">Inicio (gantt):</label>
    <select id="chartStartField" class="form-control-sm"></select>
    <label for="chartEndField" id="lblChartEndField">Fin (gantt):</label>
    <select id="chartEndField" class="form-control-sm"></select>

    <label for="chartH1" id="lblChartH1">Jerarquí­a 1:</label>
    <select id="chartH1" class="form-control-sm"></select>
    <label for="chartH2" id="lblChartH2">Jerarquí­a 2:</label>
    <select id="chartH2" class="form-control-sm"></select>
    <label for="chartH3" id="lblChartH3">Jerarquí­a 3:</label>
    <select id="chartH3" class="form-control-sm"></select>

    <label for="chartTargetField" id="lblChartTargetField">Objetivo (bullet):</label>
    <select id="chartTargetField" class="form-control-sm"></select>

    <label id="lblChartRowPercent" style="display:none;">
      <input type="checkbox" id="chartRowPercent"> Normalizar por fila (cohort)
    </label>

    <button id="btnRenderChart" class="btn custom-btn-sm">Generar</button>
    <button id="btnExportPNG" class="btn custom-btn-sm" type="button">Exportar PNG</button>
    <button id="btnExportSVG" class="btn custom-btn-sm" type="button">Exportar SVG</button>
  </div>
  <div class="filtros-container" style="margin-bottom:12px;">
    <label for="chartTitle">Título:</label>
    <input id="chartTitle" class="form-control-sm" placeholder="Opcional">
    <label for="chartDesc">Descripción:</label>
    <input id="chartDesc" class="form-control-sm w-50" placeholder="Opcional: qué se muestra y filtros aplicados">
  </div>
  <div id="chartContainer" style="background:#fff; border-radius:8px; padding:12px; border:1px solid #131415ff;width:auto">
    <svg style="color:black" id="chartSvg" width="100%" height="420"></svg>
  </div>
  <small class="text-muted">Tip: para restaurantes son útiles barras (top productos, ventas por mesero), lí­nea (ventas diarias) y pastel (forma de pago).</small>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<!-- D3.js para gráficas -->
<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="reportes.js"></script>
  </body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
