<?php
class SSLScanner {
    public static function scanDomain(string $domain): array {
        $timeout = defined('SSL_SCAN_TIMEOUT') ? (int)SSL_SCAN_TIMEOUT : 8;
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'SNI_enabled' => true,
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $client = @stream_socket_client('ssl://' . $domain . ':443', $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        $cert = null;
        if ($client) {
            $params = stream_context_get_params($client);
            if (isset($params['options']['ssl']['peer_certificate'])) $cert = $params['options']['ssl']['peer_certificate'];
        }
        $parsed = $cert ? openssl_x509_parse($cert) : null;
        $issuer = $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null);
        $cn = $parsed['subject']['CN'] ?? null;
        $from = isset($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', (int)$parsed['validFrom_time_t']) : null;
        $to = isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', (int)$parsed['validTo_time_t']) : null;
        $daysLeft = null;
        if ($to) $daysLeft = (int) floor((strtotime($to) - time()) / 86400);
        $san = [];
        if (isset($parsed['extensions']['subjectAltName'])) {
            foreach (explode(', ', $parsed['extensions']['subjectAltName']) as $entry) $san[] = $entry;
        }
        return [
            'domain' => $domain,
            'issuer' => $issuer,
            'cn' => $cn,
            'valid_from' => $from,
            'valid_to' => $to,
            'days_left' => $daysLeft,
            'san' => $san,
            'ok' => (bool)$client,
        ];
    }
}
?>
