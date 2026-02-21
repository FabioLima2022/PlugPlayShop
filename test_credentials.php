<?php
// Testar se as novas credenciais estão sendo usadas
require_once __DIR__ . '/config.php';

echo "<h1>Teste de Credenciais</h1>";

$env = load_env();
echo "<p>DB_USER: " . htmlspecialchars($env['DB_USER'] ?? 'não definido') . "</p>";
echo "<p>DB_NAME: " . htmlspecialchars($env['DB_NAME'] ?? 'não definido') . "</p>";

try {
    $db = get_db();
    echo "<p>✅ Conexão bem-sucedida!</p>";
    
    // Testar se a tabela existe
    $result = $db->query("SHOW TABLES LIKE 'products'");
    if ($result && $result->num_rows > 0) {
        echo "<p>✅ Tabela 'products' existe</p>";
        
        // Verificar estrutura
        $result = $db->query("SHOW COLUMNS FROM products LIKE 'subcategory'");
        if ($result && $result->num_rows > 0) {
            echo "<p>✅ Coluna 'subcategory' existe</p>";
        } else {
            echo "<p>❌ Coluna 'subcategory' NÃO existe</p>";
        }
    } else {
        echo "<p>❌ Tabela 'products' NÃO existe</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}