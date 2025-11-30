<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$uid = current_user_id();
$shopId = intval($_POST['shopId'] ?? 0);
$userId = intval($_POST['userId'] ?? 0);
if (!$shopId || !$userId) { http_response_code(400); echo 'Missing params'; exit; }
if (!is_shop_owner($uid, $shopId)) { http_response_code(403); echo 'Forbidden'; exit; }
$stmt = $pdo->prepare('DELETE FROM shop_members WHERE shopId = :s AND userId = :u');
$stmt->execute([':s'=>$shopId, ':u'=>$userId]);
header('Location: /owner/members?shop=' . $shopId);
exit;
