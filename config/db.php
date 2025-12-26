<?php
// Unified DB bootstrap: provides mysqli get_db() and PDOs ($pdoOp, $pdoEsp, $pdoCdi)

if (function_exists('env') === false) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        return ($val !== false && $val !== null) ? $val : $default;
    }
}

// ---- mysqli connection (legacy) ----
if (!function_exists('get_db')) {
    function get_db(): mysqli {
        static $conn = null;
        if ($conn instanceof mysqli) return $conn;
        $host = env('DB_HOST', 'localhost');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');
        $db   = env('DB_NAME', 'restaurante');
        $conn = @new mysqli($host, $user, $pass, $db);
        if ($conn->connect_errno) {
            http_response_code(500);
            die('Error de conexión: ' . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
        return $conn;
    }
}

// Expose $conn for compatibility with legacy views
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = get_db();
}

// ---- PDO connections (operativa, espejo, cdi) ----
if (!class_exists('PDO')) {
    // PDO unavailable; skip PDO setup
} else {
    if (!function_exists('pdo_connect')) {
        function pdo_connect(string $dsn, string $user, string $pass): PDO {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
            return $pdo;
        }
    }
     //esta es la BD origen de aqui tomamos los cobros
    $db1_dsn  = env('DB1_DSN', 'mysql:host=localhost;dbname=restaurante;charset=utf8mb4');
    $db1_user = env('DB1_USER', env('DB_USER', 'root'));
    $db1_pass = env('DB1_PASS', env('DB_PASS', ''));
    //esta BD es la q mueve los cobros
    $db2_dsn  = env('DB2_DSN', 'mysql:host=localhost;dbname=restaurante_espejo;charset=utf8mb4');
    $db2_user = env('DB2_USER', env('DB_USER', 'root'));
    $db2_pass = env('DB2_PASS', env('DB_PASS', ''));
    //esta BD es CDI para tomar las reques 
    $db3_dsn  = env('DB3_DSN', 'mysql:host=localhost;dbname=restaurante_cdi;charset=utf8mb4');
    $db3_user = env('DB3_USER', env('DB_USER', 'root'));
    $db3_pass = env('DB3_PASS', env('DB_PASS', ''));

    try {
        $pdoOp  = pdo_connect($db1_dsn, $db1_user, $db1_pass);
        $pdoEsp = pdo_connect($db2_dsn, $db2_user, $db2_pass);
        $pdoCdi = pdo_connect($db3_dsn, $db3_user, $db3_pass);
    } catch (Throwable $e) {
        // Skip PDO fatal to allow mysqli-only pages
    }
}
