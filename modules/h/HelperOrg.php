<?php
require_once __DIR__ . '/../../core/Database.php';
class HelperOrg {
    public static function getDominio(int $orgaoId): ?string {
        $r = Database::query('SELECT dominio FROM orgaos WHERE id=?', [$orgaoId]);
        $row = $r ? $r->fetch_assoc() : null; return $row['dominio'] ?? null;
    }

    public static function classifyEsfera(?string $tipo, string $nome): ?string {
        $t = $tipo ? strtolower($tipo) : '';
        $n = strtolower($nome);
        if (in_array($t, ['prefeitura','camara_municipal','secretaria_financas','secretaria_saude','secretaria_educacao','procuradoria_municipal'], true)) return 'municipal';
        if (strpos($n, 'prefeitura') !== false || strpos($n, 'municipal') !== false || strpos($n, 'câmara') !== false) return 'municipal';
        if (strpos($n, 'estadual') !== false || strpos($n, 'estado') !== false) return 'estadual';
        if (strpos($n, 'federal') !== false || strpos($n, 'união') !== false || strpos($n, 'ministério') !== false || strpos($n, 'tribunal') !== false) return 'federal';
        return null;
    }

    private static function slugify(string $s): string {
        $s = iconv('UTF-8','ASCII//TRANSLIT',$s);
        $s = preg_replace('/[^a-zA-Z0-9]+/','-', $s);
        $s = strtolower(trim($s,'-'));
        return $s;
    }

    public static function generateDomainCandidates(?string $tipo, string $nome, string $uf): array {
        $uf = strtoupper($uf);
        $city = $nome;
        if (stripos($nome, ' de ') !== false) { $parts = explode(' de ', $nome); $city = end($parts); }
        $slug = self::slugify($city);
        $cands = [];
        $t = $tipo ? strtolower($tipo) : '';
        if ($t === 'prefeitura') {
            $cands[] = $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = 'prefeitura.' . $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = $slug . '.gov.br';
            $cands[] = 'www.' . $slug . '.gov.br';
        } else if ($t === 'camara_municipal') {
            $cands[] = 'camara.' . $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = 'cm.' . $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = 'camaramunicipal.' . $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = $slug . '.' . strtolower($uf) . '.leg.br';
            $cands[] = 'camara.' . $slug . '.' . strtolower($uf) . '.leg.br';
            $cands[] = 'cm.' . $slug . '.' . strtolower($uf) . '.leg.br';
        } else if ($t === 'secretaria_saude' || strpos(strtolower($nome),'saúde')!==false) {
            $cands[] = 'saude.' . $slug . '.' . strtolower($uf) . '.gov.br';
        } else if ($t === 'secretaria_educacao' || strpos(strtolower($nome),'educa')!==false) {
            $cands[] = 'educacao.' . $slug . '.' . strtolower($uf) . '.gov.br';
        } else if ($t === 'secretaria_financas' || strpos(strtolower($nome),'finan')!==false) {
            $cands[] = 'financas.' . $slug . '.' . strtolower($uf) . '.gov.br';
        } else if ($t === 'autarquia' || strpos(strtolower($nome),'autarquia')!==false) {
            $cands[] = 'autarquia.' . $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = $slug . '.' . strtolower($uf) . '.gov.br';
        } else if ($t === 'empresa_publica' || strpos(strtolower($nome),'empresa')!==false) {
            $cands[] = 'empresa.' . $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = 'empresapublica.' . $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = $slug . '.' . strtolower($uf) . '.gov.br';
        } else if ($t === 'fundacao' || strpos(strtolower($nome),'funda')!==false) {
            $cands[] = 'fundacao.' . $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = $slug . '.' . strtolower($uf) . '.gov.br';
        } else {
            $cands[] = $slug . '.' . strtolower($uf) . '.gov.br';
            $cands[] = $slug . '.gov.br';
        }
        $commonSubs = ['portal','www','transparencia','diariooficial'];
        foreach ($commonSubs as $sub) {
            $cands[] = $sub . '.' . $slug . '.' . strtolower($uf) . '.gov.br';
        }
        if ($t === 'secretaria_estadual' || strpos(strtolower($nome),'secretaria')!==false || in_array(strtolower($slug), ['sefaz','sesa','ses','seduc','setur','secom','seinf','sead'])) {
            $keys = ['sefaz','sesa','seduc','setur','secom','policiacivil','detran','deinfra','ima','procon'];
            foreach ($keys as $k) { $cands[] = $k . '.' . strtolower($uf) . '.gov.br'; }
        }
        if ($t === 'orgao_federal' || strpos(strtolower($nome),'federal')!==false) {
            $fk = ['inss','receita','pf','dnit','ibge','justicafederal','tre','caixa','correios','antt','prf','funasa'];
            foreach ($fk as $k) { $cands[] = $k . '.gov.br'; }
        }
        return array_values(array_unique($cands));
    }
}
?>
