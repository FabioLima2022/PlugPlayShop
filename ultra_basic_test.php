<?php
// Teste básico que sempre deve funcionar
echo "Teste básico - Se você ver isso, PHP está funcionando<br>";
echo "Versão do PHP: " . phpversion() . "<br>";
echo "Diretório atual: " . __DIR__ . "<br>";

// Testar se config.php existe e pode ser carregado
if (file_exists(__DIR__ . '/config.php')) {
    echo "config.php existe<br>";
    try {
        require_once __DIR__ . '/config.php';
        echo "config.php carregado com sucesso<br>";
        
        // Testar função get_db
        if (function_exists('get_db')) {
            echo "Função get_db existe<br>";
            try {
                $db = get_db();
                echo "Conexão com banco estabelecida<br>";
                
                // Testar query simples
                $result = $db->query("SELECT 1 as test");
                if ($result) {
                    echo "Query simples funcionou<br>";
                } else {
                    echo "Query falhou: " . $db->error . "<br>";
                }
            } catch (Exception $e) {
                echo "Erro ao conectar: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "Função get_db NÃO existe<br>";
        }
        
    } catch (Exception $e) {
        echo "Erro ao carregar config.php: " . $e->getMessage() . "<br>";
    }
} else {
    echo "config.php NÃO existe<br>";
}

echo "<br>Fim do teste";