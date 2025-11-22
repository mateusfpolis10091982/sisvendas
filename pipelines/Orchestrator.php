<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../modules/h/HController.php';
require_once __DIR__ . '/../modules/h/HBatchManager.php';
require_once __DIR__ . '/PipelineUtils.php';

class Orchestrator {
    public static function runFunctionH(array $params = []): array {
        Database::initSchema();
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        return HBatchManager::processPending($limit);
    }
    public static function seedPrefeiturasMinimal(): array {
        Database::initSchema();
        $items = [
            ['tipo'=>'prefeitura','nome'=>'São Paulo','uf'=>'SP','dominio'=>'sp.gov.br','site'=>'https://www.saopaulo.sp.gov.br','status'=>'ativo'],
            ['tipo'=>'prefeitura','nome'=>'Rio de Janeiro','uf'=>'RJ','dominio'=>'rj.gov.br','site'=>'https://www.rj.gov.br','status'=>'ativo'],
        ];
        $inserted = 0; $skipped = 0;
        foreach ($items as $it) {
            $exists = Database::query('SELECT id FROM prefeituras WHERE nome=? AND uf=? LIMIT 1', [$it['nome'], $it['uf']]);
            $row = $exists ? $exists->fetch_assoc() : null;
            if ($row) { $skipped++; } else {
                Database::execute('INSERT INTO prefeituras(nome, uf, dominio, site, status) VALUES(?,?,?,?,?)', [$it['nome'],$it['uf'],$it['dominio'],$it['site'],$it['status']]);
                $inserted++;
            }
            $exists2 = Database::query('SELECT id FROM orgaos WHERE tipo=? AND nome=? AND dominio=? LIMIT 1', [$it['tipo'], 'Prefeitura de '.$it['nome'], $it['dominio']]);
            $row2 = $exists2 ? $exists2->fetch_assoc() : null;
            if ($row2) { } else {
                Database::execute('INSERT INTO orgaos(tipo, nome, uf, dominio, site, status) VALUES(?,?,?,?,?,?)', [$it['tipo'],'Prefeitura de '.$it['nome'],$it['uf'],$it['dominio'],$it['site'],$it['status']]);
            }
        }
        return ['inserted'=>$inserted,'skipped'=>$skipped];
    }
    public static function enrichOrgaos(): array {
        Database::initSchema();
        $src = 'prefeituras';
        $ufCol = 'uf';
        $check = Database::query("SHOW TABLES LIKE 'prefeituras_new'");
        if ($check && $check->num_rows > 0) { $src = 'prefeituras_new'; $ufCol = 'estado'; }
        $needsCollate = ($src==='prefeituras_new');
        $col = $needsCollate ? ' COLLATE utf8mb4_general_ci' : '';
        $q0 = "INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, created_at)
                SELECT 'prefeitura', CONCAT('Prefeitura de ', p.nome), p.$ufCol, " . ($src==='prefeituras_new' ? 'NULL' : 'p.dominio') . ", " . ($src==='prefeituras_new' ? 'NULL' : 'p.site') . ", 'ativo', NOW()
                FROM $src p
                WHERE NOT EXISTS (
                    SELECT 1 FROM orgaos o WHERE o.tipo='prefeitura' AND o.nome=CONCAT('Prefeitura de ', p.nome$col) AND o.uf=p.$ufCol$col
                )";
        $q1 = "INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, created_at)
                SELECT 'camara_municipal', CONCAT('Câmara Municipal de ', p.nome), p.$ufCol, " . ($src==='prefeituras_new' ? 'NULL' : 'p.dominio') . ", " . ($src==='prefeituras_new' ? 'NULL' : 'p.site') . ", 'ativo', NOW()
                FROM $src p
                WHERE NOT EXISTS (
                    SELECT 1 FROM orgaos o WHERE o.tipo='camara_municipal' AND o.nome=CONCAT('Câmara Municipal de ', p.nome$col) AND o.uf=p.$ufCol$col
                )";
        $q2 = "INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, created_at)
                SELECT 'secretaria_saude', CONCAT('Secretaria de Saúde de ', p.nome), p.$ufCol, " . ($src==='prefeituras_new' ? 'NULL' : 'p.dominio') . ", " . ($src==='prefeituras_new' ? 'NULL' : 'p.site') . ", 'ativo', NOW()
                FROM $src p
                WHERE NOT EXISTS (
                    SELECT 1 FROM orgaos o WHERE o.tipo='secretaria_saude' AND o.nome=CONCAT('Secretaria de Saúde de ', p.nome$col) AND o.uf=p.$ufCol$col
                )";
        $q3 = "INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, created_at)
                SELECT 'secretaria_educacao', CONCAT('Secretaria de Educação de ', p.nome), p.$ufCol, " . ($src==='prefeituras_new' ? 'NULL' : 'p.dominio') . ", " . ($src==='prefeituras_new' ? 'NULL' : 'p.site') . ", 'ativo', NOW()
                FROM $src p
                WHERE NOT EXISTS (
                    SELECT 1 FROM orgaos o WHERE o.tipo='secretaria_educacao' AND o.nome=CONCAT('Secretaria de Educação de ', p.nome$col) AND o.uf=p.$ufCol$col
                )";
        $q4 = "INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, created_at)
                SELECT 'secretaria_financas', CONCAT('Secretaria de Finanças de ', p.nome), p.$ufCol, " . ($src==='prefeituras_new' ? 'NULL' : 'p.dominio') . ", " . ($src==='prefeituras_new' ? 'NULL' : 'p.site') . ", 'ativo', NOW()
                FROM $src p
                WHERE NOT EXISTS (
                    SELECT 1 FROM orgaos o WHERE o.tipo='secretaria_financas' AND o.nome=CONCAT('Secretaria de Finanças de ', p.nome$col) AND o.uf=p.$ufCol$col
                )";
        $q5 = "INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, created_at)
                SELECT 'procuradoria_municipal', CONCAT('Procuradoria Geral do Município de ', p.nome), p.$ufCol, " . ($src==='prefeituras_new' ? 'NULL' : 'p.dominio') . ", " . ($src==='prefeituras_new' ? 'NULL' : 'p.site') . ", 'ativo', NOW()
                FROM $src p
                WHERE NOT EXISTS (
                    SELECT 1 FROM orgaos o WHERE o.tipo='procuradoria_municipal' AND o.nome=CONCAT('Procuradoria Geral do Município de ', p.nome$col) AND o.uf=p.$ufCol$col
                )";
        Database::execute($q0);
        Database::execute($q1);
        Database::execute($q2);
        Database::execute($q3);
        Database::execute($q4);
        Database::execute($q5);
        $r = Database::query("SELECT COUNT(*) c FROM orgaos");
        $row = $r ? $r->fetch_assoc() : ['c'=>0];
        return ['orgaos_total'=>(int)$row['c']];
    }

    public static function detectNacional(array $params = []): array {
        Database::initSchema();
        $uf = isset($params['uf']) ? strtoupper(trim($params['uf'])) : null;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
        $munWhere = []; $munParams = [];
        if ($uf) { $munWhere[] = 'uf = ?'; $munParams[] = $uf; }
        $sqlMun = 'SELECT id, nome, uf, ibge FROM municipios';
        if ($munWhere) $sqlMun .= ' WHERE ' . implode(' AND ', $munWhere);
        $sqlMun .= ' ORDER BY nome ASC LIMIT ?'; $munParams[] = $limit;
        $rm = Database::query($sqlMun, $munParams);
        $processed = 0; $created = 0; $updated = 0; $errors = 0; $items = [];
        if ($rm) {
            while ($m = $rm->fetch_assoc()) {
                $municipioId = (int)$m['id']; $nome = $m['nome']; $muf = $m['uf'];
                $cands = [];
                foreach (['prefeitura','camara_municipal','secretaria_saude','secretaria_educacao','secretaria_financas'] as $tipo) {
                    $cands = array_merge($cands, HelperOrg::generateDomainCandidates($tipo, $nome, $muf));
                }
                $cands = array_values(array_unique($cands));
                $best = null; $bestScore = -1; $bestInfo = null;
                foreach ($cands as $dom) {
                    $score = 0; $info = ['dominio'=>$dom];
                    $ip = @gethostbyname($dom); if ($ip && $ip !== $dom) { $score += 10; $info['dns_ip'] = $ip; } else { continue; }
                    $ssl = null; try { $ssl = SSLScanner::scanDomain($dom); } catch (\Throwable $e) { $ssl = null; }
                    if ($ssl && !empty($ssl['valid_to'])) { $score += 20; $info['ssl'] = $ssl; }
                    $html = null; $title = null; $okHttp = false;
                    try { $ctx = stream_context_create(['http'=>['timeout'=>5],'https'=>['timeout'=>5]]); $html = @file_get_contents('https://' . $dom, false, $ctx); $okHttp = is_string($html) && strlen($html) > 0; } catch (\Throwable $e) { $okHttp = false; }
                    if ($okHttp) {
                        if (preg_match('/<title>(.*?)<\/title>/is', $html, $mm)) { $title = trim($mm[1]); $info['title'] = $title; }
                        $txt = strtolower(strip_tags($html));
                        if (strpos($txt, 'prefeitura')!==false) { $score += 15; $info['tipo_detect'] = 'prefeitura'; }
                        else if (strpos($txt, 'câmara municipal')!==false || strpos($txt,'camara municipal')!==false) { $score += 15; $info['tipo_detect'] = 'camara_municipal'; }
                        else if (strpos($txt, 'secretaria')!==false) { $score += 10; $info['tipo_detect'] = 'secretaria'; }
                        if (strpos($txt, 'governo do estado')!==false || strpos($txt, 'estado de')!==false) { $score += 12; $info['esfera_detect'] = 'estadual'; }
                        if (strpos($txt, 'ministério')!==false || strpos($txt, 'gov.br')!==false) { $score += 8; $info['esfera_detect'] = 'federal'; }
                        if (strpos($txt, 'brasão')!==false || strpos($txt, 'brasao')!==false) { $score += 5; }
                    }
                    if (substr($dom, -7) === '.gov.br') { $score += 8; }
                    if (substr($dom, -7) === '.leg.br') { $score += 8; }
                    if ($score > $bestScore) { $bestScore = $score; $best = $dom; $bestInfo = $info; }
                }
                if ($best) {
                    $tipoFinal = $bestInfo['tipo_detect'] ?? null;
                    $esfera = HelperOrg::classifyEsfera($tipoFinal, 'Prefeitura de ' . $nome) ?? ($bestInfo['esfera_detect'] ?? null);
                    $confianca = max(0, min(100, $bestScore));
                    $exists = Database::query('SELECT id, dominio FROM orgaos WHERE nome=? AND uf=? LIMIT 1', ['Prefeitura de '.$nome, $muf]);
                    $row = $exists ? $exists->fetch_assoc() : null;
                    if ($row) {
                        $ok = Database::execute('UPDATE orgaos SET dominio=?, municipio_id=?, esfera=?, confianca_ia=?, atualizado_em=NOW() WHERE id=?', [$best, $municipioId, $esfera, $confianca, (int)$row['id']]);
                        $updated += $ok ? 1 : 0;
                    } else {
                        $ok = Database::execute('INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, municipio_id, esfera, confianca_ia, atualizado_em, created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW(),NOW())', ['prefeitura','Prefeitura de '.$nome, $muf, $best, null, 'ativo', $municipioId, $esfera, $confianca]);
                        $created += $ok ? 1 : 0;
                    }
                    $items[] = ['municipio_id'=>$municipioId,'uf'=>$muf,'nome'=>$nome,'dominio'=>$best,'score'=>$bestScore,'esfera'=>$esfera];
                } else { $errors++; $items[] = ['municipio_id'=>$municipioId,'uf'=>$muf,'nome'=>$nome,'error'=>'no_candidate']; }
                $processed++;
            }
        }
        return ['processed'=>$processed,'created'=>$created,'updated'=>$updated,'errors'=>$errors,'items'=>$items];
    }

    public static function detectEstadual(array $params = []): array {
        Database::initSchema();
        $uf = isset($params['uf']) ? strtoupper(trim($params['uf'])) : null;
        if (!$uf) return ['processed'=>0,'created'=>0,'updated'=>0,'errors'=>1,'items'=>[]];
        $keys = [
            ['key'=>'sefaz','tipo'=>'secretaria_estadual','nome'=>'Secretaria da Fazenda'],
            ['key'=>'saude','tipo'=>'secretaria_estadual','nome'=>'Secretaria da Saúde'],
            ['key'=>'educacao','tipo'=>'secretaria_estadual','nome'=>'Secretaria da Educação'],
            ['key'=>'setur','tipo'=>'secretaria_estadual','nome'=>'Secretaria do Turismo'],
            ['key'=>'secom','tipo'=>'secretaria_estadual','nome'=>'Secretaria de Comunicação'],
            ['key'=>'policiacivil','tipo'=>'orgao_estadual','nome'=>'Polícia Civil'],
            ['key'=>'detran','tipo'=>'orgao_estadual','nome'=>'DETRAN'],
        ];
        $processed=0; $created=0; $updated=0; $errors=0; $items=[];
        foreach ($keys as $k) {
            $dom = $k['key'] . '.' . strtolower($uf) . '.gov.br';
            $score = 0; $ip = @gethostbyname($dom); if ($ip && $ip !== $dom) { $score += 10; } else { $errors++; $items[]=['uf'=>$uf,'dominio'=>$dom,'error'=>'no_dns']; continue; }
            $ssl = null; try { $ssl = SSLScanner::scanDomain($dom); } catch (\Throwable $e) { $ssl=null; }
            if ($ssl && !empty($ssl['valid_to'])) $score += 20;
            $html = null; $okHttp=false; try { $ctx = stream_context_create(['http'=>['timeout'=>5],'https'=>['timeout'=>5]]); $html = @file_get_contents('https://' . $dom, false, $ctx); $okHttp = is_string($html) && strlen($html) > 0; } catch (\Throwable $e) { $okHttp=false; }
            if ($okHttp) $score += 10;
            $nome = $k['nome'] . ' - Governo do Estado de ' . $uf;
            $exists = Database::query('SELECT id FROM orgaos WHERE tipo=? AND nome=? AND uf=? LIMIT 1', [$k['tipo'], $nome, $uf]);
            $row = $exists ? $exists->fetch_assoc() : null;
            $esfera = 'estadual'; $confianca = max(0, min(100, $score));
            if ($row) { $ok = Database::execute('UPDATE orgaos SET dominio=?, esfera=?, confianca_ia=?, atualizado_em=NOW() WHERE id=?', [$dom, $esfera, $confianca, (int)$row['id']]); $updated += $ok?1:0; }
            else { $ok = Database::execute('INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, esfera, confianca_ia, atualizado_em, created_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())', [$k['tipo'],$nome,$uf,$dom,null,'ativo',$esfera,$confianca]); $created += $ok?1:0; }
            $items[] = ['uf'=>$uf,'tipo'=>$k['tipo'],'nome'=>$nome,'dominio'=>$dom,'score'=>$score];
            $processed++;
        }
        return ['processed'=>$processed,'created'=>$created,'updated'=>$updated,'errors'=>$errors,'items'=>$items];
    }

    public static function detectFederal(array $params = []): array {
        Database::initSchema();
        $keys = [
            ['dominio'=>'inss.gov.br','tipo'=>'orgao_federal','nome'=>'INSS'],
            ['dominio'=>'ibge.gov.br','tipo'=>'orgao_federal','nome'=>'IBGE'],
            ['dominio'=>'dnit.gov.br','tipo'=>'orgao_federal','nome'=>'DNIT'],
            ['dominio'=>'policiafederal.gov.br','tipo'=>'orgao_federal','nome'=>'Polícia Federal'],
            ['dominio'=>'receita.gov.br','tipo'=>'orgao_federal','nome'=>'Receita Federal'],
            ['dominio'=>'justica.gov.br','tipo'=>'orgao_federal','nome'=>'Ministério da Justiça'],
        ];
        $processed=0; $created=0; $updated=0; $errors=0; $items=[];
        foreach ($keys as $k) {
            $dom = $k['dominio'];
            $score = 0; $ip = @gethostbyname($dom); if ($ip && $ip !== $dom) { $score += 10; } else { $errors++; $items[]=['dominio'=>$dom,'error'=>'no_dns']; continue; }
            $ssl = null; try { $ssl = SSLScanner::scanDomain($dom); } catch (\Throwable $e) { $ssl=null; }
            if ($ssl && !empty($ssl['valid_to'])) $score += 20;
            $html = null; $okHttp=false; try { $ctx = stream_context_create(['http'=>['timeout'=>5],'https'=>['timeout'=>5]]); $html = @file_get_contents('https://' . $dom, false, $ctx); $okHttp = is_string($html) && strlen($html) > 0; } catch (\Throwable $e) { $okHttp=false; }
            if ($okHttp) $score += 10;
            $nome = $k['nome'] . ' - Governo Federal';
            $exists = Database::query('SELECT id FROM orgaos WHERE tipo=? AND nome=? AND uf IS NULL LIMIT 1', [$k['tipo'], $nome]);
            $row = $exists ? $exists->fetch_assoc() : null;
            $esfera = 'federal'; $confianca = max(0, min(100, $score));
            if ($row) { $ok = Database::execute('UPDATE orgaos SET dominio=?, esfera=?, confianca_ia=?, atualizado_em=NOW() WHERE id=?', [$dom, $esfera, $confianca, (int)$row['id']]); $updated += $ok?1:0; }
            else { $ok = Database::execute('INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, esfera, confianca_ia, atualizado_em, created_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())', [$k['tipo'],$nome,null,$dom,null,'ativo',$esfera,$confianca]); $created += $ok?1:0; }
            $items[] = ['tipo'=>$k['tipo'],'nome'=>$nome,'dominio'=>$dom,'score'=>$score];
            $processed++;
        }
        return ['processed'=>$processed,'created'=>$created,'updated'=>$updated,'errors'=>$errors,'items'=>$items];
    }
}
