<?php
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=clicks.csv');

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$source = trim($_GET['source'] ?? '');
$category = trim($_GET['category'] ?? '');
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$product_q = trim($_GET['product_q'] ?? '');
$params = [];
$where = [];
if ($start !== '') { $where[] = 'c.created_at >= ?'; $params[] = $start . ' 00:00:00'; }
if ($end !== '') { $where[] = 'c.created_at <= ?'; $params[] = $end . ' 23:59:59'; }
if ($source !== '') { $where[] = 'c.source = ?'; $params[] = $source; }
if ($category !== '') { $where[] = 'p.category = ?'; $params[] = $category; }
if ($product_id > 0) { $where[] = 'c.product_id = ?'; $params[] = $product_id; }
elseif ($product_q !== '') { $where[] = 'p.name LIKE ?'; $params[] = '%' . $product_q . '%'; }
$whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

$out = fopen('php://output', 'w');
fputcsv($out, ['id','product_id','product_name','source','ip','user_agent','referer','utm_source','utm_medium','utm_campaign','landing_path','created_at']);
$sql = 'SELECT c.id, c.product_id, p.name as product_name, c.source, c.ip, c.user_agent, c.referer, c.utm_source, c.utm_medium, c.utm_campaign, c.landing_path, c.created_at
        FROM clicks c JOIN products p ON p.id=c.product_id' . $whereSql . ' ORDER BY c.id';
$stmt = $db->prepare($sql);
if (!empty($params)) { $types = str_repeat('s', count($params)); $stmt->bind_param($types, ...$params); }
$stmt->execute();
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
                $row['product_id'],
                $row['product_name'],
                $row['source'],
                $row['ip'],
                $row['user_agent'],
                $row['referer'],
                $row['utm_source'],
                $row['utm_medium'],
                $row['utm_campaign'],
                $row['landing_path'],
                $row['created_at'],
            ];
            fputcsv($out, $vals);
        }
    }
    $stmt->close();
}
fclose($out);
exit;