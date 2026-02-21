<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);

$msg = '';
$type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $msg = 'Sessão expirada ou inválida. Tente novamente.';
        $type = 'error';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'E-mail inválido.';
            $type = 'error';
        } else {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = null;
            if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $row = $res ? $res->fetch_assoc() : null; if ($res) $res->close(); }
            $stmt->close();
            if (!$row) {
                $msg = 'Usuário não encontrado.';
                $type = 'error';
            } else {
                $token = bin2hex(random_bytes(32));
                $expires = (new DateTime('now'))->modify('+30 minutes')->format('Y-m-d H:i:s');
                $uid = (int)$row['id'];
                $stmt = $db->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
                $stmt->bind_param('iss', $uid, $token, $expires);
                $stmt->execute();
                $stmt->close();
                $resetLink = 'affiliate_reset.php?token=' . urlencode($token);
                $msg = 'Link de recuperação gerado. Acesse: ' . $resetLink;
                $type = 'success';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Recuperar senha • PlugPlay Shop</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand"><span class="logo">🛠️</span><span class="name">Recuperar senha</span></div>
      <nav class="actions"><a class="btn" href="affiliate_register.php">Voltar</a></nav>
    </div>
  </header>
  <main class="container">
    <h1>Recuperação de senha</h1>
    <?php if ($msg): ?><div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post" class="form" style="max-width:420px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <div class="form-row"><label>E-mail</label><input type="email" name="email" required /></div>
      <div class="form-actions"><button class="btn primary" type="submit">Gerar link</button></div>
    </form>
  </main>
</body>
</html>