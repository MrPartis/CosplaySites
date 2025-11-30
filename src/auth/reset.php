<?php
$require_db = __DIR__ . '/../db.php';
require_once $require_db;
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../helpers.php';
$pdo = getPDO();
ensure_password_reset_table($pdo);
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $message = 'Invalid CSRF token';
    } else {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            $message = 'Password too short';
        } else {
            $stmt = $pdo->prepare('SELECT userId, expiresAt FROM password_reset_tokens WHERE token = :t LIMIT 1');
            $stmt->execute([':t'=>$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && strtotime($row['expiresAt']) > time()) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET passwordHash = :p WHERE id = :id')->execute([':p'=>$hash, ':id'=>$row['userId']]);
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE token = :t')->execute([':t'=>$token]);
                $message = 'Password reset. You may now <a href="/auth/login">login</a>.';
            } else {
                $message = 'Invalid or expired token.';
            }
        }
    }
}
$metaTitle = 'Reset password — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Reset password</h2>
  <?php if ($message): ?><div class="info"><?=$message?></div><?php endif; ?>
  <?php if (!$message || $_SERVER['REQUEST_METHOD'] === 'GET'): ?>
  <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
    <label>New password<br><input name="password" type="password" required></label><br>
    <button>Set password</button>
  </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/footer.php';
