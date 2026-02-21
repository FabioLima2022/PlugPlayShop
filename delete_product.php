<?php
session_start();
require_once __DIR__ . '/config.php';
$db = get_db();
ensure_schema($db);

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin.php#produtos'); exit; }

if (!check_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['flash'] = ['msg'=>'Sessão expirada ou inválida.', 'type'=>'error'];
    $redir = isset($_POST['redirect']) ? (string)$_POST['redirect'] : 'admin.php#produtos';
    header('Location: ' . $redir);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { $_SESSION['flash'] = ['msg'=>'ID inválido.', 'type'=>'error']; header('Location: admin.php#produtos'); exit; }

$ok = false;
// Checagem de ownership para não-admin
$role = $_SESSION['role'] ?? 'admin';
if ($role !== 'admin') {
    $stmt = $db->prepare('SELECT user_id FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $owner = null;
    if (method_exists($stmt, 'get_result')) { $res = $stmt->get_result(); $owner = $res ? $res->fetch_assoc() : null; if ($res) $res->close(); }
    $stmt->close();
    if (!$owner || (int)$owner['user_id'] !== (int)$_SESSION['user_id']) {
        $_SESSION['flash'] = ['msg'=>'Operação não permitida.', 'type'=>'error'];
        $redir = isset($_POST['redirect']) ? (string)$_POST['redirect'] : 'admin.php#produtos';
        header('Location: ' . $redir);
        exit;
    }
}
$ok = delete_product($db, $id);
$_SESSION['flash'] = $ok ? ['msg'=>'Produto excluído.', 'type'=>'success'] : ['msg'=>'Falha ao excluir produto.', 'type'=>'error'];
$redir = isset($_POST['redirect']) ? (string)$_POST['redirect'] : 'admin.php#produtos';
header('Location: ' . $redir);
exit;