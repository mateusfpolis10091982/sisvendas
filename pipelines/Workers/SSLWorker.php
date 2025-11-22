<?php
require_once __DIR__ . '/../../modules/h/HController.php';
class SSLWorker { public static function handleDomain(string $d){ return HController::scan($d); } }
?>
