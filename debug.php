<?php
// Debug para PlugPlay Shop
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug PlugPlay Shop</h1>";
echo "<h2>Configurações do Servidor</h2>";
echo "<pre>";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'off') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'não definido') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'não definido') . "\n";
echo "PHP_VERSION: " . PHP_VERSION . "\n";
echo "</pre>";

echo "<h2>Variáveis de Ambiente</h2>";
echo "<pre>";
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(ltrim($line), '#') === 0) continue;
        echo htmlspecialchars($line) . "\n";
    }
} else {
    echo "Arquivo .env não encontrado\n";
}
echo "</pre>";

echo "<h2>Teste de Conexão MySQL</h2>";
try {
    require_once __DIR__ . '/config.php';
    $db = get_db();
    echo "Conexão MySQL: OK\n";
    
    $res = $db->query("SELECT COUNT(*) as total FROM products");
    if ($res) {
        $row = $res->fetch_assoc();
        echo "Total de produtos: " . $row['total'] . "\n";
    }
} catch (Exception $e) {
    echo "Erro MySQL: " . $e->getMessage() . "\n";
}

echo "<h2>Links Gerados</h2>";
echo "<pre>";
// Testa as funções SEO diretamente
if (file_exists(__DIR__ . '/seo.php')) {
    require_once __DIR__ . '/config.php'; // Carrega load_env primeiro
    require_once __DIR__ . '/seo.php';
    echo "Site URL: " . (function_exists('seo_site_url') ? seo_site_url() : 'função seo_site_url não existe') . "\n";
    echo "Canonical: " . (function_exists('seo_canonical') ? seo_canonical() : 'função seo_canonical não existe') . "\n";
} else {
    echo "Arquivo seo.php não encontrado\n";
}
echo "</pre>";

echo "<h2>Status de arquivos</h2>";
echo "<pre>";
$cf = __DIR__ . '/classifieds.php';
echo 'classifieds.php existe: ' . (file_exists($cf) ? 'sim' : 'não') . "\n";
echo 'classifieds.php legível: ' . (is_readable($cf) ? 'sim' : 'não') . "\n";
echo 'classifieds.php tamanho: ' . (file_exists($cf) ? filesize($cf) : 0) . " bytes\n";
echo 'classifieds.php modificado: ' . (file_exists($cf) ? date('c', filemtime($cf)) : '-') . "\n";
echo 'classifieds.php md5: ' . (file_exists($cf) ? md5_file($cf) : '-') . "\n";
echo "</pre>";

echo "<p><a href='index.php'>Voltar para home</a></p>";
