<?php
// Teste simples para verificar se PHP está funcionando
echo "<h1>Teste PHP Simples</h1>";
echo "<p>PHP está funcionando!</p>";
echo "<p>Versão do PHP: " . phpversion() . "</p>";

// Testar erro reporting
echo "<h2>Teste de Erro</h2>";
echo "<p>Isso deve gerar um warning:</p>";
echo $variavel_inexistente; // Isso deve gerar um warning

// Testar conexão com banco
echo "<h2>Teste de Conexão</h2>";
try {
    require_once __DIR__ . '/config.php';
    $db = get_db();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Testar uma query simples
    $result = $db->query("SELECT COUNT(*) as total FROM products");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Total de produtos: " . $row['total'] . "</p>";
    } else {
        echo "<p>❌ Erro na query: " . $db->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
}