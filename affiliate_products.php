<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);

if (!isset($_SESSION['user_id'])) { header('Location: affiliate_register.php'); exit; }
$uid = (int)$_SESSION['user_id'];

$msg = '';
$type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    if (!check_csrf($_POST['csrf'] ?? null)) { $msg = 'Sessão expirada.'; $type = 'error'; }
    else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = trim($_POST['price'] ?? '0');
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
        if (!empty($images_raw)) { $images_raw = str_replace([';','\n','\r'], [',',',',''], $images_raw); $url_images = array_filter(array_map('trim', explode(',', $images_raw))); }
        $image_urls = implode(',', array_merge($uploaded_images, $url_images));

        // Processamento de vídeos
        require_once __DIR__ . '/video_helper.php';
        $video_files = $_FILES['product_videos'] ?? [];
        $video_urls_raw = trim($_POST['video_urls'] ?? '');
        $video_urls = process_video_input($video_files, $video_urls_raw);

        $ok = insert_product($db, [
            'name'=>$name,'description'=>$description,'price'=>$price,'currency'=>$currency,
            'category'=>$category,'subcategory'=>$subcategory,'image_urls'=>$image_urls,'video_urls'=>$video_urls,'affiliate_url'=>$affiliate,
            'user_id'=>$uid
        ]);
        if (!$ok) { $msg='Falha ao adicionar produto.'; $type='error'; }
        else { $msg='Produto adicionado com sucesso!'; $type='success'; }
    }
}

// Listar apenas produtos do usuário
$productsList = [];
$sql = 'SELECT p.*, COALESCE(cnt.c,0) as clicks_count FROM products p LEFT JOIN (SELECT product_id, COUNT(*) as c FROM clicks GROUP BY product_id) cnt ON cnt.product_id = p.id WHERE p.user_id = ? ORDER BY p.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) { $productsList[] = $row; } if ($res) $res->close(); }
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Anunciante • Meus produtos</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand"><span class="logo">🧩</span><span class="name">Anunciante</span></div>
      <nav class="actions"><a class="btn" href="index.php">Home</a><a class="btn" href="logout.php">Sair</a></nav>
    </div>
  </header>
  <main class="container">
    <h1>Meus produtos</h1>
    <?php if ($msg): ?><div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <section>
      <h2>Cadastrar novo produto</h2>
      <form method="post" class="form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <div class="form-row"><label>Nome</label><input type="text" name="name" required /></div>
        <div class="form-row"><label>Descrição</label><textarea name="description" rows="4"></textarea></div>
        <div class="form-row"><label>Preço</label><input type="number" step="0.01" name="price" required /></div>
        <div class="form-row"><label>Moeda</label><select name="currency" style="height:42px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);padding:0 12px;"><option value="USD">USD</option><option value="BRL" selected>BRL</option><option value="EUR">EUR</option></select></div>
        <div class="form-row"><label>Categoria</label><input type="text" name="category" /></div>
        <div class="form-row"><label>Subcategoria</label><input type="text" name="subcategory" /></div>
        <div class="form-row"><label>Upload de Imagens</label><input type="file" name="product_images[]" multiple accept="image/*" style="padding:8px;background:var(--card);border:1px solid var(--border);border-radius:10px;color:var(--text);" /></div>
        <div class="form-row"><label>Ou Links de Imagem</label><textarea name="image_urls" rows="3" placeholder="https://... , https://..."></textarea></div>
        <div class="form-row"><label>Upload de Vídeos</label><input type="file" name="product_videos[]" multiple accept="video/mp4,video/webm,video/quicktime" style="padding:8px;background:var(--card);border:1px solid var(--border);border-radius:10px;color:var(--text);" /></div>
        <div class="form-row"><label>Ou Links de Vídeo</label><textarea name="video_urls" rows="2" placeholder="https://... , https://..."></textarea></div>
        <div class="form-row"><label>Link de Afiliado</label><input type="url" name="affiliate_url" /></div>
        <div class="form-actions"><button class="btn primary" type="submit">Salvar produto</button></div>
      </form>
    </section>
    <section>
      <h2>Lista</h2>
      <div class="card"><div class="card-body">
        <table style="width:100%; border-collapse:collapse;">
          <thead><tr style="color:#cbd1e6;"><th style="text-align:left; padding:6px;">ID</th><th style="text-align:left; padding:6px;">Nome</th><th style="text-align:left; padding:6px;">Categoria</th><th style="text-align:right; padding:6px;">Preço</th><th style="text-align:right; padding:6px;">Cliques</th><th style="text-align:right; padding:6px;">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($productsList as $p): ?>
            <tr>
              <td style="padding:6px;">#<?= (int)$p['id'] ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($p['name']) ?></td>
              <td style="padding:6px;"><?= htmlspecialchars($p['category']) ?></td>
              <td style="padding:6px; text-align:right;"><?= htmlspecialchars(format_money((float)$p['price'], (string)($p['currency'] ?? 'USD'))) ?></td>
              <td style="padding:6px; text-align:right; font-weight:700;"><?= (int)$p['clicks_count'] ?></td>
              <td style="padding:6px; text-align:right;">
                <a class="btn" href="product.php?id=<?= (int)$p['id'] ?>" target="_blank">Ver</a>
                <a class="btn" href="edit_product.php?id=<?= (int)$p['id'] ?>&return=affiliate_products.php">Editar</a>
                <?php $ac = product_unresolved_alert_count($db, (int)$p['id']); if ($ac > 0): ?>
                  <span class="badge" style="background:#c00; color:#fff;">Alertas: <?= (int)$ac ?></span>
                <?php endif; ?>
                <form method="post" action="delete_product.php" style="display:inline" onsubmit="return confirm('Excluir este produto?');">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
                  <input type="hidden" name="redirect" value="affiliate_products.php" />
                  <button class="btn" type="submit" style="background:#c00; color:#fff;">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </section>
  </main>
</body>
</html>
