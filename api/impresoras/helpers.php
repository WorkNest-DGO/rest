<?php
// Helper para obtener IP de impresora desde base de datos

function obtener_impresora_ip(mysqli $db, ?int $printId = null): string {
    $ip = null;

    if ($printId !== null) {
        if ($st = $db->prepare('SELECT ip FROM impresoras WHERE print_id = ? LIMIT 1')) {
            $st->bind_param('i', $printId);
            if ($st->execute()) {
                $res = $st->get_result()->fetch_assoc();
                $ip = $res['ip'] ?? null;
            }
            $st->close();
        }
    }

    if (!$ip) {
        $res = $db->query('SELECT ip FROM impresoras ORDER BY print_id ASC LIMIT 1');
        if ($res && ($row = $res->fetch_assoc())) {
            $ip = $row['ip'] ?? null;
        }
    }

    // Fallback por compatibilidad si no hay registro en BD
    if (!$ip) {
        $ip = 'smb://FUED/pos58';
    }

    return $ip;
}
