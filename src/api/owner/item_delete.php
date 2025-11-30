<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$uid = current_user_id();
$id = intval($_POST['id'] ?? 0);
$shopId = intval($_POST['shopId'] ?? 0);
if (!$id || !$shopId) { http_response_code(400); echo 'Missing id or shopId'; exit; }
if (!is_shop_owner($uid, $shopId)) { http_response_code(403); echo 'Forbidden'; exit; }
$stmt = $pdo->prepare('DELETE FROM items WHERE id = :id AND shopId = :s');
$stmt->execute([':id'=>$id, ':s'=>$shopId]);
header('Location: /owner/items?shop=' . $shopId);
exit;
