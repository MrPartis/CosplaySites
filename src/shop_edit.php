<?php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/helpers.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) { $errors[] = 'Invalid CSRF token'; }
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $externalUrl = trim($_POST['externalUrl'] ?? '');
    if ($name === '') { $errors[] = 'Shop name required'; }
    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE shops SET name = :name, address = :address, phone = :phone, description = :description, externalUrl = :external WHERE id = :id');
        $stmt->execute([':name'=>$name, ':address'=>$address, ':phone'=>$phone, ':description'=>$description, ':external'=>$externalUrl, ':id'=>$shop['id']]);
        header('Location: /shop/' . $shop['id']); exit;
    }
}
$metaTitle = 'Edit Shop — ' . ($shop['name'] ?? 'Shop');
require __DIR__ . '/partials/header.php';
?>
<section>
  <h2>Edit Shop</h2>
  <?php if (!empty($errors)): ?><div class="errors"><?php foreach($errors as $er) echo '<div>'.htmlspecialchars($er).'</div>'; ?></div><?php endif; ?>
  <form method="post">
    <?php echo csrf_field(); ?>
    <label>Name<br><input name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? $shop['name']); ?>"></label>
    <label>Address<br><textarea name="address"><?php echo htmlspecialchars($_POST['address'] ?? $shop['address']); ?></textarea></label>
    <label>Phone<br><input name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? $shop['phone']); ?>"></label>
    <label>Description<br><textarea name="description"><?php echo htmlspecialchars($_POST['description'] ?? $shop['description']); ?></textarea></label>
    <label>External URL<br><input name="externalUrl" value="<?php echo htmlspecialchars($_POST['externalUrl'] ?? $shop['externalUrl']); ?>"></label>
    <div style="margin-top:8px"><button class="btn">Save shop</button> <a class="btn" href="/shop/<?php echo $shop['id']; ?>">Cancel</a></div>
  </form>
</section>
<?php require __DIR__ . '/partials/footer.php';
