<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$uid = current_user_id();
$name = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$description = trim($_POST['description'] ?? '');
if (!$name) { http_response_code(400); echo 'Name required'; exit; }
$stmt = $pdo->prepare('INSERT INTO shops (ownerUserId,name,address,phone,description) VALUES (:u,:n,:a,:p,:d)');
$stmt->execute([':u'=>$uid, ':n'=>$name, ':a'=>$address, ':p'=>$phone, ':d'=>$description]);
$shopId = $pdo->lastInsertId();
header('Location: /owner/dashboard');
exit;
