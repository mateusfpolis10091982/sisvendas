<?php
class SSLParser {
    public static function parse($cert): array {
        $parsed = $cert ? openssl_x509_parse($cert) : null;
        $issuer = $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null);
        $from = isset($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', (int)$parsed['validFrom_time_t']) : null;
        $to = isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', (int)$parsed['validTo_time_t']) : null;
        return ['issuer' => $issuer, 'valid_from' => $from, 'valid_to' => $to];
    }
}
?>
