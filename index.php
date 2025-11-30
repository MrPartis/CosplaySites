<?php
require_once __DIR__ . '/src/db.php';

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';

$rawUri = str_replace('\\', '/', $rawUri);
$uri = parse_url($rawUri, PHP_URL_PATH);
$uri = rtrim($uri, '/');

if ($uri === '' || $uri === '\\') {
    header('Location: /home');
    exit;
}


$parts = explode('/', ltrim($uri, '/'));
switch ($parts[0]) {
    case 'home':
        require __DIR__ . '/src/home.php';
        break;
    case 'shop':
        $id = isset($parts[1]) ? intval($parts[1]) : 0;
        $action = isset($parts[2]) ? $parts[2] : null;
        require __DIR__ . '/src/shop.php';
        break;
    case 'item':
        $id = isset($parts[1]) ? intval($parts[1]) : 0;
        require __DIR__ . '/src/item.php';
        break;
    case 'products':
        require __DIR__ . '/src/products.php';
        break;
    case 'auth':
        $sub = isset($parts[1]) ? $parts[1] : 'login';
        if ($sub) {
            require __DIR__ . '/src/auth/' . $sub . '.php';
        } else {
            require __DIR__ . '/src/auth/login.php';
        }
        break;
    case 'api':
        
        $subpath = implode('/', array_slice($parts, 1));
        if ($subpath) {
            $file = __DIR__ . '/src/api/' . $subpath;
            if (is_file($file)) {
                require $file;
            } else {
                http_response_code(404);
                echo 'API endpoint not found';
            }
        } else {
            http_response_code(404);
            echo 'API endpoint not found';
        }
        break;
    case 'owner':
        
        $sub = isset($parts[1]) ? $parts[1] : 'dashboard';
        $file = __DIR__ . '/src/owner/' . $sub . '.php';
        if (is_file($file)) require $file; else { http_response_code(404); echo 'Owner page not found'; }
        break;
    case 'admin':
        
        $sub = isset($parts[1]) ? $parts[1] : 'index';
        $file = __DIR__ . '/src/admin/' . $sub . '.php';
        if (is_file($file)) require $file; else { http_response_code(404); echo 'Admin page not found'; }
        break;
    case 'sitemap.xml':
        require __DIR__ . '/src/sitemap.php';
        break;
    default:
        http_response_code(404);
        echo "<h1>404 Not Found</h1>";
}
