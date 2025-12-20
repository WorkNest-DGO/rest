<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
// Base app dinamica y ruta relativa para validacion
$__sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$__pos = strpos($__sn, '/vistas/');
$__app_base = $__pos !== false ? substr($__sn, 0, $__pos) : rtrim(dirname($__sn), '/');
$path_actual = preg_replace('#^' . preg_quote($__app_base, '#') . '#', '', ($__sn ?: $_SERVER['PHP_SELF']));
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
$title = 'Impresoras';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Impresoras</h2>
            </div>
            <div class="col-12">
                <a href="../../index.php">Inicio</a>
                <a href="">Impresoras</a>
            </div>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-end mb-3">
        <button class="btn custom-btn" id="btnAgregar">Agregar</button>
    </div>
    <div class="filtros-container mb-2">
        <label for="buscarImpresora" class="me-2">Buscar:</label>
        <input type="text" id="buscarImpresora" class="form-control" placeholder="Nombre logico, lugar o IP">
    </div>
    <div class="row mt-2">
        <div class="col-12">
            <ul id="paginadorImpresoras" class="pagination justify-content-center"></ul>
        </div>
    </div>
    <div class="table-responsive">
        <table id="tablaImpresoras" class="styled-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre logico</th>
                    <th>Lugar</th>
                    <th>IP</th>
                    <th>Activo</th>
                    <th>Sede</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalImpresora" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formImpresora">
                <div class="modal-header">
                    <h5 class="modal-title">Impresora</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="printIdOriginal">
                    <div class="form-group">
                        <label for="printId">ID:</label>
                        <input type="number" id="printId" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="nombreLogico">Nombre logico:</label>
                        <select id="nombreLogico" class="form-control" required>
                            <option value="barra">barra</option>
                            <option value="frio">frio</option>
                            <option value="caliente">caliente</option>
                            <option value="ticket">ticket</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="lugar">Lugar:</label>
                        <input type="text" id="lugar" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="ip">IP:</label>
                        <input type="text" id="ip" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="activo">Activo:</label>
                        <select id="activo" class="form-control">
                            <option value="1">Si</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sede">Sede:</label>
                        <input type="number" id="sede" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn custom-btn">Guardar</button>
                    <button type="button" class="btn custom-btn" data-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
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

<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="../../utils/js/modal-lite.js"></script>
<script src="impresoras.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
