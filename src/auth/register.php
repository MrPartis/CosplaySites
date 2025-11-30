<?php
$require_db = __DIR__ . '/../db.php';
require_once $require_db;
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../helpers.php';
$pdo = getPDO();

require_once __DIR__ . '/../helpers.php';
ensure_users_table($pdo);

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $hasAccountType = false;
    if ($driver === 'sqlite') {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if (isset($c['name']) && $c['name'] === 'accountType') { $hasAccountType = true; break; }
        }
    } else {
        
        $res = $pdo->query("SHOW COLUMNS FROM users LIKE 'accountType'")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($res)) $hasAccountType = true;
    }
    if (!$hasAccountType) {
        if ($driver === 'sqlite') {
            $pdo->exec("ALTER TABLE users ADD COLUMN accountType TEXT DEFAULT 'user'");
        } else {
            $pdo->exec("ALTER TABLE users ADD COLUMN accountType VARCHAR(20) DEFAULT 'user'");
        }
    }
} catch (Exception $e) {
    
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    }
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $accountType = $_POST['accountType'] ?? 'user';
    $shopName = trim($_POST['shopName'] ?? '');
    $errors = [];

    
    $username_valid = true;
    $email_valid = true;
    $password_valid = true;
    $account_valid = true;

    if (!$username) { $errors[] = 'Username required'; $username_valid = false; }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Invalid email'; $email_valid = false; }
    if (!$password || strlen($password) < 6) { $errors[] = 'Password required (min 6 chars)'; $password_valid = false; }

    
    $allowedTypes = ['user','shop'];
    if (!in_array($accountType, $allowedTypes, true)) { $errors[] = 'Invalid account type'; $account_valid = false; }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            
            $hasAccountType = false;
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) { if (isset($c['name']) && $c['name'] === 'accountType') { $hasAccountType = true; break; } }
            } else {
                $res = $pdo->query("SHOW COLUMNS FROM users LIKE 'accountType'")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($res)) $hasAccountType = true;
            }
            if ($hasAccountType) {
                $stmt = $pdo->prepare('INSERT INTO users (username,email,passwordHash,accountType) VALUES (:u,:e,:p,:t)');
                $stmt->execute([':u'=>$username,':e'=>$email,':p'=>$hash,':t'=>$accountType]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (username,email,passwordHash) VALUES (:u,:e,:p)');
                $stmt->execute([':u'=>$username,':e'=>$email,':p'=>$hash]);
            }
            $_SESSION['user_id'] = $pdo->lastInsertId();
            
            if ($accountType === 'shop') {
                try {
                    $ownerId = (int)$_SESSION['user_id'];
                    $displayName = $shopName ?: $username;
                    $stmtShop = $pdo->prepare('INSERT INTO shops (ownerUserId, name, address, phone, description, externalUrl, createdAt) VALUES (:owner,:name,NULL,NULL,NULL,NULL,CURRENT_TIMESTAMP)');
                    $stmtShop->execute([':owner'=>$ownerId, ':name'=>$displayName]);
                } catch (Exception $ee) {
                    
                    error_log('Shop creation failed for user ' . $ownerId . ': ' . $ee->getMessage());
                }
            }
            header('Location: /home'); exit;
        } catch (Exception $e) {
            $errors[] = 'User creation failed: ' . $e->getMessage();
        }
    }
    
    $formValues = [];
    if ($username_valid) $formValues['username'] = $username;
    if ($email_valid) $formValues['email'] = $email;
    
    if ($password_valid) $formValues['password'] = $password;
    if ($account_valid) $formValues['accountType'] = $accountType;
    if ($account_valid) $formValues['shopName'] = $shopName;

}
$metaTitle = 'Register — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Register</h2>
  <?php if (!empty($errors)): ?><div class="errors"><?php foreach($errors as $er) echo '<div>'.htmlspecialchars($er).'</div>'; ?></div><?php endif; ?>
    <form method="post">
                <?php echo csrf_field(); ?>
        <label>Username<br><input name="username" required value="<?php echo htmlspecialchars($formValues['username'] ?? ''); ?>"></label><br>
        <label>Email<br><input name="email" type="email" value="<?php echo htmlspecialchars($formValues['email'] ?? ''); ?>"></label><br>
        <label>Account type<br>
            <select name="accountType">
                <option value="user" <?php if(($formValues['accountType'] ?? 'user')==='user') echo 'selected'; ?>>User</option>
                <option value="shop" <?php if(($formValues['accountType'] ?? '')==='shop') echo 'selected'; ?>>Shop (owner)</option>
            </select>
        </label><br>
        <label id="shopNameRow">Shop name (optional — used when creating a shop account)<br><input id="shopNameField" name="shopName" value="<?php echo htmlspecialchars($formValues['shopName'] ?? ''); ?>"></label><br>
        <label>Password<br><input name="password" type="password" required value="<?php echo htmlspecialchars($formValues['password'] ?? ''); ?>"></label><br>
        <button>Register</button>
    </form>
</section>
<?php require __DIR__ . '/../partials/footer.php';
