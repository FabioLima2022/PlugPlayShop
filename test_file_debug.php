<?php
// Teste que escreve em arquivo para debug
file_put_contents(__DIR__ . '/debug.log', "Iniciando teste em: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

try {
    file_put_contents(__DIR__ . '/debug.log', "Carregando config.php...\n", FILE_APPEND);
    require_once __DIR__ . '/config.php';
    file_put_contents(__DIR__ . '/debug.log', "config.php carregado\n", FILE_APPEND);
    
    file_put_contents(__DIR__ . '/debug.log', "Tentando conectar ao banco...\n", FILE_APPEND);
    $db = get_db();
    file_put_contents(__DIR__ . '/debug.log', "Conexão estabelecida\n", FILE_APPEND);
    
    // Testar a função insert_product
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
    
    file_put_contents(__DIR__ . '/debug.log', "Tentando inserir produto...\n", FILE_APPEND);
    $result = insert_product($db, $test_data);
    
    if ($result) {
        file_put_contents(__DIR__ . '/debug.log', "✅ Produto inserido com sucesso! ID: " . $db->insert_id . "\n", FILE_APPEND);
        // Limpar
        $db->query("DELETE FROM products WHERE name = 'Produto Teste Debug'");
        file_put_contents(__DIR__ . '/debug.log', "Produto de teste removido\n", FILE_APPEND);
    } else {
        file_put_contents(__DIR__ . '/debug.log', "❌ Falha ao inserir produto\n", FILE_APPEND);
    }
    
    file_put_contents(__DIR__ . '/debug.log', "Teste concluído com sucesso!\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/debug.log', "❌ Erro: " . $e->getMessage() . "\n", FILE_APPEND);
}

echo "Teste concluído. Verifique o arquivo debug.log";