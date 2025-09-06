<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
$title = 'Horarios';
ob_start();
?>
<!-- Page Header Start -->
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Horarios</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Horarios de cobro</a>
            </div>
        </div>
    </div>
</div>
<!-- Page Header End -->


<div class="container mt-5 mb-5 custom-modal">
    <h1 class="text-center" style="color:#b80000;">Horarios de Series</h1>
    <form id="formHorario" class="form-box p-4">
        <input type="hidden" id="horarioId">

        <div class="form-group">
            <label for="dia_semana" class="form-label" >Día:</label>
            <select id="dia_semana" class="form-control">
                <option value="Lunes">Lunes</option>
                <option value="Martes">Martes</option>
                <option value="Miercoles">Miércoles</option>
                <option value="Jueves">Jueves</option>
                <option value="Viernes">Viernes</option>
                <option value="Sabado">Sábado</option>
                <option value="Domingo">Domingo</option>
            </select>
        </div>

        <div class="form-group">
            <label for="hora_inicio" class="form-label" >Inicio:</label>
            <input type="time" id="hora_inicio" class="form-control">
        </div>

        <div class="form-group">
            <label for="hora_fin" class="form-label" >Fin:</label>
            <input type="time" id="hora_fin" class="form-control">
        </div>

        <div class="form-group">
            <label for="serie_id" class="form-label" >Serie:</label>
            <select id="serie_id" class="form-control"></select>
        </div>

        <div class="form-group text-right">
            <button type="submit" class="btn custom-btn">Guardar</button>
            <button type="button" id="cancelar" class="btn custom-btn" style="display:none;">Cancelar</button>
        </div>
    </form>
</div>


<div class="container mt-5 mb-5">
    <h2>Horarios Configurados</h2>
    <div class="filtros-container mb-2">
        <label for="buscarHorario" class="me-2">Buscar:</label>
        <input type="text" id="buscarHorario" class="form-control" placeholder="Día, serie...">
    </div>
        <div class="row mt-2">
        <div class="col-12">
            <ul id="paginadorHorarios" class="pagination justify-content-center"></ul>
        </div>
    </div>
    <div class="table-responsive">
        <table id="tablaHorarios" class="table table-bordered custom-table">
            <thead class="thead-dark">
                <tr>
                    <th>Día</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Serie</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
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
<script src="horarios.js"></script>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
