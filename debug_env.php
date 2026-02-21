<?php
// Testar variáveis de ambiente e configuração
echo "<h1>Debug de Variáveis de Ambiente</h1>";

// Carregar config.php manualmente para ver o que acontece
$env_content = file_get_contents(__DIR__ . '/.env');
echo "<h2>Conteúdo do .env:</h2>";
echo "<pre>" . htmlspecialchars($env_content) . "</pre>";

// Testar função load_env
require_once __DIR__ . '/config.php';

$env = load_env();
echo "<h2>Variáveis carregadas:</h2>";
echo "<pre>" . htmlspecialchars(print_r($env, true)) . "</pre>";

// Testar getenv
echo "<h2>getenv():</h2>";
echo "DB_USER: " . htmlspecialchars(getenv('DB_USER')) . "<br>";
echo "DB_NAME: " . htmlspecialchars(getenv('DB_NAME')) . "<br>";