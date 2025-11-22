<?php
require_once __DIR__ . '/../../modules/h/HController.php';
class OrgaoWorker { public static function handle(int $id){ return HController::processOrgao($id); } }
?>
