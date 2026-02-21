<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);
$env = load_env();
$whsec = $env['STRIPE_WEBHOOK_SECRET'] ?? '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');
function verify_sig($sigHeader, $payload, $secret) {
    if ($secret === '' || $sigHeader === '') return false;
    $parts = [];
    foreach (explode(',', $sigHeader) as $kv) { $kvp = explode('=', $kv, 2); if (count($kvp) === 2) { $parts[trim($kvp[0])] = trim($kvp[1]); } }
    if (!isset($parts['t']) || !isset($parts['v1'])) return false;
    $signed = $parts['t'] . '.' . $payload;
    $calc = hash_hmac('sha256', $signed, $secret);
    return hash_equals($parts['v1'], $calc);
}
if (!verify_sig($sigHeader, $payload, $whsec)) { http_response_code(400); echo 'invalid'; exit; }
$event = json_decode($payload, true);
if (!$event || !isset($event['type'])) { http_response_code(200); echo 'ok'; exit; }
$type = $event['type'];
if ($type === 'checkout.session.completed') {
    $data = $event['data']['object'] ?? [];
    $status = $data['payment_status'] ?? '';
    $email = strtolower(trim((string)($data['customer_email'] ?? '')));
    $name = (string)($data['metadata']['full_name'] ?? '');
    $phone = (string)($data['metadata']['phone'] ?? '');
    $stripeCustomer = (string)($data['customer'] ?? '');
    if ($status === 'paid' && $email !== '') {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $row = null;
        if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $row = $res ? $res->fetch_assoc() : null; if ($res) $res->close(); }
        $stmt->close();
        if ($row) {
            if ($stripeCustomer !== '') {
                $stmt = $db->prepare('UPDATE users SET stripe_customer_id = ?, role = CASE WHEN role IS NULL OR role = "" THEN "affiliate" ELSE role END WHERE id = ?');
                $uid = (int)$row['id'];
                $stmt->bind_param('si', $stripeCustomer, $uid);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $pwd = bin2hex(random_bytes(6));
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password_hash, email, full_name, phone, role, stripe_customer_id) VALUES (?, ?, ?, ?, ?, "affiliate", ?)');
            $stmt->bind_param('ssssss', $email, $hash, $email, $name, $phone, $stripeCustomer);
            $stmt->execute();
            $stmt->close();
        }
    }
}
http_response_code(200);
echo 'ok';