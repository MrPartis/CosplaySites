<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$shopId = intval($_GET['shop'] ?? 0);
if (!$shopId) { http_response_code(400); echo 'Missing shop id'; exit; }
ensure_shop_owner($shopId);
$members = $pdo->prepare('SELECT sm.*, u.username, u.email FROM shop_members sm JOIN users u ON u.id = sm.userId WHERE sm.shopId = :s');
$members->execute([':s'=>$shopId]);
$members = $members->fetchAll(PDO::FETCH_ASSOC);
$metaTitle = 'Shop Members — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Members for Shop #<?php echo $shopId; ?></h2>
  <form method="post" action="/api/owner/members_add.php">
    <input type="hidden" name="shopId" value="<?php echo $shopId; ?>">
    <label>Username or Email to add<br><input name="who" required></label>
    <button class="btn">Add member</button>
  </form>
  <table>
    <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Action</th></tr>
    <?php foreach ($members as $m): ?>
      <tr>
        <td><?php echo $m['id']; ?></td>
        <td><?php echo htmlspecialchars($m['username']); ?></td>
        <td><?php echo htmlspecialchars($m['email']); ?></td>
        <td><?php echo htmlspecialchars($m['role']); ?></td>
        <td>
          <form method="post" action="/api/owner/members_remove.php" style="display:inline">
            <input type="hidden" name="shopId" value="<?php echo $shopId; ?>">
            <input type="hidden" name="userId" value="<?php echo $m['userId']; ?>">
            <button class="btn small">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
