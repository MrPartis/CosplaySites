<?php
require_once __DIR__ . '/helpers.php';

$pdo = getPDO();


$shopId = isset($id) ? intval($id) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
if ($shopId <= 0) {
    http_response_code(404);
    echo '<h1>404 Not Found</h1><p>Shop not found.</p>';
    exit;
}


$stm = $pdo->prepare('SELECT * FROM shops WHERE id = :id LIMIT 1');
$stm->execute([':id' => $shopId]);
$shop = $stm->fetch(PDO::FETCH_ASSOC);
if (!$shop) {
    http_response_code(404);
    echo '<h1>404 Not Found</h1><p>Shop not found.</p>';
    exit;
}


if (!empty($action) && $action === 'edit') {
    if (!current_user_owns_shop($pdo, $shopId)) {
        http_response_code(403);
        echo '<h1>Forbidden</h1><p>You do not have permission to edit this shop.</p>';
        exit;
    }
    require __DIR__ . '/shop_edit.php';
    exit;
}
if (!empty($action) && $action === 'add') {
    if (!current_user_owns_shop($pdo, $shopId)) {
        http_response_code(403);
        echo '<h1>Forbidden</h1><p>You do not have permission to add items to this shop.</p>';
        exit;
    }
    require __DIR__ . '/shop_add_item.php';
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_action']) && $_POST['_action'] === 'delete') {
  if (!current_user_owns_shop($pdo, $shopId)) {
    http_response_code(403);
    echo '<h1>Forbidden</h1><p>You do not have permission to delete this item.</p>';
    exit;
  }
  $itemId = intval($_POST['itemId'] ?? 0);
  if ($itemId) {
    
    $imgs = $pdo->prepare('SELECT url FROM item_images WHERE itemId = :id'); $imgs->execute([':id'=>$itemId]);
    foreach ($imgs->fetchAll(PDO::FETCH_ASSOC) as $im) { $f = __DIR__ . '/..' . ($im['url'] ?? ''); if ($f && file_exists($f)) @unlink($f); }
    $pdo->prepare('DELETE FROM item_images WHERE itemId = :id')->execute([':id'=>$itemId]);
    $pdo->prepare('DELETE FROM items WHERE id = :id AND shopId = :sid')->execute([':id'=>$itemId, ':sid'=>$shopId]);
  }
  header('Location: /shop/' . $shopId); exit;
}



$perPage = 12;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE shopId = :sid');
$totalStmt->execute([':sid' => $shopId]);
$total = intval($totalStmt->fetchColumn() ?: 0);

$itemsStm = $pdo->prepare('SELECT i.*, ia.available, ia.note FROM items i LEFT JOIN item_availability ia ON ia.itemId = i.id AND ia.shopId = :sid WHERE i.shopId = :sid ORDER BY i.createdAt DESC LIMIT :lim OFFSET :off');
$itemsStm->bindValue(':sid', $shopId, PDO::PARAM_INT);
$itemsStm->bindValue(':lim', $perPage, PDO::PARAM_INT);
$itemsStm->bindValue(':off', $offset, PDO::PARAM_INT);
$itemsStm->execute();
$items = $itemsStm->fetchAll(PDO::FETCH_ASSOC);


if ($items) {
  $imgQ = $pdo->prepare('SELECT url FROM item_images WHERE itemId = :id ORDER BY displayOrder ASC, id ASC LIMIT 1');
  foreach ($items as &$it) {
    if (empty($it['image'])) {
      $imgQ->execute([':id' => $it['id']]);
      $r = $imgQ->fetch(PDO::FETCH_ASSOC);
      if ($r && !empty($r['url'])) $it['image'] = $r['url'];
    }
  }
  unset($it);
}

$metaDesc = substr($shop['description'] ?? '', 0, 150);
require __DIR__ . '/partials/header.php';
?>

  <div class="shop-header">
    <div class="shop-info">
      <h2><?php echo htmlspecialchars($shop['name']); ?></h2>
      <div class="shop-contact">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <p class="contact-line"><strong>Address:</strong> <span class="private-field"><?php echo nl2br(htmlspecialchars($shop['address'] ?? '')); ?></span></p>
          <p class="contact-line"><strong>Phone:</strong> <span class="private-field"><?php echo htmlspecialchars($shop['phone'] ?? ''); ?></span></p>
        <?php else: ?>
          <p class="contact-line"><strong>Address:</strong> <span class="private-field-guest">Login to view the information</span></p>
          <p class="contact-line"><strong>Phone:</strong> <span class="private-field-guest">Login to view the information</span></p>
        <?php endif; ?>
      </div>
      <?php if (!empty($shop['description'])): ?>
        <p style="margin-top:12px"><?php echo nl2br(htmlspecialchars($shop['description'])); ?></p>
      <?php endif; ?>
      <?php
        $rawExt = trim($shop['externalUrl'] ?? '');
        if ($rawExt !== ''):
          $parts = preg_split('/\s+/', $rawExt);
      ?>
        <p>Original shop page:
        <?php
          $first = true;
          foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $ext = ensure_absolute_url($p);
            $linkLabel = detect_link_type($ext) ?? 'External site';
            if (!$first) echo ' ';
            echo '<a class="external-link" href="' . htmlspecialchars($ext) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($linkLabel) . '</a>';
            $first = false;
          }
        ?>
        </p>
      <?php endif; ?>
    </div>
    <div class="shop-actions">
      <?php if (current_user_owns_shop($pdo, $shopId)): ?>
        <a class="btn" href="/shop/<?php echo $shopId; ?>/edit">Edit shop details</a>
        <a class="btn" href="/shop/<?php echo $shopId; ?>/add">Add new item</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- main shop header contains name/contact/description; duplicated article removed -->

  <!-- owner management panel removed; actions are on the right in the header -->

  <section>
    <h3>Items available at this shop</h3>
    <?php if ($items): ?>
      <div class="items">
      <?php foreach ($items as $it): ?>
        <article class="item" tabindex="0" style="position:relative">
          <?php if (!empty($it['image'])): ?>
            <img src="<?php echo htmlspecialchars($it['image']); ?>" alt="<?php echo htmlspecialchars($it['name']); ?>">
          <?php else: ?>
            <div class="placeholder">No image</div>
          <?php endif; ?>
          <h3><a href="/item/<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['name']); ?></a></h3>
          <p><?php echo htmlspecialchars($it['series']); ?></p>
          <p class="price"><?php echo format_price_with_purpose($it); ?></p>
          <?php if (current_user_owns_shop($pdo, $shopId)): ?>
            <div class="manage-icons">
              <a class="small" href="/shop/<?php echo $shopId; ?>/add?edit=<?php echo $it['id']; ?>" title="Edit">✎</a>
              <form method="post" class="confirm-delete" style="display:inline;margin:0">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="itemId" value="<?php echo $it['id']; ?>">
                <button type="submit" class="small" title="Delete">✕</button>
              </form>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
      </div>
      <div class="pagination">
        <?php
          
          $preserve = $_GET;
          unset($preserve['page']);
          $baseQs = http_build_query($preserve);
          $pages = max(1, (int)ceil($total / $perPage));
          $cur = max(1, intval($_GET['page'] ?? 1));
          echo '<nav aria-label="Page navigation" class="shop-page-controller" role="navigation">';
          
          if ($cur > 1) {
            $prevQs = ($baseQs ? ($baseQs . '&page=' . ($cur-1)) : ('page=' . ($cur-1)));
            echo '<a aria-disabled="false" class="shop-icon-button shop-icon-button--left" href="/shop/' . $shopId . '?' . htmlspecialchars($prevQs) . '">‹</a>';
          }

          $maxVisible = 5;
          if ($pages <= $maxVisible + 2) {
            for ($i=1;$i<=$pages;$i++){
              if ($i === $cur) {
                echo '<a aria-current="true" class="shop-button-solid shop-button-solid--primary" href="/shop/' . $shopId . '?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$i):('page='.$i))) . '" style="background-color:#ee4d2d">' . $i . '</a>';
              } else {
                echo '<a class="shop-button-no-outline" href="/shop/' . $shopId . '?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$i):('page='.$i))) . '">' . $i . '</a>';
              }
            }
          } else {
            if ($cur === 1) {
              echo '<a aria-current="true" class="shop-button-solid shop-button-solid--primary" href="/shop/' . $shopId . '?' . htmlspecialchars(($baseQs?($baseQs.'&page=1'):('page=1'))) . '" style="background-color:#ee4d2d">1</a>';
            } else {
              echo '<a class="shop-button-no-outline" href="/shop/' . $shopId . '?' . htmlspecialchars(($baseQs?($baseQs.'&page=1'):('page=1'))) . '">1</a>';
            }

            $left = max(2, $cur - 2);
            $right = min($pages-1, $cur + 2);

            if ($left > 2) { echo '<a class="shop-button-no-outline shop-button-no-outline--non-click">...</a>'; }

            for ($i = $left; $i <= $right; $i++){
              if ($i === $cur) {
                echo '<a aria-current="true" class="shop-button-solid shop-button-solid--primary" href="/shop/' . $shopId . '?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$i):('page='.$i))) . '" style="background-color:#ee4d2d">' . $i . '</a>';
              } else {
                echo '<a class="shop-button-no-outline" href="/shop/' . $shopId . '?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$i):('page='.$i))) . '">' . $i . '</a>';
              }
            }

            if ($right < $pages-1) { echo '<a class="shop-button-no-outline shop-button-no-outline--non-click">...</a>'; }

            if ($cur === $pages) {
              echo '<a aria-current="true" class="shop-button-solid shop-button-solid--primary" href="/shop/' . $shopId . '?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$pages):('page='.$pages))) . '" style="background-color:#ee4d2d">' . $pages . '</a>';
            } else {
              echo '<a class="shop-button-no-outline" href="/shop/' . $shopId . '?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$pages):('page='.$pages))) . '">' . $pages . '</a>';
            }
          }

          
          if ($cur < $pages) {
            $nextQs = ($baseQs ? ($baseQs . '&page=' . ($cur+1)) : ('page=' . ($cur+1)));
            echo '<a aria-disabled="false" class="shop-icon-button shop-icon-button--right" href="/shop/' . $shopId . '?' . htmlspecialchars($nextQs) . '">›</a>';
          }

          echo '</nav>';
        ?>
      </div>
    <?php else: ?>
      <p>No items listed for this shop.</p>
    <?php endif; ?>
  </section>

  <script>
    (function(){
      
      document.querySelectorAll('.items .item').forEach(function(item){
        
        item.style.cursor = 'pointer';
        item.addEventListener('click', function(e){
          
          if (e.target.closest('a, button, input, select, textarea')) return;
          var a = item.querySelector('h3 a');
          if (a && a.href) {
            window.location.href = a.href;
          }
        });
        
        item.addEventListener('keydown', function(e){
          if (e.key === 'Enter' || e.key === ' ') {
            var a = item.querySelector('h3 a');
            if (a && a.href) {
              window.location.href = a.href;
            }
          }
        });
      });
    })();
  </script>

<?php
require __DIR__ . '/partials/footer.php';

