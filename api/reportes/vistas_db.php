<?php
/**
 * Endpoint para listar fuentes (vistas/tablas) y obtener datos paginados.
 *
 * Mantiene compatibilidad con la versión anterior que recibía parámetros:
 *   view, page, per_page, search, sort_by, sort_dir
 * y retornaba un arreglo plano de columnas.
 *
 * Nueva interfaz:
 *   ?action=list  => {ok:true, views:[...], tables:[...]}
 *   ?action=data&source=...&page=1&pageSize=25&q=...&orderBy=col&orderDir=asc
 *                  => {ok:true, source:"...", page:1, pageSize:25, total:0,
 *                      columns:[{key,label,type}], rows:[...]}
 */

header('Content-Type: application/json');

$fixedTables = [
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
    // --- Conexión a la base de datos ---
    $config = __DIR__ . '/../../config/db.php';
    if (file_exists($config)) {
        require $config; // define $host, $user, $pass, $db si existen
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

    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        // Devuelve vistas disponibles y tablas fijas
        $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
        $views = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode([
            'ok' => true,
            'views' => $views,
            'tables' => $fixedTables
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Parametrización unificada (nuevo y legacy)
    $source   = $_GET['source']   ?? $_GET['view']     ?? '';
    if ($source === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Parámetro source/view requerido']);
        exit;
    }

    // Obtener lista de vistas para validación
    $viewsStmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE()");
    $views = $viewsStmt->fetchAll(PDO::FETCH_COLUMN);
    $allowedSources = array_merge($views, $fixedTables);
    if (!in_array($source, $allowedSources, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Fuente no permitida']);
        exit;
    }

    $page     = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = (int)($_GET['pageSize'] ?? $_GET['per_page'] ?? 15);
    $pageSize = in_array($pageSize, [15, 25, 50], true) ? $pageSize : 15;
    $q        = trim($_GET['q'] ?? $_GET['search'] ?? '');
    $orderBy  = $_GET['orderBy'] ?? $_GET['sort_by'] ?? '';
    $orderDir = strtolower($_GET['orderDir'] ?? $_GET['sort_dir'] ?? 'asc');
    $orderDir = $orderDir === 'desc' ? 'DESC' : 'ASC';

    // Obtener metadata de columnas
    $stmt = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl ORDER BY ORDINAL_POSITION");
    $stmt->execute([':tbl' => $source]);
    $columnsInfo = $stmt->fetchAll();
    if (!$columnsInfo) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Fuente no encontrada']);
        exit;
    }

    $columnNames = array_column($columnsInfo, 'COLUMN_NAME');
    if (!in_array($orderBy, $columnNames, true)) {
        $orderBy = $columnNames[0]; // orden por primera columna
    }

    // Construcción de cláusula WHERE para búsqueda
    $allowedTypes = [
        'char','varchar','text','tinytext','mediumtext','longtext',
        'int','bigint','smallint','mediumint','decimal','float','double',
        'date','datetime','timestamp','time','year'
    ];
    $where = '';
    $params = [];
    if ($q !== '') {
        $parts = [];
        foreach ($columnsInfo as $idx => $col) {
            if (in_array(strtolower($col['DATA_TYPE']), $allowedTypes, true)) {
                $ph = ":s{$idx}";
                $parts[] = "`{$col['COLUMN_NAME']}` LIKE {$ph}";
                $params[$ph] = "%{$q}%";
            }
        }
        if ($parts) {
            $where = ' WHERE ' . implode(' OR ', $parts);
        }
    }

    $offset = ($page - 1) * $pageSize;

    // Conteo total
    $countSql = "SELECT COUNT(*) FROM `{$source}`{$where}";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $ph => $val) {
        $countStmt->bindValue($ph, $val);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    // Datos
    $colsList = '`' . implode('`,`', $columnNames) . '`';
    $dataSql = "SELECT {$colsList} FROM `{$source}`{$where} ORDER BY `{$orderBy}` {$orderDir} LIMIT :limit OFFSET :offset";
    $dataStmt = $pdo->prepare($dataSql);
    foreach ($params as $ph => $val) {
        $dataStmt->bindValue($ph, $val);
    }
    $dataStmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll();

    if ($action === 'data') {
        // Nueva respuesta estructurada
        $typeMap = [
            'char' => 'string', 'varchar' => 'string', 'text' => 'string',
            'tinytext' => 'string', 'mediumtext' => 'string', 'longtext' => 'string',
            'int' => 'number', 'bigint' => 'number', 'smallint' => 'number',
            'mediumint' => 'number', 'decimal' => 'number', 'float' => 'number', 'double' => 'number',
            'date' => 'date', 'datetime' => 'datetime', 'timestamp' => 'datetime',
            'time' => 'time', 'year' => 'number'
        ];
        $colsMeta = array_map(function($c) use ($typeMap) {
            $type = strtolower($c['DATA_TYPE']);
            return [
                'key' => $c['COLUMN_NAME'],
                'label' => $c['COLUMN_NAME'],
                'type' => $typeMap[$type] ?? 'string'
            ];
        }, $columnsInfo);

        echo json_encode([
            'ok' => true,
            'source' => $source,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'columns' => $colsMeta,
            'rows' => $rows
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Respuesta legacy
        echo json_encode([
            'columns' => $columnNames,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $pageSize
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

