<?php
class Auth {
    public static function check(): bool {
        $token = defined('AUTH_TOKEN') ? AUTH_TOKEN : '';
        if (APP_ENV === 'dev' && !$token) return true;
        $auth = Utils::getHeader('Authorization'); $apiKey = Utils::getHeader('X-Api-Key');
        if ($auth && stripos($auth, 'Bearer ') === 0) return substr($auth, 7) === $token;
        if ($apiKey && $apiKey === $token) return true; return false;
    }
}
?>
