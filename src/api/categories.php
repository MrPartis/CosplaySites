<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    header('Content-Type: application/json');
    echo json_encode($pdo->query('SELECT id,name,slug FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
if ($method === 'POST') {
    ensure_logged_in();
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($name && $slug) {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO categories (name,slug) VALUES (:n,:s)');
        $stmt->execute([':n'=>$name, ':s'=>$slug]);
        header('Location: /admin/categories'); exit;
    }
}
http_response_code(405);
echo 'Method not allowed';
