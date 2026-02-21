<?php
session_start();
require_once __DIR__ . '/config.php';
$env = load_env();
$forceHttps = strtolower((string)($env['FORCE_HTTPS'] ?? '')) === 'true';
if ($forceHttps) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    if (!$isHttps) { header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301); exit; }
}

$db = get_db_soft();
if ($db) { ensure_schema($db); }
$products = $db ? fetch_products($db, true) : [];

// Build category -> subcategories map
$categories = [];
$categoryMap = [];
foreach ($products as $p) {
    $cat = (string)($p['category'] ?? '');
    $sub = (string)($p['subcategory'] ?? '');
    if ($cat !== '' && !in_array($cat, $categories, true)) { $categories[] = $cat; }
    if ($cat !== '' && $sub !== '') {
        if (!isset($categoryMap[$cat])) { $categoryMap[$cat] = []; }
        if (!in_array($sub, $categoryMap[$cat], true)) { $categoryMap[$cat][] = $sub; }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>PlugPlay Shop — Descubra e compre com confiança</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css" />
    <?php
      // Carrega SEO com tratamento de erro
      if (file_exists(__DIR__ . '/seo.php')) {
          require_once __DIR__ . '/seo.php';
          $canonical = seo_canonical('/index.php');
          seo_meta([
            'title' => 'PlugPlay Shop — Descubra e compre com confiança',
            'description' => 'Seleção de produtos com curadoria, avaliações e links de compra seguros.',
            'canonical' => $canonical,
            'type' => 'website',
          ]);
          $siteUrl = seo_site_url();
          seo_jsonld([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'url' => $siteUrl,
            'name' => 'PlugPlay Shop',
            'potentialAction' => [
              '@type' => 'SearchAction',
              'target' => $siteUrl . '/index.php?q={search_term_string}',
              'query-input' => 'required name=search_term_string'
            ]
          ]);
      } else {
          // Fallback básico se seo.php não existir
          $siteUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
          echo '<meta name="description" content="Seleção de produtos com curadoria, avaliações e links de compra seguros." />' . "\n";
          echo '<meta property="og:title" content="PlugPlay Shop — Descubra e compre com confiança" />' . "\n";
          echo '<meta property="og:description" content="Seleção de produtos com curadoria, avaliações e links de compra seguros." />' . "\n";
          echo '<meta property="og:type" content="website" />' . "\n";
          echo '<meta property="og:url" content="' . $siteUrl . '/index.php" />' . "\n";
      }
    ?>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <div class="brand">
                <span class="logo">⚡</span>
                <span class="name">PlugPlay Shop</span>
            </div>
            <nav class="actions">
                <a class="btn" href="/affiliate_register.php">Afiliado</a>
                <a class="btn" href="/classifieds">Classificados</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container hero-inner">
            <h1>Encontre os melhores produtos com curadoria e confiança</h1>
            <p>Explore nossa seleção com avaliações, imagens e links de afiliado seguros.</p>
            <div class="search-wrap">
                <input id="searchInput" type="search" placeholder="Buscar por nome, categoria..." aria-label="Buscar produtos" />
            </div>
            <?php if (!empty($categories)): ?>
            <div class="chips" id="categoryChips" aria-label="Filtrar por categoria">
                <button class="chip active" data-category="all">Todos</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="chip" data-category="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="chips" id="subcategoryChips" aria-label="Filtrar por subcategoria" style="display:none;"></div>
            <p class="desc" id="subNotice" style="display:none;">Selecione uma subcategoria</p>
            <script>window.categorySubMap = <?= json_encode($categoryMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
            <?php endif; ?>
        </div>
    </section>

    <main class="container">
        <section class="grid" id="productGrid">
            <?php foreach ($products as $prod):
                $images = array_filter(array_map('trim', explode(',', (string)$prod['image_urls'])));
                $cover = $images[0] ?? 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=1200&auto=format&fit=crop';
            ?>
            <article class="card" data-name="<?= htmlspecialchars(mb_strtolower($prod['name'])) ?>" data-category="<?= htmlspecialchars($prod['category'] ?: 'Sem categoria') ?>" data-subcategory="<?= htmlspecialchars($prod['subcategory'] ?: '') ?>">
                <a class="card-media" href="product.php?id=<?= (int)$prod['id'] ?>" aria-label="Ver detalhes de <?= htmlspecialchars($prod['name']) ?>">
                    <img src="<?= htmlspecialchars($cover) ?>" alt="Imagem de <?= htmlspecialchars($prod['name']) ?>" loading="lazy" />
                </a>
                <div class="card-body">
                    <h3 class="card-title"><?= htmlspecialchars($prod['name']) ?></h3>
                    <?php if (!empty($prod['category'])): ?>
                        <span class="badge"><?= htmlspecialchars($prod['category']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($prod['subcategory'])): ?>
                        <span class="badge"><?= htmlspecialchars($prod['subcategory']) ?></span>
                    <?php endif; ?>
                    <p class="card-desc"><?= htmlspecialchars($prod['description']) ?></p>
                    <div class="card-actions">
                        <span class="price"><?= htmlspecialchars(format_money((float)$prod['price'], (string)($prod['currency'] ?? 'USD'))) ?></span>
                        <?php if (!empty($prod['affiliate_url'])): ?>
                            <a class="btn primary" href="go.php?id=<?= (int)$prod['id'] ?>&src=index" target="_blank" rel="noopener noreferrer">Comprar</a>
                        <?php endif; ?>
                        <a class="btn" href="product.php?id=<?= (int)$prod['id'] ?>">Ver detalhes</a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <span>© <?= date('Y') ?> PlugPlay Shop — Parcerias transparentes.</span>
        </div>
    </footer>

    <script src="assets/js/app.js?v=<?= htmlspecialchars((string)@filemtime(__DIR__ . '/assets/js/app.js')) ?>"></script>
</body>
</html>
