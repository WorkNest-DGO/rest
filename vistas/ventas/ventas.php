
<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Ventas';
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas</title>
</head>
<body>
    <h1>Registro de Venta</h1>
    <div id="controlCaja"></div>
    <form id="formVenta">
        <label for="tipo_entrega">Tipo de venta:</label>
        <select id="tipo_entrega" name="tipo_entrega">
            <option value="mesa">En restaurante</option>
            <option value="domicilio">A domicilio</option>
        </select>
        <div id="campoMesa">
            <label for="mesa_id">Mesa:</label>
            <select id="mesa_id" name="mesa_id">
                <option value="1">Mesa 1</option>
                <option value="2">Mesa 2</option>
                <option value="3">Mesa 3</option>
            </select>
        </div>
        <div id="campoRepartidor" style="display:none;">
            <label for="repartidor_id">Repartidor:</label>
            <select id="repartidor_id" name="repartidor_id"></select>
        </div>
        <label for="usuario_id">Mesero:</label>
        <select id="usuario_id" name="usuario_id" required></select>
        <h2>Productos</h2>
        <table id="productos" border="1">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <select class="producto"></select>
                    </td>
                    <td><input type="number" class="cantidad"></td>
                    <td><input type="number" step="0.01" class="precio" readonly></td>
                </tr>
            </tbody>
        </table>
        <button type="button" id="agregarProducto">Agregar Producto</button>
        <button type="button" id="registrarVenta">Registrar Venta</button>
    </form>

    <h2>Historial de Ventas</h2>
    <table id="historial" border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Total</th>
                <th>Tipo</th>
                <th>Destino</th>
                <th>Estatus</th>
                <th>Entregado</th>
                <th>Ver detalles</th>
                <th>Acci&oacute;n</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <h2>Solicitudes de Ticket</h2>
    <table id="solicitudes" border="1">
        <thead>
            <tr>
                <th>Mesa</th>
                <th>Imprimir</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <div id="modal-detalles" style="display:none;"></div>
    <div id="modalDesglose" style="display:none;"></div>

    <script>
        // ID de usuario proveniente de la sesión para operaciones en JS
        window.usuarioId = <?php echo json_encode($_SESSION['usuario_id']); ?>;
    </script>
    <script src="ventas.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>