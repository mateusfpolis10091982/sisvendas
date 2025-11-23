<?php
class Database {
    private static $conn = null;
    public static function connect(): void {
        if (self::$conn) return;
        try { 
            if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
            $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($mysqli && !$mysqli->connect_errno) { $mysqli->set_charset('utf8mb4'); self::$conn = $mysqli; }
            else {
                $errno = $mysqli ? $mysqli->connect_errno : null; $err = $mysqli ? $mysqli->connect_error : null;
                if ((defined('APP_ENV') && APP_ENV === 'dev') && $errno === 1049) {
                    try {
                        $tmp = @new mysqli(DB_HOST, DB_USER, DB_PASS);
                        if ($tmp && !$tmp->connect_errno) {
                            @$tmp->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                            @$tmp->close();
                            $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                            if ($mysqli && !$mysqli->connect_errno) { $mysqli->set_charset('utf8mb4'); self::$conn = $mysqli; return; }
                            $errno = $mysqli ? $mysqli->connect_errno : $errno; $err = $mysqli ? $mysqli->connect_error : $err;
                        }
                    } catch (\Throwable $e) {}
                }
                try { @file_put_contents((defined('LOG_DIR')?LOG_DIR:__DIR__) . '/db_connect_error.log', '[' . date('c') . "] errno=" . ($errno ?? 'n/a') . " msg=" . ($err ?? 'n/a') . " host=" . DB_HOST . " user=" . DB_USER . " name=" . DB_NAME . "\n", FILE_APPEND); } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) { self::$conn = null; }
    }
    public static function isConnected(): bool { self::connect(); return self::$conn instanceof mysqli; }
    public static function query(string $sql, array $params = []) {
        self::connect(); if (!self::$conn) return null; if (!$params) return @self::$conn->query($sql);
        try {
            $stmt = self::$conn->prepare($sql); if (!$stmt) return null; $types=''; $bind=[];
            foreach ($params as $p) { $types .= is_int($p)?'i':(is_float($p)?'d':'s'); $bind[]=$p; }
            $stmt->bind_param($types, ...$bind); if (!$stmt->execute()) return null; return $stmt->get_result();
        } catch (\Throwable $e) { return null; }
    }
    public static function execute(string $sql, array $params = []): bool {
        self::connect(); if (!self::$conn) return false; if (!$params) return @self::$conn->query($sql) === true;
        try {
            $stmt = self::$conn->prepare($sql); if (!$stmt) return false; $types=''; $bind=[];
            foreach ($params as $p) { $types .= is_int($p)?'i':(is_float($p)?'d':'s'); $bind[]=$p; }
            $stmt->bind_param($types, ...$bind); return $stmt->execute();
        } catch (\Throwable $e) { return false; }
    }
    public static function initSchema(): void {
        self::connect(); if (!self::$conn) return;
        $ddl = [
            "CREATE TABLE IF NOT EXISTS prefeituras (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255), uf CHAR(2), dominio VARCHAR(255), site VARCHAR(255), status VARCHAR(50), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS orgaos (id INT AUTO_INCREMENT PRIMARY KEY, tipo VARCHAR(255), nome VARCHAR(255), uf CHAR(2), dominio VARCHAR(255), site VARCHAR(255), status VARCHAR(50), esfera VARCHAR(32) NULL, municipio_id INT NULL, confianca_ia INT NULL, atualizado_em DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_orgao (tipo, nome, dominio))",
            "CREATE TABLE IF NOT EXISTS ssl_results (id INT AUTO_INCREMENT PRIMARY KEY, dominio VARCHAR(255), issuer VARCHAR(255), cn VARCHAR(255) NULL, valid_from DATETIME, valid_to DATETIME, dias_restantes INT, status VARCHAR(50), last_scan_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS crm_oportunidades (id INT AUTO_INCREMENT PRIMARY KEY, orgao_id INT NOT NULL, titulo VARCHAR(255) NOT NULL, descricao TEXT NULL, status VARCHAR(32) NOT NULL DEFAULT 'novo', prioridade VARCHAR(32) NULL, origem VARCHAR(64) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_opp_org (orgao_id))",
            "CREATE TABLE IF NOT EXISTS datalake_raw (id INT AUTO_INCREMENT PRIMARY KEY, source VARCHAR(64) NOT NULL, `key` VARCHAR(255) NOT NULL, payload_json TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_dl_source (source))",
            "CREATE TABLE IF NOT EXISTS pipelines_runs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME NULL, stats_json TEXT NULL, KEY idx_pipe_name (name))",
            "CREATE TABLE IF NOT EXISTS audit_events (id INT AUTO_INCREMENT PRIMARY KEY, entity_type VARCHAR(64) NOT NULL, entity_id INT NOT NULL, action VARCHAR(64) NOT NULL, payload_json TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_audit_entity (entity_type, entity_id))",
            "CREATE TABLE IF NOT EXISTS h_queue (id INT AUTO_INCREMENT PRIMARY KEY, entity_type ENUM('orgao','municipio'), entity_id INT, status ENUM('pending','processing','done','error') DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS crm_contatos (id INT AUTO_INCREMENT PRIMARY KEY, orgao_id INT NULL, municipio_id INT NULL, nome VARCHAR(255) NOT NULL, cargo VARCHAR(255), email VARCHAR(255), telefone VARCHAR(50), whatsapp VARCHAR(50), observacoes TEXT, origem ENUM('manual','funcao_h','importacao') DEFAULT 'manual', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL)",
            "CREATE TABLE IF NOT EXISTS crm_agenda (id INT AUTO_INCREMENT PRIMARY KEY, orgao_id INT NULL, municipio_id INT NULL, contato_id INT NULL, oportunidade_id INT NULL, titulo VARCHAR(255), descricao TEXT, tipo ENUM('reuniao','followup','ligacao','retorno','visita','alerta','automatico') DEFAULT 'automatico', status ENUM('pendente','feito','cancelado') DEFAULT 'pendente', data_agendada DATETIME NOT NULL, data_conclusao DATETIME NULL, responsavel VARCHAR(255), origem ENUM('manual','funcao_h','crm','ia') DEFAULT 'funcao_h', dominio VARCHAR(255) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS ssl_scans (id INT AUTO_INCREMENT PRIMARY KEY, dominio VARCHAR(255) NOT NULL, ssl_result_id INT NULL, started_at DATETIME NOT NULL, finished_at DATETIME NULL, status VARCHAR(32) NULL, ok TINYINT(1) DEFAULT 0, meta_json TEXT NULL, KEY idx_ssl_scan_dom (dominio), KEY idx_ssl_scan_res (ssl_result_id))",
            "CREATE TABLE IF NOT EXISTS dominios (id INT AUTO_INCREMENT PRIMARY KEY, dominio VARCHAR(255) NOT NULL, tipo ENUM('orgao','prefeitura','outro') DEFAULT 'outro', orgao_id INT NULL, prefeitura_id INT NULL, status VARCHAR(32) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_dom (dominio), KEY idx_dom_org (orgao_id), KEY idx_dom_pref (prefeitura_id))",
            "CREATE TABLE IF NOT EXISTS prefeituras_new (id INT AUTO_INCREMENT PRIMARY KEY, prefeitura_id INT NULL, uid VARCHAR(64) NULL, cnpj VARCHAR(32) NULL, endereco_id INT NULL, email VARCHAR(255) NULL, telefone_id INT NULL, updated_at DATETIME NULL, KEY idx_prefnew_pref (prefeitura_id))",
            "CREATE TABLE IF NOT EXISTS prefeitura_etapas (id INT AUTO_INCREMENT PRIMARY KEY, prefeitura_id INT NOT NULL, etapa VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'pendente', concluido_at DATETIME NULL, meta_json TEXT NULL, UNIQUE KEY uk_pref_etapa (prefeitura_id, etapa))",
            "CREATE TABLE IF NOT EXISTS prefeitura_status (id INT AUTO_INCREMENT PRIMARY KEY, prefeitura_id INT NOT NULL, status VARCHAR(32) NOT NULL, obs TEXT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_pref_status_pref (prefeitura_id))",
            "CREATE TABLE IF NOT EXISTS prefeitura_relacionamento (id INT AUTO_INCREMENT PRIMARY KEY, prefeitura_id INT NOT NULL, tipo VARCHAR(64) NULL, descricao TEXT NULL, responsavel VARCHAR(255) NULL, proximo_passo DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_pr_pref (prefeitura_id))",
            "CREATE TABLE IF NOT EXISTS contatos (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, email VARCHAR(255) NULL, telefone VARCHAR(50) NULL, whatsapp VARCHAR(50) NULL, cargo VARCHAR(255) NULL, orgao_id INT NULL, prefeitura_id INT NULL, municipio_id INT NULL, origem ENUM('manual','funcao_h','importacao','crm','ia') DEFAULT 'funcao_h', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_contato_email (email), KEY idx_contato_orgao (orgao_id))",
            "CREATE TABLE IF NOT EXISTS historico (id INT AUTO_INCREMENT PRIMARY KEY, orgao_id INT NULL, prefeitura_id INT NULL, contato_id INT NULL, oportunidade_id INT NULL, resumo VARCHAR(255) NULL, descricao TEXT NULL, tipo VARCHAR(64) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_hist_orgao (orgao_id), KEY idx_hist_contato (contato_id))",
            "CREATE TABLE IF NOT EXISTS municipios (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, uf CHAR(2) NOT NULL, ibge VARCHAR(16) NULL, lat DECIMAL(10,6) NULL, lng DECIMAL(10,6) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_mun_nome_uf (nome, uf), KEY idx_mun_uf (uf))",
            "CREATE TABLE IF NOT EXISTS municipios_normalizado (id INT AUTO_INCREMENT PRIMARY KEY, municipio_id INT NOT NULL, nome VARCHAR(255) NOT NULL, uf CHAR(2) NOT NULL, ibge VARCHAR(16) NULL, lat DECIMAL(10,6) NULL, lng DECIMAL(10,6) NULL, updated_at DATETIME NULL, UNIQUE KEY uk_munnorm_mun (municipio_id))",
            "CREATE TABLE IF NOT EXISTS enderecos (id INT AUTO_INCREMENT PRIMARY KEY, entidade_tipo ENUM('orgao','prefeitura','municipio','contato') NULL, entidade_id INT NULL, logradouro VARCHAR(255) NULL, numero VARCHAR(32) NULL, complemento VARCHAR(255) NULL, bairro VARCHAR(255) NULL, cep VARCHAR(16) NULL, municipio_id INT NULL, uf CHAR(2) NULL, geo_lat DECIMAL(10,6) NULL, geo_lng DECIMAL(10,6) NULL, updated_at DATETIME NULL, KEY idx_end_entidade (entidade_tipo, entidade_id))",
            "CREATE TABLE IF NOT EXISTS automacao_logs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL, level VARCHAR(32) NULL, message TEXT NULL, context_json TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_auto_name (name))",
            "CREATE TABLE IF NOT EXISTS logs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL, level VARCHAR(32) NULL, message TEXT NULL, context_json TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_logs_name (name))",
            "CREATE TABLE IF NOT EXISTS usuarios (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, senha_hash VARCHAR(255) NOT NULL, role VARCHAR(32) NOT NULL, status VARCHAR(32) DEFAULT 'ativo', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_user_email (email))",
            "CREATE TABLE IF NOT EXISTS telefones (id INT AUTO_INCREMENT PRIMARY KEY, entidade_tipo ENUM('orgao','prefeitura','municipio','contato') NULL, entidade_id INT NULL, tipo VARCHAR(32) NULL, telefone VARCHAR(50) NOT NULL, whatsapp TINYINT(1) DEFAULT 0, observacoes TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_tel_entidade (entidade_tipo, entidade_id))",
            "CREATE TABLE IF NOT EXISTS crawler_logs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL, page_url VARCHAR(1024) NULL, status VARCHAR(32) NULL, message TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_crawl_name (name))"
        ];
        foreach ($ddl as $sql) self::execute($sql);
        self::execute("ALTER TABLE orgaos ADD COLUMN esfera VARCHAR(32) NULL");
        self::execute("ALTER TABLE orgaos ADD COLUMN municipio_id INT NULL");
        self::execute("ALTER TABLE orgaos ADD COLUMN confianca_ia INT NULL");
        self::execute("ALTER TABLE orgaos ADD COLUMN atualizado_em DATETIME NULL");
        self::execute("ALTER TABLE orgaos ADD INDEX idx_org_uf (uf)");
        self::execute("ALTER TABLE orgaos ADD INDEX idx_org_tipo (tipo)");
        self::execute("ALTER TABLE orgaos ADD INDEX idx_org_created (created_at)");
        self::execute("ALTER TABLE orgaos ADD INDEX idx_org_dom (dominio)");
        self::execute("ALTER TABLE orgaos ADD INDEX idx_org_nome (nome)");
        self::execute("ALTER TABLE orgaos ADD INDEX idx_org_esfera (esfera)");
        self::execute("ALTER TABLE orgaos ADD INDEX idx_org_municipio (municipio_id)");
        self::execute("ALTER TABLE orgaos ADD INDEX idx_org_conf (confianca_ia)");
        self::execute("ALTER TABLE prefeituras ADD INDEX idx_pref_uf (uf)");
        self::execute("ALTER TABLE ssl_results ADD INDEX idx_ssl_dom_last (dominio, last_scan_at)");
        self::execute("ALTER TABLE ssl_results ADD INDEX idx_ssl_status (status)");
        self::execute("ALTER TABLE ssl_results ADD INDEX idx_ssl_days (dias_restantes)");
        self::execute("ALTER TABLE ssl_results ADD COLUMN cn VARCHAR(255) NULL");
        self::execute("ALTER TABLE crm_oportunidades ADD COLUMN dominio VARCHAR(255) NULL");
        self::execute("ALTER TABLE crm_oportunidades ADD COLUMN risco_nivel INT NULL");
        self::execute("ALTER TABLE crm_oportunidades ADD COLUMN risco_desc VARCHAR(255) NULL");
        self::execute("ALTER TABLE crm_oportunidades ADD COLUMN follow_up_ia TEXT NULL");
        self::execute("ALTER TABLE crm_oportunidades ADD COLUMN responsavel VARCHAR(255) NULL");
        self::execute("ALTER TABLE crm_oportunidades ADD COLUMN origem ENUM('manual','funcao_h','crm','ia') DEFAULT 'funcao_h'");
        self::execute("ALTER TABLE crm_oportunidades ADD COLUMN updated_at DATETIME NULL");
    }
    public static function initSchemaSummary(): array {
        self::connect(); if (!self::$conn) return ['total'=>0,'ok'=>0,'failed'=>0,'items'=>[]];
        $stmts = [];
        $base = [
            "CREATE TABLE IF NOT EXISTS prefeituras (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255), uf CHAR(2), dominio VARCHAR(255), site VARCHAR(255), status VARCHAR(50), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS orgaos (id INT AUTO_INCREMENT PRIMARY KEY, tipo VARCHAR(255), nome VARCHAR(255), uf CHAR(2), dominio VARCHAR(255), site VARCHAR(255), status VARCHAR(50), esfera VARCHAR(32) NULL, municipio_id INT NULL, confianca_ia INT NULL, atualizado_em DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_orgao (tipo, nome, dominio))",
            "CREATE TABLE IF NOT EXISTS ssl_results (id INT AUTO_INCREMENT PRIMARY KEY, dominio VARCHAR(255), issuer VARCHAR(255), cn VARCHAR(255) NULL, valid_from DATETIME, valid_to DATETIME, dias_restantes INT, status VARCHAR(50), last_scan_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS crm_oportunidades (id INT AUTO_INCREMENT PRIMARY KEY, orgao_id INT NOT NULL, titulo VARCHAR(255) NOT NULL, descricao TEXT NULL, status VARCHAR(32) NOT NULL DEFAULT 'novo', prioridade VARCHAR(32) NULL, origem VARCHAR(64) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_opp_org (orgao_id))",
            "CREATE TABLE IF NOT EXISTS datalake_raw (id INT AUTO_INCREMENT PRIMARY KEY, source VARCHAR(64) NOT NULL, `key` VARCHAR(255) NOT NULL, payload_json TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_dl_source (source))",
            "CREATE TABLE IF NOT EXISTS pipelines_runs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME NULL, stats_json TEXT NULL, KEY idx_pipe_name (name))",
            "CREATE TABLE IF NOT EXISTS audit_events (id INT AUTO_INCREMENT PRIMARY KEY, entity_type VARCHAR(64) NOT NULL, entity_id INT NOT NULL, action VARCHAR(64) NOT NULL, payload_json TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_audit_entity (entity_type, entity_id))",
            "CREATE TABLE IF NOT EXISTS h_queue (id INT AUTO_INCREMENT PRIMARY KEY, entity_type ENUM('orgao','municipio'), entity_id INT, status ENUM('pending','processing','done','error') DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS crm_contatos (id INT AUTO_INCREMENT PRIMARY KEY, orgao_id INT NULL, municipio_id INT NULL, nome VARCHAR(255) NOT NULL, cargo VARCHAR(255), email VARCHAR(255), telefone VARCHAR(50), whatsapp VARCHAR(50), observacoes TEXT, origem ENUM('manual','funcao_h','importacao') DEFAULT 'manual', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL)",
            "CREATE TABLE IF NOT EXISTS crm_agenda (id INT AUTO_INCREMENT PRIMARY KEY, orgao_id INT NULL, municipio_id INT NULL, contato_id INT NULL, oportunidade_id INT NULL, titulo VARCHAR(255), descricao TEXT, tipo ENUM('reuniao','followup','ligacao','retorno','visita','alerta','automatico') DEFAULT 'automatico', status ENUM('pendente','feito','cancelado') DEFAULT 'pendente', data_agendada DATETIME NOT NULL, data_conclusao DATETIME NULL, responsavel VARCHAR(255), origem ENUM('manual','funcao_h','crm','ia') DEFAULT 'funcao_h', dominio VARCHAR(255) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS ssl_scans (id INT AUTO_INCREMENT PRIMARY KEY, dominio VARCHAR(255) NOT NULL, ssl_result_id INT NULL, started_at DATETIME NOT NULL, finished_at DATETIME NULL, status VARCHAR(32) NULL, ok TINYINT(1) DEFAULT 0, meta_json TEXT NULL, KEY idx_ssl_scan_dom (dominio), KEY idx_ssl_scan_res (ssl_result_id))",
            "CREATE TABLE IF NOT EXISTS dominios (id INT AUTO_INCREMENT PRIMARY KEY, dominio VARCHAR(255) NOT NULL, tipo ENUM('orgao','prefeitura','outro') DEFAULT 'outro', orgao_id INT NULL, prefeitura_id INT NULL, status VARCHAR(32) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_dom (dominio), KEY idx_dom_org (orgao_id), KEY idx_dom_pref (prefeitura_id))",
            "CREATE TABLE IF NOT EXISTS prefeituras_new (id INT AUTO_INCREMENT PRIMARY KEY, prefeitura_id INT NULL, uid VARCHAR(64) NULL, cnpj VARCHAR(32) NULL, endereco_id INT NULL, email VARCHAR(255) NULL, telefone_id INT NULL, updated_at DATETIME NULL, KEY idx_prefnew_pref (prefeitura_id))",
            "CREATE TABLE IF NOT EXISTS prefeitura_etapas (id INT AUTO_INCREMENT PRIMARY KEY, prefeitura_id INT NOT NULL, etapa VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'pendente', concluido_at DATETIME NULL, meta_json TEXT NULL, UNIQUE KEY uk_pref_etapa (prefeitura_id, etapa))",
            "CREATE TABLE IF NOT EXISTS prefeitura_status (id INT AUTO_INCREMENT PRIMARY KEY, prefeitura_id INT NOT NULL, status VARCHAR(32) NOT NULL, obs TEXT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_pref_status_pref (prefeitura_id))",
            "CREATE TABLE IF NOT EXISTS prefeitura_relacionamento (id INT AUTO_INCREMENT PRIMARY KEY, prefeitura_id INT NOT NULL, tipo VARCHAR(64) NULL, descricao TEXT NULL, responsavel VARCHAR(255) NULL, proximo_passo DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_pr_pref (prefeitura_id))",
            "CREATE TABLE IF NOT EXISTS contatos (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, email VARCHAR(255) NULL, telefone VARCHAR(50) NULL, whatsapp VARCHAR(50) NULL, cargo VARCHAR(255) NULL, orgao_id INT NULL, prefeitura_id INT NULL, municipio_id INT NULL, origem ENUM('manual','funcao_h','importacao','crm','ia') DEFAULT 'funcao_h', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_contato_email (email), KEY idx_contato_orgao (orgao_id))",
            "CREATE TABLE IF NOT EXISTS historico (id INT AUTO_INCREMENT PRIMARY KEY, orgao_id INT NULL, prefeitura_id INT NULL, contato_id INT NULL, oportunidade_id INT NULL, resumo VARCHAR(255) NULL, descricao TEXT NULL, tipo VARCHAR(64) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_hist_orgao (orgao_id), KEY idx_hist_contato (contato_id))",
            "CREATE TABLE IF NOT EXISTS municipios (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, uf CHAR(2) NOT NULL, ibge VARCHAR(16) NULL, lat DECIMAL(10,6) NULL, lng DECIMAL(10,6) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_mun_nome_uf (nome, uf), KEY idx_mun_uf (uf))",
            "CREATE TABLE IF NOT EXISTS municipios_normalizado (id INT AUTO_INCREMENT PRIMARY KEY, municipio_id INT NOT NULL, nome VARCHAR(255) NOT NULL, uf CHAR(2) NOT NULL, ibge VARCHAR(16) NULL, lat DECIMAL(10,6) NULL, lng DECIMAL(10,6) NULL, updated_at DATETIME NULL, UNIQUE KEY uk_munnorm_mun (municipio_id))",
            "CREATE TABLE IF NOT EXISTS enderecos (id INT AUTO_INCREMENT PRIMARY KEY, entidade_tipo ENUM('orgao','prefeitura','municipio','contato') NULL, entidade_id INT NULL, logradouro VARCHAR(255) NULL, numero VARCHAR(32) NULL, complemento VARCHAR(255) NULL, bairro VARCHAR(255) NULL, cep VARCHAR(16) NULL, municipio_id INT NULL, uf CHAR(2) NULL, geo_lat DECIMAL(10,6) NULL, geo_lng DECIMAL(10,6) NULL, updated_at DATETIME NULL, KEY idx_end_entidade (entidade_tipo, entidade_id))",
            "CREATE TABLE IF NOT EXISTS automacao_logs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL, level VARCHAR(32) NULL, message TEXT NULL, context_json TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_auto_name (name))",
            "CREATE TABLE IF NOT EXISTS logs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL, level VARCHAR(32) NULL, message TEXT NULL, context_json TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_logs_name (name))",
            "CREATE TABLE IF NOT EXISTS usuarios (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, senha_hash VARCHAR(255) NOT NULL, role VARCHAR(32) NOT NULL, status VARCHAR(32) DEFAULT 'ativo', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_user_email (email))",
            "CREATE TABLE IF NOT EXISTS telefones (id INT AUTO_INCREMENT PRIMARY KEY, entidade_tipo ENUM('orgao','prefeitura','municipio','contato') NULL, entidade_id INT NULL, tipo VARCHAR(32) NULL, telefone VARCHAR(50) NOT NULL, whatsapp TINYINT(1) DEFAULT 0, observacoes TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_tel_entidade (entidade_tipo, entidade_id))",
            "CREATE TABLE IF NOT EXISTS crawler_logs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL, page_url VARCHAR(1024) NULL, status VARCHAR(32) NULL, message TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_crawl_name (name))"
        ];
        foreach ($base as $sql) $stmts[] = $sql;
        $stmts[] = "ALTER TABLE orgaos ADD COLUMN esfera VARCHAR(32) NULL";
        $stmts[] = "ALTER TABLE orgaos ADD COLUMN municipio_id INT NULL";
        $stmts[] = "ALTER TABLE orgaos ADD COLUMN confianca_ia INT NULL";
        $stmts[] = "ALTER TABLE orgaos ADD COLUMN atualizado_em DATETIME NULL";
        $stmts[] = "ALTER TABLE orgaos ADD INDEX idx_org_uf (uf)";
        $stmts[] = "ALTER TABLE orgaos ADD INDEX idx_org_tipo (tipo)";
        $stmts[] = "ALTER TABLE orgaos ADD INDEX idx_org_created (created_at)";
        $stmts[] = "ALTER TABLE orgaos ADD INDEX idx_org_dom (dominio)";
        $stmts[] = "ALTER TABLE orgaos ADD INDEX idx_org_nome (nome)";
        $stmts[] = "ALTER TABLE orgaos ADD INDEX idx_org_esfera (esfera)";
        $stmts[] = "ALTER TABLE orgaos ADD INDEX idx_org_municipio (municipio_id)";
        $stmts[] = "ALTER TABLE orgaos ADD INDEX idx_org_conf (confianca_ia)";
        $stmts[] = "ALTER TABLE prefeituras ADD INDEX idx_pref_uf (uf)";
        $stmts[] = "ALTER TABLE ssl_results ADD INDEX idx_ssl_dom_last (dominio, last_scan_at)";
        $stmts[] = "ALTER TABLE ssl_results ADD INDEX idx_ssl_status (status)";
        $stmts[] = "ALTER TABLE ssl_results ADD INDEX idx_ssl_days (dias_restantes)";
        $stmts[] = "ALTER TABLE ssl_results ADD COLUMN cn VARCHAR(255) NULL";
        $stmts[] = "ALTER TABLE crm_oportunidades ADD COLUMN dominio VARCHAR(255) NULL";
        $stmts[] = "ALTER TABLE crm_oportunidades ADD COLUMN risco_nivel INT NULL";
        $stmts[] = "ALTER TABLE crm_oportunidades ADD COLUMN risco_desc VARCHAR(255) NULL";
        $stmts[] = "ALTER TABLE crm_oportunidades ADD COLUMN follow_up_ia TEXT NULL";
        $stmts[] = "ALTER TABLE crm_oportunidades ADD COLUMN responsavel VARCHAR(255) NULL";
        $stmts[] = "ALTER TABLE crm_oportunidades ADD COLUMN origem ENUM('manual','funcao_h','crm','ia') DEFAULT 'funcao_h'";
        $stmts[] = "ALTER TABLE crm_oportunidades ADD COLUMN updated_at DATETIME NULL";
        $ok = 0; $failed = 0; $items = [];
        foreach ($stmts as $sql) { $res = self::execute($sql); if ($res) { $ok++; $items[]=['sql'=>$sql,'ok'=>1]; } else { $failed++; $items[]=['sql'=>$sql,'ok'=>0]; } }
        return ['total'=>count($stmts),'ok'=>$ok,'failed'=>$failed,'items'=>$items];
    }
}
?>
