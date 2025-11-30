  </main>
  <footer class="site-footer">
    <div class="container">
      <p>&copy; <?php echo date('Y'); ?> CosplaySites — A student project prototype.</p>
    </div>
  </footer>
  <script src="/assets/js/item-images.js"></script>
  <script src="/assets/js/main.js"></script>
    <?php
    
    
    
    try {
      $mysqlHost = getenv('DB_HOST');
      $mysqlDb = getenv('DB_NAME');
      if ($mysqlHost && $mysqlDb) {
        $mysqlUser = getenv('DB_USER') ?: 'T1WIN';
        $mysqlPass = getenv('DB_PASS') ?: 'leesanghyeok';
        $dsn = "mysql:host={$mysqlHost};dbname={$mysqlDb};charset=utf8mb4";
        
        try {
          $chk = new PDO($dsn, $mysqlUser, $mysqlPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
          
          $chk->query('SELECT 1');
          
        } catch (Exception $e) {
          
          error_log('MySQL connectivity check failed: ' . $e->getMessage());
          echo "<div style='position:fixed;right:12px;bottom:12px;z-index:9999;max-width:420px;padding:10px;background:#ffecec;border:1px solid #f5c6cb;border-radius:6px;color:#a94442;font-size:0.95rem;box-shadow:0 2px 6px rgba(0,0,0,0.08)'>";
          echo "<strong>Database connection warning:</strong> failed to connect to MySQL. The app may be using the local SQLite fallback. Check server environment variables (DB_HOST/DB_NAME/DB_USER/DB_PASS) or review error logs.";
          echo "</div>";
        }
      }
    } catch (Exception $ignore) {
      
    }
    ?>
</body>
</html>
