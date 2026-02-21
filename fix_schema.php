<?php
// Habilitar exibição de todos os erros para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Verificação e Atualização do Schema do Banco</h1>";

require_once __DIR__ . '/config.php';

try {
    $db = get_db();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Verificar estrutura atual da tabela
    echo "<h2>Estrutura atual da tabela products:</h2>";
    $result = $db->query("DESCRIBE products");
    if ($result) {
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
    
    // Verificar se a coluna subcategory existe
    $result = $db->query("SHOW COLUMNS FROM products LIKE 'subcategory'");
    if ($result && $result->num_rows === 0) {
        echo "<p>⚠️ Coluna 'subcategory' não encontrada. Adicionando...</p>";
        
        $alter_query = "ALTER TABLE products ADD COLUMN subcategory VARCHAR(100) AFTER category";
        if ($db->query($alter_query)) {
            echo "<p>✅ Coluna 'subcategory' adicionada com sucesso!</p>";
        } else {
            echo "<p>❌ Erro ao adicionar coluna 'subcategory': " . $db->error . "</p>";
        }
    } else {
        echo "<p>✅ Coluna 'subcategory' já existe</p>";
    }
    
    // Verificar estrutura atualizada
    echo "<h2>Estrutura atualizada da tabela products:</h2>";
    $result = $db->query("DESCRIBE products");
    if ($result) {
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
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro geral: " . $e->getMessage() . "</p>";
}