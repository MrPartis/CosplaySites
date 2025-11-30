<?php
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../helpers.php';
$pdo = getPDO();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf($_POST['_csrf'] ?? '')) {
    $err = 'Invalid CSRF token';
  } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, passwordHash FROM users WHERE username = :u OR email = :u LIMIT 1');
    $stmt->execute([':u'=>$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $err = '';
    if ($user && password_verify($password, $user['passwordHash'])) {
      
      session_regenerate_id(true);
      $_SESSION['user_id'] = $user['id'];
      
      try {
        $stm2 = $pdo->prepare('SELECT id FROM shops WHERE ownerUserId = :uid LIMIT 1');
        $stm2->execute([':uid' => (int)$user['id']]);
        $shopRow = $stm2->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user_shop_id'] = ($shopRow && !empty($shopRow['id'])) ? (int)$shopRow['id'] : null;
      } catch (Exception $e) {
        
        $_SESSION['user_shop_id'] = null;
      }

      try {
        $keep_signed_in = !empty($_POST['keep_signed_in']) && ($_POST['keep_signed_in'] === '1');
        if ($keep_signed_in) {
          ensure_user_sessions_table($pdo);
          $sid = session_id();
          $ip = $_SERVER['REMOTE_ADDR'] ?? null;
          $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
          
          try {
            $ins = $pdo->prepare('INSERT INTO user_sessions (userId, sessionId, ip, userAgent) VALUES (:uid, :sid, :ip, :ua)');
            $ins->execute([':uid' => (int)$user['id'], ':sid' => $sid, ':ip' => $ip, ':ua' => $ua]);
          } catch (Exception $e) {
            try {
              $upd = $pdo->prepare('UPDATE user_sessions SET ip = :ip, userAgent = :ua, createdAt = CURRENT_TIMESTAMP WHERE sessionId = :sid');
              $upd->execute([':ip' => $ip, ':ua' => $ua, ':sid' => $sid]);
            } catch (Exception $e2) { /* ignore */ }
          }
          
          $q = $pdo->prepare('SELECT id FROM user_sessions WHERE userId = :uid ORDER BY createdAt ASC, id ASC');
          $q->execute([':uid' => (int)$user['id']]);
          $rows = $q->fetchAll(PDO::FETCH_ASSOC);
          $count = count($rows);
          $max = 3;
          if ($count > $max) {
            $toRemove = array_slice($rows, 0, $count - $max);
            foreach ($toRemove as $r) {
              try { $del = $pdo->prepare('DELETE FROM user_sessions WHERE id = :id'); $del->execute([':id' => $r['id']]); } catch (Exception $e) { }
            }
          }
        } else {
          
          
          ensure_temp_user_sessions_table($pdo);
          $sid = session_id();
          $ip = $_SERVER['REMOTE_ADDR'] ?? null;
          $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
          try {
            $ins2 = $pdo->prepare('INSERT INTO temp_user_sessions (userId, sessionId, ip, userAgent) VALUES (:uid, :sid, :ip, :ua)');
            $ins2->execute([':uid' => (int)$user['id'], ':sid' => $sid, ':ip' => $ip, ':ua' => $ua]);
          } catch (Exception $e) {
            try {
              $upd2 = $pdo->prepare('UPDATE temp_user_sessions SET ip = :ip, userAgent = :ua, createdAt = CURRENT_TIMESTAMP WHERE sessionId = :sid');
              $upd2->execute([':ip' => $ip, ':ua' => $ua, ':sid' => $sid]);
            } catch (Exception $e2) { /* ignore */ }
          }
        }
      } catch (Exception $e) {
        
      }
      header('Location: /home'); exit;
    } else {
      $err = 'Invalid credentials';
    }
  }
}
$metaTitle = 'Login — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Login</h2>
  <?php if (!empty($err)): ?><div class="errors"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <style>
    /* Keep checkbox and label visually together in the login form */
    #keep_signed_in_row { display: inline-flex; align-items: center; gap: 8px; }
    #keep_signed_in_row label { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; width: auto; }
    #keep_signed_in_row input[type="checkbox"] { margin: 0; float: none; }
  </style>

  <form method="post">
    <?php echo csrf_field(); ?>
    <label>Username or Email<br><input name="username" required></label><br>
    <label>Password<br><input name="password" type="password" required></label><br>
    <div id="keep_signed_in_row" style="display:flex; align-items:center; gap:8px; margin:8px 0;">
      <label><input type="checkbox" name="keep_signed_in" value="1"> Remember this device</label>
    </div>
    <button>Login</button>
  </form>
  
  <p><a href="/auth/forgot">Forgot password?</a></p>
</section>
<?php require __DIR__ . '/../partials/footer.php';
