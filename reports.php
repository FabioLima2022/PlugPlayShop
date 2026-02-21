<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$source = trim($_GET['source'] ?? '');
$category = trim($_GET['category'] ?? '');
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$product_q = trim($_GET['product_q'] ?? '');
$params = [];
$where = [];
if ($start !== '') { $where[] = 'c.created_at >= ?'; $params[] = $start . ' 00:00:00'; }
if ($end !== '') { $where[] = 'c.created_at <= ?'; $params[] = $end . ' 23:59:59'; }
if ($source !== '') { $where[] = 'c.source = ?'; $params[] = $source; }
if ($category !== '') { $where[] = 'p.category = ?'; $params[] = $category; }
if ($product_id > 0) { $where[] = 'c.product_id = ?'; $params[] = $product_id; }
elseif ($product_q !== '') { $where[] = 'p.name LIKE ?'; $params[] = '%' . $product_q . '%'; }
$whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

// métricas simples no período
$sqlCount = 'SELECT COUNT(*) as clicks FROM clicks c' . $whereSql;
$stmt = $db->prepare($sqlCount);
if (!empty($params)) { $types = str_repeat('s', count($params)); $stmt->bind_param($types, ...$params); }
$stmt->execute();
if (method_exists($stmt, 'get_result')) {
    $rc = $stmt->get_result();
    $periodClicks = ($rc && ($r=$rc->fetch_assoc())) ? (int)$r['clicks'] : 0;
    $stmt->close();
} else {
    $meta = $stmt->result_metadata();
    $periodClicks = 0;
    if ($meta) {
        $row = [];
        $binds = [];
        while ($f = $meta->fetch_field()) { $row[$f->name] = null; $binds[] =& $row[$f->name]; }
        call_user_func_array([$stmt, 'bind_result'], $binds);
        if ($stmt->fetch()) { $periodClicks = isset($row['clicks']) ? (int)$row['clicks'] : (int)current($row); }
    }
    $stmt->close();
}

// tabela de cliques
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Total para paginação
$sqlTotal = 'SELECT COUNT(*) as c FROM clicks c JOIN products p ON p.id = c.product_id' . $whereSql;
$stmt = $db->prepare($sqlTotal);
if (!empty($params)) { $types = str_repeat('s', count($params)); $stmt->bind_param($types, ...$params); }
$stmt->execute();
if (method_exists($stmt, 'get_result')) {
    $resTotal = $stmt->get_result();
    $totalRows = ($resTotal && ($tr=$resTotal->fetch_assoc())) ? (int)$tr['c'] : 0;
    $stmt->close();
} else {
    $meta = $stmt->result_metadata();
    $totalRows = 0;
    if ($meta) {
        $row = [];
        $binds = [];
        while ($f = $meta->fetch_field()) { $row[$f->name] = null; $binds[] =& $row[$f->name]; }
        call_user_func_array([$stmt, 'bind_result'], $binds);
        if ($stmt->fetch()) { $totalRows = isset($row['c']) ? (int)$row['c'] : (int)current($row); }
    }
    $stmt->close();
}
$sql = 'SELECT c.created_at, c.source, c.ip, c.utm_source, c.utm_medium, c.utm_campaign, c.landing_path, p.name
        FROM clicks c JOIN products p ON p.id = c.product_id' . $whereSql . ' ORDER BY c.created_at DESC LIMIT ? OFFSET ?';
$stmt = $db->prepare($sql);
$types = '';
if (!empty($params)) { $types = str_repeat('s', count($params)); }
$types .= 'ii';
if (!empty($params)) { $stmt->bind_param($types, ...array_merge($params, [$perPage, $offset])); }
else { $stmt->bind_param($types, $perPage, $offset); }
$stmt->execute();
$rows = [];
if (method_exists($stmt, 'get_result')) {
    $res = $stmt->get_result();
    if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; } }
    $stmt->close();
} else {
    $meta = $stmt->result_metadata();
    if ($meta) {
        $row = [];
        $binds = [];
        $fields = [];
        while ($f = $meta->fetch_field()) { $row[$f->name] = null; $binds[] =& $row[$f->name]; $fields[] = $f->name; }
        call_user_func_array([$stmt, 'bind_result'], $binds);
        while ($stmt->fetch()) {
            $tmp = [];
            foreach ($fields as $name) { $tmp[$name] = $row[$name]; }
            $rows[] = $tmp;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Relatórios • PlugPlay Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand">
        <span class="logo">📈</span>
        <span class="name">Relatórios</span>
      </div>
      <nav class="actions">
        <a class="btn" href="admin.php">Voltar ao admin</a>
        <a class="btn" href="logout.php">Sair</a>
      </nav>
    </div>
  </header>
  <main class="container">
    <h1>Cliques por período</h1>
    <?php // categorias distintas
    $cats = [];
    if ($rc = $db->query('SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> "" ORDER BY category')) { while ($row = $rc->fetch_assoc()) { $cats[] = $row['category']; } $rc->close(); }
    ?>
    <form method="get" class="form" style="grid-template-columns: 1fr 1fr 1fr 1fr; align-items: end;">
      <div class="form-row">
        <label>Início</label>
        <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" />
      </div>
      <div class="form-row">
        <label>Fim</label>
        <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" />
      </div>
      <div class="form-row">
        <label>Fonte</label>
        <input type="text" name="source" value="<?= htmlspecialchars($source) ?>" placeholder="Ex: home, product" />
      </div>
      <div class="form-row">
        <label>Categoria</label>
        <select name="category" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
          <option value="">Todas</option>
          <?php foreach ($cats as $c): $sel = $c===$category?'selected':''; ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= $sel ?>><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>Produto</label>
        <div style="display:flex; gap:8px;">
          <input type="number" name="product_id" value="<?= $product_id>0?htmlspecialchars((string)$product_id):'' ?>" placeholder="ID" />
          <input type="text" name="product_q" value="<?= htmlspecialchars($product_q) ?>" placeholder="Nome contém" />
        </div>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Aplicar</button>
        <a class="btn" href="reports.php">Limpar</a>
        <a class="btn" href="export_clicks.php?start=<?= urlencode($start) ?>&end=<?= urlencode($end) ?>&source=<?= urlencode($source) ?>" target="_blank">Exportar CSV</a>
      </div>
    </form>

    <div class="grid" style="grid-template-columns: repeat(3, 1fr);">
      <div class="card"><div class="card-body"><div class="card-title">Cliques no período</div><div class="price"><?= $periodClicks ?></div></div></div>
      <div class="card"><div class="card-body"><div class="card-title">Início</div><div class="price"><?= $start ? htmlspecialchars($start) : '-' ?></div></div></div>
      <div class="card"><div class="card-body"><div class="card-title">Fim</div><div class="price"><?= $end ? htmlspecialchars($end) : '-' ?></div></div></div>
    </div>

    <div class="card"><div class="card-body">
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="color:#cbd1e6;">
            <th style="text-align:left; padding:6px;">Data</th>
            <th style="text-align:left; padding:6px;">Fonte</th>
            <th style="text-align:left; padding:6px;">Produto</th>
            <th style="text-align:left; padding:6px;">IP</th>
            <th style="text-align:left; padding:6px;">UTM</th>
            <th style="text-align:left; padding:6px;">Landing</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $c): ?>
            <tr>
              <td style="padding:6px;"><?= htmlspecialchars($c['created_at']) ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($c['source']) ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($c['name']) ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($c['ip']) ?></td>
              <td style="padding:6px;">
                <?= htmlspecialchars(($c['utm_source']?:'-') . ' / ' . ($c['utm_medium']?:'-') . ' / ' . ($c['utm_campaign']?:'-')) ?>
              </td>
              <td style="padding:6px;"><?= htmlspecialchars($c['landing_path'] ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $prev = max(1, $page-1); $next = min($totalPages, $page+1);
        $qs = $_GET; $qs['page'] = $prev; $prevUrl = 'reports.php?' . http_build_query($qs);
        $qs['page'] = $next; $nextUrl = 'reports.php?' . http_build_query($qs);
      ?>
      <div class="form-actions" style="margin-top:8px;">
        <a class="btn" href="<?= htmlspecialchars($prevUrl) ?>">Anterior</a>
        <span class="desc">Página <?= $page ?> de <?= $totalPages ?></span>
        <a class="btn" href="<?= htmlspecialchars($nextUrl) ?>">Próxima</a>
      </div>
    </div></div>
  </main>
</body>
</html>