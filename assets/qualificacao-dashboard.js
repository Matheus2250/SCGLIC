/**
 * Qualificação Dashboard JavaScript - Sistema CGLIC
 * Funcionalidades do painel de controle de qualificações
 * Baseado em licitacao-dashboard.js com adaptações para qualificação
 */

// ==================== TOGGLE SIDEBAR ====================

/**
 * Toggle da sidebar - abre/fecha a barra lateral
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleIcon = document.querySelector('#sidebarToggle i');
    
    if (sidebar && mainContent) {
        // Verificar se estamos em mobile
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            // Comportamento mobile - toggle da classe mobile-open
            sidebar.classList.toggle('mobile-open');
            
            // Alterar ícone
            if (sidebar.classList.contains('mobile-open')) {
                toggleIcon.setAttribute('data-lucide', 'x');
            } else {
                toggleIcon.setAttribute('data-lucide', 'menu');
            }
        } else {
            // Comportamento desktop - toggle da classe collapsed
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
            
            // Alterar ícone baseado no estado
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.setAttribute('data-lucide', 'panel-left-open');
            } else {
                toggleIcon.setAttribute('data-lucide', 'menu');
            }
            
            // Salvar estado no localStorage (apenas para desktop)
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
        
        // Reinicializar os ícones Lucide para atualizar o ícone alterado
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

/**
 * Restaurar estado da sidebar do localStorage
 */
function restoreSidebarState() {
    // Só restaurar estado se não estivermos em mobile
    const isMobile = window.innerWidth <= 768;
    
    if (!isMobile) {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleIcon = document.querySelector('#sidebarToggle i');
            
            if (sidebar && mainContent) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('collapsed');
                toggleIcon.setAttribute('data-lucide', 'panel-left-open');
                
                // Reinicializar os ícones Lucide
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }
    }
}

/**
 * Lidar com redimensionamento da janela
 */
function handleResize() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleIcon = document.querySelector('#sidebarToggle i');
    const isMobile = window.innerWidth <= 768;
    
    if (sidebar && mainContent && toggleIcon) {
        if (isMobile) {
            // Reset para comportamento mobile
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('collapsed');
            sidebar.classList.remove('mobile-open');
            toggleIcon.setAttribute('data-lucide', 'menu');
        } else {
            // Restaurar estado desktop
            sidebar.classList.remove('mobile-open');
            restoreSidebarState();
        }
        
        // Reinicializar os ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

// ==================== NAVEGAÇÃO ENTRE SEÇÕES ====================

/**
 * Mostrar seção específica e atualizar navegação
 */
function showSection(sectionId) {
    // Esconder todas as seções
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(section => {
        section.classList.remove('active');
    });
    
    // Mostrar seção específica
    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    // Atualizar navegação ativa
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.classList.remove('active');
    });
    
    // Ativar item de navegação correspondente
    const activeNavItem = document.querySelector(`[onclick*="${sectionId}"]`);
    if (activeNavItem) {
        activeNavItem.classList.add('active');
    }
    
    // Reinicializar componentes específicos da seção
    if (sectionId === 'dashboard') {
        initializeDashboardCharts();
    }
    
    // Salvar seção ativa
    localStorage.setItem('activeSection', sectionId);
    
    // Reinicializar os ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Restaurar seção ativa do localStorage
 */
function restoreActiveSection() {
    const activeSection = localStorage.getItem('activeSection') || 'dashboard';
    showSection(activeSection);
}

// ==================== FORMULÁRIOS E VALIDAÇÃO ====================

/**
 * Inicializar formulários com validação
 */
function initializeForms() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
        
        // Adicionar validação em tempo real
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', validateField);
            input.addEventListener('input', clearFieldError);
        });
    });
}

/**
 * Validar campo individual
 */
function validateField(event) {
    const field = event.target;
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';
    
    // Validação de campo obrigatório
    if (field.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'Este campo é obrigatório.';
    }
    
    // Validação de email
    if (field.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Digite um email válido.';
        }
    }
    
    // Validação de valores monetários
    if (field.classList.contains('currency') && value) {
        // Remover formatação para validar
        const cleanValue = value.replace(/[^\d.,]/g, '').replace(/\./g, '').replace(',', '.');
        const numericValue = parseFloat(cleanValue);
        
        if (isNaN(numericValue) || numericValue <= 0) {
            isValid = false;
            errorMessage = 'Digite um valor válido maior que zero.';
        }
    }
    
    // Mostrar/esconder erro
    if (!isValid) {
        showFieldError(field, errorMessage);
    } else {
        clearFieldError({ target: field });
    }
    
    return isValid;
}

/**
 * Mostrar erro em campo
 */
function showFieldError(field, message) {
    field.classList.add('error');
    
    // Remover erro anterior se existir
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Adicionar nova mensagem de erro
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#e74c3c';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '4px';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

/**
 * Limpar erro de campo
 */
function clearFieldError(event) {
    const field = event.target;
    field.classList.remove('error');
    
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

/**
 * Processar envio de formulário
 */
function handleFormSubmit(event) {
    const form = event.target;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isFormValid = true;
    
    // Validar todos os campos obrigatórios
    inputs.forEach(input => {
        if (!validateField({ target: input })) {
            isFormValid = false;
        }
    });
    
    if (!isFormValid) {
        event.preventDefault();
        showNotification('Por favor, corrija os erros antes de continuar.', 'error');
        return false;
    }
    
    return true;
}

// ==================== SISTEMA DE NOTIFICAÇÕES ====================

/**
 * Mostrar notificação
 */
function showNotification(message, type = 'info', duration = 5000) {
    // Remover notificações existentes
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => {
        notification.remove();
    });
    
    // Criar nova notificação
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInRight 0.3s ease-out;
    `;
    
    // Definir cor baseada no tipo
    switch (type) {
        case 'success':
            notification.style.background = 'linear-gradient(135deg, #27ae60, #2ecc71)';
            break;
        case 'error':
            notification.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
            break;
        case 'warning':
            notification.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
            break;
        default:
            notification.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Auto-remover após duração especificada
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, duration);
}

// ==================== GRÁFICOS E DASHBOARD ====================

/**
 * Inicializar gráficos do dashboard
 */
function initializeDashboardCharts() {
    // Aguardar um pouco para garantir que a seção está visível
    setTimeout(() => {
        loadChartsData();
    }, 100);
}

/**
 * Carregar dados dos gráficos via AJAX
 */
function loadChartsData() {
    fetch('process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=dashboard_stats_qualificacao'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            initializeStatusChart(data.data.status_chart);
            initializePerformanceChart(data.data.performance_chart);
        } else {
            console.error('Erro ao carregar dados dos gráficos:', data.message);
            // Em caso de erro, inicializar com dados zerados
            initializeStatusChart();
            initializePerformanceChart();
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        // Em caso de erro, inicializar com dados zerados
        initializeStatusChart();
        initializePerformanceChart();
    });
}


/**
 * Gráfico de status das qualificações
 */
function initializeStatusChart(chartData = null) {
    const ctx = document.getElementById('statusChart');
    if (!ctx || typeof Chart === 'undefined') return;
    
    // Destruir gráfico existente se houver
    if (window.statusChartInstance) {
        window.statusChartInstance.destroy();
    }
    
    // Usar dados passados ou valores padrão zerados
    const labels = chartData ? chartData.labels : ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'];
    const emAnalise = chartData ? chartData.em_analise : [0, 0, 0, 0, 0, 0];
    const concluido = chartData ? chartData.concluido : [0, 0, 0, 0, 0, 0];
    
    window.statusChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Em Análise',
                    data: emAnalise,
                    backgroundColor: '#f59e0b',
                    borderRadius: 4,
                    borderSkipped: false,
                },
                {
                    label: 'Concluído',
                    data: concluido,
                    backgroundColor: '#27ae60',
                    borderRadius: 4,
                    borderSkipped: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            family: "'Inter', 'Segoe UI', Roboto, sans-serif"
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/**
 * Gráfico de performance mensal
 */
function initializePerformanceChart(chartData = null) {
    const ctx = document.getElementById('performanceChart');
    if (!ctx || typeof Chart === 'undefined') return;
    
    // Destruir gráfico existente se houver
    if (window.performanceChartInstance) {
        window.performanceChartInstance.destroy();
    }
    
    // Usar dados passados ou valores padrão zerados
    const labels = chartData ? chartData.labels : ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    const dados = chartData ? chartData.dados : [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    
    window.performanceChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Taxa de Aprovação (%)',
                data: dados,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            family: "'Inter', 'Segoe UI', Roboto, sans-serif"
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

// ==================== PROCESSAMENTO DE FORMULÁRIOS ====================

// Função removida - usando pattern simples igual às licitações

// ==================== UTILITÁRIOS ====================

/**
 * Formatar valor como moeda
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

/**
 * Formatar data para exibição
 */
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

/**
 * Debounce para otimizar chamadas de função
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Copiar texto para clipboard
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showNotification('Texto copiado para a área de transferência!', 'success');
    } catch (err) {
        // Fallback para navegadores mais antigos
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showNotification('Texto copiado para a área de transferência!', 'success');
        } catch (fallbackErr) {
            showNotification('Não foi possível copiar o texto.', 'error');
        }
        document.body.removeChild(textArea);
    }
}

// ==================== INICIALIZAÇÃO ====================

/**
 * Inicializar todas as funcionalidades quando o DOM estiver pronto
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Qualificação Dashboard - Inicializando...');
    
    // Inicializar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Configurar event listeners
    setupEventListeners();
    
    // Event listener para o formulário de criação no modal (IGUAL LICITAÇÕES)
    const formCriarQualificacao = document.querySelector('#modalCriarQualificacao form');
    if (formCriarQualificacao) {
        formCriarQualificacao.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            // Converter valor monetário antes de enviar (IGUAL LICITAÇÕES)
            const valorEstimado = formData.get('valor_estimado');
            if (valorEstimado) {
                let cleanValue = valorEstimado.toString().trim();
                // Se tem vírgula, assumir que é separador decimal brasileiro
                if (cleanValue.includes(',')) {
                    // Remover pontos (separadores de milhares) e trocar vírgula por ponto
                    cleanValue = cleanValue.replace(/\./g, '').replace(',', '.');
                }
                // Se não tem vírgula mas tem pontos, verificar se é separador decimal ou milhares
                else if (cleanValue.includes('.')) {
                    const parts = cleanValue.split('.');
                    if (parts.length === 2 && parts[1].length <= 2) {
                        // Último ponto com 1-2 dígitos = decimal
                        cleanValue = cleanValue;
                    } else {
                        // Múltiplos pontos ou último com 3+ dígitos = separadores de milhares
                        cleanValue = cleanValue.replace(/\./g, '');
                    }
                }
                formData.set('valor_estimado', cleanValue);
            }

            // Mostrar loading (IGUAL LICITAÇÕES)
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Criando...';
            submitBtn.disabled = true;

            fetch('process.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Qualificação criada com sucesso!');
                        fecharModal('modalCriarQualificacao');
                        this.reset();
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + (data.message || 'Erro ao criar qualificação'));
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('❌ Erro de conexão');
                })
                .finally(() => {
                    // Restaurar botão (IGUAL LICITAÇÕES)
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    // Reinicializar ícones
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
        });
    }
    
    // Event listener para formulário de relatórios (IGUAL LICITAÇÕES)
    const formRelatorioQualificacao = document.getElementById('formRelatorioQualificacao');
    if (formRelatorioQualificacao) {
        formRelatorioQualificacao.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const params = new URLSearchParams();

            for (const [key, value] of formData) {
                if (value) params.append(key, value);
            }

            const formato = formData.get('formato');
            const url = 'relatorios/gerar_relatorio_qualificacao.php?' + params.toString();

            if (formato === 'html') {
                // Abrir em nova aba
                window.open(url, '_blank');
            } else {
                // Download direto
                window.location.href = url;
            }

            // Fechar modal
            fecharModal('modalRelatorioQualificacao');
        });
    }
    
    // Restaurar estados salvos
    restoreSidebarState();
    restoreActiveSection();
    
    // Só restaurar preferência se estivermos na página de qualificações
    if (document.getElementById('btn-lista-qualificacoes') && document.getElementById('btn-cards-qualificacoes')) {
        restoreQualificacaoViewPreference();
    }
    
    // Inicializar formulários
    initializeForms();
    
    // Configurar resize handler com debounce
    const debouncedResize = debounce(handleResize, 250);
    window.addEventListener('resize', debouncedResize);
    
    console.log('✅ Qualificação Dashboard - Inicialização concluída!');
});

/**
 * Configurar event listeners principais
 */
function setupEventListeners() {
    // Toggle sidebar
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Configurar filtros automáticos
    setupFiltrosAutomaticos();
    
    // Fechar sidebar ao clicar fora (apenas mobile)
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile && sidebar && 
            sidebar.classList.contains('mobile-open') && 
            !sidebar.contains(event.target) && 
            !sidebarToggle.contains(event.target)) {
            
            sidebar.classList.remove('mobile-open');
            const toggleIcon = sidebarToggle.querySelector('i');
            if (toggleIcon) {
                toggleIcon.setAttribute('data-lucide', 'menu');
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }
    });
    
    // Configurar máscaras para campos de entrada
    setupInputMasks();
    
    // Configurar botões de ação
    setupActionButtons();
}

/**
 * Configurar máscaras de entrada
 */
function setupInputMasks() {
    // Máscara para NUP (igual à licitação)
    const nupInput = document.querySelector('input[name="nup"]');
    if (nupInput) {
        nupInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = value.substring(0, 17);
                let formatted = '';
                
                if (value.length > 0) {
                    formatted = value.substring(0, 5);
                }
                if (value.length > 5) {
                    formatted += '.' + value.substring(5, 11);
                }
                if (value.length > 11) {
                    formatted += '/' + value.substring(11, 15);
                }
                if (value.length > 15) {
                    formatted += '-' + value.substring(15, 17);
                }
                
                e.target.value = formatted;
            }
        });
    }
    
    // Máscara para valores monetários
    const currencyInputs = document.querySelectorAll('.currency');
    currencyInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                // Converter para centavos e depois para reais
                let numericValue = parseInt(value);
                let formattedValue = (numericValue / 100).toFixed(2);
                
                // Adicionar separador de milhares
                let parts = formattedValue.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                
                // Usar vírgula como separador decimal
                e.target.value = parts.join(',');
            } else {
                e.target.value = '';
            }
        });
        
        input.addEventListener('blur', function(e) {
            if (e.target.value) {
                // Limpar formatação e converter
                let cleanValue = e.target.value.replace(/\./g, '').replace(',', '.');
                let numericValue = parseFloat(cleanValue);
                
                if (!isNaN(numericValue) && numericValue > 0) {
                    // Formatar como moeda brasileira
                    e.target.value = new Intl.NumberFormat('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(numericValue);
                }
            }
        });
        
        // Permitir apenas números, vírgula e ponto
        input.addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.which);
            if (!/[\d.,]/.test(char)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Configurar botões de ação
 */
function setupActionButtons() {
    // Botões de confirmação
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Botões de loading
    const loadingButtons = document.querySelectorAll('[data-loading]');
    loadingButtons.forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.textContent;
            const loadingText = this.getAttribute('data-loading') || 'Carregando...';
            
            this.textContent = loadingText;
            this.disabled = true;
            
            // Restaurar após 5 segundos (fallback)
            setTimeout(() => {
                this.textContent = originalText;
                this.disabled = false;
            }, 5000);
        });
    });
}

// ==================== SISTEMA DE FILTROS AUTOMÁTICOS ====================

/**
 * Configurar filtros automáticos
 */
function setupFiltrosAutomaticos() {
    const formFiltro = document.getElementById('filtroQualificacoes');
    if (!formFiltro) return;
    
    // Auto-submit ao alterar selects
    const selects = formFiltro.querySelectorAll('select');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            formFiltro.submit();
        });
    });
    
    // Debounce para campo de busca
    const campoBusca = document.getElementById('busca');
    if (campoBusca) {
        let timeoutId;
        campoBusca.addEventListener('input', function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                formFiltro.submit();
            }, 800); // 800ms de delay
        });
    }
}

// ==================== FUNÇÕES DE AÇÕES DA TABELA ====================

/**
 * Visualizar qualificação
 */
function visualizarQualificacao(id) {
    // Buscar dados via AJAX
    fetch('process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=buscar_qualificacao&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const qual = data.data;
            
            // Preencher modal de visualização
            const modal = document.getElementById('modalVisualizacao');
            if (!modal) {
                // Se modal não existe, criar uma única vez
                criarModalVisualizacao();
            }
            
            // Preencher dados no modal
            preencherModalVisualizacao(qual);
            
            // Mostrar modal (igual ao padrão licitações)
            const modalElement = document.getElementById('modalVisualizacao');
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            
            // Inicializar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        } else {
            showNotification(data.message || 'Erro ao buscar qualificação', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
    });
}

/**
 * Editar qualificação
 */
function editarQualificacao(id) {
    // Buscar dados da qualificação via AJAX
    fetch('process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=buscar_qualificacao&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const qual = data.data;
            
            // Verificar se modal existe, senão criar
            const modal = document.getElementById('modalEdicao');
            if (!modal) {
                criarModalEdicao();
            }
            
            // Preencher dados no modal
            preencherModalEdicao(qual);
            
            // Mostrar modal (igual ao padrão licitações)
            const modalElement = document.getElementById('modalEdicao');
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            
            // Inicializar ícones Lucide e máscaras
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            // Configurar máscara para valor monetário
            const currencyInput = document.querySelector('#modalEdicao .currency');
            if (currencyInput) {
                setupCurrencyMask(currencyInput);
            }
        } else {
            showNotification(data.message || 'Erro ao buscar qualificação', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
    });
}

/**
 * Criar modal de edição (uma única vez)
 */
function criarModalEdicao() {
    const modalHtml = `
        <div id="modalEdicao" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i data-lucide="edit"></i> Editar Qualificação</h3>
                    <span class="close" onclick="fecharModal('modalEdicao')">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="form-editar-qualificacao" class="form-grid">
                        <input type="hidden" id="edit_id" name="id">
                        <input type="hidden" name="acao" value="editar_qualificacao">
                        
                        <div class="form-group">
                            <label>NUP (Número Único de Protocolo) *</label>
                            <input type="text" id="edit_nup" name="nup" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Área Demandante *</label>
                            <input type="text" id="edit_area_demandante" name="area_demandante" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Responsável *</label>
                            <input type="text" id="edit_responsavel" name="responsavel" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Modalidade *</label>
                            <select id="edit_modalidade" name="modalidade" required>
                                <option value="">Selecione a modalidade</option>
                                <option value="PREGÃO">PREGÃO</option>
                                <option value="CONCURSO">CONCURSO</option>
                                <option value="CONCORRÊNCIA">CONCORRÊNCIA</option>
                                <option value="INEXIGIBILIDADE">INEXIGIBILIDADE</option>
                                <option value="DISPENSA">DISPENSA</option>
                                <option value="PREGÃO SRP">PREGÃO SRP</option>
                                <option value="ADESÃO">ADESÃO</option>
                            </select>
                        </div>
                        
                        <div class="form-group form-full">
                            <label>Objeto *</label>
                            <textarea id="edit_objeto" name="objeto" required rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Palavras-Chave</label>
                            <input type="text" id="edit_palavras_chave" name="palavras_chave">
                        </div>
                        
                        <div class="form-group">
                            <label>Valor Estimado (R$) *</label>
                            <input type="text" id="edit_valor_estimado" name="valor_estimado" class="currency" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Status *</label>
                            <select id="edit_status" name="status" required>
                                <option value="">Selecione o status</option>
                                <option value="EM ANÁLISE">EM ANÁLISE</option>
                                <option value="CONCLUÍDO">CONCLUÍDO</option>
                            </select>
                        </div>
                        
                        <div class="form-group form-full">
                            <label>Observações</label>
                            <textarea id="edit_observacoes" name="observacoes" rows="4"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="display: flex; gap: 15px; justify-content: flex-end; padding: 20px; border-top: 1px solid #e5e7eb;">
                    <button type="button" class="btn-secondary" onclick="fecharModal('modalEdicao')" style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="button" class="btn-primary" onclick="salvarEdicao()" style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="save"></i> Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

/**
 * Preencher dados no modal de edição
 */
function preencherModalEdicao(qualificacao) {
    document.getElementById('edit_id').value = qualificacao.id;
    document.getElementById('edit_nup').value = qualificacao.nup;
    document.getElementById('edit_area_demandante').value = qualificacao.area_demandante;
    document.getElementById('edit_responsavel').value = qualificacao.responsavel;
    document.getElementById('edit_modalidade').value = qualificacao.modalidade;
    document.getElementById('edit_objeto').value = qualificacao.objeto;
    document.getElementById('edit_palavras_chave').value = qualificacao.palavras_chave || '';
    document.getElementById('edit_valor_estimado').value = parseFloat(qualificacao.valor_estimado).toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('edit_status').value = qualificacao.status;
    document.getElementById('edit_observacoes').value = qualificacao.observacoes || '';
}

/**
 * Salvar edição da qualificação
 */
function salvarEdicao() {
    const form = document.getElementById('form-editar-qualificacao');
    const formData = new FormData(form);
    
    // Validar campos obrigatórios
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = '#e74c3c';
        } else {
            field.style.borderColor = '';
        }
    });
    
    if (!isValid) {
        showNotification('Por favor, preencha todos os campos obrigatórios', 'error');
        return;
    }
    
    // Mostrar loading
    const saveBtn = document.querySelector('#modalEdicao .btn-primary');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Salvando...';
    saveBtn.disabled = true;
    
    // Enviar dados
    fetch('process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Qualificação atualizada com sucesso!', 'success');
            fecharModal('modalEdicao');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Erro ao atualizar qualificação', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
    })
    .finally(() => {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
}

/**
 * Configurar máscara de moeda para input
 */
function setupCurrencyMask(input) {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 0) {
            let numericValue = parseInt(value);
            let formattedValue = (numericValue / 100).toFixed(2);
            let parts = formattedValue.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            e.target.value = parts.join(',');
        } else {
            e.target.value = '';
        }
    });
}

/**
 * Excluir qualificação
 */
function excluirQualificacao(id) {
    // Confirmação dupla para segurança
    const confirmacao1 = confirm('⚠️ ATENÇÃO: Você tem certeza que deseja EXCLUIR esta qualificação?\\n\\nEsta ação NÃO pode ser desfeita!');
    
    if (!confirmacao1) {
        return;
    }
    
    const confirmacao2 = confirm('🚨 CONFIRMAÇÃO FINAL: Excluir definitivamente a qualificação?');
    
    if (!confirmacao2) {
        return;
    }
    
    // Enviar requisição de exclusão
    fetch('process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=excluir_qualificacao&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Qualificação excluída com sucesso!', 'success');
            // Recarregar a página após 1 segundo
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Erro ao excluir qualificação', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
    });
}

/**
 * Criar modal de visualização (uma única vez)
 */
function criarModalVisualizacao() {
    const modalHtml = `
        <div id="modalVisualizacao" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i data-lucide="eye"></i> Detalhes da Qualificação</h3>
                    <span class="close" onclick="fecharModal('modalVisualizacao')">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="form-grid" id="dadosVisualizacao">
                        <!-- Conteúdo será preenchido dinamicamente -->
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; gap: 15px; justify-content: flex-end; padding: 20px; border-top: 1px solid #e5e7eb;">
                    <button type="button" class="btn-secondary" onclick="fecharModal('modalVisualizacao')" style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="x"></i> Fechar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

/**
 * Preencher dados no modal de visualização
 */
function preencherModalVisualizacao(qual) {
    const container = document.getElementById('dadosVisualizacao');
    if (container) {
        container.innerHTML = `
            <div class="form-group">
                <label><strong>NUP:</strong></label>
                <p>${qual.nup}</p>
            </div>
            <div class="form-group">
                <label><strong>Área Demandante:</strong></label>
                <p>${qual.area_demandante}</p>
            </div>
            <div class="form-group">
                <label><strong>Responsável:</strong></label>
                <p>${qual.responsavel}</p>
            </div>
            <div class="form-group">
                <label><strong>Modalidade:</strong></label>
                <p>${qual.modalidade}</p>
            </div>
            <div class="form-group">
                <label><strong>Status:</strong></label>
                <p><span class="status-badge status-${qual.status.toLowerCase().replace(' ', '-')}">${qual.status}</span></p>
            </div>
            <div class="form-group">
                <label><strong>Valor Estimado:</strong></label>
                <p>R$ ${parseFloat(qual.valor_estimado).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
            </div>
            ${qual.numero_contratacao ? `
            <div class="form-group">
                <label><strong>Contratação PCA:</strong></label>
                <p style="color: #27ae60; font-weight: 600;">
                    <i data-lucide="link" style="width: 16px; height: 16px; margin-right: 8px;"></i>
                    ${qual.numero_contratacao}
                </p>
            </div>
            ` : `
            <div class="form-group">
                <label><strong>Contratação PCA:</strong></label>
                <p style="color: #95a5a6; font-style: italic;">Não vinculado ao PCA</p>
            </div>
            `}
            <div class="form-group form-full">
                <label><strong>Objeto:</strong></label>
                <p>${qual.objeto}</p>
            </div>
            <div class="form-group form-full">
                <label><strong>Palavras-chave:</strong></label>
                <p>${qual.palavras_chave || 'Nenhuma'}</p>
            </div>
            <div class="form-group form-full">
                <label><strong>Observações:</strong></label>
                <p>${qual.observacoes || 'Nenhuma observação'}</p>
            </div>
            <div class="form-group">
                <label><strong>Criado em:</strong></label>
                <p>${new Date(qual.criado_em).toLocaleString('pt-BR')}</p>
            </div>
            <div class="form-group">
                <label><strong>Atualizado em:</strong></label>
                <p>${new Date(qual.atualizado_em).toLocaleString('pt-BR')}</p>
            </div>
        `;
    }
}

/**
 * Abrir modal (IGUAL LICITAÇÕES)
 */
function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.warn('Modal não encontrado:', modalId);
        return;
    }
    
    // Limpar formulário se existir
    const form = modal.querySelector('form');
    if (form) {
        form.reset();
    }
    
    // Mostrar modal
    modal.style.display = 'block';
    modal.classList.add('show');
    
    // Inicializar sistema de abas para qualificação
    if (modalId === 'modalCriarQualificacao') {
        console.log('Inicializando sistema de abas para qualificação');
        setTimeout(() => {
            mostrarAbaQualificacao('informacoes-gerais');
        }, 50);
    }
    
    // Reinicializar ícones
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Fechar modal (IGUAL LICITAÇÕES)
 */
function fecharModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.warn('Modal não encontrado:', modalId);
        return;
    }

    // Remover classe show e forçar display none
    modal.classList.remove('show');
    modal.style.display = 'none';
}

// ==================== SISTEMA DE RELATÓRIOS ====================

/**
 * Gerar relatório de qualificação (IGUAL LICITAÇÕES)
 */
function gerarRelatorioQualificacao(tipo) {
    // Definir títulos por tipo
    const titulos = {
        'status': 'Relatório por Status',
        'modalidade': 'Relatório por Modalidade', 
        'area': 'Relatório por Área Demandante',
        'financeiro': 'Relatório Financeiro'
    };
    
    // Configurar modal
    document.getElementById('tipo_relatorio_qualificacao').value = tipo;
    document.getElementById('tituloRelatorioQualificacao').textContent = titulos[tipo] || 'Configurar Relatório';
    
    // Abrir modal
    abrirModal('modalRelatorioQualificacao');
}

// ==================== EXPORTAR FUNÇÕES GLOBAIS ====================

// Disponibilizar funções principais globalmente
window.QualificacaoDashboard = {
    toggleSidebar,
    showSection,
    showNotification,
    formatCurrency,
    formatDate,
    copyToClipboard,
    initializeDashboardCharts
};

// Disponibilizar funções de ação globalmente
window.visualizarQualificacao = visualizarQualificacao;
window.editarQualificacao = editarQualificacao;
window.excluirQualificacao = excluirQualificacao;
window.abrirModal = abrirModal;
window.fecharModal = fecharModal;
window.salvarEdicao = salvarEdicao;

// Disponibilizar funções de navegação diretamente (para compatibilidade com onclick)
window.showSection = showSection;
window.toggleSidebar = toggleSidebar;
window.showNotification = showNotification;
window.gerarRelatorioQualificacao = gerarRelatorioQualificacao;

// ==================== SISTEMA DE ABAS PARA MODAL DE QUALIFICAÇÃO ====================

// Sistema de abas para o modal de qualificação
let abaAtualQualificacao = 0;
const abasQualificacao = ['informacoes-gerais', 'detalhes-objeto', 'valores-observacoes'];

function mostrarAbaQualificacao(nomeAba) {
    console.log('Mostrando aba:', nomeAba);
    
    // Ocultar todas as abas
    document.querySelectorAll('#modalCriarQualificacao .tab-content').forEach(aba => {
        aba.classList.remove('active');
    });
    
    // Remover classe active de todos os botões
    document.querySelectorAll('#modalCriarQualificacao .tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar aba selecionada
    const abaElement = document.getElementById('aba-' + nomeAba);
    if (abaElement) {
        abaElement.classList.add('active');
    }
    
    // Adicionar classe active ao botão correspondente
    const btnElement = document.querySelector(`#modalCriarQualificacao .tab-button[onclick*="${nomeAba}"]`);
    if (btnElement) {
        btnElement.classList.add('active');
    }
    
    // Atualizar índice da aba atual
    abaAtualQualificacao = abasQualificacao.indexOf(nomeAba);
    
    // Controlar visibilidade dos botões de navegação
    atualizarBotoesNavegacaoQualificacao();
    
    // Recriar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function proximaAbaQualificacao() {
    if (abaAtualQualificacao < abasQualificacao.length - 1) {
        abaAtualQualificacao++;
        mostrarAbaQualificacao(abasQualificacao[abaAtualQualificacao]);
    }
}

function abaAnteriorQualificacao() {
    if (abaAtualQualificacao > 0) {
        abaAtualQualificacao--;
        mostrarAbaQualificacao(abasQualificacao[abaAtualQualificacao]);
    }
}

function atualizarBotoesNavegacaoQualificacao() {
    const btnAnterior = document.getElementById('btn-anterior-qualificacao');
    const btnProximo = document.getElementById('btn-proximo-qualificacao');
    const btnCriar = document.getElementById('btn-criar-qualificacao');
    
    if (btnAnterior) {
        btnAnterior.style.display = abaAtualQualificacao > 0 ? 'inline-flex' : 'none';
    }
    
    if (btnProximo) {
        btnProximo.style.display = abaAtualQualificacao < abasQualificacao.length - 1 ? 'inline-flex' : 'none';
    }
    
    if (btnCriar) {
        btnCriar.style.display = abaAtualQualificacao === abasQualificacao.length - 1 ? 'inline-flex' : 'none';
    }
}

function resetarFormularioQualificacao() {
    document.getElementById('formCriarQualificacao').reset();
    mostrarAbaQualificacao('informacoes-gerais');
}

// ==================== SISTEMA DE TOGGLE LISTA/CARDS ====================

/**
 * Toggle entre visualização Lista e Cards para qualificações
 */
function toggleQualificacaoView(viewType) {
    console.log('toggleQualificacaoView chamado com:', viewType);
    
    const tableView = document.querySelector('.table-qualificacoes-view');
    const cardsView = document.querySelector('.cards-qualificacoes-view');
    const btnLista = document.getElementById('btn-lista-qualificacoes');
    const btnCards = document.getElementById('btn-cards-qualificacoes');
    
    console.log('Elementos encontrados:', {
        tableView: !!tableView,
        cardsView: !!cardsView,
        btnLista: !!btnLista,
        btnCards: !!btnCards
    });
    
    if (!tableView || !cardsView) {
        console.error('Views não encontradas. Tentando criar estrutura...');
        // Se não existirem as views, pode ser que a página ainda não carregou completamente
        setTimeout(() => toggleQualificacaoView(viewType), 500);
        return;
    }
    
    if (!btnLista || !btnCards) {
        console.warn('Botões não encontrados, mas continuando com o toggle das views');
    }
    
    if (viewType === 'cards') {
        // Mostrar cards, esconder tabela
        tableView.style.display = 'none';
        cardsView.style.display = 'block';
        
        // Atualizar botões se existirem
        if (btnLista) btnLista.classList.remove('active');
        if (btnCards) btnCards.classList.add('active');
        
        // Salvar preferência
        localStorage.setItem('qualificacaoViewPreference', 'cards');
        console.log('Modo cards ativado');
    } else {
        // Mostrar tabela, esconder cards
        tableView.style.display = 'block';
        cardsView.style.display = 'none';
        
        // Atualizar botões se existirem
        if (btnLista) btnLista.classList.add('active');
        if (btnCards) btnCards.classList.remove('active');
        
        // Salvar preferência
        localStorage.setItem('qualificacaoViewPreference', 'lista');
        console.log('Modo lista ativado');
    }
    
    // Reinicializar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Restaurar preferência de visualização salva
 */
function restoreQualificacaoViewPreference() {
    const savedPreference = localStorage.getItem('qualificacaoViewPreference') || 'lista';
    console.log('Restaurando preferência de visualização:', savedPreference);
    
    // Aguardar um pouco para garantir que o DOM foi totalmente carregado
    setTimeout(() => {
        toggleQualificacaoView(savedPreference);
    }, 100);
}

// ==================== VINCULAÇÃO COM PCA ====================

/**
 * Abrir modal de vinculação com o PCA
 */
function abrirVinculacaoPCA(qualificacaoId) {
    // Criar modal dinamicamente se não existir
    if (!document.getElementById('modalVinculacaoPCA')) {
        criarModalVinculacaoPCA();
    }
    
    // Configurar modal
    document.getElementById('qualificacao_id_vinculacao').value = qualificacaoId;
    
    // Mostrar modal
    const modal = document.getElementById('modalVinculacaoPCA');
    modal.style.display = 'block';
    modal.classList.add('show');
    
    // Reinicializar ícones
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Criar modal de vinculação com PCA - SIMPLIFICADO
 */
function criarModalVinculacaoPCA() {
    const modalHTML = `
        <div id="modalVinculacaoPCA" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="link"></i> Vincular com PCA
                    </h3>
                    <span class="close" onclick="fecharModal('modalVinculacaoPCA')">&times;</span>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom: 20px; color: #6c757d;">Selecione uma contratação do PCA para vincular com esta qualificação:</p>
                    
                    <form id="formVinculacaoPCA" action="process.php" method="POST">
                        <input type="hidden" name="acao" value="vincular_qualificacao_pca">
                        <input type="hidden" id="qualificacao_id_vinculacao" name="qualificacao_id">
                        
                        <div class="form-group">
                            <label>Contratação Selecionada:</label>
                            <input type="text" name="numero_contratacao" readonly placeholder="Clique no botão abaixo para selecionar..." style="background-color: #f8f9fa; border: 2px dashed #dee2e6;">
                        </div>
                        
                        <div class="form-group">
                            <button type="button" onclick="abrirSeletorContratacaoModal()" class="btn-secondary" style="width: 100%; padding: 12px;">
                                <i data-lucide="list"></i> Selecionar Contratação do PCA
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="display: flex; gap: 15px; justify-content: flex-end; padding: 20px; border-top: 1px solid #e5e7eb;">
                    <button type="button" class="btn-secondary" onclick="fecharModal('modalVinculacaoPCA')">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="submit" form="formVinculacaoPCA" class="btn-primary">
                        <i data-lucide="link"></i> Vincular
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Configurar busca em tempo real
    const campoBusca = document.getElementById('busca_contratacao');
    if (campoBusca) {
        let timeoutBusca;
        campoBusca.addEventListener('input', function() {
            clearTimeout(timeoutBusca);
            const termo = this.value.trim();
            
            if (termo.length >= 3) {
                timeoutBusca = setTimeout(() => buscarContratacoesPCA(termo), 500);
            } else {
                document.getElementById('resultados_busca').style.display = 'none';
            }
        });
    }
    
    // Configurar evento de submit do formulário
    const form = document.getElementById('formVinculacaoPCA');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.querySelector('#modalVinculacaoPCA .btn-primary');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Vincular';
            
            // Mostrar loading
            if (submitBtn) {
                submitBtn.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Vinculando...';
                submitBtn.disabled = true;
            }
            
            fetch('process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Qualificação vinculada com sucesso!', 'success');
                    fecharModal('modalVinculacaoPCA');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Erro ao vincular qualificação', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showNotification('Erro de conexão', 'error');
            })
            .finally(() => {
                // Restaurar botão
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
                // Reinicializar ícones
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        });
    }
}

/**
 * Buscar contratações do PCA
 */
function buscarContratacoesPCA(termo) {
    fetch('process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=buscar_contratacoes_pca&termo=' + encodeURIComponent(termo)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.contratacoes) {
            mostrarResultadosBusca(data.contratacoes);
        } else {
            document.getElementById('resultados_busca').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Erro na busca:', error);
        document.getElementById('resultados_busca').style.display = 'none';
    });
}

/**
 * Mostrar resultados da busca
 */
function mostrarResultadosBusca(contratacoes) {
    const container = document.getElementById('resultados_busca');
    if (!container) return;
    
    let html = '';
    contratacoes.forEach(contratacao => {
        html += `
            <div class="resultado-item" onclick="selecionarContratacao('${contratacao.numero_dfd}', '${contratacao.titulo_contratacao}')">
                <strong>${contratacao.numero_dfd}</strong>
                <span>${contratacao.titulo_contratacao}</span>
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.style.display = contratacoes.length > 0 ? 'block' : 'none';
}

/**
 * Selecionar contratação da busca
 */
function selecionarContratacao(numeroDfd, tituloContratacao) {
    document.querySelector('input[name="numero_dfd"]').value = numeroDfd;
    document.querySelector('input[name="numero_contratacao"]').value = tituloContratacao;
    document.getElementById('resultados_busca').style.display = 'none';
    document.getElementById('busca_contratacao').value = `${numeroDfd} - ${tituloContratacao}`;
}

// Disponibilizar funções das abas globalmente
window.mostrarAbaQualificacao = mostrarAbaQualificacao;
window.proximaAbaQualificacao = proximaAbaQualificacao;
window.abaAnteriorQualificacao = abaAnteriorQualificacao;
window.resetarFormularioQualificacao = resetarFormularioQualificacao;

// ==================== SELETOR SIMPLES DE CONTRATAÇÃO ====================

/**
 * Abrir seletor de contratação (formulário)
 */
function abrirSeletorContratacao() {
    if (!document.getElementById('modalSeletorContratacao')) {
        criarModalSeletorContratacao();
    }
    
    document.getElementById('modalSeletorContratacao').style.display = 'block';
    carregarContratacoesPCA();
}

/**
 * Abrir seletor de contratação (modal vinculação)
 */
function abrirSeletorContratacaoModal() {
    if (!document.getElementById('modalSeletorContratacao')) {
        criarModalSeletorContratacao();
    }
    
    // Marcar que é para o modal de vinculação
    document.getElementById('modalSeletorContratacao').setAttribute('data-target', 'modal');
    document.getElementById('modalSeletorContratacao').style.display = 'block';
    carregarContratacoesPCA();
}

/**
 * Criar modal seletor simples
 */
function criarModalSeletorContratacao() {
    const modalHTML = `
        <div id="modalSeletorContratacao" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 800px;">
                <div class="modal-header">
                    <h3><i data-lucide="list"></i> Selecionar Contratação do PCA</h3>
                    <span class="close" onclick="fecharModal('modalSeletorContratacao')">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="loading-contratacoes" style="text-align: center; padding: 20px;">
                        <i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i>
                        <p>Carregando contratações...</p>
                    </div>
                    <div id="lista-contratacoes" style="display: none;">
                        <!-- Lista será preenchida via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * Carregar contratações do PCA
 */
function carregarContratacoesPCA() {
    fetch('process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_contratacoes_pca'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.contratacoes && data.contratacoes.length > 0) {
            mostrarListaContratacoes(data.contratacoes);
        } else {
            document.getElementById('lista-contratacoes').innerHTML = '<p style="text-align: center; color: #6c757d;">Nenhuma contratação encontrada.</p>';
        }
        document.getElementById('loading-contratacoes').style.display = 'none';
        document.getElementById('lista-contratacoes').style.display = 'block';
    })
    .catch(error => {
        console.error('Erro:', error);
        document.getElementById('lista-contratacoes').innerHTML = '<p style="text-align: center; color: #e74c3c;">Erro ao carregar contratações.</p>';
        document.getElementById('loading-contratacoes').style.display = 'none';
        document.getElementById('lista-contratacoes').style.display = 'block';
    });
}

/**
 * Mostrar lista de contratações - APENAS NUMERO_CONTRATACAO
 */
function mostrarListaContratacoes(contratacoes) {
    const container = document.getElementById('lista-contratacoes');
    
    // Adicionar campo de busca rápida
    let html = `
        <div style="padding: 10px;">
            <input type="text" id="busca-rapida-contratacao" placeholder="Digite para filtrar contratações..." 
                   style="width: 100%; padding: 8px; border: 1px solid #dee2e6; border-radius: 4px;">
        </div>
        <div class="contratacoes-list" id="contratacoes-list-items" style="max-height: 400px; overflow-y: auto;">
    `;
    
    contratacoes.forEach(contratacao => {
        const numeroContratacao = contratacao.numero_contratacao || contratacao.titulo_contratacao || 'Sem número';
        const escapedValue = numeroContratacao.replace(/'/g, "\\'");
        html += `
            <div class="contratacao-item" data-contratacao="${numeroContratacao.toLowerCase()}" 
                 onclick="selecionarContratacaoSimples('${escapedValue}')" 
                 style="padding: 12px; border-bottom: 1px solid #e9ecef; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                <div class="contratacao-info">
                    <strong style="color: #2c3e50;">${numeroContratacao}</strong>
                </div>
                <div class="contratacao-select">
                    <i data-lucide="chevron-right" style="color: #3498db;"></i>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    // Adicionar CSS inline para hover
    html += `
    <style>
        .contratacao-item:hover {
            background-color: #f8f9fa !important;
        }
        .contratacao-item.hidden {
            display: none !important;
        }
    </style>
    `;
    
    container.innerHTML = html;
    
    // Configurar filtro em tempo real
    const buscaRapida = document.getElementById('busca-rapida-contratacao');
    if (buscaRapida) {
        buscaRapida.addEventListener('input', function() {
            const termo = this.value.toLowerCase();
            const items = document.querySelectorAll('.contratacao-item');
            
            items.forEach(item => {
                const texto = item.getAttribute('data-contratacao');
                if (texto.includes(termo)) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        });
        
        // Focar no campo de busca
        buscaRapida.focus();
    }
    
    // Reinicializar ícones
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Selecionar contratação simples - APENAS NUMERO_CONTRATACAO
 */
function selecionarContratacaoSimples(numeroContratacao) {
    const modal = document.getElementById('modalSeletorContratacao');
    const isForModal = modal.getAttribute('data-target') === 'modal';
    
    if (isForModal) {
        // Para vinculação: preencher apenas numero_contratacao
        const inputContratacao = document.querySelector('#formVinculacaoPCA input[name="numero_contratacao"]');
        if (inputContratacao) {
            inputContratacao.value = numeroContratacao;
        }
    } else {
        // Para formulário de cadastro: preencher numero_contratacao_criar
        const inputCriar = document.getElementById('numero_contratacao_criar');
        if (inputCriar) {
            inputCriar.value = numeroContratacao;
            // Atualizar visual do campo para mostrar que foi selecionado
            inputCriar.style.backgroundColor = '#e8f5e9';
            inputCriar.style.borderColor = '#4caf50';
            
            // Remover estilo após 2 segundos
            setTimeout(() => {
                inputCriar.style.backgroundColor = '';
                inputCriar.style.borderColor = '';
            }, 2000);
        }
    }
    
    // Mostrar notificação de sucesso
    showNotification(`Contratação selecionada: ${numeroContratacao}`, 'success');
    
    // Fechar modal
    fecharModal('modalSeletorContratacao');
    
    // Limpar atributo
    modal.removeAttribute('data-target');
}

/**
 * Buscar contratações do PCA para o formulário
 */
function buscarContratacoesPCAForm(termo) {
    fetch('process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=buscar_contratacoes_pca&termo=' + encodeURIComponent(termo)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.contratacoes) {
            mostrarResultadosBuscaForm(data.contratacoes);
        } else {
            document.getElementById('resultados_busca_form').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Erro na busca:', error);
        document.getElementById('resultados_busca_form').style.display = 'none';
    });
}

/**
 * Mostrar resultados da busca no formulário
 */
function mostrarResultadosBuscaForm(contratacoes) {
    const container = document.getElementById('resultados_busca_form');
    if (!container) return;
    
    let html = '';
    contratacoes.forEach(contratacao => {
        const valor = contratacao.valor_estimado ? parseFloat(contratacao.valor_estimado).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}) : 'N/A';
        html += `
            <div class="resultado-item" onclick="selecionarContratacaoForm('${contratacao.numero_dfd}', '${contratacao.titulo_contratacao}', '${contratacao.area_requisitante || ''}', '${valor}')">
                <strong>${contratacao.numero_dfd}</strong>
                <span>${contratacao.titulo_contratacao}</span>
                <small style="color: #95a5a6; font-size: 10px; display: block;">${contratacao.area_requisitante || ''} • ${valor}</small>
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.style.display = contratacoes.length > 0 ? 'block' : 'none';
}

/**
 * Selecionar contratação no formulário
 */
function selecionarContratacaoForm(numeroDfd, tituloContratacao, areaRequisitante, valor) {
    // Preencher campos ocultos
    document.getElementById('numero_dfd_criar').value = numeroDfd;
    document.getElementById('numero_contratacao_criar').value = tituloContratacao;
    
    // Mostrar preview da seleção
    document.getElementById('preview_dfd').textContent = numeroDfd;
    document.getElementById('preview_titulo').textContent = tituloContratacao;
    document.getElementById('contratacao_selecionada').style.display = 'block';
    
    // Esconder campo de busca e resultados
    document.getElementById('busca_contratacao_form').style.display = 'none';
    document.getElementById('resultados_busca_form').style.display = 'none';
    
    // Reinicializar ícones
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Limpar seleção de contratação no formulário
 */
function limparSelecaoContratacao() {
    document.getElementById('numero_dfd_criar').value = '';
    document.getElementById('numero_contratacao_criar').value = '';
    document.getElementById('busca_contratacao_form').value = '';
    document.getElementById('busca_contratacao_form').style.display = 'block';
    document.getElementById('contratacao_selecionada').style.display = 'none';
    document.getElementById('resultados_busca_form').style.display = 'none';
    
    // Focus no campo de busca
    document.getElementById('busca_contratacao_form').focus();
}

/**
 * Limpar seleção no modal
 */
function limparSelecaoContratacaoModal() {
    document.querySelector('#formVinculacaoPCA input[name="numero_dfd"]').value = '';
    document.querySelector('#formVinculacaoPCA input[name="numero_contratacao"]').value = '';
    document.getElementById('busca_contratacao').value = '';
    document.getElementById('busca_contratacao').style.display = 'block';
    document.getElementById('contratacao_selecionada_modal').style.display = 'none';
    document.getElementById('resultados_busca').style.display = 'none';
    
    // Focus no campo de busca
    document.getElementById('busca_contratacao').focus();
}

/**
 * Atualizar função de seleção no modal
 */
function selecionarContratacaoModal(numeroDfd, tituloContratacao) {
    // Preencher campos ocultos
    document.querySelector('#formVinculacaoPCA input[name="numero_dfd"]').value = numeroDfd;
    document.querySelector('#formVinculacaoPCA input[name="numero_contratacao"]').value = tituloContratacao;
    
    // Mostrar preview da seleção
    document.getElementById('preview_dfd_modal').textContent = numeroDfd;
    document.getElementById('preview_titulo_modal').textContent = tituloContratacao;
    document.getElementById('contratacao_selecionada_modal').style.display = 'block';
    
    // Esconder campo de busca e resultados
    document.getElementById('busca_contratacao').style.display = 'none';
    document.getElementById('resultados_busca').style.display = 'none';
    
    // Reinicializar ícones
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Disponibilizar funções globalmente
window.toggleQualificacaoView = toggleQualificacaoView;
window.abrirVinculacaoPCA = abrirVinculacaoPCA;
window.abrirSeletorContratacao = abrirSeletorContratacao;
window.abrirSeletorContratacaoModal = abrirSeletorContratacaoModal;
window.selecionarContratacaoSimples = selecionarContratacaoSimples;

console.log('📋 Qualificação Dashboard JavaScript carregado com sucesso!');