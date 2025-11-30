<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$uid = current_user_id();
$shops = $pdo->prepare('SELECT * FROM shops WHERE ownerUserId = :u ORDER BY id DESC');
$shops->execute([':u'=>$uid]);
$shops = $shops->fetchAll(PDO::FETCH_ASSOC);
$metaTitle = 'Owner Dashboard — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Your Shops</h2>
  <p><a href="/admin/categories">Manage Categories (Admin)</a></p>
  <?php if (empty($shops)): ?>
    <p>You don't own any shops yet. <a href="/owner/shop_create">Create your first shop</a>.</p>
  <?php else: ?>
    <ul>
      <li><a href="/owner/shop_create">Create new shop</a></li>
      <?php foreach ($shops as $s): ?>
        <li>
          <strong><?php echo htmlspecialchars($s['name']); ?></strong>
          — <a href="/owner/items?shop=<?php echo $s['id']; ?>">Manage items</a>
          — <a href="/owner/members?shop=<?php echo $s['id']; ?>">Members</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php';
