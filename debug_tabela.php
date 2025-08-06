<?php
require_once 'config.php';
require_once 'functions.php';

echo "<h2>Debug - Tabela graficos_salvos</h2>";

try {
    $pdo = conectarDB();
    
    // Verificar se a tabela existe
    echo "<h3>1. Verificando se tabela existe:</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'graficos_salvos'");
    $tabelaExiste = $stmt->rowCount() > 0;
    
    if ($tabelaExiste) {
        echo "<p style='color: green;'>✅ Tabela 'graficos_salvos' existe</p>";
        
        // Mostrar estrutura da tabela
        echo "<h3>2. Estrutura da tabela:</h3>";
        $stmt = $pdo->query("DESCRIBE graficos_salvos");
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            foreach ($row as $col) {
                echo "<td>" . htmlspecialchars($col) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        
        // Contar registros
        echo "<h3>3. Registros existentes:</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) FROM graficos_salvos");
        $count = $stmt->fetchColumn();
        echo "<p>Total de registros: <strong>$count</strong></p>";
        
        if ($count > 0) {
            echo "<h4>Últimos 5 registros:</h4>";
            $stmt = $pdo->query("SELECT id, nome, usuario_id, criado_em FROM graficos_salvos ORDER BY id DESC LIMIT 5");
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Nome</th><th>Usuário ID</th><th>Criado em</th></tr>";
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['nome']) . "</td>";
                echo "<td>" . $row['usuario_id'] . "</td>";
                echo "<td>" . $row['criado_em'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Tabela 'graficos_salvos' NÃO existe</p>";
        
        // Criar a tabela
        echo "<h3>2. Criando tabela:</h3>";
        $sql = "CREATE TABLE graficos_salvos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            nome VARCHAR(255) NOT NULL,
            configuracao TEXT NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Tabela criada com sucesso!</p>";
    }
    
    // Testar inserção
    echo "<h3>4. Teste de inserção:</h3>";
    
    $testData = [
        'usuario_id' => 1,
        'nome' => 'Teste Grafico ' . date('H:i:s'),
        'configuracao' => json_encode([
            'tipoGrafico' => 'bar',
            'campoX' => 'categoria_contratacao',
            'campoY' => 'valor_total_contratacao',
            'filtroAno' => '2025'
        ])
    ];
    
    $stmt = $pdo->prepare("INSERT INTO graficos_salvos (usuario_id, nome, configuracao) VALUES (?, ?, ?)");
    $resultado = $stmt->execute([$testData['usuario_id'], $testData['nome'], $testData['configuracao']]);
    
    if ($resultado) {
        $id = $pdo->lastInsertId();
        echo "<p style='color: green;'>✅ Inserção teste OK! ID: $id</p>";
        
        // Remover o teste
        $pdo->prepare("DELETE FROM graficos_salvos WHERE id = ?")->execute([$id]);
        echo "<p style='color: blue;'>🗑️ Registro de teste removido</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro na inserção teste</p>";
    }
    
    // Verificar sessão do usuário
    echo "<h3>5. Informações da sessão:</h3>";
    session_start();
    if (isset($_SESSION['usuario_id'])) {
        echo "<p>Usuário ID da sessão: <strong>" . $_SESSION['usuario_id'] . "</strong></p>";
        echo "<p>Usuário nome: <strong>" . ($_SESSION['usuario_nome'] ?? 'N/A') . "</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ Usuário não está logado na sessão</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr><p><a href='dashboard.php'>← Voltar ao Dashboard</a></p>";
?>