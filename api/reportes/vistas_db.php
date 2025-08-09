<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';

$action = $_GET['action'] ?? '';

$allowedTables = [
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
    if ($action === 'list_sources') {
        $views = [];
        $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $views[] = $row['TABLE_NAME'];
            }
            $res->free();
        }
        echo json_encode(['views' => $views, 'tables' => $allowedTables], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'fetch') {
        $source = $_GET['source'] ?? '';
        if ($source === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Falta el parámetro source']);
            exit;
        }

        $views = [];
        $vsql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE()";
        if ($vres = $conn->query($vsql)) {
            while ($row = $vres->fetch_assoc()) {
                $views[] = $row['TABLE_NAME'];
            }
            $vres->free();
        }

        if (in_array($source, $views, true)) {
            $sourceType = 'vista';
        } elseif (in_array($source, $allowedTables, true)) {
            $sourceType = 'tabla';
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Fuente no permitida']);
            exit;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = (int)($_GET['pageSize'] ?? 15);
        $pageSize = in_array($pageSize, [15, 25, 50], true) ? $pageSize : 15;
        $q = trim($_GET['q'] ?? '');
        $sortBy = $_GET['sortBy'] ?? '';
        $sortDir = strtolower($_GET['sortDir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        $stmtCols = $conn->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
        $stmtCols->bind_param('s', $source);
        $stmtCols->execute();
        $colsRes = $stmtCols->get_result();
        $columnsInfo = [];
        while ($row = $colsRes->fetch_assoc()) {
            $columnsInfo[] = $row;
        }
        $stmtCols->close();
        if (!$columnsInfo) {
            http_response_code(400);
            echo json_encode(['error' => 'Fuente no encontrada']);
            exit;
        }

        $columns = array_column($columnsInfo, 'COLUMN_NAME');
        if (!in_array($sortBy, $columns, true)) {
            $sortBy = '';
        }

        $where = '';
        $params = [];
        $types = '';
        if ($q !== '') {
            $parts = [];
            foreach ($columnsInfo as $col) {
                $parts[] = "CAST(`{$col['COLUMN_NAME']}` AS CHAR) LIKE ?";
                $params[] = "%{$q}%";
                $types .= 's';
            }
            if ($parts) {
                $where = ' WHERE (' . implode(' OR ', $parts) . ')';
            }
        }

        $countSql = "SELECT COUNT(*) FROM `{$source}`{$where}";
        $stmtCount = $conn->prepare($countSql);
        if ($params) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $stmtCount->bind_result($total);
        $stmtCount->fetch();
        $stmtCount->close();

        $offset = ($page - 1) * $pageSize;
        $selectCols = '`' . implode('`,`', $columns) . '`';
        $dataSql = "SELECT {$selectCols} FROM `{$source}`{$where}";
        if ($sortBy) {
            $dataSql .= " ORDER BY `{$sortBy}` {$sortDir}";
        } else {
            $dataSql .= " ORDER BY 1";
        }
        $dataSql .= " LIMIT ? OFFSET ?";

        $stmtData = $conn->prepare($dataSql);
        $bindTypes = $types . 'ii';
        $bindValues = $params;
        $bindValues[] = $pageSize;
        $bindValues[] = $offset;
        $stmtData->bind_param($bindTypes, ...$bindValues);
        $stmtData->execute();
        $resData = $stmtData->get_result();
        $rows = [];
        while ($row = $resData->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmtData->close();

        echo json_encode([
            'source' => $source,
            'sourceType' => $sourceType,
            'columns' => $columns,
            'rows' => $rows,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => (int)$total
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Acción inválida']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

