<?php
/**
 * Debug da integração PNCP - Verificar dados no banco
 */

require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

echo "<h2>🔍 Debug da Integração PNCP</h2>";

// 1. Verificar se as tabelas existem
echo "<h3>1. Verificando tabelas:</h3>";

$tabelas = ['pca_pncp', 'pca_pncp_sincronizacoes'];
foreach ($tabelas as $tabela) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM {$tabela}")->fetchColumn();
        echo "✅ Tabela <strong>{$tabela}</strong>: {$count} registros<br>";
    } catch (Exception $e) {
        echo "❌ Erro na tabela {$tabela}: " . $e->getMessage() . "<br>";
    }
}

// 2. Verificar dados específicos do PNCP
echo "<h3>2. Dados do PNCP (últimos 10 registros):</h3>";

try {
    $sql = "SELECT id, sequencial, categoria_item, descricao_item, valor_estimado, data_sincronizacao 
            FROM pca_pncp 
            WHERE ano_pca = 2026 
            ORDER BY id DESC 
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $dados = $stmt->fetchAll();
    
    if ($dados) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Sequencial</th><th>Categoria</th><th>Descrição</th><th>Valor</th><th>Data Sync</th></tr>";
        
        foreach ($dados as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['sequencial']}</td>";
            echo "<td>" . htmlspecialchars(substr($row['categoria_item'] ?? '', 0, 30)) . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['descricao_item'] ?? '', 0, 50)) . "...</td>";
            echo "<td>" . number_format($row['valor_estimado'] ?? 0, 2) . "</td>";
            echo "<td>{$row['data_sincronizacao']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ <strong>Nenhum registro encontrado na tabela pca_pncp para 2026</strong><br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao buscar dados: " . $e->getMessage() . "<br>";
}

// 3. Verificar histórico de sincronizações
echo "<h3>3. Histórico de sincronizações:</h3>";

try {
    $sql = "SELECT * FROM pca_pncp_sincronizacoes WHERE ano_pca = 2026 ORDER BY iniciada_em DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $sync = $stmt->fetchAll();
    
    if ($sync) {
        foreach ($sync as $s) {
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>ID:</strong> {$s['id']}<br>";
            echo "<strong>Status:</strong> {$s['status']}<br>";
            echo "<strong>Registros processados:</strong> {$s['registros_processados']}<br>";
            echo "<strong>Novos:</strong> {$s['registros_novos']}<br>";
            echo "<strong>Atualizados:</strong> {$s['registros_atualizados']}<br>";
            echo "<strong>Ignorados:</strong> {$s['registros_ignorados']}<br>";
            echo "<strong>Tempo:</strong> {$s['tempo_processamento']}s<br>";
            echo "<strong>Data:</strong> {$s['iniciada_em']}<br>";
            if ($s['mensagem_erro']) {
                echo "<strong>Erro:</strong> " . htmlspecialchars($s['mensagem_erro']) . "<br>";
            }
            echo "</div>";
        }
    } else {
        echo "❌ Nenhuma sincronização encontrada<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao buscar sincronizações: " . $e->getMessage() . "<br>";
}

// 4. Testar API de consulta
echo "<h3>4. Testando API de consulta:</h3>";

try {
    echo "<div id='teste-consulta'>";
    echo "<button onclick='testarConsulta()' style='padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px;'>🔍 Testar Consulta API</button>";
    echo "<div id='resultado-consulta' style='margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; display: none;'></div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

// 5. Verificar dados brutos de uma linha
echo "<h3>5. Exemplo de registro bruto:</h3>";

try {
    $sql = "SELECT dados_originais_json FROM pca_pncp WHERE ano_pca = 2026 AND dados_originais_json IS NOT NULL LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $dados_brutos = $stmt->fetchColumn();
    
    if ($dados_brutos) {
        echo "<details><summary>Ver dados originais do CSV</summary>";
        echo "<pre>" . htmlspecialchars($dados_brutos) . "</pre>";
        echo "</details>";
    } else {
        echo "❌ Nenhum dado original encontrado<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

?>

<script>
async function testarConsulta() {
    const btn = document.querySelector('button');
    const resultado = document.getElementById('resultado-consulta');
    
    btn.disabled = true;
    btn.textContent = '🔄 Testando...';
    resultado.style.display = 'block';
    resultado.textContent = 'Fazendo requisição...';
    
    try {
        const response = await fetch('api/consultar_pncp.php?acao=listar&ano=2026&limite=5');
        const data = await response.json();
        
        if (data.sucesso) {
            const dados = data.dados.dados;
            resultado.innerHTML = `
                <strong>✅ API funcionando!</strong><br>
                <strong>Total de registros:</strong> ${data.dados.paginacao.total_registros}<br>
                <strong>Registros na primeira página:</strong> ${dados.length}<br>
                <strong>Primeiro registro:</strong> ${dados[0] ? dados[0].sequencial + ' - ' + (dados[0].categoria_item || 'N/A') : 'Nenhum'}
            `;
        } else {
            resultado.innerHTML = `❌ Erro na API: ${data.erro}`;
        }
        
    } catch (error) {
        resultado.innerHTML = `❌ Erro de conexão: ${error.message}`;
    }
    
    btn.disabled = false;
    btn.textContent = '🔍 Testar Consulta API';
}

// Auto-executar teste após 2 segundos
setTimeout(testarConsulta, 2000);
</script>

<hr>
<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin-top: 20px;'>
    <strong>💡 Diagnóstico:</strong><br>
    1. Se a tabela pca_pncp tem 0 registros, o problema é na inserção<br>
    2. Se a tabela tem registros mas a API retorna 0, o problema é na consulta<br>
    3. Se a sincronização mostra "ignorados", o problema é na validação dos dados<br>
    4. Verificar os dados originais para entender a estrutura do CSV
</div>

<div style='margin-top: 20px;'>
    <a href='teste_sync_pncp.php' style='padding: 10px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>🔄 Nova Sincronização</a>
    <a href='limpar_pncp.php' style='padding: 10px 15px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;'>🗑️ Limpar Dados</a>
</div>