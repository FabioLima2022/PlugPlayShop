<?php
/**
 * Teste de Conexão MySQL - PlugPlay Shop
 * Use este script para verificar a conexão com o banco de dados
 */

require_once 'config.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Teste MySQL - PlugPlay Shop</title>\n";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;background:#f5f5f5;}";
echo ".container{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo ".success{color:#28a745;font-weight:bold;}";
echo ".error{color:#dc3545;font-weight:bold;}";
echo ".info{color:#17a2b8;}";
echo "</style>\n</head>\n<body>\n";

echo "<div class=\"container\">\n";
echo "<h1>🛠️ Teste de Conexão MySQL</h1>\n";
echo "<p><strong>Horário:</strong> " . date('d/m/Y H:i:s') . "</p>\n";

// Carregar configurações
$env = load_env();
$host = $env['DB_HOST'] ?? '127.0.0.1';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';
$dbName = $env['DB_NAME'] ?? 'plugplayshop';
$port = isset($env['DB_PORT']) ? (int)$env['DB_PORT'] : 3306;

echo "<h2>📋 Configurações Atuais</h2>\n";
echo "<table border=\"1\" cellpadding=\"10\" style=\"border-collapse:collapse;border:1px solid #ddd;\">\n";
echo "<tr><td><strong>DB_HOST</strong></td><td>" . htmlspecialchars($host) . "</td></tr>\n";
echo "<tr><td><strong>DB_USER</strong></td><td>" . htmlspecialchars($user) . "</td></tr>\n";
echo "<tr><td><strong>DB_PASS</strong></td><td>" . ($pass !== '' ? '✓ Configurada' : '✗ Vazia') . "</td></tr>\n";
echo "<tr><td><strong>DB_NAME</strong></td><td>" . htmlspecialchars($dbName) . "</td></tr>\n";
echo "<tr><td><strong>DB_PORT</strong></td><td>" . $port . "</td></tr>\n";
echo "</table>\n";

// Testar conexão
echo "<h2>🧪 Testando Conexão</h2>\n";

$hosts = array_values(array_unique([$host, '127.0.0.1', 'localhost', '::1']));
$success = false;
$errors = [];

foreach ($hosts as $h) {
    echo "<h3>Tentando conectar com host: <span class=\"info\">" . htmlspecialchars($h) . "</span></h3>\n";
    
    // Testar conexão com banco de dados
    $db = @new mysqli($h, $user, $pass, $dbName, $port);
    
    if ($db && !$db->connect_errno) {
        echo "<p class=\"success\">✅ Conexão bem-sucedida!</p>\n";
        echo "<p><strong>Versão MySQL:</strong> " . $db->server_info . "</p>\n";
        
        // Testar consulta simples
        $result = $db->query("SELECT 1 as test");
        if ($result) {
            echo "<p class=\"success\">✅ Query de teste executada com sucesso!</p>\n";
            $result->free();
        } else {
            echo "<p class=\"error\">❌ Erro na query: " . $db->error . "</p>\n";
        }
        
        $db->close();
        $success = true;
        break;
    } else {
        $error = $db ? $db->connect_error : 'Erro desconhecido';
        $errors[] = "Host '$h': $error";
        echo "<p class=\"error\">❌ Erro: " . htmlspecialchars($error) . "</p>\n";
        
        // Se falhar, tentar conectar sem banco de dados
        echo "<p>Tentando conectar sem banco de dados...</p>\n";
        $server = @new mysqli($h, $user, $pass, '', $port);
        
        if ($server && !$server->connect_errno) {
            echo "<p class=\"info\">✓ Conexão ao servidor bem-sucedida (sem banco)</p>\n";
            
            // Verificar se o banco existe
            $databases = $server->query("SHOW DATABASES LIKE '" . $server->real_escape_string($dbName) . "'");
            if ($databases && $databases->num_rows > 0) {
                echo "<p class=\"info\">✓ Banco de dados '$dbName' existe</p>\n";
            } else {
                echo "<p class=\"error\">❌ Banco de dados '$dbName' NÃO existe</p>\n";
            }
            
            $server->close();
        } else {
            $serverError = $server ? $server->connect_error : 'Erro desconhecido';
            echo "<p class=\"error\">❌ Erro na conexão sem banco: " . htmlspecialchars($serverError) . "</p>\n";
        }
    }
}

echo "<h2>📊 Resultado Final</h2>\n";
if ($success) {
    echo "<p class=\"success\">🎉 <strong>SUCESSO!</strong> A conexão MySQL está funcionando corretamente.</p>\n";
} else {
    echo "<p class=\"error\">❌ <strong>FALHA!</strong> Não foi possível conectar ao MySQL.</p>\n";
    echo "<h3>Resumo dos erros:</h3>\n";
    echo "<ul>\n";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>\n";
    }
    echo "</ul>\n";
    
    echo "<h2>🔧 Sugestões de Solução</h2>\n";
    echo "<ol>\n";
    echo "<li><strong>Verificar senha do MySQL:</strong> Se o usuário for 'root' e a senha estiver vazia, tente adicionar a senha correta no arquivo .env</li>\n";
    echo "<li><strong>Criar usuário específico:</strong> Crie um usuário MySQL específico para o site com permissões apropriadas</li>\n";
    echo "<li><strong>Verificar servidor MySQL:</strong> Certifique-se de que o MySQL está rodando e aceitando conexões</li>\n";
    echo "<li><strong>Verificar firewall:</strong> Confirme que não há firewall bloqueando a porta 3306</li>\n";
    echo "</ol>\n";
}

echo "</div>\n";
echo "</body>\n</html>\n";