<?php
header('Content-Type: application/json; charset=utf-8');
$out = [
  'ok' => false,
  'time' => date('c'),
  'php' => PHP_VERSION,
  'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
  'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
];
require_once __DIR__ . '/config.php';
$https = false;
if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') { $https = true; }
if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') { $https = true; }
$out['https'] = $https ? 'on' : 'off';
$db = get_db_soft();
if ($db) {
  $db->set_charset('utf8mb4');
  $rs = @$db->query('SELECT 1 as ok');
  if ($rs) { $row = $rs->fetch_assoc(); $out['db'] = ($row && (int)$row['ok'] === 1) ? 'ok' : 'fail'; $rs->close(); }
  else { $out['db'] = 'query_fail'; $out['error'] = db_last_error() ?: $db->error; }
  $out['db_info'] = (string)$db->server_info;
  $out['ok'] = ($out['db'] === 'ok');
} else {
  $out['db'] = 'down';
  $out['error'] = db_last_error();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
