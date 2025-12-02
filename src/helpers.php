<?php

if (session_status() === PHP_SESSION_NONE) session_start();

// Project-level paths
if (!function_exists('project_root')) {
    function project_root()
    {
        static $root = null;
        if ($root !== null) return $root;
        $root = realpath(__DIR__ . '/..');
        if ($root === false) $root = __DIR__ . '/..';
        return $root;
    }
}

if (!function_exists('upload_dir')) {
    function upload_dir()
    {
        $d = project_root() . '/data/uploads';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        return $d;
    }
}

if (!function_exists('upload_url')) {
    function upload_url()
    {
        // This returns the web path relative to the web root. Adjust if your app is served from a subpath.
        return '/data/uploads';
    }
}

function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field() {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $t . '">';
}

function verify_csrf($token) {
    if (empty($_SESSION['_csrf_token'])) return false;
    return hash_equals($_SESSION['_csrf_token'], (string)$token);
}


function ensure_password_reset_table($pdo) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Exception $e) {
        $driver = null;
    }
    if ($driver === 'mysql' || $driver === 'mysqli' || $driver === 'pdo_mysql') {
        $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            userId INT UNSIGNED NOT NULL,
            token VARCHAR(128) NOT NULL,
            expiresAt DATETIME NOT NULL,
            INDEX idx_prt_user (userId),
            INDEX idx_prt_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
    } else {
        
        $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            userId INTEGER NOT NULL,
            token TEXT NOT NULL,
            expiresAt DATETIME NOT NULL
        );";
        $pdo->exec($sql);
    }
}


function ensure_user_sessions_table($pdo) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Exception $e) {
        $driver = null;
    }
    if ($driver === 'mysql' || $driver === 'mysqli' || $driver === 'pdo_mysql') {
        $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            userId INT UNSIGNED NOT NULL,
            sessionId VARCHAR(128) NOT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            userAgent VARCHAR(512) DEFAULT NULL,
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_user_sessions_session (sessionId),
            INDEX idx_us_user (userId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
    } else {
        $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            userId INTEGER NOT NULL,
            sessionId TEXT NOT NULL,
            ip TEXT,
            userAgent TEXT,
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $pdo->exec($sql);
        try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_us_user ON user_sessions(userId);"); } catch (Exception $e) {}
    }
}


function ensure_temp_user_sessions_table($pdo) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Exception $e) {
        $driver = null;
    }
    if ($driver === 'mysql' || $driver === 'mysqli' || $driver === 'pdo_mysql') {
        $sql = "CREATE TABLE IF NOT EXISTS temp_user_sessions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            userId INT UNSIGNED NOT NULL,
            sessionId VARCHAR(128) NOT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            userAgent VARCHAR(512) DEFAULT NULL,
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_temp_user_sessions_session (sessionId),
            INDEX idx_tus_user (userId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
    } else {
        $sql = "CREATE TABLE IF NOT EXISTS temp_user_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            userId INTEGER NOT NULL,
            sessionId TEXT NOT NULL,
            ip TEXT,
            userAgent TEXT,
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $pdo->exec($sql);
        try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tus_user ON temp_user_sessions(userId);"); } catch (Exception $e) {}
    }
}


function ensure_users_table($pdo) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Exception $e) {
        $driver = null;
    }
    if ($driver === 'mysql' || $driver === 'mysqli' || $driver === 'pdo_mysql') {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            passwordHash VARCHAR(255) DEFAULT NULL,
            accountType VARCHAR(20) NOT NULL DEFAULT 'user',
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
    } else {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT,
            passwordHash TEXT,
            accountType TEXT DEFAULT 'user',
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $pdo->exec($sql);
    }
}


function clean_text($s) {
    return trim((string)$s);
}

require_once __DIR__ . '/db.php';
function current_user_id()
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) return null;
    $uid = intval($_SESSION['user_id']);
    
    try {
        $pdo = getPDO();
        ensure_user_sessions_table($pdo);
        
        ensure_temp_user_sessions_table($pdo);
        $sid = session_id();
        
        $stm = $pdo->prepare('SELECT COUNT(*) FROM user_sessions WHERE userId = :uid AND sessionId = :sid');
        $stm->execute([':uid' => $uid, ':sid' => $sid]);
        $ok = intval($stm->fetchColumn() ?: 0) > 0;
        if (!$ok) {
            
            
            try {
                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            } catch (Exception $e) {
                $driver = null;
            }
            if ($driver === 'mysql' || $driver === 'mysqli' || $driver === 'pdo_mysql') {
                $stm2 = $pdo->prepare('SELECT COUNT(*) FROM temp_user_sessions WHERE userId = :uid AND sessionId = :sid AND createdAt >= (NOW() - INTERVAL 5 MINUTE)');
            } else {
                $stm2 = $pdo->prepare("SELECT COUNT(*) FROM temp_user_sessions WHERE userId = :uid AND sessionId = :sid AND datetime(createdAt) >= datetime('now','-5 minutes')");
            }
            $stm2->execute([':uid' => $uid, ':sid' => $sid]);
            $ok2 = intval($stm2->fetchColumn() ?: 0) > 0;
            if (!$ok2) {
                
                $_SESSION = [];
                if (session_status() !== PHP_SESSION_NONE) {
                    
                }
                return null;
            }
        }
    } catch (Exception $e) {
        
        return $uid;
    }
    return $uid;
}

function ensure_logged_in()
{
    if (!current_user_id()) {
        header('Location: /auth/login');
        exit;
    }
}

function is_shop_owner($userId, $shopId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shops WHERE id = :s AND ownerUserId = :u');
    $stmt->execute([':s' => $shopId, ':u' => $userId]);
    return $stmt->fetchColumn() > 0;
}

function ensure_shop_owner($shopId)
{
    $uid = current_user_id();
    if (!$uid || !is_shop_owner($uid, $shopId)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Owner access required.</p>';
        exit;
    }
}


function current_user_owns_shop($pdo, $shopId)
{
    $uid = current_user_id();
    if (!$uid) return false;
    try {
        $stm = $pdo->prepare('SELECT id FROM shops WHERE id = :id AND ownerUserId = :uid LIMIT 1');
        $stm->execute([':id' => $shopId, ':uid' => $uid]);
        return (bool)$stm->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Ensure a URL is absolute by adding a default scheme when missing.
 * Returns normalized URL string, or null if input is empty.
 */
function ensure_absolute_url($url)
{
    $u = trim((string)$url);
    if ($u === '') return null;
    
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $u)) {
        return $u;
    }
    
    return 'https://' . $u;
}

/**
 * Detect common link types from a URL and return a short label.
 * Examples: facebook.com -> 'Facebook', youtube.com -> 'YouTube', etc.
 * If not recognized, returns the host (e.g., example.com) or 'External site'.
 */
function detect_link_type($url)
{
    $u = trim((string)$url);
    if ($u === '') return null;
    
    if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $u)) {
        $u = ensure_absolute_url($u);
    }
    $parts = @parse_url($u);
    if (empty($parts['host'])) {
        return 'External site';
    }
    $host = strtolower($parts['host']);
    
    $h = preg_replace('/^www\./i', '', $host);
    
    $map = [
        'facebook.com' => 'Facebook',
        'fb.com' => 'Facebook',
        'instagram.com' => 'Instagram',
        'twitter.com' => 'Twitter',
        'x.com' => 'X',
        'youtube.com' => 'YouTube',
        'youtu.be' => 'YouTube',
        'tiktok.com' => 'TikTok',
        'github.com' => 'GitHub',
        'discord.gg' => 'Discord',
        'etsy.com' => 'Etsy',
        'shopify.com' => 'Shopify',
        'linkedin.com' => 'LinkedIn',
        'steamcommunity.com' => 'Steam',
        'pinterest.com' => 'Pinterest'
    ];
    foreach ($map as $k => $label) {
        if (stripos($h, $k) !== false) return $label;
    }
    
    return $h;
}

/**
 * Given an item row (assoc array), return the minimum non-empty price and its purpose.
 * Returns array: [priceValue|null, purposeString|null]
 * purposeString is one of: 'test', 'shoot', 'festival'
 */
function get_item_min_price_and_purpose($item)
{
    $map = [
        'priceTest' => 'test',
        'priceShoot' => 'shoot',
        'priceFestival' => 'festival'
    ];
    $vals = [];
    foreach ($map as $field => $label) {
        if (!isset($item[$field])) continue;
        $v = $item[$field];
        if ($v === '' || $v === null) continue;
        
        if (is_numeric($v)) {
            $vals[$label] = (int)round((float)$v);
        }
    }
    if (empty($vals)) return [null, null];
    $min = null; $minLabel = null;
    foreach ($vals as $label => $v) {
        if ($min === null || $v < $min) { $min = $v; $minLabel = $label; }
    }
    return [$min, $minLabel];
}

/**
 * Format the item's minimum price with purpose, e.g. "150,000₫ (test)".
 * Returns an HTML-escaped safe string (numbers and ascii text), or an empty string when no price.
 */
function format_price_with_purpose($item)
{
    list($price, $purpose) = get_item_min_price_and_purpose($item);
    if ($price === null) return '';
    
    $fmt = number_format($price, 0, '.', ',') . '₫';
    if ($purpose) $fmt .= ' (' . htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') . ')';
    return $fmt;
}
