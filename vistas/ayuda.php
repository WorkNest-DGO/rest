<?php
require_once __DIR__ . '/../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
$title = 'Ayuda / Manual';
ob_start();
?>

<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12"><h2>Centro de Ayuda</h2></div>
      <div class="col-12">
        <a href="">Inicio</a>
        <a href="">Ayuda / Manual</a>
      </div>
    </div>
  </div>
</div>

<div class="container mt-5 mb-5">
  <h1 class="section-header">Ayuda</h1>

  <div class="filtros-container mb-3">
    <label for="buscarAyuda" class="me-2">Buscar:</label>
    <input type="text" id="buscarAyuda" class="form-control" placeholder="Escribe para filtrar secciones">
  </div>

  <div id="contenedorAyuda" class="custom-modal"></div>

  <div class="row mt-3">
    <div class="col-12">
      <ul id="paginadorAyuda" class="pagination justify-content-center"></ul>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
<script src="ayuda/ayuda.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/nav.php';
?>

