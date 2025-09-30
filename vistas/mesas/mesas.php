<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
/*$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}*/
$title = 'Mesas';
ob_start();
?>
<!-- Dragula -->
<link rel="stylesheet" href="../../utils/css/dragula.min.css">

<script src="../../utils/js/dragula.min.js"></script>

<link href="../../utils/css/style2.css" rel="stylesheet">
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Meseros</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Catálogo de Mesas</a>
            </div>
        </div>
    </div>
</div>

<h1>Mesas</h1>
<div hidden>
    <button class="btn custom-btn" id="btn-unir">Unir mesas</button>
    <select id="filtro-area"></select>
</div>
<div id="tablero"></div>
<div id="modalVenta" class="modal-flotante modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de venta</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de autorización para cambio de estado -->
<div id="modalAuthMesa" class="modal-flotante modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Autorización requerida</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Ingrese la contraseña del mesero asignado para continuar.</p>
                <input type="password" id="authMesaPass" class="form-control" autocomplete="current-password" placeholder="Contraseña del mesero asignado">
                <small id="authMesaInfo" class="form-text text-muted"></small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn custom-btn" id="btnAuthMesaContinuar">Continuar</button>
            </div>
        </div>
    </div>
    </div>

<!-- Modal para seleccionar nuevo estado de mesa -->
<div id="modalCambioEstado" class="modal-flotante modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar estado de mesa</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><strong>Nuevo estado (libre, ocupada, reservada):</strong></label>
                    <div id="estadoOpciones" class="d-flex flex-column" style="gap:6px;">
                        <label class="mb-0"><input type="checkbox" name="estado" value="libre"> Libre</label>
                        <label class="mb-0"><input type="checkbox" name="estado" value="ocupada"> Ocupada</label>
                        <label class="mb-0"><input type="checkbox" name="estado" value="reservada"> Reservada</label>
                    </div>
                </div>
                <div id="reservaCampos" class="form-group" style="display:none;">
                    <label for="reservaNombre">Nombre de la reserva:</label>
                    <input type="text" id="reservaNombre" class="form-control" placeholder="Nombre">
                    <label for="reservaFecha" class="mt-2">Fecha y hora (YYYY-MM-DD HH:MM):</label>
                    <input type="text" id="reservaFecha" class="form-control" placeholder="2025-09-30 19:30">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn custom-btn" id="btnGuardarEstadoMesa">Guardar</button>
            </div>
        </div>
    </div>
    </div>

<div>
<section class="section">
	<h1>Distribución de mesas</h1>
	<h4>Tokyo Sushy Prime </h4>
</section>

<div class="kanban-container" id="kanbanMesas">
        <ul class="drag-list" id="kanban-list"></ul>
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

<script>
window.usuarioActual = {
    id: <?= (int)($_SESSION['usuario_id'] ?? 0); ?>,
    rol: <?= json_encode($_SESSION['rol'] ?? ''); ?>
};
</script>
<script src="../../utils/js/buscador.js"></script>
<script src="kanbanMesas.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
