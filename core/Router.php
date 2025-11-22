<?php
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?? '/';
$full = __DIR__ . '/../' . ltrim($path, '/');
if ($path !== '/' && file_exists($full) && is_file($full)) { return false; }
require __DIR__ . '/../index.php';
?>
