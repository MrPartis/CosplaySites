<?php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/helpers.php';
$errors = [];
$form = ['name'=>'','series'=>'','brand'=>'','size'=>'','priceTest'=>'','priceShoot'=>'','priceFestival'=>'','sourceLink'=>'','description'=>''];


$editingItemId = null;
if (!empty($_GET['edit'])) {
  $editingItemId = intval($_GET['edit']);
}


$existingImages = [];
if ($editingItemId) {
  $itmStmt = $pdo->prepare('SELECT * FROM items WHERE id = :id AND shopId = :sid LIMIT 1');
  $itmStmt->execute([':id' => $editingItemId, ':sid' => $shop['id']]);
  $existingItem = $itmStmt->fetch(PDO::FETCH_ASSOC);
  if ($existingItem) {
    foreach ($form as $k => $_) $form[$k] = $existingItem[$k] ?? '';
    
    $imgSt = $pdo->prepare('SELECT id, url, isPrimary, displayOrder FROM item_images WHERE itemId = :id ORDER BY displayOrder ASC, id ASC');
    $imgSt->execute([':id' => $editingItemId]);
    $existingImages = $imgSt->fetchAll(PDO::FETCH_ASSOC);
    
    $fbSt = $pdo->prepare('SELECT id, userId, rating, message FROM feedbacks WHERE itemId = :id ORDER BY createdAt DESC');
    $fbSt->execute([':id' => $editingItemId]);
    $existingFeedbacks = $fbSt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($existingFeedbacks)) {
      $fbIds = array_column($existingFeedbacks, 'id');
      $in = implode(',', array_fill(0, count($fbIds), '?'));
      $mSt = $pdo->prepare('SELECT id, feedbackId, url FROM feedback_images WHERE feedbackId IN (' . $in . ') ORDER BY id ASC');
      $mSt->execute($fbIds);
      $rows = $mSt->fetchAll(PDO::FETCH_ASSOC);
      $existingFeedbackMedia = [];
      foreach ($rows as $r) {
        $existingFeedbackMedia[$r['feedbackId']][] = $r;
      }
    } else {
      $existingFeedbacks = [];
      $existingFeedbackMedia = [];
    }
  } else {
    
    $editingItemId = null;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf($_POST['_csrf'] ?? '')) { $errors[] = 'Invalid CSRF token'; }
  foreach ($form as $k => $v) { $form[$k] = trim($_POST[$k] ?? ''); }
    if ($form['name'] === '') $errors[] = 'Item name required';
    $prices = [$form['priceTest'],$form['priceShoot'],$form['priceFestival']];
    $hasValidPrice = false;
    foreach ($prices as $p) { if ($p === '') continue; if (is_numeric($p)) { $hasValidPrice = true; break; } }
    if (!$hasValidPrice) $errors[] = 'At least one price (Test/Shoot/Festival) must be filled with a valid number';
    if (empty($errors)) {
        $pTest = $form['priceTest'] === '' ? null : (int)round((float)$form['priceTest']);
        $pShoot = $form['priceShoot'] === '' ? null : (int)round((float)$form['priceShoot']);
        $pFest = $form['priceFestival'] === '' ? null : (int)round((float)$form['priceFestival']);
        if ($editingItemId) {
          
          $up = $pdo->prepare('UPDATE items SET name=:name, series=:series, brand=:brand, size=:size, priceTest=:priceTest, priceShoot=:priceShoot, priceFestival=:priceFestival, sourceLink=:sourceLink, description=:description WHERE id=:id AND shopId=:sid');
          $up->execute([':name'=>$form['name'], ':series'=>$form['series'], ':brand'=>$form['brand'], ':size'=>$form['size'], ':priceTest'=>$pTest, ':priceShoot'=>$pShoot, ':priceFestival'=>$pFest, ':sourceLink'=>$form['sourceLink'], ':description'=>$form['description'], ':id'=>$editingItemId, ':sid'=>$shop['id']]);
          $newItemId = $editingItemId;
        } else {
          $stmt = $pdo->prepare('INSERT INTO items (shopId, name, series, brand, size, priceTest, priceShoot, priceFestival, sourceLink, description, createdAt) VALUES (:sid, :name, :series, :brand, :size, :priceTest, :priceShoot, :priceFestival, :sourceLink, :description, CURRENT_TIMESTAMP)');
          $stmt->execute([':sid'=>$shop['id'], ':name'=>$form['name'], ':series'=>$form['series'], ':brand'=>$form['brand'], ':size'=>$form['size'], ':priceTest'=>$pTest, ':priceShoot'=>$pShoot, ':priceFestival'=>$pFest, ':sourceLink'=>$form['sourceLink'], ':description'=>$form['description']]);
          $newItemId = $pdo->lastInsertId();
        }

      
      $newInsertedIds = [];
      
      $upDir = __DIR__ . '/../data/uploads';
      if (!is_dir($upDir)) mkdir($upDir, 0755, true);

      if (!empty($_FILES['images'])) {
        
        $upDir = __DIR__ . '/../data/uploads';
        if (!is_dir($upDir)) mkdir($upDir, 0755, true);

        
        try {
          $pdo->exec("ALTER TABLE item_images ADD COLUMN displayOrder INTEGER DEFAULT 0;");
        } catch (Exception $e) {
          
        }

        $files = $_FILES['images'];
        $count = count($files['name']);
        $saved = 0;
        for ($i = 0; $i < $count && $saved < 10; $i++) {
          if (empty($files['name'][$i])) continue;
          if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
          if (!is_uploaded_file($files['tmp_name'][$i])) continue;
          
          $info = @getimagesize($files['tmp_name'][$i]);
          if ($info === false) continue;
          $ext = image_type_to_extension($info[2], false);
          $safe = preg_replace('/[^a-z0-9._-]/i', '_', basename($files['name'][$i]));
          $filename = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safe;
          
          if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== strtolower($ext)) {
            $filename .= '.' . $ext;
          }
          $dest = $upDir . '/' . $filename;
          if (!move_uploaded_file($files['tmp_name'][$i], $dest)) continue;
          
          $displayOrder = $saved; 
          $isPrimary = ($saved === 0) ? 1 : 0;
          
          $urlPath = '/data/uploads/' . $filename;
          $ins = $pdo->prepare('INSERT INTO item_images (itemId, url, isPrimary, displayOrder) VALUES (:iid, :url, :isPrimary, :displayOrder)');
          try {
            $ins->execute([':iid'=>$newItemId, ':url'=>$urlPath, ':isPrimary'=>$isPrimary, ':displayOrder'=>$displayOrder]);
            $newInsertedIds[] = $pdo->lastInsertId();
          } catch (Exception $e) {
            
            $ins2 = $pdo->prepare('INSERT INTO item_images (itemId, url, isPrimary) VALUES (:iid, :url, :isPrimary)');
            $ins2->execute([':iid'=>$newItemId, ':url'=>$urlPath, ':isPrimary'=>$isPrimary]);
            $newInsertedIds[] = $pdo->lastInsertId();
          }
          $saved++;
        }
      }
      
      if ($editingItemId) {
        
        $toRemove = $_POST['feedback_existing_remove'] ?? [];
        if (is_array($toRemove) && count($toRemove)) {
          foreach ($toRemove as $fid => $val) {
            if (!$val) continue;
            $fid = intval($fid);
            
            $g = $pdo->prepare('SELECT id,url FROM feedback_images WHERE feedbackId = :fid'); $g->execute([':fid'=>$fid]);
            foreach ($g->fetchAll(PDO::FETCH_ASSOC) as $r) { $fpath = __DIR__ . '/..' . ($r['url'] ?? ''); if ($fpath && file_exists($fpath)) @unlink($fpath); }
            $pdo->prepare('DELETE FROM feedback_images WHERE feedbackId = :fid')->execute([':fid'=>$fid]);
            $pdo->prepare('DELETE FROM feedbacks WHERE id = :fid')->execute([':fid'=>$fid]);
          }
        }

        
        $existingRatings = $_POST['feedback_existing_rating'] ?? [];
        $existingMessages = $_POST['feedback_existing_message'] ?? [];
        foreach ($existingRatings as $fid => $rval) {
          $fid = intval($fid);
          if (isset($toRemove[$fid]) && $toRemove[$fid]) continue;
          $rating = intval($rval);
          $message = trim($existingMessages[$fid] ?? '');
          $u = $pdo->prepare('UPDATE feedbacks SET rating = :rating, message = :message WHERE id = :id');
          $u->execute([':rating'=>($rating ?: null), ':message'=>$message, ':id'=>$fid]);
        }

        
        $mediaRemove = $_POST['feedback_existing_media_remove'] ?? [];
        if (is_array($mediaRemove) && count($mediaRemove)) {
          foreach ($mediaRemove as $mid => $v) {
            if (!$v) continue;
            $mid = intval($mid);
            $g = $pdo->prepare('SELECT url FROM feedback_images WHERE id = :id LIMIT 1'); $g->execute([':id'=>$mid]); $r = $g->fetch(PDO::FETCH_ASSOC);
            if ($r && !empty($r['url'])) { $f = __DIR__ . '/..' . $r['url']; if (file_exists($f)) @unlink($f); }
            $pdo->prepare('DELETE FROM feedback_images WHERE id = :id')->execute([':id'=>$mid]);
          }
        }

        
        if (!empty($_FILES['feedback_existing_media'])) {
          $fexists = $_FILES['feedback_existing_media'];
          
          foreach ($fexists['name'] as $fbId => $arr) {
            $fbId = intval($fbId);
            if (!is_array($arr)) continue;
            foreach ($arr as $i => $name) {
              if ($fexists['error'][$fbId][$i] !== UPLOAD_ERR_OK) continue;
              if (!is_uploaded_file($fexists['tmp_name'][$fbId][$i])) continue;
              $tmp = $fexists['tmp_name'][$fbId][$i];
              $origName = $fexists['name'][$fbId][$i];
              $info = @getimagesize($tmp);
              $ext = '';
              if ($info !== false) $ext = image_type_to_extension($info[2], false);
              else $ext = pathinfo($origName, PATHINFO_EXTENSION);
              $safe = preg_replace('/[^a-z0-9._-]/i', '_', basename($origName));
              $filename = time() . '_fb_' . bin2hex(random_bytes(6)) . '_' . $safe;
              if ($ext && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== strtolower($ext)) $filename .= '.' . $ext;
              $dest = $upDir . '/' . $filename;
              if (move_uploaded_file($tmp, $dest)) {
                $urlPath = '/data/uploads/' . $filename;
                $insImg = $pdo->prepare('INSERT INTO feedback_images (feedbackId, url) VALUES (:fid, :url)');
                $insImg->execute([':fid'=>$fbId, ':url'=>$urlPath]);
              }
            }
          }
        }
      }

      
      if (!empty($_POST['feedback_rating']) && is_array($_POST['feedback_rating'])) {
        $fbRatings = $_POST['feedback_rating'];
        $fbMessages = $_POST['feedback_message'] ?? [];
        $fbFiles = $_FILES['feedback_media'] ?? null; 
        $maxFeedbacks = 5;
        $count = 0;
        foreach ($fbRatings as $key => $ratingVal) {
          if ($count >= $maxFeedbacks) break;
          $rating = intval($ratingVal);
          $message = trim($fbMessages[$key] ?? '');
          
          $hasFile = false;
          $filesForKey = [];
          if ($fbFiles && isset($fbFiles['name'][$key]) ) {
            $names = $fbFiles['name'][$key];
            if (is_array($names)) {
              foreach ($names as $fi => $fname) {
                if ($fbFiles['error'][$key][$fi] === UPLOAD_ERR_OK) {
                  $hasFile = true;
                  $filesForKey[] = [
                    'tmp_name' => $fbFiles['tmp_name'][$key][$fi],
                    'name' => $fbFiles['name'][$key][$fi],
                    'type' => $fbFiles['type'][$key][$fi],
                  ];
                }
              }
            } elseif (!empty($names)) {
              
              if ($fbFiles['error'][$key] === UPLOAD_ERR_OK) {
                $hasFile = true;
                $filesForKey[] = [ 'tmp_name' => $fbFiles['tmp_name'][$key], 'name' => $fbFiles['name'][$key], 'type' => $fbFiles['type'][$key] ];
              }
            }
          }
          if ($rating <= 0 && $message === '' && !$hasFile) continue;
          $insFb = $pdo->prepare('INSERT INTO feedbacks (itemId, userId, rating, message) VALUES (:iid, :uid, :rating, :message)');
          $userId = $_SESSION['user_id'] ?? null;
          $insFb->execute([':iid' => $newItemId, ':uid' => $userId, ':rating' => ($rating ?: null), ':message' => $message]);
          $fbId = $pdo->lastInsertId();
          
          foreach ($filesForKey as $finfo) {
            if (!is_uploaded_file($finfo['tmp_name'])) continue;
            $info = @getimagesize($finfo['tmp_name']);
            
            $ext = '';
            if ($info !== false) {
              $ext = image_type_to_extension($info[2], false);
            } else {
              $ext = pathinfo($finfo['name'], PATHINFO_EXTENSION);
            }
            $safe = preg_replace('/[^a-z0-9._-]/i', '_', basename($finfo['name']));
            $filename = time() . '_fb_' . bin2hex(random_bytes(6)) . '_' . $safe;
            if ($ext && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== strtolower($ext)) { $filename .= '.' . $ext; }
            $dest = $upDir . '/' . $filename;
            if (move_uploaded_file($finfo['tmp_name'], $dest)) {
              $urlPath = '/data/uploads/' . $filename;
              $insImg = $pdo->prepare('INSERT INTO feedback_images (feedbackId, url) VALUES (:fid, :url)');
              $insImg->execute([':fid' => $fbId, ':url' => $urlPath]);
            }
          }
          $count++;
        }
      }
      
      if ($editingItemId) {
        $combined = $_POST['combinedOrder'] ?? null;
        $finalOrder = [];
        if (is_array($combined) && count($combined)) {
          
          foreach ($combined as $entry) {
            if (!is_string($entry)) continue;
            if (strpos($entry, 'e') === 0) {
              $id = intval(substr($entry,1)); if ($id) $finalOrder[] = $id;
            } elseif (strpos($entry, 'n') === 0) {
              $n = intval(substr($entry,1)); if (isset($newInsertedIds[$n])) $finalOrder[] = intval($newInsertedIds[$n]);
            }
          }
        } else {
          
          $existingOrder = $_POST['existingOrder'] ?? [];
          $existingOrder = array_map('intval', $existingOrder);
          $finalOrder = $existingOrder;
        }

        
        $curImgs = $pdo->prepare('SELECT id, url FROM item_images WHERE itemId = :id');
        $curImgs->execute([':id'=>$editingItemId]);
        $curImgs = $curImgs->fetchAll(PDO::FETCH_ASSOC);
        $curIds = array_column($curImgs, 'id');

        
        $toDelete = array_diff($curIds, $finalOrder);
        foreach ($toDelete as $delId) {
          
          $g = $pdo->prepare('SELECT url FROM item_images WHERE id = :id LIMIT 1'); $g->execute([':id'=>$delId]); $r = $g->fetch(PDO::FETCH_ASSOC);
          if ($r && !empty($r['url'])) {
            $f = __DIR__ . '/..' . $r['url']; if (file_exists($f)) @unlink($f);
          }
          $d = $pdo->prepare('DELETE FROM item_images WHERE id = :id'); $d->execute([':id'=>$delId]);
        }

        
        foreach ($finalOrder as $idx => $imgId) {
          $u = $pdo->prepare('UPDATE item_images SET displayOrder = :ord WHERE id = :id');
          $u->execute([':ord'=>$idx, ':id'=>$imgId]);
        }
        
        try {
          
          $clear = $pdo->prepare('UPDATE item_images SET isPrimary = 0 WHERE itemId = :iid');
          $clear->execute([':iid' => $editingItemId]);
          if (!empty($finalOrder)) {
            $firstId = intval($finalOrder[0]);
            $set = $pdo->prepare('UPDATE item_images SET isPrimary = 1 WHERE id = :id');
            $set->execute([':id' => $firstId]);
          }
        } catch (Exception $e) {
          
        }
      }

      
      
      
      try {
        $priQ = $pdo->prepare('SELECT url FROM item_images WHERE itemId = :iid ORDER BY isPrimary DESC, displayOrder ASC, id ASC LIMIT 1');
        $priQ->execute([':iid' => $newItemId]);
        $pri = $priQ->fetch(PDO::FETCH_ASSOC);
        $imgUrl = $pri && !empty($pri['url']) ? $pri['url'] : null;
        $updIt = $pdo->prepare('UPDATE items SET image = :img WHERE id = :id AND shopId = :sid');
        $updIt->execute([':img' => $imgUrl, ':id' => $newItemId, ':sid' => $shop['id']]);
      } catch (Exception $e) {
        
      }

      header('Location: /shop/' . $shop['id']); exit;
    }
}
$metaTitle = ($editingItemId ? 'Edit Item' : 'Add Item') . ' — ' . ($shop['name'] ?? 'Shop');
require __DIR__ . '/partials/header.php';
?>
<section>
  <h2><?php echo $editingItemId ? 'Edit item in ' : 'Add item to '; ?><?php echo htmlspecialchars($shop['name']); ?></h2>
  <?php if (!empty($errors)): ?><div class="errors"><?php foreach($errors as $er) echo '<div>'.htmlspecialchars($er).'</div>'; ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <label>Name<br><input name="name" value="<?php echo htmlspecialchars($form['name']); ?>"></label>
    <label>Series<br><input name="series" value="<?php echo htmlspecialchars($form['series']); ?>"></label>
    <label>Brand<br><input name="brand" value="<?php echo htmlspecialchars($form['brand']); ?>"></label>
    <label>Size<br><input name="size" value="<?php echo htmlspecialchars($form['size']); ?>"></label>
    <label>Price (Test)<br><input name="priceTest" type="number" step="0.01" value="<?php echo htmlspecialchars($form['priceTest']); ?>"></label>
    <label>Price (Shoot)<br><input name="priceShoot" type="number" step="0.01" value="<?php echo htmlspecialchars($form['priceShoot']); ?>"></label>
    <label>Price (Festival)<br><input name="priceFestival" type="number" step="0.01" value="<?php echo htmlspecialchars($form['priceFestival']); ?>"></label>
    <label>Source Link<br><input name="sourceLink" value="<?php echo htmlspecialchars($form['sourceLink']); ?>"></label>
    <label>Description<br><textarea name="description"><?php echo htmlspecialchars($form['description']); ?></textarea></label>
    <fieldset style="margin-top:8px;padding:8px;border:1px dashed #ddd">
      <legend>Images (optional, up to 10) — drag to reorder</legend>
      <div style="margin:6px 0">
        <input type="file" id="imagesInput" name="images[]" accept="image/*" multiple>
      </div>
      <div id="imagePreview" data-unified="1"<?php if ($editingItemId) echo ' data-item-id="' . intval($editingItemId) . '"'; ?> style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
        <?php if (!empty($existingImages)): ?>
          <?php foreach($existingImages as $ei): ?>
            <div class="existing-thumb" data-image-id="<?php echo $ei['id']; ?>" style="border:1px solid #ddd;padding:6px;border-radius:6px;position:relative;display:flex;flex-direction:column;gap:6px;align-items:center;">
              <img src="<?php echo htmlspecialchars($ei['url']); ?>" style="width:80px;height:80px;object-fit:cover;border-radius:4px" alt="">
              <div style="display:flex;gap:6px;margin-top:6px">
                <button type="button" class="btn small thumb-remove" style="background:#c00;border-color:#a00">Remove</button>
              </div>
              <input type="hidden" name="existingOrder[]" value="<?php echo $ei['id']; ?>">
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <p style="margin-top:6px;color:#666;font-size:0.9em">Tip: drag thumbnails to reorder. First image becomes primary.</p>
    </fieldset>
    <div style="margin-top:8px">
      <?php if ($editingItemId): ?>
        <button class="btn">Save changes</button>
      <?php else: ?>
        <button class="btn">Add item</button>
      <?php endif; ?>
      <a class="btn" href="/shop/<?php echo $shop['id']; ?>">Cancel</a>
    </div>
  </form>

</section>
<?php require __DIR__ . '/partials/footer.php';
