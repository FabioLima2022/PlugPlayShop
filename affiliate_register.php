<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);
$autoRefresh = false;

$msg = '';
$type = 'info';
if (isset($_GET['canceled'])) { $msg = 'Pagamento cancelado.'; $type = 'error'; }
if (isset($_SESSION['flash'])) {
  if (is_array($_SESSION['flash'])) { $msg = (string)($_SESSION['flash']['msg'] ?? ''); $type = (string)($_SESSION['flash']['type'] ?? 'info'); }
  else { $msg = (string)$_SESSION['flash']; $type = 'info'; }
  unset($_SESSION['flash']);
}
// Após pagamento (redirecionamento de sucesso do Stripe)
if (isset($_GET['paid'])) {
    $email = strtolower(trim($_SESSION['pending_affiliate']['email'] ?? ''));
    if ($email !== '') {
        $stmt = $db->prepare('SELECT id, stripe_customer_id FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $row = null;
        if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $row = $res ? $res->fetch_assoc() : null; if ($res) $res->close(); }
        $stmt->close();
        if ($row && !empty($row['stripe_customer_id'])) {
            $_SESSION['user_id'] = (int)$row['id'];
            $_SESSION['username'] = $email;
            $_SESSION['role'] = 'affiliate';
            header('Location: affiliate_products.php');
            exit;
        } else {
            $msg = 'Pagamento realizado. Aguarde alguns segundos e recarregue esta página.';
            $type = 'info';
            $autoRefresh = true;
        }
    } else {
        $msg = 'Pagamento realizado. Faça login para continuar.';
        $type = 'info';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $msg = 'Sessão expirada ou inválida. Tente novamente.';
        $type = 'error';
    } else {
        $name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm'] ?? '');
        if ($name === '' || $email === '' || $password === '' || $confirm === '') {
            $msg = 'Preencha todos os campos obrigatórios.';
            $type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'E-mail inválido.';
            $type = 'error';
        } elseif ($password !== $confirm) {
            $msg = 'As senhas não coincidem.';
            $type = 'error';
        } else {
            $_SESSION['pending_affiliate'] = ['full_name'=>$name,'email'=>$email,'phone'=>$phone];
            header('Location: stripe_checkout.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Cadastro de Anunciante • PlugPlay Shop</title>
  <?php if ($autoRefresh) { echo '<meta http-equiv="refresh" content="6">'; } ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand">
        <span class="logo">🤝</span>
        <span class="name">Afiliado</span>
      </div>
      <nav class="actions">
        <a class="btn" href="index.php">Voltar à loja</a>
        <a class="btn" href="affiliate_login.php">Entrar como afiliado</a>
        <a class="btn" href="affiliate_forgot.php">Recuperar senha</a>
      </nav>
    </div>
  </header>
  <main class="container">
    <h1>Cadastre-se como anunciante e comece a vender.</h1>
    <p class="desc">Valor do produto: <strong>R$ 3,50</strong></p>
    <?php if ($msg): ?><div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div style="margin:10px 0;">
      <a class="btn" href="https://wa.me/5561999884002" target="_blank" rel="noopener">Dúvidas ou erro? Fale pelo WhatsApp: (61) 99988-4002</a>
    </div>
    <form method="post" action="stripe_checkout.php" class="form" style="max-width:520px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <input type="hidden" name="origin" value="affiliate" />
      <div class="form-row">
        <label>Nome completo</label>
        <input type="text" name="full_name" required />
      </div>
      <div class="form-row">
        <label>E-mail</label>
        <input type="email" name="email" required />
      </div>
      <div class="form-row">
        <label>Celular</label>
        <input type="text" name="phone" placeholder="(DDD) 90000-0000" />
      </div>
      <div class="form-row">
        <label>Senha</label>
        <input type="password" name="password" required />
      </div>
      <div class="form-row">
        <label>Confirmar senha</label>
        <input type="password" name="confirm" required />
      </div>
      <div class="form-actions">
        <button class="btn primary" type="submit">Cadastrar</button>
      </div>
    </form>
    <?php $envVars = load_env(); $pk = $envVars['STRIPE_PUBLISHABLE_KEY'] ?? ''; $buyId = $envVars['STRIPE_BUY_BUTTON_ID'] ?? ''; $payLink = $envVars['STRIPE_PAYMENT_LINK_URL'] ?? ''; if ($pk !== '' && $buyId !== ''): ?>
      <section style="margin-top:20px;">
        <h2>Pagamento rápido</h2>
        <p class="desc">Se preferir, você pode concluir o pagamento pelo botão do Stripe abaixo.</p>
        <script async src="https://js.stripe.com/v3/buy-button.js"></script>
        <stripe-buy-button buy-button-id="<?= htmlspecialchars($buyId) ?>" publishable-key="<?= htmlspecialchars($pk) ?>"></stripe-buy-button>
      </section>
    <?php endif; if ($payLink !== ''): ?>
      <section style="margin-top:10px;">
        <a class="btn" href="<?= htmlspecialchars($payLink) ?>" target="_blank">Pagar via Link Stripe</a>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
