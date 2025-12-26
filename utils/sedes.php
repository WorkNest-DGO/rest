<?php

/**
 * Utilidades para resolver la sede de un usuario y columnas dinámicas de sede.
 */

if (!function_exists('sede_column_exists')) {
    function sede_column_exists(mysqli $conn, string $table, string $column): bool {
        $tableSafe = $conn->real_escape_string($table);
        $colSafe   = $conn->real_escape_string($column);
        $sql = "SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$colSafe}'";
        $res = $conn->query($sql);
        if (!$res) {
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->close();
        return $exists;
    }
}

if (!function_exists('sede_column_name')) {
    function sede_column_name(mysqli $conn, string $table): ?string {
        if (sede_column_exists($conn, $table, 'sede_id')) {
            return 'sede_id';
        }
        if (sede_column_exists($conn, $table, 'sede')) {
            return 'sede';
        }
        return null;
    }
}

if (!function_exists('sede_resolver_usuario')) {
    /**
     * Determina la sede del usuario (id o null) usando la columna disponible en la tabla usuarios o la sesión.
     */
    function sede_resolver_usuario(mysqli $conn, ?int $usuarioId = null): ?int {
        $uid = $usuarioId ?: (isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null);
        $sede = null;
        $colUsuarioSede = sede_column_name($conn, 'usuarios');
        if ($uid && $colUsuarioSede) {
            $stmtSede = $conn->prepare("SELECT {$colUsuarioSede} AS sede_val FROM usuarios WHERE id = ? LIMIT 1");
            if ($stmtSede) {
                $stmtSede->bind_param('i', $uid);
                if ($stmtSede->execute()) {
                    $rs = $stmtSede->get_result();
                    if ($row = $rs->fetch_assoc()) {
                        if (isset($row['sede_val'])) {
                            $sede = (int)$row['sede_val'];
                        }
                    }
                }
                $stmtSede->close();
            }
        }
        if ($sede === null) {
            if (isset($_SESSION['sede_id'])) {
                $sede = (int)$_SESSION['sede_id'];
            } elseif (isset($_SESSION['sede'])) {
                $sede = (int)$_SESSION['sede'];
            }
        }
        return $sede;
    }
}

