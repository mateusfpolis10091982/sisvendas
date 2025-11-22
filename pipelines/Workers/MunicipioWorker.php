<?php
require_once __DIR__ . '/../../modules/h/HController.php';
class MunicipioWorker { public static function handle(int $id){ return HController::processMunicipio($id); } }
?>
