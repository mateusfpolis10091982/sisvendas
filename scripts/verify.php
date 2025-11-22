<?php
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/core/Database.php';
require_once $root . '/monitor_ssl/Scanner.php';
require_once $root . '/monitor_ssl/SSLUtils.php';

$ok = Database::isConnected();
$total = 0;
if ($ok) {
    $r = Database::query('SELECT COUNT(*) c FROM orgaos');
    if ($r) { $row = $r->fetch_assoc(); $total = (int)($row['c'] ?? 0); }
}
$sample = [];
if ($ok) {
    $r = Database::query("SELECT id, tipo, nome, uf, dominio, site, status, created_at FROM orgaos ORDER BY created_at DESC LIMIT 5");
    if ($r) { while ($x = $r->fetch_assoc()) { $sample[] = $x; } }
}
$ssl = SSLScanner::scanDomain('sp.gov.br');
if (!isset($ssl['days_left']) || $ssl['days_left'] === null) { $ssl['days_left'] = SSLUtils::daysLeft($ssl['valid_to'] ?? null); }

echo json_encode([
    'db_connected' => $ok,
    'orgaos_total' => $total,
    'sample_orgaos' => $sample,
    'ssl_scan_sp_gov_br' => $ssl,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
