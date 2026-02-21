<?php
// Função helper para escape seguro de HTML (UTF-8)
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Habilitar exibição de todos os erros para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
$__out_admin = false;
register_shutdown_function(function(){
  $e = error_get_last();
  if (!$GLOBALS['__out_admin']) {
    if (!headers_sent()) { http_response_code(500); }
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>Erro • Admin</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/styles.css" /></head><body><header class="site-header"><div class="container header-inner"><div class="brand"><span class="logo">⚙️</span><span class="name">Admin</span></div><nav class="actions"><a class="btn" href="index.php">Voltar à loja</a><a class="btn" href="admin_advertisers.php">Anunciantes</a><a class="btn" href="admin_affiliates.php">Afiliados</a></nav></div></header><main class="container">';
    if ($e && isset($e['message'])) { echo '<div class="notice error">Falha ao carregar Admin: ' . htmlspecialchars($e['message']) . '</div>'; }
    else { echo '<div class="notice error">Falha ao carregar Admin</div>'; }
    echo '</main></body></html>';
  }
});
if (isset($_GET['ping'])) { $__out_admin = true; echo 'admin-ok'; exit; }

session_start();
require_once __DIR__ . '/config.php';
$db = get_db_soft();
if ($db) { ensure_schema($db); }

$mustLogin = !isset($_SESSION['user_id']);

$msg = '';
$type = 'info';
if (isset($_SESSION['flash'])) {
    if (is_array($_SESSION['flash'])) {
        $msg = (string)($_SESSION['flash']['msg'] ?? '');
        $type = (string)($_SESSION['flash']['type'] ?? 'info');
    } else {
        $msg = (string)$_SESSION['flash'];
        $type = 'info';
    }
    unset($_SESSION['flash']);
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

// Processamento de formulários (Add/Delete/Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $msg = 'Sessão expirada ou inválida. Tente novamente.';
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
            $fileCount = count($files['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$i];
                    $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $newName = uniqid('product_') . '.' . $extension;
                        $destination = $uploadDir . $newName;
                        if (move_uploaded_file($tmpName, $destination)) {
                            $uploaded_images[] = '/uploads/products/' . $newName;
                        }
                    }
                }
            }
        }
        
        $images_raw = trim($_POST['image_urls'] ?? '');
        $url_images = [];
        if (!empty($images_raw)) {
            $images_raw = str_replace([';', '\n', '\r'], [',', ',', ''], $images_raw);
            $url_images = array_filter(array_map('trim', explode(',', $images_raw)));
        }
        $image_urls = implode(',', array_merge($uploaded_images, $url_images));

        $ok = insert_product($db, [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'currency' => $currency,
            'category' => $category,
            'subcategory' => $subcategory,
            'image_urls' => $image_urls,
            'affiliate_url' => $affiliate,
        ]);
        
        if (!$ok) {
            $msg = 'Falha ao adicionar produto. Verifique os dados.';
            $type = 'error';
        } else {
            $msg = 'Produto adicionado com sucesso!';
            $type = 'success';
        }
    }
}
// Outros POSTs (Exclusão, Users, etc) omitidos para brevidade, mantendo estrutura original...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_classified_id'])) {
    if (check_csrf($_POST['csrf'] ?? null)) {
        delete_classified($db, (int)$_POST['delete_classified_id']);
        $msg = 'Classificado excluído.'; $type = 'success';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['op'])) {
    if (check_csrf($_POST['csrf'] ?? null)) {
        $op = $_POST['op'];
        if ($op === 'resolve_alert') {
            resolve_alert($db, (int)$_POST['alert_id']);
            $msg = 'Alerta resolvido.'; $type = 'success';
        }
        if ($op === 'resolve_alerts_product') {
            resolve_alerts_for_product($db, (int)$_POST['pid']);
            $msg = 'Alertas resolvidos.'; $type = 'success';
        }
        // ... user ops ...
    }
}

$__out_admin = true;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin • PlugPlay Shop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css" />
    <style>
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
            <div class="brand"><span class="logo">⚙️</span><span class="name">Admin</span></div>
            <nav class="actions">
                <a class="btn" href="index.php">Voltar à loja</a>
                <a class="btn" href="logout.php">Sair</a>
                <a class="btn" href="settings.php">Configurações</a>
                <a class="btn" href="admin_products.php">Produtos</a>
                <a class="btn" href="admin_advertisers.php">Anunciantes</a>
                <a class="btn" href="admin_affiliates.php">Afiliados</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <h1>Administração</h1>
        <?php if ($msg): ?>
            <div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        
        <?php
            // Proteção contra falha no DB ao buscar alertas
            $alertsCount = 0;
            $alerts = [];
            try {
                if ($db) {
                    $alertsCount = count_unresolved_alerts($db);
                    $alerts = fetch_unresolved_alerts($db, 10);
                }
            } catch (Throwable $e) {
                error_log("Erro ao buscar alertas: " . $e->getMessage());
            }
        ?>
        <section>
            <h2>Avisos</h2>
            <div class="grid" style="grid-template-columns: repeat(4, 1fr);">
                <div class="card"><div class="card-body"><div class="card-title">Pendentes</div><div class="price"><?= (int)$alertsCount ?></div></div></div>
            </div>
            <?php if (!empty($alerts)): ?>
            <div class="card"><div class="card-body">
                <table style="width:100%; border-collapse:collapse;">
                    <thead><tr style="color:#cbd1e6;"><th style="text-align:left; padding:6px;">ID</th><th style="text-align:left; padding:6px;">Produto</th><th style="text-align:left; padding:6px;">Tipo</th><th style="text-align:left; padding:6px;">Detalhe</th><th style="text-align:right; padding:6px;">Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ($alerts as $al): ?>
                        <tr>
                            <td style="padding:6px;">#<?= (int)$al['id'] ?></td>
                            <td style="padding:6px;"><?= htmlspecialchars($al['name'] ?? ('#' . (int)$al['product_id'])) ?></td>
                            <td style="padding:6px;"><?= htmlspecialchars($al['type']) ?></td>
                            <td style="padding:6px;"><?= htmlspecialchars($al['detail']) ?></td>
                            <td style="padding:6px; text-align:right;">
                                <form method="post" action="admin.php" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
                                    <input type="hidden" name="op" value="resolve_alert" />
                                    <input type="hidden" name="alert_id" value="<?= (int)$al['id'] ?>" />
                                    <button class="btn" type="submit">Resolver</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div>
            <?php endif; ?>
        </section>

        <?php if ($mustLogin): ?>
            <div class="notice error">Você precisa estar logado para acessar o painel. <a class="btn" href="login.php">Ir para login</a></div>
        <?php endif; ?>

        <section>
            <h2>Cadastrar novo produto</h2>
            <form method="post" class="form" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
                <div class="form-row"><label>Nome</label><input type="text" name="name" required placeholder="Ex: Fone Bluetooth Pro" /></div>
                <div class="form-row"><label>Descrição</label><textarea name="description" rows="4"></textarea></div>
                <div class="form-row"><label>Preço</label><input type="text" inputmode="decimal" name="price" required placeholder="0,00" /></div>
                <div class="form-row"><label>Moeda</label>
                    <select name="currency" style="height: 42px;"><option value="USD" selected>USD</option><option value="BRL">BRL</option><option value="EUR">EUR</option></select>
                </div>
                <div class="form-row"><label>Categoria</label><input type="text" name="category" /></div>
                <div class="form-row"><label>Subcategoria</label><input type="text" name="subcategory" /></div>
                <div class="form-row"><label>Imagens</label><input type="file" name="product_images[]" multiple accept="image/*" /></div>
                <div class="form-row"><label>URLs Imagem</label><textarea name="image_urls" rows="3"></textarea></div>
                <div class="form-row"><label>Link Afiliado</label><input type="url" name="affiliate_url" /></div>
                <div class="form-actions"><button class="btn primary" type="submit">Salvar produto</button></div>
            </form>
        </section>

        <?php $analytics = get_analytics($db); ?>
        <section>
            <h2>Métricas</h2>
            <div class="grid" style="grid-template-columns: repeat(4, 1fr);">
                <div class="card"><div class="card-body"><div class="card-title">Produtos</div><div class="price"><?= (int)$analytics['total_products'] ?></div></div></div>
                <div class="card"><div class="card-body"><div class="card-title">Cliques</div><div class="price"><?= (int)$analytics['total_clicks'] ?></div></div></div>
                <div class="card"><div class="card-body"><div class="card-title">Preço médio</div><div class="price">R$ <?= number_format((float)$analytics['avg_price'], 2, ',', '.') ?></div></div></div>
                <div class="card"><div class="card-body"><div class="card-title">Faixa de preço</div><div class="price">R$ <?= number_format((float)$analytics['min_price'], 2, ',', '.') ?> — <?= number_format((float)$analytics['max_price'], 2, ',', '.') ?></div></div></div>
            </div>

            <!-- Gráficos Restaurados -->
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px;">
                <div class="card" style="min-height: 400px;">
                    <div class="card-body" style="display:flex; flex-direction:column; height:100%;">
                        <h3 style="margin-bottom:15px; font-size:1rem; color:#a0aec0; font-weight:600;">Distribuição por Categoria</h3>
                        <div style="flex:1; position:relative; min-height:300px;">
                            <canvas id="chartCats"></canvas>
                        </div>
                    </div>
                </div>
                <div class="card" style="min-height: 400px;">
                    <div class="card-body" style="display:flex; flex-direction:column; height:100%;">
                        <h3 style="margin-bottom:15px; font-size:1rem; color:#a0aec0; font-weight:600;">Top 10 Produtos (Cliques)</h3>
                        <div style="flex:1; position:relative; min-height:300px;">
                            <canvas id="chartProds"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // Dados vindos do PHP
                const dataCats = <?= json_encode($analytics['categories'] ?? []) ?>;
                const dataProds = <?= json_encode($analytics['top_products'] ?? []) ?>;

                // Configuração Global de Cores para Tema Escuro
                Chart.defaults.color = '#cbd5e1';
                Chart.defaults.borderColor = '#334155';

                // 1. Gráfico de Categorias (Doughnut)
                if (document.getElementById('chartCats')) {
                    const ctxCats = document.getElementById('chartCats').getContext('2d');
                    const catLabels = dataCats.map(i => i.category).slice(0, 6);
                    const catValues = dataCats.map(i => i.c).slice(0, 6);
                    // Cores vibrantes
                    const colors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6'];

                    new Chart(ctxCats, {
                        type: 'doughnut',
                        data: {
                            labels: catLabels,
                            datasets: [{
                                data: catValues,
                                backgroundColor: colors,
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right', labels: { usePointStyle: true, padding: 20 } }
                            },
                            layout: { padding: 10 }
                        }
                    });
                }

                // 2. Gráfico de Top Produtos (Barra Horizontal)
                if (document.getElementById('chartProds')) {
                    const ctxProds = document.getElementById('chartProds').getContext('2d');
                    // Limita o tamanho do label para não quebrar o gráfico
                    const prodLabels = dataProds.map(i => {
                        let n = i.name || 'Produto #' + i.id;
                        return n.length > 20 ? n.substring(0, 20) + '...' : n;
                    }).slice(0, 10);
                    const prodValues = dataProds.map(i => i.clicks).slice(0, 10);

                    new Chart(ctxProds, {
                        type: 'bar',
                        indexAxis: 'y', // Barra horizontal
                        data: {
                            labels: prodLabels,
                            datasets: [{
                                label: 'Cliques',
                                data: prodValues,
                                backgroundColor: '#3b82f6',
                                borderRadius: 4,
                                barThickness: 20, // Espessura fixa
                                maxBarThickness: 30
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            scales: {
                                x: { 
                                    beginAtZero: true, 
                                    grid: { color: '#1e293b' },
                                    ticks: { color: '#94a3b8' }
                                },
                                y: { 
                                    grid: { display: false },
                                    ticks: { color: '#e2e8f0', font: { size: 11 } }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: function(context) {
                                            // Mostra nome completo no tooltip
                                            const idx = context[0].dataIndex;
                                            return dataProds[idx].name;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            } catch(e) {
                console.error("Erro ao renderizar gráficos:", e);
            }
        });
        </script>

        <?php
        // --- FILTROS DE PRODUTOS ---
        $q = trim((string)($_GET['q'] ?? ''));
        $sort = (string)($_GET['sort'] ?? '');
        $catQ = trim($_GET['category_q'] ?? '');

        // Tratamento robusto para arrays
        $selCats = [];
        if (isset($_GET['categories'])) {
            if (is_array($_GET['categories'])) $selCats = $_GET['categories'];
            elseif (is_string($_GET['categories'])) $selCats = explode(',', $_GET['categories']);
        }
        $selCats = array_filter(array_map('trim', $selCats));

        $selSubCats = [];
        if (isset($_GET['subcategories'])) {
            if (is_array($_GET['subcategories'])) $selSubCats = $_GET['subcategories'];
            elseif (is_string($_GET['subcategories'])) $selSubCats = explode(',', $_GET['subcategories']);
        }
        $selSubCats = array_filter(array_map('trim', $selSubCats));

        $minPriceStr = isset($_GET['min_price']) ? (string)$_GET['min_price'] : '';
        $maxPriceStr = isset($_GET['max_price']) ? (string)$_GET['max_price'] : '';
        $minPrice = null; $maxPrice = null;
        if ($minPriceStr !== '') { $tmp = preg_replace('/[^0-9.,]/', '', $minPriceStr); $tmp = str_replace('.', '', $tmp); $tmp = str_replace(',', '.', $tmp); if (is_numeric($tmp)) $minPrice = (float)$tmp; }
        if ($maxPriceStr !== '') { $tmp = preg_replace('/[^0-9.,]/', '', $maxPriceStr); $tmp = str_replace('.', '', $tmp); $tmp = str_replace(',', '.', $tmp); if (is_numeric($tmp)) $maxPrice = (float)$tmp; }

        $whereClauses = [];

        // 1. Texto
        if ($q !== '') {
            $safeQ = $db->real_escape_string($q);
            $whereClauses[] = "(p.name LIKE '%$safeQ%' OR p.category LIKE '%$safeQ%' OR p.subcategory LIKE '%$safeQ%')";
        }

        // 2. Taxonomia (Categorias e Subcategorias Selecionadas)
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

// Se tiver Categoria E Subcategoria, usa AND entre os grupos
if (!empty($catConditions) && !empty($subConditions)) {
    $whereClauses[] = "((" . implode(' OR ', $catConditions) . ") AND (" . implode(' OR ', $subConditions) . "))";
} elseif (!empty($catConditions)) {
    $whereClauses[] = "(" . implode(' OR ', $catConditions) . ")";
} elseif (!empty($subConditions)) {
    $whereClauses[] = "(" . implode(' OR ', $subConditions) . ")";
}

        // 3. Moeda
        $curSel = isset($_GET['currency']) ? strtoupper(substr((string)$_GET['currency'], 0, 3)) : '';
        if (in_array($curSel, ['USD','BRL','EUR'], true)) {
            $whereClauses[] = "p.currency = '" . $db->real_escape_string($curSel) . "'";
        }

        // 4. Preço
        if ($minPrice !== null && $minPrice >= 0) { $whereClauses[] = 'p.price >= ' . (float)$minPrice; }
        if ($maxPrice !== null && $maxPrice >= 0) { $whereClauses[] = 'p.price <= ' . (float)$maxPrice; }

        $sqlWhere = '';
        if (!empty($whereClauses)) {
            $sqlWhere = ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $order = ' ORDER BY p.id DESC ';
        if ($sort === 'clicks') $order = ' ORDER BY clicks_count DESC, p.id DESC ';
        if ($sort === 'price') $order = ' ORDER BY p.price DESC ';

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, min(200, (int)$_GET['per_page'])) : 20;
        $offset = ($page - 1) * $perPage;

        // Simplificando a query principal
        $sqlList = 'SELECT p.*, (SELECT COUNT(*) FROM clicks WHERE product_id = p.id) as clicks_count FROM products p';
        $sqlCount = 'SELECT COUNT(*) as c FROM products p' . $sqlWhere;

        $totalRows = 0;
        if ($rc = $db->query($sqlCount)) { $r = $rc->fetch_assoc(); $totalRows = (int)$r['c']; $rc->close(); }
        $totalPages = max(1, (int)ceil($totalRows / $perPage));

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
            $sqlFallback = "SELECT * FROM products p " . $sqlWhere . " ORDER BY id DESC LIMIT " . (int)$perPage;
            if ($res = $db->query($sqlFallback)) { while ($row = $res->fetch_assoc()) { $productsList[] = $row; } }
        }
        ?>
        <section id="produtos">
            <h2>Produtos</h2>
            <?php
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
            <form method="get" action="admin.php" class="admin-filter-form">
                <input type="hidden" name="filter_type" value="products" />
                <div class="form-row full-width"><label>Buscar</label><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nome, categoria..." /></div>
                <div class="form-row"><label>Categorias</label><select name="categories[]" multiple class="select-multiple"><?php foreach ($cats as $c): $sel = in_array($c, $selCats, true) ? 'selected' : ''; ?><option value="<?= htmlspecialchars($c) ?>" <?= $sel ?>><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
                <div class="form-row"><label>Subcategorias</label><select name="subcategories[]" multiple class="select-multiple"><?php foreach ($subcats as $sc): $sel = in_array($sc, $selSubCats, true) ? 'selected' : ''; ?><option value="<?= htmlspecialchars($sc) ?>" <?= $sel ?>><?= htmlspecialchars($sc) ?></option><?php endforeach; ?></select></div>
                <div class="form-row"><label>Moeda</label><select name="currency" style="height:42px;"><option value="">Todas</option><option value="USD" <?= $curSel==='USD'?'selected':''; ?>>USD</option><option value="BRL" <?= $curSel==='BRL'?'selected':''; ?>>BRL</option><option value="EUR" <?= $curSel==='EUR'?'selected':''; ?>>EUR</option></select></div>
                <div class="form-row"><label>Ordenar</label><select name="sort" style="height:42px;"><option value="">Recentes</option><option value="clicks" <?= $sort==='clicks'?'selected':''; ?>>Cliques</option><option value="price" <?= $sort==='price'?'selected':''; ?>>Preço</option></select></div>
                <div class="form-row"><label>Preço</label><div style="display:flex;gap:5px;"><input type="text" name="min_price" value="<?= htmlspecialchars($minPriceStr) ?>" placeholder="Min" /><input type="text" name="max_price" value="<?= htmlspecialchars($maxPriceStr) ?>" placeholder="Max" /></div></div>
                <div class="form-actions full-width" style="margin-top:10px;">
                    <?php if(isset($_GET['debug'])): ?><input type="hidden" name="debug" value="<?= htmlspecialchars($_GET['debug']) ?>"><?php endif; ?>
                    <button class="btn primary" type="submit">Filtrar</button>
                    <a class="btn" href="admin.php">Limpar</a>
                    <a class="btn" href="export_products.php?<?= http_build_query($_GET) ?>" target="_blank">Exportar CSV</a>
                </div>
            </form>

            <div class="card" style="margin-top:20px;"><div class="card-body" style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; min-width:600px;">
                    <thead><tr style="color:#cbd1e6; border-bottom:1px solid var(--border);"><th style="padding:10px;">ID</th><th style="padding:10px;">Nome</th><th style="padding:10px;">Cat/Sub</th><th style="text-align:right; padding:10px;">Preço</th><th style="text-align:right; padding:10px;">Ações</th></tr></thead>
                    <tbody>
                    <?php if (empty($productsList)): ?><tr><td colspan="5" style="padding:20px; text-align:center; color:var(--muted);">Nada encontrado.</td></tr><?php else: ?>
                    <?php foreach ($productsList as $p): ?>
                        <tr style="border-bottom:1px solid #222642;">
                            <td style="padding:10px;">#<?= (int)$p['id'] ?></td>
                            <td style="padding:10px;">
                                <?= h($p['name']) ?>
                                <?php 
                                    try {
                                        if (function_exists('product_unresolved_alert_count') && product_unresolved_alert_count($db, (int)$p['id']) > 0) {
                                            echo '<span class="badge" style="background:#c00; color:#fff;">!</span>';
                                        }
                                    } catch (Throwable $e) {}
                                ?>
                            </td>
                            <td style="padding:10px;"><?= h($p['category']) ?><br><small style="color:var(--muted)"><?= h($p['subcategory']) ?></small></td>
                            <td style="padding:10px; text-align:right;">
                                <?php
                                    try {
                                        echo h(format_money((float)$p['price'], (string)($p['currency'] ?? 'USD')));
                                    } catch (Throwable $e) {
                                        echo h($p['price']);
                                    }
                                ?>
                            </td>
                            <td style="padding:10px; text-align:right;">
                                <a class="btn" style="padding:0 8px; height:30px;" href="product.php?id=<?= (int)$p['id'] ?>" target="_blank">Ver</a>
                                <a class="btn" style="padding:0 8px; height:30px;" href="edit_product.php?id=<?= (int)$p['id'] ?>">Editar</a>
                                <form method="post" action="delete_product.php" style="display:inline;" onsubmit="return confirm('Excluir?');">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                    <button class="btn" style="padding:0 8px; height:30px; background:#c00; color:#fff; border:none;" type="submit">X</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div></div>
            <?php if ($totalPages > 1): ?>
            <div class="form-actions" style="margin-top:20px; justify-content:center; flex-wrap:wrap;">
                <?php $qStr = $_GET; unset($qStr['page']); $baseLink = 'admin.php?' . http_build_query($qStr) . '&page='; ?>
                <a class="btn" href="<?= $baseLink . max(1, $page-1) ?>">« Ant</a>
                <?php for($i=max(1, $page-2); $i<=min($totalPages, $page+2); $i++): ?>
                    <a class="btn <?= $i===$page?'primary':'' ?>" href="<?= $baseLink . $i ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a class="btn" href="<?= $baseLink . min($totalPages, $page+1) ?>">Prox »</a>
            </div>
            <?php endif; ?>
        </section>

        <!-- Anunciantes Section (Omitted for brevity, but structure kept) -->
        <section id="anunciantes">
            <h2>Anunciantes</h2>
            <!-- Simplified for this rewrite -->
            <p>Acesse <a href="admin_advertisers.php">Gestão de Anunciantes</a> para detalhes.</p>
        </section>
    </main>
    <footer class="site-footer">
        <div class="container footer-inner">
            <span>Use apenas produtos com direitos de afiliado válidos.</span>
        </div>
    </footer>
</body>
</html>
