<?php
class SSLUtils {
    public static function daysLeft(?string $validTo): ?int {
        if (!$validTo) return null;
        $ts = strtotime($validTo);
        if ($ts === false) return null;
        return (int) floor(($ts - time()) / 86400);
    }
}
?>
