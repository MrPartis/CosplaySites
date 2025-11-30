<?php
require_once __DIR__ . '/helpers.php';
$pdo = getPDO();
$perPage = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$sortParam = $_GET['sort'] ?? 'id_desc';

$orderPrefix = (isset($_GET['category']) && intval($_GET['category'])) ? 'i.' : 'items.';
$orderCol = $orderPrefix . 'id';
$orderDir = 'DESC';

if (preg_match('/^(id|name|price)_(asc|desc)$/', $sortParam, $m)) {
  $key = $m[1]; $dir = strtoupper($m[2]);
  $orderDir = ($dir === 'ASC') ? 'ASC' : 'DESC';
  if ($key === 'name') $orderCol = $orderPrefix . 'name';
  elseif ($key === 'price') {
    
    $orderCol = $orderPrefix . 'priceTest';
  } else {
    $orderCol = $orderPrefix . 'id';
  }
} else {
  
  $orderCol = $orderPrefix . 'id';
  $orderDir = 'DESC';
}

$q = trim((string)($_GET['q'] ?? ''));
$priceMin = isset($_GET['price_min']) && is_numeric($_GET['price_min']) ? (int)$_GET['price_min'] : null;
$priceMax = isset($_GET['price_max']) && is_numeric($_GET['price_max']) ? (int)$_GET['price_max'] : null;
$purpose = in_array($_GET['purpose'] ?? '', ['test','shoot','festival']) ? $_GET['purpose'] : null;
$seriesFilter = isset($_GET['series']) ? trim((string)$_GET['series']) : null;
$sizeFilter = isset($_GET['size']) ? trim((string)$_GET['size']) : null;


$where = [];
$params = [];
if ($q !== '') {
  $where[] = '(items.name LIKE :q OR items.series LIKE :q OR items.brand LIKE :q)';
  $params[':q'] = '%' . $q . '%';
}
if ($priceMin !== null || $priceMax !== null) {
  
  $sub = [];
  if ($priceMin !== null) {
    $sub[] = '(items.priceTest IS NOT NULL AND items.priceTest >= :pmin)';
    $sub[] = '(items.priceShoot IS NOT NULL AND items.priceShoot >= :pmin)';
    $sub[] = '(items.priceFestival IS NOT NULL AND items.priceFestival >= :pmin)';
    $params[':pmin'] = $priceMin;
  }
  if ($priceMax !== null) {
    $sub[] = '(items.priceTest IS NOT NULL AND items.priceTest <= :pmax)';
    $sub[] = '(items.priceShoot IS NOT NULL AND items.priceShoot <= :pmax)';
    $sub[] = '(items.priceFestival IS NOT NULL AND items.priceFestival <= :pmax)';
    $params[':pmax'] = $priceMax;
  }
  if (!empty($sub)) $where[] = '(' . implode(' OR ', $sub) . ')';
}
if ($purpose !== null) {
  
  if ($purpose === 'test') $where[] = 'items.priceTest IS NOT NULL';
  if ($purpose === 'shoot') $where[] = 'items.priceShoot IS NOT NULL';
  if ($purpose === 'festival') $where[] = 'items.priceFestival IS NOT NULL';
}
if ($seriesFilter !== null && $seriesFilter !== '') {
  $where[] = 'items.series = :series';
  $params[':series'] = $seriesFilter;
}
if ($sizeFilter !== null && $sizeFilter !== '') {
  $where[] = 'items.size = :size';
  $params[':size'] = $sizeFilter;
}


$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$totalStmt = $pdo->prepare('SELECT COUNT(DISTINCT items.id) FROM items ' . $whereSql);
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();



$priceCol = 'items.priceTest';
if ($purpose === 'shoot') $priceCol = 'items.priceShoot';
if ($purpose === 'festival') $priceCol = 'items.priceFestival';

if (strpos($orderCol, 'priceTest') !== false) {
  $orderCol = $priceCol;
}

$sql = "SELECT items.id, items.name, items.series, items.size, item_images.url AS image, items.priceTest, items.priceShoot, items.priceFestival FROM items LEFT JOIN item_images ON item_images.itemId = items.id AND item_images.isPrimary = 1 " . $whereSql . " GROUP BY items.id ORDER BY " . $orderCol . " " . $orderDir . " LIMIT :lim OFFSET :off";
$items = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $items->bindValue($k, $v); }
$items->bindValue(':lim', $perPage, PDO::PARAM_INT);
$items->bindValue(':off', $offset, PDO::PARAM_INT);
$items->execute();
$items = $items->fetchAll(PDO::FETCH_ASSOC);


$availCountStmt = $pdo->prepare('SELECT COUNT(*) FROM item_availability WHERE itemId = :id AND available = 1');

$metaTitle = 'Products — CosplaySites';
$metaDesc = 'Browse cosplay items, filter and sort results.';
require __DIR__ . '/partials/header.php';
?>
    <section>
      <h2>Products</h2>
      <div class="controls">
        <form method="get" id="filterForm" class="filter-form">
          <label>Price min:<br><input type="number" name="price_min" value="<?php echo htmlspecialchars($priceMin ?? ''); ?>" style="width:120px"></label>
          <label>Price max:<br><input type="number" name="price_max" value="<?php echo htmlspecialchars($priceMax ?? ''); ?>" style="width:120px"></label>
          <label>Purpose:<br>
            <select name="purpose">
              <option value="">Any</option>
              <option value="test" <?php if($purpose==='test') echo 'selected'; ?>>Test</option>
              <option value="shoot" <?php if($purpose==='shoot') echo 'selected'; ?>>Shoot</option>
              <option value="festival" <?php if($purpose==='festival') echo 'selected'; ?>>Festival</option>
            </select>
          </label>
          <label>Series:<br>
            <select name="series">
              <option value="">Any</option>
              <?php foreach ($pdo->query('SELECT DISTINCT series FROM items WHERE series IS NOT NULL AND series != "" ORDER BY series ASC')->fetchAll(PDO::FETCH_COLUMN) as $ser): ?>
                <option value="<?php echo htmlspecialchars($ser); ?>" <?php if($seriesFilter===$ser) echo 'selected'; ?>><?php echo htmlspecialchars($ser); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Size:<br><input name="size" value="<?php echo htmlspecialchars($sizeFilter ?? ''); ?>" placeholder="e.g. M"></label>
          <label style="margin-left:8px">Sort by:
            <select name="sort" onchange="document.getElementById('filterForm').submit()">
              <option value="id_desc" <?php if($sortParam==='id_desc') echo 'selected'; ?>>Newest</option>
              <option value="id_asc" <?php if($sortParam==='id_asc') echo 'selected'; ?>>Oldest</option>
              <option value="name_asc" <?php if($sortParam==='name_asc') echo 'selected'; ?>>Alphabet (A→Z)</option>
              <option value="name_desc" <?php if($sortParam==='name_desc') echo 'selected'; ?>>Alphabet (Z→A)</option>
              <option value="price_asc" <?php if($sortParam==='price_asc') echo 'selected'; ?>>Price ↑</option>
              <option value="price_desc" <?php if($sortParam==='price_desc') echo 'selected'; ?>>Price ↓</option>
            </select>
          </label>
          <div class="filter-actions" style="margin-left:auto"><button class="btn btn-equal" type="submit">Apply</button> <a class="btn btn-equal" href="/products">Reset</a></div>
        </form>
      </div>
      <?php if (empty($items)): ?>
        <div class="no-results" style="padding:32px;text-align:center;color:#666;border:1px dashed #eee;border-radius:8px;background:#fafafa">
          <?php echo "There's nothing but chickens...<br>Try to find something else."; ?>
        </div>
      <?php else: ?>
      <div class="items">
        <?php foreach($items as $it): ?>
          <article class="item" tabindex="0">
            <?php if ($it['image']): ?>
              <img src="<?php echo htmlspecialchars($it['image']); ?>" alt="<?php echo htmlspecialchars($it['name']); ?>">
            <?php else: ?>
              <div class="placeholder">No image</div>
            <?php endif; ?>
            <h3><a href="/item/<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['name']); ?></a></h3>
            <p><?php echo htmlspecialchars($it['series']); ?></p>
            <p class="price"><?php echo format_price_with_purpose($it); ?></p>
            <?php
              $availCountStmt->execute([':id'=>$it['id']]);
              $shopsCount = intval($availCountStmt->fetchColumn());
            ?>
            <?php if ($shopsCount > 0): ?>
              <p class="availability-badge"><?php echo $shopsCount; ?> shop<?php if($shopsCount>1) echo 's'; ?> available</p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="pagination">
        <?php
          
          $preserve = $_GET;
          unset($preserve['page']);
          $baseQs = http_build_query($preserve);
          $pages = max(1, (int)ceil($total / $perPage));

          
          function page_link($n, $label=null, $baseQs=''){
            $label = $label ?? $n;
            $qs = $baseQs ? ($baseQs . '&page=' . $n) : ('page=' . $n);
            return '<a class="shop-page-link" href="/products?' . htmlspecialchars($qs) . '">' . htmlspecialchars($label) . '</a>';
          }

          
          $cur = max(1, intval($_GET['page'] ?? 1));
          echo '<nav aria-label="Page navigation" class="shop-page-controller" role="navigation">';
          
          if ($cur > 1) {
            $prevQs = ($baseQs ? ($baseQs . '&page=' . ($cur-1)) : ('page=' . ($cur-1)));
            echo '<a aria-disabled="false" class="shop-icon-button shop-icon-button--left" href="/products?' . htmlspecialchars($prevQs) . '">‹</a>';
          }

          
          $maxVisible = 5;
          if ($pages <= $maxVisible + 2) {
            
            for ($i=1;$i<=$pages;$i++){
              if ($i === $cur) {
                echo '<a aria-current="true" class="shop-button-solid shop-button-solid--primary" href="/products?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$i):('page='.$i))) . '" style="background-color:#ee4d2d">' . $i . '</a>';
              } else {
                echo '<a class="shop-button-no-outline" href="/products?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$i):('page='.$i))) . '">' . $i . '</a>';
              }
            }
          } else {
            
            if ($cur === 1) {
              echo '<a aria-current="true" class="shop-button-solid shop-button-solid--primary" href="/products?' . htmlspecialchars(($baseQs?($baseQs.'&page=1'):('page=1'))) . '" style="background-color:#ee4d2d">1</a>';
            } else {
              echo '<a class="shop-button-no-outline" href="/products?' . htmlspecialchars(($baseQs?($baseQs.'&page=1'):('page=1'))) . '">1</a>';
            }

            $left = max(2, $cur - 2);
            $right = min($pages-1, $cur + 2);

            if ($left > 2) {
              echo '<a class="shop-button-no-outline shop-button-no-outline--non-click">...</a>';
            }

            for ($i = $left; $i <= $right; $i++){
              if ($i === $cur) {
                echo '<a aria-current="true" class="shop-button-solid shop-button-solid--primary" href="/products?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$i):('page='.$i))) . '" style="background-color:#ee4d2d">' . $i . '</a>';
              } else {
                echo '<a class="shop-button-no-outline" href="/products?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$i):('page='.$i))) . '">' . $i . '</a>';
              }
            }

            if ($right < $pages-1) {
              echo '<a class="shop-button-no-outline shop-button-no-outline--non-click">...</a>';
            }

            
            if ($cur === $pages) {
              echo '<a aria-current="true" class="shop-button-solid shop-button-solid--primary" href="/products?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$pages):('page='.$pages))) . '" style="background-color:#ee4d2d">' . $pages . '</a>';
            } else {
              echo '<a class="shop-button-no-outline" href="/products?' . htmlspecialchars(($baseQs?($baseQs.'&page='.$pages):('page='.$pages))) . '">' . $pages . '</a>';
            }
          }

          
          if ($cur < $pages) {
            $nextQs = ($baseQs ? ($baseQs . '&page=' . ($cur+1)) : ('page=' . ($cur+1)));
            echo '<a aria-disabled="false" class="shop-icon-button shop-icon-button--right" href="/products?' . htmlspecialchars($nextQs) . '">›</a>';
          }

          echo '</nav>';
        ?>
      </div>
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
