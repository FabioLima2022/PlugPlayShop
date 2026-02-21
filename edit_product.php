<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { $_SESSION['flash'] = ['msg'=>'Produto não encontrado.', 'type'=>'error']; header('Location: admin.php#produtos'); exit; }

$product = fetch_product($db, $id);
if (!$product) { $_SESSION['flash'] = ['msg'=>'Produto inexistente.', 'type'=>'error']; header('Location: admin.php#produtos'); exit; }
// Ownership check for affiliates
$role = $_SESSION['role'] ?? 'admin';
if ($role !== 'admin' && isset($product['user_id']) && (int)$product['user_id'] !== (int)$_SESSION['user_id']) {
    $_SESSION['flash'] = ['msg'=>'Operação não permitida.', 'type'=>'error'];
    header('Location: affiliate_products.php');
    exit;
}

$msg = '';
$type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $msg = 'Sessão expirada ou inválida.'; $type = 'error';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = trim($_POST['price'] ?? '0');
        $currency = strtoupper(substr(trim((string)($_POST['currency'] ?? ($product['currency'] ?? 'USD'))), 0, 3));
        if (!in_array($currency, ['USD','BRL','EUR'], true)) { $currency = 'USD'; }
        $category = trim($_POST['category'] ?? '');
        $subcategory = trim($_POST['subcategory'] ?? '');
        $affiliate = trim($_POST['affiliate_url'] ?? '');
        
        // Processa upload de imagens
        $uploaded_images = [];
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $uploadDir = __DIR__ . '/uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $files = $_FILES['product_images'];
            $fileCount = count($files['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$i];
                    $originalName = basename($files['name'][$i]);
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    // Valida tipos de imagem
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($extension, $allowedTypes)) {
                        // Gera nome único
                        $newName = uniqid('product_') . '.' . $extension;
                        $destination = $uploadDir . $newName;
                        
                        if (move_uploaded_file($tmpName, $destination)) {
                            // URL pública para a imagem
                            $uploaded_images[] = '/uploads/products/' . $newName;
                        }
                    }
                }
            }
        }
        
        // Processa URLs de imagem existentes
        $images_raw = trim($_POST['image_urls'] ?? '');
        $url_images = [];
        if (!empty($images_raw)) {
            // normaliza separadores
            $images_raw = str_replace([';', "\n", "\r"], [',', ',', ''], $images_raw);
            // limpa espaços extras
            $url_images = array_filter(array_map('trim', explode(',', $images_raw)));
        }
        
        // Combina todas as imagens (novas uploads + URLs existentes)
        $all_images = array_merge($uploaded_images, $url_images);
        $image_urls = implode(',', $all_images);

        // Processamento de vídeos
        require_once __DIR__ . '/video_helper.php';
        $video_files = $_FILES['product_videos'] ?? [];
        $video_urls_raw = trim($_POST['video_urls'] ?? '');
        $video_urls = process_video_input($video_files, $video_urls_raw);

        $ok = update_product($db, $id, [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'currency' => $currency,
            'category' => $category,
            'subcategory' => $subcategory,
            'image_urls' => $image_urls,
            'video_urls' => $video_urls,
            'affiliate_url' => $affiliate,
        ]);
        $return = isset($_GET['return']) ? (string)$_GET['return'] : ($role==='admin' ? 'admin.php#produtos' : 'affiliate_products.php');
        if ($ok) { $_SESSION['flash'] = ['msg'=>'Produto atualizado com sucesso.', 'type'=>'success']; header('Location: ' . $return); exit; }
        else { $msg = 'Falha ao atualizar produto.'; $type = 'error'; }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Editar Produto • PlugPlay Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand">
        <span class="logo">✏️</span>
        <span class="name">Editar Produto</span>
      </div>
      <nav class="actions">
        <a class="btn" href="admin.php#produtos">Voltar</a>
        <a class="btn" href="logout.php">Sair</a>
      </nav>
    </div>
  </header>
  <main class="container">
    <h1>Editar produto #<?= (int)$product['id'] ?></h1>
    <?php if ($msg): ?><div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post" class="form" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <div class="form-row">
        <label>Nome</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($product['name']) ?>" />
      </div>
      <div class="form-row">
        <label>Descrição</label>
        <textarea name="description" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>
      </div>
      <div class="form-row">
        <label>Preço</label>
        <input type="number" step="0.01" name="price" required value="<?= htmlspecialchars($product['price']) ?>" />
      </div>
      <div class="form-row">
        <label>Moeda</label>
        <?php $cur = strtoupper((string)($product['currency'] ?? 'USD')); if (!in_array($cur, ['USD','BRL','EUR'], true)) { $cur = 'USD'; } ?>
        <select name="currency" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
          <option value="USD" <?= $cur==='USD'?'selected':''; ?>>USD (US$)</option>
          <option value="BRL" <?= $cur==='BRL'?'selected':''; ?>>BRL (R$)</option>
          <option value="EUR" <?= $cur==='EUR'?'selected':''; ?>>EUR (€)</option>
        </select>
      </div>
      <div class="form-row">
        <label>Categoria</label>
        <input type="text" name="category" value="<?= htmlspecialchars($product['category']) ?>" />
      </div>
      <div class="form-row">
        <label>Subcategoria</label>
        <input type="text" name="subcategory" value="<?= htmlspecialchars($product['subcategory'] ?? '') ?>" />
      </div>
      <div class="form-row">
        <label>Upload de Imagens (múltiplas)</label>
        <input type="file" name="product_images[]" multiple accept="image/*" style="padding: 8px; background: var(--card); border: 1px solid var(--border); border-radius: 10px; color: var(--text);" />
        <small style="color: var(--muted);">Você pode selecionar várias imagens ou usar URLs abaixo</small>
      </div>
      <div class="form-row">
        <label>Links de Imagem (separados por vírgula)</label>
        <textarea name="image_urls" rows="3"><?= htmlspecialchars($product['image_urls']) ?></textarea>
      </div>
      <div class="form-row">
        <label>Upload de Vídeos</label>
        <input type="file" name="product_videos[]" multiple accept="video/mp4,video/webm,video/quicktime" style="padding: 8px; background: var(--card); border: 1px solid var(--border); border-radius: 10px; color: var(--text);" />
      </div>
      <div class="form-row">
        <label>Links de Vídeo (separados por vírgula)</label>
        <textarea name="video_urls" rows="2"><?= htmlspecialchars($product['video_urls'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <label>Link de Afiliado</label>
        <input type="url" name="affiliate_url" value="<?= htmlspecialchars($product['affiliate_url']) ?>" />
      </div>
      <div class="form-actions">
        <button class="btn primary" type="submit">Salvar alterações</button>
        <?php $return = isset($_GET['return']) ? (string)$_GET['return'] : ($role==='admin' ? 'admin.php#produtos' : 'affiliate_products.php'); ?>
        <a class="btn" href="<?= htmlspecialchars($return) ?>">Cancelar</a>
      </div>
    </form>
  </main>
</body>
</html>