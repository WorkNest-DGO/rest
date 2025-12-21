<?php
// Fuerza salida en UTF-8 para evitar problemas de acentos en vistas
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
@ini_set('default_charset', 'UTF-8');
// Detecta el base URL dinámicamente según la ruta del script (raíz del app)
if (!defined('BASE_URL')) {
    $sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $pos = strpos($sn, '/vistas/');
    $bu = $pos !== false ? substr($sn, 0, $pos) : rtrim(dirname($sn), '/');
    if ($bu === '') { $bu = '/'; }
    define('BASE_URL', $bu);
}
$base_url = BASE_URL;

require_once __DIR__ . '/../utils/cargar_permisos.php';

$navUserName = 'Usuario';
$navUserRol = '';
$navUserSede = '';
if (!empty($_SESSION['usuario_id'])) {
    $stmtUserNav = $conn->prepare('SELECT u.nombre, u.rol, s.nombre AS sede_nombre FROM usuarios u LEFT JOIN sedes s ON s.id = u.sede_id WHERE u.id = ? LIMIT 1');
    if ($stmtUserNav) {
        $stmtUserNav->bind_param('i', $_SESSION['usuario_id']);
        if ($stmtUserNav->execute()) {
            $rowNav = $stmtUserNav->get_result()->fetch_assoc();
            if ($rowNav) {
                $navUserName = $rowNav['nombre'] ?? $navUserName;
                $navUserRol = $rowNav['rol'] ?? '';
                $navUserSede = $rowNav['sede_nombre'] ?? '';
            }
        }
        $stmtUserNav->close();
    }
}

$rutas_permitidas = $_SESSION['rutas_permitidas'];
$rutas = [];
if ($rutas_permitidas) {
    $placeholders = implode(',', array_fill(0, count($rutas_permitidas), '?'));
    $types = str_repeat('s', count($rutas_permitidas));
    $sql = "SELECT nombre, path, tipo, grupo, orden FROM rutas WHERE path IN ($placeholders) ORDER BY orden ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$rutas_permitidas);
    $stmt->execute();
    $rutas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$links = [];
$dropdowns = [];
foreach ($rutas as $ruta) {
    if ($ruta['tipo'] === 'link') {
        $links[] = $ruta;
    } elseif ($ruta['tipo'] === 'dropdown') {
        if (!isset($dropdowns[$ruta['grupo']])) {
            $dropdowns[$ruta['grupo']] = [
                'label' => $ruta['nombre'],
                'items' => []
            ];
        }
    } elseif ($ruta['tipo'] === 'dropdown-item') {
        if (!isset($dropdowns[$ruta['grupo']])) {
            $dropdowns[$ruta['grupo']] = [
                'label' => $ruta['grupo'],
                'items' => []
            ];
        }
        $dropdowns[$ruta['grupo']]['items'][] = $ruta;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tokyo Sushi POS <?= $title ?? 'Sistema'; ?></title>
    <meta name="description" content="Sistema de punto de venta de Tokyo Sushi para control de cobros y operaciones.">
    <meta name="author" content="Tokyo Sushi">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400|Nunito:600,700" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/css/all.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/lib/animate/animate.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet">
    <link rel="icon" href="<?= $base_url ?>/utils/logo.png" type="image/png">
    <link href="<?= $base_url ?>/utils/css/style1.css" rel="stylesheet">
    <script>
    window.BASE_URL = '<?= $base_url ?>';
    (function(){
      const _fetch = window.fetch ? window.fetch.bind(window) : null;
      if (_fetch) {
        window.fetch = function(input, init){
          return _fetch(input, init).then(function(res){
            try {
              if (res && res.status === 401) {
                window.location.href = (window.BASE_URL || '') + '/index.php';
              } else {
                const ct = res && res.headers && res.headers.get ? (res.headers.get('content-type') || '') : '';
                if (ct.indexOf('application/json') !== -1) {
                  res.clone().json().then(function(json){
                    try {
                      if (json && typeof json === 'object') {
                        const msg = String((json.mensaje || json.msg || '') + '');
                        if (/No\s*autenticado/i.test(msg)) {
                          window.location.href = (window.BASE_URL || '') + '/index.php';
                        } else if (json.redirect) {
                          window.location.href = String(json.redirect);
                        }
                      }
                    } catch(e) {}
                  }).catch(function(){});
                }
              }
            } catch(e) {}
            return res;
          });
        };
      }
    })();
    </script>
</head>

<body>
<div class="navbar navbar-expand-lg bg-light navbar-light">
    <div class="container-fluid d-flex align-items-center">
        <a href="<?= $base_url ?>/vistas/index.php" class="navbar-brand">Tokyo <span style="text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;">Sushi</span></a>
        <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-between" id="navbarCollapse">
            <div class="navbar-nav ml-auto">
                <?php foreach ($links as $link): ?>
                    <a href="<?= $base_url . $link['path'] ?>" class="nav-item nav-link"><?= htmlspecialchars($link['nombre']) ?></a>
                <?php endforeach; ?>

                <?php foreach ($dropdowns as $grupo): ?>
                    <?php if (count($grupo['items']) > 0): ?>
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown"><?= htmlspecialchars($grupo['label']) ?></a>
                            <div class="dropdown-menu">
                                <?php foreach ($grupo['items'] as $item): ?>
                                    <a href="<?= $base_url . $item['path'] ?>" class="dropdown-item"><?= htmlspecialchars($item['nombre']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-toggle="dropdown">
                        <i class="fas fa-user-circle mr-2"></i>
                        <span class="d-none d-sm-inline"><?php echo htmlspecialchars($navUserName); ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <span class="dropdown-item-text font-weight-bold"><?php echo htmlspecialchars($navUserName); ?></span>
                        <?php if ($navUserRol): ?>
                            <span class="dropdown-item-text">Rol: <?php echo htmlspecialchars($navUserRol); ?></span>
                        <?php endif; ?>
                        <?php if ($navUserSede): ?>
                            <span class="dropdown-item-text">Sede: <?php echo htmlspecialchars($navUserSede); ?></span>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="<?= $base_url ?>/vistas/dashboard/usuario.php" class="dropdown-item">Mis datos</a>
                        <a href="<?= $base_url ?>/vistas/logout.php" class="dropdown-item">Cerrar sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php echo $content ?? ''; ?>
