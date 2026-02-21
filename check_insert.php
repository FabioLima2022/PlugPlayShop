<?php
require __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);
$ok = insert_product($db, [
    'name' => 'Teste Inserção',
    'description' => 'Desc',
    'price' => 123.45,
    'currency' => 'BRL',
    'category' => 'Teste',
    'subcategory' => 'Sub',
    'image_urls' => 'https://example.com/img.jpg',
    'affiliate_url' => 'https://example.com/aff'
]);
echo $ok ? "OK\n" : "FAIL\n";