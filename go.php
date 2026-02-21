<?php
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$src = isset($_GET['src']) ? substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['src']), 0, 20) : '';

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$product = fetch_product($db, $id);
if (!$product) {
    header('Location: index.php');
    exit;
}

$aff = trim((string)$product['affiliate_url']);
if ($aff === '') {
    header('Location: product.php?id=' . $id);
    exit;
}

record_click($db, $id, $src);
try {
    $chk = check_affiliate_availability($aff);
    if (!$chk['ok']) {
        record_product_alert($db, $id, 'affiliate_unavailable', (string)$chk['reason']);
    }
} catch (Throwable $e) {}
header('Location: ' . $aff);
exit;
