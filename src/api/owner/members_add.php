<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$uid = current_user_id();
$shopId = intval($_POST['shopId'] ?? 0);
$who = trim($_POST['who'] ?? '');
if (!$shopId || !$who) { http_response_code(400); echo 'Missing params'; exit; }
if (!is_shop_owner($uid, $shopId)) { http_response_code(403); echo 'Forbidden'; exit; }

$stmt = $pdo->prepare('SELECT id FROM users WHERE username = :w OR email = :w LIMIT 1');
$stmt->execute([':w'=>$who]);
$u = $stmt->fetchColumn();
if (!$u) { echo 'User not found'; exit; }

$stmt = $pdo->prepare('INSERT OR IGNORE INTO shop_members (shopId,userId,role) VALUES (:s,:u,:r)');
$stmt->execute([':s'=>$shopId, ':u'=>$u, ':r'=>'cooperator']);
header('Location: /owner/members?shop=' . $shopId);
exit;
