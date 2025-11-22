<?php
$envFile = __DIR__ . '/env.local.php';
if (file_exists($envFile)) { require_once $envFile; }
if (!defined('APP_NAME')) define('APP_NAME', 'SisVendas');
if (!defined('APP_VERSION')) define('APP_VERSION', '0.1.0');
if (!defined('APP_ENV')) define('APP_ENV', 'dev');
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'sisvendas');
if (!defined('AUTH_TOKEN')) define('AUTH_TOKEN', '');
if (!defined('SSL_SCAN_TIMEOUT')) define('SSL_SCAN_TIMEOUT', 8);
if (!defined('LOG_DIR')) define('LOG_DIR', __DIR__);
?>
