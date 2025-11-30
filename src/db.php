<?php

function getPDO()
{
    static $pdo = null;
    if ($pdo) return $pdo;

    
    
    
    $mysqlHost = getenv('DB_HOST') ?: null;
    $mysqlDb = getenv('DB_NAME') ?: null;
    $mysqlUser = getenv('DB_USER') ?: 'T1WIN';
    $mysqlPass = getenv('DB_PASS') ?: 'leesanghyeok';

    try {
        if ($mysqlHost && $mysqlDb && $mysqlUser !== null) {
            $dsn = "mysql:host={$mysqlHost};dbname={$mysqlDb};charset=utf8mb4";
            $pdo = new PDO($dsn, $mysqlUser, $mysqlPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            return $pdo;
        }
    } catch (Exception $e) {
        
    }

    
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
    $dbFile = $dataDir . '/cosplay.sqlite';
    $first = !file_exists($dbFile);
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($first) {
        
        $sql = <<<'SQL'
        PRAGMA foreign_keys = ON;
        CREATE TABLE IF NOT EXISTS users (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          username TEXT NOT NULL UNIQUE,
          email TEXT,
          passwordHash TEXT,
          createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
          updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS categories (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name TEXT NOT NULL UNIQUE,
          slug TEXT NOT NULL UNIQUE,
          createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS item_categories (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          itemId INTEGER NOT NULL,
          categoryId INTEGER NOT NULL,
          FOREIGN KEY(itemId) REFERENCES items(id) ON DELETE CASCADE,
          FOREIGN KEY(categoryId) REFERENCES categories(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS shops (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          ownerUserId INTEGER NOT NULL,
          name TEXT NOT NULL,
          address TEXT,
          phone TEXT,
          description TEXT,
          externalUrl TEXT,
          createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY(ownerUserId) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS shop_members (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          shopId INTEGER NOT NULL,
          userId INTEGER NOT NULL,
          role TEXT DEFAULT 'cooperator',
          createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
          UNIQUE(shopId,userId),
          FOREIGN KEY(shopId) REFERENCES shops(id) ON DELETE CASCADE,
          FOREIGN KEY(userId) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS items (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          shopId INTEGER NOT NULL,
          name TEXT NOT NULL,
          series TEXT,
          brand TEXT,
          size TEXT,
          priceTest INTEGER,
          priceShoot INTEGER,
          priceFestival INTEGER,
          sourceLink TEXT,
          description TEXT,
          createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY(shopId) REFERENCES shops(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS item_images (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          itemId INTEGER NOT NULL,
          url TEXT NOT NULL,
          isPrimary INTEGER DEFAULT 0,
          FOREIGN KEY(itemId) REFERENCES items(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS item_availability (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          itemId INTEGER NOT NULL,
          shopId INTEGER NOT NULL,
          available INTEGER DEFAULT 1,
          note TEXT,
          createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY(itemId) REFERENCES items(id) ON DELETE CASCADE,
          FOREIGN KEY(shopId) REFERENCES shops(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS feedbacks (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          itemId INTEGER NOT NULL,
          userId INTEGER,
          rating INTEGER,
          message TEXT,
          createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY(itemId) REFERENCES items(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS feedback_images (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          feedbackId INTEGER NOT NULL,
          url TEXT NOT NULL,
          FOREIGN KEY(feedbackId) REFERENCES feedbacks(id) ON DELETE CASCADE
        );
        SQL;
        $pdo->exec($sql);

        
        
        $doSeed = getenv('APP_SEED') === '1';
        if ($doSeed) {
          $pdo->exec("INSERT INTO users (username,email,passwordHash) VALUES ('alice','alice@example.com','')");
          $pdo->exec("INSERT INTO shops (ownerUserId,name,address,phone,description) VALUES (1,'Alice Cosplay Shop','123 Anime St','+84123456789','A small cosplay rental and sales shop.')");
          
          $pdo->exec("UPDATE shops SET externalUrl = 'https://example.com/alice-shop' WHERE id = 1");
          $pdo->exec("INSERT INTO items (shopId,name,series,brand,size,priceTest,priceShoot,priceFestival,description) VALUES (1,'Sailor Moon Outfit','Sailor Moon','BrandA','M',100000,200000,300000,'Classic set for rent')");
          $pdo->exec("INSERT INTO items (shopId,name,series,brand,size,priceTest,priceShoot,priceFestival,description) VALUES (1,'Naruto Wig','Naruto','BrandWigs','One-size',20000,30000,40000,'High-quality synthetic wig')");
          
          $pdo->exec("INSERT OR IGNORE INTO categories (name,slug) VALUES ('Anime','anime'), ('Wigs','wigs')");
          $cat1 = $pdo->query("SELECT id FROM categories WHERE slug='anime'")->fetchColumn();
          $cat2 = $pdo->query("SELECT id FROM categories WHERE slug='wigs'")->fetchColumn();
          $pdo->exec("INSERT OR IGNORE INTO item_categories (itemId,categoryId) VALUES (1,".intval($cat1)."),(2,".intval($cat2).")");
          
          $pdo->exec("INSERT OR IGNORE INTO item_availability (itemId,shopId,available,note) VALUES (1,1,1,'In stock'),(2,1,1,'Limited stock')");
        }
          
          try {
            $exists = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
            $exists->execute([':u' => 'T1WIN']);
            $ownerId = $exists->fetchColumn();
            if (!$ownerId) {
              $stmt = $pdo->prepare("INSERT INTO users (username,email,passwordHash,accountType) VALUES (:u,:e,:p,:t)");
              $stmt->execute([':u'=>'T1WIN',':e'=>'owner@example.com',':p'=>'',':t'=>'shop']);
              $ownerId = $pdo->lastInsertId();
            }
            
            $s = $pdo->prepare("SELECT id FROM shops WHERE ownerUserId = :oid LIMIT 1");
            $s->execute([':oid'=>$ownerId]);
            $shopId = $s->fetchColumn();
            if (!$shopId) {
              $ins = $pdo->prepare("INSERT INTO shops (ownerUserId,name,address,phone,description,externalUrl) VALUES (:oid,:n,:a,:p,:d,:url)");
              $ins->execute([':oid'=>$ownerId,':n'=>'Site Owner Shop',':a'=>'Owner Address',':p'=>'',':d'=>'Default shop for site owner',':url'=>NULL]);
            }
          } catch (Exception $e) {
            
          }
    }

      
      
      $pdo->exec("CREATE TABLE IF NOT EXISTS item_availability (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          itemId INTEGER NOT NULL,
          shopId INTEGER NOT NULL,
          available INTEGER DEFAULT 1,
          note TEXT,
          createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY(itemId) REFERENCES items(id) ON DELETE CASCADE,
          FOREIGN KEY(shopId) REFERENCES shops(id) ON DELETE CASCADE
        );");

      
      try {
        $cols = $pdo->query("PRAGMA table_info(shops)")->fetchAll(PDO::FETCH_ASSOC);
        $hasExt = false;
        foreach ($cols as $c) { if ($c['name'] === 'externalUrl') { $hasExt = true; break; } }
        if (!$hasExt) {
          $pdo->exec("ALTER TABLE shops ADD COLUMN externalUrl TEXT;");
        }
      } catch (Exception $e) {
        
      }

    return $pdo;
}
