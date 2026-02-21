<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);
$env = load_env();
$secret = $env['STRIPE_SECRET'] ?? '';
$sid = $_GET['session_id'] ?? '';
$origin = $_GET['origin'] ?? 'affiliate';
function stripe_request($method, $url, $secret, $data = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret . ':');
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp];
}
if ($secret === '' || $sid === '') {
    $_SESSION['flash'] = ['msg'=>'Pagamento inválido ou expirado.', 'type'=>'error'];
    header('Location: ' . ($origin === 'advertiser' ? 'advertiser_register.php' : 'affiliate_register.php'));
    exit;
}
if (strpos($secret, 'whsec_') === 0) {
    $_SESSION['flash'] = ['msg'=>'Chave inválida: informe a API Secret (sk_test_... ou sk_live_...), não o Webhook Secret.', 'type'=>'error'];
    header('Location: ' . ($origin === 'advertiser' ? 'advertiser_register.php' : 'affiliate_register.php'));
    exit;
}
list($code, $json) = stripe_request('GET', 'https://api.stripe.com/v1/checkout/sessions/' . urlencode($sid), $secret);
if ($code !== 200) {
    $_SESSION['flash'] = ['msg'=>'Falha ao validar pagamento.', 'type'=>'error'];
    header('Location: ' . ($origin === 'advertiser' ? 'advertiser_register.php' : 'affiliate_register.php'));
    exit;
}
$data = json_decode($json, true);
if (($data['payment_status'] ?? '') !== 'paid') {
    $_SESSION['flash'] = ['msg'=>'Pagamento não confirmado.', 'type'=>'error'];
    header('Location: affiliate_register.php');
    exit;
}
$email = strtolower(trim((string)($data['customer_email'] ?? '')));
$name = (string)($data['metadata']['full_name'] ?? '');
$phone = (string)($data['metadata']['phone'] ?? '');
if ($email === '') {
    $_SESSION['flash'] = ['msg'=>'E-mail não disponível.', 'type'=>'error'];
    header('Location: affiliate_register.php');
    exit;
}
$stmt = $db->prepare('SELECT id, role FROM users WHERE email = ? OR username = ? LIMIT 1');
$stmt->bind_param('ss', $email, $email);
$stmt->execute();
$row = null;
if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $row = $res ? $res->fetch_assoc() : null; if ($res) $res->close(); }
$stmt->close();
if ($row) {
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['username'] = $email;
    $_SESSION['role'] = $origin === 'advertiser' ? 'advertiser' : 'affiliate';
    header('Location: ' . ($origin === 'advertiser' ? 'classifieds.php' : 'affiliate_products.php'));
    exit;
}
$hash = isset($_SESSION['pending_affiliate_hash']) && is_string($_SESSION['pending_affiliate_hash']) ? $_SESSION['pending_affiliate_hash'] : password_hash(bin2hex(random_bytes(6)), PASSWORD_DEFAULT);
$roleVal = ($origin === 'advertiser') ? 'advertiser' : 'affiliate';
$stmt = $db->prepare('INSERT INTO users (username, password_hash, email, full_name, phone, role) VALUES (?, ?, ?, ?, ?, "' . $roleVal . '")');
$stmt->bind_param('sssss', $email, $hash, $email, $name, $phone);
$ok = $stmt->execute();
$stmt->close();
if (!$ok) {
    $_SESSION['flash'] = ['msg'=>'Falha ao criar conta após pagamento.', 'type'=>'error'];
    header('Location: affiliate_register.php');
    exit;
}
$_SESSION['user_id'] = (int)$db->insert_id;
$_SESSION['username'] = $email;
$_SESSION['role'] = $origin === 'advertiser' ? 'advertiser' : 'affiliate';
unset($_SESSION['pending_affiliate_hash']);
unset($_SESSION['pending_affiliate']);
header('Location: ' . ($origin === 'advertiser' ? 'classifieds.php' : 'affiliate_products.php'));
exit;
