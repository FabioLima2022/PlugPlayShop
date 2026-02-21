<?php
// product.php - Versão Standalone V4
// Força exibição de erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função de conexão isolada e robusta
function get_db_standalone() {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $name = 'plugplayshop';
    $port = 3306;

    // Tenta carregar .env manualmente se existir
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($k, $v) = explode('=', $line, 2) + [NULL, NULL];
            if ($k == 'DB_HOST') $host = trim($v);
            if ($k == 'DB_USER') $user = trim($v);
            if ($k == 'DB_PASS') $pass = trim($v);
            if ($k == 'DB_NAME') $name = trim($v);
            if ($k == 'DB_PORT') $port = (int)trim($v);
        }
    }

    // Tenta conexão
    try {
        $db = new mysqli($host, $user, $pass, $name, $port);
        if ($db->connect_error) {
            throw new Exception("Erro de Conexão: " . $db->connect_error);
        }
        $db->set_charset("utf8mb4");
        return $db;
    } catch (Exception $e) {
        // Fallback para localhost/root caso a produção falhe (modo XAMPP)
        if ($host !== 'localhost' && $user !== 'root') {
            try {
                $db = new mysqli('localhost', 'root', '', 'plugplayshop', 3306);
                if (!$db->connect_errno) {
                    $db->set_charset("utf8mb4");
                    return $db;
                }
            } catch (Exception $ex) { /* Ignora e usa o erro original */ }
        }
        die("<h1>Erro Crítico no Banco de Dados</h1><p>" . $e->getMessage() . "</p><p>Verifique o arquivo .env</p>");
    }
}

// Helpers mínimos
function h_safe($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_money($v, $c) { 
    return ($c == 'USD' ? 'US$ ' : ($c == 'EUR' ? '€ ' : 'R$ ')) . number_format((float)$v, 2, ',', '.'); 
}

// Lógica Principal
$db = get_db_standalone();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($id > 0) {
    $res = $db->query("SELECT * FROM products WHERE id = $id LIMIT 1");
    if ($res) {
        $product = $res->fetch_assoc();
    }
}

// Se não achou produto, define padrão para evitar tela branca
$notFound = false;
if (!$product) {
    $notFound = true;
    // Tenta buscar produtos aleatórios para vitrine
    $vitrine = [];
    $res = $db->query("SELECT * FROM products ORDER BY RAND() LIMIT 6");
    if ($res) { while($r = $res->fetch_assoc()) $vitrine[] = $r; }
} else {
    // Processa dados do produto
    $images = array_filter(explode(',', $product['image_urls'] ?? ''));
    if (empty($images)) $images = ['https://via.placeholder.com/600?text=Sem+Imagem'];
    $mainImg = trim($images[0]);
    $price = fmt_money($product['price'], $product['currency'] ?? 'USD');
    $desc = nl2br(h_safe($product['description']));
    $name = h_safe($product['name']);
    $cat = h_safe($product['category'] ?? 'Geral');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $notFound ? 'Loja' : $name ?> | PlugPlay Shop</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Ícones -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; min-height: 100vh; display: flex; flex-direction: column; }
        .main-img-container {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border: 1px solid #dee2e6;
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .main-img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .thumb-grid { display: flex; gap: 10px; margin-top: 15px; overflow-x: auto; padding-bottom: 5px; }
        .thumb { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 2px solid transparent; opacity: 0.7; transition: 0.2s; }
        .thumb:hover, .thumb.active { border-color: #0d6efd; opacity: 1; }
        .product-details { background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #dee2e6; height: 100%; }
        .price-display { font-size: 2.5rem; font-weight: 700; color: #198754; margin: 15px 0; }
        .buy-btn { width: 100%; padding: 15px; font-size: 1.2rem; font-weight: 600; text-transform: uppercase; }
        .footer { background: #212529; color: #fff; padding: 20px 0; margin-top: auto; }
        .not-found-container { text-align: center; padding: 50px 20px; }
    </style>
</head>
<body>

<!-- Navbar Simples -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shop"></i> PlugPlay Shop</a>
    <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>
</nav>

<div class="container mb-5">
    <?php if ($notFound): ?>
        <div class="not-found-container">
            <h1 class="display-4 text-muted mb-4">Produto não encontrado</h1>
            <p class="lead">Talvez você goste destes outros itens:</p>
            <div class="row g-4 mt-4">
                <?php foreach($vitrine as $v): ?>
                <div class="col-md-4 col-6">
                    <div class="card h-100 shadow-sm">
                        <?php 
                            $vImgs = explode(',', $v['image_urls']); 
                            $vImg = trim($vImgs[0] ?? '');
                            if(!$vImg) $vImg = 'https://via.placeholder.com/300';
                        ?>
                        <img src="<?= h_safe($vImg) ?>" class="card-img-top p-3" style="height:200px; object-fit:contain" alt="...">
                        <div class="card-body">
                            <h5 class="card-title"><?= h_safe($v['name']) ?></h5>
                            <p class="card-text fw-bold text-success"><?= fmt_money($v['price'], $v['currency']) ?></p>
                            <a href="product.php?id=<?= $v['id'] ?>" class="btn btn-primary w-100">Ver</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Coluna Imagens -->
            <div class="col-lg-7">
                <div class="main-img-container shadow-sm">
                    <img id="mainImage" src="<?= h_safe($mainImg) ?>" class="main-img" alt="<?= $name ?>">
                </div>
                <?php if (count($images) > 1): ?>
                <div class="thumb-grid">
                    <?php foreach($images as $idx => $img): $img = trim($img); if(!$img) continue; ?>
                    <img src="<?= h_safe($img) ?>" class="thumb <?= $idx==0?'active':'' ?>" onclick="setMainImage('<?= h_safe($img) ?>', this)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Coluna Detalhes -->
            <div class="col-lg-5">
                <div class="product-details shadow-sm">
                    <div class="mb-2">
                        <span class="badge bg-primary"><?= $cat ?></span>
                        <span class="badge bg-secondary">ID: <?= $id ?></span>
                    </div>
                    
                    <h1 class="fw-bold mb-3"><?= $name ?></h1>
                    
                    <div class="price-display">
                        <?= $price ?>
                    </div>
                    
                    <div class="d-grid gap-2 mb-4">
                        <?php if (!empty($product['affiliate_url'])): ?>
                            <a href="go.php?id=<?= $id ?>&src=product_v4" target="_blank" class="btn btn-success buy-btn shadow">
                                Comprar Agora <i class="bi bi-box-arrow-up-right ms-2"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary buy-btn" disabled>Indisponível</button>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <div class="mt-4">
                        <h5 class="fw-bold text-secondary"><i class="bi bi-file-text me-2"></i>Descrição</h5>
                        <div class="text-muted" style="white-space: pre-line;">
                            <?= $desc ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<footer class="footer text-center">
    <div class="container">
        <small>&copy; <?= date('Y') ?> PlugPlay Shop. Todos os direitos reservados.</small>
    </div>
</footer>

<script>
    function setMainImage(url, el) {
        document.getElementById('mainImage').src = url;
        document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
    }
</script>

<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
