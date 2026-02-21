<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
session_start();
require_once __DIR__ . '/config.php';
$__out_advdash = false;
register_shutdown_function(function(){
  $e = error_get_last();
  $fatal = $e && in_array($e['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
  if ($fatal && !$GLOBALS['__out_advdash']) {
    if (!headers_sent()) { http_response_code(500); }
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>Erro • Painel do Anunciante</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/styles.css" /></head><body><header class="site-header"><div class="container header-inner"><div class="brand"><span class="logo">🧑‍💼</span><span class="name">Painel do Anunciante</span></div><nav class="actions"><a class="btn" href="index.php">Voltar à loja</a></nav></div></header><main class="container">';
    echo '<div class="notice error">Falha ao carregar painel.</div>';
    echo '</main></body></html>';
  }
});
$db = get_db_soft();
if ($db) { ensure_schema($db); }
if (isset($_GET['ping'])) { $__out_advdash = true; echo 'advertiser-dashboard-ok'; exit; }
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'advertiser') { header('Location: advertiser_login.php'); exit; }
$msg = '';
$type = 'info';
$uid = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_csrf($_POST['csrf'] ?? null)) { $msg='Sessão expirada ou inválida.'; $type='error'; }
  else {
    $op = (string)($_POST['op'] ?? '');
    if ($op === 'create') {
      if (!$db) { $msg='Falha ao conectar ao banco.'; $type='error'; }
      else {
      $title = trim($_POST['title'] ?? '');
      $desc = trim($_POST['description'] ?? '');
      $priceRaw = trim($_POST['price'] ?? '0');
      $p = preg_replace('/[^0-9.,]/','',$priceRaw); $p = str_replace('.','',$p); $p = str_replace(',','.',$p);
      $cur = strtoupper(substr((string)($_POST['currency'] ?? 'BRL'),0,3)); if (!in_array($cur,['USD','BRL','EUR'],true)) { $cur='BRL'; }
      $cat = trim($_POST['category'] ?? '');
      $subcat = trim($_POST['subcategory'] ?? '');
      $phone = trim($_POST['contact_phone'] ?? '');
      $wa = trim($_POST['contact_whatsapp'] ?? '');
      $loc = trim($_POST['location'] ?? '');
      $state = normalize_uf(trim($_POST['state'] ?? ''));
      $neighborhood = trim($_POST['neighborhood'] ?? '');
      $zipcode = normalize_cep(trim($_POST['zipcode'] ?? ''));
      
      // Processamento de vídeos
      require_once __DIR__ . '/video_helper.php';
      $video_files = $_FILES['videos'] ?? [];
      $video_urls_raw = trim($_POST['video_urls'] ?? '');
      $video_urls = process_video_input($video_files, $video_urls_raw);

      $uploaded = [];
      if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) { $dir = __DIR__ . '/uploads/classifieds/'; if (!is_dir($dir)) { @mkdir($dir,0755,true); } $f = $_FILES['images']; $n = min(10, count($f['name'])); for($i=0;$i<$n;$i++){ if($f['error'][$i]===UPLOAD_ERR_OK){ $size = isset($f['size'][$i]) ? (int)$f['size'][$i] : 0; if($size>10*1024*1024) { continue; } $ext=strtolower(pathinfo($f['name'][$i],PATHINFO_EXTENSION)); if(in_array($ext,['jpg','jpeg','png','gif','webp'])){ $name=uniqid('cls_').'.'.$ext; if(move_uploaded_file($f['tmp_name'][$i],$dir.$name)){ $uploaded[]='/uploads/classifieds/'.$name; } } } } }
      $images_raw = trim($_POST['image_urls'] ?? ''); $urlImgs=[]; if($images_raw!==''){ $images_raw=str_replace([';','\n','\r'],[',',',',''],$images_raw); $urlImgs=array_filter(array_map('trim',explode(',',$images_raw))); }
      $image_urls = implode(',', array_merge($uploaded,$urlImgs));
      if ($title==='') { $msg='Informe um título.'; $type='error'; }
      else { $ok = insert_classified($db, ['title'=>$title,'description'=>$desc,'price'=>$p!==''?$p:'0','currency'=>$cur,'category'=>$cat,'subcategory'=>$subcat,'image_urls'=>$image_urls,'video_urls'=>$video_urls,'contact_phone'=>$phone,'contact_whatsapp'=>$wa,'location'=>$loc,'state'=>$state,'neighborhood'=>$neighborhood,'zipcode'=>$zipcode,'user_id'=>$uid]); if ($ok) { $msg='Anúncio criado.'; $type='success'; } else { $msg='Falha ao criar anúncio.'; $type='error'; } }
      }
    }
    if ($op === 'update') {
      if (!$db) { $msg='Falha ao conectar ao banco.'; $type='error'; }
      else {
      $id = (int)($_POST['id'] ?? 0);
      if ($id>0) {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $priceRaw = trim($_POST['price'] ?? '0');
        $p = preg_replace('/[^0-9.,]/','',$priceRaw); $p = str_replace('.','',$p); $p = str_replace(',','.',$p);
        $cur = strtoupper(substr((string)($_POST['currency'] ?? 'BRL'),0,3)); if (!in_array($cur,['USD','BRL','EUR'],true)) { $cur='BRL'; }
        $cat = trim($_POST['category'] ?? '');
        $subcat = trim($_POST['subcategory'] ?? '');
        $phone = trim($_POST['contact_phone'] ?? '');
        $wa = trim($_POST['contact_whatsapp'] ?? '');
      $loc = trim($_POST['location'] ?? '');
      $state = normalize_uf(trim($_POST['state'] ?? ''));
      $neighborhood = trim($_POST['neighborhood'] ?? '');
      $zipcode = normalize_cep(trim($_POST['zipcode'] ?? ''));
        
        // Processamento de vídeos
        require_once __DIR__ . '/video_helper.php';
        $video_files = $_FILES['videos'] ?? [];
        $video_urls_raw = trim($_POST['video_urls'] ?? '');
        $video_urls = process_video_input($video_files, $video_urls_raw);

        $uploaded = [];
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) { $dir = __DIR__ . '/uploads/classifieds/'; if (!is_dir($dir)) { @mkdir($dir,0755,true); } $f = $_FILES['images']; $n = min(10, count($f['name'])); for($i=0;$i<$n;$i++){ if($f['error'][$i]===UPLOAD_ERR_OK){ $size = isset($f['size'][$i]) ? (int)$f['size'][$i] : 0; if($size>10*1024*1024) { continue; } $ext=strtolower(pathinfo($f['name'][$i],PATHINFO_EXTENSION)); if(in_array($ext,['jpg','jpeg','png','gif','webp'])){ $name=uniqid('cls_').'.'.$ext; if(move_uploaded_file($f['tmp_name'][$i],$dir.$name)){ $uploaded[]='/uploads/classifieds/'.$name; } } } } }
        $images_raw = trim($_POST['image_urls'] ?? ''); $urlImgs=[]; if($images_raw!==''){ $images_raw=str_replace([';','\n','\r'],[',',',',''],$images_raw); $urlImgs=array_filter(array_map('trim',explode(',',$images_raw))); }
        $image_urls = implode(',', array_merge($urlImgs,$uploaded));
        $ok = update_classified($db, $id, ['title'=>$title,'description'=>$desc,'price'=>$p!==''?$p:'0','currency'=>$cur,'category'=>$cat,'subcategory'=>$subcat,'image_urls'=>$image_urls,'video_urls'=>$video_urls,'contact_phone'=>$phone,'contact_whatsapp'=>$wa,'location'=>$loc,'state'=>$state,'neighborhood'=>$neighborhood,'zipcode'=>$zipcode]); if ($ok) { $msg='Anúncio atualizado.'; $type='success'; } else { $msg='Falha ao atualizar.'; $type='error'; }
      }
      }
    }
    if ($op === 'delete') { if (!$db) { $msg='Falha ao conectar ao banco.'; $type='error'; } else { $id=(int)($_POST['id']??0); if($id>0){ $ok=delete_classified($db,$id); if($ok){ $msg='Anúncio excluído.'; $type='success'; } else { $msg='Falha ao excluir.'; $type='error'; } } } }
  }
}
$my = [];
if ($db) { if ($res = $db->query('SELECT * FROM classifieds WHERE user_id = ' . $uid . ' ORDER BY created_at DESC')) { while($row=$res->fetch_assoc()){ $my[]=$row; } $res->close(); } }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Painel do Anunciante • PlugPlay Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand"><span class="logo">🧑‍💼</span><span class="name">Painel do Anunciante</span></div>
      <nav class="actions"><a class="btn" href="index.php">Voltar à loja</a><a class="btn" href="classifieds.php" target="_blank">Ver classificados</a><a class="btn" href="logout.php">Sair</a></nav>
    </div>
  </header>
  <?php $__out_advdash = true; ?>
  <?php $debugMode = isset($_GET['debug']); if ($debugMode): ?>
    <div class="notice error">Diagnóstico: <?= htmlspecialchars(db_last_error()) ?> • PHP <?= htmlspecialchars(PHP_VERSION) ?></div>
  <?php endif; ?>
  <main class="container">
    <?php if ($msg): ?><div class="notice <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <section>
      <h2>Criar novo anúncio</h2>
      <form method="post" class="form" enctype="multipart/form-data" style="max-width:680px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
        <input type="hidden" name="op" value="create" />
        <div class="form-row"><label>Título</label><input type="text" name="title" required /></div>
        <div class="form-row"><label>Descrição</label><textarea name="description" rows="4"></textarea></div>
        <div class="form-row"><label>Preço</label><input type="text" inputmode="decimal" name="price" placeholder="Ex: 1.100,00" /></div>
        <div class="form-row"><label>Moeda</label>
          <select name="currency" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
            <option value="BRL" selected>BRL (R$)</option>
            <option value="USD">USD (US$)</option>
            <option value="EUR">EUR (€)</option>
          </select>
        </div>
        <div class="form-row"><label>Categoria</label><input type="text" name="category" /></div>
        <div class="form-row"><label>Subcategoria</label><input type="text" name="subcategory" /></div>
        <div class="form-row"><label>Telefone</label><input type="text" name="contact_phone" /></div>
        <div class="form-row"><label>WhatsApp</label><input type="text" name="contact_whatsapp" /></div>
        <div class="form-row"><label>Cidade</label><input type="text" name="location" placeholder="Ex: Salvador" /></div>
        <div class="form-row"><label>Estado (UF)</label><?php $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']; ?>
          <select name="state" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
            <option value="">Selecione</option>
            <?php foreach($ufs as $uf): ?>
              <option value="<?= $uf ?>"><?= $uf ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row"><label>Bairro</label><input type="text" name="neighborhood" placeholder="Ex: Centro" /></div>
        <div class="form-row"><label>CEP</label><input type="text" name="zipcode" placeholder="Ex: 40000-000" /></div>
        <div class="form-row"><label>Imagens (até 10 • 10MB cada)</label><input type="file" name="images[]" multiple accept="image/*" /></div>
        <div class="form-row"><div class="image-previews" style="display:flex; gap:8px; flex-wrap:wrap;"></div></div>
        <div class="form-row"><label>Ou Links de Imagem</label><textarea name="image_urls" rows="3" placeholder="https://... , https://..."></textarea></div>
        <div class="form-row"><label>Upload de Vídeos</label><input type="file" name="videos[]" multiple accept="video/mp4,video/webm,video/quicktime" /></div>
        <div class="form-row"><label>Ou Links de Vídeo</label><textarea name="video_urls" rows="2" placeholder="https://... , https://..."></textarea></div>
        <div class="form-actions"><button class="btn primary" type="submit">Publicar anúncio</button></div>
      </form>
    </section>
    <section>
      <h2>Meus anúncios</h2>
      <div class="grid" id="my-grid" style="grid-template-columns: repeat(2, 1fr);">
        <?php foreach ($my as $it): ?>
        <div class="card"><div class="card-body">
          <div class="card-title">#<?= (int)$it['id'] ?> • <?= htmlspecialchars($it['title']) ?></div>
          <div class="price"><?= htmlspecialchars(format_money((float)$it['price'], (string)($it['currency'] ?? 'BRL'))) ?></div>
          <?php $imgs = array_filter(array_map('trim', explode(',', (string)($it['image_urls'] ?? '')))); 
          $vids = array_filter(array_map('trim', explode(',', (string)($it['video_urls'] ?? ''))));
          if (!empty($imgs) || !empty($vids)): ?>
          <div class="image-viewer" data-urls="<?= htmlspecialchars(implode('|', array_merge($vids, $imgs))) ?>" style="position:relative; margin-top:8px;">
            <?php if (!empty($vids)): ?>
                <video class="image-viewer-img" controls style="width:100%; max-height:120px; object-fit:cover; border-radius:8px; background:#000;">
                    <source src="<?= htmlspecialchars($vids[0]) ?>" type="video/mp4">
                </video>
            <?php else: ?>
                <img class="image-viewer-img" alt="Imagem" src="<?= htmlspecialchars($imgs[0]) ?>" style="width:100%; max-height:120px; object-fit:cover; border-radius:8px;" />
            <?php endif; ?>
            <?php if ((count($imgs) + count($vids)) > 1): ?>
                <button type="button" class="btn image-prev" style="position:absolute; top:50%; left:8px; transform:translateY(-50%);">«</button>
                <button type="button" class="btn image-next" style="position:absolute; top:50%; right:8px; transform:translateY(-50%);">»</button>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="chips" style="margin-top:8px;">
            <?php if (!empty($it['category'])): ?><span class="chip"><?= htmlspecialchars($it['category']) ?></span><?php endif; ?>
            <?php if (!empty($it['subcategory'])): ?><span class="chip"><?= htmlspecialchars($it['subcategory']) ?></span><?php endif; ?>
          </div>
          <div class="form-actions" style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="btn toggle-edit" data-target="edit-<?= (int)$it['id'] ?>">Editar</button>
            <form method="post" class="form" style="display:inline;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
              <input type="hidden" name="op" value="delete" />
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" />
              <button class="btn" type="submit" style="background:#c00; color:#fff;">Excluir</button>
            </form>
          </div>
          <form method="post" class="form" id="edit-<?= (int)$it['id'] ?>" style="margin-top:8px; display:grid; grid-template-columns: 1fr 1fr; gap:8px; display:none;" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
            <input type="hidden" name="op" value="update" />
            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" />
            <div class="form-row" style="grid-column: 1 / -1;"><label>Título</label><input type="text" name="title" value="<?= htmlspecialchars($it['title']) ?>" required /></div>
            <div class="form-row" style="grid-column: 1 / -1;"><label>Descrição</label><textarea name="description" rows="3"><?= htmlspecialchars($it['description']) ?></textarea></div>
            <div class="form-row"><label>Preço</label><input type="text" inputmode="decimal" name="price" value="<?= htmlspecialchars(number_format((float)$it['price'],2,',','.')) ?>" /></div>
            <div class="form-row"><label>Moeda</label><?php $csel = strtoupper(substr((string)$it['currency'],0,3)); if (!in_array($csel,['USD','BRL','EUR'],true)) $csel='BRL'; ?>
              <select name="currency" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
                <option value="BRL" <?= $csel==='BRL'?'selected':''; ?>>BRL (R$)</option>
                <option value="USD" <?= $csel==='USD'?'selected':''; ?>>USD (US$)</option>
                <option value="EUR" <?= $csel==='EUR'?'selected':''; ?>>EUR (€)</option>
              </select>
            </div>
            <div class="form-row"><label>Categoria</label><input type="text" name="category" value="<?= htmlspecialchars($it['category']) ?>" /></div>
            <div class="form-row"><label>Subcategoria</label><input type="text" name="subcategory" value="<?= htmlspecialchars($it['subcategory']) ?>" /></div>
            <div class="form-row"><label>Telefone</label><input type="text" name="contact_phone" value="<?= htmlspecialchars($it['contact_phone']) ?>" /></div>
            <div class="form-row"><label>WhatsApp</label><input type="text" name="contact_whatsapp" value="<?= htmlspecialchars($it['contact_whatsapp']) ?>" /></div>
            <div class="form-row"><label>Cidade</label><input type="text" name="location" value="<?= htmlspecialchars($it['location']) ?>" /></div>
            <div class="form-row"><label>Estado (UF)</label><?php $ufSel = strtoupper(substr((string)($it['state'] ?? ''),0,2)); $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']; ?>
              <select name="state" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
                <option value=""></option>
                <?php foreach($ufs as $uf): $sel=$ufSel===$uf?'selected':''; ?>
                  <option value="<?= $uf ?>" <?= $sel ?>><?= $uf ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row"><label>Bairro</label><input type="text" name="neighborhood" value="<?= htmlspecialchars($it['neighborhood'] ?? '') ?>" /></div>
            <div class="form-row"><label>CEP</label><input type="text" name="zipcode" value="<?= htmlspecialchars($it['zipcode'] ?? '') ?>" /></div>
            <div class="form-row" style="grid-column: 1 / -1;"><label>Imagens (até 10 • 10MB cada)</label><input type="file" name="images[]" multiple accept="image/*" /></div>
            <div class="form-row" style="grid-column: 1 / -1;"><div class="image-previews" style="display:flex; gap:8px; flex-wrap:wrap;"></div></div>
            <div class="form-row" style="grid-column: 1 / -1;"><label>Links de Imagem</label><textarea name="image_urls" rows="2"><?= htmlspecialchars($it['image_urls']) ?></textarea></div>
            <div class="form-row" style="grid-column: 1 / -1;"><label>Upload de Vídeos</label><input type="file" name="videos[]" multiple accept="video/mp4,video/webm,video/quicktime" /></div>
            <div class="form-row" style="grid-column: 1 / -1;"><label>Links de Vídeo</label><textarea name="video_urls" rows="2"><?= htmlspecialchars($it['video_urls'] ?? '') ?></textarea></div>
            <div class="form-actions" style="grid-column: 1 / -1;"><button class="btn primary" type="submit">Salvar</button></div>
          </form>
        </div></div>
        <?php endforeach; ?>
      </div>
      <div class="form-actions" id="my-pager" style="display:flex; gap:8px; align-items:center; margin-top:10px; flex-wrap:wrap;">
        <div class="form-row" style="display:flex; align-items:center; gap:8px;">
          <label for="my-size-select">Itens por página</label>
          <select id="my-size-select" style="height: 42px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); padding: 0 12px;">
            <option value="6">6</option>
            <option value="10" selected>10</option>
            <option value="20">20</option>
          </select>
        </div>
        <button type="button" class="btn pager-prev">« Anterior</button>
        <button type="button" class="btn pager-next">Próxima »</button>
        <span class="chip pager-info"></span>
      </div>
    </section>
  </main>
  <script>
  (function(){
      var els=document.querySelectorAll('.image-viewer');
      els.forEach(function(el){
          var urls=(el.getAttribute('data-urls')||'').split('|').map(function(s){return s.trim();}).filter(function(s){return s.length>0;});
          var idx=0; var img=el.querySelector('.image-viewer-img'); var prev=el.querySelector('.image-prev'); var next=el.querySelector('.image-next');
          
          function updateView(u) {
              // Limpar container e criar do zero
              var parent = img.parentNode;
              var newEl;
              
              if (u.match(/\.(mp4|webm|mov)$/i)) {
                  newEl = document.createElement('video');
                  newEl.className = 'image-viewer-img'; // Reusar classe base para layout se necessário
                  newEl.controls = true;
                  newEl.preload = 'metadata';
                  newEl.setAttribute('playsinline', '');
                  newEl.setAttribute('webkit-playsinline', '');
                  newEl.style.width = '100%';
                  newEl.style.height = '100%';
                  newEl.style.objectFit = 'contain';
                  newEl.style.background = '#000';
                  newEl.src = u;
              } else {
                  newEl = document.createElement('img');
                  newEl.className = 'image-viewer-img';
                  newEl.alt = 'Imagem';
                  newEl.style.width = '100%';
                  newEl.style.height = '100%';
                  newEl.style.objectFit = 'cover';
                  newEl.style.borderRadius = '8px';
                  newEl.src = u;
              }
              
              // Substituição limpa
              if (img && img.parentNode) {
                  img.parentNode.replaceChild(newEl, img);
              } else {
                  // Fallback se algo estranho aconteceu
                  var container = el.querySelector('.image-viewer-img') ? el.querySelector('.image-viewer-img').parentNode : el;
                  container.appendChild(newEl);
              }
              img = newEl; // Atualiza referência
          }

          function show(i){ 
              idx=i<0?urls.length-1:(i>=urls.length?0:i); 
              if(img){ updateView(urls[idx]); } 
          }

          if (urls.length <= 1) {
            if (prev) { prev.style.display = 'none'; }
            if (next) { next.style.display = 'none'; }
          } else {
            if(prev){ prev.addEventListener('click', function(){ show(idx-1); }); }
            if(next){ next.addEventListener('click', function(){ show(idx+1); }); }
          }
      });
  })();
  </script>
  <script>
  (function(){
    var grid=document.getElementById('my-grid');
    var cards=grid?Array.from(grid.querySelectorAll('.card')):[];
    var pager=document.getElementById('my-pager');
    var prev=pager?pager.querySelector('.pager-prev'):null;
    var next=pager?pager.querySelector('.pager-next'):null;
    var info=pager?pager.querySelector('.pager-info'):null;
    var sizeSelect=document.getElementById('my-size-select');
    var size=10; var stored=localStorage.getItem('advMyPageSize'); if(stored){ var v=parseInt(stored,10); if([6,10,20].indexOf(v)!==-1){ size=v; } }
    if(sizeSelect){ sizeSelect.value=String(size); }
    var page=0;
    function renderPage(){ var total=cards.length; var pages=Math.max(1,Math.ceil(total/size)); for(var i=0;i<cards.length;i++){ var show=(i>=page*size && i<(page+1)*size); cards[i].style.display=show?'block':'none'; } if(info){ info.textContent='Página '+(page+1)+' de '+pages+' ('+total+' itens)'; } if(prev){ prev.disabled=(page<=0); } if(next){ next.disabled=(page>=pages-1); } }
    if(prev){ prev.addEventListener('click', function(){ var total=cards.length; var pages=Math.max(1,Math.ceil(total/size)); page = Math.max(0, page-1); renderPage(); }); }
    if(next){ next.addEventListener('click', function(){ var total=cards.length; var pages=Math.max(1,Math.ceil(total/size)); page = Math.min(pages-1, page+1); renderPage(); }); }
    if(sizeSelect){ sizeSelect.addEventListener('change', function(){ var v=parseInt(this.value,10)||10; size=v; try{ localStorage.setItem('advMyPageSize', String(v)); }catch(e){} page=0; renderPage(); }); }
    renderPage();
  })();
  </script>
  <script>
  (function(){
    document.querySelectorAll('.toggle-edit').forEach(function(btn){
      btn.addEventListener('click', function(){ var id=this.getAttribute('data-target'); var el=id?document.getElementById(id):null; if(!el) return; el.style.display = (el.style.display==='none' || el.style.display==='') ? 'grid' : 'none'; });
    });
  })();
  </script>
  <script>
  (function(){
    var MAX=10, SZ=10*1024*1024, WM=new WeakMap();
    function K(f){ return (f.name||'')+'|'+(f.size||0)+'|'+(f.lastModified||0); }
    function setFiles(inp, files){ var dt=new DataTransfer(); for(var i=0;i<files.length;i++){ dt.items.add(files[i]); } inp.files=dt.files; WM.set(inp, Array.from(inp.files||[])); }
    function merge(inp){ var prev=WM.get(inp)||[]; var fresh=Array.from(inp.files||[]); var all=[]; for(var i=0;i<fresh.length;i++){ if(fresh[i].size<=SZ) all.push(fresh[i]); } var seen=new Set(all.map(K)); for(var i=0;i<prev.length && all.length<MAX;i++){ var fk=K(prev[i]); if(!seen.has(fk)){ seen.add(fk); all.push(prev[i]); } } if(all.length>MAX){ all=all.slice(0,MAX); } setFiles(inp, all); }
    function parseUrls(ta){ var v=(ta.value||'').replace(/[;\n\r]+/g, ','); var urls=v.split(',').map(function(s){ return s.trim(); }).filter(function(s){ return s.length>0; }); return urls; }
    function render(inp){ var form=inp.closest('form'); if(!form) return; var cont=form.querySelector('.image-previews'); if(!cont) return; cont.innerHTML=''; var files=WM.get(inp)||Array.from(inp.files||[]); var show=files.slice(0,MAX); for(var i=0;i<show.length;i++){ var f=show[i]; var wrap=document.createElement('div'); wrap.style.position='relative'; wrap.style.width='100px'; wrap.style.height='100px'; var img=new Image(); img.src=URL.createObjectURL(f); img.style.width='100%'; img.style.height='100%'; img.style.objectFit='cover'; img.style.borderRadius='8px'; var rm=document.createElement('button'); rm.type='button'; rm.textContent='×'; rm.dataset.k=K(f); rm.style.position='absolute'; rm.style.top='-6px'; rm.style.right='-6px'; rm.style.width='22px'; rm.style.height='22px'; rm.style.border='none'; rm.style.borderRadius='50%'; rm.style.background='#c00'; rm.style.color='#fff'; rm.style.cursor='pointer'; rm.addEventListener('click', function(){ var key=this.dataset.k; var next=[]; for(var j=0;j<files.length;j++){ if(K(files[j])!==key) next.push(files[j]); } setFiles(inp,next); render(inp); }); wrap.appendChild(img); wrap.appendChild(rm); cont.appendChild(wrap); }
      var ta=form.querySelector('textarea[name="image_urls"]'); if(ta){ var urls=parseUrls(ta); for(var i=0;i<urls.length && i<MAX;i++){ var u=urls[i]; var wrap=document.createElement('div'); wrap.style.position='relative'; wrap.style.width='100px'; wrap.style.height='100px'; var img=new Image(); img.src=u; img.style.width='100%'; img.style.height='100%'; img.style.objectFit='cover'; img.style.borderRadius='8px'; var rm=document.createElement('button'); rm.type='button'; rm.textContent='×'; rm.dataset.url=u; rm.style.position='absolute'; rm.style.top='-6px'; rm.style.right='-6px'; rm.style.width='22px'; rm.style.height='22px'; rm.style.border='none'; rm.style.borderRadius='50%'; rm.style.background='#c00'; rm.style.color='#fff'; rm.style.cursor='pointer'; rm.addEventListener('click', function(){ var url=this.dataset.url; var list=parseUrls(ta).filter(function(s){ return s!==url; }); ta.value=list.join(','); render(inp); }); wrap.appendChild(img); wrap.appendChild(rm); cont.appendChild(wrap); } }
    }
    document.querySelectorAll('input[type="file"][name="images[]"]').forEach(function(inp){ inp.addEventListener('change', function(){ merge(inp); render(inp); }); var form=inp.closest('form'); if(form){ var ta=form.querySelector('textarea[name="image_urls"]'); if(ta){ ta.addEventListener('input', function(){ render(inp); }); } form.addEventListener('submit', function(e){ var files=WM.get(inp)||Array.from(inp.files||[]); var urls=[]; if(ta){ urls=parseUrls(ta); } if(files.length>MAX || (files.length+urls.length)>MAX){ e.preventDefault(); alert('No máximo 10 imagens no total.'); return; } for(var i=0;i<files.length;i++){ if(files[i].size>SZ){ e.preventDefault(); alert('Cada imagem deve ter até 10MB.'); return; } } setFiles(inp, files); }); } });
  })();
  </script>
</body>
</html>
