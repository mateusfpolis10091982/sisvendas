<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Utils.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/pipelines/Orchestrator.php';
require_once __DIR__ . '/monitor_ssl/Scanner.php';
require_once __DIR__ . '/modules/h/HController.php';
require_once __DIR__ . '/modules/h/HelperSSL.php';
require_once __DIR__ . '/modules/crm/CRMContatos.php';
require_once __DIR__ . '/modules/crm/CRMAuto.php';
require_once __DIR__ . '/modules/crm/CRMAgenda.php';
require_once __DIR__ . '/modules/h/HelperOrg.php';
require_once __DIR__ . '/modules/h/HRisco.php';
require_once __DIR__ . '/modules/h/HInsights.php';

// Inicializa schema na primeira carga
if (APP_ENV === 'dev') {
    Database::initSchema();
}

Utils::allowOrigin();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    echo json_encode(['ok' => true]);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$query = [];
parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
// Parse body JSON para POST
$body = null;
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $body = json_decode($raw, true);
        if (!is_array($body)) $body = null;
    }
}

// Serve SPA build for non-/api routes (cPanel/production)
if ($uri === '/' || (strpos($uri, '/api') !== 0)) {
    // Static assets from React build
    $cand = null;
    $build = __DIR__ . '/build';
    $pub = __DIR__ . '/public';
    if (strpos($uri, '/static/') === 0) { $cand = $build . $uri; }
    else if (strpos($uri, '/asset-manifest.json') === 0) { $cand = $build . $uri; }
    else if (strpos($uri, '/favicon.ico') === 0) { $cand = $build . $uri; }
    else if ($uri === '/%PUBLIC_URL%/favicon.ico') { $cand = $build . '/favicon.ico'; }
    else if (strpos($uri, '/manifest.json') === 0) { $cand = $build . $uri; }
    else if (strpos($uri, '/public/') === 0) { $cand = $pub . substr($uri, strlen('/public/')); }
    if ($cand && is_file($cand)) {
        $ext = strtolower(pathinfo($cand, PATHINFO_EXTENSION));
        if ($ext === 'css') { header('Content-Type: text/css; charset=utf-8'); header('Cache-Control: public, max-age=31536000'); }
        else if ($ext === 'js') { header('Content-Type: application/javascript; charset=utf-8'); header('Cache-Control: public, max-age=31536000'); }
        else if ($ext === 'html') { header('Content-Type: text/html; charset=utf-8'); }
        else if ($ext === 'json') { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: public, max-age=604800'); }
        else { header('Content-Type: application/octet-stream'); }
        readfile($cand); exit;
    }
    // Fallback to React build index.html (or public/index.html)
    $indexBuild = $build . '/index.html';
    $indexPub = $pub . '/index.html';
    if (is_file($indexBuild)) { header('Content-Type: text/html; charset=utf-8'); readfile($indexBuild); exit; }
    if ($uri === '/' && is_file($indexPub)) { header('Content-Type: text/html; charset=utf-8'); readfile($indexPub); exit; }
}
if ($uri === '/api') {
    Utils::json([
        'app' => APP_NAME,
        'version' => APP_VERSION,
        'endpoints' => [
            '/api/health',
            '/api/h/run?limit=10',
            '/api/ssl/scan?domain=exemplo.gov.br'
        ],
    ]);
    exit;
}

switch ($uri) {
    case '/api/migrate':
        Utils::requireAuth();
        $summary = Database::initSchemaSummary();
        Utils::json(['ok' => true, 'summary' => $summary]);
        break;
    case '/api/health':
        Utils::json([
            'status' => 'ok',
            'db_connected' => Database::isConnected(),
            'db' => ['host'=>DB_HOST,'user'=>DB_USER,'name'=>DB_NAME],
            'php_ext' => ['mysqli' => extension_loaded('mysqli'), 'pdo_mysql' => extension_loaded('pdo_mysql')],
            'time' => date('c'),
        ]);
        break;
    case '/api/migrate/status':
        Utils::requireAuth();
        $tables = ['prefeituras','orgaos','ssl_results','crm_oportunidades','datalake_raw','pipelines_runs','audit_events','h_queue','crm_contatos','crm_agenda','ssl_scans','dominios','prefeituras_new','prefeitura_etapas','prefeitura_status','prefeitura_relacionamento','contatos','historico','municipios','municipios_normalizado','enderecos','automacao_logs','logs','usuarios','telefones','crawler_logs'];
        $missing = [];
        foreach ($tables as $t) { $r = Database::query("SHOW TABLES LIKE '" . $t . "'"); if (!$r || $r->num_rows === 0) $missing[] = $t; }
        Utils::json(['ok'=>true,'missing_tables'=>$missing]);
        break;

    case '/api/version':
        Utils::json([
            'app' => APP_NAME,
            'version' => APP_VERSION,
            'env' => APP_ENV,
            'time' => date('c'),
        ]);
        break;
    case '/api/phpinfo':
        Utils::json([
            'ini' => php_ini_loaded_file(),
            'ext' => get_loaded_extensions(),
        ]);
        break;

    case '/dashboard':
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/public/index.html');
        break;

    case '/api/h/run':
        Utils::requireAuth();
        $params = array_merge($query, is_array($body) ? $body : []);
        $res = Orchestrator::runFunctionH($params);
        Utils::json(['ok' => true, 'summary' => $res]);
        break;

    case '/api/ia/pipeline/detect':
        Utils::requireAuth();
        $params = array_merge($query, is_array($body) ? $body : []);
        $res = Orchestrator::detectNacional($params);
        Utils::json(['ok'=>true,'result'=>$res]);
        break;

    case '/api/ia/pipeline/detect_estadual':
        Utils::requireAuth();
        $params = array_merge($query, is_array($body) ? $body : []);
        $res = Orchestrator::detectEstadual($params);
        Utils::json(['ok'=>true,'result'=>$res]);
        break;

    case '/api/ia/pipeline/detect_federal':
        Utils::requireAuth();
        $params = array_merge($query, is_array($body) ? $body : []);
        $res = Orchestrator::detectFederal($params);
        Utils::json(['ok'=>true,'result'=>$res]);
        break;

    case '/api/domains/fallback':
        $dom = ($query['dominio'] ?? ($body['dominio'] ?? null));
        if (!$dom) { Utils::json(['error'=>'dominio_required'],400); break; }
        $slug = preg_replace('/^www\./','', strtolower($dom));
        $subs = ['www','portal','transparencia','diariooficial'];
        $cands = [];
        foreach ($subs as $s) { $cands[] = $s . '.' . $slug; }
        $valid = [];
        foreach ($cands as $d) {
            $ip = @gethostbyname($d); if ($ip && $ip !== $d) {
                $okHttp=false; try { $ctx = stream_context_create(['http'=>['timeout'=>4],'https'=>['timeout'=>4]]); $html = @file_get_contents('https://' . $d, false, $ctx); $okHttp = is_string($html) && strlen($html) > 0; } catch (\Throwable $e) { $okHttp=false; }
                if ($okHttp) $valid[] = $d;
            }
        }
        Utils::json(['candidates'=>$cands,'valid'=>$valid]);
        break;

    case '/api/ia/reclassify-orgao':
        Utils::requireAuth(); Utils::requireQuery($query, ['id']);
        $id = (int)$query['id'];
        $res = Database::query('SELECT id, tipo, nome, uf, dominio FROM orgaos WHERE id=?', [$id]);
        $org = $res ? $res->fetch_assoc() : null;
        if (!$org) { Utils::json(['error'=>'not_found'],404); break; }
        $dom = $org['dominio'] ?? null;
        $score = 0; $esfera = null; $tipoFinal = null;
        if ($dom) {
            try { $ssl = SSLScanner::scanDomain($dom); if ($ssl && !empty($ssl['valid_to'])) $score += 20; } catch (\Throwable $e) {}
            $html = null; $okHttp=false; try { $ctx = stream_context_create(['http'=>['timeout'=>5],'https'=>['timeout'=>5]]); $html = @file_get_contents('https://' . $dom, false, $ctx); $okHttp = is_string($html) && strlen($html) > 0; } catch (\Throwable $e) { $okHttp=false; }
            if ($okHttp) {
                $txt = strtolower(strip_tags($html));
                if (strpos($txt, 'prefeitura')!==false) { $tipoFinal = 'prefeitura'; $score += 15; }
                else if (strpos($txt, 'câmara municipal')!==false || strpos($txt,'camara municipal')!==false) { $tipoFinal = 'camara_municipal'; $score += 15; }
                else if (strpos($txt, 'secretaria')!==false) { $tipoFinal = (strpos($txt,'estado')!==false ? 'secretaria_estadual' : 'secretaria_municipal'); $score += 10; }
                if (strpos($txt, 'governo do estado')!==false || strpos($txt, 'estado de')!==false) { $esfera = 'estadual'; $score += 12; }
                if (strpos($txt, 'ministério')!==false || strpos($txt, 'gov.br')!==false) { $esfera = 'federal'; $score += 8; }
            }
        }
        if (!$esfera) $esfera = HelperOrg::classifyEsfera($tipoFinal ?: $org['tipo'], $org['nome']);
        $confianca = max(0, min(100, $score));
        Database::execute('UPDATE orgaos SET esfera=?, confianca_ia=?, atualizado_em=NOW() WHERE id=?', [$esfera, $confianca, $id]);
        Utils::json(['ok'=>true,'id'=>$id,'esfera'=>$esfera,'confianca'=>$confianca]);
        break;

    case '/api/h/process/municipio':
        Utils::requireAuth(); Utils::requireQuery($query, ['id']);
        $out = HController::processMunicipio((int)$query['id']); Utils::json($out); break;

    case '/api/h/process/orgao':
        Utils::requireAuth(); Utils::requireQuery($query, ['id']);
        $out = HController::processOrgao((int)$query['id']); Utils::json($out); break;

    case '/api/h/scan':
        Utils::requireAuth(); Utils::requireQuery($query, ['dominio']);
        $out = HController::scan($query['dominio']); Utils::json($out); break;

    case '/api/h/info':
        Utils::json(HController::info()); break;

    case '/api/ssl/scan':
        Utils::requireQuery($query, ['domain']);
        $res = SSLScanner::scanDomain($query['domain']);
        Utils::json($res);
        break;

    case '/api/prefeituras/seed':
        Utils::requireAuth();
        $res = Orchestrator::seedPrefeiturasMinimal();
        Utils::json(['ok' => true, 'result' => $res]);
        break;

    case '/api/orgaos/enrich':
        Utils::requireAuth();
        $res = Orchestrator::enrichOrgaos();
        Utils::json(['ok' => true, 'result' => $res]);
        break;

    case '/api/orgaos/list':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $order = isset($query['order_by']) ? strtolower($query['order_by']) : 'created_at';
        $dir = isset($query['order_dir']) ? strtoupper($query['order_dir']) : 'DESC';
        if (!in_array($order, ['created_at','nome','uf','tipo'], true)) $order = 'created_at';
        if ($dir !== 'ASC' && $dir !== 'DESC') $dir = 'DESC';
        $where = [];
        $params = [];
        if (!empty($query['uf'])) { $where[] = 'uf = ?'; $params[] = $query['uf']; }
        if (!empty($query['tipo'])) { $where[] = 'tipo = ?'; $params[] = $query['tipo']; }
        if (!empty($query['esfera'])) {
            $e = strtolower(trim($query['esfera']));
            if ($e === 'municipal') { $where[] = "(tipo IN ('prefeitura','camara_municipal','secretaria_financas','secretaria_saude','secretaria_educacao','procuradoria_municipal') OR nome LIKE ? OR nome LIKE ?)"; $params[] = '%Prefeitura%'; $params[] = '%Municipal%'; }
            else if ($e === 'estadual') { $where[] = "(nome LIKE ? OR nome LIKE ?)"; $params[] = '%Estado%'; $params[] = '%Estadual%'; }
            else if ($e === 'federal') { $where[] = "(nome LIKE ? OR nome LIKE ?)"; $params[] = '%Federal%'; $params[] = '%União%'; }
        }
        $sql = 'SELECT id, tipo, nome, uf, dominio, site, status, created_at FROM orgaos';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY ' . $order . ' ' . $dir . ' LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $res = Database::query($sql, $params);
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) { $r['esfera'] = HelperOrg::classifyEsfera($r['tipo'] ?? null, $r['nome'] ?? ''); $rows[] = $r; } }
        $countSql = 'SELECT COUNT(*) c FROM orgaos';
        $paramsCount = [];
        if ($where) { $paramsCount = $params; array_pop($paramsCount); array_pop($paramsCount); $countSql .= ' WHERE ' . implode(' AND ', $where); }
        $resCount = Database::query($countSql, $paramsCount);
        $rowCount = $resCount ? $resCount->fetch_assoc() : ['c'=>0];
        Utils::json(['items' => $rows, 'total' => (int)$rowCount['c'], 'limit' => $limit, 'offset' => $offset]);
        break;

    case '/api/orgaos/get':
        Utils::requireQuery($query, ['id']);
        $id = (int)$query['id'];
        $org = null;
        $res = Database::query('SELECT * FROM orgaos WHERE id=?', [$id]);
        if ($res) { $org = $res->fetch_assoc(); }
        if (!$org) { Utils::json(['error'=>'not_found'],404); break; }
        $ssl = null;
        if (!empty($org['dominio'])) {
            $hasCap = Database::query("SHOW COLUMNS FROM ssl_results LIKE 'capturado_em'");
            $orderCol = ($hasCap && $hasCap->num_rows > 0) ? 'capturado_em' : 'last_scan_at';
            $rssl = Database::query('SELECT * FROM ssl_results WHERE dominio=? ORDER BY ' . $orderCol . ' DESC LIMIT 1', [$org['dominio']]);
            $ssl = $rssl ? $rssl->fetch_assoc() : null;
        }
        $sslCount = 0;
        if (!empty($org['dominio'])) {
            $rsc = Database::query('SELECT COUNT(*) c FROM ssl_results WHERE dominio=?', [$org['dominio']]);
            if ($rsc && ($row=$rsc->fetch_assoc())) $sslCount = (int)$row['c'];
        }
        $opps = [];
        $ro = Database::query('SELECT id, titulo, status, prioridade, origem, created_at FROM crm_oportunidades WHERE orgao_id=? ORDER BY created_at DESC LIMIT 10', [$id]);
        if ($ro) { while ($x = $ro->fetch_assoc()) $opps[] = $x; }
        $contatos = [];
        $rc = Database::query('SELECT id, nome, cargo, email, telefone, whatsapp, origem, created_at FROM crm_contatos WHERE orgao_id=? ORDER BY created_at DESC LIMIT 10', [$id]);
        if ($rc) { while ($x = $rc->fetch_assoc()) $contatos[] = $x; }
        $aud = 0;
        $ra = Database::query("SELECT COUNT(*) c FROM audit_events WHERE entity_type='orgao' AND entity_id=?", [$id]);
        if ($ra && ($row = $ra->fetch_assoc())) $aud = (int)$row['c'];
        $municipio = null;
        $src = 'prefeituras';
        $chk = Database::query("SHOW TABLES LIKE 'prefeituras_new'");
        if ($chk && $chk->num_rows > 0) $src = 'prefeituras_new';
        $where = '';
        $paramsM = [];
        if (!empty($org['dominio'])) { $where = 'dominio = ?'; $paramsM[] = $org['dominio']; }
        else {
            $n = $org['nome'] ?? '';
            $n = str_ireplace(['Prefeitura de ','Câmara Municipal de ','Secretaria de Saúde de ','Secretaria de Educação de ','Secretaria de Finanças de ','Procuradoria Geral do Município de '],'',$n);
            $n = trim($n);
            if ($n) { $where = 'nome = ?'; $paramsM[] = $n; }
        }
        if ($where) {
            if ($src === 'prefeituras_new') {
                $rm = Database::query('SELECT id, nome, estado AS uf, dominio, site, status, created_at FROM prefeituras_new WHERE ' . $where . ' LIMIT 1', $paramsM);
                $municipio = $rm ? $rm->fetch_assoc() : null;
            } else {
                $rm = Database::query('SELECT id, nome, uf, dominio, site, status, created_at FROM prefeituras WHERE ' . $where . ' LIMIT 1', $paramsM);
                $municipio = $rm ? $rm->fetch_assoc() : null;
            }
        }
        Utils::json(['item'=>$org,'ssl'=>$ssl,'ssl_scans_count'=>$sslCount,'municipio'=>$municipio,'oportunidades'=>$opps,'contatos'=>$contatos,'auditorias'=>$aud]);
        break;

    case '/api/orgaos/search':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 50;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $order = isset($query['order_by']) ? strtolower($query['order_by']) : 'created_at';
        $dir = isset($query['order_dir']) ? strtoupper($query['order_dir']) : 'DESC';
        if (!in_array($order, ['created_at','nome','uf','tipo'], true)) $order = 'created_at';
        if ($dir !== 'ASC' && $dir !== 'DESC') $dir = 'DESC';
        $where = [];
        $params = [];
        if (!empty($query['q'])) { $where[] = 'o.nome LIKE ?'; $params[] = '%' . $query['q'] . '%'; }
        if (!empty($query['uf'])) { $where[] = 'o.uf = ?'; $params[] = $query['uf']; }
        if (!empty($query['tipo'])) { $where[] = 'o.tipo = ?'; $params[] = $query['tipo']; }
        if (!empty($query['status'])) { $where[] = 'o.status = ?'; $params[] = $query['status']; }
        if (isset($query['has_dominio'])) {
            $where[] = $query['has_dominio'] ? "(o.dominio IS NOT NULL AND o.dominio <> '')" : 'o.dominio IS NULL';
        }
        if (!empty($query['esfera'])) {
            $e = strtolower(trim($query['esfera']));
            if ($e === 'municipal') { $where[] = "(o.tipo IN ('prefeitura','camara_municipal','secretaria_financas','secretaria_saude','secretaria_educacao','procuradoria_municipal') OR o.nome LIKE ? OR o.nome LIKE ?)"; $params[] = '%Prefeitura%'; $params[] = '%Municipal%'; }
            else if ($e === 'estadual') { $where[] = "(o.nome LIKE ? OR o.nome LIKE ?)"; $params[] = '%Estado%'; $params[] = '%Estadual%'; }
            else if ($e === 'federal') { $where[] = "(o.nome LIKE ? OR o.nome LIKE ?)"; $params[] = '%Federal%'; $params[] = '%União%'; }
        }
        $sqlBase = 'FROM orgaos o';
        $joinSSL = false;
        $sslFilters = false;
        if (isset($query['has_ssl'])) { $joinSSL = true; $sslFilters = true; }
        if (!empty($query['ssl_status']) || isset($query['ssl_days_max']) || isset($query['ssl_days_min'])) { $joinSSL = true; $sslFilters = true; }
        $sql = 'SELECT o.id, o.tipo, o.nome, o.uf, o.dominio, o.site, o.status, o.created_at, o.email, o.telefone' . ($joinSSL ? ', sr.status AS ssl_status, sr.valid_from, sr.valid_to, sr.dias_restantes, sr.issuer, sr.cn, sr.last_scan_at' : '') . ' ' . $sqlBase;
        if ($joinSSL) { $sql .= ' LEFT JOIN ssl_results sr ON sr.dominio = o.dominio AND sr.last_scan_at = (SELECT MAX(last_scan_at) FROM ssl_results WHERE dominio = o.dominio)'; }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        if ($sslFilters) {
            $clauses = [];
            $paramsSSL = [];
            if (isset($query['has_ssl']) && $query['has_ssl']) { $clauses[] = 'sr.id IS NOT NULL'; }
            if (!empty($query['ssl_status'])) { $clauses[] = 'sr.status = ?'; $paramsSSL[] = strtolower(trim($query['ssl_status'])); }
            if (isset($query['ssl_days_max'])) { $clauses[] = 'sr.dias_restantes <= ?'; $paramsSSL[] = (int)$query['ssl_days_max']; }
            if (isset($query['ssl_days_min'])) { $clauses[] = 'sr.dias_restantes >= ?'; $paramsSSL[] = (int)$query['ssl_days_min']; }
            if ($clauses) { $sql .= ($where ? ' AND ' : ' WHERE ') . implode(' AND ', $clauses); $params = array_merge($params, $paramsSSL); }
        }
        $sql .= ' ORDER BY o.' . $order . ' ' . $dir . ' LIMIT ? OFFSET ?';
        $params2 = array_merge($params, [$limit, $offset]);
        $res = Database::query($sql, $params2);
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) { $r['esfera'] = HelperOrg::classifyEsfera($r['tipo'] ?? null, $r['nome'] ?? ''); $rows[] = $r; } }
        $countSql = 'SELECT COUNT(*) c ' . $sqlBase . ($joinSSL ? ' LEFT JOIN ssl_results sr ON sr.dominio = o.dominio AND sr.last_scan_at = (SELECT MAX(last_scan_at) FROM ssl_results WHERE dominio = o.dominio)' : '');
        $paramsCount = $params;
        if ($where || $sslFilters) {
            $countClauses = [];
            if ($where) { $countClauses[] = implode(' AND ', $where); }
            if ($sslFilters) {
                $clauses = [];
                if (isset($query['has_ssl']) && $query['has_ssl']) { $clauses[] = 'sr.id IS NOT NULL'; }
                if (!empty($query['ssl_status'])) { $clauses[] = 'sr.status = ?'; $paramsCount[] = strtolower(trim($query['ssl_status'])); }
                if (isset($query['ssl_days_max'])) { $clauses[] = 'sr.dias_restantes <= ?'; $paramsCount[] = (int)$query['ssl_days_max']; }
                if (isset($query['ssl_days_min'])) { $clauses[] = 'sr.dias_restantes >= ?'; $paramsCount[] = (int)$query['ssl_days_min']; }
                if ($clauses) { $countClauses[] = implode(' AND ', $clauses); }
            }
            if ($countClauses) { $countSql .= ' WHERE ' . implode(' AND ', $countClauses); }
        }
        $resCount = Database::query($countSql, $paramsCount);
        $rowCount = $resCount ? $resCount->fetch_assoc() : ['c' => 0];
        Utils::json(['items' => $rows, 'total' => (int)$rowCount['c']]);
        break;

    case '/api/orgaos/list':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $order = isset($query['order_by']) ? strtolower($query['order_by']) : 'created_at';
        $dir = isset($query['order_dir']) ? strtoupper($query['order_dir']) : 'DESC';
        if (!in_array($order, ['created_at','nome','uf','tipo'], true)) $order = 'created_at';
        if ($dir !== 'ASC' && $dir !== 'DESC') $dir = 'DESC';
        $res = Database::query('SELECT id, tipo, nome, uf, dominio, site, status, created_at FROM orgaos ORDER BY ' . $order . ' ' . $dir . ' LIMIT ? OFFSET ?', [$limit, $offset]);
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; }
        Utils::json(['items' => $rows]);
        break;

    case '/api/crm/oportunidades':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $where = [];
        $params = [];
        if (!empty($query['status'])) { $where[] = 'status = ?'; $params[] = $query['status']; }
        $sql = 'SELECT id, orgao_id, titulo, descricao, status, prioridade, origem, created_at FROM crm_oportunidades';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $res = Database::query($sql, $params);
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; }
        $countSql = 'SELECT COUNT(*) c FROM crm_oportunidades';
        $paramsCount = [];
        if ($where) { $countSql .= ' WHERE ' . implode(' AND ', $where); $paramsCount = $params; array_pop($paramsCount); array_pop($paramsCount); }
        $resCount = Database::query($countSql, $paramsCount);
        $rowCount = $resCount ? $resCount->fetch_assoc() : ['c'=>0];
        Utils::json(['items' => $rows, 'total' => (int)$rowCount['c'], 'limit' => $limit, 'offset' => $offset]);
        break;

    case '/api/crm/contatos/add':
        Utils::requireAuth(); if(!is_array($body)){ Utils::json(['error'=>'body_required'],400); break; }
        $id = CRMContatos::add($body); Utils::json(['id'=>$id]); break;

    case '/api/crm/contatos/list':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 50; Utils::json(['items'=>CRMContatos::list($limit)]); break;

    case '/api/crm/contatos/get':
        Utils::requireQuery($query,['id']); Utils::json(['item'=>CRMContatos::get((int)$query['id'])]); break;

    case '/api/crm/contatos/update':
        Utils::requireAuth(); Utils::requireQuery($query,['id']); if(!is_array($body)){ Utils::json(['error'=>'body_required'],400); break; }
        $ok = CRMContatos::update((int)$query['id'],$body); Utils::json(['ok'=>$ok]); break;

    case '/api/crm/oportunidades/create_auto':
        Utils::requireAuth(); if(!is_array($body)){ Utils::json(['error'=>'body_required'],400); break; }
        Utils::json(CRMAuto::createAuto($body)); break;

    case '/api/crm/agenda/create_auto':
        Utils::requireAuth(); if(!is_array($body)){ Utils::json(['error'=>'body_required'],400); break; }
        Utils::json(CRMAgenda::createAuto($body)); break;

    case '/api/ssl/latest':
        Utils::requireQuery($query, ['domain']);
        $hasCap = Database::query("SHOW COLUMNS FROM ssl_results LIKE 'capturado_em'");
        $orderCol = ($hasCap && $hasCap->num_rows > 0) ? 'capturado_em' : 'last_scan_at';
        $res = Database::query('SELECT * FROM ssl_results WHERE dominio=? ORDER BY ' . $orderCol . ' DESC LIMIT 1', [$query['domain']]);
        $row = $res ? $res->fetch_assoc() : null;
        Utils::json(['item' => $row]);
        break;

        case '/api/ssl/scans/list':
            $limit = isset($query['limit']) ? (int)$query['limit'] : 50;
            $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
            $sql = "SELECT sr.dominio, sr.issuer, sr.cn, sr.valid_from, sr.valid_to, sr.dias_restantes, sr.status, sr.last_scan_at, o.id AS orgao_id, o.nome, o.uf FROM ssl_results sr LEFT JOIN orgaos o ON o.dominio = sr.dominio ORDER BY sr.last_scan_at DESC LIMIT ? OFFSET ?";
            $res = Database::query($sql, [$limit, $offset]);
            $rows = [];
            if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; }
            Utils::json(['items'=>$rows]);
            break;

    case '/api/audit/list':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 50;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $res = Database::query('SELECT id, entity_type, entity_id, action, payload_json, created_at FROM audit_events ORDER BY created_at DESC LIMIT ? OFFSET ?', [$limit, $offset]);
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; }
        Utils::json(['items'=>$rows]);
        break;

    case '/api/ssl/scan_batch':
        Utils::requireAuth(); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $domains = $body['domains'] ?? null; if (!is_array($domains) || !$domains) { Utils::json(['error'=>'domains_required'],400); break; }
        $done = []; $errors = [];
        foreach ($domains as $d) {
            $dom = is_string($d) ? trim($d) : '';
            if (!$dom) continue;
            try {
                $out = HelperSSL::scanAndPersist($dom);
                $risk = HRisco::classify($out);
                $ins = HInsights::generate($out, $risk);
                $opp = CRMAuto::createAuto(['dominio'=>$dom,'risco'=>$risk,'insights'=>$ins,'orgao_id'=>null]);
                CRMAgenda::createAuto(['dominio'=>$dom,'dias'=>$out['dias_restantes']??null,'oportunidade_id'=>$opp['id']??null,'orgao_id'=>null]);
                $done[] = ['domain'=>$dom,'ok'=>true,'dias'=>$out['dias_restantes']??null,'status'=>$out['status']??null];
            }
            catch (\Throwable $e) { $errors[] = ['domain'=>$dom,'ok'=>false]; }
        }
        Utils::json(['processed'=>count($done),'errors'=>count($errors),'items'=>$done,'failed'=>$errors]);
        break;

    case '/api/ssl/summary':
        $summary = ['total'=>0,'expired'=>0,'lte15'=>0,'lte30'=>0,'lte90'=>0,'unknown'=>0];
        $sql = "SELECT sr.status, sr.dias_restantes FROM ssl_results sr JOIN (SELECT dominio, MAX(last_scan_at) AS last FROM ssl_results GROUP BY dominio) latest ON latest.dominio = sr.dominio AND latest.last = sr.last_scan_at";
        $r = Database::query($sql);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $summary['total']++;
                $st = strtolower($x['status'] ?? 'unknown'); $dias = isset($x['dias_restantes']) ? (int)$x['dias_restantes'] : null;
                if ($dias === null || $st === 'unknown') { $summary['unknown']++; continue; }
                if ($dias < 0 || $st === 'expired') { $summary['expired']++; continue; }
                if ($dias <= 15) { $summary['lte15']++; continue; }
                if ($dias <= 30) { $summary['lte30']++; continue; }
                if ($dias <= 90) { $summary['lte90']++; continue; }
            }
        }
        Utils::json($summary);
        break;

    case '/api/ssl/expiring':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 50;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $daysMax = isset($query['days_max']) ? (int)$query['days_max'] : 90;
        $status = isset($query['status']) ? strtolower(trim($query['status'])) : null;
        $uf = isset($query['uf']) ? strtoupper(trim($query['uf'])) : null;
        $tipo = isset($query['tipo']) ? trim($query['tipo']) : null;
        $q = isset($query['q']) ? trim($query['q']) : null;
        $where = [];
        $params = [];
        $sql = 'SELECT o.id AS orgao_id, o.nome, o.uf, o.tipo, o.dominio, sr.issuer, sr.valid_to, sr.dias_restantes, sr.status FROM ssl_results sr LEFT JOIN orgaos o ON o.dominio = sr.dominio JOIN (SELECT dominio, MAX(last_scan_at) AS last FROM ssl_results GROUP BY dominio) latest ON latest.dominio = sr.dominio AND latest.last = sr.last_scan_at';
        if ($uf) { $where[] = 'o.uf = ?'; $params[] = $uf; }
        if ($tipo) { $where[] = 'o.tipo = ?'; $params[] = $tipo; }
        if ($q) { $where[] = 'o.nome LIKE ?'; $params[] = '%' . $q . '%'; }
        if ($status) { $where[] = 'sr.status = ?'; $params[] = $status; }
        if ($daysMax >= 0) { $where[] = 'sr.dias_restantes <= ?'; $params[] = $daysMax; }
        $where[] = 'sr.dias_restantes IS NOT NULL';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY sr.dias_restantes ASC, sr.valid_to ASC LIMIT ? OFFSET ?';
        $params[] = $limit; $params[] = $offset;
        $r = Database::query($sql, $params);
        $rows = [];
        if ($r) { while ($x = $r->fetch_assoc()) $rows[] = $x; }
        Utils::json(['items'=>$rows]);
        break;

    case '/api/datalake/recent':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $params = [];
        $sql = 'SELECT id, source, `key`, payload_json, created_at FROM datalake_raw';
        if (!empty($query['source'])) { $sql .= ' WHERE source = ?'; $params[] = $query['source']; }
        $sql .= ' ORDER BY created_at DESC LIMIT ?';
        $params[] = $limit;
        $res = Database::query($sql, $params);
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; }
        Utils::json(['items' => $rows]);
        break;

    case '/api/datalake/store':
        Utils::requireAuth(); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $source = $body['source'] ?? null; $key = $body['key'] ?? null; $payload = $body['payload'] ?? null;
        if (!$source || !$key || $payload === null) { Utils::json(['error'=>'missing_param'],400); break; }
        $ok = Database::execute('INSERT INTO datalake_raw(source, `key`, payload_json, created_at) VALUES(?, ?, ?, NOW())', [$source, $key, is_string($payload)?$payload:json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        Utils::json(['ok'=>$ok]);
        break;

    case '/api/orgaos/add':
        Utils::requireAuth(); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $tipo = $body['tipo'] ?? null; $nome = $body['nome'] ?? null; $uf = $body['uf'] ?? null;
        $dominio = $body['dominio'] ?? null; $site = $body['site'] ?? null; $status = $body['status'] ?? 'ativo';
        $scan = !empty($body['scan']);
        if (!$tipo || !$nome || !$uf) { Utils::json(['error'=>'missing_param'],400); break; }
        $exists = Database::query('SELECT id FROM orgaos WHERE tipo=? AND nome=? AND uf=? LIMIT 1', [$tipo, $nome, $uf]);
        $row = $exists ? $exists->fetch_assoc() : null;
        if ($row) { Utils::json(['id'=>(int)$row['id'],'ok'=>true,'duplicated'=>true]); break; }
        $ok = Database::execute('INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, created_at) VALUES(?,?,?,?,?,?,NOW())', [$tipo, $nome, $uf, $dominio, $site, $status]);
        $idRes = Database::query('SELECT LAST_INSERT_ID() id'); $idRow = $idRes ? $idRes->fetch_assoc() : ['id'=>null];
        $out = ['ok'=>$ok, 'id'=>(int)$idRow['id']];
        if ($ok && $scan && $dominio) {
            $ssl = HelperSSL::scanAndPersist($dominio);
            $risco = HRisco::classify($ssl);
            $opp = CRMAuto::createAuto(['dominio'=>$dominio,'risco'=>$risco,'insights'=>HInsights::generate($ssl,$risco),'orgao_id'=>$out['id']]);
            $out['ssl'] = $ssl; $out['oportunidade'] = $opp;
        }
        Utils::json($out);
        break;

    case '/api/ia/analyze-orgao':
        Utils::requireQuery($query, []);
        $id = isset($query['id']) ? (int)$query['id'] : 0;
        $dominio = $query['dominio'] ?? null;
        $org = null;
        if ($id) {
            $r = Database::query('SELECT id, tipo, nome, uf, dominio FROM orgaos WHERE id=?', [$id]);
            $org = $r ? $r->fetch_assoc() : null;
            if ($org) $dominio = $org['dominio'] ?? $dominio;
        }
        if (!$dominio) { Utils::json(['error'=>'dominio_required'],400); break; }
        $ssl = HelperSSL::scanAndPersist($dominio);
        $risco = HRisco::classify($ssl);
        $insights = HInsights::generate($ssl, $risco);
        $opp = null;
        if ($org && isset($org['id'])) {
            $opp = CRMAuto::createAuto(['dominio'=>$dominio,'risco'=>$risco,'insights'=>$insights,'orgao_id'=>(int)$org['id']]);
        }
        Utils::json(['ssl'=>$ssl,'risco'=>$risco,'insights'=>$insights,'oportunidade'=>$opp,'orgao'=>$org]);
        break;

    case '/api/ia/analyze-batch':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 50;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $uf = $query['uf'] ?? null;
        $tipo = $query['tipo'] ?? null;
        $where = ["dominio IS NOT NULL AND dominio <> ''"];
        $params = [];
        if ($uf) { $where[] = 'uf = ?'; $params[] = $uf; }
        if ($tipo) { $where[] = 'tipo = ?'; $params[] = $tipo; }
        $sql = 'SELECT id, nome, uf, tipo, dominio FROM orgaos WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $r = Database::query($sql, $params);
        $items = [];
        $ok = 0; $fail = 0;
        if ($r) {
            while ($o = $r->fetch_assoc()) {
                $res = HelperSSL::scanAndPersist($o['dominio']);
                $risk = HRisco::classify($res);
                $ins = HInsights::generate($res, $risk);
                CRMAuto::createAuto(['dominio'=>$o['dominio'],'risco'=>$risk,'insights'=>$ins,'orgao_id'=>(int)$o['id']]);
                $items[] = ['orgao_id'=>(int)$o['id'],'dominio'=>$o['dominio'],'ok'=>(bool)($res['ok']??false),'risco'=>$risk];
                if ($res && ($res['ok']??false)) $ok++; else $fail++;
            }
        }
        Utils::json(['processed'=>count($items),'ok'=>$ok,'fail'=>$fail,'items'=>$items,'limit'=>$limit,'offset'=>$offset]);
        break;

    case '/api/domains/resolve_missing':
        Utils::requireAuth(); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $uf = isset($body['uf']) ? strtoupper(trim($body['uf'])) : null;
        $esfera = isset($body['esfera']) ? strtolower(trim($body['esfera'])) : null;
        $limit = isset($body['limit']) ? (int)$body['limit'] : 200;
        $where = ['(dominio IS NULL OR dominio = \'\')']; $params = [];
        if ($uf) { $where[] = 'uf = ?'; $params[] = $uf; }
        if ($esfera === 'municipal') { $where[] = "(tipo IN ('prefeitura','camara_municipal','secretaria_financas','secretaria_saude','secretaria_educacao','procuradoria_municipal') OR nome LIKE ? OR nome LIKE ?)"; $params[] = '%Prefeitura%'; $params[] = '%Municipal%'; }
        else if ($esfera === 'estadual') { $where[] = "(nome LIKE ? OR nome LIKE ?)"; $params[] = '%Estado%'; $params[] = '%Estadual%'; }
        else if ($esfera === 'federal') { $where[] = "(nome LIKE ? OR nome LIKE ?)"; $params[] = '%Federal%'; $params[] = '%União%'; }
        $sql = 'SELECT id, tipo, nome, uf FROM orgaos WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT ?';
        $params2 = array_merge($params, [$limit]);
        $r = Database::query($sql, $params2);
        $updated = 0; $items = [];
        if ($r) {
            while ($o = $r->fetch_assoc()) {
                $cands = HelperOrg::generateDomainCandidates($o['tipo'] ?? null, $o['nome'] ?? '', $o['uf'] ?? '');
                $selected = null; $validated = false;
                foreach ($cands as $d) {
                    $res = HelperSSL::scanAndPersist($d);
                    $st = $res['status'] ?? null;
                    if ($st && $st !== 'unknown') { $selected = $d; $validated = true; break; }
                }
                if ($selected) {
                    Database::execute('UPDATE orgaos SET dominio=? WHERE id=? AND (dominio IS NULL OR dominio=\'\')', [$selected, (int)$o['id']]);
                    try {
                        $ssl = HelperSSL::scanAndPersist($selected);
                        $risk = HRisco::classify($ssl);
                        $ins = HInsights::generate($ssl, $risk);
                        $opp = CRMAuto::createAuto(['dominio'=>$selected,'risco'=>$risk,'insights'=>$ins,'orgao_id'=>(int)$o['id']]);
                        CRMAgenda::createAuto(['dominio'=>$selected,'dias'=>$ssl['dias_restantes']??null,'oportunidade_id'=>$opp['id']??null,'orgao_id'=>(int)$o['id']]);
                    } catch (\Throwable $e) {}
                    $items[] = ['id'=>(int)$o['id'],'dominio'=>$selected,'validated'=>$validated]; $updated++;
                }
            }
        }
        Utils::json(['updated'=>$updated,'items'=>$items]);
        break;

    case '/api/ssl/scan_criteria':
        Utils::requireAuth(); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $uf = isset($body['uf']) ? strtoupper(trim($body['uf'])) : null;
        $esfera = isset($body['esfera']) ? strtolower(trim($body['esfera'])) : null;
        $limit = isset($body['limit']) ? (int)$body['limit'] : 200;
        $where = ['dominio IS NOT NULL AND dominio <> \'\'']; $params = [];
        if ($uf) { $where[] = 'uf = ?'; $params[] = $uf; }
        if ($esfera === 'municipal') { $where[] = "(tipo IN ('prefeitura','camara_municipal','secretaria_financas','secretaria_saude','secretaria_educacao','procuradoria_municipal') OR nome LIKE ? OR nome LIKE ?)"; $params[] = '%Prefeitura%'; $params[] = '%Municipal%'; }
        else if ($esfera === 'estadual') { $where[] = "(nome LIKE ? OR nome LIKE ?)"; $params[] = '%Estado%'; $params[] = '%Estadual%'; }
        else if ($esfera === 'federal') { $where[] = "(nome LIKE ? OR nome LIKE ?)"; $params[] = '%Federal%'; $params[] = '%União%'; }
        $sql = 'SELECT id, nome, uf, tipo, dominio FROM orgaos WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT ?';
        $params2 = array_merge($params, [$limit]);
        $r = Database::query($sql, $params2);
        $processed = 0; $fails = 0; $items = [];
        if ($r) {
            while ($o = $r->fetch_assoc()) {
                try {
                    $res = HelperSSL::scanAndPersist($o['dominio']);
                    $risk = HRisco::classify($res);
                    $ins = HInsights::generate($res, $risk);
                    $opp = CRMAuto::createAuto(['dominio'=>$o['dominio'],'risco'=>$risk,'insights'=>$ins,'orgao_id'=>(int)$o['id']]);
                    CRMAgenda::createAuto(['dominio'=>$o['dominio'],'dias'=>$res['dias_restantes']??null,'oportunidade_id'=>$opp['id']??null,'orgao_id'=>(int)$o['id']]);
                    $processed++;
                    $items[] = ['orgao_id'=>(int)$o['id'],'dominio'=>$o['dominio'],'status'=>$res['status']??null,'dias'=>$res['dias_restantes']??null];
                } catch (\Throwable $e) { $fails++; }
            }
        }
        Utils::json(['processed'=>$processed,'fails'=>$fails,'items'=>$items]);
        break;

    case '/api/crm/oportunidades':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $res = Database::query('SELECT id, titulo, status, origem, prioridade, orgao_id, created_at FROM crm_oportunidades ORDER BY created_at DESC LIMIT ? OFFSET ?', [$limit, $offset]);
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; }
        Utils::json(['items' => $rows]);
        break;

    case '/api/crm/contatos':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $res = Database::query('SELECT id, nome, cargo, email, telefone, whatsapp, origem, orgao_id, created_at FROM crm_contatos ORDER BY created_at DESC LIMIT ? OFFSET ?', [$limit, $offset]);
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; }
        Utils::json(['items' => $rows]);
        break;

    case '/api/orgaos/import':
        Utils::requireAuth(); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $items = $body['items'] ?? null; if (!is_array($items)) { Utils::json(['error'=>'items_required'],400); break; }
        $inserted = 0; $skipped = 0;
        foreach ($items as $it) {
            $tipo = $it['tipo'] ?? null; $nome = $it['nome'] ?? null; $uf = $it['uf'] ?? null;
            $dominio = $it['dominio'] ?? null; $site = $it['site'] ?? null; $status = $it['status'] ?? 'ativo';
            if (!$tipo || !$nome || !$uf) { $skipped++; continue; }
            $exists = Database::query('SELECT id FROM orgaos WHERE tipo=? AND nome=? AND uf=? LIMIT 1', [$tipo, $nome, $uf]);
            $row = $exists ? $exists->fetch_assoc() : null;
            if ($row) { $skipped++; continue; }
            $ok = Database::execute('INSERT INTO orgaos(tipo, nome, uf, dominio, site, status, created_at) VALUES(?,?,?,?,?,?,NOW())', [$tipo, $nome, $uf, $dominio, $site, $status]);
            if ($ok) $inserted++; else $skipped++;
        }
        Utils::json(['inserted'=>$inserted,'skipped'=>$skipped]);
        break;

    case '/api/metrics/overview':
        $counts = [
            'prefeituras' => 0,
            'orgaos' => 0,
            'ssl_scans' => 0,
            'oportunidades' => 0,
            'auditorias' => 0,
        ];
        $r1 = Database::query('SELECT COUNT(*) c FROM prefeituras');
        if ($r1 && ($row = $r1->fetch_assoc())) $counts['prefeituras'] = (int)$row['c'];
        $r2 = Database::query('SELECT COUNT(*) c FROM orgaos');
        if ($r2 && ($row = $r2->fetch_assoc())) $counts['orgaos'] = (int)$row['c'];
        $r3 = Database::query('SELECT COUNT(*) c FROM ssl_results');
        if ($r3 && ($row = $r3->fetch_assoc())) $counts['ssl_scans'] = (int)$row['c'];
        $r4 = Database::query("SELECT COUNT(*) c FROM crm_oportunidades WHERE status='novo'");
        if ($r4 && ($row = $r4->fetch_assoc())) $counts['oportunidades'] = (int)$row['c'];
        $r5 = Database::query('SELECT COUNT(*) c FROM audit_events');
        if ($r5 && ($row = $r5->fetch_assoc())) $counts['auditorias'] = (int)$row['c'];
        Utils::json($counts);
        break;

    case '/api/metrics/by_uf':
        Utils::requireQuery($query, ['uf']);
        $uf = strtoupper(trim($query['uf']));
        $pref = 0; $org = 0; $ssl = 0; $opp = 0; $aud = 0;
        $r1 = Database::query('SELECT COUNT(*) c FROM prefeituras WHERE uf=?', [$uf]);
        if ($r1 && ($row = $r1->fetch_assoc())) $pref = (int)$row['c'];
        $r2 = Database::query('SELECT COUNT(*) c FROM orgaos WHERE uf=?', [$uf]);
        if ($r2 && ($row = $r2->fetch_assoc())) $org = (int)$row['c'];
        $r3 = Database::query('SELECT COUNT(*) c FROM ssl_results sr JOIN orgaos o ON o.dominio = sr.dominio WHERE o.uf=?', [$uf]);
        if ($r3 && ($row = $r3->fetch_assoc())) $ssl = (int)$row['c'];
        $r4 = Database::query('SELECT COUNT(*) c FROM crm_oportunidades c JOIN orgaos o ON o.id = c.orgao_id WHERE o.uf=? AND c.status="novo"', [$uf]);
        if ($r4 && ($row = $r4->fetch_assoc())) $opp = (int)$row['c'];
        $r5 = Database::query("SELECT COUNT(*) c FROM audit_events a JOIN orgaos o ON a.entity_type='orgao' AND a.entity_id = o.id WHERE o.uf=?", [$uf]);
        if ($r5 && ($row = $r5->fetch_assoc())) $aud = (int)$row['c'];
        Utils::json(['uf' => $uf, 'prefeituras' => $pref, 'orgaos' => $org, 'ssl_scans' => $ssl, 'oportunidades' => $opp, 'auditorias' => $aud]);
        break;

    case '/api/board/export':
        require_once __DIR__ . '/tools/BoardBuilder.php';
        $format = isset($query['format']) ? strtolower(trim($query['format'])) : 'json';
        $board = \Tools\BoardBuilder::getBoard();
        if ($format === 'md' || $format === 'markdown') {
            header('Content-Type: text/markdown; charset=utf-8');
            echo \Tools\BoardBuilder::toMarkdown($board);
        } else {
            Utils::json($board);
        }
        break;

    case '/api/pipelines/runs':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $params = [];
        $sql = 'SELECT id, name, status, started_at, finished_at, stats_json FROM pipelines_runs';
        if (!empty($query['name'])) { $sql .= ' WHERE name = ?'; $params[] = $query['name']; }
        $sql .= ' ORDER BY started_at DESC LIMIT ?';
        $params[] = $limit;
        $res = Database::query($sql, $params);
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; }
        Utils::json(['items' => $rows]);
        break;

    case '/api/municipios/count':
        $r = Database::query('SELECT COUNT(*) c FROM municipios');
        $row = $r ? $r->fetch_assoc() : ['c'=>0];
        Utils::json(['count' => (int)$row['c']]);
        break;

    case '/api/crm/summary':
        $o_total = 0; $o_novo = 0; $o_andamento = 0; $o_ganho = 0; $o_perdido = 0; $c_total = 0;
        $r1 = Database::query('SELECT COUNT(*) c FROM crm_oportunidades'); if ($r1 && ($row=$r1->fetch_assoc())) $o_total = (int)$row['c'];
        $r2 = Database::query("SELECT COUNT(*) c FROM crm_oportunidades WHERE status='novo'"); if ($r2 && ($row=$r2->fetch_assoc())) $o_novo = (int)$row['c'];
        $r3 = Database::query("SELECT COUNT(*) c FROM crm_oportunidades WHERE status='andamento'"); if ($r3 && ($row=$r3->fetch_assoc())) $o_andamento = (int)$row['c'];
        $r4 = Database::query("SELECT COUNT(*) c FROM crm_oportunidades WHERE status='ganho'"); if ($r4 && ($row=$r4->fetch_assoc())) $o_ganho = (int)$row['c'];
        $r5 = Database::query("SELECT COUNT(*) c FROM crm_oportunidades WHERE status='perdido'"); if ($r5 && ($row=$r5->fetch_assoc())) $o_perdido = (int)$row['c'];
        $r6 = Database::query('SELECT COUNT(*) c FROM crm_contatos'); if ($r6 && ($row=$r6->fetch_assoc())) $c_total = (int)$row['c'];
        Utils::json(['oportunidades_total'=>$o_total,'oportunidades_novo'=>$o_novo,'oportunidades_andamento'=>$o_andamento,'oportunidades_ganho'=>$o_ganho,'oportunidades_perdido'=>$o_perdido,'contatos_total'=>$c_total]);
        break;

    case '/api/usuarios/list':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $res = Database::query('SELECT id, nome, email, role, status, created_at FROM usuarios ORDER BY created_at DESC LIMIT ? OFFSET ?', [$limit, $offset]);
        $rows = []; if ($res) { while ($r=$res->fetch_assoc()) $rows[]=$r; }
        Utils::json(['items'=>$rows]);
        break;

    case '/api/usuarios/add':
        Utils::requireAuth(); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $nome = $body['nome'] ?? null; $email = $body['email'] ?? null; $senha = $body['senha'] ?? null; $role = $body['role'] ?? 'user'; $status = $body['status'] ?? 'ativo';
        if (!$nome || !$email || !$senha) { Utils::json(['error'=>'missing_param'],400); break; }
        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $ok = Database::execute('INSERT INTO usuarios(nome, email, senha_hash, role, status, created_at) VALUES(?,?,?,?,?,NOW())', [$nome,$email,$hash,$role,$status]);
        $r = Database::query('SELECT LAST_INSERT_ID() id'); $row = $r ? $r->fetch_assoc() : ['id'=>0];
        Utils::json(['ok'=>$ok,'id'=>(int)$row['id']]);
        break;

    case '/api/usuarios/update':
        Utils::requireAuth(); Utils::requireQuery($query,['id']); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $id = (int)$query['id'];
        $nome = $body['nome'] ?? null; $email = $body['email'] ?? null; $role = $body['role'] ?? null; $status = $body['status'] ?? null; $senha = $body['senha'] ?? null;
        $set = []; $params = [];
        if ($nome !== null) { $set[] = 'nome=?'; $params[] = $nome; }
        if ($email !== null) { $set[] = 'email=?'; $params[] = $email; }
        if ($role !== null) { $set[] = 'role=?'; $params[] = $role; }
        if ($status !== null) { $set[] = 'status=?'; $params[] = $status; }
        if ($senha !== null) { $set[] = 'senha_hash=?'; $params[] = password_hash($senha, PASSWORD_BCRYPT); }
        if (!$set) { Utils::json(['error'=>'no_fields'],400); break; }
        $params[] = $id;
        $ok = Database::execute('UPDATE usuarios SET ' . implode(', ', $set) . ' WHERE id=?', $params);
        Utils::json(['ok'=>$ok]);
        break;

    case '/api/usuarios/delete':
        Utils::requireAuth(); Utils::requireQuery($query,['id']);
        $ok = Database::execute('DELETE FROM usuarios WHERE id=?', [(int)$query['id']]);
        Utils::json(['ok'=>$ok]);
        break;

    case '/api/telefones/list':
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
        $res = Database::query('SELECT id, entidade_tipo, entidade_id, tipo, telefone, whatsapp, created_at FROM telefones ORDER BY created_at DESC LIMIT ? OFFSET ?', [$limit, $offset]);
        $rows = []; if ($res) { while ($r=$res->fetch_assoc()) $rows[]=$r; }
        Utils::json(['items'=>$rows]);
        break;

    case '/api/telefones/add':
        Utils::requireAuth(); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $ent_tipo = $body['entidade_tipo'] ?? null; $ent_id = isset($body['entidade_id']) ? (int)$body['entidade_id'] : null; $tipo = $body['tipo'] ?? null; $tel = $body['telefone'] ?? null; $zap = !empty($body['whatsapp']) ? 1 : 0;
        if (!$tel) { Utils::json(['error'=>'telefone_required'],400); break; }
        $ok = Database::execute('INSERT INTO telefones(entidade_tipo, entidade_id, tipo, telefone, whatsapp, created_at) VALUES(?,?,?,?,?,NOW())', [$ent_tipo,$ent_id,$tipo,$tel,$zap]);
        $r = Database::query('SELECT LAST_INSERT_ID() id'); $row = $r ? $r->fetch_assoc() : ['id'=>0];
        Utils::json(['ok'=>$ok,'id'=>(int)$row['id']]);
        break;

    case '/api/telefones/update':
        Utils::requireAuth(); Utils::requireQuery($query,['id']); if (!is_array($body)) { Utils::json(['error'=>'body_required'],400); break; }
        $id = (int)$query['id']; $tipo = $body['tipo'] ?? null; $tel = $body['telefone'] ?? null; $zap = isset($body['whatsapp']) ? (int)(!!$body['whatsapp']) : null;
        $set = []; $params = [];
        if ($tipo !== null) { $set[] = 'tipo=?'; $params[] = $tipo; }
        if ($tel !== null) { $set[] = 'telefone=?'; $params[] = $tel; }
        if ($zap !== null) { $set[] = 'whatsapp=?'; $params[] = $zap; }
        if (!$set) { Utils::json(['error'=>'no_fields'],400); break; }
        $params[] = $id;
        $ok = Database::execute('UPDATE telefones SET ' . implode(', ', $set) . ' WHERE id=?', $params);
        Utils::json(['ok'=>$ok]);
        break;

    case '/api/telefones/delete':
        Utils::requireAuth(); Utils::requireQuery($query,['id']);
        $ok = Database::execute('DELETE FROM telefones WHERE id=?', [(int)$query['id']]);
        Utils::json(['ok'=>$ok]);
        break;

    case '/api/logs/list':
        $type = isset($query['type']) ? strtolower(trim($query['type'])) : 'logs';
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $tbl = ($type === 'automacao') ? 'automacao_logs' : (($type === 'crawler') ? 'crawler_logs' : 'logs');
        $res = Database::query('SELECT id, name, level, message, created_at FROM ' . $tbl . ' ORDER BY created_at DESC LIMIT ?', [$limit]);
        $rows = []; if ($res) { while ($r=$res->fetch_assoc()) $rows[]=$r; }
        Utils::json(['items'=>$rows]);
        break;

    default:
        Utils::json(['error' => 'Endpoint não encontrado', 'path' => $uri], 404);
        break;
}

?>
        case '/api/crm/oportunidades/create':
            if ($method !== 'POST') { Utils::json(['error'=>'Método inválido'], 405); break; }
            $body = Utils::readJsonBody();
            $orgao_id = isset($body['orgao_id']) ? (int)$body['orgao_id'] : 0;
            $titulo = isset($body['titulo']) ? trim($body['titulo']) : '';
            $status = isset($body['status']) ? trim($body['status']) : 'novo';
            $origem = isset($body['origem']) ? trim($body['origem']) : 'manual';
            if ($orgao_id <= 0 || $titulo === '') { Utils::json(['error'=>'Dados inválidos'], 400); break; }
            $sql = 'INSERT INTO crm_oportunidades (orgao_id, titulo, status, origem, created_at) VALUES (?, ?, ?, ?, NOW())';
            $ok = Database::execute($sql, [$orgao_id, $titulo, $status, $origem]);
            if ($ok) {
                $id = Database::lastInsertId();
                Utils::json(['id'=>$id, 'ok'=>true]);
            } else {
                Utils::json(['error'=>'Falha ao criar oportunidade'], 500);
            }
            break;
