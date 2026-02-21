<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
$__out_af = false;
register_shutdown_function(function(){
  $e = error_get_last();
  $fatal = $e && in_array($e['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
  if ($fatal && !$GLOBALS['__out_af']) {
    if (!headers_sent()) { http_response_code(500); }
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>Erro • Afiliados</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/styles.css" /></head><body><header class="site-header"><div class="container header-inner"><div class="brand"><span class="logo">👥</span><span class="name">Afiliados</span></div><nav class="actions"><a class="btn" href="admin.php">Admin</a><a class="btn" href="admin_advertisers.php">Anunciantes</a></nav></div></header><main class="container">';
    if ($e && isset($e['message'])) { echo '<div class="notice error">Falha ao carregar Afiliados: ' . htmlspecialchars($e['message']) . '</div>'; }
    else { echo '<div class="notice error">Falha ao carregar Afiliados</div>'; }
    echo '</main></body></html>';
  }
});
session_start();
require_once __DIR__ . '/config.php';
$db = get_db_soft();
if ($db) { ensure_schema($db); }
if (isset($_GET['ping'])) { $__out_af = true; echo 'affiliates-ok'; exit; }
$mustLogin = !isset($_SESSION['user_id']);
$notAdmin = (!$mustLogin) && (($_SESSION['role'] ?? 'admin') !== 'admin');

$msg = '';
$type = 'info';
$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_csrf($_POST['csrf'] ?? null)) { $msg = 'Sessão expirada ou inválida.'; $type = 'error'; }
    else {
        if (!$db) { $msg = 'Falha ao conectar ao banco.'; $type = 'error'; }
        else {
        $op = $_POST['op'] ?? '';
        if ($op === 'create_user') {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $password = trim($_POST['password'] ?? '');
            if ($full_name !== '' && $email !== '' && $password !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
                $stmt->bind_param('ss', $email, $email);
                $stmt->execute();
                $exists = null;
                if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $exists = $res ? $res->fetch_assoc() : null; if ($res) $res->close(); }
                $stmt->close();
                if ($exists) { $msg='E-mail já cadastrado.'; $type='error'; }
                else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('INSERT INTO users (username, password_hash, email, full_name, phone, role) VALUES (?, ?, ?, ?, ?, "affiliate")');
                    $stmt->bind_param('sssss', $email, $hash, $email, $full_name, $phone);
                    $ok = $stmt->execute();
                    $stmt->close();
                    if ($ok) { $msg='Afiliado criado.'; $type='success'; }
                    else { $msg='Falha ao criar afiliado.'; $type='error'; }
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
                $stmt->bind_param('ssssi', $full_name, $email, $email, $phone, $id);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) { $msg = 'Afiliado atualizado.'; $type = 'success'; $uid = $id; }
                else { $msg = 'Falha ao atualizar afiliado.'; $type = 'error'; }
            } else { $msg = 'Dados inválidos.'; $type = 'error'; }
        } elseif ($op === 'delete_user') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare('DELETE FROM products WHERE user_id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
                $stmt->bind_param('i', $id);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) { $msg = 'Afiliado removido.'; $type = 'success'; $uid = 0; }
                else { $msg = 'Falha ao remover afiliado.'; $type = 'error'; }
            } else { $msg = 'ID inválido.'; $type = 'error'; }
        }
        if ($op === 'resolve_product_alerts') {
            $pid = (int)($_POST['pid'] ?? 0);
            if ($pid > 0) {
                $ok = resolve_alerts_for_product($db, $pid);
                if ($ok) { $msg = 'Alertas resolvidos para o produto.'; $type = 'success'; }
                else { $msg = 'Falha ao resolver alertas.'; $type = 'error'; }
            }
        }
        if ($op === 'generate_reset') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $token = bin2hex(random_bytes(32));
                $expires = (new DateTime('now'))->modify('+30 minutes')->format('Y-m-d H:i:s');
                $stmt = $db->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
                $stmt->bind_param('iss', $id, $token, $expires);
                $stmt->execute();
                $stmt->close();
                $msg = 'Link de redefinição: affiliate_reset.php?token=' . $token; $type='success';
            } else { $msg='ID inválido.'; $type='error'; }
        }
        }
  }
}

$affiliates = [];
$sqlAff = 'SELECT u.*, (SELECT COUNT(*) FROM products p WHERE p.user_id = u.id) as products_count FROM users u WHERE u.role = "affiliate"';
if ($q !== '') { $like = '%' . $db->real_escape_string($q) . '%'; $sqlAff .= " AND (u.username LIKE '$like' OR u.email LIKE '$like' OR u.full_name LIKE '$like')"; }
$sqlAff .= ' ORDER BY u.created_at DESC';
if ($res = $db->query($sqlAff)) { while ($row = $res->fetch_assoc()) { $affiliates[] = $row; } $res->close(); }

$selected = null;
if ($uid > 0) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND role = "affiliate" LIMIT 1');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    if (method_exists($stmt, 'get_result')) { $r = $stmt->get_result(); $selected = $r ? $r->fetch_assoc() : null; if ($r) $r->close(); }
    $stmt->close();
}

$products = [];
if ($selected) {
    $stmt = $db->prepare('SELECT p.*, COALESCE(cnt.c,0) as clicks_count FROM products p LEFT JOIN (SELECT product_id, COUNT(*) as c FROM clicks GROUP BY product_id) cnt ON cnt.product_id = p.id WHERE p.user_id = ? ORDER BY p.created_at DESC');
    $stmt->bind_param('i', $selected['id']);
    $stmt->execute();
    if (method_exists($stmt, 'get_result')) { $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) { $products[] = $row; } if ($r) $r->close(); }
    $stmt->close();
}
?>
<?php $__out_af = true; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin • Afiliados</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand"><span class="logo">👥</span><span class="name">Afiliados</span></div>
      <nav class="actions"><a class="btn" href="admin.php">Admin</a><a class="btn" href="admin_products.php">Produtos</a><a class="btn" href="admin_advertisers.php">Anunciantes</a><a class="btn" href="logout.php">Sair</a></nav>
    </div>
  </header>
  <main class="container">
    <h1>Gerenciar afiliados</h1>
    <?php if ($msg): ?><div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <section>
      <h2>Novo afiliado</h2>
      <form method="post" class="form" style="max-width:680px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <input type="hidden" name="op" value="create_user" />
        <div class="form-row"><label>Nome completo</label><input type="text" name="full_name" required /></div>
        <div class="form-row"><label>E-mail</label><input type="email" name="email" required /></div>
        <div class="form-row"><label>Telefone</label><input type="text" name="phone" /></div>
        <div class="form-row"><label>Senha inicial</label><input type="password" name="password" required /></div>
        <div class="form-actions"><button class="btn primary" type="submit">Criar afiliado</button></div>
      </form>
    </section>
    <section>
      <form method="get" action="admin_affiliates.php" class="form" style="grid-template-columns: 1fr 160px; align-items: end; max-width:680px;">
        <div class="form-row"><label>Buscar</label><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nome, e-mail..." /></div>
        <div class="form-actions"><button class="btn" type="submit">Filtrar</button><a class="btn" href="admin_affiliates.php">Limpar</a></div>
      </form>
      <div class="card"><div class="card-body">
        <table style="width:100%; border-collapse:collapse;">
          <thead><tr style="color:#cbd1e6;"><th style="text-align:left; padding:6px;">ID</th><th style="text-align:left; padding:6px;">Nome</th><th style="text-align:left; padding:6px;">E-mail</th><th style="text-align:left; padding:6px;">Telefone</th><th style="text-align:right; padding:6px;">Produtos</th><th style="text-align:right; padding:6px;">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($affiliates as $u): ?>
            <tr>
              <td style="padding:6px;">#<?= (int)$u['id'] ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($u['email'] ?? $u['username']) ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($u['phone'] ?? '') ?></td>
              <td style="padding:6px; text-align:right; font-weight:700;"><?= (int)$u['products_count'] ?></td>
              <td style="padding:6px; text-align:right;"><a class="btn" href="admin_affiliates.php?uid=<?= (int)$u['id'] ?>">Ver</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </section>
    <?php if (!$mustLogin && !$notAdmin && $selected): ?>
    <section>
      <h2>Afiliado #<?= (int)$selected['id'] ?></h2>
      <form method="post" class="form" style="max-width:640px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <input type="hidden" name="op" value="update_user" />
        <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>" />
        <div class="form-row"><label>Nome completo</label><input type="text" name="full_name" value="<?= htmlspecialchars($selected['full_name'] ?? '') ?>" /></div>
        <div class="form-row"><label>E-mail</label><input type="email" name="email" value="<?= htmlspecialchars($selected['email'] ?? $selected['username']) ?>" /></div>
        <div class="form-row"><label>Telefone</label><input type="text" name="phone" value="<?= htmlspecialchars($selected['phone'] ?? '') ?>" /></div>
        <div class="form-actions"><button class="btn primary" type="submit">Salvar</button></div>
      </form>
      <form method="post" style="margin-top:10px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <input type="hidden" name="op" value="generate_reset" />
        <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>" />
        <button class="btn" type="submit">Gerar link de redefinição</button>
      </form>
      <?php
        $period = isset($_GET['period']) ? (int)$_GET['period'] : 30;
        if ($period < 1 || $period > 365) $period = 30;
        $totals = ['products'=>0,'clicks'=>0,'clicks_period'=>0];
        $rs = $db->query('SELECT COUNT(*) as c FROM products WHERE user_id=' . (int)$selected['id']);
        if ($rs) { $r=$rs->fetch_assoc(); $totals['products']=(int)$r['c']; $rs->close(); }
        $rs = $db->query('SELECT COUNT(*) as c FROM clicks c JOIN products p ON p.id=c.product_id WHERE p.user_id=' . (int)$selected['id']);
        if ($rs) { $r=$rs->fetch_assoc(); $totals['clicks']=(int)$r['c']; $rs->close(); }
        $stmt = $db->prepare('SELECT COUNT(*) as c FROM clicks c JOIN products p ON p.id=c.product_id WHERE p.user_id=? AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)');
        $stmt->bind_param('ii', $selected['id'], $period);
        $stmt->execute();
        if (method_exists($stmt,'get_result')) { $r=$stmt->get_result(); $row=$r?$r->fetch_assoc():['c'=>0]; $totals['clicks_period']=(int)$row['c']; if($r) $r->close(); }
        $stmt->close();
      ?>
      <?php
        $daily = [];
        $maxC = 0;
        $stmt = $db->prepare('SELECT DATE(c.created_at) as d, COUNT(*) as c FROM clicks c JOIN products p ON p.id=c.product_id WHERE p.user_id=? AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(c.created_at) ORDER BY d ASC');
        $stmt->bind_param('i', $selected['id']);
        $stmt->execute();
        if (method_exists($stmt,'get_result')) { $r=$stmt->get_result(); while($row=$r->fetch_assoc()){ $daily[]=$row; $maxC=max($maxC,(int)$row['c']); } if($r) $r->close(); }
        $stmt->close();
      ?>
      <h3>Métricas</h3>
      <form method="get" action="admin_affiliates.php" class="form" style="grid-template-columns: 1fr 160px; align-items: end; max-width:480px;">
        <input type="hidden" name="uid" value="<?= (int)$selected['id'] ?>" />
        <div class="form-row"><label>Período (dias)</label><input type="number" name="period" value="<?= (int)$period ?>" min="1" max="365" /></div>
        <div class="form-actions"><button class="btn" type="submit">Aplicar</button></div>
      </form>
      <div class="grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="card"><div class="card-body"><div class="card-title">Produtos</div><div class="price"><?= (int)$totals['products'] ?></div></div></div>
        <div class="card"><div class="card-body"><div class="card-title">Cliques (total)</div><div class="price"><?= (int)$totals['clicks'] ?></div></div></div>
        <div class="card"><div class="card-body"><div class="card-title">Cliques (<?= (int)$period ?>d)</div><div class="price"><?= (int)$totals['clicks_period'] ?></div></div></div>
      </div>
      <h3>Cliques por dia (30 dias)</h3>
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
      <form method="post" onsubmit="return confirm('Remover afiliado e todos os seus produtos?');" style="margin-top:10px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <input type="hidden" name="op" value="delete_user" />
        <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>" />
        <button class="btn" type="submit" style="background:#c00; color:#fff;">Remover afiliado</button>
      </form>
    </section>
    <section>
      <h2>Produtos do afiliado</h2>
      <div class="card"><div class="card-body">
        <?php
          $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
          $perPage = isset($_GET['per_page']) ? max(1,min(200,(int)$_GET['per_page'])) : 20;
          $offset = ($page-1)*$perPage;
          $stmt = $db->prepare('SELECT COUNT(*) as c FROM products WHERE user_id=?');
          $stmt->bind_param('i', $selected['id']);
          $stmt->execute();
          $totalRows = 0;
          if (method_exists($stmt,'get_result')) { $r=$stmt->get_result(); $row=$r?$r->fetch_assoc():['c'=>0]; $totalRows=(int)$row['c']; if($r) $r->close(); }
          $stmt->close();
          $totalPages = max(1, (int)ceil($totalRows/$perPage));
          $stmt = $db->prepare('SELECT p.*, COALESCE(cnt.c,0) as clicks_count FROM products p LEFT JOIN (SELECT product_id, COUNT(*) as c FROM clicks GROUP BY product_id) cnt ON cnt.product_id = p.id WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT ? OFFSET ?');
          $stmt->bind_param('iii', $selected['id'], $perPage, $offset);
          $products = [];
          $stmt->execute();
          if (method_exists($stmt,'get_result')) { $r=$stmt->get_result(); while($row=$r->fetch_assoc()){ $products[]=$row; } if($r) $r->close(); }
          $stmt->close();
        ?>
        <table style="width:100%; border-collapse:collapse;">
          <thead><tr style="color:#cbd1e6;"><th style="text-align:left; padding:6px;">ID</th><th style="text-align:left; padding:6px;">Nome</th><th style="text-align:left; padding:6px;">Categoria</th><th style="text-align:right; padding:6px;">Preço</th><th style="text-align:right; padding:6px;">Cliques</th><th style="text-align:right; padding:6px;">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td style="padding:6px;">#<?= (int)$p['id'] ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($p['name']) ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($p['category']) ?></td>
              <td style="padding:6px; text-align:right;">&nbsp;<?= htmlspecialchars(format_money((float)$p['price'], (string)($p['currency'] ?? 'USD'))) ?></td>
              <td style="padding:6px; text-align:right; font-weight:700;">&nbsp;<?= (int)$p['clicks_count'] ?></td>
              <td style="padding:6px; text-align:right;">
                <a class="btn" href="product.php?id=<?= (int)$p['id'] ?>" target="_blank">Ver</a>
                <a class="btn" href="edit_product.php?id=<?= (int)$p['id'] ?>&return=admin_affiliates.php%3Fuid%3D<?= (int)$selected['id'] ?>">Editar</a>
                <?php $ac = product_unresolved_alert_count($db, (int)$p['id']); if ($ac > 0): ?>
                  <span class="badge" style="background:#c00; color:#fff;">Alertas: <?= (int)$ac ?></span>
                  <form method="post" action="admin_affiliates.php?uid=<?= (int)$selected['id'] ?>" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
                    <input type="hidden" name="op" value="resolve_product_alerts" />
                    <input type="hidden" name="pid" value="<?= (int)$p['id'] ?>" />
                    <button class="btn" type="submit">Resolver</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="delete_product.php" style="display:inline" onsubmit="return confirm('Excluir este produto?');">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
                  <input type="hidden" name="redirect" value="admin_affiliates.php?uid=<?= (int)$selected['id'] ?>" />
                  <button class="btn" type="submit" style="background:#c00; color:#fff;">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php
          $params = $_GET; $params['uid'] = (int)$selected['id'];
          $prevPage = max(1,$page-1); $nextPage = min($totalPages,$page+1);
          $params['page']=$prevPage; $prevLink = 'admin_affiliates.php?' . http_build_query($params);
          $params['page']=$nextPage; $nextLink = 'admin_affiliates.php?' . http_build_query($params);
          function af_page_link($p){ $params = $_GET; $params['page']=$p; return 'admin_affiliates.php?' . http_build_query($params); }
        ?>
        <div class="form-actions" style="margin-top:10px; display:flex; align-items:center; gap:10px; justify-content:space-between;">
          <div style="display:flex; gap:8px; align-items:center;">
            <a class="btn" href="<?= htmlspecialchars($prevLink) ?>" <?= $page<=1?'style="opacity:.6; pointer-events:none;"':''; ?>>« Anterior</a>
            <span class="desc">Página <?= (int)$page ?> de <?= (int)$totalPages ?> (<?= (int)$totalRows ?> itens)</span>
            <a class="btn" href="<?= htmlspecialchars($nextLink) ?>" <?= $page>=$totalPages?'style="opacity:.6; pointer-events:none;"':''; ?>>Próxima »</a>
          </div>
        </div>
      </div></div>
    </section>
    <?php endif; ?>
  </main>
</body>
</html>
