<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
@$db->query('CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$env = load_env();
$requiredToken = $env['ADMIN_SEED_TOKEN'] ?? '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? null)) { $msg = 'Sessão inválida'; }
    else {
        $reqToken = trim($_POST['token'] ?? '');
        if ($requiredToken === '' || !hash_equals((string)$requiredToken, (string)$reqToken)) { http_response_code(403); $msg = 'Acesso negado'; }
        else {
            $u = trim($_POST['username'] ?? '');
            $p = trim($_POST['password'] ?? '');
            $c = trim($_POST['confirm'] ?? '');
            $ow = isset($_POST['overwrite']) ? (int)$_POST['overwrite'] : 0;
            if ($u === '' || $p === '' || $c === '') { $msg = 'Preencha todos os campos'; }
            elseif ($p !== $c) { $msg = 'Senhas diferentes'; }
            else {
                $h = password_hash($p, PASSWORD_DEFAULT);
                $stmt = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                $stmt->bind_param('s', $u);
                $stmt->execute();
                $exists = false;
                if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $exists = $res && $res->fetch_assoc() ? true : false; $stmt->close(); }
                else { $meta = $stmt->result_metadata(); if ($meta) { $row = []; $binds = []; while ($f = $meta->fetch_field()) { $row[$f->name] = null; $binds[] =& $row[$f->name]; } call_user_func_array([$stmt, 'bind_result'], $binds); $exists = $stmt->fetch() ? true : false; } $stmt->close(); }
                if ($exists && $ow === 1) {
                    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
                    $stmt->bind_param('ss', $h, $u);
                    $ok = $stmt->execute();
                    $stmt->close();
                    $msg = $ok ? 'Senha atualizada' : 'Falha ao atualizar';
                } elseif ($exists) {
                    $msg = 'Usuário já existe';
                } else {
                    $stmt = $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                    $stmt->bind_param('ss', $u, $h);
                    $ok = $stmt->execute();
                    $stmt->close();
                    $msg = $ok ? 'Usuário criado' : 'Falha ao criar';
                }
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
  <title>Criar usuário • PlugPlay Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <main class="container" style="max-width:560px;">
    <h1>Criar usuário</h1>
    <?php if ($msg): ?><div class="notice"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post" class="form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <div class="form-row">
        <label>Token</label>
        <input type="text" name="token" required />
      </div>
      <div class="form-row">
        <label>Usuário</label>
        <input type="text" name="username" required />
      </div>
      <div class="form-row">
        <label>Senha</label>
        <input type="password" name="password" required />
      </div>
      <div class="form-row">
        <label>Confirmar senha</label>
        <input type="password" name="confirm" required />
      </div>
      <div class="form-row">
        <label><input type="checkbox" name="overwrite" value="1" /> Atualizar se existir</label>
      </div>
      <div class="form-actions">
        <button class="btn primary" type="submit">Salvar</button>
      </div>
    </form>
    <p class="desc">Acesse com o novo usuário em <code>login.php</code>.</p>
  </main>
</body>
</html>