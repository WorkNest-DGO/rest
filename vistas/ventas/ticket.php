<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
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

<div id="dividir" style="display:none;">
    <h2>Dividir venta</h2>
    <table id="tablaProductos" border="1">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant</th>
                <th>Precio</th>
                <th>Subcuenta</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
    <button id="agregarSub">Agregar subcuenta</button>
    <button id="guardarSub">Guardar Tickets</button>
    <div id="subcuentas"></div>
    <div id="teclado" style="margin-top:10px;"></div>
</div>

<div id="imprimir" style="display:none;">
    <h2 id="nombreRestaurante">Mi Restaurante</h2>
    <div id="fechaHora"></div>
    <div>Folio: <span id="folio"></span></div>
    <div>Venta: <span id="ventaId"></span></div>
    <table id="productos">
        <tbody></tbody>
    </table>
    <div id="propina"></div>
    <div id="totalVenta"></div>
    <p>Gracias por su compra</p>
    <button id="btnImprimir" onclick="window.print()">Imprimir</button>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>

<script src="ticket.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
