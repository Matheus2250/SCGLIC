<?php
/**
 * Teste direto da sincronização PNCP
 */

require_once 'config.php';
require_once 'functions.php';

// Verificar se está logado
verificarLogin();

// Gerar token CSRF
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste Sincronização PNCP</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .log { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; white-space: pre-wrap; font-family: monospace; }
        .progress { width: 100%; background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #007bff, #0056b3); width: 0%; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Teste de Sincronização PNCP</h1>
        
        <div class="form-group">
            <label>Ano do PCA:</label>
            <select id="ano" onchange="updateUrl()">
                <option value="2026">2026</option>
                <option value="2025">2025</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>URL da API:</label>
            <input type="text" id="url" style="width: 100%; padding: 8px;" readonly>
        </div>
        
        <div class="form-group">
            <button id="btnTestarConexao" class="btn" onclick="testarConexao()">
                🔗 Testar Conexão com API
            </button>
            
            <button id="btnSincronizar" class="btn" onclick="sincronizar()" style="margin-left: 10px;">
                🔄 Sincronizar Dados
            </button>
        </div>
        
        <div id="progress" class="progress" style="display: none;">
            <div id="progressBar" class="progress-bar"></div>
        </div>
        <div id="progressText" style="text-align: center; margin-top: 5px; display: none;"></div>
        
        <div id="log" class="log"></div>
        
        <!-- Token CSRF oculto -->
        <input type="hidden" id="csrf_token" value="<?php echo $csrf_token; ?>">
    </div>

    <script>
        function updateUrl() {
            const ano = document.getElementById('ano').value;
            const url = `https://pncp.gov.br/api/pncp/v1/orgaos/00394544000185/pca/${ano}/csv`;
            document.getElementById('url').value = url;
        }
        
        function log(message) {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            logDiv.textContent += `[${timestamp}] ${message}\n`;
            logDiv.scrollTop = logDiv.scrollHeight;
            console.log(`[PNCP Test] ${message}`);
        }
        
        function updateProgress(percent, text) {
            const progressDiv = document.getElementById('progress');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            if (percent > 0) {
                progressDiv.style.display = 'block';
                progressText.style.display = 'block';
                progressBar.style.width = percent + '%';
                progressText.textContent = text || `${percent}%`;
            } else {
                progressDiv.style.display = 'none';
                progressText.style.display = 'none';
            }
        }
        
        async function testarConexao() {
            const btn = document.getElementById('btnTestarConexao');
            btn.disabled = true;
            btn.textContent = '🔄 Testando...';
            
            log('Iniciando teste de conexão...');
            
            try {
                const ano = document.getElementById('ano').value;
                const url = `https://pncp.gov.br/api/pncp/v1/orgaos/00394544000185/pca/${ano}/csv`;
                
                log(`Testando URL: ${url}`);
                
                // Fazer uma requisição HEAD para verificar disponibilidade
                const response = await fetch(url, { 
                    method: 'HEAD',
                    mode: 'no-cors' // Para evitar problemas de CORS
                });
                
                log('Resposta recebida da API');
                log(`Status: ${response.status || 'Indeterminado (no-cors)'}`);
                
                if (response.type === 'opaque') {
                    log('✅ API está acessível (resposta opaca devido ao CORS)');
                } else if (response.ok) {
                    log('✅ API está acessível e respondendo');
                } else {
                    log(`⚠️ API respondeu com status ${response.status}`);
                }
                
            } catch (error) {
                log(`❌ Erro ao testar conexão: ${error.message}`);
                log('💡 Isso pode ser normal devido ao CORS. A sincronização pode ainda funcionar via servidor PHP.');
            }
            
            btn.disabled = false;
            btn.textContent = '🔗 Testar Conexão com API';
        }
        
        async function sincronizar() {
            const btn = document.getElementById('btnSincronizar');
            btn.disabled = true;
            btn.textContent = '🔄 Sincronizando...';
            
            log('=== INICIANDO SINCRONIZAÇÃO ===');
            
            try {
                const ano = document.getElementById('ano').value;
                const csrfToken = document.getElementById('csrf_token').value;
                
                log(`Ano selecionado: ${ano}`);
                log(`Token CSRF: ${csrfToken.substring(0, 10)}...`);
                
                updateProgress(10, 'Preparando dados...');
                
                const formData = new FormData();
                formData.append('acao', 'sincronizar');
                formData.append('ano', ano);
                formData.append('csrf_token', csrfToken);
                
                log('Enviando requisição para api/pncp_integration.php');
                updateProgress(20, 'Conectando com servidor...');
                
                const response = await fetch('api/pncp_integration.php', {
                    method: 'POST',
                    body: formData
                });
                
                log(`Status da resposta: ${response.status}`);
                updateProgress(50, 'Processando resposta...');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const contentType = response.headers.get('content-type');
                log(`Content-Type: ${contentType}`);
                
                const responseText = await response.text();
                log(`Resposta recebida (${responseText.length} chars)`);
                
                updateProgress(70, 'Analisando dados...');
                
                let resultado;
                try {
                    resultado = JSON.parse(responseText);
                    log('✅ JSON parsado com sucesso');
                } catch (parseError) {
                    log('❌ Erro ao parsear JSON:');
                    log(responseText.substring(0, 500) + '...');
                    throw new Error('Resposta não é um JSON válido');
                }
                
                updateProgress(90, 'Finalizando...');
                
                if (resultado.sucesso) {
                    log('✅ SINCRONIZAÇÃO CONCLUÍDA COM SUCESSO!');
                    log(`📊 Registros processados: ${resultado.total_processados || 'N/A'}`);
                    log(`🆕 Novos registros: ${resultado.novos || 'N/A'}`);
                    log(`🔄 Registros atualizados: ${resultado.atualizados || 'N/A'}`);
                    log(`⏱️ Tempo: ${resultado.tempo || 'N/A'}s`);
                    
                    if (resultado.log && Array.isArray(resultado.log)) {
                        log('\n--- LOG DETALHADO ---');
                        resultado.log.forEach(logEntry => {
                            log(logEntry);
                        });
                    }
                    
                    updateProgress(100, 'Concluído!');
                    
                } else {
                    log('❌ ERRO NA SINCRONIZAÇÃO:');
                    log(resultado.erro || 'Erro desconhecido');
                    updateProgress(0);
                }
                
            } catch (error) {
                log(`❌ ERRO CRÍTICO: ${error.message}`);
                log('Stack trace:');
                log(error.stack || 'Não disponível');
                updateProgress(0);
            }
            
            btn.disabled = false;
            btn.textContent = '🔄 Sincronizar Dados';
        }
        
        // Inicializar
        updateUrl();
        log('Sistema de teste inicializado');
        log('Usuário logado: <?php echo $_SESSION['usuario_nome'] ?? 'N/A'; ?>');
        log('Nível de acesso: <?php echo $_SESSION['usuario_nivel'] ?? 'N/A'; ?>');
    </script>
</body>
</html>