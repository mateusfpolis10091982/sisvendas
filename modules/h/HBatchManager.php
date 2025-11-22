<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../pipelines/Workers/SSLWorker.php';
require_once __DIR__ . '/../../pipelines/Workers/OrgaoWorker.php';
require_once __DIR__ . '/../../pipelines/Workers/MunicipioWorker.php';

class HBatchManager {
    public static function processPending(int $limit = 20): array {
        Database::initSchema();
        $res = Database::query("SELECT id, entity_type, entity_id FROM h_queue WHERE status='pending' ORDER BY created_at ASC LIMIT ?", [$limit]);
        $done = 0; $errors = 0; $items = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $id = (int)$r['id']; $type = $r['entity_type']; $eid = (int)$r['entity_id'];
                Database::execute("UPDATE h_queue SET status='processing' WHERE id=?", [$id]);
                try {
                    $out = null;
                    if ($type === 'orgao') $out = OrgaoWorker::handle($eid);
                    else if ($type === 'municipio') $out = MunicipioWorker::handle($eid);
                    else $out = null;
                    Database::execute("UPDATE h_queue SET status='done' WHERE id=?", [$id]);
                    $done++; $items[] = ['id'=>$id,'type'=>$type,'entity_id'=>$eid,'ok'=>true];
                } catch (\Throwable $e) {
                    Database::execute("UPDATE h_queue SET status='error' WHERE id=?", [$id]);
                    $errors++; $items[] = ['id'=>$id,'type'=>$type,'entity_id'=>$eid,'ok'=>false];
                }
            }
        }
        return ['processed'=>$done,'errors'=>$errors,'items'=>$items];
    }
}
?>
