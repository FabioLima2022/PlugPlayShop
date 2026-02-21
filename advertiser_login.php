<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db_soft();
if ($db) { ensure_schema($db); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada ou inválida. Tente novamente.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass = trim($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($email !== '' && $pass !== '') {
            $maxAttempts = 5;
            $lockMinutes = 15;
            $attempt = null;
            if ($db) {
            $stmt = $db->prepare('SELECT id, attempt_count, locked_until FROM login_attempts WHERE username = ? AND ip = ? LIMIT 1');
            $stmt->bind_param('ss', $email, $ip);
            $stmt->execute();
            if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $attempt = $res ? $res->fetch_assoc() : null; $stmt->close(); }
            else { $attempt = null; $stmt->close(); }
            }
            $now = new DateTime('now');
            if ($attempt && !empty($attempt['locked_until'])) { $lockedUntil = new DateTime($attempt['locked_until']); if ($lockedUntil > $now) { $error = 'Muitas tentativas. Tente novamente após ' . $lockedUntil->format('H:i'); } }
            if ($error === '') {
                $row = null;
                if ($db) {
                $stmt = $db->prepare('SELECT id, username, password_hash, role FROM users WHERE (username = ? OR email = ?) AND role = "advertiser" LIMIT 1');
                $stmt->bind_param('ss', $email, $email);
                $stmt->execute();
                if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $row = $res ? $res->fetch_assoc() : null; $stmt->close(); }
                else { $row = null; $stmt->close(); }
                }
                if ($row && password_verify($pass, $row['password_hash'])) {
                    $_SESSION['user_id'] = (int)$row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = 'advertiser';
                    if ($db && $attempt) { $stmt = $db->prepare('UPDATE login_attempts SET attempt_count = 0, locked_until = NULL, last_attempt = CURRENT_TIMESTAMP WHERE id = ?'); $stmt->bind_param('i', $attempt['id']); $stmt->execute(); $stmt->close(); }
                    header('Location: advertiser_dashboard.php');
                    exit;
                } else {
                    if ($db && $attempt) { $count = (int)$attempt['attempt_count'] + 1; $lockedUntilVal = null; if ($count >= $maxAttempts) { $locked = (clone $now)->modify('+' . $lockMinutes . ' minutes')->format('Y-m-d H:i:s'); $lockedUntilVal = $locked; } $stmt = $db->prepare('UPDATE login_attempts SET attempt_count = ?, last_attempt = CURRENT_TIMESTAMP, locked_until = ? WHERE id = ?'); $stmt->bind_param('isi', $count, $lockedUntilVal, $attempt['id']); $stmt->execute(); $stmt->close(); }
                    else if ($db) { $lockedUntilVal = null; $stmt = $db->prepare('INSERT INTO login_attempts (username, ip, attempt_count, locked_until) VALUES (?, ?, 1, ?)'); $stmt->bind_param('sss', $email, $ip, $lockedUntilVal); $stmt->execute(); $stmt->close(); }
                    $error = 'Credenciais inválidas.';
                }
            }
        } else { $error = 'Informe e-mail e senha.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login de Anunciante • PlugPlay Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand"><span class="logo">🔐</span><span class="name">Login Anunciante</span></div>
      <nav class="actions"><a class="btn" href="index.php">Voltar à loja</a><a class="btn" href="advertiser_register.php">Cadastre-se</a><a class="btn" href="affiliate_forgot.php">Recuperar senha</a></nav>
    </div>
  </header>
  <main class="container">
    <h1>Acesso do Anunciante</h1>
    <?php if ($error): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="form" style="max-width:420px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <div class="form-row"><label>E-mail</label><input type="email" name="email" required /></div>
      <div class="form-row"><label>Senha</label><input type="password" name="password" required /></div>
      <div class="form-actions"><button class="btn primary" type="submit">Entrar</button></div>
    </form>
  </main>
</body>
</html>
