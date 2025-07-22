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
<!-- Dragula CSS -->
<link rel="stylesheet" href="https://unpkg.com/dragula@3.7.2/dist/dragula.min.css">

<!-- Dragula JS -->
<script src="https://unpkg.com/dragula@3.7.2/dist/dragula.min.js"></script>

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
	<ul class="drag-list">
		<li class="drag-column drag-column-on-hold">
			<span class="drag-column-header">
				<h2>On Hold</h2>
				<svg class="drag-header-more" data-target="options1" fill="#FFFFFF" height="24" viewBox="0 0 24 24" width="24"><path d="M0 0h24v24H0z" fill="none"/><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/</svg>
			</span>
				
			<div class="drag-options" id="options1"></div>
			
			<ul class="drag-inner-list" id="1">
				<li class="drag-item"></li>
				<li class="drag-item"></li>
			</ul>
		</li>

	</ul>
</div>

</div>




<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="mesas2.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
