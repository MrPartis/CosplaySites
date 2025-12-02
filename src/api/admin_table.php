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
        http_response_code(500);
        echo json_encode(['error' => 'Unable to connect to DB']);
        exit;
    }
}

$pdo = getAdminPDO();
if (!$pdo) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$table = isset($_GET['table']) ? trim($_GET['table']) : '';
if ($table === '') {
    // return list of tables
    $stmt = $pdo->query('SHOW TABLES');
    $tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    echo json_encode(['tables' => $tables]);
    exit;
}

// Validate table exists
$sth = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
$sth->execute([':t' => $table]);
if ($sth->fetchColumn() == 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown table']);
    exit;
}

// get columns
$colStmt = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t ORDER BY ORDINAL_POSITION');
$colStmt->execute([':t' => $table]);
$columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_map(function($c){return $c['COLUMN_NAME'];}, $columns);
$pkCols = array_filter($columns, function($c){return ($c['COLUMN_KEY']==='PRI');});
$pk = count($pkCols) ? array_values($pkCols)[0]['COLUMN_NAME'] : (in_array('id', $colNames) ? 'id' : $colNames[0]);

// if id param provided, return single row
if (isset($_GET['id']) && $_GET['id'] !== '') {
    $id = $_GET['id'];
    $q = "SELECT * FROM `" . str_replace('`','``',$table) . "` WHERE `".str_replace('`','``',$pk)."` = :id LIMIT 1";
    $s = $pdo->prepare($q);
    $s->execute([':id' => $id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['columns' => $columns, 'row' => $row]);
    exit;
}

// pagination + search + sort
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = max(1, min(500, intval($_GET['per_page'] ?? 50)));
$offset = ($page - 1) * $per_page;
$sort = $_GET['sort'] ?? $pk;
$dir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$search = trim($_GET['q'] ?? '');

if (!in_array($sort, $colNames)) $sort = $pk;

$where = '1=1';
$params = [];
if ($search !== '') {
    // apply search to all text-like columns
    $likeCols = array_filter($columns, function($c){
        $t = strtolower($c['DATA_TYPE']);
        return in_array($t, ['varchar','char','text','tinytext','mediumtext','longtext','enum','set']);
    });
    if (count($likeCols)) {
        $likes = [];
        foreach ($likeCols as $i => $c) {
            $k = ':s' . $i;
            $likes[] = "`".str_replace('`','``',$c['COLUMN_NAME'])."` LIKE " . $k;
            $params[$k] = '%'.$search.'%';
        }
        $where = '(' . implode(' OR ', $likes) . ')';
    } else {
        // fall back to search in all columns cast to char
        $likes = [];
        foreach ($colNames as $i => $cn) {
            $k = ':s' . $i;
            $likes[] = "CAST(`".str_replace('`','``',$cn)."` AS CHAR) LIKE " . $k;
            $params[$k] = '%'.$search.'%';
        }
        $where = '(' . implode(' OR ', $likes) . ')';
    }
}

$countQ = "SELECT COUNT(*) FROM `".str_replace('`','``',$table).'` WHERE '.$where;
$countStmt = $pdo->prepare($countQ);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$dataQ = "SELECT * FROM `".str_replace('`','``',$table).'` WHERE '.$where." ORDER BY `".str_replace('`','``',$sort).'` '.$dir." LIMIT :lim OFFSET :off";
$dataStmt = $pdo->prepare($dataQ);
foreach ($params as $k=>$v) $dataStmt->bindValue($k, $v);
$dataStmt->bindValue(':lim', (int)$per_page, PDO::PARAM_INT);
$dataStmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$dataStmt->execute();
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'columns' => $columns,
    'rows' => $rows,
    'total' => $total,
    'page' => $page,
    'per_page' => $per_page,
    'sort' => $sort,
    'dir' => $dir,
]);

