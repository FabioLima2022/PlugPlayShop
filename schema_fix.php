<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
$env = load_env();
$tokenEnv = (string)($env['ADMIN_SEED_TOKEN'] ?? '');
$tokenReq = (string)($_GET['token'] ?? '');
$out = ['ok'=>false,'steps'=>[]];
if ($tokenEnv !== '' && $tokenReq !== $tokenEnv) { echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }
$db = get_db_soft();
if (!$db) { echo json_encode(['ok'=>false,'error'=>db_last_error()]); exit; }
$db->set_charset('utf8mb4');
$existsTable = function($name) use ($db){ $q = $db->prepare('SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'); $q->bind_param('s',$name); $q->execute(); $c=0; if (method_exists($q,'get_result')){ $r=$q->get_result(); $row=$r?$r->fetch_assoc():['c'=>0]; $c=(int)$row['c']; if($r) $r->close(); } $q->close(); return $c>0; };
$existsCol = function($table,$col) use ($db){ $q = $db->prepare('SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'); $q->bind_param('ss',$table,$col); $q->execute(); $c=0; if (method_exists($q,'get_result')){ $r=$q->get_result(); $row=$r?$r->fetch_assoc():['c'=>0]; $c=(int)$row['c']; if($r) $r->close(); } $q->close(); return $c>0; };
// ensure core tables
if (!$existsTable('users')) {
  $ok = (bool)$db->query('CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, email VARCHAR(200), full_name VARCHAR(200), phone VARCHAR(30), role VARCHAR(20) DEFAULT "admin", stripe_customer_id VARCHAR(64), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $out['steps'][] = ['create_table_users'=>$ok];
} else { $out['steps'][] = ['create_table_users'=>true]; }
if (!$existsTable('classifieds')) {
  $sql = 'CREATE TABLE classifieds (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, description TEXT, price DECIMAL(10,2) DEFAULT 0, currency VARCHAR(3) NOT NULL DEFAULT "BRL", category VARCHAR(100), subcategory VARCHAR(100), image_urls TEXT, contact_phone VARCHAR(30), contact_whatsapp VARCHAR(30), location VARCHAR(100), user_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_cls_cat (category), INDEX idx_cls_subcat (subcategory), INDEX idx_cls_price (price)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
  $ok = (bool)$db->query($sql); $out['steps'][] = ['create_table_classifieds'=>$ok];
} else { $out['steps'][] = ['create_table_classifieds'=>true]; }
if (!$existsTable('products')) {
  $sql = 'CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, description TEXT, price DECIMAL(10,2) DEFAULT 0, currency VARCHAR(3) NOT NULL DEFAULT "USD", category VARCHAR(100), subcategory VARCHAR(100), image_urls TEXT, affiliate_url VARCHAR(500), user_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
  $ok = (bool)$db->query($sql); $out['steps'][] = ['create_table_products'=>$ok];
} else { $out['steps'][] = ['create_table_products'=>true]; }
if (!$existsTable('clicks')) {
  $sql = 'CREATE TABLE clicks (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, source VARCHAR(20), ip VARCHAR(45), user_agent VARCHAR(255), referer VARCHAR(500), utm_source VARCHAR(100), utm_medium VARCHAR(100), utm_campaign VARCHAR(100), landing_path VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_clicks_product (product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
  $ok = (bool)$db->query($sql); $out['steps'][] = ['create_table_clicks'=>$ok];
} else { $out['steps'][] = ['create_table_clicks'=>true]; }
if (!$existsTable('login_attempts')) {
  $sql = 'CREATE TABLE login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100), ip VARCHAR(45), attempt_count INT DEFAULT 0, last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP, locked_until TIMESTAMP NULL DEFAULT NULL, INDEX idx_login_user_ip (username, ip), INDEX idx_login_locked (locked_until)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
  $ok = (bool)$db->query($sql); $out['steps'][] = ['create_table_login_attempts'=>$ok];
} else { $out['steps'][] = ['create_table_login_attempts'=>true]; }
if (!$existsTable('password_resets')) {
  $sql = 'CREATE TABLE password_resets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token VARCHAR(64) UNIQUE NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_pr_user (user_id), INDEX idx_pr_expires (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
  $ok = (bool)$db->query($sql); $out['steps'][] = ['create_table_password_resets'=>$ok];
} else { $out['steps'][] = ['create_table_password_resets'=>true]; }
if (!$existsCol('products','currency')) { $ok = (bool)$db->query('ALTER TABLE products ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT "USD"'); $out['steps'][] = ['add_products_currency'=>$ok]; } else { $out['steps'][] = ['add_products_currency'=>true]; }
if (!$existsCol('products','subcategory')) { $ok = (bool)$db->query('ALTER TABLE products ADD COLUMN subcategory VARCHAR(100)'); $out['steps'][] = ['add_products_subcategory'=>$ok]; } else { $out['steps'][] = ['add_products_subcategory'=>true]; }
if (!$existsCol('products','user_id')) { $ok = (bool)$db->query('ALTER TABLE products ADD COLUMN user_id INT NULL'); $out['steps'][] = ['add_products_user_id'=>$ok]; } else { $out['steps'][] = ['add_products_user_id'=>true]; }
if (!$existsCol('users','email')) { $ok = (bool)$db->query('ALTER TABLE users ADD COLUMN email VARCHAR(200)'); $out['steps'][] = ['add_users_email'=>$ok]; } else { $out['steps'][] = ['add_users_email'=>true]; }
if (!$existsCol('users','full_name')) { $ok = (bool)$db->query('ALTER TABLE users ADD COLUMN full_name VARCHAR(200)'); $out['steps'][] = ['add_users_full_name'=>$ok]; } else { $out['steps'][] = ['add_users_full_name'=>true]; }
if (!$existsCol('users','phone')) { $ok = (bool)$db->query('ALTER TABLE users ADD COLUMN phone VARCHAR(30)'); $out['steps'][] = ['add_users_phone'=>$ok]; } else { $out['steps'][] = ['add_users_phone'=>true]; }
if (!$existsCol('users','role')) { $ok = (bool)$db->query('ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT "admin"'); $out['steps'][] = ['add_users_role'=>$ok]; } else { $out['steps'][] = ['add_users_role'=>true]; }
if (!$existsCol('users','stripe_customer_id')) { $ok = (bool)$db->query('ALTER TABLE users ADD COLUMN stripe_customer_id VARCHAR(64)'); $out['steps'][] = ['add_users_stripe_customer_id'=>$ok]; } else { $out['steps'][] = ['add_users_stripe_customer_id'=>true]; }
$db->query("UPDATE users SET role='admin' WHERE (role IS NULL OR role='') AND username='admin'");
$out['ok'] = true;
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
