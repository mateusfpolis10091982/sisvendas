<?php
class PipelineUtils { public static function enqueue(string $type, int $id): bool { return Database::execute('INSERT INTO h_queue(entity_type, entity_id, status, created_at) VALUES(?,?,"pending",NOW())',[ $type, $id ]); } }
?>
