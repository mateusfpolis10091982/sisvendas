<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/pipelines/Orchestrator.php';
require_once __DIR__ . '/monitor_ssl/Scanner.php';
require_once __DIR__ . '/tools/BoardBuilder.php';

function usage() {
    echo "\nSisVendas CLI\n";
    echo "\nComandos:\n";
    echo "  php cli.php h [--limit=50]        Executa Função H integrada\n";
    echo "  php cli.php ssl --domain=HOST     Faz scan SSL do domínio\n";
    echo "  php cli.php seed                  Insere prefeituras mínimas\n";
    echo "  php cli.php migrate               Inicializa/atualiza o schema do banco\n";
    echo "  php cli.php enrich-orgaos         Cria órgãos adicionais (câmara, secretarias, procuradoria)\n";
    echo "\n";
}

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$cmd = $argv[0] ?? null;

if (!$cmd) { usage(); exit(0); }

// Evitar init automático; usar migrate dedicado

switch ($cmd) {
    case 'h':
        $limit = 50;
        foreach ($argv as $a) {
            if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = (int)$m[1];
        }
        $summary = Orchestrator::runFunctionH(['limit' => $limit]);
        echo json_encode(['ok' => true, 'summary' => $summary], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'enrich-orgaos':
        Database::initSchema();
        $res = Orchestrator::enrichOrgaos();
        echo json_encode(['ok' => true, 'result' => $res], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'ssl':
        $domain = null;
        foreach ($argv as $a) {
            if (preg_match('/^--domain=(.+)$/', $a, $m)) $domain = $m[1];
        }
        if (!$domain) { echo "Erro: informe --domain=HOST\n"; exit(1); }
        $res = SSLScanner::scanDomain($domain);
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'seed':
        Database::initSchema();
        $res = Orchestrator::seedPrefeiturasMinimal();
        echo json_encode(['ok' => true, 'result' => $res], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'board':
        $sub = $argv[1] ?? null;
        if ($sub === 'export') {
            $format = 'json';
            $outFile = null;
            foreach ($argv as $arg) {
                if (strpos($arg, '--format=') === 0) $format = strtolower(substr($arg, 9));
                if (strpos($arg, '--out=') === 0) $outFile = substr($arg, 6);
            }
            $board = \Tools\BoardBuilder::getBoard();
            $content = ($format === 'md' || $format === 'markdown')
                ? \Tools\BoardBuilder::toMarkdown($board)
                : json_encode($board, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($outFile) {
                file_put_contents($outFile, $content);
                fwrite(STDERR, "Board exportado em $format para $outFile\n");
            } else {
                echo $content . "\n";
            }
        } else {
            echo "Uso: php cli.php board export --format=json|md [--out=arquivo]\n";
            exit(1);
        }
        break;

    case 'migrate':
        Database::initSchema();
        echo json_encode(['ok' => true, 'message' => 'Schema verificado/criado'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        break;

    default:
        usage();
        exit(1);
}

?>
