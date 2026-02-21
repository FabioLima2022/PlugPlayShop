<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
echo "DEBUG product v2\n";
echo "time=" . gmdate('c') . "\n";
$env = function_exists('load_env') ? load_env() : [];
echo "env.DB_HOST=" . ($env['DB_HOST'] ?? '') . "\n";
echo "env.DB_USER=" . ($env['DB_USER'] ?? '') . "\n";
echo "env.DB_NAME=" . ($env['DB_NAME'] ?? '') . "\n";
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
echo "id=" . $id . "\n";
$db = get_db_soft();
if (!$db) {
  echo "db.error=" . db_last_error() . "\n";
  exit;
}
echo "db.ok\n";
$p = null;
if ($id > 0) { $p = fetch_product($db, $id); }
if (!$p) {
  echo "product.not_found\n";
  exit;
}
echo "product.row=" . json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
$imgs = [];
if (!empty($p['image_urls'])) {
 $parts = explode(',', (string)$p['image_urls']);
 foreach ($parts as $q) { $q = trim($q); if ($q !== '') $imgs[] = $q; }
}
echo "images.count=" . count($imgs) . "\n";
echo "images=" . json_encode($imgs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
echo "affiliate=" . (string)($p['affiliate_url'] ?? '') . "\n";
exit;
