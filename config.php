<?php
// Configura conexão e schema do MySQL para PlugPlay Shop

if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('enable_app_debug')) { function enable_app_debug(){ $env = []; if (function_exists('load_env')) { $env = load_env(); } else { $p = __DIR__.'/.env'; if (is_file($p)) { $lines = file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); foreach ($lines as $line){ if (strpos(ltrim($line),'#')===0) continue; $parts = explode('=', $line, 2); if (count($parts)===2){ $env[$parts[0]] = $parts[1]; } } } } $dbg = isset($env['APP_DEBUG']) ? strtolower($env['APP_DEBUG']) : ''; if (in_array($dbg, ['1','true','on','yes'], true)) { ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL); } } }
if (!function_exists('app_log')) { function app_log($m){ $dir = __DIR__ . '/storage/logs'; if (!is_dir($dir)) { @mkdir($dir, 0775, true); } error_log($m); $f = $dir . '/app.log'; $fp = @fopen($f, 'ab'); if ($fp) { fwrite($fp, date('c') . ' ' . $m . "\n"); fclose($fp);} } }
set_error_handler(function($severity,$message,$file,$line){ app_log('ERR ' . $severity . ' ' . $message . ' at ' . $file . ':' . $line); });
set_exception_handler(function($e){ app_log('EXC ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()); });
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e) {
    app_log('SHUT ' . $e['message'] . ' at ' . $e['file'] . ':' . $e['line']);
    $env = function_exists('load_env') ? load_env() : [];
    $dbg = isset($env['APP_DEBUG']) ? strtolower($env['APP_DEBUG']) : '';
    $isDbg = in_array($dbg, ['1','true','on','yes'], true);
    if (!headers_sent()) { http_response_code(500); }
    if ($isDbg && !headers_sent()) {
      echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>Erro</title><link rel="stylesheet" href="assets/css/styles.css" /></head><body><main class="container"><div class="notice error">Falha interna: ' . htmlspecialchars($e['message']) . '</div></main></body></html>';
    } else if (!headers_sent()) {
      echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>Erro</title><link rel="stylesheet" href="assets/css/styles.css" /></head><body><main class="container"><div class="notice error">Falha interna ao processar a página.</div></main></body></html>';
    }
  }
});
if (!function_exists('str_starts_with')) { function str_starts_with($haystack, $needle) { return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0; } }
if (!function_exists('str_ends_with')) { function str_ends_with($haystack, $needle) { if ($needle === '') return true; $len = strlen($needle); return substr($haystack, -$len) === $needle; } }
if (!function_exists('mb_strtolower')) { function mb_strtolower($str, $enc = null) { return strtolower($str); } }
if (!function_exists('mb_substr')) { function mb_substr($str, $start, $length = null, $enc = null) { return $length !== null ? substr($str, $start, $length) : substr($str, $start); } }
if (!function_exists('random_bytes')) { function random_bytes($length) { return openssl_random_pseudo_bytes($length); } }
if (!function_exists('hash_equals')) { function hash_equals($known, $user) { if (!is_string($known) || !is_string($user)) return false; if (strlen($known) !== strlen($user)) return false; $res = 0; for ($i = 0; $i < strlen($known); $i++) { $res |= ord($known[$i]) ^ ord($user[$i]); } return $res === 0; } }

function load_env() {
    $paths = [];
    $paths[] = __DIR__ . '/.env';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : null;
    if ($docRoot) { $paths[] = $docRoot . '/.env'; }
    $paths[] = dirname(__DIR__) . '/.env';
    $vars = [];
    foreach ($paths as $envPath) {
        if (is_file($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(ltrim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = trim($parts[1]);
                    if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) { $val = substr($val, 1, -1); }
                    if (!array_key_exists($key, $vars)) { $vars[$key] = $val; }
                }
            }
        }
    }
    foreach (['DB_HOST','DB_USER','DB_PASS','DB_NAME','DB_PORT','APP_DEBUG','APP_INIT'] as $k) { $v = getenv($k); if ($v !== false && $v !== '') { $vars[$k] = $v; } }
    
    // Fallback para ambiente local se as credenciais de produção falharem
    if (!isset($vars['DB_USER']) || $vars['DB_USER'] === 'hg457f15_plugplayshop_db') {
        // Verificar se é ambiente local (XAMPP)
        if (php_sapi_name() === 'cli' || strpos(__DIR__, 'xampp') !== false || !isset($_SERVER['HTTP_HOST'])) {
            error_log("Ambiente local detectado - usando credenciais locais");
            $vars['DB_HOST'] = $vars['DB_HOST'] ?? 'localhost';
            $vars['DB_USER'] = 'root';
            $vars['DB_PASS'] = '';
            $vars['DB_NAME'] = 'plugplayshop';
            $vars['APP_INIT'] = 'true'; // Ativar criação de banco local
        }
    }
    
    return $vars;
}

// Ativa debug com base no .env após carregá-lo
enable_app_debug();

function get_db() {
    if (!class_exists('mysqli')) {
        http_response_code(500);
        die('<h2>Extensão MySQLi não disponível</h2><p>Instale/ative a extensão mysqli do PHP no servidor.</p>');
    }
    $env = load_env();
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASS'] ?? '';
    $dbName = $env['DB_NAME'] ?? 'plugplayshop';
    $port = isset($env['DB_PORT']) ? (int)$env['DB_PORT'] : (int)(ini_get('mysqli.default_port') ?: 3306);
    $init = isset($env['APP_INIT']) ? strtolower($env['APP_INIT']) : '';
    $doInit = in_array($init, ['1','true','on','yes'], true);
    $hosts = array_values(array_unique([$host, '127.0.0.1', 'localhost']));
    
    // Se localhost falhar, tentar com socket ou IP específico
    if ($host === 'localhost' || $host === '127.0.0.1') {
        $hosts[] = '::1'; // IPv6 localhost
    }
    
    // Se o usuário for root e a senha estiver vazia, tentar diferentes métodos
    if ($user === 'root' && $pass === '') {
        error_log("Usuário root sem senha detectado - tentando diferentes métodos de conexão");
    }
    $lastErr = '';
    
    // Log para debug
    error_log("Tentando conectar ao MySQL: host=$host, user=$user, db=$dbName, port=$port");
    
    foreach ($hosts as $h) {
        // Primeiro tentar conectar diretamente ao banco de dados
        $db = @new mysqli($h, $user, $pass, $dbName, $port);
        if ($db && !$db->connect_errno) {
            $db->set_charset('utf8mb4');
            error_log("Conexão MySQL bem-sucedida!");
            return $db;
        } else {
            $lastErr = $db ? $db->connect_error : 'Erro ao inicializar mysqli';
            error_log("Erro na conexão direta ao banco '$dbName': $lastErr");
            
            // Se falhar e APP_INIT estiver ativado, tentar criar o banco
            if ($doInit) {
                error_log("Tentando criar banco de dados '$dbName'...");
                $server = @new mysqli($h, $user, $pass, '', $port);
                if ($server && !$server->connect_errno) {
                    $server->set_charset('utf8mb4');
                    @$server->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $server->close();
                    
                    // Tentar novamente após criar o banco
                    $db = @new mysqli($h, $user, $pass, $dbName, $port);
                    if ($db && !$db->connect_errno) {
                        $db->set_charset('utf8mb4');
                        error_log("Conexão MySQL bem-sucedida após criar banco!");
                        return $db;
                    } else {
                        $lastErr = $db ? $db->connect_error : 'Erro ao inicializar mysqli';
                        error_log("Erro na conexão após criar banco: $lastErr");
                    }
                } else {
                    $serverErr = $server ? $server->connect_error : 'Erro ao inicializar mysqli';
                    error_log("Erro na conexão sem banco: $serverErr");
                }
            }
        }
    }
    
    http_response_code(500);
    error_log("DB final error: $lastErr");
    die('<h2>Falha ao conectar ao MySQL</h2><p>' . htmlspecialchars($lastErr) . '</p>' .
        '<p>Verifique <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code> e <code>DB_PORT</code> no <code>.env</code>.</p>');
}

function get_db_soft() {
    if (!class_exists('mysqli')) { $GLOBALS['DB_LAST_ERROR'] = 'Extensão MySQLi não disponível'; return null; }
    $env = load_env();
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASS'] ?? '';
    $dbName = $env['DB_NAME'] ?? 'plugplayshop';
    $port = isset($env['DB_PORT']) ? (int)$env['DB_PORT'] : (int)(ini_get('mysqli.default_port') ?: 3306);
    $init = isset($env['APP_INIT']) ? strtolower($env['APP_INIT']) : '';
    $doInit = in_array($init, ['1','true','on','yes'], true);
    $hosts = array_values(array_unique([$host, '127.0.0.1', 'localhost']));
    if ($host === 'localhost' || $host === '127.0.0.1') { $hosts[] = '::1'; }
    $lastErr = '';
    foreach ($hosts as $h) {
        $db = @new mysqli($h, $user, $pass, $dbName, $port);
        if ($db && !$db->connect_errno) { $db->set_charset('utf8mb4'); return $db; }
        $lastErr = $db ? $db->connect_error : 'Erro ao inicializar mysqli';
        if ($doInit) {
            $server = @new mysqli($h, $user, $pass, '', $port);
            if ($server && !$server->connect_errno) {
                $server->set_charset('utf8mb4');
                @$server->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $server->close();
                $db = @new mysqli($h, $user, $pass, $dbName, $port);
                if ($db && !$db->connect_errno) { $db->set_charset('utf8mb4'); return $db; }
                $lastErr = $db ? $db->connect_error : 'Erro ao inicializar mysqli';
            }
        }
    }
    $GLOBALS['DB_LAST_ERROR'] = $lastErr ?: 'Falha ao conectar ao MySQL';
    return null;
}

function db_last_error() { return isset($GLOBALS['DB_LAST_ERROR']) ? (string)$GLOBALS['DB_LAST_ERROR'] : ''; }

function ensure_schema($db) {
    $env = load_env();
    $init = isset($env['APP_INIT']) ? strtolower($env['APP_INIT']) : '';
    $doInit = in_array($init, ['1','true','on','yes'], true);

    if ($doInit) {
        $db->query(
            'CREATE TABLE IF NOT EXISTS classifieds (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                price DECIMAL(10,2) DEFAULT 0,
                currency VARCHAR(3) NOT NULL DEFAULT "BRL",
                category VARCHAR(100),
                subcategory VARCHAR(100),
                image_urls TEXT,
                contact_phone VARCHAR(30),
                contact_whatsapp VARCHAR(30),
                location VARCHAR(100),
                state VARCHAR(60),
                neighborhood VARCHAR(120),
                zipcode VARCHAR(20),
                user_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cls_cat (category),
                INDEX idx_cls_subcat (subcategory),
                INDEX idx_cls_price (price)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $db->query(
            'CREATE TABLE IF NOT EXISTS product_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                type VARCHAR(40) NOT NULL,
                detail VARCHAR(500) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_pa_resolved (resolved_at),
                INDEX idx_pa_product (product_id),
                CONSTRAINT fk_pa_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $db->query(
            'CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                price DECIMAL(10,2) DEFAULT 0,
                currency VARCHAR(3) NOT NULL DEFAULT "USD",
                category VARCHAR(100),
                subcategory VARCHAR(100),
                image_urls TEXT,
                affiliate_url VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $db->query(
            'CREATE TABLE IF NOT EXISTS clicks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                source VARCHAR(20),
                ip VARCHAR(45),
                user_agent VARCHAR(255),
                referer VARCHAR(500),
                utm_source VARCHAR(100),
                utm_medium VARCHAR(100),
                utm_campaign VARCHAR(100),
                landing_path VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_clicks_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        @$db->query('CREATE INDEX IF NOT EXISTS idx_clicks_product ON clicks(product_id)');
        @$db->query('CREATE INDEX IF NOT EXISTS idx_clicks_created ON clicks(created_at)');

        @$db->query('CREATE INDEX IF NOT EXISTS idx_category ON products(category)');
        @$db->query('CREATE INDEX IF NOT EXISTS idx_name ON products(name)');

        $db->query(
            'CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(200),
                full_name VARCHAR(200),
                phone VARCHAR(30),
                role VARCHAR(20) DEFAULT "admin",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $db->query(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100),
                ip VARCHAR(45),
                attempt_count INT DEFAULT 0,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                locked_until TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_login_user_ip (username, ip),
                INDEX idx_login_locked (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $res = $db->query("SELECT COUNT(*) as c FROM users WHERE username='admin'");
        $r = $res ? $res->fetch_assoc() : ['c' => 0];
        if ((int)$r['c'] === 0) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $u = 'admin';
            $stmt->bind_param('ss', $u, $hash);
            $stmt->execute();
            $stmt->close();
        }

        $res = $db->query('SELECT COUNT(*) as c FROM products');
        $row = $res ? $res->fetch_assoc() : ['c' => 0];
        if ((int)$row['c'] === 0) {
            $samples = [
                [
                    'name' => 'Fone Bluetooth Pro',
                    'description' => 'Som nítido, cancelamento de ruído e bateria de longa duração.',
                    'price' => 349.90,
                    'category' => 'Eletrônicos',
                    'images' => [
                        'https://images.unsplash.com/photo-1518444027693-0f5f39de3f4c?q=80&w=1200&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1511367461989-f85a21fda167?q=80&w=1200&auto=format&fit=crop'
                    ],
                    'affiliate' => 'https://example.com/affiliado/fone-pro'
                ],
                [
                    'name' => 'Smartwatch Active',
                    'description' => 'Monitore saúde, notificações e treinos com estilo.',
                    'price' => 599.00,
                    'category' => 'Fitness',
                    'images' => [
                        'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?q=80&w=1200&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1541532713592-79a0317b6b77?q=80&w=1200&auto=format&fit=crop'
                    ],
                    'affiliate' => 'https://example.com/affiliado/smartwatch-active'
                ],
                [
                    'name' => 'Mouse Gamer X',
                    'description' => 'Alta precisão, RGB e ergonomia para longas sessões.',
                    'price' => 249.00,
                    'category' => 'Eletrônicos',
                    'images' => [
                        'https://images.unsplash.com/photo-1544829097-3eac1049f3c3?q=80&w=1200&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?q=80&w=1200&auto=format&fit=crop'
                    ],
                    'affiliate' => 'https://example.com/affiliado/mouse-gamer-x'
                ],
                [
                    'name' => 'Caixa de Som Portátil',
                    'description' => 'Graves potentes e resistência à água para qualquer momento.',
                    'price' => 399.99,
                    'category' => 'Eletrônicos',
                    'images' => [
                        'https://images.unsplash.com/photo-1557682224-5b8590b01584?q=80&w=1200&auto=format&fit=crop'
                    ],
                    'affiliate' => 'https://example.com/affiliado/caixa-portatil'
                ],
                [
                    'name' => 'Cafeteira Compacta',
                    'description' => 'Seu café perfeito em minutos, design minimalista.',
                    'price' => 329.90,
                    'category' => 'Casa',
                    'images' => [
                        'https://images.unsplash.com/photo-1502945015378-0e284ca06ccb?q=80&w=1200&auto=format&fit=crop'
                    ],
                    'affiliate' => 'https://example.com/affiliado/cafeteira-compacta'
                ],
                [
                    'name' => 'Teclado Mecânico TKL',
                    'description' => 'Feedback tátil, switches silenciosos e construção robusta.',
                    'price' => 459.00,
                    'category' => 'Eletrônicos',
                    'images' => [
                        'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?q=80&w=1200&auto=format&fit=crop'
                    ],
                    'affiliate' => 'https://example.com/affiliado/teclado-tkl'
                ],
            ];

            $stmt = $db->prepare('INSERT INTO products (name, description, price, currency, category, image_urls, affiliate_url) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($samples as $s) {
                $imagesStr = implode(',', $s['images']);
                $cur = 'USD';
                $stmt->bind_param('ssdssss', $s['name'], $s['description'], $s['price'], $cur, $s['category'], $imagesStr, $s['affiliate']);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    $chkCur = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'currency'");
    $rowCur = $chkCur ? $chkCur->fetch_assoc() : ['c' => 0];
    if ($chkCur) { $chkCur->close(); }
    if ((int)$rowCur['c'] === 0) { @ $db->query('ALTER TABLE products ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT "USD" AFTER price'); }

    $chkSub = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'subcategory'");
    $rowSub = $chkSub ? $chkSub->fetch_assoc() : ['c' => 0];
    if ($chkSub) { $chkSub->close(); }
    if ((int)$rowSub['c'] === 0) { @ $db->query('ALTER TABLE products ADD COLUMN subcategory VARCHAR(100) AFTER category'); }

    // Users table columns migration (add if missing)
    $needUsersCols = [
        ['name'=>'email','sql'=>'ALTER TABLE users ADD COLUMN email VARCHAR(200)'],
        ['name'=>'full_name','sql'=>'ALTER TABLE users ADD COLUMN full_name VARCHAR(200)'],
        ['name'=>'phone','sql'=>'ALTER TABLE users ADD COLUMN phone VARCHAR(30)'],
        ['name'=>'role','sql'=>'ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT "admin"']
    ];
    foreach ($needUsersCols as $col) {
        $chk = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = '" . $db->real_escape_string($col['name']) . "'");
        $rowC = $chk ? $chk->fetch_assoc() : ['c' => 0];
        if ($chk) { $chk->close(); }
        if ((int)$rowC['c'] === 0) { @ $db->query($col['sql']); }
    }
    $chkStripeCust = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'stripe_customer_id'");
    $rowStripeCust = $chkStripeCust ? $chkStripeCust->fetch_assoc() : ['c' => 0];
    if ($chkStripeCust) { $chkStripeCust->close(); }
    if ((int)$rowStripeCust['c'] === 0) { @ $db->query('ALTER TABLE users ADD COLUMN stripe_customer_id VARCHAR(64)'); }
    @ $db->query("UPDATE users SET role='admin' WHERE (role IS NULL OR role='') AND username='admin'");

    $chkOwner = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'user_id'");
    $rowOwner = $chkOwner ? $chkOwner->fetch_assoc() : ['c' => 0];
    if ($chkOwner) { $chkOwner->close(); }
    if ((int)$rowOwner['c'] === 0) { @ $db->query('ALTER TABLE products ADD COLUMN user_id INT NULL AFTER affiliate_url'); }

    // Migração para video_urls (se ainda não existir)
    $chkVideoProd = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'video_urls'");
    $rowVideoProd = $chkVideoProd ? $chkVideoProd->fetch_assoc() : ['c' => 0];
    if ($chkVideoProd) { $chkVideoProd->close(); }
    if ((int)$rowVideoProd['c'] === 0) { @ $db->query('ALTER TABLE products ADD COLUMN video_urls TEXT AFTER image_urls'); }

    $chkVideoCls = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classifieds' AND COLUMN_NAME = 'video_urls'");
    $rowVideoCls = $chkVideoCls ? $chkVideoCls->fetch_assoc() : ['c' => 0];
    if ($chkVideoCls) { $chkVideoCls->close(); }
    if ((int)$rowVideoCls['c'] === 0) { @ $db->query('ALTER TABLE classifieds ADD COLUMN video_urls TEXT AFTER image_urls'); }

    // Migração para coluna state em classifieds
    $chkStateCls = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classifieds' AND COLUMN_NAME = 'state'");
    $rowStateCls = $chkStateCls ? $chkStateCls->fetch_assoc() : ['c' => 0];
    if ($chkStateCls) { $chkStateCls->close(); }
    if ((int)$rowStateCls['c'] === 0) { @ $db->query('ALTER TABLE classifieds ADD COLUMN state VARCHAR(60) AFTER location'); }

    // Migração para coluna neighborhood
    $chkNeighborhoodCls = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classifieds' AND COLUMN_NAME = 'neighborhood'");
    $rowNeighborhoodCls = $chkNeighborhoodCls ? $chkNeighborhoodCls->fetch_assoc() : ['c' => 0];
    if ($chkNeighborhoodCls) { $chkNeighborhoodCls->close(); }
    if ((int)$rowNeighborhoodCls['c'] === 0) { @ $db->query('ALTER TABLE classifieds ADD COLUMN neighborhood VARCHAR(120) AFTER state'); }

    // Migração para coluna zipcode
    $chkZipcodeCls = $db->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classifieds' AND COLUMN_NAME = 'zipcode'");
    $rowZipcodeCls = $chkZipcodeCls ? $chkZipcodeCls->fetch_assoc() : ['c' => 0];
    if ($chkZipcodeCls) { $chkZipcodeCls->close(); }
    if ((int)$rowZipcodeCls['c'] === 0) { @ $db->query('ALTER TABLE classifieds ADD COLUMN zipcode VARCHAR(20) AFTER neighborhood'); }

    $db->query(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pr_user (user_id),
            INDEX idx_pr_expires (expires_at),
            CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function fetch_products($db, $random = false) {
    $out = [];
    // cache de 5 min para ordenação aleatória
    if ($random) {
        $cacheKey = 'products_random_' . gmdate('YmdHi', floor(time() / 300));
        $cacheFile = __DIR__ . '/storage/cache/' . $cacheKey . '.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
            $json = file_get_contents($cacheFile);
            $out = json_decode($json, true);
            return $out ?: [];
        }
    }
    $order = $random ? 'RAND()' : 'created_at DESC';
    $res = $db->query('SELECT * FROM products ORDER BY ' . $order);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        $res->close();
    }
    if ($random) {
        @mkdir(dirname($cacheFile), 0775, true);
        file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    return $out;
}

function invalidate_products_cache() {
    $dir = __DIR__ . '/storage/cache';
    if (!is_dir($dir)) { return; }
    $files = glob($dir . '/products_random_*.json');
    if ($files) { foreach ($files as $f) { @unlink($f); } }
}

function fetch_product($db, $id) {
    $safeId = (int)$id;
    $res = $db->query("SELECT * FROM products WHERE id = $safeId LIMIT 1");
    if ($res) {
        $row = $res->fetch_assoc();
        $res->close();
        return $row ?: null;
    }
    return null;
}

function insert_product($db, $data) {
    try {
        // Verifica colunas existentes
        $hasOwnerCol = false;
        $hasVideoCol = false;
        $res = $db->query("SHOW COLUMNS FROM products LIKE 'user_id'");
        if ($res && $res->num_rows > 0) { $hasOwnerCol = true; }
        $res = $db->query("SHOW COLUMNS FROM products LIKE 'video_urls'");
        if ($res && $res->num_rows > 0) { $hasVideoCol = true; }

        $name = $data['name'] ?? '';
        $desc = $data['description'] ?? '';
        $price = isset($data['price']) ? (float)$data['price'] : 0.0;
        $currency = strtoupper(substr((string)($data['currency'] ?? 'USD'), 0, 3));
        if (!in_array($currency, ['USD','BRL','EUR'], true)) { $currency = 'USD'; }
        $cat = $data['category'] ?? '';
        $subcat = $data['subcategory'] ?? '';
        $images = $data['image_urls'] ?? '';
        $videos = $data['video_urls'] ?? '';
        $aff = $data['affiliate_url'] ?? '';
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;

        $sql = "INSERT INTO products (name, description, price, currency, category, subcategory, image_urls, affiliate_url";
        if ($hasVideoCol) { $sql .= ", video_urls"; }
        if ($hasOwnerCol) { $sql .= ", user_id"; }
        $sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?";
        if ($hasVideoCol) { $sql .= ", ?"; }
        if ($hasOwnerCol) { $sql .= ", ?"; }
        $sql .= ")";
        
        $stmt = $db->prepare($sql);
        if (!$stmt) { error_log("Prepare failed: " . $db->error); return false; }
        
        $types = "ssdsssss";
        $params = [$name, $desc, $price, $currency, $cat, $subcat, $images, $aff];
        
        if ($hasVideoCol) {
            $types .= "s";
            $params[] = $videos;
        }
        
        if ($hasOwnerCol) {
            $types .= "i";
            $params[] = $userId;
        }
        
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();

        if (!$ok) {
            error_log("Erro ao executar insert: " . $stmt->error);
        }

        $stmt->close();
        if ($ok) { invalidate_products_cache(); }
        return $ok;
    } catch (Exception $e) {
        error_log("Exceção ao inserir produto: " . $e->getMessage());
        return false;
    }
}

function update_product($db, $id, $data) {
    // Verifica coluna video_urls
    $hasVideoCol = false;
    $res = $db->query("SHOW COLUMNS FROM products LIKE 'video_urls'");
    if ($res && $res->num_rows > 0) { $hasVideoCol = true; }

    $sql = 'UPDATE products SET name = ?, description = ?, price = ?, currency = ?, category = ?, subcategory = ?, image_urls = ?, affiliate_url = ?';
    if ($hasVideoCol) { $sql .= ', video_urls = ?'; }
    $sql .= ' WHERE id = ?';

    $stmt = $db->prepare($sql);
    $name = $data['name'] ?? '';
    $desc = $data['description'] ?? '';
    $price = isset($data['price']) ? (float)$data['price'] : 0.0;
    $currency = strtoupper(substr((string)($data['currency'] ?? 'USD'), 0, 3));
    if (!in_array($currency, ['USD','BRL','EUR'], true)) { $currency = 'USD'; }
    $cat = $data['category'] ?? '';
    $subcat = $data['subcategory'] ?? '';
    $images = $data['image_urls'] ?? '';
    $aff = $data['affiliate_url'] ?? '';
    $videos = $data['video_urls'] ?? '';

    $types = 'ssdsssss';
    $params = [$name, $desc, $price, $currency, $cat, $subcat, $images, $aff];

    if ($hasVideoCol) {
        $types .= 's';
        $params[] = $videos;
    }
    
    $types .= 'i';
    $params[] = $id;

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) { invalidate_products_cache(); }
    return $ok;
}

function delete_product($db, $id) {
    $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) { invalidate_products_cache(); }
    return $ok;
}

// CSRF simples por sessão
function csrf_token() {
    if (!isset($_SESSION)) { session_start(); }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf($token) {
    if (!isset($_SESSION)) { session_start(); }
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// Formata preço conforme moeda
function format_money($amount, $currency) {
    $cur = strtoupper(substr($currency, 0, 3));
    switch ($cur) {
        case 'USD':
            return 'US$ ' . number_format($amount, 2, '.', ',');
        case 'EUR':
            return '€ ' . number_format($amount, 2, ',', '.');
        case 'BRL':
        default:
            return 'R$ ' . number_format($amount, 2, ',', '.');
    }
}

function normalize_uf($uf) {
    $s = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$uf));
    $s = substr($s, 0, 2);
    $allowed = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
    return in_array($s, $allowed, true) ? $s : '';
}

function normalize_cep($zip) {
    $d = preg_replace('/\D+/', '', (string)$zip);
    if (strlen($d) >= 8) { $d = substr($d, 0, 8); return substr($d, 0, 5) . '-' . substr($d, 5); }
    return $d;
}

function record_click($db, $productId, $source = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $refShort = substr($ref, 0, 490);
    $utm_source = '';
    $utm_medium = '';
    $utm_campaign = '';
    $landing_path = '';
    // Extrai UTM do referer
    if ($ref) {
        $parts = parse_url($ref);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            $utm_source = substr((string)($q['utm_source'] ?? ''), 0, 100);
            $utm_medium = substr((string)($q['utm_medium'] ?? ''), 0, 100);
            $utm_campaign = substr((string)($q['utm_campaign'] ?? ''), 0, 100);
        }
        $landing_path = isset($parts['path']) ? substr($parts['path'], 0, 250) : '';
    }
    $stmt = $db->prepare('INSERT INTO clicks (product_id, source, ip, user_agent, referer, utm_source, utm_medium, utm_campaign, landing_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issssssss', $productId, $source, $ip, $ua, $refShort, $utm_source, $utm_medium, $utm_campaign, $landing_path);
    $stmt->execute();
    $stmt->close();
}

function get_analytics($db) {
    $metrics = [
        'total_products' => 0,
        'total_clicks' => 0,
        'avg_price' => 0.0,
        'min_price' => 0.0,
        'max_price' => 0.0,
        'categories' => [],
        'top_products' => [],
        'recent_clicks' => [],
    ];

    if (!$db) { return $metrics; }

    try {
        // Totais e preço
        if ($res = $db->query('SELECT COUNT(*) as c, AVG(price) as avgp, MIN(price) as minp, MAX(price) as maxp FROM products')) {
            $r = $res->fetch_assoc();
            $metrics['total_products'] = (int)($r['c'] ?? 0);
            $metrics['avg_price'] = (float)($r['avgp'] ?? 0);
            $metrics['min_price'] = (float)($r['minp'] ?? 0);
            $metrics['max_price'] = (float)($r['maxp'] ?? 0);
            $res->close();
        }
        if ($res = $db->query('SELECT COUNT(*) as c FROM clicks')) {
            $r = $res->fetch_assoc();
            $metrics['total_clicks'] = (int)($r['c'] ?? 0);
            $res->close();
        }

        // Categorias
        if ($res = $db->query('SELECT category, COUNT(*) as c FROM products WHERE category IS NOT NULL AND category <> "" GROUP BY category ORDER BY c DESC')) {
            while ($row = $res->fetch_assoc()) {
                $metrics['categories'][] = $row;
            }
            $res->close();
        }

        // Top produtos por cliques
        $sqlTop = 'SELECT p.id, p.name, p.category, p.price, p.currency, COUNT(c.id) as clicks
                   FROM products p LEFT JOIN clicks c ON c.product_id = p.id
                   GROUP BY p.id, p.name, p.category, p.price, p.currency
                   ORDER BY clicks DESC, p.id DESC
                   LIMIT 10';
        if ($res = $db->query($sqlTop)) {
            while ($row = $res->fetch_assoc()) {
                $metrics['top_products'][] = $row;
            }
            $res->close();
        }

        // Últimos cliques
        if ($res = $db->query('SELECT c.created_at, c.source, c.ip, c.utm_source, c.utm_medium, c.utm_campaign, c.landing_path, p.name FROM clicks c JOIN products p ON p.id = c.product_id ORDER BY c.created_at DESC LIMIT 20')) {
            while ($row = $res->fetch_assoc()) {
                $metrics['recent_clicks'][] = $row;
            }
            $res->close();
        }
    } catch (Throwable $e) {
        error_log("Erro em get_analytics: " . $e->getMessage());
    }

    return $metrics;
}
function check_affiliate_availability($url) {
    $url = (string)$url;
    $out = ['ok'=>false,'code'=>0,'reason'=>''];
    if ($url === '') return $out;
    $code = 0; $body = '';
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $body = (string)curl_exec($ch);
            curl_close($ch);
        }
    } else {
        $ctx = stream_context_create(['http'=>['method'=>'HEAD','timeout'=>3,'follow_location'=>1]]);
        $headers = @get_headers($url, 1, $ctx);
        if (is_array($headers)) {
            foreach ($headers as $h) {
                if (is_string($h) && preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $h, $m)) { $code = (int)$m[1]; }
            }
        }
        if ($code === 200) {
        // HEAD request was successful, but we need to check the body for "out of stock" text
        // Many sites return 200 OK even for out of stock pages, so we must fetch body
        $ctx = stream_context_create(['http'=>['method'=>'GET','timeout'=>3,'follow_location'=>1]]);
        $body = @file_get_contents($url, false, $ctx);
        $body = is_string($body) ? $body : '';
    } else {
        // Try GET if HEAD failed or gave non-200 (some servers block HEAD)
        $ctx = stream_context_create(['http'=>['method'=>'GET','timeout'=>3,'follow_location'=>1]]);
        $headers = @get_headers($url, 1, $ctx);
        if (is_array($headers)) {
            foreach ($headers as $h) {
                if (is_string($h) && preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $h, $m)) { $code = (int)$m[1]; }
            }
        }
        $body = @file_get_contents($url, false, $ctx);
        $body = is_string($body) ? $body : '';
    }
    }
    
    // Final check
    if (isset($code) && $code >= 400 && $code < 600) { $out['reason'] = 'http:' . $code; return $out; }
    
    // Check content keywords
    $text = mb_strtolower(strip_tags((string)$body));
    $bad = ['sem estoque','indisponível','indisponivel','out of stock','esgotado','não disponível','nao disponivel','sold out'];
    foreach ($bad as $b) { 
        if ($text !== '' && strpos($text, $b) !== false) { 
            $out['reason'] = 'content:' . $b; 
            return $out; 
        } 
    }
    $out['ok'] = true;
    return $out;
}
function record_product_alert($db, $productId, $type, $detail) {
    $stmt = $db->prepare('INSERT INTO product_alerts (product_id, type, detail) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $productId, $type, $detail);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
function fetch_unresolved_alerts($db, $limit = 20) {
    $limit = max(1, min(200, (int)$limit));
    $sql = 'SELECT a.id, a.product_id, a.type, a.detail, a.created_at, p.name FROM product_alerts a LEFT JOIN products p ON p.id = a.product_id WHERE a.resolved_at IS NULL ORDER BY a.created_at DESC LIMIT ' . $limit;
    $out = [];
    if ($res = $db->query($sql)) { while ($row = $res->fetch_assoc()) { $out[] = $row; } $res->close(); }
    return $out;
}
function count_unresolved_alerts($db) {
    $total = 0;
    if ($res = $db->query('SELECT COUNT(*) as c FROM product_alerts WHERE resolved_at IS NULL')) { $r = $res->fetch_assoc(); $total = (int)$r['c']; $res->close(); }
    return $total;
}
function resolve_alert($db, $id) {
    $stmt = $db->prepare('UPDATE product_alerts SET resolved_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
function product_unresolved_alert_count($db, $productId) {
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM product_alerts WHERE product_id = ? AND resolved_at IS NULL');
    $stmt->bind_param('i', $productId);
    $count = 0;
    $stmt->execute();
    if (method_exists($stmt, 'get_result')) { $r = $stmt->get_result(); $row = $r ? $r->fetch_assoc() : ['c'=>0]; $count = (int)$row['c']; if ($r) $r->close(); }
    $stmt->close();
    return $count;
}
function resolve_alerts_for_product($db, $productId) {
    $stmt = $db->prepare('UPDATE product_alerts SET resolved_at = CURRENT_TIMESTAMP WHERE product_id = ? AND resolved_at IS NULL');
    $stmt->bind_param('i', $productId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
function insert_classified($db, $data) {
    try {
        $hasVideoCol = false;
        $res = $db->query("SHOW COLUMNS FROM classifieds LIKE 'video_urls'");
        if ($res && $res->num_rows > 0) { $hasVideoCol = true; }
        $hasStateCol = false;
        $res2 = $db->query("SHOW COLUMNS FROM classifieds LIKE 'state'");
        if ($res2 && $res2->num_rows > 0) { $hasStateCol = true; }
        $hasNeighborhoodCol = false;
        $res3 = $db->query("SHOW COLUMNS FROM classifieds LIKE 'neighborhood'");
        if ($res3 && $res3->num_rows > 0) { $hasNeighborhoodCol = true; }
        $hasZipcodeCol = false;
        $res4 = $db->query("SHOW COLUMNS FROM classifieds LIKE 'zipcode'");
        if ($res4 && $res4->num_rows > 0) { $hasZipcodeCol = true; }

        $sql = 'INSERT INTO classifieds (title, description, price, currency, category, subcategory, image_urls, contact_phone, contact_whatsapp, location';
        if ($hasStateCol) { $sql .= ', state'; }
        if ($hasNeighborhoodCol) { $sql .= ', neighborhood'; }
        if ($hasZipcodeCol) { $sql .= ', zipcode'; }
        $sql .= ', user_id';
        if ($hasVideoCol) { $sql .= ', video_urls'; }
        $sql .= ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
        if ($hasStateCol) { $sql .= ', ?'; }
        if ($hasNeighborhoodCol) { $sql .= ', ?'; }
        if ($hasZipcodeCol) { $sql .= ', ?'; }
        if ($hasVideoCol) { $sql .= ', ?'; }
        $sql .= ')';

        $stmt = $db->prepare($sql);
        if (!$stmt) { error_log("Erro ao preparar insert_classified: " . $db->error); return false; }
        
        $title = $data['title'] ?? '';
        $desc = $data['description'] ?? '';
        $price = isset($data['price']) ? (float)$data['price'] : 0.0;
        $currency = strtoupper(substr((string)($data['currency'] ?? 'BRL'), 0, 3));
        if (!in_array($currency, ['USD','BRL','EUR'], true)) { $currency = 'BRL'; }
        $cat = $data['category'] ?? '';
        $subcat = $data['subcategory'] ?? '';
        $images = $data['image_urls'] ?? '';
        $phone = $data['contact_phone'] ?? '';
        $wa = $data['contact_whatsapp'] ?? '';
        $loc = $data['location'] ?? '';
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $videos = $data['video_urls'] ?? '';

        $types = 'ssdsssssss';
        $params = [$title, $desc, $price, $currency, $cat, $subcat, $images, $phone, $wa, $loc];
        if ($hasStateCol) { $types .= 's'; $params[] = ($data['state'] ?? ''); }
        if ($hasNeighborhoodCol) { $types .= 's'; $params[] = ($data['neighborhood'] ?? ''); }
        if ($hasZipcodeCol) { $types .= 's'; $params[] = ($data['zipcode'] ?? ''); }
        $types .= 'i'; $params[] = $userId;

        if ($hasVideoCol) {
            $types .= 's';
            $params[] = $videos;
        }

        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        if (!$ok) { error_log("Erro insert_classified: " . $stmt->error); }
        $stmt->close();
        return $ok;
    } catch (Exception $e) {
        error_log("Exceção insert_classified: " . $e->getMessage());
        return false;
    }
}

function update_classified($db, $id, $data) {
    try {
        $hasVideoCol = false;
        $res = $db->query("SHOW COLUMNS FROM classifieds LIKE 'video_urls'");
        if ($res && $res->num_rows > 0) { $hasVideoCol = true; }
        $hasStateCol = false;
        $res2 = $db->query("SHOW COLUMNS FROM classifieds LIKE 'state'");
        if ($res2 && $res2->num_rows > 0) { $hasStateCol = true; }
        $hasNeighborhoodCol = false;
        $res3 = $db->query("SHOW COLUMNS FROM classifieds LIKE 'neighborhood'");
        if ($res3 && $res3->num_rows > 0) { $hasNeighborhoodCol = true; }
        $hasZipcodeCol = false;
        $res4 = $db->query("SHOW COLUMNS FROM classifieds LIKE 'zipcode'");
        if ($res4 && $res4->num_rows > 0) { $hasZipcodeCol = true; }

        $sql = 'UPDATE classifieds SET title = ?, description = ?, price = ?, currency = ?, category = ?, subcategory = ?, image_urls = ?, contact_phone = ?, contact_whatsapp = ?, location = ?';
        if ($hasStateCol) { $sql .= ', state = ?'; }
        if ($hasNeighborhoodCol) { $sql .= ', neighborhood = ?'; }
        if ($hasZipcodeCol) { $sql .= ', zipcode = ?'; }
        if ($hasVideoCol) { $sql .= ', video_urls = ?'; }
        $sql .= ' WHERE id = ?';

        $stmt = $db->prepare($sql);
        if (!$stmt) { error_log("Erro update_classified: " . $db->error); return false; }

        $title = $data['title'] ?? '';
        $desc = $data['description'] ?? '';
        $price = isset($data['price']) ? (float)$data['price'] : 0.0;
        $currency = strtoupper(substr((string)($data['currency'] ?? 'BRL'), 0, 3));
        if (!in_array($currency, ['USD','BRL','EUR'], true)) { $currency = 'BRL'; }
        $cat = $data['category'] ?? '';
        $subcat = $data['subcategory'] ?? '';
        $images = $data['image_urls'] ?? '';
        $phone = $data['contact_phone'] ?? '';
        $wa = $data['contact_whatsapp'] ?? '';
        $loc = $data['location'] ?? '';
        $videos = $data['video_urls'] ?? '';

        $types = 'ssdsssssss';
        $params = [$title, $desc, $price, $currency, $cat, $subcat, $images, $phone, $wa, $loc];
        if ($hasStateCol) { $types .= 's'; $params[] = ($data['state'] ?? ''); }
        if ($hasNeighborhoodCol) { $types .= 's'; $params[] = ($data['neighborhood'] ?? ''); }
        if ($hasZipcodeCol) { $types .= 's'; $params[] = ($data['zipcode'] ?? ''); }
        if ($hasVideoCol) { $types .= 's'; $params[] = $videos = ($data['video_urls'] ?? ''); }
        $types .= 'i';
        $params[] = $id;

        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    } catch (Exception $e) {
        error_log("Exceção update_classified: " . $e->getMessage());
        return false;
    }
}

function delete_classified($db, $id) {
    $stmt = $db->prepare('DELETE FROM classifieds WHERE id = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function fetch_classifieds($db, $filters = []) {
    $conds = [];
    if (!empty($filters['q'])) {
        $q = '%' . $db->real_escape_string($filters['q']) . '%';
        $conds[] = "(title LIKE '$q' OR category LIKE '$q' OR subcategory LIKE '$q')";
    }
    if (!empty($filters['user_id'])) { $uid = (int)$filters['user_id']; if ($uid > 0) { $conds[] = 'user_id = ' . $uid; } }
    if (!empty($filters['category'])) { $c = $db->real_escape_string($filters['category']); $conds[] = "category = '$c'"; }
    if (!empty($filters['subcategory'])) { $c = $db->real_escape_string($filters['subcategory']); $conds[] = "subcategory = '$c'"; }
    if (!empty($filters['currency'])) { $c = $db->real_escape_string(strtoupper(substr($filters['currency'],0,3))); $conds[] = "currency = '$c'"; }
    if (isset($filters['min_price'])) { $conds[] = 'price >= ' . (float)$filters['min_price']; }
    if (isset($filters['max_price'])) { $conds[] = 'price <= ' . (float)$filters['max_price']; }
    $where = !empty($conds) ? (' WHERE ' . implode(' AND ', $conds)) : '';
    $order = ' ORDER BY created_at DESC ';
    $limit = '';
    if (isset($filters['limit']) && isset($filters['offset'])) {
        $limit = ' LIMIT ' . (int)$filters['limit'] . ' OFFSET ' . (int)$filters['offset'];
    }
    $sql = 'SELECT * FROM classifieds' . $where . $order . $limit;
    $out = [];
    if ($res = $db->query($sql)) { while ($row = $res->fetch_assoc()) { $out[] = $row; } $res->close(); }
    return $out;
}

function count_classifieds($db, $filters = []) {
    $conds = [];
    if (!empty($filters['q'])) {
        $q = '%' . $db->real_escape_string($filters['q']) . '%';
        $conds[] = "(title LIKE '$q' OR category LIKE '$q' OR subcategory LIKE '$q')";
    }
    if (!empty($filters['user_id'])) { $uid = (int)$filters['user_id']; if ($uid > 0) { $conds[] = 'user_id = ' . $uid; } }
    if (!empty($filters['category'])) { $c = $db->real_escape_string($filters['category']); $conds[] = "category = '$c'"; }
    if (!empty($filters['subcategory'])) { $c = $db->real_escape_string($filters['subcategory']); $conds[] = "subcategory = '$c'"; }
    if (!empty($filters['currency'])) { $c = $db->real_escape_string(strtoupper(substr($filters['currency'],0,3))); $conds[] = "currency = '$c'"; }
    if (isset($filters['min_price'])) { $conds[] = 'price >= ' . (float)$filters['min_price']; }
    if (isset($filters['max_price'])) { $conds[] = 'price <= ' . (float)$filters['max_price']; }
    $where = !empty($conds) ? (' WHERE ' . implode(' AND ', $conds)) : '';
    $sql = 'SELECT COUNT(*) as c FROM classifieds' . $where;
    $total = 0;
    if ($res = $db->query($sql)) { $r = $res->fetch_assoc(); $total = (int)$r['c']; $res->close(); }
    return $total;
}
