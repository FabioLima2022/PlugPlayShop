<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);
$env = load_env();
$secret = $env['STRIPE_SECRET'] ?? '';
$productId = $env['STRIPE_PRODUCT_ID'] ?? 'prod_TTdBdLSvhReZ14';
$priceEnv = $env['STRIPE_PRICE_ID'] ?? '';
$base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$name = '';
$email = '';
$phone = '';
$origin = 'affiliate';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['flash'] = ['msg'=>'Sessão expirada ou inválida.', 'type'=>'error'];
        header('Location: affiliate_register.php');
        exit;
    }
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $pwd = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');
    $origin = $_POST['origin'] ?? 'affiliate';
    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = ['msg'=>'Dados inválidos.', 'type'=>'error'];
        header('Location: affiliate_register.php');
        exit;
    }
    if ($pwd === '' || $confirm === '' || $pwd !== $confirm) {
        $_SESSION['flash'] = ['msg'=>'Senhas inválidas ou não coincidem.', 'type'=>'error'];
        header('Location: affiliate_register.php');
        exit;
    }
    $_SESSION['pending_affiliate_hash'] = password_hash($pwd, PASSWORD_DEFAULT);
    $_SESSION['pending_origin'] = $origin;
} else {
    $name = $_SESSION['pending_affiliate']['full_name'] ?? '';
    $email = $_SESSION['pending_affiliate']['email'] ?? '';
    $phone = $_SESSION['pending_affiliate']['phone'] ?? '';
    $origin = $_SESSION['pending_origin'] ?? 'affiliate';
}
if ($secret === '' || $email === '' || $name === '') {
    $_SESSION['flash'] = ['msg'=>'Configuração de pagamento ausente ou dados inválidos.', 'type'=>'error'];
    header('Location: affiliate_register.php');
    exit;
}
if (strpos($secret, 'whsec_') === 0) {
    $_SESSION['flash'] = ['msg'=>'Chave inválida: informe a API Secret (sk_test_... ou sk_live_...), não o Webhook Secret.', 'type'=>'error'];
    header('Location: affiliate_register.php');
    exit;
}
function stripe_request($method, $url, $secret, $data = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret . ':');
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // Desativar verificação SSL para ambiente local (XAMPP)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp];
}
if ($priceEnv !== '') { $priceId = $priceEnv; }
else {
    list($code, $pricesJson) = stripe_request('GET', 'https://api.stripe.com/v1/prices?product=' . urlencode($productId) . '&active=true&limit=1', $secret);
    $priceId = '';
    if ($code === 200) { $p = json_decode($pricesJson, true); if (!empty($p['data'][0]['id'])) { $priceId = $p['data'][0]['id']; } }
}
if ($priceId === '') {
    $_SESSION['flash'] = ['msg'=>'Preço do produto indisponível.', 'type'=>'error'];
    header('Location: affiliate_register.php');
    exit;
}
$root = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($root === '') { $root = '/'; }
$returnPage = $origin === 'advertiser' ? '/advertiser_register.php' : '/affiliate_register.php';
// sucesso passa pelo validador para criar usuário com o papel correto
$success = $base . $root . '/affiliate_post_payment.php?session_id={CHECKOUT_SESSION_ID}&origin=' . urlencode($origin);
// cancelamento volta para a página de origem
$cancel = $base . $root . $returnPage . '?canceled=1';
$payload = [
    'mode' => 'subscription',
    'success_url' => $success,
    'cancel_url' => $cancel,
    'customer_email' => $email,
    'metadata[full_name]' => $name,
    'metadata[phone]' => $phone,
    'line_items[0][price]' => $priceId,
    'line_items[0][quantity]' => 1
];
list($code2, $sessionJson) = stripe_request('POST', 'https://api.stripe.com/v1/checkout/sessions', $secret, $payload);
if ($code2 !== 200) {
    $err = json_decode($sessionJson, true);
    $msg = $err['error']['message'] ?? 'Erro desconhecido';
    error_log("Stripe Checkout Error: " . $sessionJson);
    $_SESSION['flash'] = ['msg'=>'Falha ao criar sessão de pagamento: ' . $msg, 'type'=>'error'];
    header('Location: ' . $returnPage);
    exit;
}
$session = json_decode($sessionJson, true);
if (empty($session['url'])) {
    $_SESSION['flash'] = ['msg'=>'Sessão de pagamento indisponível.', 'type'=>'error'];
    header('Location: affiliate_register.php');
    exit;
}
header('Location: ' . $session['url']);
exit;
