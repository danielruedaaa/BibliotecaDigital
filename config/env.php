<?php
// Cargador mínimo de .env (sin dependencias).
// Busca .env en la raíz del proyecto y lo carga en getenv/$_ENV una sola vez.
function loadEnv($dir) {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($file)) return;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        // Quitar comillas simples/dobles
        if (strlen($val) >= 2 && (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'"))) {
            $val = substr($val, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

function env($key, $default = null) {
    $v = getenv($key);
    if ($v === false && isset($_ENV[$key])) $v = $_ENV[$key];
    if ($v === false || $v === '') return $default;
    return $v;
}

// Cargar .env de la raíz (config/.. = raíz)
loadEnv(__DIR__ . '/..');
