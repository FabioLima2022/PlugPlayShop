<?php
require_once __DIR__ . '/config.php';
$db = get_db_soft();
if (!$db) { die("DB Connection Error"); }

echo "<h1>Database Inspection</h1>";

// 1. Check specific search terms the user mentioned
$terms = ['Relógio', 'Relógios', 'Smart Watch', 'Smartwatch', 'Eletrônicos'];

echo "<h2>Exact Match Check</h2>";
foreach ($terms as $term) {
    $safe = $db->real_escape_string($term);
    $res = $db->query("SELECT id, name, category, subcategory FROM products WHERE category = '$safe' OR subcategory = '$safe'");
    echo "<p>Searching for exact <b>'$term'</b>: " . ($res ? $res->num_rows : 0) . " results</p>";
    if ($res && $res->num_rows > 0) {
        echo "<ul>";
        while ($row = $res->fetch_assoc()) {
            echo "<li>[#{$row['id']}] {$row['name']} (Cat: '{$row['category']}', Sub: '{$row['subcategory']}')</li>";
        }
        echo "</ul>";
    }
}

// 2. Check LIKE match
echo "<h2>LIKE Match Check</h2>";
foreach ($terms as $term) {
    $safe = $db->real_escape_string($term);
    $res = $db->query("SELECT id, name, category, subcategory FROM products WHERE category LIKE '%$safe%' OR subcategory LIKE '%$safe%'");
    echo "<p>Searching for LIKE <b>'%$term%'</b>: " . ($res ? $res->num_rows : 0) . " results</p>";
}

// 3. Dump all Categories
echo "<h2>All Categories (Grouped)</h2>";
$res = $db->query("SELECT category, COUNT(*) as c, LENGTH(category) as len, HEX(category) as hex FROM products GROUP BY category");
echo "<table border=1><tr><th>Category</th><th>Count</th><th>Length</th><th>Hex</th></tr>";
while ($row = $res->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($row['category']) . "</td><td>{$row['c']}</td><td>{$row['len']}</td><td>{$row['hex']}</td></tr>";
}
echo "</table>";

// 4. Dump all Subcategories
echo "<h2>All Subcategories (Grouped)</h2>";
$res = $db->query("SELECT subcategory, COUNT(*) as c, LENGTH(subcategory) as len, HEX(subcategory) as hex FROM products GROUP BY subcategory");
echo "<table border=1><tr><th>Subcategory</th><th>Count</th><th>Length</th><th>Hex</th></tr>";
while ($row = $res->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($row['subcategory']) . "</td><td>{$row['c']}</td><td>{$row['len']}</td><td>{$row['hex']}</td></tr>";
}
echo "</table>";
?>
