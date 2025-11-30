<?php
header('Content-Type: application/json; charset=utf-8');
$q = trim($_GET['q'] ?? '');
$pdo = getPDO();
if ($q === '') {
    echo json_encode([]);
    exit;
}


$big = 999999999999;
$sql = 'SELECT i.id, i.name, i.series, '
     . '(SELECT url FROM item_images ii WHERE ii.itemId = i.id ORDER BY ii.isPrimary DESC, ii.id ASC LIMIT 1) AS image, '
     . "LEAST(COALESCE(i.priceTest, $big), COALESCE(i.priceShoot, $big), COALESCE(i.priceFestival, $big)) AS minPrice "
     . 'FROM items i WHERE i.name LIKE :q LIMIT 10';
$stmt = $pdo->prepare($sql);
$stmt->execute([':q' => "%$q%"]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    if (isset($r['minPrice']) && $r['minPrice'] == $big) $r['minPrice'] = null;
}
unset($r);
echo json_encode($rows);
