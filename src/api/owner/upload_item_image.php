<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
ensure_logged_in();
$pdo = getPDO();
$userId = current_user_id();


$itemId = intval($_POST['itemId'] ?? 0);
if (!$itemId) { http_response_code(400); echo json_encode(['error'=>'Missing itemId']); exit; }


$itm = $pdo->prepare('SELECT id, shopId FROM items WHERE id = :id LIMIT 1');
$itm->execute([':id'=>$itemId]);
$row = $itm->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo json_encode(['error'=>'Item not found']); exit; }
$shopId = intval($row['shopId']);
if (!is_shop_owner($userId, $shopId)) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['error'=>'No file uploaded or upload error']);
  exit;
}

$upDir = __DIR__ . '/../../../data/uploads';
if (!is_dir($upDir)) mkdir($upDir, 0755, true);

$f = $_FILES['file'];
if (!is_uploaded_file($f['tmp_name'])) { http_response_code(400); echo json_encode(['error'=>'Invalid upload']); exit; }
$info = @getimagesize($f['tmp_name']);
if ($info === false) { http_response_code(400); echo json_encode(['error'=>'Not an image']); exit; }
$ext = image_type_to_extension($info[2], false);
$safe = preg_replace('/[^a-z0-9._-]/i', '_', basename($f['name']));
$filename = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safe;
if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== strtolower($ext)) $filename .= '.' . $ext;
$dest = $upDir . '/' . $filename;
if (!move_uploaded_file($f['tmp_name'], $dest)) { http_response_code(500); echo json_encode(['error'=>'Failed to move uploaded file']); exit; }


try {
  $q = $pdo->prepare('SELECT COALESCE(MAX(displayOrder), -1) AS m FROM item_images WHERE itemId = :iid');
  $q->execute([':iid'=>$itemId]);
  $m = intval($q->fetchColumn());
  $displayOrder = $m + 1;
} catch (Exception $e) { $displayOrder = 0; }

$urlPath = '/data/uploads/' . $filename;
try {
  $ins = $pdo->prepare('INSERT INTO item_images (itemId, url, isPrimary, displayOrder) VALUES (:iid, :url, :isPrimary, :displayOrder)');
  
  $isPrimary = 0;
  $c = $pdo->prepare('SELECT COUNT(*) FROM item_images WHERE itemId = :iid'); $c->execute([':iid'=>$itemId]);
  $cnt = intval($c->fetchColumn() ?: 0);
  if ($cnt === 0) $isPrimary = 1;
  $ins->execute([':iid'=>$itemId, ':url'=>$urlPath, ':isPrimary'=>$isPrimary, ':displayOrder'=>$displayOrder]);
  $newId = $pdo->lastInsertId();
  header('Content-Type: application/json');
  echo json_encode(['success'=>true, 'id'=>$newId, 'url'=>$urlPath, 'isPrimary'=>$isPrimary]);
  exit;
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error'=>'DB error']);
  exit;
}

?>
