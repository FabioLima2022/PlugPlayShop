<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$msg = '';
$type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $msg = 'Sessão expirada ou inválida. Tente novamente.';
        $type = 'error';
    } else {
        $current = trim($_POST['current'] ?? '');
        $new = trim($_POST['new'] ?? '');
        $confirm = trim($_POST['confirm'] ?? '');
        if ($new === '' || $confirm === '' || $current === '') {
            $msg = 'Preencha todos os campos.';
            $type = 'error';
        } elseif ($new !== $confirm) {
            $msg = 'A confirmação não confere com a nova senha.';
            $type = 'error';
        } else {
            $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE id = ? LIMIT 1');
            $uid = (int)$_SESSION['user_id'];
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            if (method_exists($stmt, 'get_result')) {
                $res = $stmt->get_result();
                $user = $res ? $res->fetch_assoc() : null;
                $stmt->close();
            } else {
                $meta = $stmt->result_metadata();
                $user = null;
                if ($meta) {
                    $data = [];
                    $binds = [];
                    while ($f = $meta->fetch_field()) { $data[$f->name] = null; $binds[] =& $data[$f->name]; }
                    call_user_func_array([$stmt, 'bind_result'], $binds);
                    if ($stmt->fetch()) { $user = $data; }
                }
                $stmt->close();
            }
            if (!$user || !password_verify($current, $user['password_hash'])) {
                $msg = 'Senha atual incorreta.';
                $type = 'error';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->bind_param('si', $hash, $uid);
                $ok = $stmt->execute();
                $stmt->close();
                $msg = $ok ? 'Senha alterada com sucesso.' : 'Falha ao alterar senha.';
                $type = $ok ? 'success' : 'error';
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
  <title>Configurações • PlugPlay Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand">
        <span class="logo">⚙️</span>
        <span class="name">Configurações</span>
      </div>
      <nav class="actions">
        <a class="btn" href="admin.php">Voltar ao admin</a>
        <a class="btn" href="logout.php">Sair</a>
      </nav>
    </div>
  </header>
  <main class="container">
    <h1>Troca de senha</h1>
    <?php if ($msg): ?>
      <div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <form method="post" class="form" style="max-width:460px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <div class="form-row">
        <label>Senha atual</label>
        <input type="password" name="current" required />
      </div>
      <div class="form-row">
        <label>Nova senha</label>
        <input type="password" name="new" required />
      </div>
      <div class="form-row">
        <label>Confirmar nova senha</label>
        <input type="password" name="confirm" required />
      </div>
      <div class="form-actions">
        <button class="btn primary" type="submit">Alterar senha</button>
      </div>
    </form>
  </main>
</body>
</html>