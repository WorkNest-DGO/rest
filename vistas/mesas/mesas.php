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
<style>
/* Mosaico de 4 columnas para las tarjetas de mesas */
#kanban-list .kanban-dropzone {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    padding: 10px;
}
#kanban-list .kanban-item {
    margin: 0;
    width: 100%;
}
@media (max-width: 1200px) {
    #kanban-list .kanban-dropzone { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
    #kanban-list .kanban-dropzone { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 600px) {
    #kanban-list .kanban-dropzone { grid-template-columns: 1fr; }
}
/* Permitir que la lista crezca sin scroll vertical */
#kanban-list .drag-column {
    overflow: visible;
}
#kanban-list .kanban-dropzone {
    max-height: none !important;
    overflow: visible;
}
/* Modal Detalle de venta en mesa: ancho fijo y centrado */
.modal-venta-detalle {
    max-width: 900px;
    width: 90%;
    margin: 1.75rem auto;
}
</style>

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
<div id="modalVenta" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-venta-detalle" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de venta en mesa</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body"><!-- contenido dinámico --></div>
            <div class="modal-footer">
                <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de autorización para cambio de estado -->
<div id="modalAuthMesa" class="modal-flotante modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
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
    <div class="modal-dialog modal-dialog-centered">
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
                        <!--label class="mb-0"><input type="checkbox" name="estado" value="reservada"> Reservada</label-->
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

<!-- Modal para asignar mesero (embebido) -->
<div id="modalAsignarMesero" class="modal-flotante modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Asignar mesero a mesa</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="selMeseroAsignar"><strong>Mesero</strong></label>
          <select id="selMeseroAsignar" class="form-control"></select>
        </div>
        <div class="form-group mt-2">
          <label for="passMeseroAsignar">Contraseña del mesero</label>
          <input type="password" id="passMeseroAsignar" class="form-control" autocomplete="current-password">
          <small class="form-text text-muted" id="infoAsignarMesero"></small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn custom-btn" id="btnConfirmarAsignacion">Asignar</button>
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

<!-- Modal de error de promocion -->
<div class="modal fade" id="modalPromoError" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Promocion no aplicable</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="promoErrorMsg"></div>
      </div>
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
window.API_LISTAR_MESAS = '../../api/mesas/listar_mesas.php?user_id=' + encodeURIComponent(window.usuarioActual.id || '') + '&usuario_id=' + encodeURIComponent(window.usuarioActual.id || '');
window.API_MESEROS = '../../api/mesas/meseros.php?user_id=' + encodeURIComponent(window.usuarioActual.id || '') + '&usuario_id=' + encodeURIComponent(window.usuarioActual.id || '');
window.API_LISTAR_MESEROS_USUARIOS = '../../api/usuarios/listar_meseros.php?user_id=' + encodeURIComponent(window.usuarioActual.id || '') + '&usuario_id=' + encodeURIComponent(window.usuarioActual.id || '');
</script>
<script src="../../utils/js/buscador.js"></script>
<script src="kanbanMesas.js"></script>
<script>
// Long-poll ventas: refresca tablero de mesas cuando cambian ventas (apertura/cierre)
(function iniciarLongPollVentasMesas(){
  let since = 0;
  async function tick(){
    try {
      const resp = await fetch('../../api/ventas/listen_cambios.php?since=' + since, { cache: 'no-store' });
      const data = await resp.json();
      if (data && typeof data.version !== 'undefined') since = parseInt(data.version) || since;
      if (data && data.changed) {
        if (typeof cargarMesas === 'function') {
          try { await cargarMesas(); } catch(e) {}
        }
      }
    } catch (e) {
      // silencioso
    } finally {
      setTimeout(tick, 1500);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(tick, 150));
  } else {
    setTimeout(tick, 150);
  }
})();
</script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
