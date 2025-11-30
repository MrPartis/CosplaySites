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

$pdo = getAdminPDO();
if (!$pdo) { http_response_code(403); echo 'Admin login required'; exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; exit; }
if (!verify_csrf($_POST['_csrf'] ?? '')) { http_response_code(400); echo 'Invalid CSRF'; exit; }

$action = $_POST['action'] ?? '';
try {
    if ($action === 'delete_user') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id'=>$id]);
        }
    } elseif ($action === 'delete_shop') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            
            $stmt = $pdo->prepare('DELETE FROM shops WHERE id = :id');
            $stmt->execute([':id'=>$id]);
        }
    }
} catch (Exception $e) {
    error_log('Admin action error: ' . $e->getMessage());
}

header('Location: /admin');
exit;
