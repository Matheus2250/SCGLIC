/**
 * Dashboard JavaScript - Sistema CGLIC
 * Funcionalidades do painel de controle principal
 * Inclui sistema de visualização em cards para contratações
 */

// Variável global para armazenar dados do PHP
let dashboardData = {};

// ==================== SISTEMA DE CARDS CONTRATAÇÕES ====================

/**
 * Alternar entre visualização de tabela e cards
 */
function toggleContratacaoView(viewType) {
    const tableView = document.querySelector('.table-contratacoes-view');
    const cardsView = document.querySelector('.cards-contratacoes-view');
    const listBtn = document.querySelector('.view-toggle-btn[onclick*="lista"]');
    const cardsBtn = document.querySelector('.view-toggle-btn[onclick*="cards"]');
    
    // Atualizar botões ativos
    if (listBtn && cardsBtn) {
        listBtn.classList.toggle('active', viewType === 'lista');
        cardsBtn.classList.toggle('active', viewType === 'cards');
    }
    
    // Mostrar/ocultar visualizações
    if (tableView && cardsView) {
        if (viewType === 'lista') {
            tableView.style.display = 'block';
            cardsView.style.display = 'none';
        } else {
            tableView.style.display = 'none';
            cardsView.style.display = 'block';
        }
    }
    
    // Salvar preferência do usuário
    localStorage.setItem('contratacao_view_preference', viewType);
    
    // Reinicializar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Restaurar visualização preferida do usuário
 */
function restoreContratacaoViewPreference() {
    const savedView = localStorage.getItem('contratacao_view_preference') || 'lista';
    toggleContratacaoView(savedView);
}

/**
 * Abrir modal de qualificação para contratação
 */
function abrirQualificacaoContratacao(numeroDfd, tituloContratacao) {
    // Criar modal dinamicamente se não existir
    if (!document.getElementById('modalQualificacaoContratacao')) {
        criarModalQualificacaoContratacao();
    }
    
    const modal = document.getElementById('modalQualificacaoContratacao');
    const form = document.getElementById('formQualificacaoContratacao');
    const titleElement = document.getElementById('qualificacaoContratacaoTitle');
    
    // Preencher dados do modal
    if (titleElement) {
        titleElement.textContent = `Qualificar Contratação - DFD: ${numeroDfd}`;
    }
    
    // Preencher NUP com número DFD (removendo espaços)
    const nupInput = form.querySelector('input[name="nup"]');
    if (nupInput) {
        nupInput.value = numeroDfd;
    }
    
    // Preencher objeto com título da contratação
    const objetoTextarea = form.querySelector('textarea[name="objeto"]');
    if (objetoTextarea && tituloContratacao) {
        objetoTextarea.value = tituloContratacao;
    }
    
    // Exibir modal
    modal.style.display = 'block';
    
    // Reset para primeira aba
    if (typeof mostrarAbaQualificacao === 'function') {
        mostrarAbaQualificacao('informacoes-gerais');
    }
    
    // Focus no primeiro campo
    const firstInput = form.querySelector('input[name="area_demandante"]');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 100);
    }
    
    // Reinicializar ícones
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Fechar modal de qualificação
 */
function fecharQualificacaoContratacao() {
    const modal = document.getElementById('modalQualificacaoContratacao');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Criar modal de qualificação dinamicamente - USANDO O FORMULÁRIO EXISTENTE
 */
function criarModalQualificacaoContratacao() {
    const modalHTML = `
        <div id="modalQualificacaoContratacao" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 900px;">
                <div class="modal-header">
                    <h3 id="qualificacaoContratacaoTitle" style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="plus-circle"></i> Criar Nova Qualificação
                    </h3>
                    <span class="close" onclick="fecharQualificacaoContratacao()">&times;</span>
                </div>
                <div class="modal-body">
                    <!-- Sistema de Abas -->
                    <div class="tabs-container">
                        <div class="tabs-header">
                            <button type="button" class="tab-button active" onclick="mostrarAbaQualificacao('informacoes-gerais')">
                                <i data-lucide="info"></i> Informações Gerais
                            </button>
                            <button type="button" class="tab-button" onclick="mostrarAbaQualificacao('detalhes-objeto')">
                                <i data-lucide="file-text"></i> Detalhes do Objeto
                            </button>
                            <button type="button" class="tab-button" onclick="mostrarAbaQualificacao('valores-observacoes')">
                                <i data-lucide="dollar-sign"></i> Valores e Observações
                            </button>
                        </div>

                        <form action="process.php" method="POST" id="formQualificacaoContratacao">
                            <input type="hidden" name="acao" value="criar_qualificacao">
                            <input type="hidden" name="numero_dfd" value="">

                            <!-- Aba 1: Informações Gerais -->
                            <div id="aba-informacoes-gerais" class="tab-content active">
                                <h4 style="margin: 0 0 15px 0; color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 8px;">
                                    <i data-lucide="info"></i> Informações Gerais
                                </h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>NUP (Número Único de Protocolo) *</label>
                                        <input type="text" name="nup" id="nup_criar" required placeholder="xxxxx.xxxxxx/xxxx-xx" maxlength="20">
                                    </div>

                                    <div class="form-group">
                                        <label>Área Demandante *</label>
                                        <input type="text" name="area_demandante" required placeholder="Nome da área solicitante">
                                    </div>

                                    <div class="form-group">
                                        <label>Responsável *</label>
                                        <input type="text" name="responsavel" required placeholder="Nome do responsável">
                                    </div>

                                    <div class="form-group">
                                        <label>Modalidade *</label>
                                        <select name="modalidade" required>
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

                                    <div class="form-group">
                                        <label>Status *</label>
                                        <select name="status" required>
                                            <option value="">Selecione o status</option>
                                            <option value="EM ANÁLISE">EM ANÁLISE</option>
                                            <option value="CONCLUÍDO">CONCLUÍDO</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Aba 2: Detalhes do Objeto -->
                            <div id="aba-detalhes-objeto" class="tab-content">
                                <h4 style="margin: 0 0 15px 0; color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 8px;">
                                    <i data-lucide="file-text"></i> Detalhes do Objeto
                                </h4>
                                <div class="form-grid">
                                    <div class="form-group form-full">
                                        <label>Objeto *</label>
                                        <textarea name="objeto" required placeholder="Descrição detalhada do objeto da qualificação" rows="5"></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label>Palavras-Chave</label>
                                        <input type="text" name="palavras_chave" placeholder="Ex: equipamentos, serviços, tecnologia">
                                    </div>
                                </div>
                            </div>

                            <!-- Aba 3: Valores e Observações -->
                            <div id="aba-valores-observacoes" class="tab-content">
                                <h4 style="margin: 0 0 15px 0; color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 8px;">
                                    <i data-lucide="dollar-sign"></i> Valores e Observações
                                </h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Valor Estimado (R$) *</label>
                                        <input type="text" name="valor_estimado" class="currency" required placeholder="R$ 0,00">
                                    </div>

                                    <div class="form-group form-full">
                                        <label>Observações</label>
                                        <textarea name="observacoes" placeholder="Observações adicionais sobre a qualificação" rows="6"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Botões de Ação -->
                            <div style="margin-top: 20px; display: flex; gap: 15px; justify-content: flex-end; align-items: center; padding-top: 15px; border-top: 2px solid #e9ecef;">
                                <button type="button" id="btn-anterior-qualificacao" onclick="abaAnteriorQualificacao()" class="btn-secondary" style="display: none;">
                                    <i data-lucide="chevron-left"></i> Anterior
                                </button>
                                <button type="button" id="btn-proximo-qualificacao" onclick="proximaAbaQualificacao()" class="btn-primary">
                                    Próximo <i data-lucide="chevron-right"></i>
                                </button>
                                <button type="button" onclick="fecharQualificacaoContratacao()" class="btn-secondary">
                                    <i data-lucide="x"></i> Cancelar
                                </button>
                                <button type="reset" class="btn-secondary" onclick="resetarFormularioQualificacao()">
                                    <i data-lucide="refresh-cw"></i> Limpar
                                </button>
                                <button type="submit" class="btn-success" id="btn-criar-qualificacao" style="display: none;">
                                    <i data-lucide="check"></i> Criar Qualificação
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Adicionar event listener para fechar modal clicando fora
    const modal = document.getElementById('modalQualificacaoContratacao');
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            fecharQualificacaoContratacao();
        }
    });
    
    // Adicionar funções necessárias para as abas
    if (!window.mostrarAbaQualificacao) {
        window.mostrarAbaQualificacao = function(abaId) {
            // Ocultar todas as abas
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            
            // Mostrar aba selecionada
            document.getElementById(`aba-${abaId}`).classList.add('active');
            document.querySelector(`[onclick="mostrarAbaQualificacao('${abaId}')"]`).classList.add('active');
            
            // Controlar botões de navegação
            const isFirst = abaId === 'informacoes-gerais';
            const isLast = abaId === 'valores-observacoes';
            
            document.getElementById('btn-anterior-qualificacao').style.display = isFirst ? 'none' : 'inline-flex';
            document.getElementById('btn-proximo-qualificacao').style.display = isLast ? 'none' : 'inline-flex';
            document.getElementById('btn-criar-qualificacao').style.display = isLast ? 'inline-flex' : 'none';
        };
        
        window.proximaAbaQualificacao = function() {
            const activeTab = document.querySelector('.tab-content.active');
            if (activeTab.id === 'aba-informacoes-gerais') {
                mostrarAbaQualificacao('detalhes-objeto');
            } else if (activeTab.id === 'aba-detalhes-objeto') {
                mostrarAbaQualificacao('valores-observacoes');
            }
        };
        
        window.abaAnteriorQualificacao = function() {
            const activeTab = document.querySelector('.tab-content.active');
            if (activeTab.id === 'aba-valores-observacoes') {
                mostrarAbaQualificacao('detalhes-objeto');
            } else if (activeTab.id === 'aba-detalhes-objeto') {
                mostrarAbaQualificacao('informacoes-gerais');
            }
        };
        
        window.resetarFormularioQualificacao = function() {
            document.getElementById('formQualificacaoContratacao').reset();
            mostrarAbaQualificacao('informacoes-gerais');
        };
    }
}

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
        
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}


// ==================== NAVEGAÇÃO E INTERFACE ====================

/**
 * Navegação da Sidebar
 */
function showSection(sectionId) {
    // Esconder todas as seções
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remover active de todos os nav-items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Mostrar a seção selecionada
    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    // Adicionar active ao botão correto
    const activeButton = document.querySelector(`button[onclick*="showSection('${sectionId}')"]`);
    if (activeButton) {
        activeButton.classList.add('active');
    }
    
    // Atualizar URL apenas no histórico, sem recarregar a página
    const url = new URL(window.location);
    const secaoAtual = url.searchParams.get('secao');
    
    // Atualizar URL apenas se mudou de seção
    if (secaoAtual !== sectionId) {
        url.searchParams.set('secao', sectionId);
        // Para seções que requerem paginação, resetar para página 1
        if (['lista-contratacoes', 'lista-licitacoes'].includes(sectionId)) {
            url.searchParams.set('pagina', '1');
        }
        // Atualizar histórico sem recarregar
        window.history.pushState({}, '', url.toString());
    }
    
    // Carregar dados específicos da seção
    if (sectionId === 'backup-sistema') {
        atualizarEstatisticasBackup();
        atualizarHistoricoBackups();
    } else if (sectionId === 'relatorios') {
        // Inicializar construtor de gráficos se estiver na seção de relatórios
        setTimeout(() => {
            if (window.initConstrutorGraficos) {
                console.log('Inicializando construtor de gráficos...');
                window.initConstrutorGraficos();
            } else if (typeof ConstrutorGraficos !== 'undefined') {
                console.log('Criando nova instância do construtor de gráficos...');
                window.construtorGraficos = new ConstrutorGraficos();
            } else {
                console.log('Forçando inicialização manual do construtor...');
                // Forçar visibilidade do campo Y
                const grupoY = document.getElementById('grupoValorY');
                const campoY = document.getElementById('campoY');
                
                if (grupoY) {
                    grupoY.style.display = 'block';
                    grupoY.style.visibility = 'visible';
                    console.log('Campo Y grupo forçado para visível');
                }
                
                if (campoY) {
                    campoY.style.display = 'block';
                    campoY.style.visibility = 'visible';
                    console.log('Campo Y select forçado para visível');
                }
            }
        }, 200);
    } else if (sectionId === 'pncp-integration') {
        // Inicializar módulo PNCP
        setTimeout(() => {
            if (window.inicializarPNCP) {
                console.log('Inicializando módulo PNCP...');
                window.inicializarPNCP();
            }
        }, 100);
    }
}

/**
 * Inicializar navegação ao carregar a página
 */
function initNavigation() {
    // Verificar se há uma seção na URL
    const urlParams = new URLSearchParams(window.location.search);
    const secaoAtiva = urlParams.get('secao') || 'dashboard';
    
    // Ativar a seção correta
    const secaoElement = document.getElementById(secaoAtiva);
    if (secaoElement) {
        // Simular clique para ativar a seção
        const menuItem = document.querySelector(`.nav-item[onclick*="${secaoAtiva}"]`);
        if (menuItem) {
            showSection(secaoAtiva, { currentTarget: menuItem });
        } else {
            // Se não encontrar o item do menu, apenas mostrar a seção
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            secaoElement.classList.add('active');
        }
    }
}

/**
 * Inicializar gráficos do dashboard
 */
function initCharts() {
    setTimeout(() => {
        // Verificar se os dados foram carregados do PHP
        if (!window.dashboardData) {
            console.warn('Dados do dashboard não carregados');
            return;
        }

        // Verificar se Chart.js está disponível
        if (typeof Chart === 'undefined') {
            console.error('Chart.js não está carregado!');
            return;
        }

        const dadosCategoria = window.dashboardData.dados_categoria || [];
        const dadosArea = window.dashboardData.dados_area || [];
        const dadosMensal = window.dashboardData.dados_mensal || [];
        const dadosStatus = window.dashboardData.dados_status || [];
        const stats = window.dashboardData.stats || {};

        // Debug: verificar dados
        console.log('Dashboard - dados_area:', dadosArea.length, 'itens');

        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Gráfico de Áreas (Contratação por Área)
        if (document.getElementById('chartArea')) {
            try {
                console.log('Criando gráfico de área com', dadosArea.length, 'itens');
                if (dadosArea && dadosArea.length > 0) {
                    new Chart(document.getElementById('chartArea'), {
                        type: 'bar',
                        data: {
                            labels: dadosArea.map(item => {
                                // Truncar nomes muito longos para melhor visualização
                                const nome = item.area || 'Não definido';
                                return nome.length > 25 ? nome.substring(0, 25) + '...' : nome;
                            }),
                            datasets: [{
                                label: 'Contratações',
                                data: dadosArea.map(item => item.total || item.quantidade || 0),
                                backgroundColor: [
                                    '#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6',
                                    '#1abc9c', '#e67e22', '#8e44ad', '#34495e', '#95a5a6'
                                ]
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: function(context) {
                                            // Mostrar o nome completo no tooltip
                                            const index = context[0].dataIndex;
                                            return dadosArea[index].area || 'Não definido';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                },
                                y: {
                                    ticks: {
                                        font: {
                                            size: 11
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    // Exibir gráfico vazio quando não há dados
                    console.log('Sem dados reais, exibindo gráfico vazio');
                    document.getElementById('chartArea').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 200px; color: #7f8c8d; text-align: center;"><div><i data-lucide="bar-chart-3" style="width: 48px; height: 48px; margin-bottom: 10px; opacity: 0.5;"></i><p>Nenhum dado disponível<br><small>Importe dados do PCA para ver os gráficos</small></p></div></div>';
                    lucide.createIcons();
                }
            } catch (error) {
                console.error('Erro ao criar gráfico de áreas:', error);
            }
        }

        // Gráfico Mensal (Evolução Mensal)
        if (document.getElementById('chartMensal')) {
            try {
                if (dadosMensal.length > 0) {
                    new Chart(document.getElementById('chartMensal'), {
                        type: 'line',
                        data: {
                            labels: dadosMensal.map(item => {
                                if (item.mes) {
                                    const [ano, mes] = item.mes.split('-');
                                    return new Date(ano, mes - 1).toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
                                }
                                return item.periodo || 'N/A';
                            }),
                            datasets: [{
                                label: 'Contratações Iniciadas',
                                data: dadosMensal.map(item => item.quantidade || item.total || 0),
                                borderColor: '#3498db',
                                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointBackgroundColor: '#3498db',
                                pointBorderColor: '#2980b9',
                                pointBorderWidth: 2,
                                pointRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { display: false }
                            },
                            scales: {
                                x: {
                                    display: true,
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                } else {
                    // Exibir gráfico vazio quando não há dados mensais
                    console.log('Sem dados mensais, exibindo gráfico vazio');
                    document.getElementById('chartMensal').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 200px; color: #7f8c8d; text-align: center;"><div><i data-lucide="trending-up" style="width: 48px; height: 48px; margin-bottom: 10px; opacity: 0.5;"></i><p>Nenhum dado mensal disponível<br><small>Importe dados do PCA para ver a evolução mensal</small></p></div></div>';
                    lucide.createIcons();
                }
            } catch (error) {
                console.error('Erro ao criar gráfico mensal:', error);
            }
        }

        // Gráfico de Status das Contratações
        if (document.getElementById('chartStatus')) {
            try {
                console.log('Criando gráfico de status com', dadosStatus.length, 'itens');
                if (dadosStatus && dadosStatus.length > 0) {
                    new Chart(document.getElementById('chartStatus'), {
                        type: 'doughnut',
                        data: {
                            labels: dadosStatus.map(item => item.status || 'Não definido'),
                            datasets: [{
                                data: dadosStatus.map(item => item.total || 0),
                                backgroundColor: [
                                    '#27ae60', // Concluído - Verde
                                    '#3498db', // Em andamento - Azul
                                    '#f39c12', // Não iniciado - Amarelo
                                    '#e74c3c', // Suspenso/Cancelado - Vermelho
                                    '#9b59b6', // Outros - Roxo
                                    '#1abc9c'  // Outros - Verde água
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    position: 'bottom',
                                    labels: {
                                        padding: 10,
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = ((value / total) * 100).toFixed(1);
                                            return `${label}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    // Exibir gráfico vazio quando não há dados de status
                    console.log('Sem dados de status, exibindo gráfico vazio');
                    document.getElementById('chartStatus').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 200px; color: #7f8c8d; text-align: center;"><div><i data-lucide="activity" style="width: 48px; height: 48px; margin-bottom: 10px; opacity: 0.5;"></i><p>Nenhum dado de status disponível<br><small>Importe dados do PCA para ver o status das contratações</small></p></div></div>';
                    lucide.createIcons();
                }
            } catch (error) {
                console.error('Erro ao criar gráfico de status:', error);
            }
        }

        // Gráfico de Categorias (se existir)
        if (document.getElementById('chartCategoria')) {
            try {
                if (dadosCategoria.length > 0) {
                    new Chart(document.getElementById('chartCategoria'), {
                        type: 'doughnut',
                        data: {
                            labels: dadosCategoria.map(item => item.categoria || item.categoria_contratacao || 'Não definido'),
                            datasets: [{
                                data: dadosCategoria.map(item => item.total || item.quantidade || 0),
                                backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6', '#1abc9c', '#e67e22']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            } catch (error) {
                console.error('Erro ao criar gráfico de categorias:', error);
            }
        }

        console.log('Gráficos inicializados com sucesso');
    }, 500);
}

/**
 * Configurar dados do dashboard vindos do PHP
 */
function setDashboardData(data) {
    dashboardData = data;
}

// ==================== FUNÇÕES DA TABELA ====================

/**
 * Ver detalhes de uma contratação
 */
function verDetalhes(ids) {
    const modal = document.getElementById('modalDetalhes');
    const conteudo = document.getElementById('conteudoDetalhes');
    
    conteudo.innerHTML = '<div style="text-align: center; padding: 40px;"><p>Carregando...</p></div>';
    modal.style.display = 'block';
    
    fetch('utils/detalhes.php?ids=' + ids)
        .then(response => response.text())
        .then(html => {
            conteudo.innerHTML = html;
        })
        .catch(() => {
            conteudo.innerHTML = '<div style="padding: 40px; text-align: center;">Erro ao carregar detalhes</div>';
        });
}

/**
 * Fechar modal de detalhes
 */
function fecharModalDetalhes() {
    document.getElementById('modalDetalhes').style.display = 'none';
    document.getElementById('conteudoDetalhes').innerHTML = '';
}

/**
 * Ver histórico de uma contratação
 */
function verHistorico(numero) {
    const modal = document.getElementById('modalDetalhes');
    const conteudo = document.getElementById('conteudoDetalhes');
    
    conteudo.innerHTML = '<div style="text-align: center; padding: 40px;"><p>Carregando histórico...</p></div>';
    modal.style.display = 'block';
    
    fetch('utils/historico_contratacao.php?numero=' + encodeURIComponent(numero))
        .then(response => response.text())
        .then(html => {
            conteudo.innerHTML = html;
        })
        .catch(() => {
            conteudo.innerHTML = '<div style="padding: 40px; text-align: center;">Erro ao carregar histórico</div>';
        });
}


/**
 * Filtrar por limite de registros
 */
function filtrarPorLimite(limite) {
    const url = new URL(window.location);
    url.searchParams.set('limite', limite);
    url.searchParams.set('secao', 'lista-contratacoes');
    window.location.href = url.toString();
}

// ==================== FUNÇÕES DE RELATÓRIOS ====================

/**
 * Gerar relatório PCA
 */
function gerarRelatorioPCA(tipo) {
    const modal = document.getElementById('modalRelatorioPCA');
    const titulo = document.getElementById('tituloRelatorioPCA');
    document.getElementById('tipo_relatorio_pca').value = tipo;
    
    // Resetar formulário
    document.getElementById('formRelatorioPCA').reset();
    document.getElementById('pca_data_inicial').value = new Date().getFullYear() + '-01-01';
    document.getElementById('pca_data_final').value = new Date().toISOString().split('T')[0];
    document.getElementById('pca_graficos').checked = true;
    
    // Configurar título e visibilidade dos campos
    switch(tipo) {
        case 'categoria':
            titulo.textContent = 'Relatório por Categoria';
            document.getElementById('filtroCategoriaPCA').style.display = 'block';
            document.getElementById('filtroAreaPCA').style.display = 'block';
            document.getElementById('filtroSituacaoPCA').style.display = 'block';
            break;
            
        case 'area':
            titulo.textContent = 'Relatório por Área Requisitante';
            document.getElementById('filtroCategoriaPCA').style.display = 'block';
            document.getElementById('filtroAreaPCA').style.display = 'block';
            document.getElementById('filtroSituacaoPCA').style.display = 'block';
            break;
            
        case 'prazos':
            titulo.textContent = 'Relatório de Análise de Prazos';
            document.getElementById('filtroCategoriaPCA').style.display = 'block';
            document.getElementById('filtroAreaPCA').style.display = 'block';
            document.getElementById('filtroSituacaoPCA').style.display = 'none';
            break;
            
        case 'financeiro':
            titulo.textContent = 'Relatório Financeiro do PCA';
            document.getElementById('filtroCategoriaPCA').style.display = 'block';
            document.getElementById('filtroAreaPCA').style.display = 'block';
            document.getElementById('filtroSituacaoPCA').style.display = 'block';
            break;
    }
    
    modal.style.display = 'block';
}

/**
 * Fechar modal genérico
 */
function fecharModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// ==================== SISTEMA DE BACKUP ====================

/**
 * Executar backup manual
 */
function executarBackup(tipo) {
    const button = document.getElementById(`btn-backup-${tipo === 'database' ? 'db' : 'files'}`);
    const statusDiv = document.getElementById('backup-status');
    const progressBar = document.getElementById('backup-progress-bar');
    const messageDiv = document.getElementById('backup-message');
    
    // Desabilitar botão e mostrar progresso
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Executando...';
    statusDiv.style.display = 'block';
    statusDiv.style.background = '#e3f2fd';
    statusDiv.style.border = '1px solid #2196f3';
    
    // Simular progresso
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += Math.random() * 20;
        if (progress > 90) progress = 90;
        progressBar.style.width = progress + '%';
    }, 1000);
    
    // Fazer requisição para executar backup
    fetch('api/backup_api_simple.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: 'executar_backup',
            tipo: tipo
        })
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        
        if (data.sucesso) {
            statusDiv.style.background = '#e8f5e8';
            statusDiv.style.border = '1px solid #4caf50';
            messageDiv.innerHTML = `✅ Backup ${tipo} concluído com sucesso!<br>
                <small>Arquivo: ${data.arquivo || 'N/A'} | Tamanho: ${data.tamanho_formatado || 'N/A'}</small>`;
            
            // Atualizar estatísticas e histórico imediatamente
            atualizarEstatisticasBackup();
            atualizarHistoricoBackups();
            
            // Atualizar novamente após 1 segundo para garantir
            setTimeout(() => {
                atualizarHistoricoBackups();
                atualizarEstatisticasBackup();
            }, 1000);
            
        } else {
            statusDiv.style.background = '#ffebee';
            statusDiv.style.border = '1px solid #f44336';
            messageDiv.innerHTML = `❌ Erro no backup: ${data.erro || 'Erro desconhecido'}`;
        }
        
        // Reabilitar botão
        setTimeout(() => {
            button.disabled = false;
            button.innerHTML = getButtonIcon(tipo) + ' ' + getButtonText(tipo);
            lucide.createIcons();
        }, 3000);
        
        // Esconder status após delay
        setTimeout(() => {
            statusDiv.style.display = 'none';
            progressBar.style.width = '0%';
        }, 10000);
        
    })
    .catch(error => {
        clearInterval(progressInterval);
        console.error('Erro:', error);
        
        statusDiv.style.background = '#ffebee';
        statusDiv.style.border = '1px solid #f44336';
        messageDiv.innerHTML = '❌ Erro de comunicação com o servidor';
        
        button.disabled = false;
        button.innerHTML = getButtonIcon(tipo) + ' ' + getButtonText(tipo);
        lucide.createIcons();
    });
}

/**
 * Obter ícone do botão de backup
 */
function getButtonIcon(tipo) {
    switch(tipo) {
        case 'database': return '<i data-lucide="database"></i>';
        case 'arquivos': return '<i data-lucide="folder"></i>';
        default: return '<i data-lucide="shield"></i>';
    }
}

/**
 * Obter texto do botão de backup
 */
function getButtonText(tipo) {
    switch(tipo) {
        case 'database': return 'Backup do Banco de Dados';
        case 'arquivos': return 'Backup dos Arquivos';
        default: return 'Backup';
    }
}

/**
 * Atualizar estatísticas de backup
 */
function atualizarEstatisticasBackup() {
    fetch('api/backup_api_simple.php?acao=estatisticas')
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            document.getElementById('ultimo-backup').textContent = data.ultimo_backup || 'Nunca';
            document.getElementById('backups-mes').textContent = data.backups_mes || '0';
            document.getElementById('tamanho-backups').textContent = data.tamanho_total || '0 MB';
            
            // Atualizar status do sistema
            const statusElement = document.getElementById('status-sistema');
            if (data.sistema_ok) {
                statusElement.innerHTML = '🟢 Online';
                statusElement.style.color = '#27ae60';
            } else {
                statusElement.innerHTML = '🔴 Problemas';
                statusElement.style.color = '#e74c3c';
            }
        }
    })
    .catch(error => {
        console.error('Erro ao carregar estatísticas:', error);
    });
}

/**
 * Atualizar histórico de backups
 */
function atualizarHistoricoBackups() {
    const loadingDiv = document.getElementById('loading-backups');
    const tabelaDiv = document.getElementById('tabela-backups');
    const tbody = document.getElementById('tbody-backups');
    
    loadingDiv.style.display = 'block';
    tabelaDiv.style.display = 'none';
    
    fetch('api/backup_api_simple.php?acao=historico')
    .then(response => response.json())
    .then(data => {
        if (data.sucesso && data.backups) {
            tbody.innerHTML = '';
            
            data.backups.forEach(backup => {
                const row = document.createElement('tr');
                
                // Status badge
                let statusBadge = '';
                if (backup.status === 'sucesso') {
                    statusBadge = '<span style="background: #e8f5e8; color: #2e7d32; padding: 4px 8px; border-radius: 12px; font-size: 12px;">✅ Sucesso</span>';
                } else if (backup.status === 'erro') {
                    statusBadge = '<span style="background: #ffebee; color: #c62828; padding: 4px 8px; border-radius: 12px; font-size: 12px;">❌ Erro</span>';
                } else {
                    statusBadge = '<span style="background: #fff3e0; color: #f57c00; padding: 4px 8px; border-radius: 12px; font-size: 12px;">⏳ Em andamento</span>';
                }
                
                // Tipo badge
                let tipoBadge = `<span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px;">${backup.tipo}</span>`;
                
                row.innerHTML = `
                    <td>${formatarDataHora(backup.inicio)}</td>
                    <td>${tipoBadge}</td>
                    <td>${statusBadge}</td>
                    <td>${backup.tamanho_formatado || '-'}</td>
                    <td>${backup.tempo_execucao ? backup.tempo_execucao + 's' : '-'}</td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            ${backup.status === 'sucesso' ? `
                                <button onclick="verificarBackup(${backup.id})" class="btn-acao btn-ver" title="Verificar integridade">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                </button>
                            ` : ''}
                            ${backup.arquivo_database ? `
                                <button onclick="downloadBackup('${backup.arquivo_database}')" class="btn-acao btn-historico" title="Download DB">
                                    <i data-lucide="database" style="width: 14px; height: 14px;"></i>
                                </button>
                            ` : ''}
                            ${backup.arquivo_files ? `
                                <button onclick="downloadBackup('${backup.arquivo_files}')" class="btn-acao btn-historico" title="Download Files">
                                    <i data-lucide="folder" style="width: 14px; height: 14px;"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
            
            lucide.createIcons();
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #7f8c8d;">Nenhum backup encontrado</td></tr>';
        }
        
        loadingDiv.style.display = 'none';
        tabelaDiv.style.display = 'block';
    })
    .catch(error => {
        console.error('Erro ao carregar histórico:', error);
        loadingDiv.style.display = 'none';
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #e74c3c;">Erro ao carregar dados</td></tr>';
        tabelaDiv.style.display = 'block';
    });
}

/**
 * Formatar data e hora
 */
function formatarDataHora(dataISO) {
    const data = new Date(dataISO);
    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Verificar integridade de um backup
 */
function verificarBackup(backupId) {
    if (!confirm('Verificar a integridade deste backup? Esta operação pode demorar alguns minutos.')) {
        return;
    }
    
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" style="width: 14px; height: 14px; animation: spin 1s linear infinite;"></i>';
    
    fetch('api/backup_api_simple.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: 'verificar_integridade',
            backup_id: backupId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            if (data.integro) {
                alert('✅ Backup verificado com sucesso! Todos os arquivos estão íntegros.');
            } else {
                alert('❌ Problemas encontrados no backup:\n' + (data.erros ? data.erros.join('\n') : 'Erro desconhecido'));
            }
        } else {
            alert('❌ Erro ao verificar backup: ' + (data.erro || 'Erro desconhecido'));
        }
        
        button.disabled = false;
        button.innerHTML = originalHTML;
        lucide.createIcons();
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro de comunicação com o servidor');
        button.disabled = false;
        button.innerHTML = originalHTML;
        lucide.createIcons();
    });
}

/**
 * Download de arquivo de backup
 */
function downloadBackup(nomeArquivo) {
    if (!confirm(`Fazer download do arquivo ${nomeArquivo}?`)) {
        return;
    }
    
    window.open(`backup_api.php?acao=download&arquivo=${encodeURIComponent(nomeArquivo)}`, '_blank');
}

/**
 * Limpar backups antigos
 */
function limparBackupsAntigos() {
    if (!confirm('Limpar backups antigos conforme política de retenção? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    const button = event.target;
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Limpando...';
    
    fetch('api/backup_api_simple.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: 'limpar_antigos'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            alert(`✅ Limpeza concluída! ${data.arquivos_removidos || 0} arquivos removidos.`);
            atualizarHistoricoBackups();
            atualizarEstatisticasBackup();
        } else {
            alert('❌ Erro na limpeza: ' + (data.erro || 'Erro desconhecido'));
        }
        
        button.disabled = false;
        button.innerHTML = originalHTML;
        lucide.createIcons();
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro de comunicação com o servidor');
        button.disabled = false;
        button.innerHTML = originalHTML;
        lucide.createIcons();
    });
}

/**
 * Mostrar ajuda sobre automação
 */
function mostrarAjudaAutomacao() {
    alert('📋 Para automação no Windows/XAMPP:\n\n1. Abrir "Agendador de Tarefas"\n2. Criar Tarefa Básica\n3. Configurar execução diária\n4. Programa: C:\\xampp\\php\\php.exe\n5. Argumentos: C:\\xampp\\htdocs\\sistema_licitacao\\cron_backup.php --tipo=database\n\nPara mais detalhes, consulte o arquivo INSTALACAO_BACKUP.md');
}

/**
 * Gerenciar arquivos de backup
 */
function gerenciarArquivos() {
    const modal = document.getElementById('modalDetalhes');
    const conteudo = document.getElementById('conteudoDetalhes');
    
    // Criar interface para gerenciar arquivos
    conteudo.innerHTML = `
        <div style="padding: 20px;">
            <h3 style="margin: 0 0 20px 0; color: #2c3e50; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="folder-open"></i> Gerenciador de Arquivos de Backup
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px;">
                    <h4 style="margin: 0 0 10px 0; color: #495057;">Banco de Dados</h4>
                    <div id="arquivos-database">
                        <div style="text-align: center; padding: 20px; color: #6c757d;">
                            <i data-lucide="loader-2" style="width: 24px; height: 24px; animation: spin 1s linear infinite;"></i>
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
                
                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px;">
                    <h4 style="margin: 0 0 10px 0; color: #495057;">Arquivos</h4>
                    <div id="arquivos-files">
                        <div style="text-align: center; padding: 20px; color: #6c757d;">
                            <i data-lucide="loader-2" style="width: 24px; height: 24px; animation: spin 1s linear infinite;"></i>
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #dee2e6;">
                <div style="display: flex; gap: 10px;">
                    <button onclick="abrirDiretorioBackups()" class="btn-info">
                        <i data-lucide="external-link"></i> Abrir Pasta de Backups
                    </button>
                    <button onclick="verificarEspaco()" class="btn-secondary">
                        <i data-lucide="hard-drive"></i> Verificar Espaço
                    </button>
                </div>
                <button onclick="fecharModalDetalhes()" class="btn-primary">
                    <i data-lucide="x"></i> Fechar
                </button>
            </div>
        </div>
    `;
    
    modal.style.display = 'block';
    lucide.createIcons();
    
    // Carregar lista de arquivos
    carregarListaArquivos();
}

/**
 * Carregar lista de arquivos
 */
function carregarListaArquivos() {
    // Simular carregamento de arquivos (em produção, usar uma API real)
    setTimeout(() => {
        const arquivosDB = document.getElementById('arquivos-database');
        const arquivosFiles = document.getElementById('arquivos-files');
        
        if (arquivosDB) {
            arquivosDB.innerHTML = `
                <div style="max-height: 200px; overflow-y: auto; display: flex; align-items: center; justify-content: center; color: #6c757d; text-align: center;">
                    <div>
                        <i data-lucide="database" style="width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>Nenhum backup de banco encontrado<br><small>Execute um backup para visualizar os arquivos</small></p>
                    </div>
                </div>
            `;
            lucide.createIcons();
        }
        
        if (arquivosFiles) {
            arquivosFiles.innerHTML = `
                <div style="max-height: 200px; overflow-y: auto; display: flex; align-items: center; justify-content: center; color: #6c757d; text-align: center;">
                    <div>
                        <i data-lucide="folder" style="width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>Nenhum backup de arquivos encontrado<br><small>Execute um backup para visualizar os arquivos</small></p>
                    </div>
                </div>
            `;
            lucide.createIcons();
        }
        
    }, 1000);
}

/**
 * Abrir diretório de backups
 */
function abrirDiretorioBackups() {
    if (confirm('Abrir a pasta de backups no explorador de arquivos?')) {
        // Em um ambiente real, isso seria feito via API do sistema
        alert('🗂️ Pasta de backups está localizada em:\n\nC:\\xampp\\htdocs\\sistema_licitacao\\backups\\');
    }
}

/**
 * Abrir modal de criar licitação
 */
function abrirModalCriarLicitacao() {
    const modal = document.getElementById('modalCriarLicitacao');
    
    // Limpar formulário
    modal.querySelector('form').reset();
    
    // Definir ano atual
    modal.querySelector('input[name="ano"]').value = new Date().getFullYear();
    
    // Mostrar modal
    modal.style.display = 'block';
    
    // Focar no primeiro campo
    setTimeout(() => {
        modal.querySelector('#nup_criar').focus();
    }, 100);
}

/**
 * Verificar espaço em disco
 */
function verificarEspaco() {
    alert('💾 Verificação de Espaço em Disco:\n\n' +
          'Esta funcionalidade requer implementação de API no servidor.\n' +
          'Entre em contato com o administrador do sistema para verificar o espaço em disco.');
}

// ==================== ATUALIZAÇÕES RECENTES ====================

/**
 * Atualizar lista de atividades recentes
 */
function atualizarAtividadesRecentes() {
    const button = event.target;
    const originalHTML = button.innerHTML;
    
    // Mostrar loading
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" style="width: 12px; height: 12px; animation: spin 1s linear infinite;"></i> Atualizando...';
    
    // Simular requisição (em produção, fazer fetch para uma API)
    setTimeout(() => {
        // Recarregar a página para atualizar os dados
        window.location.reload();
    }, 1000);
}


// ==================== EVENT LISTENERS ====================

/**
 * Inicialização quando DOM estiver carregado
 */
document.addEventListener('DOMContentLoaded', function() {
    // Restaurar estado da sidebar
    restoreSidebarState();
    
    // Event listener para redimensionamento
    window.addEventListener('resize', handleResize);
    
    // Inicializar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Inicializar gráficos se Chart.js estiver disponível
    if (typeof Chart !== 'undefined') {
        initCharts();
    }
    
    // Event listener para formulário de relatório PCA
    const formRelatorioPCA = document.getElementById('formRelatorioPCA');
    if (formRelatorioPCA) {
        formRelatorioPCA.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const params = new URLSearchParams();
            
            for (const [key, value] of formData) {
                if (value) params.append(key, value);
            }
            
            const formato = formData.get('formato');
            const url = 'relatorios/gerar_relatorio_planejamento.php?' + params.toString();
            
            if (formato === 'html') {
                // Abrir em nova aba
                window.open(url, '_blank');
            } else {
                // Download direto
                window.location.href = url;
            }
            
            fecharModal('modalRelatorioPCA');
        });
    }
});

/**
 * Fechar modais ao clicar fora
 */
window.onclick = function(event) {
    const modalPCA = document.getElementById('modalRelatorioPCA');
    const modalDetalhes = document.getElementById('modalDetalhes');
    
    if (event.target == modalPCA) {
        fecharModal('modalRelatorioPCA');
    } else if (event.target == modalDetalhes) {
        fecharModalDetalhes();
    }
};

/**
 * Garantir inicialização do construtor de gráficos
 */
function ensureConstrutorGraficos() {
    // Verificar se estamos na seção de relatórios
    const relatoriosSection = document.getElementById('relatorios');
    if (!relatoriosSection || !relatoriosSection.classList.contains('active')) {
        return;
    }
    
    // Verificar se os elementos existem
    const tipoGrafico = document.getElementById('tipoGrafico');
    const campoY = document.getElementById('campoY');
    const grupoY = document.getElementById('grupoValorY');
    
    if (!tipoGrafico || !campoY || !grupoY) {
        console.log('Elementos do construtor não encontrados');
        return;
    }
    
    console.log('Forçando visibilidade do construtor de gráficos...');
    
    // Forçar visibilidade do campo Y
    grupoY.style.display = 'block !important';
    grupoY.style.visibility = 'visible !important';
    grupoY.style.opacity = '1';
    
    campoY.style.display = 'block !important';
    campoY.style.visibility = 'visible !important';
    campoY.style.opacity = '1';
    
    // Tentar inicializar o construtor
    if (typeof window.initConstrutorGraficos === 'function') {
        window.initConstrutorGraficos();
    } else if (typeof ConstrutorGraficos !== 'undefined' && !window.construtorGraficos) {
        window.construtorGraficos = new ConstrutorGraficos();
    }
}

// Chamar a função após um delay para garantir que os elementos estejam prontos
setTimeout(ensureConstrutorGraficos, 1000);

// Também chamar quando a página terminar de carregar
window.addEventListener('load', ensureConstrutorGraficos);

// Adicionar event listener para o botão de relatórios
document.addEventListener('DOMContentLoaded', function() {
    const botaoRelatorios = document.querySelector('button[onclick*="showSection(\'relatorios\')"]');
    if (botaoRelatorios) {
        botaoRelatorios.addEventListener('click', function() {
            setTimeout(ensureConstrutorGraficos, 300);
        });
    }
});