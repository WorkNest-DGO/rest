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
<div>
    <button id="btn-unir">Unir mesas</button>
    <select id="filtro-area"></select>
</div>
<div id="tablero"></div>
<div id="modal-detalle" style="display:none;"></div>

<div>
<section class="section">
	<h1>Kanban Drag and Drop Interface Layout</h1>
	<h4>Inspired by <a href="https://trello.com/
    ">Trello</a>, and <a href="https://www.google.com/keep/">Google Keep</a>, <a href="http://blog.invisionapp.com/design-project-management-tool/">Invision</a> and <a href="https://twitter.com/aaronstump">@aaronstump</a></h4>
</section>

<div class="drag-container">
        <ul class="drag-list" id="kanban-list"></ul>
</div>

</div>




<?php require_once __DIR__ . '/../footer.php'; ?>

<script src="kanbanMesas.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
