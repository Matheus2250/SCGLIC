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
        initializeQualificationChart();
        initializeStatusChart();
        initializePerformanceChart();
    }, 100);
}

/**
 * Gráfico de qualificações por categoria
 */
function initializeQualificationChart() {
    const ctx = document.getElementById('qualificationChart');
    if (!ctx || typeof Chart === 'undefined') return;
    
    // Destruir gráfico existente se houver
    if (window.qualificationChartInstance) {
        window.qualificationChartInstance.destroy();
    }
    
    window.qualificationChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Técnica', 'Econômica', 'Jurídica', 'Ambiental', 'Social'],
            datasets: [{
                data: [0, 0, 0, 0, 0],
                backgroundColor: [
                    '#f59e0b',
                    '#d97706',
                    '#b45309',
                    '#92400e',
                    '#78350f'
                ],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 12,
                            family: "'Inter', 'Segoe UI', Roboto, sans-serif"
                        }
                    }
                }
            }
        }
    });
}

/**
 * Gráfico de status das qualificações
 */
function initializeStatusChart() {
    const ctx = document.getElementById('statusChart');
    if (!ctx || typeof Chart === 'undefined') return;
    
    // Destruir gráfico existente se houver
    if (window.statusChartInstance) {
        window.statusChartInstance.destroy();
    }
    
    window.statusChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
            datasets: [
                {
                    label: 'Aprovadas',
                    data: [0, 0, 0, 0, 0, 0],
                    backgroundColor: '#27ae60',
                    borderRadius: 4,
                    borderSkipped: false,
                },
                {
                    label: 'Reprovadas',
                    data: [0, 0, 0, 0, 0, 0],
                    backgroundColor: '#e74c3c',
                    borderRadius: 4,
                    borderSkipped: false,
                },
                {
                    label: 'Pendentes',
                    data: [0, 0, 0, 0, 0, 0],
                    backgroundColor: '#f59e0b',
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
function initializePerformanceChart() {
    const ctx = document.getElementById('performanceChart');
    if (!ctx || typeof Chart === 'undefined') return;
    
    // Destruir gráfico existente se houver
    if (window.performanceChartInstance) {
        window.performanceChartInstance.destroy();
    }
    
    window.performanceChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
            datasets: [{
                label: 'Taxa de Aprovação (%)',
                data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
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

/**
 * Processar envio do formulário de qualificação
 */
function processarFormularioQualificacao(event) {
    console.log('🚀 processarFormularioQualificacao chamado!');
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validar campos obrigatórios
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    let firstErrorField = null;
    
    requiredFields.forEach(field => {
        // Limpar erros anteriores
        clearFieldError({ target: field });
        
        if (!field.value.trim()) {
            isValid = false;
            showFieldError(field, 'Este campo é obrigatório.');
            if (!firstErrorField) firstErrorField = field;
        } else {
            // Validação específica por tipo de campo - apenas para campos críticos
            if (field.name === 'valor_estimado' && field.classList.contains('currency')) {
                const cleanValue = field.value.replace(/[^\d.,]/g, '').replace(/\./g, '').replace(',', '.');
                const numericValue = parseFloat(cleanValue);
                
                if (isNaN(numericValue) || numericValue <= 0) {
                    isValid = false;
                    showFieldError(field, 'Digite um valor válido maior que zero.');
                    if (!firstErrorField) firstErrorField = field;
                }
            }
        }
    });
    
    if (!isValid) {
        showNotification('Por favor, corrija os erros destacados no formulário.', 'error');
        // Focar no primeiro campo com erro
        if (firstErrorField) {
            firstErrorField.focus();
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }
    
    // Mostrar loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Salvando...';
    submitBtn.disabled = true;
    
    // Debug: Log dados antes do envio
    console.log('=== ENVIANDO QUALIFICAÇÃO ===');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    // Enviar via AJAX
    fetch('process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.text(); // Primeiro pegar como texto para debug
    })
    .then(responseText => {
        console.log('Response text:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Erro ao parsear JSON:', e);
            console.error('Resposta recebida:', responseText);
            throw new Error('Resposta inválida do servidor');
        }
        
        console.log('Response data:', data);
        
        if (data.success) {
            showNotification(data.message || 'Qualificação salva com sucesso!', 'success');
            form.reset();
            // Atualizar estatísticas
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification(data.message || 'Erro ao salvar qualificação.', 'error');
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        showNotification('Erro ao salvar qualificação: ' + error.message, 'error');
    })
    .finally(() => {
        // Restaurar botão
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

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
    
    // Configurar formulário de qualificação
    const formQualificacao = document.getElementById('form-nova-qualificacao');
    if (formQualificacao) {
        formQualificacao.addEventListener('submit', processarFormularioQualificacao);
    }
    
    // Restaurar estados salvos
    restoreSidebarState();
    restoreActiveSection();
    
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

// ==================== FUNÇÕES DE AÇÕES DA TABELA ====================

/**
 * Visualizar qualificação
 */
function visualizarQualificacao(id) {
    console.log(`Visualizar qualificação ID: ${id}`);
    showNotification('Funcionalidade de visualização em desenvolvimento', 'info');
}

/**
 * Editar qualificação
 */
function editarQualificacao(id) {
    console.log(`Editar qualificação ID: ${id}`);
    showNotification('Funcionalidade de edição em desenvolvimento', 'info');
}

/**
 * Excluir qualificação
 */
function excluirQualificacao(id) {
    console.log(`Excluir qualificação ID: ${id}`);
    
    if (!confirm('Tem certeza que deseja excluir esta qualificação? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('acao', 'excluir_qualificacao');
    formData.append('id', id);
    
    fetch('process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Qualificação excluída com sucesso!', 'success');
            // Recarregar página após pequeno delay
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || 'Erro ao excluir qualificação.', 'error');
        }
    })
    .catch(error => {
        console.error('Erro ao excluir:', error);
        showNotification('Erro ao excluir qualificação. Tente novamente.', 'error');
    });
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
    initializeDashboardCharts,
    processarFormularioQualificacao
};

// Disponibilizar funções de ação globalmente
window.visualizarQualificacao = visualizarQualificacao;
window.editarQualificacao = editarQualificacao;
window.excluirQualificacao = excluirQualificacao;

console.log('📋 Qualificação Dashboard JavaScript carregado com sucesso!');