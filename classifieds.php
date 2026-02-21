<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
header('Content-Type: text/html; charset=UTF-8');
$__out = false;
register_shutdown_function(function(){
  $e = error_get_last();
  if (!$GLOBALS['__out']) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>Erro • Classificados</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/styles.css" /></head><body><header class="site-header"><div class="container header-inner"><div class="brand"><span class="logo">📣</span><span class="name">Classificados</span></div><nav class="actions"><a class="btn" href="index.php">Voltar à loja</a><a class="btn" href="affiliate_register.php">Anunciante</a></nav></div></header><main class="container">';
    if ($e && isset($e['message'])) { echo '<div class="notice error">Falha ao carregar Classificados: ' . htmlspecialchars($e['message']) . '</div>'; }
    else { echo '<div class="notice error">Falha ao carregar Classificados</div>'; }
    echo '</main></body></html>';
  }
});
// Trace de acesso
$__ld = __DIR__ . '/storage/logs';
if (!is_dir($__ld)) { @mkdir($__ld, 0775, true); }
@file_put_contents($__ld . '/classifieds_access.log', date('c') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . "\n", FILE_APPEND);
if (isset($_GET['ping'])) { echo 'classifieds-ok'; exit; }
try {
  require_once __DIR__ . '/config.php';
  $db = get_db();
  ensure_schema($db);
} catch (Throwable $e) {
  $__out = true;
  http_response_code(500);
  ?>
  <!DOCTYPE html>
  <html lang="pt-br">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Erro • Classificados</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css" />
  </head>
  <body>
    <header class="site-header"><div class="container header-inner"><div class="brand"><span class="logo">📣</span><span class="name">Classificados</span></div><nav class="actions"><a class="btn" href="index.php">Voltar à loja</a><a class="btn" href="affiliate_register.php">Anunciante</a></nav></div></header>
    <main class="container">
      <div class="notice error">Falha ao carregar Classificados: <?= htmlspecialchars($e->getMessage()) ?></div>
    </main>
  </body>
  </html>
  <?php
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE && isset($_COOKIE[session_name()])) { @session_start(); }
$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$isAffiliate = isset($_SESSION['role']) && $_SESSION['role'] === 'affiliate';
$msg = '';
$type = 'info';

if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') === 'POST' && isset($_POST['delete_classified_id'])) {
    if (!$uid) { header('Location: affiliate_register.php'); exit; }
    if (!check_csrf($_POST['csrf'] ?? null)) { $msg = 'Sessão expirada.'; $type = 'error'; }
    else {
        $cid = (int)($_POST['delete_classified_id'] ?? 0);
        if ($cid > 0) {
            $own = false;
            if ($res = $db->query('SELECT user_id FROM classifieds WHERE id = ' . $cid . ' LIMIT 1')) { $row = $res->fetch_assoc(); $own = ($row && ((int)$row['user_id'] === $uid)); $res->close(); }
            if ($own) {
                $ok = delete_classified($db, $cid);
                if ($ok) { $msg = 'Anúncio excluído.'; $type = 'success'; }
                else { $msg = 'Falha ao excluir.'; $type = 'error'; }
            } else { $msg = 'Operação não permitida.'; $type = 'error'; }
        }
    }
}
elseif ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') === 'POST' && isset($_POST['edit_classified_id'])) {
    if (!$uid) { header('Location: affiliate_register.php'); exit; }
    if (!check_csrf($_POST['csrf'] ?? null)) { $msg = 'Sessão expirada.'; $type = 'error'; }
    else {
        $eid = (int)($_POST['edit_classified_id'] ?? 0);
        $own = false;
        if ($res = $db->query('SELECT user_id FROM classifieds WHERE id = ' . $eid . ' LIMIT 1')) { $row = $res->fetch_assoc(); $own = ($row && ((int)$row['user_id'] === $uid)); $res->close(); }
        if ($own) {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priceRaw = trim($_POST['price'] ?? '0');
            $priceClean = preg_replace('/[^0-9.,]/', '', $priceRaw);
            $priceClean = str_replace('.', '', $priceClean);
            $priceClean = str_replace(',', '.', $priceClean);
            $currency = strtoupper(substr(trim((string)($_POST['currency'] ?? 'BRL')), 0, 3));
            if (!in_array($currency, ['USD','BRL','EUR'], true)) { $currency = 'BRL'; }
            $category = trim($_POST['category'] ?? '');
            $subcategory = trim($_POST['subcategory'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $contact_whatsapp = trim($_POST['contact_whatsapp'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $state = normalize_uf(trim($_POST['state'] ?? ''));
            $neighborhood = trim($_POST['neighborhood'] ?? '');
            $zipcode = normalize_cep(trim($_POST['zipcode'] ?? ''));
            $ok = update_classified($db, $eid, [
                'title' => $title,
                'description' => $description,
                'price' => $priceClean !== '' ? $priceClean : '0',
                'currency' => $currency,
                'category' => $category,
                'subcategory' => $subcategory,
                'image_urls' => '',
                'contact_phone' => $contact_phone,
                'contact_whatsapp' => $contact_whatsapp,
                'location' => $location,
                'state' => $state,
                'neighborhood' => $neighborhood,
                'zipcode' => $zipcode
            ]);
            if ($ok) { $msg = 'Anúncio atualizado.'; $type = 'success'; }
            else { $msg = 'Falha ao atualizar.'; $type = 'error'; }
        } else { $msg = 'Operação não permitida.'; $type = 'error'; }
    }
}
elseif ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') === 'POST' && isset($_POST['title'])) {
    if (!$uid) { header('Location: affiliate_register.php'); exit; }
    if (!check_csrf($_POST['csrf'] ?? null)) { $msg = 'Sessão expirada.'; $type = 'error'; }
    else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priceRaw = trim($_POST['price'] ?? '0');
        $priceClean = preg_replace('/[^0-9.,]/', '', $priceRaw);
        $priceClean = str_replace('.', '', $priceClean);
        $priceClean = str_replace(',', '.', $priceClean);
        $currency = strtoupper(substr(trim((string)($_POST['currency'] ?? 'BRL')), 0, 3));
        if (!in_array($currency, ['USD','BRL','EUR'], true)) { $currency = 'BRL'; }
        $category = trim($_POST['category'] ?? '');
        $subcategory = trim($_POST['subcategory'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_whatsapp = trim($_POST['contact_whatsapp'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $state = normalize_uf(trim($_POST['state'] ?? ''));
        $neighborhood = trim($_POST['neighborhood'] ?? '');
        $zipcode = normalize_cep(trim($_POST['zipcode'] ?? ''));

        $uploaded_images = [];
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $uploadDir = __DIR__ . '/uploads/classifieds/';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
            $files = $_FILES['images'];
            $count = min(10, count($files['name']));
            for ($i=0; $i<$count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $size = isset($files['size'][$i]) ? (int)$files['size'][$i] : 0;
                    if ($size > 10*1024*1024) { continue; }
                    $tmp = $files['tmp_name'][$i];
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                        $name = uniqid('cls_') . '.' . $ext;
                        if (move_uploaded_file($tmp, $uploadDir . $name)) { $uploaded_images[] = '/uploads/classifieds/' . $name; }
                    }
                }
            }
        }
        $images_raw = trim($_POST['image_urls'] ?? '');
        $url_images = [];
        if ($images_raw !== '') { $images_raw = str_replace([';', '\n', '\r'], [',', ',', ''], $images_raw); $url_images = array_filter(array_map('trim', explode(',', $images_raw))); }
        $all_images = array_merge($uploaded_images, $url_images);
        $image_urls = implode(',', $all_images);

        if ($title === '') { $msg = 'Informe um título.'; $type = 'error'; }
        else {
            $ok = insert_classified($db, [
                'title' => $title,
                'description' => $description,
                'price' => $priceClean !== '' ? $priceClean : '0',
                'currency' => $currency,
                'category' => $category,
                'subcategory' => $subcategory,
                'image_urls' => $image_urls,
                'contact_phone' => $contact_phone,
                'contact_whatsapp' => $contact_whatsapp,
                'location' => $location,
                'state' => $state,
                'neighborhood' => $neighborhood,
                'zipcode' => $zipcode,
                'user_id' => $uid,
            ]);
            if ($ok) { $msg = 'Anúncio criado com sucesso!'; $type = 'success'; }
            else { $msg = 'Falha ao criar anúncio.'; $type = 'error'; }
        }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['category'] ?? ''));
$subcat = trim((string)($_GET['subcategory'] ?? ''));
$curSel = strtoupper(substr((string)($_GET['currency'] ?? ''), 0, 3));
if (!in_array($curSel, ['USD','BRL','EUR'], true)) { $curSel = ''; }
$minStr = (string)($_GET['min_price'] ?? '');
$maxStr = (string)($_GET['max_price'] ?? '');
$min = null; $max = null;
if ($minStr !== '') { $t = preg_replace('/[^0-9.,]/','',$minStr); $t = str_replace('.','',$t); $t = str_replace(',','.',$t); if ($t !== '' && is_numeric($t)) { $min = (float)$t; } }
if ($maxStr !== '') { $t = preg_replace('/[^0-9.,]/','',$maxStr); $t = str_replace('.','',$t); $t = str_replace(',','.',$t); if ($t !== '' && is_numeric($t)) { $max = (float)$t; } }
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 12;
$offset = ($page - 1) * $perPage;
$filters = [];
if ($q !== '') { $filters['q'] = $q; }
if ($cat !== '') { $filters['category'] = $cat; }
if ($subcat !== '') { $filters['subcategory'] = $subcat; }
if ($curSel !== '') { $filters['currency'] = $curSel; }
if ($min !== null) { $filters['min_price'] = $min; }
if ($max !== null) { $filters['max_price'] = $max; }
if ($db && function_exists('count_classifieds')) { $totalRows = count_classifieds($db, $filters); }
else { $totalRows = 0; }
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$filters['limit'] = $perPage; $filters['offset'] = $offset;
if ($db && function_exists('fetch_classifieds')) { $list = fetch_classifieds($db, $filters); }
else { $list = []; }

$cats = [];
if ($db && ($rc = $db->query("SELECT DISTINCT category FROM classifieds WHERE category IS NOT NULL AND category <> '' ORDER BY category"))) { while ($row = $rc->fetch_assoc()) { $cats[] = $row['category']; } $rc->close(); }
$subcats = [];
if ($db && ($rc = $db->query("SELECT DISTINCT subcategory FROM classifieds WHERE subcategory IS NOT NULL AND subcategory <> '' ORDER BY subcategory"))) { while ($row = $rc->fetch_assoc()) { $subcats[] = $row['subcategory']; } $rc->close(); }
$__out = true;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Classificados • PlugPlay Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
  <?php if (file_exists(__DIR__ . '/seo.php')) { require_once __DIR__ . '/seo.php'; $canonical = seo_canonical('/classifieds'); seo_meta(['title'=>'Classificados • PlugPlay Shop','description'=>'Publique e encontre anúncios com contato e categorias.','canonical'=>$canonical,'type'=>'website']); } ?>
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand">
        <span class="logo">📣</span>
        <span class="name">Classificados</span>
      </div>
      <nav class="actions">
        <a class="btn" href="index.php">Voltar à loja</a>
        <?php if ($uid && $isAffiliate): ?>
          <span class="chip">Logado</span>
        <?php else: ?>
          <a class="btn" href="advertiser_login.php">Entrar</a>
          <a class="btn" href="advertiser_register.php">Cadastrar</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="container">
    <?php if ($msg): ?><div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card" style="margin:12px 0;">
      <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <div>
          <div class="card-title">É anunciante?</div>
          <p class="desc">Publique seu anúncio agora e alcance compradores.</p>
        </div>
        <a class="btn primary" href="advertiser_register.php">Sou anunciante</a>
      </div>
    </div>

    <section>
      <h2>Buscar classificado</h2>
      <form method="get" class="form" style="grid-template-columns: 1.2fr 160px 1fr 1fr 1fr 140px; align-items: end;">
        <div class="form-row">
          <label>Buscar (título/categoria/subcategoria)</label>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ex: Notebook, Móveis..." />
        </div>
        <div class="form-row">
          <label>Itens por página</label>
          <select name="per_page" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
            <option value="12" <?= $perPage===12?'selected':''; ?>>12</option>
            <option value="24" <?= $perPage===24?'selected':''; ?>>24</option>
            <option value="48" <?= $perPage===48?'selected':''; ?>>48</option>
          </select>
        </div>
        <div class="form-row">
          <label>Moeda</label>
          <select name="currency" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
            <option value="">Todas</option>
            <option value="BRL" <?= $curSel==='BRL'?'selected':''; ?>>BRL (R$)</option>
            <option value="USD" <?= $curSel==='USD'?'selected':''; ?>>USD (US$)</option>
            <option value="EUR" <?= $curSel==='EUR'?'selected':''; ?>>EUR (€)</option>
          </select>
        </div>
        <div class="form-row">
          <label>Categorias</label>
          <select name="category" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
            <option value="">Todas</option>
            <?php foreach ($cats as $c): $sel = $cat===$c?'selected':''; ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= $sel ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>Subcategorias</label>
          <select name="subcategory" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
            <option value="">Todas</option>
            <?php foreach ($subcats as $sc): $sel = $subcat===$sc?'selected':''; ?>
              <option value="<?= htmlspecialchars($sc) ?>" <?= $sel ?>><?= htmlspecialchars($sc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>Preço mínimo / máximo</label>
          <?php $minIn = htmlspecialchars($minStr); $maxIn = htmlspecialchars($maxStr); ?>
          <div style="display:flex; gap:8px;">
            <input type="text" inputmode="decimal" name="min_price" value="<?= $minIn ?>" placeholder="Mín (ex: 1.100,00)" />
            <input type="text" inputmode="decimal" name="max_price" value="<?= $maxIn ?>" placeholder="Máx (ex: 2.500,00)" />
          </div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Filtrar</button><a class="btn" href="classifieds.php">Limpar</a></div>
      </form>
    </section>

    

    

    <section>
      <h2>Classificados recentes</h2>
      <?php if (empty($list)): ?>
        <p class="desc">Nenhum classificado publicado ainda.</p>
      <?php endif; ?>

      <div class="grid" style="grid-template-columns: repeat(3, 1fr);">
        <?php foreach ($list as $item): ?>
          <div class="card">
            <div class="card-body">
              <div class="card-title"><?= htmlspecialchars($item['title']) ?></div>
              <div class="price"><?= htmlspecialchars(format_money((float)$item['price'], (string)($item['currency'] ?? 'BRL'))) ?></div>
              <p class="desc"><?= htmlspecialchars(mb_substr((string)$item['description'], 0, 140)) ?><?= strlen((string)$item['description'])>140?'...':''; ?></p>
              <?php $imgs = array_filter(array_map('trim', explode(',', (string)($item['image_urls'] ?? '')))); 
              $vids = array_filter(array_map('trim', explode(',', (string)($item['video_urls'] ?? ''))));
              if (!empty($imgs) || !empty($vids)): ?>
                <div class="image-viewer" data-urls="<?= htmlspecialchars(implode('|', array_merge($vids, $imgs))) ?>" style="position:relative; margin-top:8px; height:160px; overflow:hidden;">
                  <?php if (!empty($vids)): ?>
                    <video class="image-viewer-img" controls style="width:100%; height:100%; object-fit:contain; background:#000;">
                        <source src="<?= htmlspecialchars($vids[0]) ?>" type="video/mp4">
                        Seu navegador não suporta vídeo.
                    </video>
                  <?php elseif (!empty($imgs)): ?>
                    <img class="image-viewer-img" alt="Imagem" src="<?= htmlspecialchars($imgs[0]) ?>" style="width:100%; height:100%; object-fit:contain; border-radius:8px; display:block;" />
                  <?php endif; ?>
                  <?php if ((count($imgs) + count($vids)) > 1): ?>
                    <button type="button" class="btn image-prev" style="position:absolute; top:50%; left:8px; transform:translateY(-50%);">«</button>
                    <button type="button" class="btn image-next" style="position:absolute; top:50%; right:8px; transform:translateY(-50%);">»</button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div class="chips" style="margin-top:8px;">
                <?php if (!empty($item['category'])): ?><span class="chip"><?= htmlspecialchars($item['category']) ?></span><?php endif; ?>
                <?php if (!empty($item['subcategory'])): ?><span class="chip"><?= htmlspecialchars($item['subcategory']) ?></span><?php endif; ?>
                <?php if (!empty($item['location']) || !empty($item['state'])): ?>
                  <span class="chip">
                    <?= htmlspecialchars(trim($item['location'])) ?><?= (!empty($item['location']) && !empty($item['state']))?' - ':''; ?><?= htmlspecialchars(trim((string)($item['state'] ?? ''))) ?>
                  </span>
                <?php endif; ?>
                <?php if (!empty($item['neighborhood'])): ?><span class="chip"><?= htmlspecialchars($item['neighborhood']) ?></span><?php endif; ?>
                <?php if (!empty($item['zipcode'])): ?><span class="chip">CEP: <?= htmlspecialchars($item['zipcode']) ?></span><?php endif; ?>
                <?php if (!empty($item['contact_phone'])): ?><span class="chip"><?= htmlspecialchars($item['contact_phone']) ?></span><?php endif; ?>
              </div>
              <?php
                // Publicado em: Hoje/ontem/data + hora
                $dtStr = (string)($item['created_at'] ?? '');
                $label = '';
                if ($dtStr !== '') {
                  try {
                    $dt = new DateTime($dtStr);
                    $now = new DateTime('now');
                    $d1 = $dt->format('Y-m-d');
                    $d2 = $now->format('Y-m-d');
                    $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
                    $time = $dt->format('H:i');
                    if ($d1 === $d2) { $label = 'Hoje, ' . $time; }
                    elseif ($d1 === $yesterday) { $label = 'Ontem, ' . $time; }
                    else { $label = $dt->format('d/m/Y, H:i'); }
                  } catch (Exception $e) {}
                }
              ?>
              <?php if ($label): ?><p class="desc" style="margin-top:6px; color:#cbd1e6;">Publicado: <?= htmlspecialchars($label) ?></p><?php endif; ?>
              <div class="form-actions" style="margin-top:8px; display:flex; gap:8px;">
                <?php if (!empty($item['contact_phone'])): ?>
                  <?php $tel = preg_replace('/\D+/', '', (string)$item['contact_phone']); ?>
                  <a class="btn" href="tel:<?= htmlspecialchars($tel) ?>">Ligar</a>
                <?php endif; ?>
                
                <?php if (!empty($item['contact_whatsapp'])): ?>
                  <a class="btn" href="https://wa.me/<?= htmlspecialchars(preg_replace('/\D+/', '', $item['contact_whatsapp'])) ?>" target="_blank">WhatsApp</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

      <?php
        $params = $_GET;
        $prevPage = max(1, $page - 1);
        $nextPage = min($totalPages, $page + 1);
        $params['page'] = $prevPage; $prevLink = 'classifieds.php?' . http_build_query($params);
        $params['page'] = $nextPage; $nextLink = 'classifieds.php?' . http_build_query($params);
      ?>
      <div class="form-actions" style="margin-top:10px; display:flex; align-items:center; gap:10px; justify-content:space-between;">
        <div style="display:flex; gap:8px; align-items:center;">
          <a class="btn" href="<?= htmlspecialchars($prevLink) ?>" <?= $page<=1?'style="opacity:.6; pointer-events:none;"':''; ?>>« Anterior</a>
          <span class="desc">Página <?= (int)$page ?> de <?= (int)$totalPages ?> (<?= (int)$totalRows ?> itens)</span>
          <a class="btn" href="<?= htmlspecialchars($nextLink) ?>" <?= $page>=$totalPages?'style="opacity:.6; pointer-events:none;"':''; ?>>Próxima »</a>
        </div>
      </div>
    </section>
  </main>
  <script>
  (function(){
      var els=document.querySelectorAll('.image-viewer');
      els.forEach(function(el){
          var urls=(el.getAttribute('data-urls')||'').split('|').map(function(s){return s.trim();}).filter(function(s){return s.length>0;});
          var idx=0; var img=el.querySelector('.image-viewer-img'); var prev=el.querySelector('.image-prev'); var next=el.querySelector('.image-next');
          function show(i){ 
              idx=i<0?urls.length-1:(i>=urls.length?0:i); 
              if(img){ 
                  var u = urls[idx];
                  if (u.match(/\.(mp4|webm|mov)$/i) || (isVideo && u === cur)) {
                      if (img.tagName !== 'VIDEO') {
                          var v = document.createElement('video');
                          v.className = img.className;
                          v.style.cssText = img.style.cssText;
                          v.style.position = 'relative';
                          v.style.zIndex = '1';
                          v.controls = true;
                          v.autoplay = false;
                          v.setAttribute('playsinline', '');
                          v.setAttribute('webkit-playsinline', '');
                          v.style.background = '#000';
                          v.style.pointerEvents = 'auto';
                          img.parentNode.replaceChild(v, img);
                          img = v;
                      }
                      img.src = u;
                  } else {
                      if (img.tagName !== 'IMG') {
                          var im = document.createElement('img');
                          im.className = img.className;
                          im.style.cssText = img.style.cssText;
                          im.style.background = '';
                          im.alt = 'Imagem';
                          img.parentNode.replaceChild(im, img);
                          img = im;
                      }
                      img.src = u; 
                  }
              } 
          }
          if (urls.length <= 1) {
            if (prev) { prev.style.display = 'none'; }
            if (next) { next.style.display = 'none'; }
          } else {
            if(prev){ prev.addEventListener('click', function(){ show(idx-1); }); }
            if(next){ next.addEventListener('click', function(){ show(idx+1); }); }
          }
          if(img){
            img.addEventListener('click', function(){
              var md=document.querySelector('.modal');
              if(!md){
                md=document.createElement('div');
                md.className='modal';
                md.innerHTML='<div class="modal-card card"><div class="modal-header"><div class="title"></div><button class="close">×</button></div><div class="modal-body" style="overflow:hidden; display:flex; align-items:center; justify-content:center; min-height:300px;"><div class="modal-media" style="width:100%; height:100%; display:flex; justify-content:center; align-items:center; position:relative;"></div><button class="prev" style="z-index:200; position:absolute; left:20px;">«</button><button class="next" style="z-index:200; position:absolute; right:20px;">»</button></div></div>';
                document.body.appendChild(md);
              }
              var mdMedia = md.querySelector('.modal-media');
              var mdPrev=md.querySelector('.prev');
              var mdNext=md.querySelector('.next');
              var mdClose=md.querySelector('.close');
              var mdTitle=md.querySelector('.modal-header .title');
              
              var cur = img.getAttribute('src') || '';
              if (img.tagName === 'VIDEO') {
                  var srcEl = img.querySelector('source');
                  if (srcEl) cur = srcEl.src;
                  else cur = img.src;
              }

              // Normalizar URL para achar índice
              var curPath = cur;
              try { curPath = new URL(cur).pathname; } catch(e) {}
              
              idx = -1;
              for(var k=0; k<urls.length; k++) {
                  if(urls[k] === cur || urls[k].indexOf(curPath) !== -1) {
                      idx = k;
                      break;
                  }
              }
              if(idx<0) idx=0; 
              
              function updateMedia(u) {
                  mdMedia.innerHTML = ''; // Limpa tudo
                  var isVid = u.match(/\.(mp4|webm|mov)$/i);
                  
                  if (isVid) {
                      var v = document.createElement('video');
                      v.controls = true;
                      v.preload = 'metadata';
                      v.setAttribute('playsinline', '');
                      v.setAttribute('webkit-playsinline', '');
                      v.style.maxWidth = '100%';
                      v.style.maxHeight = '80vh';
                      v.style.display = 'block';
                      v.style.background = '#000';
                      v.src = u;
                      mdMedia.appendChild(v);
                  } else {
                      var im = document.createElement('img');
                      im.alt = 'Imagem';
                      im.style.maxWidth = '100%';
                      im.style.maxHeight = '80vh';
                      im.style.objectFit = 'contain';
                      im.style.display = 'block';
                      im.src = u;
                      mdMedia.appendChild(im);
                  }
                  
                  try { 
                      var ou=new URL(u, window.location.href); 
                      var name=(ou.pathname||'').split('/').pop(); 
                      if(mdTitle){ mdTitle.textContent = name || 'Mídia'; } 
                  } catch(e) { 
                      if(mdTitle){ mdTitle.textContent = 'Mídia'; } 
                  }
              }

              updateMedia(urls[idx]);
              md.classList.add('active');
              
              mdPrev.onclick=function(e){ 
                  e.stopPropagation();
                  idx = idx - 1;
                  if(idx < 0) idx = urls.length - 1;
                  updateMedia(urls[idx]); 
              };
              mdNext.onclick=function(e){ 
                  e.stopPropagation();
                  idx = idx + 1;
                  if(idx >= urls.length) idx = 0;
                  updateMedia(urls[idx]); 
              };
              function closeMd(){ md.classList.remove('active'); mdMedia.innerHTML = ''; }
              mdClose.onclick=function(){ closeMd(); };
              md.addEventListener('click', function(e){ if(e.target===md){ closeMd(); } });
              function onKey(e){ if(e.key==='Escape'){ closeMd(); } else if(e.key==='ArrowLeft'){ mdPrev.click(); } else if(e.key==='ArrowRight'){ mdNext.click(); } }
              document.addEventListener('keydown', onKey, { once: true });
            });
          }
      });
  })();
  </script>
</body>
</html>
