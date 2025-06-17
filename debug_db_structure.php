<?php
/**
 * Script de debug para verificar estrutura do banco
 */
require_once 'config.php';
require_once 'functions.php';

echo "<h1>Debug - Estrutura do Banco de Dados</h1>";

try {
    $pdo = conectarDB();
    echo "<p style='color: green;'>✅ Conexão com banco estabelecida</p>";

    // 1. Verificar AUTO_INCREMENT das tabelas críticas
    echo "<h2>🔍 AUTO_INCREMENT das Tabelas</h2>";
    $tabelas = ['usuarios', 'pca_importacoes', 'pca_dados', 'licitacoes', 'logs_sistema'];
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Tabela</th><th>AUTO_INCREMENT</th><th>MAX(id)</th><th>Total Registros</th><th>Status</th></tr>";
    
    foreach ($tabelas as $tabela) {
        // AUTO_INCREMENT
        $sql_auto = "SELECT AUTO_INCREMENT FROM information_schema.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        $stmt_auto = $pdo->prepare($sql_auto);
        $stmt_auto->execute([$tabela]);
        $auto_result = $stmt_auto->fetch();
        $auto_increment = $auto_result ? $auto_result['AUTO_INCREMENT'] : 'NULL';
        
        // MAX(id) e COUNT
        $sql_max = "SELECT COALESCE(MAX(id), 0) as max_id, COUNT(*) as total FROM $tabela";
        $stmt_max = $pdo->prepare($sql_max);
        $stmt_max->execute();
        $max_result = $stmt_max->fetch();
        
        $max_id = $max_result['max_id'];
        $total = $max_result['total'];
        
        // Status
        $status = '✅ OK';
        if ($auto_increment <= 0) {
            $status = '❌ AUTO_INCREMENT inválido';
        } elseif ($auto_increment <= $max_id) {
            $status = '⚠️ AUTO_INCREMENT menor que MAX(id)';
        }
        
        echo "<tr>";
        echo "<td>$tabela</td>";
        echo "<td>$auto_increment</td>";
        echo "<td>$max_id</td>";
        echo "<td>$total</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 2. Verificar registros da pca_importacoes
    echo "<h2>📊 Últimas Importações</h2>";
    $sql_import = "SELECT id, nome_arquivo, usuario_id, ano_pca, status, criado_em 
                   FROM pca_importacoes 
                   ORDER BY criado_em DESC 
                   LIMIT 10";
    $stmt_import = $pdo->query($sql_import);
    $importacoes = $stmt_import->fetchAll();
    
    if ($importacoes) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Arquivo</th><th>Usuário</th><th>Ano</th><th>Status</th><th>Data</th></tr>";
        
        foreach ($importacoes as $imp) {
            echo "<tr>";
            echo "<td>" . $imp['id'] . "</td>";
            echo "<td>" . htmlspecialchars($imp['nome_arquivo']) . "</td>";
            echo "<td>" . $imp['usuario_id'] . "</td>";
            echo "<td>" . $imp['ano_pca'] . "</td>";
            echo "<td>" . $imp['status'] . "</td>";
            echo "<td>" . $imp['criado_em'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhuma importação encontrada.</p>";
    }

    // 3. Verificar usuários
    echo "<h2>👥 Usuários Ativos</h2>";
    $sql_users = "SELECT id, nome, email, nivel_acesso, ativo FROM usuarios WHERE ativo = 1 LIMIT 10";
    $stmt_users = $pdo->query($sql_users);
    $usuarios = $stmt_users->fetchAll();
    
    if ($usuarios) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Nível</th></tr>";
        
        foreach ($usuarios as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . $user['nivel_acesso'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum usuário ativo encontrado.</p>";
    }

    // 4. Testar função de correção
    echo "<h2>🔧 Teste da Função de Correção</h2>";
    $resultado_correcao = verificarECorrigirAutoIncrement('pca_importacoes');
    echo "<p>Resultado da correção de AUTO_INCREMENT para pca_importacoes: <strong>$resultado_correcao</strong></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>