<?php
// Teste de formulário similar ao admin.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
$_SESSION['user_id'] = 1; // Simular login

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Processando formulário...</h2>";
    
    try {
        $db = get_db();
        echo "<p>✅ Conexão com banco estabelecida</p>";
        
        // Simular dados do formulário
        $name = "Produto Teste Formulário";
        $description = "Descrição de teste do formulário";
        $price = 149.99;
        $currency = "USD";
        $category = "Eletrônicos";
        $subcategory = "Smartphones";
        $affiliate = "https://exemplo.com/produto";
        $image_urls = "https://via.placeholder.com/300,https://via.placeholder.com/600";
        
        echo "<p>Dados do produto:</p>";
        echo "<pre>" . htmlspecialchars(print_r([
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'currency' => $currency,
            'category' => $category,
            'subcategory' => $subcategory,
            'image_urls' => $image_urls,
            'affiliate_url' => $affiliate
        ], true)) . "</pre>";
        
        echo "<p>Tentando inserir produto...</p>";
        
        $result = insert_product($db, [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'currency' => $currency,
            'category' => $category,
            'subcategory' => $subcategory,
            'image_urls' => $image_urls,
            'affiliate_url' => $affiliate,
        ]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Produto inserido com sucesso! ID: " . $db->insert_id . "</p>";
            // Limpar
            $db->query("DELETE FROM products WHERE name = 'Produto Teste Formulário'");
            echo "<p>Produto de teste removido</p>";
        } else {
            echo "<p style='color: red;'>❌ Falha ao inserir produto</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teste de Cadastro de Produto</title>
</head>
<body>
    <h1>Teste de Cadastro de Produto</h1>
    <form method="post">
        <button type="submit">Testar Cadastro de Produto</button>
    </form>
</body>
</html>