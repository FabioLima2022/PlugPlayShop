<?php
require __DIR__ . '/config.php';
$db = get_db();
$res = $db->query('SHOW COLUMNS FROM products');
if (!$res) { echo 'ERR: ' . $db->error; exit(1); }
while ($row = $res->fetch_assoc()) { echo $row['Field'] . ':' . $row['Type'] . PHP_EOL; }
$res->close();