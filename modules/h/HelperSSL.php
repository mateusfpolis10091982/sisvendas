<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../monitor_ssl/Scanner.php';
require_once __DIR__ . '/../../monitor_ssl/SSLUtils.php';
class HelperSSL {
    public static function scanAndPersist(string $dominio): array {
        $res = SSLScanner::scanDomain($dominio);
        $dias = SSLUtils::daysLeft($res['valid_to'] ?? null);
        $status = ($dias === null) ? 'unknown' : (($dias < 0) ? 'expired' : 'valid');
        $prefId = null;
        $chk = Database::query("SHOW TABLES LIKE 'prefeituras_new'");
        if ($chk && $chk->num_rows > 0) {
            $rp = Database::query('SELECT id FROM prefeituras_new WHERE dominio=? LIMIT 1', [$dominio]);
            if ($rp && ($row = $rp->fetch_assoc())) $prefId = (int)$row['id'];
        } else {
            $rp = Database::query('SELECT id FROM prefeituras WHERE dominio=? LIMIT 1', [$dominio]);
            if ($rp && ($row = $rp->fetch_assoc())) $prefId = (int)$row['id'];
        }
        $hasEmissor = Database::query("SHOW COLUMNS FROM ssl_results LIKE 'emissor'");
        if ($hasEmissor && $hasEmissor->num_rows > 0) {
            $sql = 'INSERT INTO ssl_results(dominio, emissor, valido_ate, dias_restantes, status, capturado_em' . ($prefId ? ', prefeitura_id' : '') . ') VALUES(?, ?, ?, ?, ?, NOW()' . ($prefId ? ', ?' : '') . ')';
            $params = [$dominio, $res['issuer'] ?? null, $res['valid_to'] ?? null, $dias, strtoupper($status) === 'VALID' ? 'OK' : (strtoupper($status) === 'EXPIRED' ? 'ERRO' : strtoupper($status))];
            if ($prefId) $params[] = $prefId;
            Database::execute($sql, $params);
            return ['dominio'=>$dominio,'emissor'=>$res['issuer'] ?? null,'valido_ate'=>$res['valid_to'] ?? null,'dias_restantes'=>$dias,'status'=>$params[4],'prefeitura_id'=>$prefId,'ok'=>($res['ok']??false)];
        } else {
            Database::execute('INSERT INTO ssl_results(dominio, issuer, cn, valid_from, valid_to, dias_restantes, status, last_scan_at' . ($prefId ? ', prefeitura_id' : '') . ') VALUES(?, ?, ?, ?, ?, ?, ?, NOW()' . ($prefId ? ', ?' : '') . ')', [
                $dominio, $res['issuer'] ?? null, $res['cn'] ?? null, $res['valid_from'] ?? null, $res['valid_to'] ?? null, $dias, $status, ...($prefId ? [$prefId] : [])
            ]);
            return ['dominio' => $dominio, 'issuer' => $res['issuer'] ?? null, 'cn' => $res['cn'] ?? null, 'valid_from' => $res['valid_from'] ?? null, 'valid_to' => $res['valid_to'] ?? null, 'dias_restantes' => $dias, 'status' => $status, 'prefeitura_id'=>$prefId,'ok'=>($res['ok']??false)];
        }
    }
}
?>
