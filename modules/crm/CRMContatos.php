<?php
require_once __DIR__ . '/../../core/Database.php';
class CRMContatos {
    public static function add(array $d): int { Database::execute('INSERT INTO crm_contatos(orgao_id, municipio_id, nome, cargo, email, telefone, whatsapp, observacoes, origem, created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())', [
        $d['orgao_id']??null,$d['municipio_id']??null,$d['nome'],$d['cargo']??null,$d['email']??null,$d['telefone']??null,$d['whatsapp']??null,$d['observacoes']??null,$d['origem']??'manual'
    ]); $r=Database::query('SELECT LAST_INSERT_ID() id'); $row=$r?$r->fetch_assoc():null; return (int)($row['id']??0); }
    public static function list(int $limit=50): array { $res=Database::query('SELECT * FROM crm_contatos ORDER BY created_at DESC LIMIT ?',[$limit]); $out=[]; if($res) while($r=$res->fetch_assoc()) $out[]=$r; return $out; }
    public static function get(int $id): ?array { $res=Database::query('SELECT * FROM crm_contatos WHERE id=?',[$id]); $row=$res?$res->fetch_assoc():null; return $row?:null; }
    public static function update(int $id, array $d): bool { return Database::execute('UPDATE crm_contatos SET nome=?, cargo=?, email=?, telefone=?, whatsapp=?, observacoes=?, updated_at=NOW() WHERE id=?', [
        $d['nome'],$d['cargo']??null,$d['email']??null,$d['telefone']??null,$d['whatsapp']??null,$d['observacoes']??null,$id
    ]); }
}
?>
