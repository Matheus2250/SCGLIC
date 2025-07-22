<?php
// Teste simples para qualificação
require_once 'config.php';
require_once 'functions.php';

// Simular sessão para teste
session_start();
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 1;
    $_SESSION['usuario_nome'] = 'Teste';
    $_SESSION['usuario_email'] = 'teste@teste.com';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Formulário Qualificação</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: red; margin-top: 10px; }
        .success { color: green; margin-top: 10px; }
        #result { margin-top: 20px; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔧 Teste de Formulário de Qualificação</h1>
    
    <form id="testForm" method="POST">
        <input type="hidden" name="acao" value="criar_qualificacao">
        
        <div class="form-group">
            <label>NUP:</label>
            <input type="text" name="nup" value="12345.678901/2024-12" required>
        </div>
        
        <div class="form-group">
            <label>Área Demandante:</label>
            <input type="text" name="area_demandante" value="CGLIC - Teste" required>
        </div>
        
        <div class="form-group">
            <label>Responsável:</label>
            <input type="text" name="responsavel" value="João da Silva" required>
        </div>
        
        <div class="form-group">
            <label>Modalidade:</label>
            <select name="modalidade" required>
                <option value="">Selecione...</option>
                <option value="Pregão Eletrônico" selected>Pregão Eletrônico</option>
                <option value="Concorrência">Concorrência</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Objeto:</label>
            <textarea name="objeto" required>Teste de qualificação para verificar funcionamento do sistema</textarea>
        </div>
        
        <div class="form-group">
            <label>Palavras-chave:</label>
            <input type="text" name="palavras_chave" value="teste, sistema, qualificacao">
        </div>
        
        <div class="form-group">
            <label>Valor Estimado:</label>
            <input type="text" name="valor_estimado" value="1000,00" required>
        </div>
        
        <div class="form-group">
            <label>Status:</label>
            <select name="status" required>
                <option value="">Selecione...</option>
                <option value="Em Análise" selected>Em Análise</option>
                <option value="Aprovado">Aprovado</option>
                <option value="Reprovado">Reprovado</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Observações:</label>
            <textarea name="observacoes">Teste automatizado do sistema</textarea>
        </div>
        
        <button type="submit">Salvar Qualificação (Teste)</button>
    </form>
    
    <div id="result"></div>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            
            console.log('=== TESTE QUALIFICAÇÃO ===');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            fetch('process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response:', text);
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        resultDiv.innerHTML = `<div class="success">✅ Sucesso: ${data.message}</div>`;
                    } else {
                        resultDiv.innerHTML = `<div class="error">❌ Erro: ${data.message}</div>`;
                    }
                } catch (e) {
                    console.error('Erro JSON:', e);
                    resultDiv.innerHTML = `<div class="error">❌ Resposta inválida: ${text}</div>`;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                resultDiv.innerHTML = `<div class="error">❌ Erro de conexão: ${error.message}</div>`;
            });
        });
    </script>
    
    <h2>🔍 Verificação do Banco</h2>
    <?php
    try {
        $pdo = conectarDB();
        
        // Verificar se tabela existe
        $result = $pdo->query("SHOW TABLES LIKE 'qualificacoes'");
        if ($result->rowCount() > 0) {
            echo "<p>✅ Tabela 'qualificacoes' existe</p>";
            
            // Contar registros
            $count = $pdo->query("SELECT COUNT(*) as total FROM qualificacoes")->fetch();
            echo "<p>📊 Total de qualificações: " . $count['total'] . "</p>";
            
            // Mostrar estrutura
            echo "<h3>Estrutura da tabela:</h3>";
            $desc = $pdo->query("DESCRIBE qualificacoes");
            echo "<ul>";
            while ($row = $desc->fetch()) {
                echo "<li><strong>{$row['Field']}</strong> - {$row['Type']}</li>";
            }
            echo "</ul>";
            
        } else {
            echo "<p>❌ Tabela 'qualificacoes' NÃO existe</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    }
    ?>
    
    <h2>📄 Últimas qualificações</h2>
    <?php
    try {
        $stmt = $pdo->query("SELECT * FROM qualificacoes ORDER BY criado_em DESC LIMIT 5");
        $qualificacoes = $stmt->fetchAll();
        
        if (empty($qualificacoes)) {
            echo "<p>Nenhuma qualificação encontrada.</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>NUP</th><th>Área</th><th>Status</th><th>Valor</th><th>Criado em</th></tr>";
            foreach ($qualificacoes as $q) {
                echo "<tr>";
                echo "<td>{$q['id']}</td>";
                echo "<td>{$q['nup']}</td>";
                echo "<td>{$q['area_demandante']}</td>";
                echo "<td>{$q['status']}</td>";
                echo "<td>R$ " . number_format($q['valor_estimado'], 2, ',', '.') . "</td>";
                echo "<td>{$q['criado_em']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<p>Erro ao buscar qualificações: " . $e->getMessage() . "</p>";
    }
    ?>
</body>
</html>