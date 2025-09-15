<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
// Base app dinámica y ruta relativa para validación
$__sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$__pos = strpos($__sn, '/vistas/');
$__app_base = $__pos !== false ? substr($__sn, 0, $__pos) : rtrim(dirname($__sn), '/');
$path_actual = preg_replace('#^' . preg_quote($__app_base, '#') . '#', '', ($__sn ?: $_SERVER['PHP_SELF']));
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

$title = 'dash';
ob_start();
?>
<!-- Page Header Start -->
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="section-header">Panel de Administrador</h1>
            </div>
        </div>
    </div>
</div>

<div class="container mt-5 mb-5">
    <div class="table-responsive">
        <table class="styled-table">
            <thead>
                <tr>
                    <th colspan="3" class="text-center">Tablas</th>
                </tr>
            </thead>
            <tbody>
                <tr class="text-center">
                    <td class="px-3 py-4">
                        <a href="./bancos.php" class="btn custom-btn">Catalogo de Bancos</a>
                    </td>
                    <td class="px-3 py-4">
                        <a href="./sedes.php" class="btn custom-btn">Sedes</a>
                    </td>
                    <td class="px-3 py-4">
                        <a href="./tarjetas.php" class="btn custom-btn">Catalogo de Tarjetas</a>
                    </td>
                </tr>
                
                <tr class="text-center">
                    <td class="px-3 py-4">
                        <a href="" class="btn custom-btn">Catalogo de Categorias</a>
                    </td>
                    <td class="px-3 py-4">
                        <a href="" class="btn custom-btn">Repartidores</a>
                    </td>
                    <td class="px-3 py-4">
                        <a href="" class="btn custom-btn">Alineacion de Mesas</a>
                    </td>
                </tr>       
                <tr class="text-center">
                    <td class="px-3 py-4">
                        <a href="" class="btn custom-btn">Tabla 7</a>
                    </td>
                    <td class="px-3 py-4">
                        <a href="" class="btn custom-btn">Tabla 8</a>
                    </td>
                    <td class="px-3 py-4">
                        <a href="" class="btn custom-btn">Tabla 9</a>
                    </td>
                </tr>  
                <tr class="text-center">
                    <td class="px-3 py-4">
                        <a href="" class="btn custom-btn">Tabla 7</a>
                    </td>
                    <td class="px-3 py-4">
                        <a href="" class="btn custom-btn">Tabla 8</a>
                    </td>
                    <td class="px-3 py-4">
                        <a href="" class="btn custom-btn">Tabla 9</a>
                    </td>
                </tr> 


            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="/../utils/js/modal-lite.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
