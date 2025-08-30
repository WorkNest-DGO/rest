<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
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
</div>
<div id="dividir" style="display:none;" class="container mt-5">
    <h2 class="section-subheader">Dividir venta</h2>
    <div class="table-responsive">
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


<?php require_once __DIR__ . '/../footer.php'; ?>
<script>
    const catalogosUrl = '../../api/tickets/catalogos_tarjeta.php';
    const denominacionesUrl = '../../api/corte_caja/listar_denominaciones.php';
</script>
<script src="ticket.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
