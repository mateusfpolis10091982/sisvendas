<?php
require_once __DIR__ . '/../../core/Database.php';
class CRMAuto {
    public static function createAuto(array $ctx): array {
        $dom = $ctx['dominio']??null; $risco=$ctx['risco']??['nivel'=>0,'descricao'=>'OK']; $titulo = 'SSL: ' . ($risco['descricao']??'');
        $exists = Database::query('SELECT id FROM crm_oportunidades WHERE dominio=? AND status=?', [$dom, 'novo']);
        $row = $exists?$exists->fetch_assoc():null; if($row) return ['id'=>(int)$row['id'],'duplicated'=>true];
        Database::execute('INSERT INTO crm_oportunidades(orgao_id, titulo, descricao, status, prioridade, origem, dominio, risco_nivel, risco_desc, follow_up_ia, created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())',[
            $ctx['orgao_id']??null,$titulo,$ctx['insights']??null,'novo','alta','funcao_h',$dom,$risco['nivel']??0,$risco['descricao']??null,$ctx['insights']??null
        ]);
        $r=Database::query('SELECT LAST_INSERT_ID() id'); $row=$r?$r->fetch_assoc():null; return ['id'=>(int)($row['id']??0)];
    }
}
?>
