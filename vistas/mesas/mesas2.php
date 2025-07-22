<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$title = 'Mesas';
ob_start();
?>
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
<div class='app'>
    <main class='project'>
        <div class='project-info'>
            <h1>Asignación actual</h1>
        </div>
        <div id='tablero-meseros' class='project-tasks'></div>
    </main>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="mesas2.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
