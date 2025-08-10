<?php
/**
 * Endpoint genérico para consultar vistas de base de datos.
 * Para agregar una nueva vista, inclúyela en el arreglo $whitelist y
 * agrega su etiqueta legible en el mapa viewLabels de vistas_db.js.
 */
header('Content-Type: application/json');

$whitelist = [
    'vista_productos_mas_vendidos',
    'vista_resumen_cortes',
    'vista_resumen_pagos',
    'vista_ventas_diarias',
    'vista_ventas_por_mesero',
    'vw_consumo_insumos',
    'vw_corte_resumen',
    'vw_ventas_detalladas',
    'logs_accion',
    'log_asignaciones_mesas',
    'log_mesas',
    'movimientos_insumos',
    'fondo',
    'insumos',
    'tickets',
    'ventas',
    'qrs_insumo'
];

try {
    $view = $_GET['view'] ?? '';
    if (!in_array($view, $whitelist, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Vista no permitida']);
        exit;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 15);
    $perPage = in_array($perPage, [15,25,50], true) ? $perPage : 15;
    $search = trim($_GET['search'] ?? '');
    $sortBy = $_GET['sort_by'] ?? '';
    $sortDir = strtolower($_GET['sort_dir'] ?? 'asc');
    $sortDir = $sortDir === 'desc' ? 'DESC' : 'ASC';

    $config = __DIR__ . '/../../config/db.php';
    if (file_exists($config)) {
        require $config; // provee $host, $user, $pass, $db
    }
    $host = $host ?? getenv('DB_HOST') ?? 'localhost';
    $db   = $db   ?? getenv('DB_NAME') ?? 'restaurante';
    $user = $user ?? getenv('DB_USER') ?? 'root';
    $pass = $pass ?? getenv('DB_PASS') ?? '';

    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :view ORDER BY ORDINAL_POSITION");
    $stmt->execute([':view' => $view]);
    $columnsInfo = $stmt->fetchAll();
    if (!$columnsInfo) {
        http_response_code(400);
        echo json_encode(['error' => 'Vista no encontrada']);
        exit;
    }
    $columns = array_column($columnsInfo, 'COLUMN_NAME');
    if (!in_array($sortBy, $columns, true)) {
        $sortBy = '';
    }

    $allowedTypes = ['char','varchar','text','tinytext','mediumtext','longtext','int','bigint','smallint','mediumint','decimal','float','double','date','datetime','timestamp','time','year'];
    $where = '';
    $params = [];
    if ($search !== '') {
        $parts = [];
        foreach ($columnsInfo as $idx => $col) {
            if (in_array(strtolower($col['DATA_TYPE']), $allowedTypes, true)) {
                $ph = ":s{$idx}";
                $parts[] = "`{$col['COLUMN_NAME']}` LIKE {$ph}";
                $params[$ph] = "%{$search}%";
            }
        }
        if ($parts) {
            $where = ' WHERE ' . implode(' OR ', $parts);
        }
    }

    $offset = ($page - 1) * $perPage;

    $countSql = "SELECT COUNT(*) FROM `{$view}`{$where}";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $ph => $val) {
        $countStmt->bindValue($ph, $val);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $dataSql = "SELECT * FROM `{$view}`{$where}";
    if ($sortBy) {
        $dataSql .= " ORDER BY `{$sortBy}` {$sortDir}";
    }
    $dataSql .= " LIMIT :limit OFFSET :offset";

    $dataStmt = $pdo->prepare($dataSql);
    foreach ($params as $ph => $val) {
        $dataStmt->bindValue($ph, $val);
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll();

    echo json_encode([
        'columns' => $columns,
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}