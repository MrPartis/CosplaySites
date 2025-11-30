<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$uid = current_user_id();
$shopId = intval($_POST['shopId'] ?? 0);
if (!$shopId) { http_response_code(400); echo 'Missing shopId'; exit; }
if (!is_shop_owner($uid, $shopId)) { http_response_code(403); echo 'Forbidden'; exit; }
$id = intval($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$priceTest = intval($_POST['priceTest'] ?? 0);
if (!$name) { http_response_code(400); echo 'Name required'; exit; }
if ($id) {
    $stmt = $pdo->prepare('UPDATE items SET name = :n, description = :d, priceTest = :p WHERE id = :id AND shopId = :s');
    $stmt->execute([':n'=>$name, ':d'=>$description, ':p'=>$priceTest, ':id'=>$id, ':s'=>$shopId]);
    header('Location: /owner/items?shop=' . $shopId);
    exit;
} else {
    $stmt = $pdo->prepare('INSERT INTO items (shopId,name,description,priceTest) VALUES (:s,:n,:d,:p)');
    $stmt->execute([':s'=>$shopId, ':n'=>$name, ':d'=>$description, ':p'=>$priceTest]);
    header('Location: /owner/items?shop=' . $shopId);
    exit;
}
