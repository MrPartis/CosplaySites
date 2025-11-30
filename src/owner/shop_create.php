<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$metaTitle = 'Create Shop — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Create a Shop</h2>
  <form method="post" action="/api/owner/shop_save.php">
    <label>Name<br><input name="name" required></label><br>
    <label>Address<br><input name="address"></label><br>
    <label>Phone<br><input name="phone"></label><br>
    <label>Description<br><textarea name="description"></textarea></label><br>
    <button class="btn">Create shop</button>
  </form>
  <p><a href="/owner/dashboard">Back to dashboard</a></p>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
