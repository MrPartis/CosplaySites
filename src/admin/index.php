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
        return null;
    }
}

$adminPdo = getAdminPDO();
if (!$adminPdo) {
    header('Location: /admin/login');
    exit;
}

$metaTitle = 'Admin — CosplaySites';
require __DIR__ . '/../partials/header.php';
?>
<section>
  <h2>Admin Database Explorer</h2>
  <p><a href="/admin/logout">Logout</a></p>

  <div id="db-explorer">
    <div style="margin-bottom:12px;">
      <label for="table-select">Table:</label>
      <select id="table-select"></select>
      <label for="search">Search:</label>
      <input id="search" placeholder="free text search" />
      <label for="per-page">Per page:</label>
      <select id="per-page"><option>10</option><option>25</option><option selected>50</option><option>100</option></select>
      <button id="refresh">Refresh</button>
    </div>

    <div id="table-controls" style="margin-bottom:8px;display:none;">
      <span id="table-meta"></span>
    </div>

    <div id="table-container">Loading...</div>
  </div>

  <!-- Row detail modal -->
  <div id="row-modal" style="display:none;position:fixed;left:10%;top:10%;width:80%;height:80%;background:#fff;border:1px solid #ccc;padding:12px;overflow:auto;z-index:9999;">
    <button id="close-modal" style="float:right;">Close</button>
    <h3>Row Details</h3>
    <pre id="row-json" style="white-space:pre-wrap;background:#f6f6f6;padding:8px;border:1px solid #ddd;"></pre>
  </div>

</section>

<script src="/assets/js/admin-dashboard.js"></script>

<?php require __DIR__ . '/../partials/footer.php';
