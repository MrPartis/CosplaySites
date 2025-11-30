<?php
$require_db = __DIR__ . '/../db.php';
require_once $require_db;
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../helpers.php';
$pdo = getPDO();
ensure_password_reset_table($pdo);
$message = '';
$sendLog = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf($_POST['_csrf'] ?? '')) {
    $message = 'Invalid CSRF token';
  } else {
    $email = trim($_POST['email'] ?? '');
    if ($email) {
      $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
      $stmt->execute([':e'=>$email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($user) {
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (userId, token, expiresAt) VALUES (:uid, :t, :ex)');
        $stmt->execute([':uid'=>$user['id'], ':t'=>$token, ':ex'=>$expires]);
        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/auth/reset?token=' . $token;
        
        require_once __DIR__ . '/../mail.php';
        $sendRes = send_email_reset($email, $link);
        
        if (is_array($sendRes)) {
          $sendLog = '<pre style="white-space:pre-wrap;word-break:break-word">' . htmlspecialchars(json_encode($sendRes, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) . '</pre>';
        } else {
          $sendLog = '<pre>' . htmlspecialchars(var_export($sendRes, true)) . '</pre>';
        }
        if (is_array($sendRes) && !$sendRes['ok']) {
          
          error_log('SMTP send failed: ' . ($sendRes['error'] ?? 'unknown'));
          $message = 'Reset link (dev, email failed): ' . htmlspecialchars($link) . ' (email error logged)';
        } else {
          $message = 'If that email exists we have sent reset instructions. Please check your inbox.';
        }
      } else {
        $message = 'If that email exists we have sent reset instructions. Please check your inbox.';
      }
    }
  }
}

$metaTitle = 'Forgot Password — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Forgot password</h2>
  <?php if ($message): ?><div class="info"><?=$message?></div><?php endif; ?>
    <?php if ($sendLog): ?><div class="info">SMTP result: <?=$sendLog?></div><?php endif; ?>
  <form method="post">
    <?php echo csrf_field(); ?>
    <label>Email<br><input name="email" type="email" required></label><br>
    <button>Send reset link</button>
  </form>
</section>
<?php require __DIR__ . '/../partials/footer.php';
