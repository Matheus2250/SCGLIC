<?php
/**
 * Teste da API de consulta PNCP
 */

require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

echo "<h2>🔍 Teste da Consulta PNCP</h2>";

// 1. Verificar dados diretamente no banco
echo "<h3>1. Consulta direta no banco:</h3>";

try {
    $sql = "SELECT COUNT(*) as total FROM pca_pncp WHERE ano_pca = 2026";
    $total = $pdo->query($sql)->fetchColumn();
    echo "✅ Total de registros no banco: <strong>{$total}</strong><br>";
    
    if ($total > 0) {
        // Buscar alguns registros de exemplo
        $sql = "SELECT id, sequencial, categoria_item, descricao_item, valor_estimado 
                FROM pca_pncp 
                WHERE ano_pca = 2026 
                LIMIT 5";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $registros = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Sequencial</th><th>Categoria</th><th>Descrição</th><th>Valor</th></tr>";
        
        foreach ($registros as $reg) {
            echo "<tr>";
            echo "<td>{$reg['id']}</td>";
            echo "<td>{$reg['sequencial']}</td>";
            echo "<td>" . htmlspecialchars(substr($reg['categoria_item'] ?? 'N/A', 0, 20)) . "</td>";
            echo "<td>" . htmlspecialchars(substr($reg['descricao_item'] ?? 'N/A', 0, 40)) . "...</td>";
            echo "<td>" . number_format($reg['valor_estimado'] ?? 0, 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

// 2. Testar API via requisição HTTP
echo "<h3>2. Teste da API de consulta:</h3>";

echo "<div id='resultado-api' style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "Clique no botão abaixo para testar a API...";
echo "</div>";

echo "<button onclick='testarAPI()' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;'>🔍 Testar API</button>";

?>

<script>
async function testarAPI() {
    const resultado = document.getElementById('resultado-api');
    
    resultado.innerHTML = '🔄 Testando API...';
    
    try {
        // Testar diferentes endpoints
        const testes = [
            { nome: 'Listar dados', url: 'api/consultar_pncp.php?acao=listar&ano=2026&limite=5' },
            { nome: 'Estatísticas', url: 'api/consultar_pncp.php?acao=estatisticas&ano=2026' },
            { nome: 'Opções filtro', url: 'api/consultar_pncp.php?acao=filtros&ano=2026' }
        ];
        
        let html = '<h4>Resultados dos testes:</h4>';
        
        for (const teste of testes) {
            html += `<div style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">`;
            html += `<strong>${teste.nome}:</strong><br>`;
            
            try {
                const response = await fetch(teste.url);
                const data = await response.json();
                
                if (data.sucesso) {
                    html += `✅ <span style="color: green;">Sucesso</span><br>`;
                    
                    if (teste.nome === 'Listar dados') {
                        const dados = data.dados;
                        html += `📊 Total: ${dados.paginacao?.total_registros || 0}<br>`;
                        html += `📄 Página: ${dados.dados?.length || 0} registros<br>`;
                        
                        if (dados.dados && dados.dados.length > 0) {
                            const primeiro = dados.dados[0];
                            html += `🔍 Primeiro: ${primeiro.sequencial} - ${primeiro.categoria_item || 'N/A'}<br>`;
                        }
                    } else if (teste.nome === 'Estatísticas') {
                        const stats = data.dados.geral;
                        html += `📊 Total registros: ${stats?.total_registros || 0}<br>`;
                        html += `💰 Valor total: R$ ${Number(stats?.valor_total || 0).toLocaleString()}<br>`;
                    } else if (teste.nome === 'Opções filtro') {
                        const opcoes = data.dados;
                        html += `🏷️ Categorias: ${opcoes?.categorias?.length || 0}<br>`;
                        html += `📋 Modalidades: ${opcoes?.modalidades?.length || 0}<br>`;
                    }
                } else {
                    html += `❌ <span style="color: red;">Erro: ${data.erro}</span><br>`;
                }
                
            } catch (error) {
                html += `❌ <span style="color: red;">Erro de rede: ${error.message}</span><br>`;
            }
            
            html += `</div>`;
        }
        
        resultado.innerHTML = html;
        
    } catch (error) {
        resultado.innerHTML = `❌ Erro geral: ${error.message}`;
    }
}

// Auto-executar após 2 segundos
setTimeout(testarAPI, 2000);
</script>

<div style="margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 5px;">
    <strong>💡 Diagnóstico:</strong><br>
    • Se o banco tem dados mas a API retorna 0, pode ser problema nos filtros SQL<br>
    • Se a API funciona mas o dashboard não mostra, pode ser problema no JavaScript<br>
    • Verificar console do navegador (F12) para erros JavaScript
</div>

<div style="margin-top: 20px;">
    <a href="debug_pncp.php" style="padding: 10px 15px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;">🔍 Debug Completo</a>
    <a href="dashboard.php?secao=pncp-integration" style="padding: 10px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">📊 Ir para Dashboard</a>
</div>