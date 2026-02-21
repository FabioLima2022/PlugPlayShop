<?php
// Ativar exibição de erros para debug imediato
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

// Obter ID do produto
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = get_db();
$product = fetch_product($db, $id);

// Se não encontrar, exibe 404 amigável
if (!$product) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Produto não encontrado</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="bg-light"><div class="container text-center mt-5">';
    echo '<h1>😕 Produto não encontrado</h1>';
    echo '<p class="lead">O produto que você procura não existe ou foi removido.</p>';
    echo '<a href="index.php" class="btn btn-primary">Voltar para a Loja</a>';
    echo '</div></body></html>';
    exit;
}

// Preparar dados para exibição
$name = h($product['name']);
$desc = nl2br(h($product['description']));
$price = format_money((float)$product['price'], $product['currency'] ?? 'USD');
$category = h($product['category'] ?? 'Geral');
$subcategory = h($product['subcategory'] ?? '');

// Processar imagens
$images = [];
if (!empty($product['image_urls'])) {
    $parts = explode(',', $product['image_urls']);
    foreach ($parts as $p) {
        $clean = trim($p);
        if (!empty($clean)) {
            $images[] = $clean;
        }
    }
}
// Fallback se não tiver imagem
if (empty($images)) {
    $images[] = 'https://via.placeholder.com/600x600?text=Sem+Imagem';
}

$mainImage = $images[0];
$affiliateUrl = $product['affiliate_url'] ?? '';

// Contar alertas (opcional, mantendo lógica original)
$alertCount = 0;
if (function_exists('product_unresolved_alert_count')) {
    $alertCount = product_unresolved_alert_count($db, $id);
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $name ?> | PlugPlay Shop</title>
    
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-product-image {
            width: 100%;
            height: 500px;
            object-fit: contain;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
        }
        .thumb-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .thumb-img:hover, .thumb-img.active {
            border-color: #0d6efd;
            opacity: 0.8;
        }
        .price-tag {
            font-size: 2.5rem;
            font-weight: 700;
            color: #198754;
        }
        .badge-cat { font-size: 0.9rem; }
        .product-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-top: 30px;
            margin-bottom: 50px;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">🛍️ PlugPlay Shop</a>
            <div class="ms-auto">
                <a href="index.php" class="btn btn-outline-light btn-sm">← Voltar</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container product-container">
        <div class="row">
            <!-- Galeria de Imagens -->
            <div class="col-lg-6 mb-4">
                <div class="text-center mb-3">
                    <img id="mainImg" src="<?= h($mainImage) ?>" alt="<?= $name ?>" class="main-product-image">
                </div>
                
                <?php if (count($images) > 1): ?>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <?php foreach($images as $idx => $img): ?>
                        <img src="<?= h($img) ?>" 
                             class="thumb-img <?= $idx === 0 ? 'active' : '' ?>" 
                             onclick="changeImage('<?= h($img) ?>', this)" 
                             alt="Thumbnail">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Detalhes do Produto -->
            <div class="col-lg-6">
                <div class="mb-2">
                    <span class="badge bg-primary badge-cat"><?= $category ?></span>
                    <?php if($subcategory): ?>
                        <span class="badge bg-secondary badge-cat"><?= $subcategory ?></span>
                    <?php endif; ?>
                </div>

                <h1 class="display-5 fw-bold mb-3"><?= $name ?></h1>
                
                <div class="mb-4">
                    <span class="price-tag"><?= $price ?></span>
                </div>

                <?php if ($alertCount > 0): ?>
                    <div class="alert alert-warning">
                        ⚠️ Este produto pode estar com estoque baixo ou indisponível no fornecedor.
                    </div>
                <?php endif; ?>

                <div class="d-grid gap-2 mb-4">
                    <?php if (!empty($affiliateUrl)): ?>
                        <a href="go.php?id=<?= $id ?>&src=product" target="_blank" class="btn btn-success btn-lg p-3 fw-bold text-uppercase shadow-sm">
                            Comprar Agora 🛒
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-lg p-3" disabled>Indisponível no momento</button>
                    <?php endif; ?>
                </div>

                <div class="card bg-light border-0">
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-muted">Descrição</h5>
                        <p class="card-text text-dark" style="white-space: pre-line;"><?= $desc ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center text-muted py-4 mt-auto">
        <small>&copy; <?= date('Y') ?> PlugPlay Shop. Todos os direitos reservados.</small>
    </footer>

    <!-- Script Simples de Galeria -->
    <script>
        function changeImage(src, element) {
            document.getElementById('mainImg').src = src;
            document.querySelectorAll('.thumb-img').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
        }
    </script>
    
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
