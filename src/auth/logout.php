<?php
if (session_status() === PHP_SESSION_NONE) session_start();

try {
	require_once __DIR__ . '/../db.php';
	require_once __DIR__ . '/../helpers.php';
	$pdo = getPDO();
	if (!empty($_SESSION['user_id'])) {
		$sid = session_id();
		try { $del = $pdo->prepare('DELETE FROM user_sessions WHERE sessionId = :sid AND userId = :uid'); $del->execute([':sid'=>$sid, ':uid'=>intval($_SESSION['user_id'])]); } catch (Exception $e) {}
	}
} catch (Exception $e) { /* ignore */ }

session_unset();
session_destroy();
header('Location: /home');
exit;
