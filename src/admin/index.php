<?php
require_once __DIR__ . '/../../src/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function getAdminPDO() {
    if (empty($_SESSION['is_admin_db'])) return null;
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'cosplay_sites';
    $user = $_SESSION['admin_db_user'] ?? null;
    $pass = $_SESSION['admin_db_pass'] ?? null;
    if (!$user) return null;
    try {
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Exception $e) {
        return null;
    }
}

$adminPdo = getAdminPDO();
if (!$adminPdo) {
    header('Location: /admin/login');
    exit;
}

$msg = '';

$users = $adminPdo->query('SELECT id, username, email, accountType, createdAt FROM users ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$shops = $adminPdo->query('SELECT id, name, ownerUserId, address, phone, createdAt FROM shops ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);

$metaTitle = 'Admin — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Admin Dashboard</h2>
  <p><a href="/admin/logout">Logout</a></p>

  <h3>Users</h3>
  <table border="1" cellpadding="6">
    <tr><th>ID</th><th>Username</th><th>Email</th><th>Type</th><th>Created</th><th>Actions</th></tr>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?php echo $u['id']; ?></td>
        <td><?php echo htmlspecialchars($u['username']); ?></td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td><?php echo htmlspecialchars($u['accountType']); ?></td>
        <td><?php echo htmlspecialchars($u['createdAt']); ?></td>
        <td>
          <form method="post" action="/admin/action.php" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
            <button onclick="return confirm('Delete user <?php echo htmlspecialchars($u['username']); ?>?')">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h3>Shops</h3>
  <table border="1" cellpadding="6">
    <tr><th>ID</th><th>Name</th><th>Owner</th><th>Phone</th><th>Created</th><th>Actions</th></tr>
    <?php foreach ($shops as $s): ?>
      <tr>
        <td><?php echo $s['id']; ?></td>
        <td><?php echo htmlspecialchars($s['name']); ?></td>
        <td><?php echo htmlspecialchars($s['ownerUserId']); ?></td>
        <td><?php echo htmlspecialchars($s['phone']); ?></td>
        <td><?php echo htmlspecialchars($s['createdAt']); ?></td>
        <td>
          <form method="post" action="/admin/action.php" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="delete_shop">
            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
            <button onclick="return confirm('Delete shop <?php echo htmlspecialchars($s['name']); ?> and its items?')">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

</section>

<?php require __DIR__ . '/../partials/footer.php';
