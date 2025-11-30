<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();

$pdo = getPDO();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($name && $slug) {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO categories (name,slug) VALUES (:n,:s)');
        $stmt->execute([':n'=>$name, ':s'=>$slug]);
        header('Location: /admin/categories'); exit;
    }
}
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$metaTitle = 'Categories — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Categories</h2>
  <form method="post">
    <label>Name<br><input name="name" required></label><br>
    <label>Slug<br><input name="slug" required></label><br>
    <button>Add Category</button>
  </form>
  <ul>
    <?php foreach ($cats as $c): ?>
      <li><?php echo htmlspecialchars($c['name']); ?> — <?php echo htmlspecialchars($c['slug']); ?></li>
    <?php endforeach; ?>
  </ul>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
