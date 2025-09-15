<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
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

$title = 'Ticket';
ob_start();
?>
<!-- Page Header Start -->
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Impresion de Tickets</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Modulo de Tickets</a>
            </div>
        </div>
    </div>
</div>
<!-- Page Header End -->
<div id="sinDatos" class="container mt-5">Sin datos cargados</div>
<div id="divReimprimir" style="display:none;">
<br>
<button id="btnReimprimir" class="btn custom-btn">Re-Imprimir tickets</button>
<!-- PROMOS: acceso al módulo de promociones en nueva pestaña -->
<a href="../promociones/index.php" target="_blank" class="btn btn-secondary" style="margin-left:8px;">Promociones</a>
</div>
<div id="dividir" style="display:none;" class="container mt-5">
    <h2 class="section-subheader">Dividir venta</h2>
    <div class="table-responsive">
        <style>
          /* Bloqueo visual de descuentos/cortesías hasta desbloquear */
          #dividir.bloq-desc .descuentosPanel { display: none !important; }
          /* Tabla principal: ocultar columna 4 (Cortesía) */
          #dividir.bloq-desc #tablaProductos thead th:nth-child(4),
          #dividir.bloq-desc #tablaProductos tbody td:nth-child(4) { display: none !important; }
          /* Tablas de subcuentas: ocultar columna 3 (Cortesía) */
          #dividir.bloq-desc #subcuentas table thead th:nth-child(3),
          #dividir.bloq-desc #subcuentas table tbody td:nth-child(3) { display: none !important; }
        </style>
        <table id="tablaProductos" class="styled-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant</th>
                    <th>Precio</th>
                    <th>Cortesía</th>
                    <th>Subcuenta</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <!-- Panel de descuentos global oculto (los descuentos se aplican por subcuenta) -->
    <div id="descuentosPanel" style="display:none;"></div>
    <button id="agregarSub" class="btn custom-btn">Agregar subcuenta</button>
    <button id="btnCrearTicket" class="btn custom-btn">imprimir Tickets</button>
    <div id="subcuentas"></div>
    <div style="margin-top:10px;">
      <strong>Monto actual (suma de subcuentas):</strong>
      <span id="lblMontoActualGlobal">0.00</span>
    </div>
    <br>
    <div class="mb-2">
      <button id="btnDescuentos" type="button" class="btn btn-warning">Descuentos</button>
    </div>
    <div id="regPropinas" style="display:none;">
        <h3>Propinas:</h3>
        <div id="propinaEfectivoD" style="display:none;">
            <label for="propinaEfectivo" class="form-label">Efectivo: </label>
            <input type="number" step="0.01" disabled class="form-control" id="propinaEfectivo" required>
        </div>
        <div id="propinaChequeD" style="display:none;">
            <label for="propinaCheque" class="form-label">Cheque: </label>
            <input type="number" step="0.01" disabled class="form-control" id="propinaCheque" required>
        </div>
        <div id="propinaTarjetaD" style="display:none;">
            <label for="propinaTarjeta" class="form-label">Tarjeta: </label>
            <input type="number" step="0.01" disabled class="form-control" id="propinaTarjeta" required>
        </div>
    <br>
    <button id="btnGuardarTicket" class="btn custom-btn">Guardar</button>
    </div>
    <div id="teclado" class="mt-3"></div>
</div>

<div id="imprimir" style="display:none;" class="custom-modal2">
    <div id="ticketContainer">
        <img id="ticketLogo" src="../../utils/logo.png" alt="Logo" style="max-width:100px;">
        <h2 id="nombreRestaurante" class="section-header">Mi Restaurante</h2>
        <div id="direccionNegocio"></div>
        <div id="rfcNegocio"></div>
        <div id="telefonoNegocio"></div>
        <div id="fechaHora" style="margin-bottom:10px;"></div>
        <div><strong>Folio:</strong> <span id="folio"></span></div>
        <div><strong>Venta:</strong> <span id="ventaId"></span></div>
        <div><strong>Sede:</strong> <span id="sedeId"></span></div>
        <div><strong>Mesa:</strong> <span id="mesaNombre"></span></div>
        <div><strong>Mesero:</strong> <span id="meseroNombre"></span></div>
        <div><strong>Tipo entrega:</strong> <span id="tipoEntrega"></span></div>
        <div><strong>Tipo pago:</strong> <span id="tipoPago"></span></div>
        <div id="tarjetaInfo" style="display:none;">
            <div><strong>Marca tarjeta:</strong> <span id="tarjetaMarca"></span></div>
            <div><strong>Banco:</strong> <span id="tarjetaBanco"></span></div>
            <div><strong>Boucher:</strong> <span id="tarjetaBoucher"></span></div>
        </div>
        <div id="chequeInfo" style="display:none;">
            <div><strong>No. Cheque:</strong> <span id="chequeNumero"></span></div>
            <div><strong>Banco:</strong> <span id="chequeBanco"></span></div>
        </div>
        <div><strong>Inicio:</strong> <span id="horaInicio"></span></div>
        <div><strong>Fin:</strong> <span id="horaFin"></span></div>
        <div><strong>Tiempo:</strong> <span id="tiempoServicio"></span></div>
        <table id="productos" class="styled-table" style="margin-top: 10px;">
            <tbody></tbody>
        </table>
        <!-- <div class="mt-2"><strong>Propina:</strong> <span id="propina"></span></div> -->
        <div class="mt-2"><strong>Cambio:</strong> <span id="cambio"></span></div>
        <div id="totalVenta" class="mt-2 mb-2"></div>
        <div id="totalLetras"></div>
        <p>Gracias por su compra</p>
    </div>
    <button id="btnImprimir" class="btn custom-btn">Imprimir</button>
</div>

<!-- Modal login admin para activar descuentos -->
<div class="modal fade" id="modalDescuentos" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Desbloquear descuentos</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Usuario (admin)</label>
          <input type="text" class="form-control" id="adminUser" autocomplete="username">
        </div>
        <div class="form-group">
          <label>Contraseña</label>
          <input type="password" class="form-control" id="adminPass" autocomplete="current-password">
        </div>
        <div id="adminMsg" class="text-danger"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn custom-btn" id="btnConfirmarDescuentos">Desbloquear</button>
      </div>
    </div>
  </div>
  </div>

<?php require_once __DIR__ . '/../footer.php'; ?>
<script>
    const catalogosUrl = '../../api/tickets/catalogos_tarjeta.php';
    const promocionesUrl = '../../api/tickets/promociones.php';
    const denominacionesUrl = '../../api/corte_caja/listar_denominaciones.php';
    // Bloquear descuentos/cortesías por defecto, y permitir desbloquear con admin
    document.addEventListener('DOMContentLoaded', () => {
      const dividir = document.getElementById('dividir');
      if (dividir && !dividir.classList.contains('bloq-desc')) dividir.classList.add('bloq-desc');
      const btn = document.getElementById('btnDescuentos');
      if (btn) btn.addEventListener('click', ()=> { if (window.jQuery && $('#modalDescuentos').modal) { $('#modalDescuentos').modal('show'); } });
      const confirmar = document.getElementById('btnConfirmarDescuentos');
      if (confirmar) confirmar.addEventListener('click', async ()=>{
        const u = (document.getElementById('adminUser').value||'').trim();
        const p = document.getElementById('adminPass').value||'';
        const msg = document.getElementById('adminMsg');
        msg.textContent='';
        try {
          const resp = await fetch('../../api/usuarios/verificar_admin.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({usuario:u, contrasena:p})});
          const data = await resp.json();
          if (!data.success) { msg.textContent = data.mensaje || 'No autorizado'; return; }
          if (dividir) dividir.classList.remove('bloq-desc');
          if (window.jQuery && $('#modalDescuentos').modal) { $('#modalDescuentos').modal('hide'); }
          const b = document.getElementById('btnDescuentos');
          if (b) { b.disabled = true; b.textContent = 'Descuentos desbloqueados'; }
        } catch (e) { msg.textContent = 'Error de red'; }
      });
    });
</script>
<script src="ticket.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
