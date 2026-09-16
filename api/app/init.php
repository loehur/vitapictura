<?php
ob_start();

$envFile = __DIR__ . '/Config/Env.php';
if (!file_exists($envFile)) {
    $envFile = __DIR__ . '/Config/Env.example.php';
}
require_once $envFile;

function resolveAllowedOrigin(): ?string
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!$origin) {
        return null;
    }

    $allowedOrigins = defined('Env::ALLOWED_ORIGINS') ? \Env::ALLOWED_ORIGINS : [];
    $parsedOrigin = parse_url($origin);
    $originHost = strtolower($parsedOrigin['host'] ?? '');
    $originPort = isset($parsedOrigin['port']) ? ':' . $parsedOrigin['port'] : '';
    $originBase = ($parsedOrigin['scheme'] ?? 'http') . '://' . $originHost . $originPort;

    foreach ($allowedOrigins as $allowed) {
        $parsedAllowed = parse_url($allowed);
        $allowedHost = strtolower($parsedAllowed['host'] ?? '');
        $allowedPort = isset($parsedAllowed['port']) ? ':' . $parsedAllowed['port'] : '';
        $allowedBase = ($parsedAllowed['scheme'] ?? 'http') . '://' . $allowedHost . $allowedPort;

        if ($originBase === $allowedBase) {
            return $origin;
        }
    }

    if (\Env::isDev() && in_array($originHost, ['localhost', '127.0.0.1'], true)) {
        return $origin;
    }

    return null;
}

function setCorsHeadersForError(): void
{
    $allowedOrigin = resolveAllowedOrigin();

    if ($allowedOrigin) {
        header("Access-Control-Allow-Origin: {$allowedOrigin}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, User-Agent');
    }
}

$allowedOrigin = resolveAllowedOrigin();
if ($allowedOrigin) {
    header("Access-Control-Allow-Origin: {$allowedOrigin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, User-Agent');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(200);
    exit;
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (ob_get_level()) {
        ob_clean();
    }

    setCorsHeadersForError();

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => false,
        'message' => 'PHP Error',
        'error' => $errstr,
        'file' => basename($errfile),
        'line' => $errline,
    ]);
    exit;
});

set_exception_handler(function ($e) {
    if (ob_get_level()) {
        ob_clean();
    }

    setCorsHeadersForError();

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => false,
        'message' => 'Exception',
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ]);
    exit;
});

date_default_timezone_set('Asia/Jakarta');
$GLOBALS['now'] = date('Y-m-d H:i:s');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '604800');

    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    // SameSite=None + Secure needed when admin frontend origin != API host (credentials fetch).
    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
        'httponly' => true,
        'secure' => $https,
        'samesite' => $https ? 'None' : 'Lax',
    ]);
    session_start();
}

if (\Env::isDev()) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        if (file_exists(__DIR__ . '/Core/' . $class . '.php')) {
            require_once __DIR__ . '/Core/' . $class . '.php';
            return;
        }
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
