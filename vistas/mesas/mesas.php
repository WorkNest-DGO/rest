<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Mesas';
ob_start();
?>
<!-- Page Header Start -->


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


<!-- Page Header End -->
<h1>Mesas</h1>
<div>
    <button id="btn-unir">Unir mesas</button>
    <select id="filtro-area"></select>
</div>
<div id="tablero"></div>
<div id="modal-detalle" style="display:none;"></div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="mesas.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
