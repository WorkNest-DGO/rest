<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

function json_out(bool $ok, $payload, int $code = 200): void {
    http_response_code($code);
    if ($ok) {
        echo json_encode(['success' => true, 'resultado' => $payload], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'mensaje' => (string)$payload], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function table_exists(mysqli $db, string $t): bool {
    $q = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $q->bind_param('s',$t);
    $q->execute();
    $q->store_result();
    $ok = $q->num_rows>0;
    $q->close();
    return $ok;
}

function column_exists(mysqli $db, string $t, string $c): bool {
    $q = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $q->bind_param('ss',$t,$c);
    $q->execute();
    $q->store_result();
    $ok = $q->num_rows>0;
    $q->close();
    return $ok;
}

/**
 * Enviar correo usando SMTP de Gmail sin librerías externas
 */
function send_via_gmail_smtp(string $to, string $subject, string $body, string $headers): bool {
    $smtpServer = 'smtp.gmail.com';
    $port       = 465; // SSL directo
    $username   = 'tokyosushiprime@gmail.com';
    $password   = 'chffrfdlabfuzzbl'; 

    $errno  = 0;
    $errstr = '';
    $fp = fsockopen("ssl://{$smtpServer}", $port, $errno, $errstr, 30);
    if (!$fp) {
        error_log("SMTP connect error: $errstr ($errno)");
        return false;
    }

    $newline = "\r\n";

    $read = function() use ($fp) {
        $data = '';
        while ($str = fgets($fp, 515)) {
            $data .= $str;
            if (strlen($str) < 4) {
                break;
            }
            if (substr($str, 3, 1) === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = function(string $cmd) use ($fp, $newline) {
        fputs($fp, $cmd . $newline);
    };

    // Saludo inicial
    $greet = $read();
    if (strpos($greet, '220') !== 0) {
        fclose($fp);
        return false;
    }

    $write('EHLO localhost');
    $ehlo = $read();
    if (strpos($ehlo, '250') !== 0) {
        fclose($fp);
        return false;
    }

    // Autenticación LOGIN
    $write('AUTH LOGIN');
    $resp = $read();
    if (strpos($resp, '334') !== 0) {
        fclose($fp);
        return false;
    }

    $write(base64_encode($username));
    $resp = $read();
    if (strpos($resp, '334') !== 0) {
        fclose($fp);
        return false;
    }

    $write(base64_encode($password));
    $resp = $read();
    if (strpos($resp, '235') !== 0) {
        fclose($fp);
        return false;
    }

    // Envelope
    $fromEnvelope = $username;

    $write("MAIL FROM:<{$fromEnvelope}>");
    $resp = $read();
    if (strpos($resp, '250') !== 0) {
        fclose($fp);
        return false;
    }

    $write("RCPT TO:<{$to}>");
    $resp = $read();
    if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) {
        fclose($fp);
        return false;
    }

    $write('DATA');
    $resp = $read();
    if (strpos($resp, '354') !== 0) {
        fclose($fp);
        return false;
    }

    // Construir mensaje completo: Subject + headers + body
    $message  = 'Subject: ' . $subject . $newline;
    $message .= rtrim($headers, "\r\n") . $newline . $newline;
    $message .= $body . $newline . '.';

    $write($message);
    $resp = $read();
    if (strpos($resp, '250') !== 0) {
        fclose($fp);
        return false;
    }

    $write('QUIT');
    fclose($fp);

    return true;
}

try {
    $db = get_db();
    $db->set_charset('utf8mb4');

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;

    $facturaId    = isset($data['factura_id']) ? (int)$data['factura_id'] : 0;
    $correoManual = isset($data['correo']) ? trim((string)$data['correo']) : '';
    if ($facturaId <= 0) {
        json_out(false, 'factura_id requerido', 422);
    }

    $hasClienteId = column_exists($db, 'facturas', 'cliente_id');
    $hasCorreoCF  = table_exists($db, 'clientes_facturacion') && column_exists($db, 'clientes_facturacion', 'correo');
    $hasRazonCF   = table_exists($db, 'clientes_facturacion') && column_exists($db, 'clientes_facturacion', 'razon_social');
    $hasUUID      = column_exists($db, 'facturas', 'uuid');
    $hasPdfPath   = column_exists($db, 'facturas', 'pdf_path');
    $hasXmlPath   = column_exists($db, 'facturas', 'xml_path');

    $sel = "SELECT f.id";
    if ($hasClienteId) $sel .= ", f.cliente_id";
    if ($hasUUID)      $sel .= ", f.uuid";
    if (column_exists($db, 'facturas', 'total'))          $sel .= ", f.total";
    if (column_exists($db, 'facturas', 'fecha_emision'))  $sel .= ", f.fecha_emision";
    if ($hasPdfPath)   $sel .= ", f.pdf_path";
    if ($hasXmlPath)   $sel .= ", f.xml_path";

    if ($hasClienteId && $hasCorreoCF) {
        $sel .= ", cf.correo";
        if ($hasRazonCF) $sel .= ", cf.razon_social";
        $sel .= " FROM facturas f LEFT JOIN clientes_facturacion cf ON cf.id = f.cliente_id WHERE f.id = ?";
    } else {
        if (column_exists($db, 'facturas', 'correo'))      $sel .= ", f.correo";
        if (column_exists($db, 'facturas', 'razon_social'))$sel .= ", f.razon_social";
        $sel .= " FROM facturas f WHERE f.id = ?";
    }

    $st = $db->prepare($sel);
    if (!$st) {
        json_out(false, 'Error preparando consulta: '.$db->error, 500);
    }
    $st->bind_param('i',$facturaId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $st->close();

    if (!$row) {
        json_out(false, 'Factura no encontrada', 404);
    }

    $destino = $correoManual !== '' ? $correoManual : trim((string)($row['correo'] ?? ''));
    if ($destino === '') {
        json_out(false, 'El cliente no tiene correo registrado', 422);
    }

    $uuid  = $hasUUID ? ($row['uuid'] ?? '') : '';
    $razon = $row['razon_social'] ?? 'Cliente';
    $total = (float)($row['total'] ?? 0);
    $fecha = $row['fecha_emision'] ?? date('Y-m-d H:i');

    $baseUrl = (isset($_SERVER['HTTP_HOST'])
        ? ((
                (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1))
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            ) ? 'https://' : 'http://'
          ) . $_SERVER['HTTP_HOST']
        : ''
    );

    $linkPdf = $baseUrl . '/rest/api/facturas/descargar.php?factura_id=' . urlencode((string)$facturaId) . '&tipo=pdf';
    $linkXml = $baseUrl . '/rest/api/facturas/descargar.php?factura_id=' . urlencode((string)$facturaId) . '&tipo=xml';

    $attachments = [];
    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) $baseDir = __DIR__ . '/../../';

    if ($hasPdfPath && !empty($row['pdf_path'])) {
        $pdfAbs = rtrim($baseDir, '/\\') . '/' . ltrim((string)$row['pdf_path'], '/\\');
        if (is_file($pdfAbs)) {
            $attachments[] = [
                'path' => $pdfAbs,
                'name' => basename($pdfAbs),
                'type' => 'application/pdf'
            ];
        }
    }

    if ($hasXmlPath && !empty($row['xml_path'])) {
        $xmlAbs = rtrim($baseDir, '/\\') . '/' . ltrim((string)$row['xml_path'], '/\\');
        if (is_file($xmlAbs)) {
            $attachments[] = [
                'path' => $xmlAbs,
                'name' => basename($xmlAbs),
                'type' => 'application/xml'
            ];
        }
    }

    // Construir correo con enlaces + adjuntos si existen
    $subject  = "Factura #" . $facturaId . ($uuid ? " - $uuid" : '');
    $boundary = '==MIME_BOUND_' . md5(uniqid((string)$facturaId, true));
    $headers  = "From: Tokyo Sushi Prime <tokyosushiprime@gmail.com>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    $hasAttach = !empty($attachments);
    if ($hasAttach) {
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"";

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=\"utf-8\"\r\n\r\n";
        $body .= "Hola $razon,\r\n";
        $body .= "Adjuntamos tu factura generada el $fecha por un monto de $total.\r\n\r\n";
        $body .= "Si no ves los adjuntos, también puedes descargar los archivos con estos enlaces:\r\n";
       $body .= "PDF: $linkPdf\r\nXML: $linkXml\r\n\r\n";

        foreach ($attachments as $att) {
            $bin = @file_get_contents($att['path']);
            if ($bin === false) continue;

            $body .= "--$boundary\r\n";
            $body .= "Content-Type: {$att['type']}; name=\"{$att['name']}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$att['name']}\"\r\n\r\n";
            $body .= chunk_split(base64_encode($bin)) . "\r\n";
        }

        $body .= "--$boundary--";
    } else {
        $headers .= "Content-Type: text/plain; charset=\"utf-8\"";
        $body  = "Hola $razon,\r\n";
        $body .= "Tu factura está disponible:\r\n";
        $body .= "PDF: $linkPdf\r\nXML: $linkXml\r\n";
    }

    // Enviar por SMTP de Gmail
    $sent = send_via_gmail_smtp($destino, $subject, $body, $headers);
    if (!$sent) {
        json_out(false, 'No se pudo enviar el correo (error SMTP, revisa usuario/contraseña o permisos de Gmail)', 500);
    }

    json_out(true, ['enviado_a' => $destino]);

} catch (Throwable $e) {
    json_out(false, 'Error: '.$e->getMessage(), 500);
}
