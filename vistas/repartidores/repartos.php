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
$title = 'Repartos';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Entregas</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Repartos</a>
            </div>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div id="user-info" data-usuario-id="<?php echo htmlspecialchars($_SESSION['usuario_id'] ?? '', ENT_QUOTES); ?>" data-rol="<?php echo htmlspecialchars($_SESSION['rol'] ?? '', ENT_QUOTES); ?>" hidden></div>
<div id="limit-alert" class="alert alert-warning d-none">Vista limitada a tus registros</div>

<div class="container mt-5 mb-5 ">
    <h1 class="section-header">Repartos</h1>

    <h2 class="section-subheader">Pendientes</h2>
    <div class="table-responsive">
        <table id="tabla-pendientes" class="styled-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Repartidor</th>
                    <th>Productos</th>
                    <th>Observación</th>
                    <th>Asignado</th>
                    <th>Inicio</th>
                    <th>Entrega</th>
                    <th>Total (min)</th>
                    <th>En camino (min)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <!-- Se llena dinámicamente -->
            </tbody>
        </table>
    </div>
</div>

</div>

<div class="container mt-5 mb-5">
    <h2 class="section-subheader">Entregadas</h2>
    <div class="table-responsive">
        <table id="tabla-entregadas" class="styled-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Repartidor</th>
                    <th>Productos</th>
                    <th>Observación</th>
                    <th>Asignado</th>
                    <th>Inicio</th>
                    <th>Entrega</th>
                    <th>Total (min)</th>
                    <th>En camino (min)</th>
                    <th>Ver detalles</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>


<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modal-detalles" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de reparto</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body"><!-- contenido dinámico --></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
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
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="../../utils/js/modal-lite.js"></script>
<script src="repartos.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
