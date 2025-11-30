<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$shopId = intval($_GET['shop'] ?? 0);
if (!$shopId) { http_response_code(400); echo 'Missing shop id'; exit; }
ensure_shop_owner($shopId);
$items = $pdo->prepare('SELECT * FROM items WHERE shopId = :s ORDER BY id DESC');
$items->execute([':s'=>$shopId]);
$items = $items->fetchAll(PDO::FETCH_ASSOC);
$metaTitle = 'Manage Items — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Items for Shop #<?php echo $shopId; ?></h2>
  <p><a href="/owner/item_edit?shop=<?php echo $shopId; ?>">Create new item</a></p>
  <table>
    <tr><th>ID</th><th>Name</th><th>PriceTest</th><th>Actions</th></tr>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><?php echo $it['id']; ?></td>
        <td><?php echo htmlspecialchars($it['name']); ?></td>
        <td><?php echo htmlspecialchars($it['priceTest']); ?></td>
        <td>
          <a href="/owner/item_edit?shop=<?php echo $shopId; ?>&id=<?php echo $it['id']; ?>">Edit</a>
          <a href="#" data-delete="<?php echo $it['id']; ?>" class="delete-item">Delete</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
