/**
 * SISTEMA UNIFICADO DE LISTAS - JavaScript
 * Funcionalidades padronizadas para todas as listas do sistema
 */

class UnifiedListManager {
    constructor(listType, options = {}) {
        this.listType = listType; // 'qualificacoes', 'planejamento', 'licitacoes', 'contratos'
        this.options = {
            itemsPerPage: 10,
            defaultView: 'lista',
            enableFilters: true,
            enablePagination: true,
            enableExport: true,
            ...options
        };
        
        this.currentPage = 1;
        this.currentView = this.options.defaultView;
        this.filters = {};
        this.totalItems = 0;
        this.isLoading = false;
        
        this.init();
    }
    
    /**
     * Inicialização do sistema
     */
    init() {
        console.log(`🚀 Inicializando UnifiedListManager para: ${this.listType}`);
        
        this.setupViewToggle();
        this.setupFilters();
        this.setupPagination();
        this.restoreUserPreferences();
        this.bindEvents();
        
        // Carregar dados iniciais
        this.loadData();
    }
    
    /**
     * Configurar toggle de visualização Lista/Cards
     */
    setupViewToggle() {
        const toggleContainer = document.querySelector('.unified-view-toggle');
        if (!toggleContainer) return;
        
        const btnLista = document.getElementById(`btn-lista-${this.listType}`);
        const btnCards = document.getElementById(`btn-cards-${this.listType}`);
        
        if (btnLista && btnCards) {
            btnLista.addEventListener('click', () => this.switchView('lista'));
            btnCards.addEventListener('click', () => this.switchView('cards'));
        }
    }
    
    /**
     * Alternar entre visualização Lista e Cards
     */
    switchView(viewType) {
        console.log(`Alternando visualização para: ${viewType}`);
        
        const tableView = document.querySelector('.unified-table-view');
        const cardsView = document.querySelector('.unified-cards-view');
        const btnLista = document.getElementById(`btn-lista-${this.listType}`);
        const btnCards = document.getElementById(`btn-cards-${this.listType}`);
        
        if (!tableView || !cardsView || !btnLista || !btnCards) {
            console.error('Elementos de visualização não encontrados');
            return;
        }
        
        if (viewType === 'cards') {
            tableView.style.display = 'none';
            cardsView.style.display = 'block';
            btnLista.classList.remove('active');
            btnCards.classList.add('active');
        } else {
            tableView.style.display = 'block';
            cardsView.style.display = 'none';
            btnLista.classList.add('active');
            btnCards.classList.remove('active');
        }
        
        this.currentView = viewType;
        this.saveUserPreferences();
        
        // Reinicializar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    /**
     * Configurar sistema de filtros
     */
    setupFilters() {
        const filterForm = document.getElementById(`filtros-${this.listType}`);
        if (!filterForm) return;
        
        // Bind eventos nos campos de filtro
        const filterInputs = filterForm.querySelectorAll('input, select');
        filterInputs.forEach(input => {
            input.addEventListener('input', this.debounce(() => this.handleFilterChange(), 500));
            input.addEventListener('change', () => this.handleFilterChange());
        });
        
        // Botão filtrar
        const btnFiltrar = document.getElementById(`btn-filtrar-${this.listType}`);
        if (btnFiltrar) {
            btnFiltrar.addEventListener('click', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }
        
        // Botão limpar
        const btnLimpar = document.getElementById(`btn-limpar-${this.listType}`);
        if (btnLimpar) {
            btnLimpar.addEventListener('click', (e) => {
                e.preventDefault();
                this.clearFilters();
            });
        }
    }
    
    /**
     * Processar mudanças nos filtros
     */
    handleFilterChange() {
        this.collectFilters();
        this.currentPage = 1; // Reset para primeira página
        this.loadData();
    }
    
    /**
     * Coletar valores dos filtros
     */
    collectFilters() {
        const filterForm = document.getElementById(`filtros-${this.listType}`);
        if (!filterForm) return;
        
        const formData = new FormData(filterForm);
        this.filters = {};
        
        for (let [key, value] of formData.entries()) {
            if (value && value.trim() !== '') {
                this.filters[key] = value.trim();
            }
        }
        
        console.log('Filtros coletados:', this.filters);
    }
    
    /**
     * Aplicar filtros
     */
    applyFilters() {
        this.collectFilters();
        this.currentPage = 1;
        this.loadData();
        this.showNotification('Filtros aplicados com sucesso', 'success');
    }
    
    /**
     * Limpar filtros
     */
    clearFilters() {
        const filterForm = document.getElementById(`filtros-${this.listType}`);
        if (!filterForm) return;
        
        // Limpar campos
        const inputs = filterForm.querySelectorAll('input:not([type="hidden"])');
        const selects = filterForm.querySelectorAll('select');
        
        inputs.forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        
        selects.forEach(select => {
            select.selectedIndex = 0;
        });
        
        this.filters = {};
        this.currentPage = 1;
        this.loadData();
        this.showNotification('Filtros limpos', 'success');
    }
    
    /**
     * Configurar paginação
     */
    setupPagination() {
        // Items per page
        const itemsSelect = document.getElementById(`items-per-page-${this.listType}`);
        if (itemsSelect) {
            itemsSelect.addEventListener('change', (e) => {
                this.options.itemsPerPage = parseInt(e.target.value);
                this.currentPage = 1;
                this.loadData();
                this.saveUserPreferences();
            });
        }
    }
    
    /**
     * Atualizar interface de paginação
     */
    updatePaginationUI() {
        const totalPages = Math.ceil(this.totalItems / this.options.itemsPerPage);
        
        // Informações da paginação
        const paginationSummary = document.querySelector('.unified-pagination-summary');
        if (paginationSummary) {
            const startItem = ((this.currentPage - 1) * this.options.itemsPerPage) + 1;
            const endItem = Math.min(this.currentPage * this.options.itemsPerPage, this.totalItems);
            
            paginationSummary.textContent = 
                `Mostrando ${startItem} a ${endItem} de ${this.totalItems} registros`;
        }
        
        // Navegação
        this.renderPaginationNav(totalPages);
    }
    
    /**
     * Renderizar navegação de páginas
     */
    renderPaginationNav(totalPages) {
        const navContainer = document.querySelector('.unified-pagination-nav');
        if (!navContainer) return;
        
        let html = '';
        
        // Botão anterior
        html += `
            <button class="unified-page-btn" onclick="unifiedListManager.goToPage(${this.currentPage - 1})" 
                    ${this.currentPage <= 1 ? 'disabled' : ''}>
                <i data-lucide="chevron-left"></i>
            </button>
        `;
        
        // Números das páginas
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(totalPages, this.currentPage + 2);
        
        if (startPage > 1) {
            html += `<button class="unified-page-btn" onclick="unifiedListManager.goToPage(1)">1</button>`;
            if (startPage > 2) {
                html += `<span class="unified-page-info">...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button class="unified-page-btn ${i === this.currentPage ? 'active' : ''}" 
                        onclick="unifiedListManager.goToPage(${i})">
                    ${i}
                </button>
            `;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<span class="unified-page-info">...</span>`;
            }
            html += `<button class="unified-page-btn" onclick="unifiedListManager.goToPage(${totalPages})">${totalPages}</button>`;
        }
        
        // Botão próximo
        html += `
            <button class="unified-page-btn" onclick="unifiedListManager.goToPage(${this.currentPage + 1})" 
                    ${this.currentPage >= totalPages ? 'disabled' : ''}>
                <i data-lucide="chevron-right"></i>
            </button>
        `;
        
        navContainer.innerHTML = html;
        
        // Reinicializar ícones
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    /**
     * Ir para página específica
     */
    goToPage(page) {
        const totalPages = Math.ceil(this.totalItems / this.options.itemsPerPage);
        
        if (page < 1 || page > totalPages || page === this.currentPage) {
            return;
        }
        
        this.currentPage = page;
        this.loadData();
    }
    
    /**
     * Carregar dados via AJAX
     */
    async loadData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoadingState();
        
        try {
            const params = new URLSearchParams({
                ajax: 'filtrar_dados',
                tipo: this.listType,
                pagina: this.currentPage,
                por_pagina: this.options.itemsPerPage,
                ...this.filters
            });
            
            const response = await fetch('process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            });
            
            if (!response.ok) {
                throw new Error('Erro na requisição');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.totalItems = data.total || 0;
                this.renderData(data.items || []);
                this.updatePaginationUI();
                this.hideLoadingState();
            } else {
                throw new Error(data.message || 'Erro desconhecido');
            }
            
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            this.showError('Erro ao carregar dados: ' + error.message);
            this.hideLoadingState();
        }
        
        this.isLoading = false;
    }
    
    /**
     * Renderizar dados na interface
     */
    renderData(items) {
        // Renderizar tabela
        this.renderTable(items);
        
        // Renderizar cards
        this.renderCards(items);
        
        // Reinicializar ícones
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    /**
     * Renderizar tabela
     */
    renderTable(items) {
        const tbody = document.querySelector('.unified-table tbody');
        if (!tbody) return;
        
        if (items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="100%" class="unified-empty-state">
                        <i data-lucide="inbox"></i>
                        <h3>Nenhum registro encontrado</h3>
                        <p>Tente ajustar os filtros ou adicionar novos registros.</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = items.map(item => this.renderTableRow(item)).join('');
    }
    
    /**
     * Renderizar cards
     */
    renderCards(items) {
        const cardsGrid = document.querySelector('.unified-cards-grid');
        if (!cardsGrid) return;
        
        if (items.length === 0) {
            cardsGrid.innerHTML = `
                <div class="unified-empty-state" style="grid-column: 1 / -1;">
                    <i data-lucide="inbox"></i>
                    <h3>Nenhum registro encontrado</h3>
                    <p>Tente ajustar os filtros ou adicionar novos registros.</p>
                </div>
            `;
            return;
        }
        
        cardsGrid.innerHTML = items.map(item => this.renderCard(item)).join('');
    }
    
    /**
     * Estados de loading
     */
    showLoadingState() {
        const contentArea = document.querySelector('.unified-content-area');
        if (contentArea) {
            contentArea.style.opacity = '0.6';
            contentArea.style.pointerEvents = 'none';
        }
        
        // Adicionar spinner se não existir
        if (!document.querySelector('.unified-loading-spinner')) {
            const spinner = document.createElement('div');
            spinner.className = 'unified-loading-spinner';
            spinner.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 1000;
            `;
            spinner.innerHTML = `
                <i data-lucide="loader-2" style="width: 32px; height: 32px; animation: spin 1s linear infinite; color: var(--primary-color);"></i>
            `;
            contentArea.appendChild(spinner);
        }
    }
    
    hideLoadingState() {
        const contentArea = document.querySelector('.unified-content-area');
        if (contentArea) {
            contentArea.style.opacity = '1';
            contentArea.style.pointerEvents = 'auto';
        }
        
        const spinner = document.querySelector('.unified-loading-spinner');
        if (spinner) {
            spinner.remove();
        }
    }
    
    /**
     * Gerenciamento de preferências do usuário
     */
    saveUserPreferences() {
        const prefs = {
            view: this.currentView,
            itemsPerPage: this.options.itemsPerPage
        };
        
        localStorage.setItem(`unified-${this.listType}-prefs`, JSON.stringify(prefs));
    }
    
    restoreUserPreferences() {
        const saved = localStorage.getItem(`unified-${this.listType}-prefs`);
        if (!saved) return;
        
        try {
            const prefs = JSON.parse(saved);
            
            if (prefs.view) {
                this.currentView = prefs.view;
                setTimeout(() => this.switchView(prefs.view), 100);
            }
            
            if (prefs.itemsPerPage) {
                this.options.itemsPerPage = prefs.itemsPerPage;
                const select = document.getElementById(`items-per-page-${this.listType}`);
                if (select) {
                    select.value = prefs.itemsPerPage;
                }
            }
        } catch (error) {
            console.error('Erro ao restaurar preferências:', error);
        }
    }
    
    /**
     * Sistema de notificações
     */
    showNotification(message, type = 'info') {
        // Usar sistema de notificação existente se disponível
        if (typeof showNotification === 'function') {
            showNotification(message, type);
            return;
        }
        
        // Implementação básica
        const notification = document.createElement('div');
        notification.className = `unified-notification unified-notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            background: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#3498db'};
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    /**
     * Exportar dados
     */
    async exportData(format = 'csv') {
        try {
            const params = new URLSearchParams({
                acao: 'exportar_dados',
                tipo: this.listType,
                formato: format,
                ...this.filters
            });
            
            const response = await fetch('process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${this.listType}_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                this.showNotification(`Dados exportados com sucesso!`, 'success');
            } else {
                throw new Error('Erro na exportação');
            }
        } catch (error) {
            console.error('Erro na exportação:', error);
            this.showError('Erro ao exportar dados');
        }
    }
    
    /**
     * Bind de eventos globais
     */
    bindEvents() {
        // Teclas de atalho
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'f':
                        e.preventDefault();
                        const searchInput = document.querySelector('.unified-filter-input[type="text"]');
                        if (searchInput) searchInput.focus();
                        break;
                    case 'e':
                        e.preventDefault();
                        this.exportData();
                        break;
                }
            }
        });
        
        // Refresh automático (opcional)
        if (this.options.autoRefresh) {
            setInterval(() => {
                if (!document.hidden) {
                    this.loadData();
                }
            }, this.options.autoRefresh * 1000);
        }
    }
    
    /**
     * Utility: Debounce
     */
    debounce(func, wait) {
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
     * Métodos abstratos que devem ser implementados por cada lista específica
     */
    renderTableRow(item) {
        throw new Error('renderTableRow deve ser implementado pela subclasse');
    }
    
    renderCard(item) {
        throw new Error('renderCard deve ser implementado pela subclasse');
    }
}

// Instância global para acesso nos event handlers
let unifiedListManager;

// Função para inicializar o sistema
function initUnifiedList(listType, options = {}) {
    unifiedListManager = new UnifiedListManager(listType, options);
    return unifiedListManager;
}

// Disponibilizar globalmente
window.UnifiedListManager = UnifiedListManager;
window.initUnifiedList = initUnifiedList;

// CSS para animações
const style = document.createElement('style');
style.textContent = `
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
`;
document.head.appendChild(style);