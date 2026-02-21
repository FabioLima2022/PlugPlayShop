<?php
// Test script para verificar conexão e estrutura do banco
echo "<h1>Teste de Conexão e Estrutura do Banco</h1>";

require_once __DIR__ . '/config.php';

try {
    $db = get_db();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Verificar estrutura da tabela
    $result = $db->query("DESCRIBE products");
    if ($result) {
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
    } else {
        echo "<p>❌ Erro ao descrever tabela: " . $db->error . "</p>";
    }
    
    // Testar inserção simples
    echo "<h2>Teste de Inserção:</h2>";
    $stmt = $db->prepare("INSERT INTO products (name, description, price, currency, category, subcategory, image_urls, affiliate_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $name = "Produto Teste";
        $desc = "Descrição teste";
        $price = 99.99;
        $currency = "USD";
        $category = "Teste";
        $subcategory = "Subteste";
        $images = "";
        $affiliate = "";
        
        $stmt->bind_param('ssdsssss', $name, $desc, $price, $currency, $category, $subcategory, $images, $affiliate);
        $ok = $stmt->execute();
        
        if ($ok) {
            echo "<p>✅ Inserção bem-sucedida! ID: " . $db->insert_id . "</p>";
            // Deletar o registro de teste
            $db->query("DELETE FROM products WHERE name = 'Produto Teste'");
        } else {
            echo "<p>❌ Erro na inserção: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p>❌ Erro ao preparar statement: " . $db->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro geral: " . $e->getMessage() . "</p>";
}