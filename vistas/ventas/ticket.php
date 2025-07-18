<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
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
<div id="dividir" style="display:none;" class="container mt-5">
    <h2 class="section-subheader">Dividir venta</h2>
    <div class="table-responsive">
        <table id="tablaProductos" class="table">
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
    </div>
    <button id="agregarSub" class="btn custom-btn">Agregar subcuenta</button>
    <button id="guardarSub" class="btn custom-btn">Guardar Tickets</button>
    <div id="subcuentas"></div>
    <div id="teclado" class="mt-3"></div>
</div>

<div id="imprimir" style="display:none;" class="custom-modal2">
    <h2 id="nombreRestaurante" class="section-header">Mi Restaurante</h2>
    <div id="fechaHora" style="margin-bottom:10px;"></div>
    <div><strong>Folio:</strong> <span id="folio"></span></div>
    <div><strong>Venta:</strong> <span id="ventaId"></span></div>
    <table id="productos" class="table" style="margin-top: 10px;">
        <tbody></tbody>
    </table>
    <div id="propina" class="mt-2"></div>
    <div id="totalVenta" class="mt-2 mb-2"></div>
    <p>Gracias por su compra</p>
    <button id="btnImprimir" class="btn custom-btn" onclick="window.print()">Imprimir</button>
</div>


<?php require_once __DIR__ . '/../footer.php'; ?>

<script src="ticket.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
