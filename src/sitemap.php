<?php
header('Content-Type: application/xml; charset=utf-8');
$pdo = getPDO();
$urls = [];
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
$urls[] = ['loc' => $base . '/home', 'priority' => '0.8'];
$urls[] = ['loc' => $base . '/products', 'priority' => '0.7'];

foreach ($pdo->query('SELECT id FROM shops') as $r) {
    $urls[] = ['loc' => $base . '/shop/' . $r['id'], 'priority' => '0.6'];
}
foreach ($pdo->query('SELECT id FROM items') as $r) {
    $urls[] = ['loc' => $base . '/item/' . $r['id'], 'priority' => '0.6'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>\n';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
foreach ($urls as $u) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($u['loc']) . "</loc>\n";
    echo "    <priority>" . htmlspecialchars($u['priority']) . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
