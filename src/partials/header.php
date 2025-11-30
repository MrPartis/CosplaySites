<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../helpers.php';


$__CUR_USER_ID = current_user_id();
if (!isset($metaTitle)) $metaTitle = 'CosplaySites';
if (!isset($metaDesc)) $metaDesc = 'Cosplay rental and shop listings';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($metaTitle); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?php echo empty($__CUR_USER_ID) ? 'guest' : 'auth'; ?>">
  <script>
    window.__USER_LOGGED_IN = <?php echo !empty($__CUR_USER_ID) ? 'true' : 'false'; ?>;
  </script>
  <script>
    
    (function(){
      if (!window.__USER_LOGGED_IN) return;
      var HB_INTERVAL = 60 * 1000; 
      async function refreshSession(){
        try {
          await fetch('/api/refresh_session.php', { method: 'POST', credentials: 'same-origin' });
        } catch(e) { /* ignore network errors */ }
      }
      
      setTimeout(refreshSession, 5000);
      setInterval(refreshSession, HB_INTERVAL);
    })();
  </script>

  <!-- Login modal (reusable) -->
  <div id="loginModal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;z-index:2000" aria-hidden="true">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.6)"></div>
    <div style="position:relative;z-index:2001;background:#fff;border-radius:8px;padding:18px;max-width:420px;width:90%;box-sizing:border-box">
      <button id="loginModalClose" style="position:absolute;right:8px;top:8px">×</button>
      <h3>Please sign in</h3>
      <p>You must be signed in to view this content or perform this action.</p>
      <div style="display:flex;gap:8px;margin-top:12px">
        <a id="loginModalLogin" class="btn" href="/auth/login">Login</a>
        <a id="loginModalRegister" class="btn" href="/auth/register">Register</a>
      </div>
      <div style="margin-top:8px"><a id="loginModalForgot" class="btn" href="/auth/forgot">Forgot password?</a></div>
    </div>
  </div>
  <header class="site-header">
    <div class="container header-inner">
      <div class="logo"><a href="/home">CosplaySites</a></div>
      <nav class="main-nav">
        <a href="/home">Home</a>
        <a href="/products">Products</a>
        <?php
        
        if (!empty($__CUR_USER_ID)) {
          
          if (!empty($_SESSION['user_shop_id'])) {
            echo '<a href="/shop/' . htmlspecialchars((int)$_SESSION['user_shop_id']) . '">Your shop</a>';
          } else {
            try {
              
              if (!function_exists('getPDO')) {
                require_once __DIR__ . '/../db.php';
              }
              $pdo = getPDO();
              $stm = $pdo->prepare('SELECT id FROM shops WHERE ownerUserId = :uid LIMIT 1');
              $stm->execute([':uid' => $__CUR_USER_ID]);
              $s = $stm->fetch(PDO::FETCH_ASSOC);
              if ($s && !empty($s['id'])) {
                echo '<a href="/shop/' . htmlspecialchars($s['id']) . '">Your shop</a>';
              }
            } catch (Exception $e) {
              
            }
          }
        }
        ?>
      </nav>
      <div class="auth-links">
        <?php if (!empty($__CUR_USER_ID)): ?>
          <a href="/auth/logout">Logout</a>
        <?php else: ?>
          <a href="/auth/login">Login</a> | <a href="/auth/register">Register</a>
        <?php endif; ?>
      </div>
      <div class="header-search">
        <input id="search-input" placeholder="Search products by name..." value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <button id="searchBtn" type="button" class="btn">Search</button>
        <div id="search-suggestions" class="suggestions"></div>
      </div>
    </div>
  </header>
  <main class="container">
