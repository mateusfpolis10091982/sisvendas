<?php
class Utils {
    public static function allowOrigin(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowed = [];
        if (defined('CORS_ORIGINS') && CORS_ORIGINS) foreach (explode(',', CORS_ORIGINS) as $o) $allowed[] = trim($o);
        if (APP_ENV === 'dev' || !$allowed) header('Access-Control-Allow-Origin: *');
        else if (in_array($origin, $allowed, true)) header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');
        header('Access-Control-Max-Age: 86400');
    }
    public static function json($data, int $status = 200): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    public static function jsonError(string $code, int $status = 400, array $extra = []): void { self::json(array_merge(['error'=>$code], $extra), $status); }
    public static function getHeader(string $name): ?string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $val = $_SERVER[$key] ?? null;
        if (!$val && isset($_SERVER['REDIRECT_' . $key])) $val = $_SERVER['REDIRECT_' . $key];
        if (!$val && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $k => $v) { if (strcasecmp($k, $name) === 0) { $val = $v; break; } }
        }
        return $val;
    }
    public static function requireAuth(): void {
        $token = defined('AUTH_TOKEN') ? AUTH_TOKEN : '';
        if (APP_ENV === 'dev' && !$token) return;
        $auth = self::getHeader('Authorization'); $apiKey = self::getHeader('X-Api-Key'); $ok = false;
        if ($auth && stripos($auth, 'Bearer ') === 0) $ok = substr($auth, 7) === $token;
        if (!$ok && $auth && trim($auth) === $token) $ok = true;
        if ($apiKey && $apiKey === $token) $ok = true;
        if (!$ok && isset($_REQUEST['token']) && $_REQUEST['token'] === $token) $ok = true;
        if (!$ok) { self::json(['error' => 'unauthorized'], 401); exit; }
    }
    public static function requireQuery(array $q, array $keys): void {
        foreach ($keys as $k) { if (!isset($q[$k]) || $q[$k] === '') { self::json(['error' => 'missing_param', 'param' => $k], 400); exit; } }
    }
    public static function log(string $name, $data): void {
        if (!defined('LOG_DIR')) return;
        $f = rtrim(LOG_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ($name) . '.log';
        $line = '[' . date('c') . '] ' . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @file_put_contents($f, $line . PHP_EOL, FILE_APPEND);
    }
}
?>
