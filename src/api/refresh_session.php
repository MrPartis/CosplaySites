<?php

require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
try {
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'not_authenticated']);
        http_response_code(401);
        exit;
    }
    $uid = (int)$_SESSION['user_id'];
    $sid = session_id();
    $pdo = getPDO();
    
    require_once __DIR__ . '/../helpers.php';
    ensure_user_sessions_table($pdo);
    ensure_temp_user_sessions_table($pdo);

    
    $updated = false;
    try {
        
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Exception $e) {
        $driver = null;
    }
    if ($driver === 'mysql' || $driver === 'mysqli' || $driver === 'pdo_mysql') {
        $u1 = $pdo->prepare('UPDATE user_sessions SET createdAt = CURRENT_TIMESTAMP WHERE userId = :uid AND sessionId = :sid');
        $u1->execute([':uid' => $uid, ':sid' => $sid]);
        if ($u1->rowCount() > 0) $updated = true;
        
        $u2 = $pdo->prepare('UPDATE temp_user_sessions SET createdAt = CURRENT_TIMESTAMP WHERE userId = :uid AND sessionId = :sid');
        $u2->execute([':uid' => $uid, ':sid' => $sid]);
        if ($u2->rowCount() > 0) $updated = true;
        
        $del = $pdo->prepare('DELETE FROM temp_user_sessions WHERE createdAt < (NOW() - INTERVAL 5 MINUTE)');
        $del->execute();
    } else {
        
        $u1 = $pdo->prepare("UPDATE user_sessions SET createdAt = CURRENT_TIMESTAMP WHERE userId = :uid AND sessionId = :sid");
        $u1->execute([':uid' => $uid, ':sid' => $sid]);
        if ($u1->rowCount() > 0) $updated = true;
        $u2 = $pdo->prepare("UPDATE temp_user_sessions SET createdAt = CURRENT_TIMESTAMP WHERE userId = :uid AND sessionId = :sid");
        $u2->execute([':uid' => $uid, ':sid' => $sid]);
        if ($u2->rowCount() > 0) $updated = true;
        
        $del = $pdo->prepare("DELETE FROM temp_user_sessions WHERE datetime(createdAt) < datetime('now','-5 minutes')");
        $del->execute();
    }

    echo json_encode(['success' => true, 'touched' => $updated]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}
