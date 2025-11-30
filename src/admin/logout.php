<?php
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['is_admin_db'], $_SESSION['admin_db_user'], $_SESSION['admin_db_pass']);
session_regenerate_id(true);
header('Location: /admin/login');
exit;
