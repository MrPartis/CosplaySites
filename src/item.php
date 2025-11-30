<?php
require_once __DIR__ . '/helpers.php';
$pdo = getPDO();


$item = null;
if (!empty($id)) {
    $stmt = $pdo->prepare('SELECT items.*, shops.name AS shop_name FROM items JOIN shops ON shops.id = items.shopId WHERE items.id = :id');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$item) {
    http_response_code(404);
    echo '<h1>Item not found</h1>';
    exit;
}


$imgsStmt = $pdo->prepare('SELECT url, isPrimary FROM item_images WHERE itemId = :id');
$imgsStmt->execute([':id' => $item['id']]);
$imgs = $imgsStmt->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
  
  if (!current_user_id()) {
    
    header('Location: /item/' . $item['id'] . '?login_required=1'); exit;
  }
  if (!verify_csrf($_POST['_csrf'] ?? '')) {
    
    header('Location: /item/' . $item['id']); exit;
  }
  $rating = intval($_POST['feedback_rating'] ?? 0);
  $message = trim($_POST['feedback_message'] ?? '');
  $userId = $_SESSION['user_id'] ?? null;

  
  $hasFile = !empty($_FILES['feedback_media']) && (!empty($_FILES['feedback_media']['name'][0]) || is_array($_FILES['feedback_media']['name']));
  if ($rating <= 0 && $message === '' && !$hasFile) {
    
    header('Location: /item/' . $item['id']); exit;
  }

  
  $upDir = __DIR__ . '/../data/uploads'; if (!is_dir($upDir)) mkdir($upDir, 0755, true);

  $insFb = $pdo->prepare('INSERT INTO feedbacks (itemId, userId, rating, message, createdAt) VALUES (:iid, :uid, :rating, :message, CURRENT_TIMESTAMP)');
  $insFb->execute([':iid' => $item['id'], ':uid' => $userId, ':rating' => ($rating ?: null), ':message' => $message]);
  $fbId = $pdo->lastInsertId();

  
  if (!empty($_FILES['feedback_media'])) {
    $fm = $_FILES['feedback_media'];
    
    $files = [];
    if (is_array($fm['name'])) {
      for ($i = 0; $i < count($fm['name']); $i++) {
        if ($fm['error'][$i] !== UPLOAD_ERR_OK) continue;
        if (!is_uploaded_file($fm['tmp_name'][$i])) continue;
        $files[] = ['tmp'=>$fm['tmp_name'][$i], 'name'=>$fm['name'][$i]];
      }
    } else {
      if ($fm['error'] === UPLOAD_ERR_OK && is_uploaded_file($fm['tmp_name'])) $files[] = ['tmp'=>$fm['tmp_name'], 'name'=>$fm['name']];
    }
    foreach ($files as $f) {
      $info = @getimagesize($f['tmp']);
      $ext = '';
      if ($info !== false) $ext = image_type_to_extension($info[2], false);
      else $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
      $safe = preg_replace('/[^a-z0-9._-]/i', '_', basename($f['name']));
      $filename = time() . '_fb_' . bin2hex(random_bytes(6)) . '_' . $safe;
      if ($ext && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== strtolower($ext)) $filename .= '.' . $ext;
      $dest = $upDir . '/' . $filename;
      if (move_uploaded_file($f['tmp'], $dest)) {
        $urlPath = '/data/uploads/' . $filename;
        $insImg = $pdo->prepare('INSERT INTO feedback_images (feedbackId, url) VALUES (:fid, :url)');
        $insImg->execute([':fid' => $fbId, ':url' => $urlPath]);
      }
    }
  }

  header('Location: /item/' . $item['id']); exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_feedback'])) {
  if (!verify_csrf($_POST['_csrf'] ?? '')) { header('Location: /item/' . $item['id']); exit; }
  $fid = intval($_POST['remove_feedback']);
  if ($fid <= 0) { header('Location: /item/' . $item['id']); exit; }
  $curUid = current_user_id();
  if (!$curUid) { header('Location: /item/' . $item['id']); exit; }
  
  $g = $pdo->prepare('SELECT userId FROM feedbacks WHERE id = :id LIMIT 1');
  $g->execute([':id' => $fid]);
  $row = $g->fetch(PDO::FETCH_ASSOC);
  if ($row && intval($row['userId']) === intval($curUid)) {
    
    $m = $pdo->prepare('SELECT id, url FROM feedback_images WHERE feedbackId = :fid');
    $m->execute([':fid' => $fid]);
    foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $mr) {
      if (!empty($mr['url'])) { $fpath = __DIR__ . '/..' . $mr['url']; if (file_exists($fpath)) @unlink($fpath); }
    }
    $pdo->prepare('DELETE FROM feedback_images WHERE feedbackId = :fid')->execute([':fid' => $fid]);
    $pdo->prepare('DELETE FROM feedbacks WHERE id = :id')->execute([':id' => $fid]);
  }
  header('Location: /item/' . $item['id']); exit;
}


$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;


$countStmt = $pdo->prepare('SELECT COUNT(*) as c FROM feedbacks WHERE itemId = :id');
$countStmt->execute([':id' => $item['id']]);
$totalFeedbacks = (int)($countStmt->fetchColumn() ?: 0);

$feedbacksStmt = $pdo->prepare('SELECT f.*, u.username FROM feedbacks f LEFT JOIN users u ON u.id = f.userId WHERE f.itemId = :id ORDER BY f.createdAt DESC LIMIT :lim OFFSET :off');
$feedbacksStmt->bindValue(':id', $item['id'], PDO::PARAM_INT);
$feedbacksStmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
$feedbacksStmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$feedbacksStmt->execute();
$feedbacks = $feedbacksStmt->fetchAll(PDO::FETCH_ASSOC);


$fbMedia = [];
if (!empty($feedbacks)) {
  $fbIds = array_map(function($f){ return (int)$f['id']; }, $feedbacks);
  $in = implode(',', array_fill(0, count($fbIds), '?'));
  $mediaStmt = $pdo->prepare('SELECT feedbackId, url FROM feedback_images WHERE feedbackId IN (' . $in . ') ORDER BY id ASC');
  $mediaStmt->execute($fbIds);
  $rows = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) {
    $fid = (int)$r['feedbackId'];
    $fbMedia[$fid][] = $r['url'];
  }
}


$catsStmt = $pdo->prepare('SELECT c.* FROM item_categories ic JOIN categories c ON c.id = ic.categoryId WHERE ic.itemId = :id');
$catsStmt->execute([':id' => $item['id']]);
$cats = $catsStmt->fetchAll(PDO::FETCH_ASSOC);


$availStmt = $pdo->prepare('SELECT s.id, s.name, s.externalUrl, ia.available, ia.note FROM item_availability ia JOIN shops s ON s.id = ia.shopId WHERE ia.itemId = :id');
$availStmt->execute([':id' => $item['id']]);
$availability = $availStmt->fetchAll(PDO::FETCH_ASSOC);


$seen = [];
$unique = [];
foreach ($imgs as $imgRow) {
    $u = $imgRow['url'];
    
    $u_no_q = preg_replace('/\?.*$/', '', $u);
    
    $path = parse_url($u_no_q, PHP_URL_PATH);
    $base = basename($path);
    $ext = pathinfo($base, PATHINFO_EXTENSION);
    $name = $ext ? substr($base, 0, -(strlen($ext) + 1)) : $base;
    
    $normname = preg_replace('/(_thumb|_small|_s|_m|_tplv-[^_]+|_tplv.*|-[0-9]+x[0-9]+|_\d+x\d+)$/i', '', $name);
    $normalized = strtolower($normname . ($ext ? '.' . $ext : ''));
    if (isset($seen[$normalized])) continue;
    $seen[$normalized] = true;
    $unique[] = $imgRow;
}


$primaryIndex = 0;
if (!empty($unique)) {
    foreach ($unique as $i => $r) {
        if (!empty($r['isPrimary'])) { $primaryIndex = $i; break; }
    }
    $mainUrl = $unique[$primaryIndex]['url'];
} else {
    $mainUrl = '';
}

$metaTitle = ($item['name'] ?? 'Item') . ' — CosplaySites';
$metaDesc = substr($item['description'] ?? '', 0, 150);
require __DIR__ . '/partials/header.php';
?>

<section class="flex card product-card">
  <section class="left-gallery">
    <div class="gallery-wrap">
      <?php if ($unique): ?>
        <div class="main-img">
          <div class="main-img-inner">
            <img id="mainItemImg" src="<?php echo htmlspecialchars($mainUrl); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
          </div>
        </div>

        <?php if (count($unique) > 1): ?>
          <div class="thumb-strip" id="thumbStrip">
            <button type="button" class="thumb-nav left" id="thumbPrev">‹</button>
            <div class="thumbs" id="thumbsContainer">
              <?php foreach ($unique as $i => $r): ?>
                <?php $tUrl = $r['url']; ?>
                <div class="thumb-item"><img class="thumb <?php echo ($i === $primaryIndex) ? 'active' : ''; ?>" src="<?php echo htmlspecialchars($tUrl); ?>" data-index="<?php echo $i; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"></div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="thumb-nav right" id="thumbNext">›</button>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="placeholder">No images</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="right-details">
    <div class="meta-row">
      <div class="shop-link">Shop: <a href="/shop/<?php echo $item['shopId']; ?>"><?php echo htmlspecialchars($item['shop_name']); ?></a></div>
    </div>
    <h1 class="product-title"><?php echo htmlspecialchars($item['name']); ?></h1>

    <div class="rating-row">
      <div class="rating"><?php echo 'Feedbacks: '.($totalFeedbacks); ?></div>
    </div>

    <div class="price-block">
      <h3>Price:</h3>
      <div class="prices">
        <?php if (isset($item['priceTest'])): ?>
          <div>Test: <?php echo number_format((float)$item['priceTest']); ?>₫</div>
        <?php endif; ?>
        <?php if (isset($item['priceShoot'])): ?>
          <div>Shoot: <?php echo number_format((float)$item['priceShoot']); ?>₫</div>
        <?php endif; ?>
        <?php if (isset($item['priceFestival'])): ?>
          <div>Fes: <?php echo number_format((float)$item['priceFestival']); ?>₫</div>
        <?php endif; ?>
      </div>
    </div>

    <?php
    
    $shopPhone = $shopAddress = $shopExternal = '';
    if (!empty($item['shopId'])) {
      $sStmt = $pdo->prepare('SELECT phone, address, externalUrl FROM shops WHERE id = :id LIMIT 1');
      $sStmt->execute([':id' => $item['shopId']]);
      $sRow = $sStmt->fetch(PDO::FETCH_ASSOC);
      $shopPhone = $sRow['phone'] ?? '';
      $shopAddress = $sRow['address'] ?? '';
      $shopExternal = trim($sRow['externalUrl'] ?? '');
    }
    $shopUrl = '/shop/' . ($item['shopId'] ?? '');
    ?>
    <div class="cta-row">
      <div class="cta-single">
      <button type="button" class="btn" id="contactBtn">Contact</button>
      </div>
    </div>

    <?php if (current_user_id()): ?>
    <script>
    (function(){
      var btn = document.getElementById('contactBtn');
      var shopAddress = <?php echo json_encode($shopAddress); ?>;
      var shopPhone = <?php echo json_encode($shopPhone); ?>;
      var shopExternal = <?php echo json_encode($shopExternal); ?>; 
      var shopPage = <?php echo json_encode($shopUrl); ?>;
      if (!btn) return;

      
      function buildLinksHtml(raw) {
        if (!raw) return '';
        
        var parts = raw.trim().split(/\s+/).filter(Boolean);
        return parts.map(function(p){
          try { var href = (p.match(/^[a-z][a-z0-9+.-]*:/i) ? p : ('https://' + p)); } catch(e){ var href = p; }
          var label = href.replace(/^https?:\/\//i,'').replace(/^www\./i,'');
          return '<a class="external-link" href="'+href.replace(/"/g,'')+'" target="_blank" rel="noopener noreferrer">'+label+'</a>';
        }).join(' ');
      }

      
      var modal = document.createElement('div'); modal.id = 'shopContactModal';
      modal.style.display = 'none'; modal.style.position='fixed'; modal.style.inset='0'; modal.style.alignItems='center'; modal.style.justifyContent='center'; modal.style.zIndex='1600';
      modal.innerHTML = '<div style="position:fixed;inset:0;background:rgba(0,0,0,0.6)"></div><div style="position:relative;z-index:1601;background:#fff;border-radius:8px;padding:16px;max-width:720px;width:90%;box-sizing:border-box">'
        + '<button id="shopContactClose" style="position:absolute;right:10px;top:10px">×</button>'
        + '<h3>Contact</h3>'
        + '<div style="margin-top:8px"><strong>Address:</strong><div style="margin-top:6px">' + (shopAddress ? shopAddress.replace(/\n/g, '<br>') : '<em>Not provided</em>') + '</div></div>'
        + '<div style="margin-top:8px"><strong>Phone:</strong> ' + (shopPhone ? shopPhone : '<em>Not provided</em>') + '</div>'
        + '<div style="margin-top:8px"><strong>Links:</strong> <div style="margin-top:6px">' + buildLinksHtml(shopExternal) + ' &nbsp; <a href="'+shopPage+'">Shop page</a></div></div>'
        + '</div>';
      document.body.appendChild(modal);
      var closeBtn = modal.querySelector('#shopContactClose');
      function openModal(){ modal.style.display = 'flex'; }
      function closeModal(){ modal.style.display = 'none'; }
      btn.addEventListener('click', function(){ openModal(); });
      closeBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
      window.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
    })();
    </script>
    <?php else: ?>
    <!-- Guests: clicking contact redirects to login (no modal) -->
    <script>document.addEventListener('DOMContentLoaded', function(){ var b=document.getElementById('contactBtn'); if (b) b.addEventListener('click', function(e){ e.preventDefault(); try{ var ret=encodeURIComponent(window.location.pathname+window.location.search+window.location.hash); window.location.href='/auth/login?return='+ret;}catch(ex){window.location.href='/auth/login'} }); });</script>
    <?php endif; ?>
  </section>
</section>

<!-- Image viewer modal (hidden) -->
<div id="imageViewerModal" class="image-viewer-modal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="viewer-backdrop" id="viewerBackdrop"></div>
  <div class="viewer-content" role="document" aria-label="Image viewer">
    <button class="viewer-close" id="viewerClose" aria-label="Close viewer">×</button>
    <div class="viewer-main">
      <img id="viewerMainImg" src="<?php echo htmlspecialchars($mainUrl); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
    </div>
    <?php if (count($unique) > 1): ?>
      <div class="viewer-thumb-slider">
        <button type="button" class="thumb-nav left" id="viewerPrev" aria-label="Previous image">‹</button>
        <div class="viewer-thumbs" id="viewerThumbsContainer" role="list">
          <?php foreach ($unique as $i => $r): ?>
            <img role="listitem" class="viewer-thumb <?php echo ($i === $primaryIndex) ? 'active' : ''; ?>" src="<?php echo htmlspecialchars($r['url']); ?>" data-index="<?php echo $i; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
          <?php endforeach; ?>
        </div>
        <button type="button" class="thumb-nav right" id="viewerNext" aria-label="Next image">›</button>
      </div>
    <?php endif; ?>
  </div>
</div>

<section>
  <h3>Feedbacks</h3>
  <form id="leaveFeedbackForm" method="post" enctype="multipart/form-data" class="feedback-form" style="margin-bottom:12px">
    <?php echo csrf_field(); ?>
    <div class="feedback-card" style="display:flex;flex-direction:column;gap:8px">
      <label>Rating<br>
        <div id="userStarRow" class="star-row" style="font-size:1.2rem"> 
          <button type="button" class="star" data-value="1">★</button>
          <button type="button" class="star" data-value="2">★</button>
          <button type="button" class="star" data-value="3">★</button>
          <button type="button" class="star" data-value="4">★</button>
          <button type="button" class="star" data-value="5">★</button>
        </div>
        <input type="hidden" name="feedback_rating" id="feedback_rating_input" value="">
      </label>
      <label>Comment<br><textarea name="feedback_message" rows="4" style="width:100%"></textarea></label>
      <label>Media (optional)<br><input type="file" name="feedback_media[]" accept="image/*,video/*" multiple></label>
      <div><button type="submit" name="submit_feedback" value="1" class="btn">Submit feedback</button></div>
    </div>
  </form>
  <?php if ($feedbacks): foreach ($feedbacks as $f): ?>
    <div class="feedback feedback-box" data-feedback-id="<?php echo $f['id']; ?>">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
        <div><strong><?php echo htmlspecialchars($f['username'] ?: 'Anonymous'); ?></strong></div>
        <div class="rating"><?php echo intval($f['rating']); ?>/5</div>
      </div>
          <?php if (current_user_id() && intval(current_user_id()) === intval($f['userId'])): ?>
            <form method="post" class="fb-remove-form">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="remove_feedback" value="<?php echo (int)$f['id']; ?>">
              <button type="submit" class="small btn" style="background:#c00;border-color:#a00">Remove</button>
            </form>
          <?php endif; ?>
      <div style="margin-top:6px"><?php echo nl2br(htmlspecialchars($f['message'])); ?></div>
      <?php $meds = $fbMedia[$f['id']] ?? []; if (!empty($meds)): ?>
        <div class="feedback-media-strip" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
          <?php foreach ($meds as $m):
            $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
            $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp']);
          ?>
            <div class="fb-media-thumb" style="width:80px;height:130px;overflow:hidden;border:1px solid #eee;border-radius:6px;display:flex;flex-direction:column;align-items:center;padding:6px;cursor:pointer" data-media-src="<?php echo htmlspecialchars($m); ?>" data-media-type="<?php echo $isImg ? 'image' : 'video'; ?>">
              <div style="width:100%;height:80px;overflow:hidden;border-radius:4px;display:flex;align-items:center;justify-content:center">
              <?php if ($isImg): ?>
                <img src="<?php echo htmlspecialchars($m); ?>" style="width:100%;height:100%;object-fit:cover;display:block" alt="">
              <?php else: ?>
                <video src="<?php echo htmlspecialchars($m); ?>" style="width:100%;height:100%;object-fit:cover" muted></video>
              <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; else: ?>
    <p>No feedbacks yet.</p>
  <?php endif; ?>
</section>

<script>

(function(){
  var row = document.getElementById('userStarRow');
  var hidden = document.getElementById('feedback_rating_input');
  if (!row || !hidden) return;
  var stars = Array.from(row.querySelectorAll('.star'));
  function setStars(val){
    stars.forEach(function(s){ var v = parseInt(s.dataset.value,10); if (v <= val) s.classList.add('selected'); else s.classList.remove('selected'); });
    hidden.value = val;
  }
  stars.forEach(function(s){
    s.addEventListener('mouseenter', function(){ setStars(parseInt(s.dataset.value,10)); });
    s.addEventListener('mouseleave', function(){ setStars(parseInt(hidden.value||0,10)); });
    s.addEventListener('click', function(){ setStars(parseInt(s.dataset.value,10)); });
  });
})();
</script>

<?php

$totalPages = max(1, (int)ceil($totalFeedbacks / $perPage));
if ($totalPages > 1) {
  $baseUrl = '/item/' . $item['id'];
  echo '<nav class="pagination" aria-label="Feedback pages"><ul style="list-style:none;display:flex;gap:6px;padding:0;margin-top:8px">';
  if ($page > 1) {
    $prevUrl = $baseUrl . '?page=' . ($page - 1);
    echo '<li><a class="btn" href="' . htmlspecialchars($prevUrl) . '">Prev</a></li>';
  }
  $start = max(1, $page - 2);
  $end = min($totalPages, $page + 2);
  if ($start > 1) {
    $firstUrl = $baseUrl . '?page=1';
    echo '<li><a href="' . htmlspecialchars($firstUrl) . '">1</a></li>';
    if ($start > 2) echo '<li style="padding:6px 8px">…</li>';
  }
  for ($p = $start; $p <= $end; $p++) {
    if ($p == $page) {
      echo '<li><strong style="padding:6px 8px;border:1px solid #888;border-radius:4px;background:#eee">' . $p . '</strong></li>';
    } else {
      $u = $baseUrl . '?page=' . $p;
      echo '<li><a class="btn" href="' . htmlspecialchars($u) . '">' . $p . '</a></li>';
    }
  }
  if ($end < $totalPages) {
    if ($end < $totalPages - 1) echo '<li style="padding:6px 8px">…</li>';
    $lastUrl = $baseUrl . '?page=' . $totalPages;
    echo '<li><a href="' . htmlspecialchars($lastUrl) . '">' . $totalPages . '</a></li>';
  }
  if ($page < $totalPages) {
    $nextUrl = $baseUrl . '?page=' . ($page + 1);
    echo '<li><a class="btn" href="' . htmlspecialchars($nextUrl) . '">Next</a></li>';
  }
  echo '</ul></nav>';
}
?>

<!-- Feedback media modal -->
<div id="feedbackMediaModal" aria-hidden="true" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;z-index:1400">
  <div id="fbModalBackdrop" style="position:fixed;inset:0;background:rgba(0,0,0,0.7)"></div>
  <div style="position:relative;z-index:1401;max-width:90vw;max-height:90vh;padding:12px;display:flex;align-items:center;justify-content:center">
    <button id="fbModalClose" style="position:absolute;right:8px;top:8px;z-index:1402">×</button>
    <div id="fbModalContent" style="max-width:100%;max-height:100%;display:flex;align-items:center;justify-content:center"></div>
  </div>
</div>
<script>
  (function(){
    const modal = document.getElementById('feedbackMediaModal');
    const content = document.getElementById('fbModalContent');
    const close = document.getElementById('fbModalClose');
    const backdrop = document.getElementById('fbModalBackdrop');
    function openMedia(src, type){
      content.innerHTML = '';
      if (type === 'image'){
        const img = document.createElement('img'); img.src = src; img.style.maxWidth='90vw'; img.style.maxHeight='90vh'; img.style.display='block'; content.appendChild(img);
      } else {
        const vid = document.createElement('video'); vid.src = src; vid.controls = true; vid.autoplay = true; vid.style.maxWidth='90vw'; vid.style.maxHeight='90vh'; content.appendChild(vid);
      }
      modal.style.display = 'flex'; modal.setAttribute('aria-hidden','false');
    }
    function closeModal(){ modal.style.display='none'; modal.setAttribute('aria-hidden','true'); content.innerHTML=''; }
    document.querySelectorAll('.fb-media-thumb').forEach(t => t.addEventListener('click', function(){ openMedia(t.dataset.mediaSrc, t.dataset.mediaType); }));
    close.addEventListener('click', closeModal); backdrop.addEventListener('click', closeModal);
    window.addEventListener('keydown', function(e){ if (e.key==='Escape') closeModal(); });
  })();
</script>

<?php
require __DIR__ . '/partials/footer.php';
?>

