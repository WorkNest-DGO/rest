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

// Validación: si la venta ya tiene ticket, mostrar solo sección de propinas
$ventaIdParam = isset($_GET['venta']) ? (int)$_GET['venta'] : 0;
$soloPropinas = false;
if ($ventaIdParam > 0) {
    if ($st = $conn->prepare('SELECT 1 FROM tickets WHERE venta_id = ? LIMIT 1')) {
        $st->bind_param('i', $ventaIdParam);
        if ($st->execute()) {
            $st->store_result();
            $soloPropinas = $st->num_rows > 0;
        }
        $st->close();
    }
}

$title = 'Ticket';
$tieneTicket = false;
$tienePropina = false;
$totalVenta = 0.00; // total de la venta (sin propinas, desde ventas.total o sum(detalles))
$propinaTotal = 0.00;
if ($ventaIdParam > 0) {
    // ¿Existe ticket?
    if ($st = $conn->prepare('SELECT 1 FROM tickets WHERE venta_id = ? LIMIT 1')) {
        $st->bind_param('i', $ventaIdParam);
        if ($st->execute()) { $st->store_result(); $tieneTicket = $st->num_rows > 0; }
        $st->close();
    }
    // ¿Propina registrada en ventas?
    if ($sp = $conn->prepare('SELECT total, COALESCE(propina_efectivo,0)+COALESCE(propina_cheque,0)+COALESCE(propina_tarjeta,0) AS propina_total FROM ventas WHERE id = ?')) {
        $sp->bind_param('i', $ventaIdParam);
        if ($sp->execute()) {
            $r = $sp->get_result()->fetch_assoc();
            $propinaTotal = (float)($r['propina_total'] ?? 0);
            $tienePropina = $propinaTotal > 0;
            $totalVenta = isset($r['total']) ? (float)$r['total'] : 0.00;
        }
        $sp->close();
    }
    // Si ventas.total no está seteado, calcular desde venta_detalles
    if ($totalVenta <= 0.0) {
        if ($sv = $conn->prepare('SELECT SUM(cantidad * precio_unitario) AS total FROM venta_detalles WHERE venta_id = ?')) {
            $sv->bind_param('i', $ventaIdParam);
            if ($sv->execute()) { $row = $sv->get_result()->fetch_assoc(); $totalVenta = (float)($row['total'] ?? 0); }
            $sv->close();
        }
    }
}
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
    <style>
      /* Layout: dos columnas en desktop para facilitar uso */
      #dividir { display: grid; grid-template-columns: 1.4fr 1fr; gap: 16px; align-items: start; }
      #dividir > h2 { grid-column: 1 / -1; }
      #dividir > .table-responsive, #dividir > #descuentosPanel { grid-column: 1; }
      #dividir > .mb-2,
      #dividir > .btn-row,
      #dividir > #subcuentas,
      #dividir > #regPropinas,
      #dividir > #teclado,
      #dividir > .monto-actual { grid-column: 2; }
      .btn-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
      .btn-row > .btn { width: 100%; }
      .monto-actual { display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border: 1px solid #333; border-radius: 6px; }
      .monto-actual #lblMontoActualGlobal { font-size: 20px; font-weight: bold; }
      #subcuentas { max-height: 50vh; overflow: auto; }
      @media (max-width: 992px) {
        #dividir { grid-template-columns: 1fr; }
        #dividir > .mb-2,
        #dividir > .btn-row,
        #dividir > #subcuentas,
        #dividir > #regPropinas,
        #dividir > #teclado,
        #dividir > .monto-actual { grid-column: 1; }
      }
    </style>
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
    <div class="btn-row">
      <button id="agregarSub" class="btn custom-btn">Agregar subcuenta</button>
      <button id="btnCrearTicket" class="btn custom-btn">imprimir Tickets</button>
    </div>
    <div id="subcuentas"></div>
    <div class="monto-actual" style="margin-top:10px;">
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

<!-- Modal de error de promoción -->
<div class="modal fade" id="modalPromoError" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Promoción no aplicable</h5>
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
    // Señales servidor
    window.__SOLO_PROPINAS__ = <?php echo ($tieneTicket && !$tienePropina) ? 'true' : 'false'; ?>; // ticket sin propina => solo propinas
    window.__TIENE_PROPINA__ = <?php echo $tienePropina ? 'true' : 'false'; ?>; // ya hay propina => ocultar combos
    // Monto actual = total de venta + propinas
    window.__TOTAL_VENTA__ = <?php echo json_encode(number_format(max(0, $totalVenta + $propinaTotal), 2, '.', '')); ?>;
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
<script>
// Al cargar, si el servidor indica SOLO_PROPINAS, ocultar controles de cobro y mostrar solo propinas
document.addEventListener('DOMContentLoaded', function(){
  try {
    if (window.__SOLO_PROPINAS__) {
      var div = document.getElementById('dividir');
      if (div) { div.style.display = 'block'; }
      var idsOcultar = ['tablaProductos','descuentosPanel','agregarSub','btnCrearTicket','subcuentas','teclado','btnDescuentos'];
      idsOcultar.forEach(function(id){ var el = document.getElementById(id); if (el) { el.style.display = 'none'; el.disabled = true; }});
      var reg = document.getElementById('regPropinas');
      if (reg) { reg.style.display = 'block'; }
      // Habilitar inputs de propinas y mostrarlos
      var pe = document.getElementById('propinaEfectivo'); if (pe) { pe.disabled = false; }
      var pc = document.getElementById('propinaCheque'); if (pc) { pc.disabled = false; }
      var pt = document.getElementById('propinaTarjeta'); if (pt) { pt.disabled = false; }
      var ped = document.getElementById('propinaEfectivoD'); if (ped) { ped.style.display = 'block'; }
      var pcd = document.getElementById('propinaChequeD'); if (pcd) { pcd.style.display = 'block'; }
      var ptd = document.getElementById('propinaTarjetaD'); if (ptd) { ptd.style.display = 'block'; }
    } else if (window.__TIENE_PROPINA__) {
      // Ya existe propina: ocultar combos de propina y mostrar solo Monto actual
      var div = document.getElementById('dividir');
      if (div) { div.style.display = 'block'; }
      var idsOcultar2 = ['tablaProductos','descuentosPanel','agregarSub','btnCrearTicket','subcuentas','teclado','btnDescuentos','propinaEfectivoD','propinaChequeD','propinaTarjetaD','btnGuardarTicket','regPropinas'];
      idsOcultar2.forEach(function(id){ var el = document.getElementById(id); if (el) { el.style.display = 'none'; el.disabled = true; }});
      var g = document.getElementById('lblMontoActualGlobal');
      if (g) { g.textContent = (Number(window.__TOTAL_VENTA__) || 0).toFixed(2); }
    }
  } catch(e) { /* noop */ }
});
</script>
<script src="ticket.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
