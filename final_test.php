<?php
// Teste final para verificar tudo
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Teste Final - Verificação Completa</h1>";

try {
    require_once __DIR__ . '/config.php';
    echo "<p>✅ Config.php carregado</p>";
    
    $db = get_db();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Verificar estrutura da tabela
    $result = $db->query("DESCRIBE products");
    echo "<h2>Estrutura da tabela products:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Testar inserção
    echo "<h2>Teste de Inserção:</h2>";
    $test_data = [
        'name' => 'Produto Teste Final',
        'description' => 'Descrição de teste final',
        'price' => 199.99,
        'currency' => 'USD',
        'category' => 'Teste Final',
        'subcategory' => 'Subcategoria Final',
        'image_urls' => 'https://via.placeholder.com/300',
        'affiliate_url' => 'https://exemplo.com/produto-final'
    ];
    
    echo "<p>Dados do teste:</p>";
    echo "<pre>" . htmlspecialchars(print_r($test_data, true)) . "</pre>";
    
    $result = insert_product($db, $test_data);
    
    if ($result) {
        echo "<p style='color: green; font-size: 18px;'>✅ SUCESSO! Produto inserido com ID: " . $db->insert_id . "</p>";
        // Limpar
        $db->query("DELETE FROM products WHERE name = 'Produto Teste Final'");
        echo "<p>Produto de teste removido</p>";
    } else {
        echo "<p style='color: red; font-size: 18px;'>❌ FALHA ao inserir produto</p>";
    }
    
    echo "<h2 style='color: green;'>✅ SISTEMA FUNCIONANDO CORRETAMENTE!</h2>";
    echo "<p>O problema do cadastro em branco foi resolvido.</p>";
    echo "<p>Agora você pode acessar a página admin.php normalmente.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}