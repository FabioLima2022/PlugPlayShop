<?php
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products.csv');

// Filtros alinhados ao admin: q (busca), categories[], min_price, max_price
$q = trim($_GET['q'] ?? '');
$selCats = isset($_GET['categories']) ? (array)$_GET['categories'] : [];
$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$curSel = isset($_GET['currency']) ? strtoupper(substr((string)$_GET['currency'], 0, 3)) : '';
if (!in_array($curSel, ['USD','BRL','EUR'], true)) { $curSel = ''; }

$conds = [];
$params = [];
$types = '';

if ($q !== '') {
    $like = '%' . $q . '%';
    $conds[] = '(p.name LIKE ? OR p.category LIKE ?)';
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
if (!empty($selCats)) {
    // placeholders dinâmicos para IN
    $ph = implode(',', array_fill(0, count($selCats), '?'));
    $conds[] = 'p.category IN (' . $ph . ')';
    foreach ($selCats as $c) { $params[] = $c; $types .= 's'; }
}
if ($minPrice !== null && $minPrice >= 0) { $conds[] = 'p.price >= ?'; $params[] = (float)$minPrice; $types .= 'd'; }
if ($maxPrice !== null && $maxPrice >= 0) { $conds[] = 'p.price <= ?'; $params[] = (float)$maxPrice; $types .= 'd'; }
if ($curSel !== '') { $conds[] = 'p.currency = ?'; $params[] = $curSel; $types .= 's'; }

$whereSql = '';
if (!empty($conds)) { $whereSql = ' WHERE ' . implode(' AND ', $conds); }

$sql = 'SELECT p.id,p.name,p.description,p.price,p.currency,p.category,p.image_urls,p.affiliate_url,p.created_at FROM products p' . $whereSql . ' ORDER BY p.id';
$stmt = $db->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$out = fopen('php://output', 'w');
fputcsv($out, ['id','name','description','price','currency','category','image_urls','affiliate_url','created_at']);
if (method_exists($stmt, 'get_result')) {
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) { fputcsv($out, $row); }
    }
    $stmt->close();
} else {
    $meta = $stmt->result_metadata();
    if ($meta) {
        $row = [];
        $binds = [];
        $fields = [];
        while ($f = $meta->fetch_field()) { $row[$f->name] = null; $binds[] =& $row[$f->name]; $fields[] = $f->name; }
        call_user_func_array([$stmt, 'bind_result'], $binds);
        while ($stmt->fetch()) {
            $vals = [
                $row['id'],
                $row['name'],
                $row['description'],
                $row['price'],
                $row['currency'],
                $row['category'],
                $row['image_urls'],
                $row['affiliate_url'],
                $row['created_at'],
            ];
            fputcsv($out, $vals);
        }
    }
    $stmt->close();
}
fclose($out);
exit;