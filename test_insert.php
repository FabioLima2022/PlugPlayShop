<?php
// Habilitar exibição de todos os erros para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Teste de Inserção de Produto</h1>";

require_once __DIR__ . '/config.php';

try {
    $db = get_db();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Testar a função insert_product diretamente
    echo "<h2>Testando insert_product()...</h2>";
    
    $test_data = [
        'name' => 'Produto Teste Debug',
        'description' => 'Descrição de teste',
        'price' => 99.99,
        'currency' => 'USD',
        'category' => 'Teste',
        'subcategory' => 'Subteste',
        'image_urls' => '',
        'affiliate_url' => ''
    ];
    
    echo "<p>Dados do teste:</p>";
    echo "<pre>" . htmlspecialchars(print_r($test_data, true)) . "</pre>";
    
    $result = insert_product($db, $test_data);
    
    if ($result) {
        echo "<p>✅ Produto inserido com sucesso! ID: " . $db->insert_id . "</p>";
        // Limpar o produto de teste
        $db->query("DELETE FROM products WHERE name = 'Produto Teste Debug'");
    } else {
        echo "<p>❌ Falha ao inserir produto</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro geral: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Fim do teste</h2>";