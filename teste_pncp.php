<?php
/**
 * Arquivo de teste para verificar a integração PNCP
 */

require_once 'config.php';
require_once 'functions.php';

// Verificar se as tabelas existem
$pdo = conectarDB();

echo "<h2>🔍 Teste da Integração PNCP</h2>";

// 1. Verificar se as tabelas existem
echo "<h3>1. Verificando tabelas no banco:</h3>";

$tabelas = ['pca_pncp', 'pca_pncp_sincronizacoes'];

foreach ($tabelas as $tabela) {
    try {
        $sql = "SHOW TABLES LIKE '{$tabela}'";
        $result = $pdo->query($sql);
        
        if ($result->rowCount() > 0) {
            echo "✅ Tabela <strong>{$tabela}</strong> existe<br>";
            
            // Mostrar estrutura
            $desc = $pdo->query("DESCRIBE {$tabela}")->fetchAll();
            echo "<details><summary>Ver estrutura</summary>";
            echo "<pre>";
            foreach ($desc as $col) {
                echo "{$col['Field']} - {$col['Type']}\n";
            }
            echo "</pre></details>";
            
        } else {
            echo "❌ Tabela <strong>{$tabela}</strong> NÃO existe<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao verificar tabela {$tabela}: " . $e->getMessage() . "<br>";
    }
}

// 2. Verificar arquivos da API
echo "<h3>2. Verificando arquivos da API:</h3>";

$arquivos = [
    'api/pncp_integration.php',
    'api/consultar_pncp.php',
    'assets/pncp-integration.js'
];

foreach ($arquivos as $arquivo) {
    if (file_exists($arquivo)) {
        echo "✅ Arquivo <strong>{$arquivo}</strong> existe (" . number_format(filesize($arquivo)) . " bytes)<br>";
    } else {
        echo "❌ Arquivo <strong>{$arquivo}</strong> NÃO existe<br>";
    }
}

// 3. Testar conectividade básica com a API PNCP
echo "<h3>3. Testando conectividade com API PNCP:</h3>";

try {
    $url = 'https://pncp.gov.br/api/pncp/v1/orgaos/00394544000185/pca/2026/csv';
    
    echo "🔗 Testando URL: <a href='{$url}' target='_blank'>{$url}</a><br>";
    
    // Testar com get_headers (mais rápido)
    $headers = @get_headers($url, 1);
    
    if ($headers) {
        $status = $headers[0];
        echo "📡 Status da API: <strong>{$status}</strong><br>";
        
        if (strpos($status, '200') !== false) {
            echo "✅ API está respondendo<br>";
        } else {
            echo "⚠️ API retornou status diferente de 200<br>";
        }
        
        if (isset($headers['Content-Length'])) {
            $size = is_array($headers['Content-Length']) ? end($headers['Content-Length']) : $headers['Content-Length'];
            echo "📁 Tamanho do arquivo: " . number_format($size) . " bytes<br>";
        }
        
    } else {
        echo "❌ Não foi possível conectar com a API<br>";
        echo "💡 Possíveis causas:<br>";
        echo "- Firewall bloqueando<br>";
        echo "- Sem conexão com internet<br>";
        echo "- API temporariamente indisponível<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao testar API: " . $e->getMessage() . "<br>";
}

// 4. Verificar permissões do usuário atual
echo "<h3>4. Verificando sessão e permissões:</h3>";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario_id'])) {
    echo "✅ Usuário logado: " . ($_SESSION['usuario_nome'] ?? 'N/A') . "<br>";
    echo "📧 Email: " . ($_SESSION['usuario_email'] ?? 'N/A') . "<br>";
    echo "🔑 Nível: " . ($_SESSION['usuario_nivel'] ?? 'N/A') . "<br>";
    
    // Verificar permissão para importar PCA
    if (function_exists('temPermissao')) {
        $podeImportar = temPermissao('pca_importar');
        echo $podeImportar ? "✅ Tem permissão para importar PCA<br>" : "❌ NÃO tem permissão para importar PCA<br>";
    } else {
        echo "⚠️ Função temPermissao() não encontrada<br>";
    }
    
} else {
    echo "❌ Usuário NÃO está logado<br>";
    echo "➡️ <a href='index.php'>Fazer login</a><br>";
}

// 5. Teste de CSRF Token
echo "<h3>5. Testando CSRF Token:</h3>";

if (function_exists('generateCSRFToken')) {
    $token = generateCSRFToken();
    echo "✅ CSRF Token gerado: <code>" . substr($token, 0, 20) . "...</code><br>";
    echo "<input type='hidden' name='csrf_token' value='{$token}' id='test-csrf-token'><br>";
} else {
    echo "❌ Função generateCSRFToken() não encontrada<br>";
}

// 6. JavaScript de teste
echo "<h3>6. Teste JavaScript:</h3>";
?>

<script>
console.log('=== TESTE PNCP DEBUG ===');

// Verificar se as funções estão disponíveis
const funcoesPNCP = [
    'sincronizarPNCP',
    'inicializarPNCP', 
    'consultarDadosPNCP',
    'compararDados'
];

funcoesPNCP.forEach(func => {
    if (typeof window[func] === 'function') {
        console.log('✅ Função', func, 'está disponível');
    } else {
        console.log('❌ Função', func, 'NÃO está disponível');
    }
});

// Verificar elementos da interface
const elementos = [
    'btn-sincronizar-pncp',
    'ano-pncp',
    'progresso-pncp',
    'csrf_token'
];

elementos.forEach(id => {
    const elemento = document.getElementById(id);
    if (elemento) {
        console.log('✅ Elemento', id, 'encontrado:', elemento.tagName);
    } else {
        console.log('❌ Elemento', id, 'NÃO encontrado');
    }
});

// Testar clique no botão
function testarBotaoPNCP() {
    console.log('🔄 Testando clique no botão PNCP...');
    
    const botao = document.getElementById('btn-sincronizar-pncp');
    if (botao) {
        console.log('Botão encontrado, simulando clique...');
        
        // Simular clique
        if (typeof window.sincronizarPNCP === 'function') {
            try {
                window.sincronizarPNCP();
                console.log('✅ Função sincronizarPNCP executada');
            } catch (error) {
                console.error('❌ Erro ao executar sincronizarPNCP:', error);
            }
        } else {
            console.error('❌ Função sincronizarPNCP não está definida');
        }
    } else {
        console.error('❌ Botão não encontrado');
    }
}

// Aguardar um pouco e executar teste
setTimeout(testarBotaoPNCP, 2000);
</script>

<div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
    <h3>🧪 Teste Manual</h3>
    <p>Abra o <strong>Console do Navegador</strong> (F12) para ver os logs de debug.</p>
    <button onclick="testarBotaoPNCP()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
        🔄 Testar Botão PNCP Manualmente
    </button>
</div>

<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
    <strong>📝 Próximos passos:</strong><br>
    1. Verificar se todas as verificações acima estão ✅<br>
    2. Se alguma tabela não existir, executar o script SQL<br>
    3. Verificar Console do Navegador (F12) para erros JavaScript<br>
    4. Se tudo estiver OK, o problema pode ser de permissões ou sessão
</div>

<?php
echo "<hr><small><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</small>";
?>