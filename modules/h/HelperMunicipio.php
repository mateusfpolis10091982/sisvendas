<?php
require_once __DIR__ . '/../../core/Database.php';
class HelperMunicipio {
    public static function getDominio(int $municipioId): ?string {
        $r = Database::query('SELECT dominio FROM prefeituras WHERE id=?', [$municipioId]);
        $row = $r ? $r->fetch_assoc() : null; return $row['dominio'] ?? null;
    }
}
?>
