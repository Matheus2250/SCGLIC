<?php
// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_licitacao');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '8111');
define('DB_CHARSET', 'utf8mb4');

// Configurações do sistema
define('SITE_NAME', 'Sistema de Licitações');
define('SITE_URL', 'http://localhost/sistema_licitacao/');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Função para conectar ao banco
function conectarDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Erro na conexão: " . $e->getMessage());
    }
}

// Iniciar sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>