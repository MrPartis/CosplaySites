<?php
require_once __DIR__ . '/../../src/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$msg = '';

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'cosplay_sites';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $msg = 'Invalid CSRF token';
    } else {
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';
        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            $_SESSION['is_admin_db'] = true;
            $_SESSION['admin_db_user'] = $user;
            $_SESSION['admin_db_pass'] = $pass;
            
            header('Location: /admin');
            exit;
        } catch (Exception $e) {
            $msg = 'DB connection failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$metaTitle = 'Admin login — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Admin DB Login</h2>
  <?php if ($msg): ?><div class="errors"><?php echo $msg; ?></div><?php endif; ?>
  <p>This page authenticates using database user credentials (MySQL). This grants elevated admin actions. For security, use this only on local or trusted hosts.</p>
  <form method="post">
    <?php echo csrf_field(); ?>
    <label>DB Host<br><input name="db_host" value="<?php echo htmlspecialchars($dbHost); ?>" disabled></label><br>
    <label>DB Name<br><input name="db_name" value="<?php echo htmlspecialchars($dbName); ?>" disabled></label><br>
    <label>DB Username<br><input name="db_user" required></label><br>
    <label>DB Password<br><input name="db_pass" type="password"></label><br>
    <button>Login</button>
  </form>
</section>
<?php require __DIR__ . '/../partials/footer.php';
