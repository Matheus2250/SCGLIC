<?php
/**
 * Script de Configuração Automática para XAMPP
 * Execute este arquivo uma vez para configurar o sistema automaticamente
 */

echo "<h1>Configuração do Sistema para XAMPP</h1>";

// 1. Configurar config.php
$configPath = __DIR__ . '/config/config.php';
$configContent = '<?php
define(\'APP_NAME\', \'Sistema de Licitações\');
define(\'BASE_URL\', \'http://localhost/SistemaNovo/public/\');
';

if (file_put_contents($configPath, $configContent)) {
    echo "<p>✅ config.php configurado com sucesso!</p>";
} else {
    echo "<p>❌ Erro ao configurar config.php</p>";
}

// 2. Configurar database.php
$databasePath = __DIR__ . '/config/database.php';
$databaseContent = '<?php
class Database {
    public static function connect() {
        $host = \'localhost\';
        $db   = \'sistema_licitacao\';
        $user = \'root\';
        $pass = \'\';
        $charset = \'utf8mb4\';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            return $pdo;
        } catch (PDOException $e) {
            die("Erro na conexão: " . $e->getMessage());
        }
    }
}
';

if (file_put_contents($databasePath, $databaseContent)) {
    echo "<p>✅ database.php configurado com sucesso!</p>";
} else {
    echo "<p>❌ Erro ao configurar database.php</p>";
}

// 3. Verificar .htaccess
$htaccessPath = __DIR__ . '/public/.htaccess';
if (file_exists($htaccessPath)) {
    echo "<p>✅ .htaccess encontrado!</p>";
} else {
    $htaccessContent = 'RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.+)$ index.php?url=$1 [QSA,L]
';
    if (file_put_contents($htaccessPath, $htaccessContent)) {
        echo "<p>✅ .htaccess criado com sucesso!</p>";
    } else {
        echo "<p>❌ Erro ao criar .htaccess</p>";
    }
}

// 4. Testar conexão com banco
echo "<h2>Teste de Conexão</h2>";
try {
    require_once $databasePath;
    $pdo = Database::connect();
    echo "<p>✅ Conexão com banco de dados estabelecida!</p>";
    
    // Verificar se tabela usuarios existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Tabela 'usuarios' encontrada!</p>";
        
        // Contar usuários
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
        $count = $stmt->fetchColumn();
        echo "<p>📊 Total de usuários cadastrados: $count</p>";
    } else {
        echo "<p>⚠️ Tabela 'usuarios' não encontrada. Importe o arquivo banco/sistema_licitacao.sql</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro na conexão: " . $e->getMessage() . "</p>";
    echo "<p>📝 Verifique se:</p>";
    echo "<ul>";
    echo "<li>MySQL está rodando no XAMPP</li>";
    echo "<li>Banco 'sistema_licitacao' foi criado</li>";
    echo "<li>Arquivo banco/sistema_licitacao.sql foi importado</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h2>Próximos Passos:</h2>";
echo "<ol>";
echo "<li>Se ainda não fez, importe o arquivo <code>banco/sistema_licitacao.sql</code> no phpMyAdmin</li>";
echo "<li>Acesse: <a href='http://localhost/SistemaNovo/public/' target='_blank'>http://localhost/SistemaNovo/public/</a></li>";
echo "<li>Use o usuário: <strong>onesiolucena@gmail.com</strong> (Coordenador)</li>";
echo "<li>Delete este arquivo setup_xampp.php após a configuração</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Sistema configurado em: " . date('d/m/Y H:i:s') . "</small></p>";
?>