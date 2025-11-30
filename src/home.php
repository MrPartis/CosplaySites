<?php
require_once __DIR__ . '/helpers.php';
$pdo = getPDO();
$sessionNeeded = (session_status() === PHP_SESSION_NONE);


$items = $pdo->query('SELECT items.id, items.name, items.series, item_images.url AS image, items.priceTest, items.priceShoot, items.priceFestival FROM items LEFT JOIN item_images ON item_images.itemId = items.id AND item_images.isPrimary = 1 GROUP BY items.id ORDER BY items.id DESC LIMIT 12')->fetchAll(PDO::FETCH_ASSOC);
$metaTitle = 'Home - CosplaySites';
$metaDesc = 'Discover cosplay shops and items for rent or sale.';
require __DIR__ . '/partials/header.php';
?>

    <section>
      <h2>Latest Items</h2>
      <div class="items">
      <?php foreach ($items as $it): ?>
        <article class="item" tabindex="0">
          <?php if ($it['image']): ?>
            <img src="<?php echo htmlspecialchars($it['image']); ?>" alt="<?php echo htmlspecialchars($it['name']); ?>">
          <?php else: ?>
            <div class="placeholder">No image</div>
          <?php endif; ?>
          <h3><a href="/item/<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['name']); ?></a></h3>
          <p class="series"><?php echo htmlspecialchars($it['series']); ?></p>
          <p class="price"><?php echo format_price_with_purpose($it); ?></p>
        </article>
      <?php endforeach; ?>
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

