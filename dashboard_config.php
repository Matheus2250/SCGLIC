<?php
/**
 * Configurações Personalizadas de Dashboard - Sistema CGLIC
 * 
 * CRIADO: 01/01/2025
 * FUNCIONALIDADES: Personalização de widgets, densidade, configurações de exibição
 */

require_once 'config.php';
require_once 'functions.php';

verificarLogin();

// Configurações padrão do dashboard
$defaultDashboardConfig = [
    'widgets' => [
        'estatisticas_gerais' => true,
        'grafico_areas' => true,
        'grafico_situacao' => true,
        'grafico_mensal' => true,
        'licitacoes_recentes' => true,
        'contratacoes_atrasadas' => true,
        'importacoes_recentes' => true,
        'riscos_altos' => true
    ],
    'densidade' => 'normal', // compact, normal, spacious
    'itens_por_pagina' => 10,
    'refresh_automatico' => false,
    'tempo_refresh' => 30, // segundos
    'ordenacao_padrao' => 'data_desc',
    'tema_grafico' => 'default'
];

// Processar configurações via POST
if ($_POST && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_POST['action']) {
            case 'save_dashboard_config':
                $config = [];
                
                // Processar widgets
                $config['widgets'] = [];
                foreach ($defaultDashboardConfig['widgets'] as $widget => $default) {
                    $config['widgets'][$widget] = isset($_POST['widget_' . $widget]);
                }
                
                // Processar outras configurações
                $config['densidade'] = $_POST['densidade'] ?? 'normal';
                $config['itens_por_pagina'] = max(5, min(100, intval($_POST['itens_por_pagina'] ?? 10)));
                $config['refresh_automatico'] = isset($_POST['refresh_automatico']);
                $config['tempo_refresh'] = max(10, min(300, intval($_POST['tempo_refresh'] ?? 30)));
                $config['ordenacao_padrao'] = $_POST['ordenacao_padrao'] ?? 'data_desc';
                $config['tema_grafico'] = $_POST['tema_grafico'] ?? 'default';
                
                // Salvar no localStorage (será feito via JavaScript)
                $response['success'] = true;
                $response['message'] = 'Configurações salvas com sucesso!';
                $response['config'] = $config;
                break;
                
            case 'reset_dashboard_config':
                $response['success'] = true;
                $response['message'] = 'Configurações restauradas para o padrão!';
                $response['config'] = $defaultDashboardConfig;
                break;
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    // Retornar JSON para AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Função para obter configuração atual (JavaScript será responsável por isso)
function getDashboardConfig() {
    global $defaultDashboardConfig;
    return $defaultDashboardConfig;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações do Dashboard - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/dark-mode.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        .config-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px;
            background-color: var(--bg-primary);
            min-height: 100vh;
        }
        
        .config-header {
            background: linear-gradient(135deg, #6c5ce7 0%, #5a4fcf 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.3);
            text-align: center;
        }
        
        .config-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            color: white !important;
        }
        
        .config-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }
        
        .config-section {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-card);
            margin-bottom: 30px;
        }
        
        .config-section h3 {
            margin: 0 0 20px 0;
            color: var(--text-primary);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .widgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .widget-control {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .widget-control:hover {
            border-color: var(--border-input);
        }
        
        .widget-control.active {
            border-color: #6c5ce7;
            background: rgba(108, 92, 231, 0.1);
        }
        
        .widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .widget-title {
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .widget-toggle {
            position: relative;
            width: 50px;
            height: 26px;
            background: #ddd;
            border-radius: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .widget-toggle.active {
            background: #6c5ce7;
        }
        
        .widget-toggle::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 22px;
            height: 22px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .widget-toggle.active::before {
            transform: translateX(24px);
        }
        
        .widget-description {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }
        
        .config-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-group select,
        .form-group input[type="number"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-input);
            border-radius: 8px;
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group select:focus,
        .form-group input[type="number"]:focus {
            outline: none;
            border-color: #6c5ce7;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding: 15px;
            background: var(--bg-secondary);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .checkbox-group:hover {
            background: var(--border-input);
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #6c5ce7;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }
        
        .actions-bar {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
        }
        
        .btn-primary, .btn-secondary, .btn-danger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 140px;
            justify-content: center;
        }
        
        .btn-primary {
            background: #6c5ce7;
            color: white;
            box-shadow: 0 2px 8px rgba(108, 92, 231, 0.3);
        }
        
        .btn-primary:hover {
            background: #5a4fcf;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 92, 231, 0.4);
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 2px solid var(--border-input);
        }
        
        .btn-secondary:hover {
            background: var(--border-input);
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        
        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
        }
        
        .preview-section {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-card);
            margin-bottom: 30px;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .preview-widget {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .preview-widget.disabled {
            opacity: 0.5;
            filter: grayscale(100%);
        }
        
        .preview-widget.compact {
            padding: 15px;
            font-size: 14px;
        }
        
        .preview-widget.spacious {
            padding: 25px;
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .config-container {
                padding: 20px;
            }
            
            .widgets-grid {
                grid-template-columns: 1fr;
            }
            
            .config-form {
                grid-template-columns: 1fr;
            }
            
            .actions-bar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="config-container">
        <!-- Header -->
        <div class="config-header">
            <h1><i data-lucide="layout-dashboard"></i> Configurações do Dashboard</h1>
            <p>Personalize sua experiência no dashboard do sistema</p>
        </div>

        <!-- Preview Section -->
        <div class="preview-section">
            <h3><i data-lucide="eye"></i> Visualização</h3>
            <div class="preview-grid" id="preview-grid">
                <!-- Widgets preview será gerado via JavaScript -->
            </div>
        </div>

        <!-- Form de Configuração -->
        <form id="dashboard-config-form">
            <!-- Widgets Section -->
            <div class="config-section">
                <h3><i data-lucide="grid-3x3"></i> Widgets do Dashboard</h3>
                <div class="widgets-grid" id="widgets-grid">
                    <!-- Widgets serão gerados via JavaScript -->
                </div>
            </div>

            <!-- Configurações Gerais -->
            <div class="config-section">
                <h3><i data-lucide="sliders"></i> Configurações Gerais</h3>
                <div class="config-form">
                    <div class="form-group">
                        <label for="densidade">Densidade da Interface</label>
                        <select id="densidade" name="densidade">
                            <option value="compact">Compacta (mais informações)</option>
                            <option value="normal" selected>Normal (padrão)</option>
                            <option value="spacious">Espaçosa (mais confortável)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="itens_por_pagina">Itens por Página</label>
                        <input type="number" id="itens_por_pagina" name="itens_por_pagina" value="10" min="5" max="100" step="5">
                    </div>
                    
                    <div class="form-group">
                        <label for="ordenacao_padrao">Ordenação Padrão</label>
                        <select id="ordenacao_padrao" name="ordenacao_padrao">
                            <option value="data_desc">Data (mais recente)</option>
                            <option value="data_asc">Data (mais antiga)</option>
                            <option value="nome_asc">Nome (A-Z)</option>
                            <option value="nome_desc">Nome (Z-A)</option>
                            <option value="valor_desc">Valor (maior)</option>
                            <option value="valor_asc">Valor (menor)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tema_grafico">Tema dos Gráficos</label>
                        <select id="tema_grafico" name="tema_grafico">
                            <option value="default">Padrão</option>
                            <option value="dark">Escuro</option>
                            <option value="colorful">Colorido</option>
                            <option value="minimal">Minimalista</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Configurações de Atualização -->
            <div class="config-section">
                <h3><i data-lucide="refresh-cw"></i> Atualização Automática</h3>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="refresh_automatico" name="refresh_automatico">
                    <label for="refresh_automatico">Ativar atualização automática dos dados</label>
                </div>
                
                <div class="form-group">
                    <label for="tempo_refresh">Intervalo de Atualização (segundos)</label>
                    <input type="number" id="tempo_refresh" name="tempo_refresh" value="30" min="10" max="300" step="10">
                </div>
            </div>

            <!-- Actions -->
            <div class="actions-bar">
                <a href="selecao_modulos.php" class="btn-secondary">
                    <i data-lucide="arrow-left"></i> Voltar
                </a>
                <button type="button" class="btn-danger" onclick="resetConfig()">
                    <i data-lucide="rotate-ccw"></i> Restaurar Padrão
                </button>
                <button type="submit" class="btn-primary">
                    <i data-lucide="save"></i> Salvar Configurações
                </button>
            </div>
        </form>
    </div>

    <script src="assets/dark-mode.js"></script>
    <script>
        // Configurações padrão
        const defaultConfig = <?php echo json_encode($defaultDashboardConfig); ?>;
        
        // Widgets disponíveis
        const availableWidgets = {
            'estatisticas_gerais': {
                name: 'Estatísticas Gerais',
                description: 'Cards com números principais do sistema',
                icon: 'bar-chart'
            },
            'grafico_areas': {
                name: 'Gráfico por Áreas',
                description: 'Distribuição de contratações por área',
                icon: 'pie-chart'
            },
            'grafico_situacao': {
                name: 'Gráfico de Situação',
                description: 'Status das contratações e licitações',
                icon: 'activity'
            },
            'grafico_mensal': {
                name: 'Evolução Mensal',
                description: 'Gráfico de linha com evolução temporal',
                icon: 'trending-up'
            },
            'licitacoes_recentes': {
                name: 'Licitações Recentes',
                description: 'Lista das últimas licitações criadas',
                icon: 'gavel'
            },
            'contratacoes_atrasadas': {
                name: 'Contratações Atrasadas',
                description: 'Alertas de contratações em atraso',
                icon: 'alert-triangle'
            },
            'importacoes_recentes': {
                name: 'Importações Recentes',
                description: 'Histórico de importações de PCA',
                icon: 'upload'
            },
            'riscos_altos': {
                name: 'Riscos Altos',
                description: 'Alertas de riscos com probabilidade alta',
                icon: 'shield-alert'
            }
        };

        // Carregar configuração atual
        function loadCurrentConfig() {
            const saved = localStorage.getItem('dashboard_config');
            return saved ? JSON.parse(saved) : defaultConfig;
        }

        // Salvar configuração
        function saveConfig(config) {
            localStorage.setItem('dashboard_config', JSON.stringify(config));
        }

        // Gerar widgets grid
        function generateWidgetsGrid() {
            const grid = document.getElementById('widgets-grid');
            const currentConfig = loadCurrentConfig();
            
            grid.innerHTML = '';
            
            Object.entries(availableWidgets).forEach(([key, widget]) => {
                const isActive = currentConfig.widgets[key] || false;
                
                const widgetElement = document.createElement('div');
                widgetElement.className = `widget-control ${isActive ? 'active' : ''}`;
                widgetElement.innerHTML = `
                    <div class="widget-header">
                        <div class="widget-title">
                            <i data-lucide="${widget.icon}"></i>
                            ${widget.name}
                        </div>
                        <div class="widget-toggle ${isActive ? 'active' : ''}" onclick="toggleWidget('${key}', this)"></div>
                    </div>
                    <p class="widget-description">${widget.description}</p>
                    <input type="checkbox" name="widget_${key}" ${isActive ? 'checked' : ''} style="display: none;">
                `;
                
                grid.appendChild(widgetElement);
            });
        }

        // Gerar preview
        function generatePreview() {
            const grid = document.getElementById('preview-grid');
            const currentConfig = loadCurrentConfig();
            
            grid.innerHTML = '';
            
            Object.entries(availableWidgets).forEach(([key, widget]) => {
                const isActive = currentConfig.widgets[key] || false;
                
                const previewElement = document.createElement('div');
                previewElement.className = `preview-widget ${currentConfig.densidade} ${!isActive ? 'disabled' : ''}`;
                previewElement.innerHTML = `
                    <i data-lucide="${widget.icon}"></i>
                    <div style="margin-top: 10px; font-weight: 600;">${widget.name}</div>
                `;
                
                grid.appendChild(previewElement);
            });
        }

        // Toggle widget
        function toggleWidget(widgetKey, toggleElement) {
            const widgetControl = toggleElement.closest('.widget-control');
            const checkbox = widgetControl.querySelector(`input[name="widget_${widgetKey}"]`);
            
            toggleElement.classList.toggle('active');
            widgetControl.classList.toggle('active');
            checkbox.checked = !checkbox.checked;
            
            generatePreview();
        }

        // Aplicar configuração atual nos campos
        function applyCurrentConfig() {
            const config = loadCurrentConfig();
            
            document.getElementById('densidade').value = config.densidade;
            document.getElementById('itens_por_pagina').value = config.itens_por_pagina;
            document.getElementById('ordenacao_padrao').value = config.ordenacao_padrao;
            document.getElementById('tema_grafico').value = config.tema_grafico;
            document.getElementById('refresh_automatico').checked = config.refresh_automatico;
            document.getElementById('tempo_refresh').value = config.tempo_refresh;
        }

        // Resetar configuração
        function resetConfig() {
            if (confirm('Tem certeza que deseja restaurar as configurações padrão?')) {
                saveConfig(defaultConfig);
                location.reload();
            }
        }

        // Submissão do formulário
        document.getElementById('dashboard-config-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const config = {
                widgets: {},
                densidade: formData.get('densidade'),
                itens_por_pagina: parseInt(formData.get('itens_por_pagina')),
                ordenacao_padrao: formData.get('ordenacao_padrao'),
                tema_grafico: formData.get('tema_grafico'),
                refresh_automatico: formData.has('refresh_automatico'),
                tempo_refresh: parseInt(formData.get('tempo_refresh'))
            };
            
            // Processar widgets
            Object.keys(availableWidgets).forEach(key => {
                config.widgets[key] = formData.has(`widget_${key}`);
            });
            
            // Salvar configuração
            saveConfig(config);
            
            // Mostrar feedback
            showNotification('Configurações salvas com sucesso!', 'success');
            
            // Atualizar preview
            generatePreview();
        });

        // Listeners para mudanças em tempo real
        document.getElementById('densidade').addEventListener('change', generatePreview);

        // Função de notificação
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#27ae60' : '#e74c3c'};
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                font-weight: 500;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            generateWidgetsGrid();
            generatePreview();
            applyCurrentConfig();
            
            // Inicializar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>