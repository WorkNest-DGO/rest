<?php
require_once __DIR__ . '/../utils/cargar_permisos.php';
// Determina la base del app de forma dinámica (dos niveles arriba del script)
$__sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$__app_base = rtrim(dirname(dirname($__sn)), '/');
// Normaliza ruta actual relativa al app (ej. /vistas/ayuda.php)
$path_actual = preg_replace('#^' . preg_quote($__app_base, '#') . '#', '', ($__sn ?: $_SERVER['PHP_SELF']));
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

<!-- Video Modal (mismo formato que index) -->
<div class="modal fade" id="videoModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <div class="embed-responsive embed-responsive-16by9">
          <iframe class="embed-responsive-item" src="" id="video" allowscriptaccess="always" allow="autoplay"></iframe>
        </div>
      </div>
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
