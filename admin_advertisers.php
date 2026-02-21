<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
$__out_adv = false;
register_shutdown_function(function(){
  $e = error_get_last();
  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
  $isFatal = $e && in_array($e['type'] ?? 0, $fatalTypes, true);
  if ($isFatal && !$GLOBALS['__out_adv']) {
    if (!headers_sent()) { http_response_code(500); }
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>Erro • Anunciantes</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/styles.css" /></head><body><header class="site-header"><div class="container header-inner"><div class="brand"><span class="logo">🧑‍💼</span><span class="name">Anunciantes</span></div><nav class="actions"><a class="btn" href="admin.php">Admin</a><a class="btn" href="admin_affiliates.php">Afiliados</a></nav></div></header><main class="container">';
    if ($e && isset($e['message'])) { echo '<div class="notice error">Falha ao carregar Anunciantes: ' . htmlspecialchars($e['message']) . '</div>'; }
    else { echo '<div class="notice error">Falha ao carregar Anunciantes</div>'; }
    echo '</main></body></html>';
  }
});
// resposta imediata para verificação/ping sem depender de DB
if (isset($_GET['ping'])) { $__out_adv = true; echo 'advertisers-ok'; exit; }
session_start();
require_once __DIR__ . '/config.php';
$db = get_db_soft();
if ($db) { ensure_schema($db); }
$mustLogin = false;
$notAdmin = false;
if (!isset($_SESSION['user_id'])) { $mustLogin = true; }
elseif (($_SESSION['role'] ?? 'admin') !== 'admin') { $notAdmin = true; }

$msg = '';
$type = 'info';
$aff_uid = isset($_GET['aff_uid']) ? (int)$_GET['aff_uid'] : 0;
$aff_q = isset($_GET['aff_q']) ? trim((string)$_GET['aff_q']) : '';

if (!$db) { $msg = 'Falha ao conectar ao banco.'; $type = 'error'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_csrf($_POST['csrf'] ?? null)) { $msg = 'Sessão expirada ou inválida.'; $type = 'error'; }
  else {
    if (!$db) { $msg = 'Falha ao conectar ao banco.'; $type = 'error'; }
    else {
    $op = (string)($_POST['op'] ?? '');
    if ($op === 'create_user') {
      $full_name = trim($_POST['full_name'] ?? '');
      $email = strtolower(trim($_POST['email'] ?? ''));
      $phone = trim($_POST['phone'] ?? '');
      $password = trim($_POST['password'] ?? '');
      if ($full_name !== '' && $email !== '' && $password !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
        if ($stmt) {
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $exists = null;
        if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $exists = $res ? $res->fetch_assoc() : null; if ($res) $res->close(); }
        $stmt->close();
        } else { $exists = null; }
        if ($exists) { $msg='E-mail já cadastrado.'; $type='error'; }
        else {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $db->prepare('INSERT INTO users (username, password_hash, email, full_name, phone, role) VALUES (?, ?, ?, ?, ?, "advertiser")');
          if ($stmt) {
          $stmt->bind_param('sssss', $email, $hash, $email, $full_name, $phone);
          $ok = $stmt->execute();
          $stmt->close();
          } else { $ok = false; }
          if ($ok) { $msg='Anunciante criado.'; $type='success'; }
          else { $msg='Falha ao criar anunciante.'; $type='error'; }
        }
      } else { $msg='Dados inválidos.'; $type='error'; }
    }
    if ($op === 'update_user') {
      $id = (int)($_POST['id'] ?? 0);
      $full_name = trim($_POST['full_name'] ?? '');
      $email = strtolower(trim($_POST['email'] ?? ''));
      $phone = trim($_POST['phone'] ?? '');
      if ($id > 0 && $email !== '') {
        $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ?, username = ?, phone = ? WHERE id = ?');
        if ($stmt) {
        $stmt->bind_param('ssssi', $full_name, $email, $email, $phone, $id);
        $ok = $stmt->execute();
        $stmt->close();
        } else { $ok = false; }
        if ($ok) { $msg = 'Anunciante atualizado.'; $type = 'success'; $aff_uid = $id; }
        else { $msg = 'Falha ao atualizar anunciante.'; $type = 'error'; }
      } else { $msg = 'Dados inválidos.'; $type = 'error'; }
    }
    if ($op === 'delete_user') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM products WHERE user_id = ?');
        if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
        $stmt = $db->prepare('DELETE FROM classifieds WHERE user_id = ?');
        if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        if ($stmt) { $stmt->bind_param('i', $id); $ok = $stmt->execute(); $stmt->close(); } else { $ok = false; }
        if ($ok) { $msg = 'Anunciante removido.'; $type = 'success'; $aff_uid = 0; }
        else { $msg = 'Falha ao remover anunciante.'; $type = 'error'; }
      } else { $msg = 'ID inválido.'; $type = 'error'; }
    }
    if ($op === 'delete_classified') {
      $cid = (int)($_POST['id'] ?? 0);
      if ($cid > 0) {
        $ok = delete_classified($db, $cid);
        if ($ok) { $msg = 'Classificado excluído.'; $type = 'success'; }
        else { $msg = 'Falha ao excluir classificado.'; $type = 'error'; }
      } else { $msg = 'ID inválido.'; $type = 'error'; }
    }
    }
  }
}

$affiliates = [];
if ($db) {
$sqlAff = 'SELECT u.*, (SELECT COUNT(*) FROM classifieds c WHERE c.user_id = u.id) as classifieds_count, (SELECT COUNT(*) FROM products p WHERE p.user_id = u.id) as products_count FROM users u WHERE u.role = "advertiser"';
  if ($aff_q !== '') { $like = '%' . $db->real_escape_string($aff_q) . '%'; $sqlAff .= " AND (u.username LIKE '$like' OR u.email LIKE '$like' OR u.full_name LIKE '$like')"; }
  $sqlAff .= ' ORDER BY u.created_at DESC';
  if ($res = $db->query($sqlAff)) { while ($row = $res->fetch_assoc()) { $affiliates[] = $row; } $res->close(); }
}

$affSel = null;
if ($db && $aff_uid > 0) {
  $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND role = "advertiser" LIMIT 1');
  if ($stmt) {
  $stmt->bind_param('i', $aff_uid);
  $stmt->execute();
  if (method_exists($stmt, 'get_result')) { $r = $stmt->get_result(); $affSel = $r ? $r->fetch_assoc() : null; if ($r) $r->close(); }
  $stmt->close();
  }
}

$affCls = [];
if ($db && $affSel) {
  $stmt = $db->prepare('SELECT * FROM classifieds WHERE user_id = ? ORDER BY created_at DESC');
  if ($stmt) {
  $stmt->bind_param('i', $affSel['id']);
  $stmt->execute();
  if (method_exists($stmt, 'get_result')) { $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) { $affCls[] = $row; } if ($r) $r->close(); }
  $stmt->close();
  }
}

// Totais gerais para anunciantes
$advTotals = ['users'=>0,'classifieds'=>0,'products'=>0];
if ($db) {
  $rs = $db->query("SELECT COUNT(*) as c FROM users WHERE role='advertiser'");
  if ($rs) { $r=$rs->fetch_assoc(); $advTotals['users']=(int)$r['c']; $rs->close(); }
  $rs = $db->query("SELECT COUNT(*) as c FROM classifieds c JOIN users u ON u.id=c.user_id WHERE u.role='advertiser'");
  if ($rs) { $r=$rs->fetch_assoc(); $advTotals['classifieds']=(int)$r['c']; $rs->close(); }
  $rs = $db->query("SELECT COUNT(*) as c FROM products p JOIN users u ON u.id=p.user_id WHERE u.role='advertiser'");
  if ($rs) { $r=$rs->fetch_assoc(); $advTotals['products']=(int)$r['c']; $rs->close(); }
}

// Série diária de cliques para anunciante selecionado
$daily = [];
$maxC = 0;
if ($db && $affSel) {
  $stmt = $db->prepare('SELECT DATE(c.created_at) as d, COUNT(*) as c FROM clicks c JOIN products p ON p.id=c.product_id WHERE p.user_id=? AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(c.created_at) ORDER BY d ASC');
  if ($stmt) {
    $stmt->bind_param('i', $affSel['id']);
    $stmt->execute();
    if (method_exists($stmt,'get_result')) { $r=$stmt->get_result(); while($row=$r->fetch_assoc()){ $daily[]=$row; $maxC=max($maxC,(int)$row['c']); } if($r) $r->close(); }
    $stmt->close();
  }
}
?>
<?php $__out_adv = true; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin • Anunciantes</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand"><span class="logo">🧑‍💼</span><span class="name">Anunciantes</span></div>
      <nav class="actions"><a class="btn" href="admin.php">Admin</a><a class="btn" href="admin_products.php">Produtos</a><a class="btn" href="#novo-anunciante">Novo anunciante</a><a class="btn" href="admin_affiliates.php">Afiliados</a><a class="btn" href="logout.php">Sair</a></nav>
    </div>
  </header>
  <main class="container">
    <h1>Gerenciar anunciantes</h1>
    <?php if ($msg): ?><div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($mustLogin): ?><div class="notice error">Você precisa estar logado como administrador para acessar esta página. <a class="btn" href="login.php">Ir para login</a></div><?php endif; ?>
    <?php if ($notAdmin): ?><div class="notice error">Acesso restrito a administradores. <a class="btn" href="index.php">Voltar</a></div><?php endif; ?>

    <?php if (!$mustLogin && !$notAdmin): ?>
    <section>
      <h2>Visão rápida</h2>
      <div class="grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="card"><div class="card-body"><div class="card-title">Anunciantes</div><div class="price"><?= (int)$advTotals['users'] ?></div></div></div>
        <div class="card"><div class="card-body"><div class="card-title">Classificados</div><div class="price"><?= (int)$advTotals['classifieds'] ?></div></div></div>
        <div class="card"><div class="card-body"><div class="card-title">Produtos</div><div class="price"><?= (int)$advTotals['products'] ?></div></div></div>
      </div>
    </section>
    <section id="novo-anunciante">
      <h2>Novo anunciante</h2>
      <div class="card"><div class="card-body">
      <form method="post" action="admin_advertisers.php#novo-anunciante" class="form" style="max-width:680px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <input type="hidden" name="op" value="create_user" />
        <div class="form-row"><label>Nome completo</label><input type="text" name="full_name" required /></div>
        <div class="form-row"><label>E-mail</label><input type="email" name="email" required /></div>
        <div class="form-row"><label>Telefone</label><input type="text" name="phone" /></div>
        <div class="form-row"><label>Senha inicial</label><input type="password" name="password" required /></div>
        <div class="form-actions"><button class="btn primary" type="submit">Criar anunciante</button></div>
      </form>
      </div></div>
    </section>

    <section>
      <h2>Lista de anunciantes</h2>
      <form method="get" action="admin_advertisers.php" class="form" style="grid-template-columns: 1.2fr 140px; align-items: end; max-width:520px;">
        <div class="form-row"><label>Buscar</label><input type="text" name="aff_q" value="<?= htmlspecialchars($aff_q) ?>" placeholder="Nome, e-mail..." /></div>
        <div class="form-actions"><button class="btn" type="submit">Filtrar</button><a class="btn" href="admin_advertisers.php">Limpar</a></div>
      </form>
      <div class="card"><div class="card-body">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="color:#cbd1e6;">
              <th style="text-align:left; padding:6px;">ID</th>
              <th style="text-align:left; padding:6px;">Nome</th>
              <th style="text-align:left; padding:6px;">E-mail</th>
              <th style="text-align:left; padding:6px;">Celular</th>
              <th style="text-align:right; padding:6px;">Anúncios</th>
              <th style="text-align:right; padding:6px;">Produtos</th>
              <th style="text-align:right; padding:6px;">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($affiliates as $u): ?>
              <tr>
                <td style="padding:6px;">#<?= (int)$u['id'] ?></td>
                <td style="padding:6px;"><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></td>
                <td style="padding:6px;"><?= htmlspecialchars($u['email'] ?: $u['username']) ?></td>
                <td style="padding:6px;"><?= htmlspecialchars($u['phone']) ?></td>
                <td style="padding:6px; text-align:right; font-weight:700;"><?= (int)$u['classifieds_count'] ?></td>
                <td style="padding:6px; text-align:right; font-weight:700;"><?= (int)$u['products_count'] ?></td>
                <td style="padding:6px; text-align:right;">
                  <a class="btn" href="admin_advertisers.php?aff_uid=<?= (int)$u['id'] ?>">Perfil</a>
                  <a class="btn" href="admin.php?owner_id=<?= (int)$u['id'] ?>#classificados" target="_blank">Ver anúncios</a>
                  <a class="btn" href="admin_affiliates.php?uid=<?= (int)$u['id'] ?>" target="_blank">Ver produtos</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </section>

    <?php if (!$mustLogin && !$notAdmin && $affSel): ?>
    <section>
      <h2>Perfil do anunciante #<?= (int)$affSel['id'] ?></h2>
      <div class="card"><div class="card-body">
        <form method="post" action="admin_advertisers.php?aff_uid=<?= (int)$affSel['id'] ?>" class="form" style="max-width:640px;">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
          <input type="hidden" name="op" value="update_user" />
          <input type="hidden" name="id" value="<?= (int)$affSel['id'] ?>" />
          <div class="form-row"><label>Nome</label><input type="text" name="full_name" value="<?= htmlspecialchars($affSel['full_name']) ?>" /></div>
          <div class="form-row"><label>E-mail</label><input type="email" name="email" value="<?= htmlspecialchars($affSel['email'] ?: $affSel['username']) ?>" required /></div>
          <div class="form-row"><label>Celular</label><input type="text" name="phone" value="<?= htmlspecialchars($affSel['phone']) ?>" /></div>
          <div class="form-actions">
            <button class="btn primary" type="submit">Salvar</button>
          </div>
        </form>
        <div class="form-actions" style="margin-top:8px;">
          <form method="post" action="admin_advertisers.php?aff_uid=<?= (int)$affSel['id'] ?>" onsubmit="return confirm('Remover anunciante e seus itens?');" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
            <input type="hidden" name="op" value="delete_user" />
            <input type="hidden" name="id" value="<?= (int)$affSel['id'] ?>" />
            <button class="btn" type="submit" style="background:#c00; color:#fff;">Remover</button>
          </form>
        </div>
      </div></div>
    </section>
    <section>
      <h2>Cliques por dia (30 dias)</h2>
      <div class="card"><div class="card-body" style="display:grid; gap:8px;">
        <?php if (!empty($daily)): ?>
          <?php
            $W = 720; $H = 220; $M = 28;
            $n = count($daily);
            $innerW = $W - 2*$M; $innerH = $H - 2*$M;
            $stepX = $n > 1 ? ($innerW / ($n - 1)) : 0;
            $path = '';
            foreach ($daily as $i=>$r) {
              $c = (int)$r['c'];
              $x = $M + $i * $stepX;
              $y = $H - $M - ($maxC > 0 ? ($c / $maxC) * $innerH : 0);
              $cmd = ($i === 0) ? 'M' : 'L';
              $path .= $cmd . ' ' . round($x,1) . ' ' . round($y,1) . ' ';
            }
            $dots = [];
            foreach ($daily as $i=>$r) {
              $c = (int)$r['c'];
              $x = $M + $i * $stepX;
              $y = $H - $M - ($maxC > 0 ? ($c / $maxC) * $innerH : 0);
              $dots[] = ['x'=>$x, 'y'=>$y, 'd'=>$r['d'], 'c'=>$c];
            }
            $baseline = $H - $M;
            $areaPath = '';
            foreach ($daily as $i=>$r) {
              $c = (int)$r['c'];
              $x = $M + $i * $stepX;
              $y = $H - $M - ($maxC > 0 ? ($c / $maxC) * $innerH : 0);
              $cmd = ($i === 0) ? 'M' : 'L';
              $areaPath .= $cmd . ' ' . round($x,1) . ' ' . round($y,1) . ' ';
            }
            if ($n > 1) {
              $lastX = $M + ($n - 1) * $stepX; $firstX = $M;
              $areaPath .= ' L ' . round($lastX,1) . ' ' . round($baseline,1) . ' L ' . round($firstX,1) . ' ' . round($baseline,1) . ' Z';
            }
          ?>
          <svg width="<?= $W ?>" height="<?= $H ?>" viewBox="0 0 <?= $W ?> <?= $H ?>" style="background:#0f1427; border-radius:12px;">
            <rect x="<?= $M ?>" y="<?= $M ?>" width="<?= $innerW ?>" height="<?= $innerH ?>" fill="#0f1427" stroke="#222642" />
            <path d="<?= $areaPath ?>" fill="#7c5cff22" stroke="none" />
            <path d="<?= $path ?>" fill="none" stroke="#7c5cff" stroke-width="2" />
            <?php foreach ($dots as $pt): ?>
              <circle cx="<?= round($pt['x'],1) ?>" cy="<?= round($pt['y'],1) ?>" r="3.5" fill="#7c5cff">
                <title><?= htmlspecialchars($pt['d']) ?> • <?= (int)$pt['c'] ?> clique<?= $pt['c']==1?'':'s' ?></title>
              </circle>
            <?php endforeach; ?>
            <text x="6" y="<?= $M ?>" fill="#cbd1e6" font-size="12"><?= (int)$maxC ?></text>
            <text x="6" y="<?= $H - $M ?>" fill="#cbd1e6" font-size="12">0</text>
            <?php foreach ($dots as $i=>$pt): if ($i % max(1, (int)floor($n/6)) !== 0) continue; ?>
              <text x="<?= round($pt['x'],1) ?>" y="<?= $H - 6 ?>" fill="#cbd1e6" font-size="11" text-anchor="middle"><?= htmlspecialchars($pt['d']) ?></text>
            <?php endforeach; ?>
          </svg>
        <?php else: ?>
          <p class="desc">Sem cliques registrados no período.</p>
        <?php endif; ?>
      </div></div>
    </section>
    <section>
      <h2>Anúncios do anunciante</h2>
      <div class="card"><div class="card-body">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="color:#cbd1e6;">
              <th style="text-align:left; padding:6px;">ID</th>
              <th style="text-align:left; padding:6px;">Título</th>
              <th style="text-align:left; padding:6px;">Categoria</th>
              <th style="text-align:right; padding:6px;">Preço</th>
              <th style="text-align:right; padding:6px;">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($affCls as $c): ?>
              <tr>
                <td style="padding:6px;">#<?= (int)$c['id'] ?></td>
                <td style="padding:6px;"><?= htmlspecialchars($c['title']) ?></td>
                <td style="padding:6px;"><?= htmlspecialchars($c['category']) ?></td>
                <td style="padding:6px; text-align:right;"><?= htmlspecialchars(format_money((float)$c['price'], (string)($c['currency'] ?? 'BRL'))) ?></td>
                <td style="padding:6px; text-align:right;">
                  <a class="btn" href="admin.php?edit_classified_id=<?= (int)$c['id'] ?>#classificados" target="_blank">Editar</a>
                  <form method="post" action="admin_advertisers.php?aff_uid=<?= (int)$affSel['id'] ?>" style="display:inline" onsubmit="return confirm('Excluir este anúncio?');">
                    <input type="hidden" name="op" value="delete_classified" />
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
                    <button class="btn" type="submit" style="background:#c00; color:#fff;">Excluir</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </section>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($mustLogin || $notAdmin): ?>
      <p class="desc">Entre como admin para visualizar e gerenciar anunciantes.</p>
    <?php endif; ?>
  </main>
</body>
</html>
