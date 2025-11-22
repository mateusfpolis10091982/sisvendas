<?php
require_once __DIR__ . '/../../core/Database.php';
class CRMAgenda {
    public static function createAuto(array $ctx): array {
        $dias = is_int($ctx['dias']??null) ? (int)$ctx['dias'] : null;
        $titulo = 'Follow-up SSL';
        $desc = 'Verificar renovação SSL';
        $delta = 86400;
        if ($dias === null || $dias < 0) { $titulo = 'SSL ausente/vencido'; $desc = 'Atuar imediatamente para renovar'; $delta = 3600; }
        else if ($dias <= 15) { $titulo = 'Renovar SSL (≤15 dias)'; $desc = 'Contato com TI/fornecedor e preparar emissão'; $delta = 86400; }
        else if ($dias <= 30) { $titulo = 'Preparar renovação (≤30 dias)'; $desc = 'Planejar renovação e validar CSR'; $delta = 7*86400; }
        else if ($dias <= 90) { $titulo = 'Planejar renovação (≤90 dias)'; $desc = 'Programar ação e confirmar cadeia de certificados'; $delta = 30*86400; }
        $data = date('Y-m-d H:00:00', time()+$delta);
        Database::execute('INSERT INTO crm_agenda(orgao_id, municipio_id, contato_id, oportunidade_id, titulo, descricao, tipo, status, data_agendada, responsavel, origem, dominio, created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [
            $ctx['orgao_id']??null,$ctx['municipio_id']??null,null,$ctx['oportunidade_id']??null,$titulo,$desc,'automatico','pendente',$data,null,'funcao_h',$ctx['dominio']??null
        ]);
        $r=Database::query('SELECT LAST_INSERT_ID() id'); $row=$r?$r->fetch_assoc():null; return ['id'=>(int)($row['id']??0)];
    }
}
?>
