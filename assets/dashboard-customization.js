/**
 * Sistema de Personalização de Dashboard - Sistema CGLIC
 * 
 * CRIADO: 01/01/2025
 * FUNCIONALIDADES: Aplicar configurações personalizadas do usuário no dashboard
 */

class DashboardCustomization {
    constructor() {
        this.defaultConfig = {
            widgets: {
                'estatisticas_gerais': true,
                'grafico_areas': true,
                'grafico_situacao': true,
                'grafico_mensal': true,
                'licitacoes_recentes': true,
                'contratacoes_atrasadas': true,
                'importacoes_recentes': true,
                'riscos_altos': true
            },
            densidade: 'normal',
            itens_por_pagina: 10,
            refresh_automatico: false,
            tempo_refresh: 30,
            ordenacao_padrao: 'data_desc',
            tema_grafico: 'default'
        };
        
        this.config = this.loadConfig();
        this.refreshInterval = null;
        
        this.init();
    }
    
    init() {
        // Aplicar configurações quando a página carregar
        document.addEventListener('DOMContentLoaded', () => {
            this.applyAllConfigurations();
            this.setupAutoRefresh();
        });
    }
    
    loadConfig() {
        const saved = localStorage.getItem('dashboard_config');
        return saved ? { ...this.defaultConfig, ...JSON.parse(saved) } : this.defaultConfig;
    }
    
    saveConfig(config) {
        this.config = { ...this.defaultConfig, ...config };
        localStorage.setItem('dashboard_config', JSON.stringify(this.config));
    }
    
    applyAllConfigurations() {
        this.applyWidgetVisibility();
        this.applyDensity();
        this.applyItemsPerPage();
        this.applyDefaultOrdering();
        this.applyChartTheme();
    }
    
    // Aplicar visibilidade dos widgets
    applyWidgetVisibility() {
        const widgetMappings = {
            'estatisticas_gerais': '.stats-cards, .cards-grid',
            'grafico_areas': '#chart-areas, .chart-container[data-chart="areas"]',
            'grafico_situacao': '#chart-situacao, .chart-container[data-chart="situacao"]',
            'grafico_mensal': '#chart-mensal, .chart-container[data-chart="mensal"]',
            'licitacoes_recentes': '.licitacoes-recentes, #recent-licitacoes',
            'contratacoes_atrasadas': '.contratacoes-atrasadas, #atrasadas-widget',
            'importacoes_recentes': '.importacoes-recentes, #recent-imports',
            'riscos_altos': '.riscos-altos, #high-risks'
        };
        
        Object.entries(this.config.widgets).forEach(([widget, visible]) => {
            const selector = widgetMappings[widget];
            if (selector) {
                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    if (visible) {
                        element.style.display = '';
                        element.classList.remove('widget-hidden');
                    } else {
                        element.style.display = 'none';
                        element.classList.add('widget-hidden');
                    }
                });
            }
        });
    }
    
    // Aplicar densidade da interface
    applyDensity() {
        const body = document.body;
        
        // Remover classes de densidade existentes
        body.classList.remove('density-compact', 'density-normal', 'density-spacious');
        
        // Adicionar nova densidade
        body.classList.add(`density-${this.config.densidade}`);
        
        // Aplicar estilos específicos via CSS
        this.injectDensityStyles();
    }
    
    injectDensityStyles() {
        // Remover estilos existentes
        const existingStyle = document.getElementById('density-styles');
        if (existingStyle) {
            existingStyle.remove();
        }
        
        const style = document.createElement('style');
        style.id = 'density-styles';
        
        const densityRules = {
            compact: {
                cardPadding: '15px',
                cardMargin: '10px',
                fontSize: '14px',
                lineHeight: '1.4',
                tableRowHeight: '35px'
            },
            normal: {
                cardPadding: '20px',
                cardMargin: '15px',
                fontSize: '16px',
                lineHeight: '1.6',
                tableRowHeight: '45px'
            },
            spacious: {
                cardPadding: '30px',
                cardMargin: '25px',
                fontSize: '18px',
                lineHeight: '1.8',
                tableRowHeight: '55px'
            }
        };
        
        const rules = densityRules[this.config.densidade];
        
        style.textContent = `
            .density-${this.config.densidade} .card,
            .density-${this.config.densidade} .dashboard-card,
            .density-${this.config.densidade} .widget {
                padding: ${rules.cardPadding} !important;
                margin-bottom: ${rules.cardMargin} !important;
                font-size: ${rules.fontSize} !important;
                line-height: ${rules.lineHeight} !important;
            }
            
            .density-${this.config.densidade} table tr {
                height: ${rules.tableRowHeight} !important;
            }
            
            .density-${this.config.densidade} .form-group {
                margin-bottom: ${parseInt(rules.cardMargin) - 5}px !important;
            }
        `;
        
        document.head.appendChild(style);
    }
    
    // Aplicar itens por página
    applyItemsPerPage() {
        // Atualizar selects de paginação
        const paginationSelects = document.querySelectorAll('select[name="por_pagina"], #por_pagina');
        paginationSelects.forEach(select => {
            if (select && !select.value) {
                select.value = this.config.itens_por_pagina;
            }
        });
        
        // Salvar preferência para futuros carregamentos
        sessionStorage.setItem('preferred_per_page', this.config.itens_por_pagina);
    }
    
    // Aplicar ordenação padrão
    applyDefaultOrdering() {
        const orderingSelects = document.querySelectorAll('select[name="ordenacao"], #ordenacao');
        orderingSelects.forEach(select => {
            if (select && !select.value) {
                select.value = this.config.ordenacao_padrao;
            }
        });
        
        sessionStorage.setItem('preferred_ordering', this.config.ordenacao_padrao);
    }
    
    // Aplicar tema dos gráficos
    applyChartTheme() {
        const themes = {
            default: {
                backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14'],
                borderColor: '#ffffff',
                gridColor: 'rgba(0,0,0,0.1)'
            },
            dark: {
                backgroundColor: ['#495057', '#6c757d', '#adb5bd', '#343a40', '#212529', '#495057'],
                borderColor: '#ffffff',
                gridColor: 'rgba(255,255,255,0.1)'
            },
            colorful: {
                backgroundColor: ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#f0932b', '#eb4d4b'],
                borderColor: '#ffffff',
                gridColor: 'rgba(0,0,0,0.1)'
            },
            minimal: {
                backgroundColor: ['#6c757d', '#495057', '#343a40', '#212529', '#f8f9fa', '#e9ecef'],
                borderColor: '#ffffff',
                gridColor: 'rgba(0,0,0,0.05)'
            }
        };
        
        // Aplicar tema aos gráficos Chart.js existentes
        if (window.Chart && window.Chart.defaults) {
            const theme = themes[this.config.tema_grafico] || themes.default;
            
            // Configurar cores padrão
            window.Chart.defaults.color = document.documentElement.getAttribute('data-theme') === 'dark' ? '#ffffff' : '#000000';
            window.Chart.defaults.plugins.legend.labels.color = document.documentElement.getAttribute('data-theme') === 'dark' ? '#ffffff' : '#000000';
            
            // Salvar tema para novos gráficos
            window.dashboardChartTheme = theme;
        }
        
        // Recriar gráficos existentes com novo tema
        this.updateExistingCharts();
    }
    
    updateExistingCharts() {
        // Aguardar um pouco para garantir que os gráficos foram inicializados
        setTimeout(() => {
            if (window.Chart) {
                Chart.instances.forEach((chart, index) => {
                    if (chart && chart.config && chart.config.data && chart.config.data.datasets) {
                        const theme = window.dashboardChartTheme;
                        if (theme) {
                            chart.config.data.datasets.forEach((dataset, i) => {
                                if (theme.backgroundColor[i]) {
                                    dataset.backgroundColor = theme.backgroundColor[i];
                                    dataset.borderColor = theme.borderColor;
                                }
                            });
                            chart.update();
                        }
                    }
                });
            }
        }, 1000);
    }
    
    // Configurar auto-refresh
    setupAutoRefresh() {
        // Limpar interval existente
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        if (this.config.refresh_automatico) {
            this.refreshInterval = setInterval(() => {
                this.refreshDashboardData();
            }, this.config.tempo_refresh * 1000);
            
            // Adicionar indicador visual
            this.addRefreshIndicator();
        } else {
            this.removeRefreshIndicator();
        }
    }
    
    refreshDashboardData() {
        // Atualizar dados do dashboard via AJAX
        console.log('Atualizando dados do dashboard...');
        
        // Mostrar indicador de loading
        this.showRefreshIndicator();
        
        // Simular atualização (em implementação real, fazer requisições AJAX)
        setTimeout(() => {
            this.hideRefreshIndicator();
            
            // Recarregar gráficos se existirem
            if (typeof atualizarGraficos === 'function') {
                atualizarGraficos();
            }
            
            // Notificação discreta
            this.showRefreshNotification();
        }, 2000);
    }
    
    addRefreshIndicator() {
        if (document.querySelector('.auto-refresh-indicator')) return;
        
        const indicator = document.createElement('div');
        indicator.className = 'auto-refresh-indicator';
        indicator.innerHTML = `
            <i data-lucide="refresh-cw"></i>
            <span>Auto-refresh ativo</span>
            <small>A cada ${this.config.tempo_refresh}s</small>
        `;
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 10px 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-direction: column;
            text-align: center;
        `;
        
        document.body.appendChild(indicator);
        
        // Inicializar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    removeRefreshIndicator() {
        const indicator = document.querySelector('.auto-refresh-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    showRefreshIndicator() {
        const indicator = document.querySelector('.auto-refresh-indicator i');
        if (indicator) {
            indicator.style.animation = 'spin 1s linear infinite';
        }
    }
    
    hideRefreshIndicator() {
        const indicator = document.querySelector('.auto-refresh-indicator i');
        if (indicator) {
            indicator.style.animation = '';
        }
    }
    
    showRefreshNotification() {
        const notification = document.createElement('div');
        notification.textContent = 'Dashboard atualizado';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 14px;
            z-index: 10000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 2000);
    }
    
    // Método público para recarregar configurações
    reloadConfig() {
        this.config = this.loadConfig();
        this.applyAllConfigurations();
        this.setupAutoRefresh();
    }
    
    // Método público para obter configuração atual
    getConfig() {
        return { ...this.config };
    }
}

// CSS para animação de loading
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .widget-hidden {
        display: none !important;
    }
`;
document.head.appendChild(style);

// Inicializar sistema de personalização
window.dashboardCustomization = new DashboardCustomization();

// Função global para recarregar configurações (para uso em outras páginas)
window.reloadDashboardConfig = function() {
    if (window.dashboardCustomization) {
        window.dashboardCustomization.reloadConfig();
    }
};

// Função global para obter configurações (para uso em outras páginas)
window.getDashboardConfig = function() {
    if (window.dashboardCustomization) {
        return window.dashboardCustomization.getConfig();
    }
    return null;
};