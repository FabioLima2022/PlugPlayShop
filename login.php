<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);
@ $db->query('CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$rs = @ $db->query("SELECT COUNT(*) as c FROM users WHERE username='admin'");
if ($rs) { $rr = $rs->fetch_assoc(); if ((int)$rr['c'] === 0) { $h = password_hash('admin123', PASSWORD_DEFAULT); $st = $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)'); $u = 'admin'; $st->bind_param('ss', $u, $h); $st->execute(); $st->close(); } }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada ou inválida. Tente novamente.';
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($user !== '' && $pass !== '') {
        // rate limit: 5 tentativas, bloqueio por 15min
        $maxAttempts = 5;
        $lockMinutes = 15;
        $stmt = $db->prepare('SELECT id, attempt_count, locked_until FROM login_attempts WHERE username = ? AND ip = ? LIMIT 1');
        $stmt->bind_param('ss', $user, $ip);
        $stmt->execute();
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            $attempt = $res ? $res->fetch_assoc() : null;
            $stmt->close();
        } else {
            $meta = $stmt->result_metadata();
            $attempt = null;
            if ($meta) {
                $row = [];
                $binds = [];
                while ($f = $meta->fetch_field()) { $row[$f->name] = null; $binds[] =& $row[$f->name]; }
                call_user_func_array([$stmt, 'bind_result'], $binds);
                if ($stmt->fetch()) { $attempt = $row; }
            }
            $stmt->close();
        }
        $now = new DateTime('now');
        if ($attempt && !empty($attempt['locked_until'])) {
            $lockedUntil = new DateTime($attempt['locked_until']);
            if ($lockedUntil > $now) {
                $error = 'Muitas tentativas. Tente novamente após ' . $lockedUntil->format('H:i');
            }
        }

        if ($error === '') {
            // Busca usuário
            $stmt = $db->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $user);
            $stmt->execute();
            if (method_exists($stmt, 'get_result')) {
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
            } else {
                $meta = $stmt->result_metadata();
                $row = null;
                if ($meta) {
                    $data = [];
                    $binds = [];
                    while ($f = $meta->fetch_field()) { $data[$f->name] = null; $binds[] =& $data[$f->name]; }
                    call_user_func_array([$stmt, 'bind_result'], $binds);
                    if ($stmt->fetch()) { $row = $data; }
                }
                $stmt->close();
            }
            if ($row && password_verify($pass, $row['password_hash'])) {
                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'] ?? 'admin';
                // reset tentativas
                if ($attempt) {
                    $stmt = $db->prepare('UPDATE login_attempts SET attempt_count = 0, locked_until = NULL, last_attempt = CURRENT_TIMESTAMP WHERE id = ?');
                    $stmt->bind_param('i', $attempt['id']);
                    $stmt->execute();
                    $stmt->close();
                }
                header('Location: admin.php');
                exit;
            } else {
                // incrementa tentativa e aplica bloqueio se necessário
                if ($attempt) {
                    $count = (int)$attempt['attempt_count'] + 1;
                    $lockedUntilVal = null;
                    if ($count >= $maxAttempts) {
                        $locked = (clone $now)->modify('+' . $lockMinutes . ' minutes')->format('Y-m-d H:i:s');
                        $lockedUntilVal = $locked;
                    }
                    $stmt = $db->prepare('UPDATE login_attempts SET attempt_count = ?, last_attempt = CURRENT_TIMESTAMP, locked_until = ? WHERE id = ?');
                    $stmt->bind_param('isi', $count, $lockedUntilVal, $attempt['id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $lockedUntilVal = null;
                    $stmt = $db->prepare('INSERT INTO login_attempts (username, ip, attempt_count, locked_until) VALUES (?, ?, 1, ?)');
                    $stmt->bind_param('sss', $user, $ip, $lockedUntilVal);
                    $stmt->execute();
                    $stmt->close();
                }
                $error = 'Credenciais inválidas.';
            }
        }
        }
        else {
            $error = 'Informe usuário e senha.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login • PlugPlay Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand">
        <span class="logo">🔒</span>
        <span class="name">Login</span>
      </div>
      <nav class="actions">
        <a class="btn" href="index.php">Voltar à loja</a>
      </nav>
    </div>
  </header>
  <main class="container">
    <h1>Acesso ao painel</h1>
    <?php if ($error): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="form" style="max-width:420px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <div class="form-row">
        <label>Usuário</label>
        <input type="text" name="username" required />
      </div>
      <div class="form-row">
        <label>Senha</label>
        <input type="password" name="password" required />
      </div>
      <div class="form-actions">
        <button class="btn primary" type="submit">Entrar</button>
      </div>
    </form>
    <p class="desc">Dica: usuário default é <code>admin</code>. Altere a senha no banco.</p>
  </main>
</body>
</html>