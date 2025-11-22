<?php
class HRisco {
    public static function classify(array $ssl): array {
        $dias = $ssl['dias_restantes'] ?? null; $nivel = 0; $desc = 'OK';
        if ($dias === null) { $nivel = 3; $desc = 'Sem certificado'; }
        else if ($dias < 0) { $nivel = 3; $desc = 'SSL vencido'; }
        else if ($dias <= 15) { $nivel = 2; $desc = 'Expira em ≤15 dias'; }
        else if ($dias <= 30) { $nivel = 1; $desc = 'Vencimento em ≤30 dias'; }
        else if ($dias <= 90) { $nivel = 1; $desc = 'Planejar renovação em ≤90 dias'; }
        return ['nivel' => $nivel, 'descricao' => $desc, 'dias'=>$dias];
    }
}
?>
