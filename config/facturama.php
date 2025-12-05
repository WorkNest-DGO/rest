<?php
// Facturama helper local para REST: credenciales, wrappers HTTP y utilidades

class FacturamaCfg {
    public static function baseUrl(): string {
        $base = getenv('FACTURAMA_BASE') ?: getenv('FACTURAMA_BASE_URL');
        if ($base && trim($base) !== '') return rtrim($base, '/');
       //return 'https://api.facturama.mx';
        return 'https://apisandbox.facturama.mx';

    }

    public static function user(): string {
        $u = getenv('FACTURAMA_USER');
        if ($u && trim($u) !== '') return $u;
        return 'tokyosushi';
    }

    public static function pass(): string {
        $p = getenv('FACTURAMA_PASS');
        if ($p && trim($p) !== '') return $p;
    //return 'Marf9401109i5'; 
     return 'k"2ix=+g&QqyZ9y';
    }

    public static function expeditionPlace(): string {
        $cp = getenv('FACTURAMA_EXPEDITION_PLACE');
        if ($cp && preg_match('/^\\d{5}$/', $cp)) return $cp;
        return '34217';
    }
}

// Helpers de decimales usados en CFDI
if (!function_exists('dec6')) { function dec6($n) { return round((float)$n, 6); } }
if (!function_exists('dec2')) { function dec2($n) { return round((float)$n, 2); } }

// Logger sencillo para depurar facturacion (usa rest/logs)
if (!function_exists('facturas_log')) {
    function facturas_log(string $label, $data = null): void {
        try {
            $logDir = realpath(__DIR__ . '/../logs');
            if ($logDir === false) { $logDir = __DIR__ . '/../logs'; }
            if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
            $file = rtrim($logDir, '/\\') . '/facturama-' . date('Y-m') . '.log';
            $entry = ['ts'=>date('Y-m-d H:i:s'),'label'=>$label];
            if (!empty($_SERVER['REMOTE_ADDR'])) { $entry['ip'] = (string)$_SERVER['REMOTE_ADDR']; }
            if ($data instanceof Throwable) {
                $entry['error'] = ['type'=>get_class($data), 'code'=>$data->getCode(), 'message'=>$data->getMessage()];
            } elseif ($data !== null) {
                $entry['data'] = $data;
            }
            $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($json === false) {
                $json = $entry['ts'] . ' ' . $label . ' :: ' . (is_scalar($data) ? (string)$data : '[unserializable]');
            }
            @file_put_contents($file, $json . PHP_EOL, FILE_APPEND);
        } catch (Throwable $ignored) {}
    }
}

// Precio neto desde monto con IVA incluido
if (!function_exists('netearIvaItem')) {
    function netearIvaItem(float $precioConIva, float $qty = 1.0, float $tasaIva = 0.16): array {
        $unitPriceNeto = dec6($precioConIva / (1 + $tasaIva));
        $subtotal = dec6($unitPriceNeto * $qty);
        $iva = dec6($subtotal * $tasaIva);
        $total = dec2($subtotal + $iva);
        return [
            'unitPriceNeto' => $unitPriceNeto,
            'subtotal'      => $subtotal,
            'iva'           => $iva,
            'total'         => $total,
        ];
    }
}

/**
 * Helper de bajo nivel para llamar Facturama.
 * Si $jsonBody es distinto de null se envia como JSON, de lo contrario se envia como form-data.
 */
function facturama_request(string $method, string $path, array $fields = [], ?array $jsonBody = null): array {
    $baseUrl = FacturamaCfg::baseUrl();
    $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    $skipSsl = false;
    $skipEnv = getenv('FACTURAMA_SKIP_SSL_VERIFY');
    if ($skipEnv !== false && trim((string)$skipEnv) !== '' && trim((string)$skipEnv) !== '0') {
        $skipSsl = true;
    } elseif (stripos($baseUrl, 'apisandbox.facturama.mx') !== false) {
        // Sandbox suele presentar problemas de certificado en Windows
        $skipSsl = true;
    }
    $ch = curl_init();
    $headers = ['Accept: application/json'];
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => FacturamaCfg::user() . ':' . FacturamaCfg::pass(),
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($skipSsl) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = false;
    }
    $m = strtoupper($method);
    if ($m === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
        } else {
            $opts[CURLOPT_POSTFIELDS] = $fields;
        }
    } elseif ($m !== 'GET') {
        $opts[CURLOPT_CUSTOMREQUEST] = $m;
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
        } elseif (!empty($fields)) {
            $opts[CURLOPT_POSTFIELDS] = $fields;
        }
    }

    $rid = substr(uniqid('', true), -8);
    if (function_exists('facturas_log')) {
        facturas_log('FACTURAMA_REQUEST', [
            'rid' => $rid,
            'method' => $m,
            'path' => $path,
            'url' => $url,
            'payload_type' => $jsonBody !== null ? 'json' : (!empty($fields) ? 'form' : 'none'),
            'payload' => $jsonBody !== null ? $jsonBody : $fields,
        ]);
    }

    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);
        if (function_exists('facturas_log')) {
            facturas_log('FACTURAMA_CURL_ERROR', ['rid'=>$rid, 'error'=>$err, 'code'=>$code]);
        }
        throw new RuntimeException('Facturama cURL error: ' . $err, $code ?: 500);
    }
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($raw, true);
    if ($http < 200 || $http >= 300) {
        $msg = is_array($json) ? (json_encode($json, JSON_UNESCAPED_UNICODE) ?: $raw) : $raw;
        if (function_exists('facturas_log')) {
            facturas_log('FACTURAMA_HTTP_ERROR', ['rid'=>$rid, 'http'=>$http, 'response'=>$msg]);
        }
        throw new RuntimeException('Facturama HTTP ' . $http . ': ' . $msg, $http);
    }
    if (!is_array($json)) {
        if (function_exists('facturas_log')) {
            facturas_log('FACTURAMA_JSON_DECODE_ERROR', ['rid'=>$rid, 'raw'=>$raw]);
        }
        throw new RuntimeException('Facturama invalid JSON response');
    }
    return $json;
}

function facturama_create_cfdi(array $fields): array {
    return facturama_request('POST', '/3/cfdis', $fields, null);
}

function facturama_create_cfdi_json(array $cfdi): array {
    return facturama_request('POST', '/api/3/cfdis', [], $cfdi);
}

/**
 * Descarga contenido (XML o PDF) para CFDI emitido por su Facturama Id.
 * Devuelve [contenido, content-type].
 */
function facturama_download_issued(string $type, string $facturamaId): array {
    $type = strtolower($type) === 'pdf' ? 'pdf' : 'xml';
    $baseUrl = FacturamaCfg::baseUrl();
    $skipSsl = false;
    $skipEnv = getenv('FACTURAMA_SKIP_SSL_VERIFY');
    if ($skipEnv !== false && trim((string)$skipEnv) !== '' && trim((string)$skipEnv) !== '0') {
        $skipSsl = true;
    } elseif (stripos($baseUrl, 'apisandbox.facturama.mx') !== false) {
        $skipSsl = true;
    }
    $path = "/api/Cfdi/{$type}/issued/" . rawurlencode($facturamaId);
    $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => FacturamaCfg::user() . ':' . FacturamaCfg::pass(),
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HEADER => true,
    ];
    if ($skipSsl) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = false;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);
        throw new RuntimeException('Facturama download error: ' . $err, $code ?: 500);
    }
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersRaw = substr($resp, 0, $hdrSize);
    $body = substr($resp, $hdrSize);
    curl_close($ch);
    if ($http < 200 || $http >= 300) {
        throw new RuntimeException('Facturama download HTTP ' . $http);
    }
    $contentType = 'application/octet-stream';
    foreach (explode("\r\n", $headersRaw) as $h) {
        if (stripos($h, 'Content-Type:') === 0) {
            $contentType = trim(substr($h, strlen('Content-Type:')));
            break;
        }
    }
    return [$body, $contentType];
}

// Sucursales (lugares de expedicion)
if (!function_exists('facturama_list_branch_offices')) {
    function facturama_list_branch_offices(): array {
        try {
            $resp = facturama_request('GET', '/api/BranchOffice');
        } catch (Throwable $e) {
            $resp = facturama_request('GET', '/BranchOffice');
        }
        if (isset($resp['Data']) && is_array($resp['Data'])) return $resp['Data'];
        if (is_array($resp)) return $resp;
        return [];
    }
}

if (!function_exists('facturama_get_branch_by_zip')) {
    function facturama_get_branch_by_zip(string $zip): ?array {
        $zip = trim($zip);
        foreach (facturama_list_branch_offices() as $b) {
            $bz = $b['Address']['ZipCode'] ?? $b['ZipCode'] ?? null;
            if ($bz === $zip) return $b;
        }
        return null;
    }
}

if (!function_exists('facturama_get_default_branch')) {
    function facturama_get_default_branch(): ?array {
        $all = facturama_list_branch_offices();
        foreach ($all as $b) if (!empty($b['IsDefault'])) return $b;
        return $all[0] ?? null;
    }
}
