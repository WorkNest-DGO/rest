<?php
  require_once __DIR__ . '/../config/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    $sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
    $pos = strpos($sn, '/vistas/');
    $baseUrl = $pos !== false ? substr($sn, 0, $pos) : rtrim(dirname($sn), '/');
    if ($baseUrl === '') { $baseUrl = '/'; }

    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );

    if ($isAjax) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(401);
        }
        echo json_encode(['success' => false, 'mensaje' => 'No autenticado', 'redirect' => $baseUrl . '/index.php']);
        exit;
    }

    if (!headers_sent()) {
        header('Location: ' . $baseUrl . '/index.php');
    }
    exit;
}

if (!isset($_SESSION['rutas_permitidas'])) {
  

    $usuario_id = $_SESSION['usuario_id'];
    $sql = "SELECT r.path FROM usuario_ruta ur INNER JOIN rutas r ON ur.ruta_id = r.id WHERE ur.usuario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $_SESSION['rutas_permitidas'] = [];
    while ($row = $result->fetch_assoc()) {
        $_SESSION['rutas_permitidas'][] = $row['path'];
    }
}
?>
