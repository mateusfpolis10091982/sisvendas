<?php
require_once __DIR__ . '/HelperMunicipio.php';
require_once __DIR__ . '/HelperOrg.php';
require_once __DIR__ . '/HelperSSL.php';
require_once __DIR__ . '/HRisco.php';
require_once __DIR__ . '/HInsights.php';
require_once __DIR__ . '/../../modules/crm/CRMAuto.php';
require_once __DIR__ . '/../../modules/crm/CRMAgenda.php';
require_once __DIR__ . '/../../core/Database.php';
class HController {
    public static function processMunicipio(int $id): array { $dom = HelperMunicipio::getDominio($id); if (!$dom) return ['error'=>'dominio_nao_encontrado']; return self::processDomain($dom, ['municipio_id'=>$id]); }
    public static function processOrgao(int $id): array { $dom = HelperOrg::getDominio($id); if (!$dom) return ['error'=>'dominio_nao_encontrado']; return self::processDomain($dom, ['orgao_id'=>$id]); }
    public static function scan(string $dominio): array { return self::processDomain($dominio, []); }
    public static function run(array $params): array { $limit = isset($params['limit'])?(int)$params['limit']:10; $count=0; for($i=0;$i<$limit;$i++){ $count++; } return ['processed'=>$count]; }
    public static function info(): array { return ['name'=>'Funcao H','version'=>'1.0']; }
    private static function processDomain(string $dominio, array $ctx): array {
        $ssl = HelperSSL::scanAndPersist($dominio); $risco = HRisco::classify($ssl); $insights = HInsights::generate($ssl,$risco);
        $opp = CRMAuto::createAuto(['dominio'=>$dominio,'risco'=>$risco,'insights'=>$insights,'orgao_id'=>$ctx['orgao_id']??null]);
        $agenda = CRMAgenda::createAuto(['dominio'=>$dominio,'dias'=>$ssl['dias_restantes']??null,'oportunidade_id'=>$opp['id']??null,'orgao_id'=>$ctx['orgao_id']??null]);
        return ['ssl'=>$ssl,'risco'=>$risco,'insights'=>$insights,'oportunidade'=>$opp,'agenda'=>$agenda];
    }
}
?>
