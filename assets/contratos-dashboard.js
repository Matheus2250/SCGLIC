/* ========================================
   CONTRATOS DASHBOARD - JAVASCRIPT
======================================== */

// Variáveis globais
let statusChart = null;
let performanceChart = null;

// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    console.log('Contratos Dashboard carregado');
    
    // Inicializar componentes
    initializeSidebar();
    initializeCharts();
    initializeLucideIcons();
    initializeModals();
    
    // Carregar dados dos gráficos
    loadChartsData();
});

// ========================================
// NAVEGAÇÃO E SIDEBAR
// ========================================

function initializeSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
    }
    
    // Auto-colapsar sidebar em telas pequenas
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
}

function showSection(sectionName) {
    // Esconder todas as seções
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(section => {
        section.classList.remove('active');
    });
    
    // Mostrar a seção selecionada
    const targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    // Atualizar navegação ativa
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.classList.remove('active');
    });
    
    // Encontrar e ativar o item de navegação correspondente
    const activeNavItem = document.querySelector(`[onclick="showSection('${sectionName}')"]`);
    if (activeNavItem) {
        activeNavItem.classList.add('active');
    }
    
    // Recarregar gráficos se necessário
    if (sectionName === 'dashboard') {
        setTimeout(() => {
            if (statusChart) statusChart.resize();
            if (performanceChart) performanceChart.resize();
        }, 100);
    }
}

// ========================================
// GRÁFICOS
// ========================================

function initializeCharts() {
    initializeStatusChart();
    initializePerformanceChart();
}

function initializeStatusChart(data = null) {
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;
    
    // Dados padrão se não fornecidos
    const chartData = data || {
        labels: ['Vigentes', 'Vencidos', 'Rescindidos'],
        datasets: [{
            data: [0, 0, 0],
            backgroundColor: [
                '#10b981',
                '#ef4444',
                '#f59e0b'
            ],
            borderWidth: 0
        }]
    };
    
    // Destruir gráfico existente se houver
    if (statusChart) {
        statusChart.destroy();
    }
    
    statusChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return `${context.label}: ${context.parsed} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function initializePerformanceChart(data = null) {
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;
    
    // Dados padrão se não fornecidos
    const chartData = data || {
        labels: [],
        datasets: [{
            label: 'Contratos Assinados',
            data: [],
            borderColor: '#7c3aed',
            backgroundColor: 'rgba(124, 58, 237, 0.1)',
            fill: true,
            tension: 0.4
        }]
    };
    
    // Destruir gráfico existente se houver
    if (performanceChart) {
        performanceChart.destroy();
    }
    
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6
                }
            }
        }
    });
}

// ========================================
// CARREGAMENTO DE DADOS
// ========================================

async function loadChartsData() {
    try {
        // Simular carregamento de dados - substituir por chamada real à API
        setTimeout(() => {
            // Dados simulados para o gráfico de status
            const statusData = {
                labels: ['Vigentes', 'Vencidos', 'Rescindidos'],
                datasets: [{
                    data: [0, 0, 0], // Dados zerados por enquanto
                    backgroundColor: [
                        '#10b981',
                        '#ef4444',
                        '#f59e0b'
                    ],
                    borderWidth: 0
                }]
            };
            
            // Dados simulados para o gráfico de performance
            const performanceData = {
                labels: [],
                datasets: [{
                    label: 'Contratos Assinados',
                    data: [],
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            };
            
            // Atualizar gráficos
            initializeStatusChart(statusData);
            initializePerformanceChart(performanceData);
            
        }, 1000);
        
    } catch (error) {
        console.error('Erro ao carregar dados dos gráficos:', error);
    }
}

// ========================================
// MODAIS
// ========================================

function initializeModals() {
    // Fechar modal ao clicar fora
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
}

function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        
        // Focar no primeiro input do modal
        const firstInput = modal.querySelector('input, select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function fecharModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        
        // Limpar formulário se existir
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
    }
}

// ========================================
// RELATÓRIOS
// ========================================

function gerarRelatorio(tipo) {
    // Mostrar loading
    showNotification('Gerando relatório...', 'info');
    
    // Simular geração de relatório
    setTimeout(() => {
        switch (tipo) {
            case 'geral':
                showNotification('Relatório geral gerado com sucesso!', 'success');
                break;
            case 'periodo':
                showNotification('Relatório por período gerado com sucesso!', 'success');
                break;
            case 'vencendo':
                showNotification('Relatório de contratos vencendo gerado com sucesso!', 'success');
                break;
            case 'financeiro':
                showNotification('Relatório financeiro gerado com sucesso!', 'success');
                break;
            default:
                showNotification('Tipo de relatório não reconhecido.', 'error');
        }
    }, 2000);
}

// ========================================
// UTILITÁRIOS
// ========================================

function initializeLucideIcons() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function showNotification(message, type = 'info') {
    // Função de notificação simples
    if (typeof showNotification !== 'undefined') {
        // Usar sistema de notificações se disponível
        window.showNotification(message, type);
    } else {
        // Fallback simples
        alert(message);
    }
}

// ========================================
// FORMULÁRIOS
// ========================================

function validarFormulario(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const campos = form.querySelectorAll('[required]');
    let valido = true;
    
    campos.forEach(campo => {
        if (!campo.value.trim()) {
            campo.classList.add('error');
            valido = false;
        } else {
            campo.classList.remove('error');
        }
    });
    
    return valido;
}

// ========================================
// BUSCA E FILTROS
// ========================================

function aplicarFiltros() {
    const form = document.querySelector('.filtros-form');
    if (form) {
        form.submit();
    }
}

function limparFiltros() {
    window.location.href = window.location.pathname;
}

// ========================================
// RESPONSIVIDADE
// ========================================

window.addEventListener('resize', function() {
    // Reajustar gráficos
    if (statusChart) {
        statusChart.resize();
    }
    if (performanceChart) {
        performanceChart.resize();
    }
    
    // Ajustar sidebar
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    } else if (window.innerWidth > 768) {
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('expanded');
    }
});

// ========================================
// EXPORT DE FUNÇÕES GLOBAIS
// ========================================

window.showSection = showSection;
window.abrirModal = abrirModal;
window.fecharModal = fecharModal;
window.gerarRelatorio = gerarRelatorio;
window.aplicarFiltros = aplicarFiltros;
window.limparFiltros = limparFiltros;