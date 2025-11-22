<?php
class HInsights {
    public static function generate(array $ssl, array $risco): string {
        $d = $ssl['dominio'] ?? ''; $nl = $risco['nivel'] ?? 0; $desc = $risco['descricao'] ?? '';
        return 'Domínio ' . $d . ' risco ' . $nl . ' — ' . $desc;
    }
}
?>
