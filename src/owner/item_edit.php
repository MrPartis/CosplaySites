<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$shopId = intval($_GET['shop'] ?? 0);
if (!$shopId) { http_response_code(400); echo 'Missing shop id'; exit; }
ensure_shop_owner($shopId);
$id = intval($_GET['id'] ?? 0);
$item = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id AND shopId = :s LIMIT 1');
    $stmt->execute([':id'=>$id, ':s'=>$shopId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $price = intval($_POST['priceTest'] ?? 0);
    $data = ['shopId'=>$shopId, 'id'=>$id, 'name'=>$name, 'description'=>$desc, 'priceTest'=>$price];
    
    $_POST = $data;
    require __DIR__ . '/../api/owner/item_save.php';
    exit;
}
$metaTitle = $id ? 'Edit Item' : 'Create Item';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2><?php echo $metaTitle; ?></h2>
  <form method="post">
    <label>Name<br><input name="name" value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" required></label><br>
    <label>Price (test)<br><input name="priceTest" type="number" value="<?php echo htmlspecialchars($item['priceTest'] ?? 0); ?>"></label><br>
    <label>Description<br><textarea name="description"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea></label><br>
    <button class="btn"><?php echo $id ? 'Save' : 'Create'; ?></button>
  </form>
  <?php if ($id): ?>
    <p><a href="/owner/items?shop=<?php echo $shopId; ?>">Back to items</a></p>
  <?php else: ?>
    <p><a href="/owner/items?shop=<?php echo $shopId; ?>">Cancel</a></p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
