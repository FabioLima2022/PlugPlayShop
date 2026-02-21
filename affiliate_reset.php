<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);

$msg = '';
$type = 'info';
$token = $_GET['token'] ?? '';
$valid = false;
$userId = null;
if ($token !== '') {
    $stmt = $db->prepare('SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at FROM password_resets pr WHERE pr.token = ? LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = null;
    if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $row = $res ? $res->fetch_assoc() : null; if ($res) $res->close(); }
    $stmt->close();
    if ($row) {
        $now = new DateTime('now');
        $exp = new DateTime($row['expires_at']);
        if (empty($row['used_at']) && $exp > $now) { $valid = true; $userId = (int)$row['user_id']; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? null)) { $msg = 'Sessão expirada.'; $type = 'error'; }
    else {
        $token = $_POST['token'] ?? '';
        $pass = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm'] ?? '');
        if ($token === '' || $pass === '' || $confirm === '') { $msg = 'Preencha todos os campos.'; $type = 'error'; }
        elseif ($pass !== $confirm) { $msg = 'Senhas não coincidem.'; $type = 'error'; }
        else {
            $stmt = $db->prepare('SELECT id, user_id FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $row = null;
            if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $row = $res ? $res->fetch_assoc() : null; if ($res) $res->close(); }
            $stmt->close();
            if (!$row) { $msg = 'Token inválido ou expirado.'; $type = 'error'; }
            else {
                $uid = (int)$row['user_id'];
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->bind_param('si', $hash, $uid);
                $stmt->execute();
                $stmt->close();
                $stmt = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
                $stmt->bind_param('i', $row['id']);
                $stmt->execute();
                $stmt->close();
                $msg = 'Senha redefinida com sucesso. Faça login.'; $type = 'success';
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
  <title>Redefinir senha • PlugPlay Shop</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand"><span class="logo">🔑</span><span class="name">Redefinir senha</span></div>
      <nav class="actions"><a class="btn" href="affiliate_register.php">Voltar</a></nav>
    </div>
  </header>
  <main class="container">
    <h1>Redefinir senha</h1>
    <?php if ($msg): ?><div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($valid): ?>
    <form method="post" class="form" style="max-width:420px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
      <div class="form-row"><label>Nova senha</label><input type="password" name="password" required /></div>
      <div class="form-row"><label>Confirmar senha</label><input type="password" name="confirm" required /></div>
      <div class="form-actions"><button class="btn primary" type="submit">Redefinir</button></div>
    </form>
    <?php else: ?>
      <p class="desc">Link inválido ou expirado.</p>
    <?php endif; ?>
  </main>
</body>
</html>