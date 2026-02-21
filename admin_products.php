<?php
// Função helper para escape seguro de HTML (UTF-8)
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

session_start();
require_once __DIR__ . '/config.php';
$db = get_db_soft();
if ($db) { ensure_schema($db); }

$mustLogin = !isset($_SESSION['user_id']);
$notAdmin = (!$mustLogin) && (($_SESSION['role'] ?? 'admin') !== 'admin');

$msg = '';
$type = 'info';

// Processamento do formulário de adição de produto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    if (!check_csrf($_POST['csrf'] ?? null)) { 
        $msg = 'Sessão expirada ou inválida.'; 
        $type = 'error'; 
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priceRaw = trim($_POST['price'] ?? '0');
        $priceClean = preg_replace('/[^0-9.,]/', '', $priceRaw);
        $priceClean = str_replace('.', '', $priceClean);
        $priceClean = str_replace(',', '.', $priceClean);
        $price = $priceClean !== '' ? $priceClean : '0';
        $currency = strtoupper(substr(trim((string)($_POST['currency'] ?? 'USD')), 0, 3));
        if (!in_array($currency, ['USD','BRL','EUR'], true)) { $currency = 'USD'; }
        $category = trim($_POST['category'] ?? '');
        $subcategory = trim($_POST['subcategory'] ?? '');
        $affiliate = trim($_POST['affiliate_url'] ?? '');

        $uploaded_images = [];
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $uploadDir = __DIR__ . '/uploads/products/';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
            $files = $_FILES['product_images'];
            $count = count($files['name']);
            for ($i=0; $i<$count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp = $files['tmp_name'][$i];
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (in_array($ext, $allowed)) {
                        $new = uniqid('product_') . '.' . $ext;
                        $dest = $uploadDir . $new;
                        if (move_uploaded_file($tmp, $dest)) { $uploaded_images[] = '/uploads/products/' . $new; }
                    }
                }
            }
        }

        $images_raw = trim($_POST['image_urls'] ?? '');
        $url_images = [];
        if (!empty($images_raw)) { 
            $images_raw = str_replace([';','\n','\r'], [',',',',''], $images_raw); 
            $url_images = array_filter(array_map('trim', explode(',', $images_raw))); 
        }
        $image_urls = implode(',', array_merge($uploaded_images, $url_images));

        require_once __DIR__ . '/video_helper.php';
        $video_files = $_FILES['product_videos'] ?? [];
        $video_urls_raw = trim($_POST['video_urls'] ?? '');
        $video_urls = process_video_input($video_files, $video_urls_raw);

        $ok = insert_product($db, [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'currency' => $currency,
            'category' => $category,
            'subcategory' => $subcategory,
            'image_urls' => $image_urls,
            'video_urls' => $video_urls,
            'affiliate_url' => $affiliate
        ]);
        if (!$ok) { $msg = 'Falha ao adicionar produto.'; $type = 'error'; }
        else { $msg = 'Produto adicionado com sucesso!'; $type = 'success'; }
    }
}

// Resolver alertas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['op']) && $_POST['op'] === 'resolve_product_alerts') {
    if (!check_csrf($_POST['csrf'] ?? null)) { $msg = 'Sessão expirada.'; $type = 'error'; }
    else {
        $pid = (int)($_POST['pid'] ?? 0);
        if ($pid > 0) {
            $ok = resolve_alerts_for_product($db, $pid);
            if ($ok) { $msg = 'Alertas resolvidos.'; $type = 'success'; }
            else { $msg = 'Falha ao resolver alertas.'; $type = 'error'; }
        }
    }
}

// Configuração de Debug (Persistente via Cookie)
if (isset($_GET['debug'])) { 
    setcookie('debug_sql', $_GET['debug'], time()+3600, '/'); 
    $_COOKIE['debug_sql'] = $_GET['debug']; 
}
if (isset($_GET['debug_off'])) { 
    setcookie('debug_sql', '', time()-3600, '/'); 
    unset($_COOKIE['debug_sql']); 
    unset($_GET['debug']); 
}
$debugMode = $_GET['debug'] ?? ($_COOKIE['debug_sql'] ?? null);

// --- FILTROS ---
$q = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? '');
$catQ = trim($_GET['category_q'] ?? '');

// Tratamento robusto para arrays de categorias/subcategorias
$selCats = [];
if (isset($_GET['categories'])) {
    if (is_array($_GET['categories'])) {
        $selCats = $_GET['categories'];
    } elseif (is_string($_GET['categories'])) {
        $selCats = explode(',', $_GET['categories']);
    }
}
$selCats = array_filter(array_map('trim', $selCats));

$selSubCats = [];
if (isset($_GET['subcategories'])) {
    if (is_array($_GET['subcategories'])) {
        $selSubCats = $_GET['subcategories'];
    } elseif (is_string($_GET['subcategories'])) {
        $selSubCats = explode(',', $_GET['subcategories']);
    }
}
$selSubCats = array_filter(array_map('trim', $selSubCats));

// Preços
$minPriceStr = isset($_GET['min_price']) ? (string)$_GET['min_price'] : '';
$maxPriceStr = isset($_GET['max_price']) ? (string)$_GET['max_price'] : '';
$minPrice = null; $maxPrice = null;
if ($minPriceStr !== '') { 
    $tmp = preg_replace('/[^0-9.,]/', '', $minPriceStr); 
    $tmp = str_replace('.', '', $tmp); 
    $tmp = str_replace(',', '.', $tmp); 
    if ($tmp !== '' && is_numeric($tmp)) { $minPrice = (float)$tmp; } 
}
if ($maxPriceStr !== '') { 
    $tmp = preg_replace('/[^0-9.,]/', '', $maxPriceStr); 
    $tmp = str_replace('.', '', $tmp); 
    $tmp = str_replace(',', '.', $tmp); 
    if ($tmp !== '' && is_numeric($tmp)) { $maxPrice = (float)$tmp; } 
}

// Construção da Query
$whereClauses = [];

// 1. Busca textual (Nome ou Categoria ou Subcategoria - parcial)
if ($q !== '') {
    $safeQ = $db->real_escape_string($q);
    $whereClauses[] = "(p.name LIKE '%$safeQ%' OR p.category LIKE '%$safeQ%' OR p.subcategory LIKE '%$safeQ%')";
}

// 2. Filtro de Categoria (Input texto parcial)
if ($catQ !== '') {
    $safeCatQ = $db->real_escape_string($catQ);
    $whereClauses[] = "p.category LIKE '%$safeCatQ%'";
}

// 3. Taxonomia (Categorias e Subcategorias Selecionadas)
// Lógica: (Categoria ... AND Subcategoria ...)
// Alterado para AND quando ambos são selecionados, para ser mais restritivo e preciso.
$catConditions = [];
if (!empty($selCats)) {
    foreach ($selCats as $c) {
        $safe = $db->real_escape_string($c);
        $catConditions[] = "LOWER(p.category) LIKE LOWER('%$safe%') COLLATE utf8mb4_unicode_ci";
    }
}

$subConditions = [];
if (!empty($selSubCats)) {
    foreach ($selSubCats as $sc) {
        $safe = $db->real_escape_string($sc);
        $subConditions[] = "LOWER(p.subcategory) LIKE LOWER('%$safe%') COLLATE utf8mb4_unicode_ci";
    }
}

// Se tiver Categoria E Subcategoria, usa AND entre os grupos (Cat1 OR Cat2) AND (Sub1 OR Sub2)
if (!empty($catConditions) && !empty($subConditions)) {
    $whereClauses[] = "((" . implode(' OR ', $catConditions) . ") AND (" . implode(' OR ', $subConditions) . "))";
} elseif (!empty($catConditions)) {
    $whereClauses[] = "(" . implode(' OR ', $catConditions) . ")";
} elseif (!empty($subConditions)) {
    $whereClauses[] = "(" . implode(' OR ', $subConditions) . ")";
}

// 4. Moeda
$curSel = isset($_GET['currency']) ? strtoupper(substr((string)$_GET['currency'], 0, 3)) : '';
if (in_array($curSel, ['USD','BRL','EUR'], true)) {
    $whereClauses[] = "p.currency = '" . $db->real_escape_string($curSel) . "'";
}

// 5. Preço
if ($minPrice !== null && $minPrice >= 0) { $whereClauses[] = 'p.price >= ' . (float)$minPrice; }
if ($maxPrice !== null && $maxPrice >= 0) { $whereClauses[] = 'p.price <= ' . (float)$maxPrice; }

// Montagem do SQL
$sqlWhere = '';
if (!empty($whereClauses)) {
    $sqlWhere = ' WHERE ' . implode(' AND ', $whereClauses);
}

// Ordenação
$order = ' ORDER BY p.id DESC ';
if ($sort === 'clicks') $order = ' ORDER BY clicks_count DESC, p.id DESC ';
if ($sort === 'price') $order = ' ORDER BY p.price DESC ';

// Paginação
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(200, (int)$_GET['per_page'])) : 20;
$offset = ($page - 1) * $perPage;

// Simplificando a query principal para evitar erros de subquery ou ordenação
// Removemos a subquery de clicks do SELECT principal se não for ordenar por clicks
if ($sort === 'clicks') {
    $sqlList = 'SELECT p.*, (SELECT COUNT(*) FROM clicks WHERE product_id = p.id) as clicks_count FROM products p';
} else {
    // Se não ordena por clicks, não precisamos do subselect custoso agora (podemos pegar depois ou deixar 0)
    // Mas para manter compatibilidade visual, vamos manter, mas simplificar a query de contagem
    $sqlList = 'SELECT p.*, (SELECT COUNT(*) FROM clicks WHERE product_id = p.id) as clicks_count FROM products p';
}

$sqlCount = 'SELECT COUNT(*) as c FROM products p' . $sqlWhere;

// Executa Contagem
$totalRows = 0;
if ($rc = $db->query($sqlCount)) { $r = $rc->fetch_assoc(); $totalRows = (int)$r['c']; $rc->close(); }
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Executa Listagem
$sqlList .= $sqlWhere . $order . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

// Debug
if ($debugMode === 'sql') {
    echo "<div class='debug-box'><strong>DEBUG SQL:</strong>\n" . htmlspecialchars($sqlList) . "\n\n<strong>GET:</strong>\n"; var_dump($_GET); echo "\n\n<strong>Rows Found in Count:</strong> $totalRows</div>";
}

$productsList = [];
if ($res = $db->query($sqlList)) { 
    while ($row = $res->fetch_assoc()) { $productsList[] = $row; } 
    $res->close(); 
} else {
    // Se falhar, tenta query ultra simples de fallback
    if ($debugMode === 'sql') echo "<div class='notice error'>Query falhou: " . $db->error . ". Tentando fallback...</div>";
    $sqlFallback = "SELECT * FROM products p " . $sqlWhere . " ORDER BY id DESC LIMIT " . (int)$perPage;
    if ($res = $db->query($sqlFallback)) { while ($row = $res->fetch_assoc()) { $productsList[] = $row; } }
}

// Analytics (para o dashboard no topo)
$analytics = $db ? get_analytics($db) : ['total_products'=>0,'total_clicks'=>0,'avg_price'=>0,'min_price'=>0,'max_price'=>0,'categories'=>[],'top_products'=>[],'recent_clicks'=>[]];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin • Produtos</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
  <style>
      /* Ajustes específicos para o painel admin */
      .admin-filter-form {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 16px;
          align-items: end;
          background: var(--card);
          padding: 20px;
          border-radius: 16px;
          border: 1px solid var(--border);
      }
      .full-width { grid-column: 1 / -1; }
      .select-multiple {
          min-height: 120px;
          padding: 8px;
          background: #121527;
      }
      .debug-box {
          background: #2a0a0a;
          color: #ff9999;
          padding: 15px;
          border: 1px solid #cc0000;
          margin: 10px 0;
          font-family: monospace;
          white-space: pre-wrap;
          border-radius: 8px;
      }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand"><span class="logo">📦</span><span class="name">Produtos</span></div>
      <nav class="actions">
          <a class="btn" href="admin.php">Dashboard</a>
          <a class="btn" href="admin_advertisers.php">Anunciantes</a>
          <a class="btn" href="admin_affiliates.php">Afiliados</a>
          <a class="btn" href="settings.php">Configurações</a>
          <a class="btn" href="logout.php">Sair</a>
      </nav>
    </div>
  </header>

  <main class="container">
    <h1>Gestão de produtos</h1>
    
    <?php if ($msg): ?>
        <div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($debugMode === 'sql'): ?>
        <div class="debug-box">
            <strong>DEBUG SQL:</strong>
            <?= htmlspecialchars($sqlList) ?>

            <strong>GET PARAMS:</strong>
            <?php var_dump($_GET); ?>

            <strong>CATEGORY STATS (DB):</strong>
            <?php
            $stats = [];
            if ($r = $db->query("SELECT category, COUNT(*) as c FROM products GROUP BY category")) {
                while($row = $r->fetch_assoc()) { $stats[] = $row['category'] . ' (' . $row['c'] . ')'; }
            }
            echo implode(', ', $stats);
            ?>
        </div>
    <?php endif; ?>

    <?php if ($mustLogin): ?>
        <div class="notice error">Você precisa estar logado como administrador. <a class="btn" href="login.php">Ir para login</a></div>
    <?php elseif ($notAdmin): ?>
        <div class="notice error">Acesso restrito a administradores. <a class="btn" href="index.php">Voltar</a></div>
    <?php else: ?>

    <!-- Dashboard Rápido -->
    <section>
      <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
        <div class="card"><div class="card-body"><div class="card-title">Produtos</div><div class="price"><?= (int)$analytics['total_products'] ?></div></div></div>
        <div class="card"><div class="card-body"><div class="card-title">Cliques</div><div class="price"><?= (int)$analytics['total_clicks'] ?></div></div></div>
        <div class="card"><div class="card-body"><div class="card-title">Preço médio</div><div class="price">R$ <?= number_format((float)$analytics['avg_price'], 2, ',', '.') ?></div></div></div>
      </div>
    </section>

    <!-- Formulário de Novo Produto -->
    <section>
      <h2>Cadastrar novo produto</h2>
      <form method="post" class="form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <div class="form-row"><label>Nome</label><input type="text" name="name" required placeholder="Ex: Fone Bluetooth Pro" /></div>
        <div class="form-row"><label>Descrição</label><textarea name="description" rows="4" placeholder="Destaques..."></textarea></div>
        <div class="form-row"><label>Preço</label><input type="text" inputmode="decimal" name="price" required placeholder="0,00" /></div>
        <div class="form-row"><label>Moeda</label>
            <select name="currency" style="height:42px;"><option value="USD" selected>USD (US$)</option><option value="BRL">BRL (R$)</option><option value="EUR">EUR (€)</option></select>
        </div>
        <div class="form-row"><label>Categoria</label><input type="text" name="category" placeholder="Ex: Eletrônicos" /></div>
        <div class="form-row"><label>Subcategoria</label><input type="text" name="subcategory" placeholder="Ex: Smartphones" /></div>
        <div class="form-row"><label>Imagens (Upload)</label><input type="file" name="product_images[]" multiple accept="image/*" /></div>
        <div class="form-row"><label>Ou URLs de Imagem</label><textarea name="image_urls" rows="2" placeholder="https://..."></textarea></div>
        <div class="form-row"><label>Vídeos (Upload)</label><input type="file" name="product_videos[]" multiple accept="video/*" /></div>
        <div class="form-row"><label>Ou URLs de Vídeo</label><textarea name="video_urls" rows="2" placeholder="https://..."></textarea></div>
        <div class="form-row"><label>Link de Afiliado</label><input type="url" name="affiliate_url" placeholder="https://..." /></div>
        <div class="form-actions"><button class="btn primary" type="submit">Salvar produto</button></div>
      </form>
    </section>

    <!-- Listagem e Filtros -->
    <section id="produtos">
      <h2>Lista de Produtos</h2>
      
      <?php
        // Carrega opções para os selects
        $cats = [];
        if ($rc = $db->query('SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> "" ORDER BY category')) { 
            while ($row = $rc->fetch_assoc()) { 
                $c = trim($row['category']);
                if ($c !== '' && !in_array($c, $cats)) { $cats[] = $c; }
            } 
            $rc->close(); 
        }
        sort($cats);

        $subcats = [];
        if ($rc = $db->query('SELECT DISTINCT subcategory FROM products WHERE subcategory IS NOT NULL AND subcategory <> "" ORDER BY subcategory')) { 
            while ($row = $rc->fetch_assoc()) { 
                $s = trim($row['subcategory']);
                if ($s !== '' && !in_array($s, $subcats)) { $subcats[] = $s; }
            } 
            $rc->close(); 
        }
        sort($subcats);
      ?>

      <form method="get" action="admin_products.php" class="admin-filter-form">
        <!-- Linha 1: Busca Texto e Categoria Texto -->
        <div class="form-row full-width">
            <label>Buscar (Nome, Categoria, Subcategoria)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Digite para buscar..." />
        </div>

        <!-- Linha 2: Selects de Taxonomia -->
        <div class="form-row">
            <label>Categorias (Selecione uma ou mais)</label>
            <select name="categories[]" multiple class="select-multiple">
                <?php foreach ($cats as $c): $sel = in_array($c, $selCats, true) ? 'selected' : ''; ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $sel ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Subcategorias (Selecione uma ou mais)</label>
            <select name="subcategories[]" multiple class="select-multiple">
                <?php foreach ($subcats as $sc): $sel = in_array($sc, $selSubCats, true) ? 'selected' : ''; ?>
                    <option value="<?= htmlspecialchars($sc) ?>" <?= $sel ?>><?= htmlspecialchars($sc) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Linha 3: Filtros Menores -->
        <div class="form-row">
            <label>Moeda</label>
            <select name="currency" style="height:42px;">
                <option value="">Todas</option>
                <option value="USD" <?= $curSel==='USD'?'selected':''; ?>>USD</option>
                <option value="BRL" <?= $curSel==='BRL'?'selected':''; ?>>BRL</option>
                <option value="EUR" <?= $curSel==='EUR'?'selected':''; ?>>EUR</option>
            </select>
        </div>
        <div class="form-row">
            <label>Ordenar por</label>
            <select name="sort" style="height:42px;">
                <option value="">Recentes</option>
                <option value="clicks" <?= $sort==='clicks'?'selected':''; ?>>Cliques</option>
                <option value="price" <?= $sort==='price'?'selected':''; ?>>Preço</option>
            </select>
        </div>
        <div class="form-row">
            <label>Preço Mín/Máx</label>
            <div style="display:flex; gap:5px;">
                <input type="text" name="min_price" value="<?= htmlspecialchars($minPriceStr) ?>" placeholder="Min" />
                <input type="text" name="max_price" value="<?= htmlspecialchars($maxPriceStr) ?>" placeholder="Max" />
            </div>
        </div>
        <div class="form-row">
            <label>Itens/Pág</label>
            <select name="per_page" style="height:42px;">
                <option value="20" <?= $perPage===20?'selected':''; ?>>20</option>
                <option value="50" <?= $perPage===50?'selected':''; ?>>50</option>
                <option value="100" <?= $perPage===100?'selected':''; ?>>100</option>
            </select>
        </div>

        <!-- Botões -->
        <div class="form-actions full-width" style="margin-top: 10px;">
            <?php if(isset($_GET['debug'])): ?>
                <input type="hidden" name="debug" value="<?= htmlspecialchars($_GET['debug']) ?>">
            <?php endif; ?>
            
            <button class="btn primary" type="submit">Aplicar Filtros</button>
            <a class="btn" href="admin_products.php">Limpar Filtros</a>
            <a class="btn" href="export_products.php?<?= http_build_query($_GET) ?>" target="_blank">Exportar CSV</a>
        </div>
      </form>

      <!-- Chips de Filtros Ativos -->
      <?php if (!empty($selCats) || !empty($selSubCats) || $q !== '' || $minPrice || $maxPrice): ?>
      <div class="chips" style="justify-content:flex-start; margin: 15px 0;">
          <?php if($q): ?><span class="chip active">Busca: <?= htmlspecialchars($q) ?></span><?php endif; ?>
          <?php foreach($selCats as $c): ?><span class="chip active">Cat: <?= htmlspecialchars($c) ?></span><?php endforeach; ?>
          <?php foreach($selSubCats as $sc): ?><span class="chip active">Sub: <?= htmlspecialchars($sc) ?></span><?php endforeach; ?>
          <?php if($minPrice): ?><span class="chip active">Min: <?= $minPrice ?></span><?php endif; ?>
          <?php if($maxPrice): ?><span class="chip active">Max: <?= $maxPrice ?></span><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Tabela de Resultados -->
      <div class="card" style="margin-top:20px;">
          <div class="card-body" style="overflow-x:auto;">
            <div style="padding:10px; font-weight:bold; color:var(--primary);">
                Encontrados: <?= (int)$totalRows ?> produtos
                <?php if($totalRows > $perPage): ?>
                    (Exibindo <?= (int)$offset + 1 ?> a <?= min((int)$totalRows, (int)$offset + (int)$perPage) ?>)
                <?php endif; ?>
            </div>
            <table style="width:100%; border-collapse:collapse; min-width:600px;">
              <thead>
                <tr style="color:#cbd1e6; border-bottom:1px solid var(--border);">
                  <th style="text-align:left; padding:10px;">ID</th>
                  <th style="text-align:left; padding:10px;">Nome</th>
                  <th style="text-align:left; padding:10px;">Categoria</th>
                  <th style="text-align:left; padding:10px;">Subcategoria</th>
                  <th style="text-align:right; padding:10px;">Preço</th>
                  <th style="text-align:right; padding:10px;">Cliques</th>
                  <th style="text-align:right; padding:10px;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($productsList)): ?>
                    <tr><td colspan="7" style="padding:20px; text-align:center; color:var(--muted);">Nenhum produto encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($productsList as $index => $p): ?>
                    <!-- DEBUG: Start Row <?= $index ?> (ID: <?= $p['id'] ?>) -->
                    <tr style="border-bottom:1px solid #222642;">
                      <td style="padding:10px;">#<?= (int)$p['id'] ?></td>
                      <td style="padding:10px;">
                          <?= h($p['name']) ?>
                          <?php 
                            // Tenta exibir alerta de forma segura, se falhar, ignora
                            try {
                                if (function_exists('product_unresolved_alert_count') && product_unresolved_alert_count($db, (int)$p['id']) > 0) {
                                    echo '<span class="badge" style="background:#c00; color:#fff;">!</span>';
                                }
                            } catch (Throwable $e) {
                                // Ignora erro no alerta para não quebrar a tabela
                                echo "<!-- Erro alerta: " . $e->getMessage() . " -->";
                            }
                          ?>
                      </td>
                      <td style="padding:10px;"><?= h($p['category']) ?></td>
                      <td style="padding:10px;"><?= h($p['subcategory']) ?></td>
                      <td style="padding:10px; text-align:right;">
                          <?php
                            try {
                                echo h(format_money((float)$p['price'], (string)($p['currency'] ?? 'USD')));
                            } catch (Throwable $e) {
                                echo h($p['price']); // Fallback
                            }
                          ?>
                      </td>
                      <td style="padding:10px; text-align:right; font-weight:bold;"><?= isset($p['clicks_count']) ? (int)$p['clicks_count'] : 0 ?></td>
                      <td style="padding:10px; text-align:right; display:flex; gap:5px; justify-content:flex-end;">
                        <a class="btn" style="padding:0 8px; height:30px;" href="product.php?id=<?= (int)$p['id'] ?>" target="_blank">Ver</a>
                        <a class="btn" style="padding:0 8px; height:30px;" href="edit_product.php?id=<?= (int)$p['id'] ?>">Editar</a>
                        <form method="post" action="delete_product.php" style="display:inline;" onsubmit="return confirm('Excluir?');">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <button class="btn" style="padding:0 8px; height:30px; background:#c00; color:#fff; border:none;" type="submit">X</button>
                        </form>
                      </td>
                    </tr>
                    <!-- DEBUG: End Row <?= $index ?> -->
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
      </div>

      <!-- Paginação -->
      <?php if ($totalPages > 1): ?>
      <div class="form-actions" style="margin-top:20px; justify-content:center; flex-wrap:wrap;">
        <?php 
            $qStr = $_GET; 
            unset($qStr['page']); 
            $baseLink = 'admin_products.php?' . http_build_query($qStr) . '&page=';
        ?>
        <a class="btn" href="<?= $baseLink . max(1, $page-1) ?>" <?= $page<=1?'disabled style="opacity:0.5"':'' ?>>« Ant</a>
        
        <?php for($i=max(1, $page-2); $i<=min($totalPages, $page+2); $i++): ?>
            <a class="btn <?= $i===$page?'primary':'' ?>" href="<?= $baseLink . $i ?>"><?= $i ?></a>
        <?php endfor; ?>
        
        <a class="btn" href="<?= $baseLink . min($totalPages, $page+1) ?>" <?= $page>=$totalPages?'disabled style="opacity:0.5"':'' ?>>Prox »</a>
      </div>
      <?php endif; ?>

    </section>
    <?php endif; ?>
  </main>
</body>
</html>
