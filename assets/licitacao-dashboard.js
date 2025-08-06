/**
 * Licitação Dashboard JavaScript - Sistema CGLIC
 * Funcionalidades do painel de controle de licitações
 * Inclui sistema de importação e consulta de andamentos
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
        
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

// ==================== NAVEGAÇÃO E INTERFACE ====================

// Variável global para armazenar instâncias dos gráficos
window.chartInstances = [];

/**
 * Navegação da Sidebar
 */
function showSection(sectionId) {
    // Esconder todas as seções
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });

    // Remover classe ativa de todos os nav-items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });

    // Mostrar seção selecionada
    document.getElementById(sectionId).classList.add('active');

    // Ativar nav-item clicado
    const clickedElement = event.target.closest('.nav-item');
    if (clickedElement) {
        clickedElement.classList.add('active');
    }

    // Atualizar URL para manter seção ativa na paginação
    const url = new URL(window.location);
    const secaoAtual = url.searchParams.get('secao');
    
    // Só resetar página se estivermos mudando de seção
    if (secaoAtual !== sectionId) {
        url.searchParams.set('secao', sectionId);
        url.searchParams.set('pagina', '1');
        window.history.pushState({}, '', url);
    }
}

function formatarValorCorreto(valor) {
    if (!valor || valor === null || valor === undefined) {
        return 'R$ 0,00';
    }

    const numero = typeof valor === 'string' ? parseFloat(valor) : valor;

    if (isNaN(numero)) {
        return 'R$ 0,00';
    }

    return numero.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ==================== ANDAMENTOS ====================

/**
 * Abrir modal de importação de andamentos
 */
function abrirModalImportarAndamentos(nup) {
    console.log('=== ABRINDO MODAL DE IMPORTAÇÃO ===');
    console.log('NUP:', nup);
    console.log('Todos os modais na página:', document.querySelectorAll('.modal'));

    // Verificar se elementos existem
    const modalElement = document.getElementById('modalImportarAndamentos');
    const nupElement = document.getElementById('nupSelecionado');

    console.log('Modal encontrado:', modalElement);
    console.log('Elemento NUP encontrado:', nupElement);

    if (!modalElement) {
        console.error('Modal modalImportarAndamentos não encontrado');
        console.log('Todos os elementos com ID:', document.querySelectorAll('[id*="modal"]'));
        alert('Erro: Modal não encontrado. Verifique o console para mais detalhes.');
        return;
    }

    if (!nupElement) {
        console.error('Elemento nupSelecionado não encontrado');
        console.log('Elementos dentro do modal:', modalElement.querySelectorAll('*[id]'));
        alert('Erro: Elemento NUP não encontrado. Verifique o console para mais detalhes.');
        return;
    }

    console.log("Definindo texto do NUP...");
    nupElement.textContent = nup;
    
    // Adicionar classe show primeiro (para que o CSS .modal.show funcione)
    modalElement.classList.add("show");
    modalElement.style.display = "block";
    
    console.log("Modal exibido. Display:", modalElement.style.display);
    console.log("Classes do modal:", modalElement.classList.toString());
    setTimeout(() => {
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            console.log('Recriando ícones Lucide...');
            lucide.createIcons();
        }
    }, 100);

    console.log('=== FIM DA FUNÇÃO ===');
}

/**
 * Consultar andamentos de um processo
 */
function consultarAndamentos(nup) {
    console.log('Consultando andamentos para NUP:', nup);

    // Verificar se modal existe
    const modalElement = document.getElementById('modalVisualizarAndamentos');
    const conteudoElement = document.getElementById('conteudoAndamentos');

    if (!modalElement) {
        console.error('Modal modalVisualizarAndamentos não encontrado');
        alert('Erro: Modal de visualização não encontrado');
        return;
    }

    if (!conteudoElement) {
        console.error('Elemento conteudoAndamentos não encontrado');
        alert('Erro: Conteúdo do modal não encontrado');
        return;
    }

    // Atualizar NUP no header do modal
    const nupDisplayElement = document.getElementById('nup-display');
    if (nupDisplayElement) {
        nupDisplayElement.textContent = 'NUP: ' + nup;
    }

    // Abrir modal
    modalElement.classList.add('show');
    modalElement.style.display = 'block';
    conteudoElement.innerHTML = '<div style="text-align: center; padding: 20px;"><i data-lucide="loader" style="width: 32px; height: 32px; animation: spin 1s linear infinite;"></i><p>Carregando andamentos...</p></div>';

    // Recriar ícones
    setTimeout(() => {
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    }, 100);

    // Buscar dados
    fetch('api/consultar_andamentos.php?nup=' + encodeURIComponent(nup) + '&calcular_tempo=true')
        .then(response => response.json())
        .then(data => {
            console.log('Dados de andamentos:', data);

            if (data.success) {
                if (data.total === 0) {
                    conteudoElement.innerHTML = '<div style="text-align: center; padding: 40px; color: #7f8c8d;"><i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: 20px;"></i><h3 style="margin: 0 0 10px 0;">Nenhum andamento encontrado</h3><p style="margin: 0;">Não há dados de andamentos para este NUP.</p></div>';
                } else {
                    // Atualizar dados no novo modal
                    atualizarModalAndamentos(data, nup);
                    
                    // Gerar HTML rico com timeline dos andamentos
                    let html = generateAndamentosTimeline(data, nup);
                    conteudoElement.innerHTML = html;
                }
            } else {
                conteudoElement.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><h3>Erro</h3><p>' + data.message + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            conteudoElement.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><h3>Erro de conexão</h3><p>Não foi possível consultar os andamentos.</p></div>';
        })
        .finally(() => {
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        });
}

/**
 * Atualizar dados no novo modal de andamentos
 */
function atualizarModalAndamentos(data, nup) {
    console.log('Atualizando modal de andamentos:', { data, nup });
    
    // Buscar informações da licitação no sistema
    const licitacao = buscarLicitacaoPorNup(nup);
    console.log('Licitação encontrada:', licitacao);
    
    // Atualizar NUP e Status
    const nupDisplay = document.getElementById('nup-display');
    const statusDisplay = document.getElementById('status-display');
    
    if (nupDisplay) {
        nupDisplay.textContent = `NUP: ${nup}`;
    }
    
    if (statusDisplay) {
        const status = licitacao ? licitacao.situacao : 'N/D';
        statusDisplay.textContent = `Status: ${status}`;
    }
    
    // Processar dados de andamentos de forma mais robusta
    let andamentos = [];
    let totalAndamentos = 0;
    let processo = null;
    
    if (data && data.data && Array.isArray(data.data)) {
        processo = data.data[0];
        andamentos = processo?.andamentos || [];
        
        // Calcular total de andamentos mais precisamente
        totalAndamentos = data.total_andamentos_individuais || 
                         data.total_andamentos || 
                         andamentos.length || 0;
    } else if (data && data.andamentos) {
        // Formato alternativo dos dados
        andamentos = data.andamentos;
        totalAndamentos = andamentos.length;
    }
    
    console.log('Andamentos processados:', { andamentos, totalAndamentos });
    
    // Calcular estatísticas de forma mais robusta
    const unidadesUnicas = [...new Set(andamentos.map(a => a.unidade).filter(u => u && u.trim()))];
    
    // Calcular tempo médio entre andamentos
    let tempoMedio = 0;
    if (andamentos.length > 1) {
        const datas = andamentos
            .map(a => new Date(a.data_hora))
            .filter(d => !isNaN(d.getTime()))
            .sort((a, b) => a - b);
        
        if (datas.length > 1) {
            const tempoTotal = datas[datas.length - 1] - datas[0];
            tempoMedio = Math.round(tempoTotal / (1000 * 60 * 60 * 24)); // em dias
        }
    }
    
    // Obter data da última atualização
    const ultimaData = andamentos.length > 0 ? 
        Math.max(...andamentos.map(a => new Date(a.data_hora).getTime())) : null;
    
    // Atualizar estatísticas com verificação de existência dos elementos
    const totalAndamentosEl = document.getElementById('totalAndamentos');
    const tempoMedioEl = document.getElementById('tempoMedio');
    const unidadesEnvolvidasEl = document.getElementById('unidadesEnvolvidas');
    const ultimaAtualizacaoEl = document.getElementById('ultimaAtualizacao');
    
    if (totalAndamentosEl) totalAndamentosEl.textContent = totalAndamentos;
    if (tempoMedioEl) tempoMedioEl.textContent = tempoMedio > 0 ? `${tempoMedio} dias` : '-';
    if (unidadesEnvolvidasEl) unidadesEnvolvidasEl.textContent = unidadesUnicas.length || '-';
    if (ultimaAtualizacaoEl) {
        ultimaAtualizacaoEl.textContent = ultimaData ? 
            new Date(ultimaData).toLocaleDateString('pt-BR') : '-';
    }
    
    // Atualizar informações do processo com verificação de existência
    const modalidadeInfoEl = document.getElementById('modalidadeInfo');
    const pregoeiroInfoEl = document.getElementById('pregoeiroInfo');
    const valorInfoEl = document.getElementById('valorInfo');
    const dataAberturaInfoEl = document.getElementById('dataAberturaInfo');
    
    if (licitacao) {
        if (modalidadeInfoEl) modalidadeInfoEl.textContent = licitacao.modalidade || '-';
        if (pregoeiroInfoEl) pregoeiroInfoEl.textContent = licitacao.pregoeiro || '-';
        if (valorInfoEl) valorInfoEl.textContent = licitacao.valor_estimado ? formatarMoeda(licitacao.valor_estimado) : '-';
        if (dataAberturaInfoEl) dataAberturaInfoEl.textContent = licitacao.data_abertura ? formatarDataSimples(licitacao.data_abertura) : '-';
    } else {
        // Valores padrão se não encontrar a licitação
        if (modalidadeInfoEl) modalidadeInfoEl.textContent = '-';
        if (pregoeiroInfoEl) pregoeiroInfoEl.textContent = '-';
        if (valorInfoEl) valorInfoEl.textContent = '-';
        if (dataAberturaInfoEl) dataAberturaInfoEl.textContent = '-';
    }
    
    console.log('Modal atualizado com estatísticas:', {
        totalAndamentos,
        tempoMedio: tempoMedio > 0 ? `${tempoMedio} dias` : '-',
        unidadesEnvolvidas: unidadesUnicas.length,
        ultimaAtualizacao: ultimaData ? new Date(ultimaData).toLocaleDateString('pt-BR') : '-'
    });
}

/**
 * Buscar informações da licitação por NUP
 */
function buscarLicitacaoPorNup(nup) {
    console.log('Buscando licitação por NUP:', nup);
    
    // Tentar diferentes seletores para encontrar a tabela
    const possiveisSeletores = [
        '#resultadosLicitacoes table tbody',
        '#lista-licitacoes tbody',
        'table tbody',
        '.data-table tbody',
        '.licitacoes-table tbody'
    ];
    
    let tabela = null;
    for (const seletor of possiveisSeletores) {
        tabela = document.querySelector(seletor);
        if (tabela) {
            console.log(`Tabela encontrada com seletor: ${seletor}`);
            break;
        }
    }
    
    if (!tabela) {
        console.log('Nenhuma tabela encontrada');
        return null;
    }
    
    const linhas = tabela.querySelectorAll('tr');
    console.log(`Procurando NUP ${nup} em ${linhas.length} linhas`);
    
    for (let linha of linhas) {
        // Tentar diferentes formas de encontrar o NUP
        let celulaNup = linha.querySelector('td:first-child strong') || 
                       linha.querySelector('td:first-child') ||
                       linha.querySelector('strong');
        
        // Se não encontrou, tentar procurar em todas as células
        if (!celulaNup) {
            const todasCelulas = linha.querySelectorAll('td');
            for (let celula of todasCelulas) {
                if (celula.textContent.includes(nup)) {
                    celulaNup = celula;
                    break;
                }
            }
        }
        
        if (celulaNup && celulaNup.textContent.trim().includes(nup)) {
            console.log('NUP encontrado na tabela');
            // Extrair dados da linha
            const colunas = linha.querySelectorAll('td');
            console.log(`Encontradas ${colunas.length} colunas`);
            
            if (colunas.length >= 6) { // Mínimo 6 colunas para ter dados básicos
                try {
                    // Função auxiliar para extrair texto limpo
                    const extrairTexto = (coluna, indice) => {
                        if (!coluna || !colunas[indice]) return '';
                        
                        // Tentar span primeiro, depois texto direto
                        const span = colunas[indice].querySelector('span');
                        return span ? span.textContent.trim() : colunas[indice].textContent.trim();
                    };
                    
                    // Mapear colunas de forma mais flexível
                    const resultado = {
                        nup: nup,
                        numero_contratacao: extrairTexto(colunas[1], 1),
                        modalidade: extrairTexto(colunas[2], 2),
                        objeto: colunas[3] ? (colunas[3].getAttribute('title') || colunas[3].textContent.trim()) : '',
                        valor_estimado: colunas[4] ? extrairValorMonetario(colunas[4].textContent) : 0,
                        situacao: extrairTexto(colunas[5], 5),
                        pregoeiro: colunas[6] ? extrairTexto(colunas[6], 6) : '',
                        data_abertura: colunas[7] ? extrairTexto(colunas[7], 7) : ''
                    };
                    
                    console.log('Dados extraídos da licitação:', resultado);
                    return resultado;
                    
                } catch (error) {
                    console.error('Erro ao extrair dados da linha:', error);
                    return {
                        nup: nup,
                        numero_contratacao: '',
                        modalidade: '',
                        objeto: '',
                        valor_estimado: 0,
                        situacao: '',
                        pregoeiro: '',
                        data_abertura: ''
                    };
                }
            }
        }
    }
    
    console.log('NUP não encontrado na tabela');
    return null;
}

/**
 * Extrair valor monetário de string
 */
function extrairValorMonetario(texto) {
    const match = texto.match(/R\$\s*([\d.,]+)/);
    if (match) {
        return parseFloat(match[1].replace(/\./g, '').replace(',', '.'));
    }
    return 0;
}

/**
 * Formatar data simples
 */
function formatarDataSimples(data) {
    if (!data) return '-';
    
    try {
        const date = new Date(data);
        return date.toLocaleDateString('pt-BR');
    } catch (e) {
        return data;
    }
}

/**
 * Formatar moeda
 */
function formatarMoeda(valor) {
    if (!valor || valor === 0) return 'R$ 0,00';
    
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

/**
 * Gerar HTML para timeline de andamentos melhorada
 */
function generateAndamentosTimeline(data, nup) {
    let html = '';
    
    // Armazenar dados originais para filtros
    window.andamentosData = data;
    window.andamentosOriginais = data.data[0]?.andamentos || [];
    
    // Cabeçalho com resumo
    const processo = data.data[0];
    html += `
        <div style="background: linear-gradient(135deg, #64748b 0%, #475569 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="file-text"></i> NUP: ${nup}
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <div>
                    <strong>Total de Andamentos:</strong><br>
                    <span id="totalAndamentosAtual">${data.total_andamentos_individuais || 0}</span>
                </div>
                <div>
                    <strong>Período:</strong><br>
                    ${processo?.primeira_data ? formatarDataHora(processo.primeira_data) : 'N/A'} até ${processo?.ultima_data ? formatarDataHora(processo.ultima_data) : 'N/A'}
                </div>
                <div>
                    <strong>Unidades Envolvidas:</strong><br>
                    <span id="unidadesEnvolvidasAtual">${processo?.unidades_envolvidas?.length || 0}</span>
                </div>
                <div>
                    <strong>Tempo Total:</strong><br>
                    ${data.total_dias_geral || 0} dias
                </div>
            </div>
        </div>
    `;

    // Painel de filtros avançados - Layout compacto
    html += `
        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e9ecef; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <h5 style="margin: 0; color: #495057; display: flex; align-items: center; gap: 8px; font-size: 15px;">
                    <i data-lucide="filter" style="width: 16px; height: 16px;"></i> Filtros
                </h5>
                <button type="button" onclick="limparFiltrosAndamentos()" 
                        style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px; font-weight: 500; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(220,53,69,0.3);"
                        onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(220,53,69,0.4)'"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(220,53,69,0.3)'">
                    <i data-lucide="x" style="width: 12px; height: 12px;"></i> Limpar
                </button>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div>
                    <input type="text" id="filtroTexto" placeholder="Buscar na descrição..." 
                           style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 13px; transition: border-color 0.2s ease;"
                           oninput="aplicarFiltrosAndamentos()"
                           onfocus="this.style.borderColor='#007cba'; this.style.boxShadow='0 0 0 2px rgba(0,124,186,0.1)'"
                           onblur="this.style.borderColor='#ced4da'; this.style.boxShadow='none'">
                </div>
                
                <div>
                    <select id="filtroUnidade" style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 13px; transition: border-color 0.2s ease;"
                            onchange="aplicarFiltrosAndamentos()"
                            onfocus="this.style.borderColor='#007cba'"
                            onblur="this.style.borderColor='#ced4da'">
                        <option value=""><i data-lucide="clipboard" style="width: 14px; height: 14px; margin-right: 6px; vertical-align: middle;"></i>Todas as unidades</option>
                    </select>
                </div>
                
                <div>
                    <select id="filtroUsuario" style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 13px; transition: border-color 0.2s ease;"
                            onchange="aplicarFiltrosAndamentos()"
                            onfocus="this.style.borderColor='#007cba'"
                            onblur="this.style.borderColor='#ced4da'">
                        <option value=""><i data-lucide="user" style="width: 14px; height: 14px; margin-right: 6px; vertical-align: middle;"></i>Todos os usuários</option>
                    </select>
                </div>
                
                <div>
                    <select id="filtroPeriodo" style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 13px; transition: border-color 0.2s ease;"
                            onchange="aplicarFiltrosAndamentos()"
                            onfocus="this.style.borderColor='#007cba'"
                            onblur="this.style.borderColor='#ced4da'">
                        <option value=""><i data-lucide="calendar" style="width: 14px; height: 14px; margin-right: 6px; vertical-align: middle;"></i>Todo o período</option>
                        <option value="7">Últimos 7 dias</option>
                        <option value="30">Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                        <option value="365">Último ano</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid #f1f3f4;">
                <div style="font-size: 13px; color: #6c757d; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="info" style="width: 14px; height: 14px;"></i>
                    <span id="resultadosFiltro">Mostrando todos os andamentos</span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" onclick="exportarAndamentosFiltrados()" 
                            style="background: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(108,117,125,0.3);"
                            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(108,117,125,0.4)'; this.style.background='#5a6268'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(108,117,125,0.3)'; this.style.background='#6c757d'">
                        <i data-lucide="download" style="width: 12px; height: 12px;"></i> CSV
                    </button>
                    <button type="button" onclick="imprimirAndamentos()" 
                            style="background: #495057; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(73,80,87,0.3);"
                            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(73,80,87,0.4)'; this.style.background='#343a40'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(73,80,87,0.3)'; this.style.background='#495057'">
                        <i data-lucide="printer" style="width: 12px; height: 12px;"></i> PDF
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Resumo por unidade - Layout horizontal compacto
    if (data.resumo_tempo_por_unidade) {
        const unidadesCount = Object.keys(data.resumo_tempo_por_unidade).length;
        const showCollapsed = unidadesCount > 4; // Colapsar se muitas unidades
        
        html += `<div id="resumoUnidades" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e9ecef;">`;
        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h5 style="margin: 0; color: #495057; display: flex; align-items: center; gap: 6px; font-size: 15px;">
                    <i data-lucide="clock" style="width: 16px; height: 16px;"></i> Tempo por Unidade
                </h5>
                ${showCollapsed ? `
                    <button type="button" onclick="toggleResumoUnidades()" id="btnToggleResumo" 
                            style="background: #868e96; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer; display: flex; align-items: center; gap: 3px;">
                        <i data-lucide="eye" style="width: 12px; height: 12px;"></i> Ver Detalhes
                    </button>
                ` : ''}
            </div>
        `;
        
        if (showCollapsed) {
            // Versão resumida - apenas totais
            const totalDias = Object.values(data.resumo_tempo_por_unidade).reduce((acc, tempo) => acc + (tempo.dias || 0), 0);
            const totalUnidades = Object.keys(data.resumo_tempo_por_unidade).length;
            
            html += `
                <div id="resumoCompacto" style="display: flex; justify-content: space-around; align-items: center; background: white; padding: 12px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #007cba;">${totalDias}</div>
                        <div style="font-size: 12px; color: #6c757d;">Dias Total</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #28a745;">${totalUnidades}</div>
                        <div style="font-size: 12px; color: #6c757d;">Unidades</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 20px; font-weight: bold; color: #fd7e14;">${(totalDias/totalUnidades).toFixed(1)}</div>
                        <div style="font-size: 12px; color: #6c757d;">Média/Unidade</div>
                    </div>
                </div>
                
                <div id="resumoDetalhado" style="display: none; margin-top: 12px;">`;
        } else {
            html += '<div id="resumoDetalhado">';
        }
        
        html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px;">';
        
        for (const [unidade, tempoData] of Object.entries(data.resumo_tempo_por_unidade)) {
            const dias = tempoData.dias !== undefined ? tempoData.dias : 0;
            const periodos = tempoData.total_periodos || 1;
            const media = tempoData.media_dias_por_periodo !== undefined ? tempoData.media_dias_por_periodo : (dias / periodos);
            const cor = getCorUnidade(unidade);
            
            html += `
                <div style="background: white; padding: 10px; border-radius: 6px; border-left: 3px solid ${cor}; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-weight: 600; color: ${cor}; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; font-size: 13px;">
                        <i data-lucide="${getIconeUnidade(unidade)}" style="width: 14px; height: 14px;"></i>
                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${unidade}">${unidade}</span>
                    </div>
                    <div style="font-size: 12px; color: #6c757d; line-height: 1.3;">
                        <strong>${dias}d</strong> total • <strong>${periodos}</strong> estadia(s)<br>
                        Média: <strong>${media.toFixed(1)}d</strong>
                    </div>
                </div>
            `;
        }
        html += '</div></div></div>';
    }
    
    // Container da timeline - Layout otimizado
    html += `
        <div id="timelineContainer" style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef; max-height: 60vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; position: sticky; top: 0; background: white; z-index: 10; padding-bottom: 10px; border-bottom: 1px solid #f1f3f4;">
                <h5 style="margin: 0; color: #495057; display: flex; align-items: center; gap: 6px; font-size: 15px;">
                    <i data-lucide="git-commit" style="width: 16px; height: 16px;"></i> Timeline de Andamentos
                </h5>
                <div style="display: flex; gap: 8px;">
                    <button type="button" onclick="alternarVisualizacao()" id="btnVisualizacao" 
                            style="background: #adb5bd; color: #495057; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(173,181,189,0.3);"
                            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(173,181,189,0.4)'; this.style.background='#9ca3af'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(173,181,189,0.3)'; this.style.background='#adb5bd'">
                        <i data-lucide="list" style="width: 12px; height: 12px;"></i> Compacta
                    </button>
                </div>
            </div>
            <div id="timelineContent" style="max-height: calc(60vh - 60px); overflow-y: auto;">
                ${generateTimelineContent(processo?.andamentos || [])}
            </div>
        </div>
    `;
    
    // Inicializar filtros após renderização
    setTimeout(() => {
        inicializarFiltrosAndamentos();
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    }, 100);
    
    return html;
}

/**
 * Formatar data e hora para exibição
 */
function formatarDataHora(dataHora) {
    if (!dataHora) return 'N/A';
    try {
        const date = new Date(dataHora);
        return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
    } catch (e) {
        return dataHora;
    }
}

/**
 * Função de teste para debugar modais
 */
function testarModal() {
    console.log('=== TESTE DE MODAL ===');
    console.log('Tentando abrir modal de teste...');
    abrirModalImportarAndamentos('12345.123456/2024-99');
}

/**
 * Inicializar sistema de andamentos
 */
function initAndamentos() {
    console.log('=== INICIALIZANDO SISTEMA DE ANDAMENTOS ===');
    
    // Formulário de importação de andamentos
    const formElement = document.getElementById('formImportarAndamentos');
    if (formElement) {
        console.log('Formulário de importação encontrado');
        formElement.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const nupElement = document.getElementById('nupSelecionado');
            const nup = nupElement ? nupElement.textContent : '';

            console.log('NUP para importação:', nup);

            // Verificar se arquivo foi selecionado
            const arquivo = document.getElementById('arquivo_json').files[0];
            if (!arquivo) {
                alert('Por favor, selecione um arquivo JSON.');
                return;
            }

            // Adicionar NUP ao FormData
            formData.append('nup', nup);

            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-lucide="loader" style="animation: spin 1s linear infinite;"></i> Importando...';
            submitBtn.disabled = true;

            // Recriar ícones
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }

            // Enviar requisição
            fetch('api/importar_andamentos.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Resposta da importação:', data);

                    if (data.success) {
                        alert('Andamentos importados com sucesso!\n\n' +
                            'NUP: ' + (data.data.nup || nup) + '\n' +
                            'Processo ID: ' + (data.data.processo_id || 'N/A') + '\n' +
                            'Total esperados: ' + (data.data.total_esperados || '0') + '\n' +
                            'Total processados: ' + (data.data.total_processados || '0') + '\n' +
                            'Ação: ' + (data.data.acao || 'Importação'));

                        // Fechar modal e limpar formulário
                        fecharModal('modalImportarAndamentos');
                        this.reset();
                    } else {
                        alert('Erro ao importar andamentos:\n' + (data.message || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao processar requisição de importação.');
                })
                .finally(() => {
                    // Restaurar botão
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    if (typeof lucide !== 'undefined' && lucide.createIcons) {
                        lucide.createIcons();
                    }
                });
        });
    } else {
        console.error('Formulário formImportarAndamentos não encontrado');
    }
}

/**
 * Funções auxiliares para o modal de andamentos melhorado
 */

// Mapeamento de cores por tipo de unidade
function getCorUnidade(unidade) {
    const coresUnidades = {
        'CGLIC': '#007cba',
        'DIPLAN': '#28a745', 
        'DIPLI': '#dc3545',
        'SEGESP': '#fd7e14',
        'CONJUR': '#6f42c1',
        'TCU': '#20c997',
        'AGU': '#ffc107',
        'Secretário': '#6c757d'
    };
    
    // Buscar por palavra-chave na unidade
    for (const [key, cor] of Object.entries(coresUnidades)) {
        if (unidade.toUpperCase().includes(key)) {
            return cor;
        }
    }
    
    // Cor padrão baseada em hash da string
    let hash = 0;
    for (let i = 0; i < unidade.length; i++) {
        hash = unidade.charCodeAt(i) + ((hash << 5) - hash);
    }
    const cores = ['#007cba', '#28a745', '#dc3545', '#fd7e14', '#6f42c1', '#20c997'];
    return cores[Math.abs(hash) % cores.length];
}

// Mapeamento de ícones por tipo de unidade
function getIconeUnidade(unidade) {
    const iconesUnidades = {
        'CGLIC': 'building',
        'DIPLAN': 'calendar',
        'DIPLI': 'gavel', 
        'SEGESP': 'shield',
        'CONJUR': 'scale',
        'TCU': 'eye',
        'AGU': 'briefcase',
        'Secretário': 'crown'
    };
    
    for (const [key, icone] of Object.entries(iconesUnidades)) {
        if (unidade.toUpperCase().includes(key)) {
            return icone;
        }
    }
    
    return 'building-2'; // Ícone padrão
}

// Gerar conteúdo da timeline
function generateTimelineContent(andamentos, modoCompacto = false) {
    if (!andamentos || andamentos.length === 0) {
        return '<div style="text-align: center; padding: 40px; color: #6c757d;"><i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 15px;"></i><h4>Nenhum andamento encontrado</h4><p>Não há andamentos que correspondam aos filtros aplicados.</p></div>';
    }

    let html = '';
    
    if (modoCompacto) {
        // Visualização em lista compacta
        html += '<div style="background: #f8f9fa; border-radius: 8px; overflow: hidden;">';
        andamentos.forEach((andamento, index) => {
            const cor = getCorUnidade(andamento.unidade);
            const icone = getIconeUnidade(andamento.unidade);
            
            html += `
                <div style="display: flex; align-items: center; padding: 12px 16px; border-bottom: ${index === andamentos.length - 1 ? 'none' : '1px solid #dee2e6'}; background: ${index % 2 === 0 ? 'white' : '#f8f9fa'};">
                    <div style="width: 40px; height: 40px; background: ${cor}; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0;">
                        <i data-lucide="${icone}" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                            <div style="font-weight: 600; color: ${cor}; margin-right: 10px;">${andamento.unidade}</div>
                            <div style="font-size: 12px; color: #6c757d; white-space: nowrap;">
                                ${formatarDataHora(andamento.data_hora)}
                            </div>
                        </div>
                        <div style="color: #495057; font-size: 14px; line-height: 1.3; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${andamento.descricao}</div>
                        ${andamento.usuario ? `<div style="font-size: 12px; color: #6c757d; display: flex; align-items: center; gap: 4px;"><i data-lucide="user" style="width: 12px; height: 12px;"></i> ${andamento.usuario}</div>` : ''}
                    </div>
                </div>
            `;
        });
        html += '</div>';
    } else {
        // Visualização em timeline (padrão)
        html += '<div style="position: relative;">';
        
        // Linha vertical da timeline
        html += '<div style="position: absolute; left: 16px; top: 0; bottom: 0; width: 2px; background: linear-gradient(to bottom, #007cba, #dee2e6);"></div>';
        
        andamentos.forEach((andamento, index) => {
            const isLast = index === andamentos.length - 1;
            const cor = getCorUnidade(andamento.unidade);
            const icone = getIconeUnidade(andamento.unidade);
            
            html += `
                <div style="position: relative; padding-left: 50px; margin-bottom: ${isLast ? '0' : '15px'};">
                    <div style="position: absolute; left: 6px; top: 6px; width: 20px; height: 20px; background: ${cor}; color: white; border: 2px solid white; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; z-index: 2;">
                        <i data-lucide="${icone}" style="width: 12px; height: 12px;"></i>
                    </div>
                    <div style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); padding: 15px; border-radius: 8px; border-left: 3px solid ${cor}; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: all 0.2s ease;" onmouseover="this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)'; this.style.transform='translateY(-1px)'" onmouseout="this.style.boxShadow='0 2px 4px rgba(0,0,0,0.05)'; this.style.transform='translateY(0)'">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <div style="font-weight: 600; color: ${cor}; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                                ${andamento.unidade}
                            </div>
                            <div style="font-size: 11px; color: #6c757d; white-space: nowrap; margin-left: 10px; background: #e9ecef; padding: 2px 6px; border-radius: 8px;">
                                <i data-lucide="clock" style="width: 10px; height: 10px; margin-right: 2px;"></i>
                                ${formatarDataHora(andamento.data_hora)}
                            </div>
                        </div>
                        <div style="color: #495057; margin-bottom: 6px; line-height: 1.4; font-size: 13px;">${andamento.descricao}</div>
                        ${andamento.usuario ? `<div style="font-size: 11px; color: #6c757d; display: flex; align-items: center; gap: 4px; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f3f4;"><i data-lucide="user" style="width: 12px; height: 12px;"></i> <strong>Responsável:</strong> ${andamento.usuario}</div>` : ''}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    return html;
}

// Inicializar filtros
function inicializarFiltrosAndamentos() {
    if (!window.andamentosOriginais || window.andamentosOriginais.length === 0) {
        return;
    }

    // Popular select de unidades
    const unidades = [...new Set(window.andamentosOriginais.map(a => a.unidade))].sort();
    const selectUnidade = document.getElementById('filtroUnidade');
    if (selectUnidade) {
        selectUnidade.innerHTML = '<option value="">Todas as unidades</option>';
        unidades.forEach(unidade => {
            selectUnidade.innerHTML += `<option value="${unidade}">${unidade}</option>`;
        });
    }

    // Popular select de usuários
    const usuarios = [...new Set(window.andamentosOriginais.map(a => a.usuario).filter(u => u && u.trim() !== ''))].sort();
    const selectUsuario = document.getElementById('filtroUsuario');
    if (selectUsuario) {
        selectUsuario.innerHTML = '<option value="">Todos os usuários</option>';
        usuarios.forEach(usuario => {
            selectUsuario.innerHTML += `<option value="${usuario}">${usuario}</option>`;
        });
    }
}

// Aplicar filtros
function aplicarFiltrosAndamentos() {
    if (!window.andamentosOriginais || window.andamentosOriginais.length === 0) {
        return;
    }

    const filtroTexto = document.getElementById('filtroTexto')?.value.toLowerCase() || '';
    const filtroUnidade = document.getElementById('filtroUnidade')?.value || '';
    const filtroUsuario = document.getElementById('filtroUsuario')?.value || '';
    const filtroPeriodo = document.getElementById('filtroPeriodo')?.value || '';

    let andamentosFiltrados = window.andamentosOriginais.filter(andamento => {
        // Filtro de texto
        if (filtroTexto && !andamento.descricao.toLowerCase().includes(filtroTexto)) {
            return false;
        }

        // Filtro de unidade
        if (filtroUnidade && andamento.unidade !== filtroUnidade) {
            return false;
        }

        // Filtro de usuário
        if (filtroUsuario && andamento.usuario !== filtroUsuario) {
            return false;
        }

        // Filtro de período
        if (filtroPeriodo) {
            const diasLimite = parseInt(filtroPeriodo);
            const dataAndamento = new Date(andamento.data_hora);
            const dataLimite = new Date();
            dataLimite.setDate(dataLimite.getDate() - diasLimite);
            
            if (dataAndamento < dataLimite) {
                return false;
            }
        }

        return true;
    });

    // Atualizar timeline
    const timelineContent = document.getElementById('timelineContent');
    if (timelineContent) {
        const modoCompacto = document.getElementById('btnVisualizacao')?.innerHTML.includes('Timeline');
        timelineContent.innerHTML = generateTimelineContent(andamentosFiltrados, modoCompacto);
    }

    // Atualizar contadores
    const totalElement = document.getElementById('totalAndamentosAtual');
    if (totalElement) {
        totalElement.textContent = andamentosFiltrados.length;
    }

    const unidadesUnicas = [...new Set(andamentosFiltrados.map(a => a.unidade))].length;
    const unidadesElement = document.getElementById('unidadesEnvolvidasAtual');
    if (unidadesElement) {
        unidadesElement.textContent = unidadesUnicas;
    }

    // Atualizar texto de resultado
    const resultadoElement = document.getElementById('resultadosFiltro');
    if (resultadoElement) {
        const total = window.andamentosOriginais.length;
        const mostrados = andamentosFiltrados.length;
        
        if (mostrados === total) {
            resultadoElement.textContent = `Mostrando todos os ${total} andamentos`;
        } else {
            resultadoElement.textContent = `Mostrando ${mostrados} de ${total} andamentos`;
        }
    }

    // Recriar ícones
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
}

// Limpar filtros
function limparFiltrosAndamentos() {
    document.getElementById('filtroTexto').value = '';
    document.getElementById('filtroUnidade').value = '';
    document.getElementById('filtroUsuario').value = '';
    document.getElementById('filtroPeriodo').value = '';
    aplicarFiltrosAndamentos();
}

// Alternar visualização
function alternarVisualizacao() {
    const btn = document.getElementById('btnVisualizacao');
    const timelineContent = document.getElementById('timelineContent');
    
    if (!btn || !timelineContent) return;

    const modoCompacto = btn.innerHTML.includes('Lista Compacta');
    
    if (modoCompacto) {
        btn.innerHTML = '<i data-lucide="git-commit"></i> Timeline';
        timelineContent.innerHTML = generateTimelineContent(getCurrentFilteredAndamentos(), true);
    } else {
        btn.innerHTML = '<i data-lucide="list"></i> Lista Compacta';
        timelineContent.innerHTML = generateTimelineContent(getCurrentFilteredAndamentos(), false);
    }

    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
}

// Obter andamentos filtrados atuais
function getCurrentFilteredAndamentos() {
    if (!window.andamentosOriginais) return [];
    
    const filtroTexto = document.getElementById('filtroTexto')?.value.toLowerCase() || '';
    const filtroUnidade = document.getElementById('filtroUnidade')?.value || '';
    const filtroUsuario = document.getElementById('filtroUsuario')?.value || '';
    const filtroPeriodo = document.getElementById('filtroPeriodo')?.value || '';

    return window.andamentosOriginais.filter(andamento => {
        if (filtroTexto && !andamento.descricao.toLowerCase().includes(filtroTexto)) return false;
        if (filtroUnidade && andamento.unidade !== filtroUnidade) return false;
        if (filtroUsuario && andamento.usuario !== filtroUsuario) return false;
        
        if (filtroPeriodo) {
            const diasLimite = parseInt(filtroPeriodo);
            const dataAndamento = new Date(andamento.data_hora);
            const dataLimite = new Date();
            dataLimite.setDate(dataLimite.getDate() - diasLimite);
            if (dataAndamento < dataLimite) return false;
        }
        
        return true;
    });
}

// Exportar andamentos filtrados
function exportarAndamentosFiltrados() {
    const andamentos = getCurrentFilteredAndamentos();
    
    if (andamentos.length === 0) {
        alert('Não há andamentos para exportar com os filtros aplicados.');
        return;
    }

    // Criar CSV
    let csv = 'Data/Hora,Unidade,Usuário,Descrição\n';
    
    andamentos.forEach(andamento => {
        const dataHora = formatarDataHora(andamento.data_hora);
        const unidade = (andamento.unidade || '').replace(/"/g, '""');
        const usuario = (andamento.usuario || '').replace(/"/g, '""');
        const descricao = (andamento.descricao || '').replace(/"/g, '""');
        
        csv += `"${dataHora}","${unidade}","${usuario}","${descricao}"\n`;
    });

    // Download
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `andamentos_${new Date().toISOString().slice(0, 10)}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Toggle do resumo de unidades
function toggleResumoUnidades() {
    const resumoCompacto = document.getElementById('resumoCompacto');
    const resumoDetalhado = document.getElementById('resumoDetalhado');
    const btnToggle = document.getElementById('btnToggleResumo');
    
    if (!resumoCompacto || !resumoDetalhado || !btnToggle) return;
    
    const isDetalhado = resumoDetalhado.style.display !== 'none';
    
    if (isDetalhado) {
        resumoDetalhado.style.display = 'none';
        resumoCompacto.style.display = 'flex';
        btnToggle.innerHTML = '<i data-lucide="eye" style="width: 12px; height: 12px;"></i> Ver Detalhes';
    } else {
        resumoDetalhado.style.display = 'block';
        resumoCompacto.style.display = 'none';
        btnToggle.innerHTML = '<i data-lucide="eye-off" style="width: 12px; height: 12px;"></i> Resumir';
    }
    
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
}

// Imprimir andamentos
function imprimirAndamentos() {
    const andamentos = getCurrentFilteredAndamentos();
    
    if (andamentos.length === 0) {
        alert('Não há andamentos para imprimir com os filtros aplicados.');
        return;
    }

    // Criar conteúdo para impressão
    let conteudo = `
        <html>
        <head>
            <title>Andamentos do Processo</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #007cba; padding-bottom: 15px; }
                .andamento { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
                .unidade { font-weight: bold; color: #007cba; margin-bottom: 5px; }
                .data { font-size: 12px; color: #666; margin-bottom: 10px; }
                .descricao { margin-bottom: 8px; }
                .usuario { font-size: 12px; color: #666; font-style: italic; }
                @media print { body { margin: 10px; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Andamentos do Processo</h2>
                <p>Total de andamentos: ${andamentos.length} | Data da impressão: ${new Date().toLocaleDateString('pt-BR')}</p>
            </div>
    `;

    andamentos.forEach(andamento => {
        conteudo += `
            <div class="andamento">
                <div class="unidade">${andamento.unidade}</div>
                <div class="data">${formatarDataHora(andamento.data_hora)}</div>
                <div class="descricao">${andamento.descricao}</div>
                ${andamento.usuario ? `<div class="usuario">Responsável: ${andamento.usuario}</div>` : ''}
            </div>
        `;
    });

    conteudo += '</body></html>';

    // Abrir janela de impressão
    const janelaImpressao = window.open('', '_blank');
    janelaImpressao.document.write(conteudo);
    janelaImpressao.document.close();
    janelaImpressao.focus();
    janelaImpressao.print();
}

// Exportar funções para o escopo global
window.abrirModalImportarAndamentos = abrirModalImportarAndamentos;
window.consultarAndamentos = consultarAndamentos;
window.aplicarFiltrosAndamentos = aplicarFiltrosAndamentos;
window.limparFiltrosAndamentos = limparFiltrosAndamentos;
window.alternarVisualizacao = alternarVisualizacao;
window.exportarAndamentosFiltrados = exportarAndamentosFiltrados;
window.imprimirAndamentos = imprimirAndamentos;
window.toggleResumoUnidades = toggleResumoUnidades;
window.testarModal = testarModal;
window.showSection = showSection;
window.formatarValorCorreto = formatarValorCorreto;

// ==================== GRÁFICOS ====================

/**
 * Inicializar gráficos do dashboard com correções de altura
 */
function initCharts() {
    setTimeout(() => {
        // Verificar se os dados foram passados do PHP
        if (!window.dadosModalidade || !window.dadosPregoeiro ||
            !window.dadosMensal || !window.stats) {
            console.warn('Dados do dashboard não foram carregados do PHP');
            return;
        }

        const dadosModalidade = window.dadosModalidade;
        const dadosPregoeiro = window.dadosPregoeiro;
        const dadosMensal = window.dadosMensal;
        const stats = window.stats;

        // Limpar instâncias anteriores
        destroyAllCharts();

        // Configurações globais do Chart.js
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.legend.labels.boxWidth = 12;
        Chart.defaults.plugins.legend.labels.padding = 10;

        // Gráfico de Modalidades (Donut)
        const ctxModalidade = document.getElementById('chartModalidade');
        if (ctxModalidade) {
            const chartModalidade = new Chart(ctxModalidade, {
                type: 'doughnut',
                data: {
                    labels: dadosModalidade.map(item => item.modalidade),
                    datasets: [{
                        data: dadosModalidade.map(item => item.quantidade),
                        backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 20,
                            right: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            window.chartInstances.push(chartModalidade);
        }

        // Gráfico de Pregoeiros (Barras)
        const ctxPregoeiro = document.getElementById('chartPregoeiro');
        if (ctxPregoeiro) {
            const chartPregoeiro = new Chart(ctxPregoeiro, {
                type: 'bar',
                data: {
                    labels: dadosPregoeiro.map(item => {
                        // Truncar nomes muito longos
                        const nome = item.pregoeiro;
                        return nome.length > 15 ? nome.substring(0, 15) + '...' : nome;
                    }),
                    datasets: [{
                        label: 'Licitações',
                        data: dadosPregoeiro.map(item => item.quantidade),
                        backgroundColor: '#3498db',
                        borderColor: '#2980b9',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 10,
                            left: 10,
                            right: 10
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: function(tooltipItems) {
                                    // Mostrar nome completo no tooltip
                                    const index = tooltipItems[0].dataIndex;
                                    return dadosPregoeiro[index].pregoeiro;
                                }
                            }
                        }
                    }
                }
            });
            window.chartInstances.push(chartPregoeiro);
        }

        // Gráfico Mensal (Linha)
        const ctxMensal = document.getElementById('chartMensal');
        if (ctxMensal) {
            const chartMensal = new Chart(ctxMensal, {
                type: 'line',
                data: {
                    labels: dadosMensal.map(item => {
                        const [ano, mes] = item.mes.split('-');
                        const data = new Date(ano, mes - 1);
                        return data.toLocaleDateString('pt-BR', {
                            month: 'short',
                            year: 'numeric'
                        }).replace('.', '');
                    }),
                    datasets: [{
                        label: 'Licitações Criadas',
                        data: dadosMensal.map(item => item.quantidade),
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        pointBackgroundColor: '#e74c3c',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 10,
                            left: 10,
                            right: 10
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            window.chartInstances.push(chartMensal);
        }

        // Gráfico de Status (Donut)
        const ctxStatus = document.getElementById('chartStatus');
        if (ctxStatus) {
            const chartStatus = new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: ['Em Andamento', 'Homologadas', 'Fracassadas', 'Revogadas'],
                    datasets: [{
                        data: [
                            stats.em_andamento || 0,
                            stats.homologadas || 0,
                            stats.fracassadas || 0,
                            stats.revogadas || 0
                        ],
                        backgroundColor: ['#f39c12', '#27ae60', '#e74c3c', '#95a5a6'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 20,
                            right: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            window.chartInstances.push(chartStatus);
        }

        console.log('Gráficos inicializados com sucesso! Total de instâncias:', window.chartInstances.length);

        // Forçar redimensionamento após pequeno delay
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 100);

    }, 500);
}

/**
 * Destruir todas as instâncias de gráficos
 */
function destroyAllCharts() {
    if (window.chartInstances && window.chartInstances.length > 0) {
        window.chartInstances.forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        window.chartInstances = [];
    }
}

/**
 * Redimensionar todos os gráficos
 */
function resizeAllCharts() {
    if (window.chartInstances && window.chartInstances.length > 0) {
        window.chartInstances.forEach(chart => {
            if (chart && typeof chart.resize === 'function') {
                try {
                    chart.resize();
                } catch (error) {
                    console.warn('Erro ao redimensionar gráfico:', error);
                }
            }
        });
    }
}

// Função para redimensionar gráficos quando a janela muda
window.addEventListener('resize', () => {
    resizeAllCharts();
});

// Atualizar o event listener para o botão de voltar ao dashboard
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DOM CONTENT LOADED - LICITACAO DASHBOARD ===');
    
    // Reinicializar gráficos ao mudar de seção
    const originalShowSection = window.showSection;
    if (originalShowSection) {
        window.showSection = function(sectionId) {
            originalShowSection.call(this, sectionId);

            // Se voltou para o dashboard, redimensionar gráficos
            if (sectionId === 'dashboard') {
                setTimeout(() => {
                    resizeAllCharts();
                }, 100);
            }
        };
    }

    // Inicializar sistema de andamentos
    initAndamentos();
    
    // Verificar se modais existem
    const modalImportar = document.getElementById('modalImportarAndamentos');
    const modalVisualizar = document.getElementById('modalVisualizarAndamentos');
    console.log('Modal Importar encontrado:', !!modalImportar);
    console.log('Modal Visualizar encontrado:', !!modalVisualizar);
});

// ==================== FUNÇÕES DA TABELA ====================

/**
 * Filtrar licitações por situação
 */
function filtrarLicitacoes(situacao) {
    const rows = document.querySelectorAll('#lista-licitacoes tbody tr');

    rows.forEach(row => {
        if (situacao === '') {
            row.style.display = '';
        } else {
            const statusCell = row.querySelector('.status-badge');
            const status = statusCell.textContent.trim().toUpperCase().replace(' ', '_');

            if (status === situacao) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

/**
 * Exportar licitações para CSV
 */
function exportarLicitacoes() {
    const dados = [];
    const rows = document.querySelectorAll('#lista-licitacoes tbody tr');

    dados.push(['NUP', 'Modalidade', 'Número/Ano', 'Objeto', 'Valor Estimado', 'Situação', 'Pregoeiro', 'Data Abertura']);

    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            dados.push([
                cells[0].textContent.trim(),
                cells[1].textContent.trim(),
                cells[2].textContent.trim(),
                cells[3].textContent.trim(),
                cells[4].textContent.trim(),
                cells[5].textContent.trim(),
                cells[6].textContent.trim(),
                cells[7].textContent.trim()
            ]);
        }
    });

    let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
    dados.forEach(row => {
        csvContent += row.map(cell => '"' + cell + '"').join(';') + '\n';
    });

    const link = document.createElement('a');
    link.setAttribute('href', encodeURI(csvContent));
    link.setAttribute('download', 'licitacoes_' + new Date().toISOString().split('T')[0] + '.csv');
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ==================== FUNÇÕES DE RELATÓRIOS ====================

/**
 * Abrir modal de relatório
 */
function gerarRelatorio(tipo) {
    const modal = document.getElementById('modalRelatorio');
    const titulo = document.getElementById('tituloRelatorio');
    document.getElementById('tipo_relatorio').value = tipo;

    // Resetar formulário
    document.getElementById('formRelatorio').reset();
    document.getElementById('rel_data_final').value = new Date().toISOString().split('T')[0];

    // Configurar título e campos específicos
    switch(tipo) {
        case 'modalidade':
            titulo.textContent = 'Relatório por Modalidade';
            document.getElementById('filtroModalidade').style.display = 'none';
            document.getElementById('filtroPregoeiro').style.display = 'none';
            break;

        case 'pregoeiro':
            titulo.textContent = 'Relatório por Pregoeiro';
            document.getElementById('filtroModalidade').style.display = 'block';
            document.getElementById('filtroPregoeiro').style.display = 'block';
            break;

        case 'prazos':
            titulo.textContent = 'Relatório de Prazos';
            document.getElementById('filtroModalidade').style.display = 'block';
            document.getElementById('filtroPregoeiro').style.display = 'none';
            break;

        case 'financeiro':
            titulo.textContent = 'Relatório Financeiro';
            document.getElementById('filtroModalidade').style.display = 'block';
            document.getElementById('filtroPregoeiro').style.display = 'none';
            break;
    }

    modal.style.display = 'block';
}

// ==================== MODAL DE DETALHES ====================

/**
 * Ver detalhes de uma licitação
 */
function verDetalhes(id) {
    const modal = document.getElementById('modalDetalhes');
    const content = document.getElementById('detalhesContent');

    if (!modal || !content) {
        return;
    }

    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Carregando...</div>';
    
    modal.classList.add('show');
    modal.style.display = 'block';

    fetch('api/get_licitacao.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const lic = data.data;
                content.innerHTML = `
                    <div style="display: grid; gap: 25px;">
                        <div>
                            <h4 style="margin: 0 0 20px 0; color: #2c3e50; padding-bottom: 10px; border-bottom: 2px solid #f8f9fa;">
                                <i data-lucide="info"></i> Informações Gerais
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">NUP</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.nup}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Modalidade</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">
                                        <span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 14px; font-weight: 600;">${lic.modalidade}</span>
                                    </div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Tipo</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.tipo}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Número da Contratação</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.numero_contratacao || '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Situação</label>
                                    <div style="font-size: 16px; margin-top: 5px;">
                                        <span class="status-badge status-${lic.situacao.toLowerCase().replace('_', '-')}">${lic.situacao.replace('_', ' ')}</span>
                                    </div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Valor Estimado</label>
                                    <div style="font-size: 16px; color: #27ae60; font-weight: 600; margin-top: 5px;">${formatarValorCorreto(lic.valor_estimado)}</div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 style="margin: 0 0 15px 0; color: #2c3e50;">
                                <i data-lucide="file-text"></i> Objeto
                            </h4>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; line-height: 1.6;">
                                ${lic.objeto}
                            </div>
                        </div>

                        ${lic.numero_contratacao ? `
                        <div>
                            <h4 style="margin: 0 0 15px 0; color: #2c3e50;">
                                <i data-lucide="database"></i> Dados do PCA
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; background: #e8f5e9; padding: 15px; border-radius: 8px;">
                                <div>
                                    <label style="font-weight: 600; color: #388e3c; font-size: 12px; text-transform: uppercase;">Nº Contratação PCA</label>
                                    <div style="font-size: 16px; color: #2e7d32; margin-top: 5px;">${lic.numero_contratacao || '-'}</div>
                                </div>
                                <div style="grid-column: 1 / -1;">
                                    <label style="font-weight: 600; color: #388e3c; font-size: 12px; text-transform: uppercase;">Título Contratação</label>
                                    <div style="font-size: 16px; color: #2e7d32; margin-top: 5px;">${lic.titulo_contratacao || '-'}</div>
                                </div>
                            </div>
                        </div>
                        ` : ''}

                        <div>
                            <h4 style="margin: 0 0 20px 0; color: #2c3e50; padding-bottom: 10px; border-bottom: 2px solid #f8f9fa;">
                                <i data-lucide="calendar"></i> Datas e Responsáveis
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Data Entrada DIPLI</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.data_entrada_dipli ? new Date(lic.data_entrada_dipli).toLocaleDateString('pt-BR') : '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Data Abertura</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.data_abertura ? new Date(lic.data_abertura).toLocaleDateString('pt-BR') : '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Pregoeiro</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.pregoeiro || '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Área Demandante</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.area_demandante || '-'}</div>
                                </div>
                            </div>
                        </div>

                        ${lic.situacao === 'HOMOLOGADO' ? `
                        <div>
                            <h4 style="margin: 0 0 20px 0; color: #27ae60; padding-bottom: 10px; border-bottom: 2px solid #d4edda;">
                                <i data-lucide="check-circle"></i> Dados da Homologação
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Data Homologação</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.data_homologacao ? new Date(lic.data_homologacao).toLocaleDateString('pt-BR') : '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Qtd Homologada</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.qtd_homol || '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Valor Homologado</label>
                                    <div style="font-size: 16px; color: #27ae60; font-weight: 600; margin-top: 5px;">${formatarValorCorreto(lic.valor_homologado)}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Economia</label>
                                    <div style="font-size: 16px; color: #3498db; font-weight: 600; margin-top: 5px;">${formatarValorCorreto(lic.economia)}</div>
                                </div>
                            </div>
                        </div>
                        ` : ''}

                        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; color: #6c757d; font-size: 14px;">
                            <p style="margin: 0;">Criado por: <strong>${lic.usuario_nome || 'N/A'}</strong> em ${new Date(lic.criado_em).toLocaleString('pt-BR')}</p>
                            ${lic.atualizado_em !== lic.criado_em ? `<p style="margin: 5px 0 0 0;">Última atualização: ${new Date(lic.atualizado_em).toLocaleString('pt-BR')}</p>` : ''}
                        </div>
                    </div>
                `;

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            } else {
                content.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;">Erro ao carregar detalhes da licitação</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;">Erro ao conectar com o servidor</div>';
        });
}

// ==================== EDIÇÃO DE LICITAÇÕES ====================

/**
 * Editar licitação
 */
function editarLicitacao(id) {
    const modal = document.getElementById('modalEdicao');

    // Buscar dados via AJAX
    fetch('api/get_licitacao.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const lic = data.data;

                // Preencher todos os campos do formulário
                document.getElementById('edit_id').value = lic.id;
                document.getElementById('edit_nup').value = lic.nup;
                document.getElementById('edit_data_entrada_dipli').value = lic.data_entrada_dipli || '';
                document.getElementById('edit_resp_instrucao').value = lic.resp_instrucao || '';
                document.getElementById('edit_area_demandante').value = lic.area_demandante || '';
                document.getElementById('edit_pregoeiro').value = lic.pregoeiro || '';
                document.getElementById('edit_modalidade').value = lic.modalidade;
                document.getElementById('edit_tipo').value = lic.tipo;

                // Preencher campo de contratação
                document.getElementById('edit_input_contratacao').value = lic.numero_contratacao || '';
                document.getElementById('edit_titulo_contratacao_selecionado').value = lic.titulo_contratacao || '';

                document.getElementById('edit_ano').value = lic.ano || new Date().getFullYear();

                // Formatar valores monetários
                if (lic.valor_estimado) {
                    document.getElementById('edit_valor_estimado').value = parseFloat(lic.valor_estimado).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                } else {
                    document.getElementById('edit_valor_estimado').value = '';
                }

                document.getElementById('edit_data_abertura').value = lic.data_abertura || '';
                document.getElementById('edit_data_homologacao').value = lic.data_homologacao || '';

                if (lic.valor_homologado) {
                    document.getElementById('edit_valor_homologado').value = parseFloat(lic.valor_homologado).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                } else {
                    document.getElementById('edit_valor_homologado').value = '';
                }

                if (lic.economia) {
                    document.getElementById('edit_economia').value = parseFloat(lic.economia).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                } else {
                    document.getElementById('edit_economia').value = '';
                }

                document.getElementById('edit_link').value = lic.link || '';
                document.getElementById('edit_situacao').value = lic.situacao;
                document.getElementById('edit_objeto').value = lic.objeto;

                // Mostrar modal
                modal.style.display = 'block';

                // Atualizar ícones Lucide após adicionar o conteúdo
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            } else {
                alert('Erro ao carregar dados da licitação');
            }
        })
        .catch(error => {
            alert('Erro ao conectar com o servidor');
        });
}

// ==================== FUNÇÕES DE FORMATAÇÃO ====================

/**
 * Formatar NUP
 */
function formatarNUP(input) {
    let value = input.value.replace(/\D/g, '');
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

        input.value = formatted;
    }
}

/**
 * Formatar valores monetários
 */
function formatarValorMonetario(input) {
    // Salvar posição do cursor
    let cursorPos = input.selectionStart;
    let originalLength = input.value.length;

    // Remover formatação anterior (manter apenas dígitos e vírgula)
    let value = input.value.replace(/[^\d,]/g, '');

    if (value.length === 0) {
        input.value = '';
        return;
    }

    // Permitir que o usuário digite sem forçar vírgula
    let parts = value.split(',');

    // Se há mais de uma vírgula, manter apenas a última
    if (parts.length > 2) {
        let decimais = parts.pop();
        value = parts.join('') + ',' + decimais;
        parts = value.split(',');
    }

    // Limitar decimais a 2 dígitos
    if (parts[1] && parts[1].length > 2) {
        parts[1] = parts[1].substring(0, 2);
    }

    // Formatar parte inteira e decimal
    let inteiros = parts[0];
    let decimais = parts[1];

    // Adicionar pontos nos milhares apenas na parte inteira
    if (inteiros.length > 3) {
        inteiros = inteiros.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Montar valor final
    if (decimais !== undefined) {
        input.value = inteiros + ',' + decimais;
    } else {
        input.value = inteiros;
    }

    // Restaurar posição do cursor aproximada
    let newLength = input.value.length;
    let newCursorPos = cursorPos + (newLength - originalLength);
    input.setSelectionRange(newCursorPos, newCursorPos);

    // Calcular economia
    // Verifica qual modal está ativo para chamar a função correta
    if (input.id.includes('_criar') && typeof calcularEconomiaModal === 'function') {
        calcularEconomiaModal();
    } else if (input.id.includes('_edit') && typeof calcularEconomiaEdit === 'function') {
        calcularEconomiaEdit();
    }
}

/**
 * Calcular economia
 */
function calcularEconomia() { // Esta função pode ser depreciada, usando calcularEconomiaModal/Edit
    const valorEstimadoField = document.getElementById('valor_estimado_criar');
    const valorHomologadoField = document.getElementById('valor_homologado_criar');
    const economiaField = document.getElementById('economia_criar');

    if (!valorEstimadoField || !valorHomologadoField || !economiaField) {
        return;
    }

    // Converter valores para números
    const valorEstimadoStr = valorEstimadoField.value.replace(/\./g, '').replace(',', '.');
    const valorHomologadoStr = valorHomologadoField.value.replace(/\./g, '').replace(',', '.');

    const valorEstimado = parseFloat(valorEstimadoStr) || 0;
    const valorHomologado = parseFloat(valorHomologadoStr) || 0;

    if (valorEstimado > 0 && valorHomologado > 0) {
        const economia = valorEstimado - valorHomologado;
        economiaField.value = economia.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        economiaField.value = '';
    }
}

// ==================== INTEGRAÇÃO COM PCA ====================

/**
 * Carregar dados do PCA
 */
function carregarDadosPCA(numeroContratacao) {
    // Esta função é chamada pelo selecionarContratacao
    // O preenchimento dos campos já é feito diretamente em selecionarContratacao.
    // Esta função pode ser removida se não houver outras dependências.
    console.log('Chamada para carregarDadosPCA com:', numeroContratacao);
}

/**
 * Preencher dados do PCA selecionado
 */
function preencherDadosPCA() {
    // Esta função é chamada pelo onchange de um select que não existe mais.
    // Pode ser removida.
    console.warn('Função preencherDadosPCA chamada, mas pode não ser mais relevante.');
    return;
}

// ==================== FUNÇÕES GENÉRICAS ====================

/**
 * Fechar modal genérico
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

    // Para o modal de criar licitação, limpar formulário
    if (modalId === 'modalCriarLicitacao') {
        const form = modal.querySelector('form');
        if (form) {
            form.reset();

            // Limpar campos específicos
            const economiaField = document.getElementById('economia_criar');
            if (economiaField) economiaField.value = '';

            const tituloField = document.getElementById('titulo_contratacao_selecionado');
            if (tituloField) tituloField.value = '';

            const inputContratacao = document.getElementById('input_contratacao');
            if (inputContratacao) inputContratacao.value = '';
        }
    }

    console.log('Modal fechado:', modalId);
}

// ==================== EVENT LISTENERS ====================

/**
 * Inicialização quando DOM estiver carregado
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Licitacao Dashboard');
    
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

    // Máscaras e formatação para formulário de criação
    const nupInput = document.getElementById('nup_criar');
    if (nupInput) {
        nupInput.addEventListener('input', function() {
            formatarNUP(this);
        });
    }

    const valorEstimadoInput = document.getElementById('valor_estimado_criar');
    if (valorEstimadoInput) {
        valorEstimadoInput.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        valorEstimadoInput.addEventListener('blur', function() {
            calcularEconomiaModal(); // Chamada para a função específica do modal de criação
        });
    }

    const valorHomologadoInput = document.getElementById('valor_homologado_criar');
    if (valorHomologadoInput) {
        valorHomologadoInput.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        valorHomologadoInput.addEventListener('blur', function() {
            calcularEconomiaModal(); // Chamada para a função específica do modal de criação
        });
    }

    // Event listener para formulário de relatório
    const formRelatorio = document.getElementById('formRelatorio');
    if (formRelatorio) {
        formRelatorio.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const params = new URLSearchParams();

            for (const [key, value] of formData) {
                if (value) params.append(key, value);
            }

            const formato = formData.get('formato');
            const url = 'relatorios/gerar_relatorio_licitacao.php?' + params.toString();

            if (formato === 'html') {
                // Abrir em nova aba
                window.open(url, '_blank');
            } else {
                // Download direto
                window.location.href = url;
            }

            fecharModal('modalRelatorio');
        });
    }

    // Event listener para formulário de edição (já existia e foi ajustado no final do arquivo)
    // const formEditarLicitacao = document.getElementById('formEditarLicitacao');
    // if (formEditarLicitacao) { ... }

    // Event listener para formulário de exportação
    const formExportar = document.getElementById('formExportar');
    if (formExportar) {
        formExportar.addEventListener('submit', function(e) {
            e.preventDefault();

            const formato = document.getElementById('formato_export').value;
            const campos = Array.from(document.querySelectorAll('input[name="campos[]"]:checked')).map(cb => cb.value);
            const aplicarFiltros = document.getElementById('export_filtros').checked;

            // Pegar situação atual do filtro se aplicável
            let situacao = '';
            if (aplicarFiltros) {
                const filtroAtual = document.querySelector('#lista-licitacoes select').value;
                if (filtroAtual) situacao = filtroAtual;
            }

            // Construir URL
            const params = new URLSearchParams({
                formato: formato,
                campos: campos.join(','),
                situacao: situacao
            });

            // Abrir download
            window.open('exportar_licitacoes.php?' + params.toString(), '_blank');
            fecharModal('modalExportar');
        });
    }
});

/**
 * Fechar modais ao clicar fora
 */
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        const modalId = event.target.id;
        if (modalId) {
            fecharModal(modalId);
        } else {
            // Fallback para modais sem ID
            event.target.style.setProperty('display', 'none', 'important');
            event.target.classList.remove('show');
        }
    }
}

/**
 * Abrir modal de criar licitação
 */
function abrirModalCriarLicitacao() {
    console.log('=== ABRINDO MODAL DE CRIAR LICITAÇÃO ===');
    const modal = document.getElementById('modalCriarLicitacao');

    // Limpar formulário
    const form = modal.querySelector('form');
    form.reset();

    // Definir ano atual
    const anoInput = modal.querySelector('input[name="ano"]');
    if (anoInput) {
        anoInput.value = new Date().getFullYear();
    }

    // Limpar campos calculados
    const economiaField = document.getElementById('economia_criar');
    if (economiaField) {
        economiaField.value = '';
    }

    // Limpar campos de PCA ocultos
    document.getElementById('titulo_contratacao_selecionado').value = '';
    document.getElementById('input_contratacao').value = ''; // Limpar o campo de input
    // Abrir modal
    modal.style.display = 'block';
    modal.classList.add('show');
    
    // Reinicializar ícones Lucide e sistema de abas
    setTimeout(() => {
        // Inicializar primeira aba
        mostrarAba('vinculacao-pca');
        
        // Focar no primeiro campo
        const nupField = modal.querySelector('#nup_criar');
        if (nupField) {
            nupField.focus();
        }
        
        // Garantir que ícones Lucide sejam inicializados
        inicializarLucideIcons();
    }, 100);
}

// Atualizar o event listener para o formulário de criação no modal
document.addEventListener('DOMContentLoaded', function() {
    // Event listener para o formulário de criação no modal
    const formCriarLicitacao = document.querySelector('#modalCriarLicitacao form');
    if (formCriarLicitacao) {
        formCriarLicitacao.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            // Converter valores monetários antes de enviar
            ['valor_estimado', 'valor_homologado', 'economia'].forEach(field => {
                const value = formData.get(field);
                if (value) {
                    // Remover separadores de milhares (pontos) e converter vírgula decimal para ponto
                    let cleanValue = value.toString().trim();
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
                    formData.set(field, cleanValue);
                }
            });

            // Mostrar loading
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
                        // Mostrar mensagem de sucesso
                        if (typeof showNotification === 'function') {
                            showNotification(data.message || 'Licitação criada com sucesso!', 'success');
                        } else {
                            alert(data.message || 'Licitação criada com sucesso!');
                        }
                        
                        // Fechar modal e recarregar página
                        fecharModal('modalCriarLicitacao');
                        
                        // Aguardar um pouco antes de recarregar para mostrar a notificação
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Mostrar erro
                        if (typeof showNotification === 'function') {
                            showNotification(data.message || 'Erro ao criar licitação', 'error');
                        } else {
                            alert(data.message || 'Erro ao criar licitação');
                        }
                        
                        // Restaurar botão
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }
                })
                .catch(error => {
                    alert('Erro ao processar requisição');
                    // Restaurar botão
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
        });
    }

    // Aplicar máscaras e eventos para os campos do modal de criação
    const nupInputModal = document.querySelector('#modalCriarLicitacao #nup_criar');
    if (nupInputModal) {
        nupInputModal.addEventListener('input', function() {
            formatarNUP(this);
        });
    }

    const valorEstimadoInputModal = document.querySelector('#modalCriarLicitacao #valor_estimado_criar');
    if (valorEstimadoInputModal) {
        valorEstimadoInputModal.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        valorEstimadoInputModal.addEventListener('blur', function() {
            calcularEconomiaModal();
        });
    }

    const valorHomologadoInputModal = document.querySelector('#modalCriarLicitacao #valor_homologado_criar');
    if (valorHomologadoInputModal) {
        valorHomologadoInputModal.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        valorHomologadoInputModal.addEventListener('blur', function() {
            calcularEconomiaModal();
        });
    }
});

/**
 * Calcular economia no modal de criação
 */
function calcularEconomiaModal() {
    const valorEstimadoField = document.querySelector('#modalCriarLicitacao #valor_estimado_criar');
    const valorHomologadoField = document.querySelector('#modalCriarLicitacao #valor_homologado_criar');
    const economiaField = document.querySelector('#modalCriarLicitacao #economia_criar');

    if (!valorEstimadoField || !valorHomologadoField || !economiaField) {
        return;
    }

    // Converter valores para números
    const valorEstimadoStr = valorEstimadoField.value.replace(/\./g, '').replace(',', '.');
    const valorHomologadoStr = valorHomologadoField.value.replace(/\./g, '').replace(',', '.');

    const valorEstimado = parseFloat(valorEstimadoStr) || 0;
    const valorHomologado = parseFloat(valorHomologadoStr) || 0;

    if (valorEstimado > 0 && valorHomologado > 0) {
        const economia = valorEstimado - valorHomologado;
        economiaField.value = economia.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        economiaField.value = '';
    }
}

/**
 * Atualizar a função preencherDadosPCA para funcionar no modal
 */
function preencherDadosPCA() {
    // Esta função é chamada pelo onchange de um select que não existe mais.
    // Pode ser removida.
    console.warn('Função preencherDadosPCA chamada, mas pode não ser mais relevante.');
    return;
}


// ==================== CAMPO DE PESQUISA PARA CONTRATAÇÕES ====================

// Variáveis globais para o autocomplete
let contratacoesPCA = [];
let sugestoesVisiveis = false;
let indiceSelecionado = -1;
let sugestoesFiltradas = [];
let timeoutPesquisa = null;

/**
 * Inicializar dados das contratações PCA
 */
function inicializarContratacoesPCA() {
    // Os dados são passados do PHP via window.contratacoesPCA
    if (typeof window.contratacoesPCA !== 'undefined') {
        contratacoesPCA = window.contratacoesPCA;
        console.log(`${contratacoesPCA.length} contratações PCA carregadas`);
    } else {
        console.warn('Dados das contratações PCA não foram carregados');
        contratacoesPCA = [];
    }
}

/**
 * Pesquisar contratações em tempo real
 */
function pesquisarContratacao(termo) {
    // Função desabilitada - usando sistema inline
    return;
}

/**
 * Realizar a pesquisa propriamente dita
 */
function realizarPesquisa(termo, sugestoesDiv, container, input) {
    try {
        // Verificar se os dados foram carregados
        if (!contratacoesPCA || contratacoesPCA.length === 0) {
            mostrarErro(sugestoesDiv, 'Dados das contratações não foram carregados');
            return;
        }

        // Filtrar contratações
        const termoLower = termo.toLowerCase().trim();
        sugestoesFiltradas = contratacoesPCA.filter(contratacao => {
            const numero = (contratacao.numero_contratacao || '').toLowerCase();
            const titulo = (contratacao.titulo_contratacao || '').toLowerCase();

            return numero.includes(termoLower) ||
                titulo.includes(termoLower);
        }).slice(0, 15); // Limitar a 15 resultados para performance

        // Mostrar sugestões
        mostrarSugestoesFiltradas(sugestoesDiv, container, termoLower);
        input.classList.remove('searching');

    } catch (error) {
        console.error('Erro na pesquisa:', error);
        mostrarErro(sugestoesDiv, 'Erro ao pesquisar contratações');
        input.classList.remove('searching');
    }
}

/**
 * Mostrar sugestões filtradas
 */
function mostrarSugestoesFiltradas(sugestoesDiv, container, termoLower) {
    if (sugestoesFiltradas.length === 0) {
        sugestoesDiv.innerHTML = `
            <div class="no-results">
                Nenhuma contratação encontrada para "${termoLower}"
            </div>
        `;
    } else {
        let html = '';

        // Adicionar contador de resultados se houver muitos
        if (contratacoesPCA.length > 15) {
            html += `<div class="suggestions-count">${sugestoesFiltradas.length} de ${contratacoesPCA.length}</div>`;
        }

        sugestoesFiltradas.forEach((contratacao, index) => {
            // Limitar o título para não ficar muito longo
            let tituloTruncado = contratacao.titulo_contratacao || 'Título não disponível';
            if (tituloTruncado.length > 100) {
                tituloTruncado = tituloTruncado.substring(0, 100) + '...';
            }

            // Destacar termo pesquisado
            const numeroDestacado = destacarTermo(contratacao.numero_contratacao || '', termoLower);
            const tituloDestacado = destacarTermo(tituloTruncado, termoLower);

            html += `
                <div class="suggestion-item"
                     data-index="${index}"
                     onclick="selecionarContratacao('${escapeHtml(contratacao.numero_contratacao)}', '${escapeHtml(contratacao.titulo_contratacao || '')}')">
                    <div class="suggestion-number">${numeroDestacado}</div>
                </div>
            `;
        });

        // Indicador se há mais resultados
        if (contratacoesPCA.length > sugestoesFiltradas.length) {
            const restantes = contratacoesPCA.length - sugestoesFiltradas.length;
            html += `<div class="more-results-indicator">Mais ${restantes} resultados disponíveis. Refine sua pesquisa.</div>`;
        }

        sugestoesDiv.innerHTML = html;
    }

    // Mostrar container de sugestões
    container.classList.add('has-suggestions');
    sugestoesDiv.style.display = 'block';
    sugestoesDiv.classList.add('show');
    sugestoesVisiveis = true;
    indiceSelecionado = -1;
}

/**
 * Destacar termo pesquisado no texto
 */
function destacarTermo(texto, termo) {
    if (!termo || !texto) return texto;

    const regex = new RegExp(`(${termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return texto.replace(regex, '<span class="highlight">$1</span>');
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Mostrar erro nas sugestões
 */
function mostrarErro(sugestoesDiv, mensagem) {
    sugestoesDiv.innerHTML = `
        <div class="no-data-loaded">
            ${mensagem}
        </div>
    `;
    sugestoesDiv.style.display = 'block';
    sugestoesVisiveis = true;
}

/**
 * Selecionar uma contratação (função original, agora atualizada)
 */
// window.selecionarContratacao = function(numero, dfd, titulo) { ... } // Comentado, pois a função foi movida para o topo de licitacao_dashboard.php

/**
 * Preencher campos do formulário com base na contratação selecionada
 */
function preencherCamposFormulario(numeroContratacao, titulo) {
    // Preencher objeto se estiver vazio
    const objetoField = document.getElementById('objeto_textarea');
    if (objetoField && !objetoField.value.trim() && titulo) {
        objetoField.value = titulo;
    }

    // Buscar dados completos da contratação
    const contratacao = contratacoesPCA.find(c => c.numero_contratacao === numeroContratacao);
    if (contratacao) {
        // Preencher área demandante se disponível
        const areaField = document.getElementById('area_demandante_criar');
        if (areaField && !areaField.value.trim() && contratacao.area_requisitante) {
            areaField.value = contratacao.area_requisitante;
        }

        // Preencher valor estimado se disponível
        const valorField = document.getElementById('valor_estimado_criar');
        if (valorField && !valorField.value.trim() && contratacao.valor_total_contratacao) {
            const valor = parseFloat(contratacao.valor_total_contratacao);
            if (!isNaN(valor)) {
                valorField.value = valor.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }
    }
}

/**
 * Mostrar sugestões quando input recebe foco
 */
function mostrarSugestoes() {
    // Função desabilitada - usando sistema inline
    return;
}

/**
 * Ocultar sugestões
 */
function ocultarSugestoes() {
    // Função desabilitada - usando sistema inline
    return;
}

/**
 * Limpar campos ocultos
 */
function limparCamposOcultos() {
    const tituloField = document.getElementById('titulo_contratacao_selecionado');

    if (tituloField) tituloField.value = '';
}

/**
 * Atualizar seleção visual (para navegação por teclado)
 */
function atualizarSelecaoVisual() {
    const sugestoes = document.querySelectorAll('.suggestion-item');

    sugestoes.forEach((item, index) => {
        if (index === indiceSelecionado) {
            item.classList.add('selected');
            // Scroll para manter item visível
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else {
            item.classList.remove('selected');
        }
    });
}

/**
 * Configurar navegação por teclado
 */
function configurarNavegacaoTeclado() {
    const input = document.getElementById('input_contratacao');

    if (!input) return;

    input.addEventListener('keydown', function(e) {
        if (!sugestoesVisiveis) return;

        const sugestoes = document.querySelectorAll('.suggestion-item');
        const totalSugestoes = sugestoes.length;

        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                indiceSelecionado = Math.min(indiceSelecionado + 1, totalSugestoes - 1);
                atualizarSelecaoVisual();
                break;

            case 'ArrowUp':
                e.preventDefault();
                indiceSelecionado = Math.max(indiceSelecionado - 1, -1);
                atualizarSelecaoVisual();
                break;

            case 'Enter':
                e.preventDefault();
                if (indiceSelecionado >= 0 && sugestoes[indiceSelecionado]) {
                    sugestoes[indiceSelecionado].click();
                } else if (totalSugestoes === 1) {
                    // Se há apenas uma sugestão, selecioná-la
                    sugestoes[0].click();
                }
                break;

            case 'Escape':
                e.preventDefault();
                ocultarSugestoes();
                input.blur();
                break;

            case 'Tab':
                // Permitir tab normal, mas ocultar sugestões
                ocultarSugestoes();
                break;
        }
    });
}

/**
 * Inicializar funcionalidades do campo de pesquisa
 */
function inicializarCampoPesquisa() {
    // Sistema desabilitado - usando sistema inline
    return;
}

// Sistema externo completamente desabilitado - usando apenas sistema inline
// document.addEventListener('DOMContentLoaded', function() {
//     inicializarCampoPesquisa();
// });

/**
 * Função compatível com código existente (manter se necessário)
 */
// Esta função é redundante se selecionarContratacao já preenche tudo
// function preencherDadosPCA() {
//     const numeroContratacao = document.getElementById('input_contratacao').value;

//     if (!numeroContratacao) return;

//     // Buscar dados da contratação selecionada
//     const contratacao = contratacoesPCA.find(c => c.numero_contratacao === numeroContratacao);

//     if (contratacao) {
//         preencherCamposFormulario(numeroContratacao, contratacao.titulo_contratacao);
//     }
// }

// Exportar funções para uso global se necessário
// window.pesquisarContratacao = pesquisarContratacao; // Já é definida no HTML
// window.selecionarContratacao = selecionarContratacao; // Já é definida no HTML
window.mostrarSugestoes = mostrarSugestoes;
window.ocultarSugestoes = ocultarSugestoes;
window.abrirModalCriarLicitacao = abrirModalCriarLicitacao;

// ==================== FUNÇÃO EXCLUIR LICITAÇÃO ====================

/**
 * Excluir licitação com confirmação
 */
function excluirLicitacao(id, nup) {
    // Confirmação dupla para segurança
    const confirmacao1 = confirm(`ATENÇÃO: Você tem certeza que deseja EXCLUIR a licitação?\n\nNUP: ${nup}\n\nEsta ação NÃO pode ser desfeita!`);
    
    if (!confirmacao1) {
        return;
    }
    
    const confirmacao2 = confirm(`CONFIRMAÇÃO FINAL: Excluir definitivamente a licitação ${nup}?\n\nDigite 'EXCLUIR' para confirmar:`);
    
    if (!confirmacao2) {
        return;
    }
    
    // Mostrar loading
    const loadingToast = document.createElement('div');
    loadingToast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #f39c12;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        font-weight: 600;
    `;
    loadingToast.innerHTML = `
        <i data-lucide="loader" style="width: 16px; height: 16px; animation: spin 1s linear infinite; margin-right: 8px;"></i>
        Excluindo licitação...
    `;
    document.body.appendChild(loadingToast);
    
    // Reinicializar ícones
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
    
    // Fazer requisição AJAX
    const formData = new FormData();
    formData.append('acao', 'excluir_licitacao');
    formData.append('id', id);
    
    fetch('process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Remover loading
        document.body.removeChild(loadingToast);
        
        if (data.success) {
            // Sucesso - mostrar feedback positivo
            showNotification(`Licitação ${data.nup || nup} excluída com sucesso!`, 'success');
            
            // Recarregar a lista de licitações sempre via refresh da página
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } else {
            // Erro - mostrar mensagem de erro
            showNotification(`Erro ao excluir licitação: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        // Remover loading
        if (document.body.contains(loadingToast)) {
            document.body.removeChild(loadingToast);
        }
        
        console.error('Erro na requisição:', error);
        showNotification('Erro de conexão. Tente novamente.', 'error');
    });
}

// Função auxiliar para mostrar notificações
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const bgColor = type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#3498db';
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${bgColor};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        font-weight: 600;
        max-width: 400px;
        animation: slideInRight 0.3s ease;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (document.body.contains(notification)) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }
    }, 5000);
}

window.excluirLicitacao = excluirLicitacao;
window.editarLicitacao = editarLicitacao;
window.verDetalhes = verDetalhes;
window.gerarRelatorio = gerarRelatorio;
window.filtrarLicitacoes = filtrarLicitacoes;
window.adicionarEventListenersPaginacao = adicionarEventListenersPaginacao;
window.exportarLicitacoes = exportarLicitacoes;
window.fecharModal = fecharModal;

// formatarValorCorreto é usada no verDetalhes
// window.formatarValorCorreto = formatarValorCorreto; // Já é definida no topo

// ==================== FUNÇÕES PARA O MODAL DE EDIÇÃO ====================

/**
 * Pesquisar contratação no modal de edição
 */
// window.pesquisarContratacaoInlineEdit = function(termo) { ... } // Já é definida no topo de licitacao_dashboard.php

/**
 * Selecionar contratação no modal de edição
 */
// window.selecionarContratacaoEdit = function(numero) { ... } // Já é definida no topo de licitacao_dashboard.php

// window.mostrarSugestoesInlineEdit = function() { ... } // Já é definida no topo de licitacao_dashboard.php
// window.ocultarSugestoesInlineEdit = function() { ... } // Já é definida no topo de licitacao_dashboard.php

/**
 * Calcular economia no modal de edição
 */
function calcularEconomiaEdit() {
    const valorEstimadoField = document.getElementById('edit_valor_estimado');
    const valorHomologadoField = document.getElementById('edit_valor_homologado');
    const economiaField = document.getElementById('edit_economia');

    if (!valorEstimadoField || !valorHomologadoField || !economiaField) {
        return;
    }

    // Converter valores para números
    const valorEstimadoStr = valorEstimadoField.value.replace(/\./g, '').replace(',', '.');
    const valorHomologadoStr = valorHomologadoField.value.replace(/\./g, '').replace(',', '.');

    const valorEstimado = parseFloat(valorEstimadoStr) || 0;
    const valorHomologado = parseFloat(valorHomologadoStr) || 0;

    if (valorEstimado > 0 && valorHomologado > 0) {
        const economia = valorEstimado - valorHomologado;
        economiaField.value = economia.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        economiaField.value = '';
    }
}

// Adicionar event listeners para o modal de edição no DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // Máscaras e formatação para modal de edição
    const editNupInput = document.getElementById('edit_nup');
    if (editNupInput) {
        editNupInput.addEventListener('input', function() {
            formatarNUP(this);
        });
    }

    const editValorEstimadoInput = document.getElementById('edit_valor_estimado');
    if (editValorEstimadoInput) {
        editValorEstimadoInput.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        editValorEstimadoInput.addEventListener('blur', function() {
            calcularEconomiaEdit();
        });
    }

    const editValorHomologadoInput = document.getElementById('edit_valor_homologado');
    if (editValorHomologadoInput) {
        editValorHomologadoInput.addEventListener('input', function() {
            formatarValorMonetario(this);
        });

        editValorHomologadoInput.addEventListener('blur', function() {
            calcularEconomiaEdit();
        });
    }

    // Event listener para formulário de edição atualizado
    const formEditarLicitacao = document.getElementById('formEditarLicitacao');
    if (formEditarLicitacao) {
        formEditarLicitacao.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            // Converter valores monetários
            ['valor_estimado', 'valor_homologado', 'economia'].forEach(field => {
                const value = formData.get(field);
                if (value) {
                    // Remover separadores de milhares (pontos) e converter vírgula decimal para ponto
                    let cleanValue = value.toString().trim();
                    if (cleanValue.includes(',')) {
                        cleanValue = cleanValue.replace(/\./g, '').replace(',', '.');
                    } else if (cleanValue.includes('.')) {
                        const parts = cleanValue.split('.');
                        if (parts.length === 2 && parts[1].length <= 2) {
                            cleanValue = cleanValue;
                        } else {
                            cleanValue = cleanValue.replace(/\./g, '');
                        }
                    }
                    formData.set(field, cleanValue);
                }
            });

            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Salvando...';
            submitBtn.disabled = true;

            fetch('process.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fecharModal('modalEdicao');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Erro ao salvar alterações');
                        // Restaurar botão
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }
                })
                .catch(error => {
                    alert('Erro ao processar requisição');
                    // Restaurar botão
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
        });
    }
});

/**
 * Fechar um modal genérico
 */
function fecharModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
    }
}

window.fecharModal = fecharModal;




// ==================== FUNÇÕES INLINE PARA AUTOCOMPLETE ====================

/**
 * Pesquisar contratação inline (modal de criação)
 */
function pesquisarContratacaoInline(termo) {
    const sugestoesDiv = document.getElementById('sugestoes_contratacao');
    
    if (!sugestoesDiv) {
        console.error('Elemento sugestoes_contratacao não encontrado');
        return;
    }

    if (!termo || termo.length < 2) {
        sugestoesDiv.style.display = 'none';
        return;
    }

    // Inicializar dados se necessário
    if (!contratacoesPCA || contratacoesPCA.length === 0) {
        inicializarContratacoesPCA();
    }

    // Filtrar contratações
    const termoLower = termo.toLowerCase().trim();
    const filtradas = contratacoesPCA.filter(contratacao => {
        const numero = (contratacao.numero_contratacao || '').toLowerCase();
        const titulo = (contratacao.titulo_contratacao || '').toLowerCase();
        return numero.includes(termoLower) || titulo.includes(termoLower);
    }).slice(0, 10);

    // Gerar HTML das sugestões
    if (filtradas.length === 0) {
        sugestoesDiv.innerHTML = '<div class="no-results">Nenhuma contratação encontrada</div>';
    } else {
        let html = '';
        filtradas.forEach(contratacao => {
            const titulo = contratacao.titulo_contratacao || 'Título não disponível';
            const tituloTruncado = titulo.length > 80 ? titulo.substring(0, 80) + '...' : titulo;
            const tituloEscapado = titulo.replace(/'/g, "&#39;").replace(/"/g, "&quot;");
            
            html += `
                <div class="suggestion-item" onclick="selecionarContratacao('${contratacao.numero_contratacao}', '${tituloEscapado}')">
                    <div class="suggestion-numero">${contratacao.numero_contratacao}</div>
                    <div class="suggestion-titulo">${tituloTruncado}</div>
                </div>
            `;
        });
        sugestoesDiv.innerHTML = html;
    }

    sugestoesDiv.style.display = 'block';
}

/**
 * Mostrar sugestões inline (modal de criação)
 */
function mostrarSugestoesInline() {
    const input = document.getElementById('input_contratacao');
    const sugestoesDiv = document.getElementById('sugestoes_contratacao');
    
    if (input && input.value && input.value.length >= 2) {
        pesquisarContratacaoInline(input.value);
    }
}

/**
 * Ocultar sugestões inline (modal de criação)
 */
function ocultarSugestoesInline() {
    setTimeout(() => {
        const sugestoesDiv = document.getElementById('sugestoes_contratacao');
        if (sugestoesDiv) {
            sugestoesDiv.style.display = 'none';
        }
    }, 200);
}

/**
 * Selecionar contratação
 */
function selecionarContratacao(numero, titulo) {
    const inputContratacao = document.getElementById('input_contratacao') || document.getElementById('edit_input_contratacao');
    const tituloHidden = document.getElementById('titulo_contratacao_selecionado') || document.getElementById('edit_titulo_contratacao_selecionado');
    const sugestoesDiv = document.getElementById('sugestoes_contratacao') || document.getElementById('edit_sugestoes_contratacao');
    
    if (inputContratacao) {
        inputContratacao.value = numero;
    }
    
    if (tituloHidden) {
        tituloHidden.value = titulo;
    }
    
    if (sugestoesDiv) {
        sugestoesDiv.style.display = 'none';
    }

    // Preencher outros campos se estiver criando
    if (document.getElementById('input_contratacao')) {
        preencherCamposFormulario(numero, titulo);
    }

    console.log('Contratação selecionada:', numero, titulo);
}

/**
 * Pesquisar contratação inline (modal de edição)
 */
function pesquisarContratacaoInlineEdit(termo) {
    const sugestoesDiv = document.getElementById('edit_sugestoes_contratacao');
    
    if (!sugestoesDiv) {
        console.error('Elemento edit_sugestoes_contratacao não encontrado');
        return;
    }

    if (!termo || termo.length < 2) {
        sugestoesDiv.style.display = 'none';
        return;
    }

    // Inicializar dados se necessário
    if (!contratacoesPCA || contratacoesPCA.length === 0) {
        inicializarContratacoesPCA();
    }

    // Filtrar contratações
    const termoLower = termo.toLowerCase().trim();
    const filtradas = contratacoesPCA.filter(contratacao => {
        const numero = (contratacao.numero_contratacao || '').toLowerCase();
        const titulo = (contratacao.titulo_contratacao || '').toLowerCase();
        return numero.includes(termoLower) || titulo.includes(termoLower);
    }).slice(0, 10);

    // Gerar HTML das sugestões
    if (filtradas.length === 0) {
        sugestoesDiv.innerHTML = '<div class="no-results">Nenhuma contratação encontrada</div>';
    } else {
        let html = '';
        filtradas.forEach(contratacao => {
            const titulo = contratacao.titulo_contratacao || 'Título não disponível';
            const tituloTruncado = titulo.length > 80 ? titulo.substring(0, 80) + '...' : titulo;
            const tituloEscapado = titulo.replace(/'/g, "&#39;").replace(/"/g, "&quot;");
            
            html += `
                <div class="suggestion-item" onclick="selecionarContratacao('${contratacao.numero_contratacao}', '${tituloEscapado}')">
                    <div class="suggestion-numero">${contratacao.numero_contratacao}</div>
                    <div class="suggestion-titulo">${tituloTruncado}</div>
                </div>
            `;
        });
        sugestoesDiv.innerHTML = html;
    }

    sugestoesDiv.style.display = 'block';
}

/**
 * Mostrar sugestões inline (modal de edição)
 */
function mostrarSugestoesInlineEdit() {
    const input = document.getElementById('edit_input_contratacao');
    const sugestoesDiv = document.getElementById('edit_sugestoes_contratacao');
    
    if (input && input.value && input.value.length >= 2) {
        pesquisarContratacaoInlineEdit(input.value);
    }
}

/**
 * Ocultar sugestões inline (modal de edição)
 */
function ocultarSugestoesInlineEdit() {
    setTimeout(() => {
        const sugestoesDiv = document.getElementById('edit_sugestoes_contratacao');
        if (sugestoesDiv) {
            sugestoesDiv.style.display = 'none';
        }
    }, 200);
}

// Exportar funções para escopo global
window.pesquisarContratacaoInline = pesquisarContratacaoInline;
window.mostrarSugestoesInline = mostrarSugestoesInline;
window.ocultarSugestoesInline = ocultarSugestoesInline;
window.selecionarContratacao = selecionarContratacao;
window.pesquisarContratacaoInlineEdit = pesquisarContratacaoInlineEdit;
window.mostrarSugestoesInlineEdit = mostrarSugestoesInlineEdit;
window.ocultarSugestoesInlineEdit = ocultarSugestoesInlineEdit;

/**
 * Selecionar todos os campos de exportação
 */
function selecionarTodosCampos(selecionar) {
    const checkboxes = document.querySelectorAll('input[name="campos[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selecionar;
    });
}

// Exportar função para escopo global
window.selecionarTodosCampos = selecionarTodosCampos;

// Inicializar dados das contratações quando DOM carregar
document.addEventListener('DOMContentLoaded', function() {
    inicializarContratacoesPCA();
});

// ==================== SISTEMA DE ABAS ==================== 

let abaAtual = 0;
const abas = ['vinculacao-pca', 'informacoes-gerais', 'prazos-datas', 'valores-financeiro', 'responsaveis'];

/**
 * Mostrar aba específica
 */
function mostrarAba(nomeAba) {
    // Remover active de todas as abas
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Ativar aba selecionada
    document.querySelector(`[onclick="mostrarAba('${nomeAba}')"]`).classList.add('active');
    document.getElementById(`aba-${nomeAba}`).classList.add('active');
    
    // Atualizar índice atual
    abaAtual = abas.indexOf(nomeAba);
    
    // Atualizar botões de navegação
    atualizarBotoesNavegacao();
    
    // Reinicializar ícones Lucide após um pequeno delay para garantir renderização
    setTimeout(() => {
        inicializarLucideIcons();
    }, 50);
}

/**
 * Ir para próxima aba
 */
function proximaAba() {
    if (abaAtual < abas.length - 1) {
        abaAtual++;
        mostrarAba(abas[abaAtual]);
    }
}

/**
 * Ir para aba anterior
 */
function abaAnterior() {
    if (abaAtual > 0) {
        abaAtual--;
        mostrarAba(abas[abaAtual]);
    }
}

/**
 * Atualizar estado dos botões de navegação
 */
function atualizarBotoesNavegacao() {
    const btnAnterior = document.getElementById('btn-anterior');
    const btnProximo = document.getElementById('btn-proximo');
    const btnCriar = document.getElementById('btn-criar');
    
    // Botão anterior
    if (abaAtual === 0) {
        btnAnterior.style.display = 'none';
    } else {
        btnAnterior.style.display = 'inline-flex';
    }
    
    // Botão próximo/criar
    if (abaAtual === abas.length - 1) {
        btnProximo.style.display = 'none';
        btnCriar.style.display = 'inline-flex';
    } else {
        btnProximo.style.display = 'inline-flex';
        btnCriar.style.display = 'none';
    }
}

/**
 * Resetar formulário e voltar para primeira aba
 */
function resetarFormulario() {
    // Resetar formulário
    document.getElementById('formCriarLicitacao').reset();
    
    // Voltar para primeira aba
    abaAtual = 0;
    mostrarAba(abas[0]);
    
    // Limpar informações da contratação selecionada
    const infoContratacao = document.getElementById('info_contratacao_selecionada');
    if (infoContratacao) {
        infoContratacao.style.display = 'none';
    }
    
    // Limpar campos ocultos
    const numeroDfdField = document.getElementById('numero_dfd_selecionado');
    const tituloField = document.getElementById('titulo_contratacao_selecionado');
    if (numeroDfdField) numeroDfdField.value = '';
    if (tituloField) tituloField.value = '';
}

/**
 * Validação por aba
 */
function validarAbaAtual() {
    const abaAtiva = document.getElementById(`aba-${abas[abaAtual]}`);
    const camposObrigatorios = abaAtiva.querySelectorAll('input[required], select[required], textarea[required]');
    
    let valido = true;
    camposObrigatorios.forEach(campo => {
        if (!campo.value.trim()) {
            campo.style.borderColor = '#e74c3c';
            valido = false;
        } else {
            campo.style.borderColor = '#ddd';
        }
    });
    
    return valido;
}

/**
 * Avançar com validação
 */
function proximaAbaComValidacao() {
    if (validarAbaAtual()) {
        proximaAba();
    } else {
        alert('Por favor, preencha todos os campos obrigatórios da aba atual.');
    }
}

/**
 * Calcular economia automaticamente
 */
function calcularEconomia() {
    const valorEstimado = document.getElementById('valor_estimado_criar');
    const valorHomologado = document.getElementById('valor_homologado_criar');
    const economia = document.getElementById('economia_criar');
    
    if (valorEstimado && valorHomologado && economia) {
        valorEstimado.addEventListener('input', calcular);
        valorHomologado.addEventListener('input', calcular);
        
        function calcular() {
            const estimado = parseFloat(valorEstimado.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            const homologado = parseFloat(valorHomologado.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            
            if (estimado > 0 && homologado > 0) {
                const economiaValor = estimado - homologado;
                economia.value = economiaValor.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            } else {
                economia.value = '';
            }
        }
    }
}

// Função auxiliar para inicializar ícones Lucide de forma segura
function inicializarLucideIcons() {
    try {
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    } catch (error) {
        console.warn('Erro ao inicializar ícones Lucide:', error);
    }
}

// Exportar funções para escopo global
window.mostrarAba = mostrarAba;
window.proximaAba = proximaAba;
window.abaAnterior = abaAnterior;
window.resetarFormulario = resetarFormulario;
window.proximaAbaComValidacao = proximaAbaComValidacao;
window.inicializarLucideIcons = inicializarLucideIcons;

// ==================== SISTEMA DE FILTROS AJAX ====================

/**
 * Adicionar event listeners para paginação AJAX - função auxiliar
 */
function adicionarEventListenersPaginacao() {
    const resultadosDiv = document.getElementById('resultadosLicitacoes');
    if (!resultadosDiv) return;
    
    document.querySelectorAll('.ajax-link').forEach(link => {
        // Clonar o elemento para remover todos os event listeners antigos
        const newLink = link.cloneNode(true);
        link.parentNode.replaceChild(newLink, link);
        
        // Adicionar novo event listener
        newLink.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            if (url) {
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            resultadosDiv.innerHTML = data.html;
                            setTimeout(() => {
                                inicializarLucideIcons();
                                // Reinicializar recursivamente os event listeners para nova paginação
                                adicionarEventListenersPaginacao();
                            }, 100);
                        }
                    })
                    .catch(error => {
                        console.error('Erro na paginação:', error);
                    });
            }
        });
    });
}

/**
 * Filtrar licitações via AJAX
 */
function filtrarLicitacoes(formData) {
    const resultadosDiv = document.getElementById('resultadosLicitacoes');
    if (!resultadosDiv) return;

    // Mostrar loading
    resultadosDiv.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i data-lucide="loader" style="width: 32px; height: 32px; animation: spin 1s linear infinite; color: #3498db;"></i>
            <p style="margin-top: 15px; color: #666;">Carregando...</p>
        </div>
    `;

    // Fazer requisição AJAX
    const url = new URL(window.location.href);
    url.searchParams.set('ajax', 'filtrar_licitacoes');
    
    // Adicionar parâmetros do formulário
    for (const [key, value] of formData.entries()) {
        if (value) {
            url.searchParams.set(key, value);
        }
    }

    fetch(url.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultadosDiv.innerHTML = data.html;
                
                // Reinicializar ícones Lucide e event listeners para paginação
                setTimeout(() => {
                    inicializarLucideIcons();
                    adicionarEventListenersPaginacao();
                }, 100);
            }
        })
        .catch(error => {
            console.error('Erro na filtragem:', error);
            resultadosDiv.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #dc3545;">
                    <i data-lucide="alert-circle" style="width: 32px; height: 32px; margin-bottom: 15px;"></i>
                    <p>Erro ao carregar os dados. Tente novamente.</p>
                </div>
            `;
        });
}

// Inicializar sistema de abas quando DOM carregar
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar estado das abas
    abaAtual = 0;
    atualizarBotoesNavegacao();
    
    // Inicializar cálculo de economia
    calcularEconomia();
    
    // Modificar botão próximo para usar validação
    const btnProximo = document.getElementById('btn-proximo');
    if (btnProximo) {
        btnProximo.onclick = proximaAbaComValidacao;
    }
    
    // Interceptar formulário de filtros
    const formFiltros = document.getElementById('formFiltrosLicitacao');
    if (formFiltros) {
        formFiltros.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Garantir que estamos na aba de lista de licitações
            showSection('lista-licitacoes');
            
            // Preparar dados do formulário
            const formData = new FormData(this);
            filtrarLicitacoes(formData);
        });
        
        // Filtrar automaticamente quando campo "por página" mudar
        const selectPorPagina = formFiltros.querySelector('select[name="por_pagina"]');
        if (selectPorPagina) {
            selectPorPagina.addEventListener('change', function() {
                const formData = new FormData(formFiltros);
                filtrarLicitacoes(formData);
            });
        }
    }
    
    // Inicializar ícones Lucide após carregamento completo
    setTimeout(() => {
        inicializarLucideIcons();
    }, 200);
});

/**
 * Alterar quantidade de itens por página no contexto AJAX
 */
function alterarItensPorPaginaAjax(novoValor) {
    const formFiltros = document.getElementById('formFiltrosLicitacao');
    if (formFiltros) {
        // Criar um FormData baseado no formulário atual
        const formData = new FormData(formFiltros);
        formData.set('por_pagina', novoValor);
        formData.set('pagina', '1'); // Voltar para primeira página
        
        // Fazer filtro AJAX
        filtrarLicitacoes(formData);
    } else {
        // Fallback para recarga da página se não estiver em contexto AJAX
        const url = new URL(window.location);
        url.searchParams.set('por_pagina', novoValor);
        url.searchParams.set('pagina', '1');
        window.location.href = url.toString();
    }
}

// ==================== SISTEMA MODERNO DE ANDAMENTOS COM PAGINAÇÃO ====================

/**
 * Classe para gerenciar a timeline de andamentos com paginação
 */
class AndamentosManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 50;
        this.allAndamentos = [];
        this.filteredAndamentos = [];
        this.totalItems = 0;
        this.totalPages = 0;
        this.isLoading = false;
        this.currentNup = '';
        this.currentFilters = {
            dataInicio: '',
            dataFim: '',
            unidade: '',
            busca: ''
        };
        this.tempoUnidadeChart = null;
    }

    /**
     * Carregar andamentos via API
     */
    async loadAndamentos(nup) {
        try {
            this.showLoading();
            this.currentNup = nup;
            
            const response = await fetch(`api/consultar_andamentos.php?nup=${encodeURIComponent(nup)}&calcular_tempo=true`);
            const data = await response.json();
            
            if (data.success && data.data && data.data.length > 0) {
                const processoData = data.data[0];
                this.allAndamentos = processoData.andamentos || [];
                this.filteredAndamentos = [...this.allAndamentos];
                this.totalItems = this.allAndamentos.length;
                this.totalPages = Math.ceil(this.totalItems / this.itemsPerPage);
                
                // Renderizar primeira página
                this.renderPage(1);
                
                // Criar gráfico de tempo por unidade após renderizar
                setTimeout(() => {
                    if (processoData.tempo_por_unidade) {
                        this.createTempoUnidadeChart(processoData.tempo_por_unidade);
                    }
                }, 100);
                
            } else {
                this.showEmptyState();
            }
        } catch (error) {
            console.error('Erro ao carregar andamentos:', error);
            this.showError('Erro ao carregar andamentos. Tente novamente.');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Mostrar estado de carregamento
     */
    showLoading() {
        this.isLoading = true;
        const container = document.getElementById('conteudoAndamentos');
        if (container) {
            container.innerHTML = `
                <div class="loading-timeline">
                    <i data-lucide="loader"></i>
                    <p>Carregando timeline do processo...</p>
                </div>
            `;
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }

    /**
     * Esconder estado de carregamento
     */
    hideLoading() {
        this.isLoading = false;
    }

    /**
     * Renderizar página específica
     */
    renderPage(page) {
        if (page < 1 || page > this.totalPages) return;
        
        this.currentPage = page;
        const startIndex = (page - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const pageItems = this.filteredAndamentos.slice(startIndex, endIndex);
        
        this.renderTimeline(pageItems);
    }

    /**
     * Renderizar timeline
     */
    renderTimeline(andamentos) {
        const container = document.getElementById('conteudoAndamentos');
        if (!container) return;

        // Usar todos os andamentos filtrados
        const allAndamentos = this.filteredAndamentos || andamentos;
        
        // Gerar HTML das abas
        const tabsHtml = this.generateTabsInterface(allAndamentos);
        
        container.innerHTML = tabsHtml;
        
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        
        // Inicializar sistema de abas
        this.initTabSystem();
        
        // Ativar primeira aba por padrão
        this.switchTab('resumo');
    }

    /**
     * Gerar interface com abas
     */
    generateTabsInterface(andamentos) {
        return `
            <div class="tabs-navigation">
                <button class="tab-button active" onclick="andamentosManager.switchTab('resumo')" data-tab="resumo">
                    <i data-lucide="bar-chart-3"></i>
                    Resumo
                </button>
                <button class="tab-button" onclick="andamentosManager.switchTab('cronologia')" data-tab="cronologia">
                    <i data-lucide="clock"></i>
                    Cronologia
                </button>
                <button class="tab-button" onclick="andamentosManager.switchTab('detalhes')" data-tab="detalhes">
                    <i data-lucide="file-text"></i>
                    Detalhes
                </button>
            </div>
            
            <div class="tab-content active" id="tab-resumo">
                ${this.generateResumoContent(andamentos)}
            </div>
            
            <div class="tab-content" id="tab-cronologia">
                ${this.generateCronologiaContent(andamentos)}
            </div>
            
            <div class="tab-content" id="tab-detalhes">
                ${this.generateDetalhesContent(andamentos)}
            </div>
        `;
    }

    /**
     * Gerar conteúdo da aba Resumo
     */
    generateResumoContent(andamentos) {
        if (!andamentos || andamentos.length === 0) {
            return `
                <div class="tab-empty">
                    <i data-lucide="inbox"></i>
                    <h3>Nenhum andamento disponível</h3>
                    <p>Não há dados para exibir estatísticas.</p>
                </div>
            `;
        }

        const stats = this.calculateStats(andamentos);
        
        return `
            <div class="resumo-stats">
                <div class="stat-card-resumo">
                    <div class="stat-number">${andamentos.length}</div>
                    <div class="stat-label">Total de Andamentos</div>
                </div>
                <div class="stat-card-resumo success">
                    <div class="stat-number">${stats.diasTotal}</div>
                    <div class="stat-label">Dias Estimados</div>
                </div>
                <div class="stat-card-resumo info">
                    <div class="stat-number">${stats.unidadesUnicas}</div>
                    <div class="stat-label">Unidades Envolvidas</div>
                </div>
                <div class="stat-card-resumo warning">
                    <div class="stat-number">${stats.diasMedio}</div>
                    <div class="stat-label">Dias Médios por Andamento</div>
                </div>
            </div>
            
            <div class="resumo-chart">
                <h4 style="margin: 0 0 16px 0; color: #374151;">
                    <i data-lucide="pie-chart"></i> Distribuição por Unidade
                </h4>
                <div id="resumoChart" style="height: 200px;"></div>
            </div>
        `;
    }

    /**
     * Gerar conteúdo da aba Cronologia
     */
    generateCronologiaContent(andamentos) {
        if (!andamentos || andamentos.length === 0) {
            return `
                <div class="tab-empty">
                    <i data-lucide="clock"></i>
                    <h3>Nenhum andamento disponível</h3>
                    <p>Não há cronologia para exibir.</p>
                </div>
            `;
        }

        let html = '<div class="cronologia-simple">';
        
        andamentos.forEach(andamento => {
            const type = this.getAndamentoType(andamento);
            const icon = this.getIconForAndamento(andamento);
            
            html += `
                <div class="cronologia-item">
                    <div class="cronologia-icon ${type}">
                        <i data-lucide="${icon}"></i>
                    </div>
                    <div class="cronologia-content">
                        <div class="cronologia-header">
                            <h5 class="cronologia-title">${andamento.unidade || 'Unidade não informada'}</h5>
                            <span class="cronologia-date">${this.formatDate(andamento.data_hora)}</span>
                        </div>
                        <div class="cronologia-description">
                            ${andamento.descricao || 'Descrição não disponível'}
                            ${andamento.dias ? `<br><small style="color: #9ca3af;"><i data-lucide="clock"></i> ${andamento.dias} dias estimados</small>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }

    /**
     * Gerar conteúdo da aba Detalhes
     */
    generateDetalhesContent(andamentos) {
        if (!andamentos || andamentos.length === 0) {
            return `
                <div class="tab-empty">
                    <i data-lucide="file-text"></i>
                    <h3>Nenhum andamento disponível</h3>
                    <p>Não há detalhes para exibir.</p>
                </div>
            `;
        }

        let html = '<div class="detalhes-grid">';
        
        andamentos.forEach((andamento, index) => {
            html += `
                <div class="detalhes-card">
                    <h4 class="detalhes-title">
                        <i data-lucide="${this.getIconForAndamento(andamento)}"></i>
                        Andamento ${index + 1}
                    </h4>
                    
                    <div class="detalhes-item">
                        <div class="detalhes-label">Unidade Responsável:</div>
                        <div class="detalhes-value">${andamento.unidade || 'Não informada'}</div>
                    </div>
                    
                    <div class="detalhes-item">
                        <div class="detalhes-label">Data/Hora:</div>
                        <div class="detalhes-value">${this.formatDate(andamento.data_hora)}</div>
                    </div>
                    
                    <div class="detalhes-item">
                        <div class="detalhes-label">Descrição:</div>
                        <div class="detalhes-value">${andamento.descricao || 'Não disponível'}</div>
                    </div>
                    
                    ${andamento.dias ? `
                        <div class="detalhes-item">
                            <div class="detalhes-label">Dias Estimados:</div>
                            <div class="detalhes-value">${andamento.dias} dias</div>
                        </div>
                    ` : ''}
                    
                    ${andamento.tipo_operacao ? `
                        <div class="detalhes-item">
                            <div class="detalhes-label">Tipo de Operação:</div>
                            <div class="detalhes-value">${andamento.tipo_operacao}</div>
                        </div>
                    ` : ''}
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }

    /**
     * Calcular estatísticas dos andamentos
     */
    calculateStats(andamentos) {
        if (!andamentos || andamentos.length === 0) {
            return { diasTotal: 0, unidadesUnicas: 0, diasMedio: 0 };
        }

        const diasTotal = andamentos.reduce((sum, a) => sum + (parseInt(a.dias) || 0), 0);
        const unidades = [...new Set(andamentos.map(a => a.unidade).filter(Boolean))];
        const diasMedio = Math.round(diasTotal / andamentos.length) || 0;

        return {
            diasTotal,
            unidadesUnicas: unidades.length,
            diasMedio
        };
    }

    /**
     * Inicializar sistema de abas
     */
    initTabSystem() {
        // Sistema já inicializado via onclick nos botões
        console.log('Sistema de abas inicializado');
    }

    /**
     * Trocar aba ativa
     */
    switchTab(tabName) {
        // Remover classe active de todos os botões e conteúdos
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Adicionar classe active ao botão e conteúdo selecionados
        const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
        const activeContent = document.getElementById(`tab-${tabName}`);
        
        if (activeButton) activeButton.classList.add('active');
        if (activeContent) activeContent.classList.add('active');
        
        // Recrear ícones para o conteúdo ativo
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    /**
     * Determinar tipo do andamento para classes CSS
     */
    getAndamentoType(andamento) {
        if (!andamento.descricao) return '';
        
        const desc = andamento.descricao.toLowerCase();
        if (desc.includes('homolog') || desc.includes('aprovad')) return 'success';
        if (desc.includes('indeferid') || desc.includes('rejeitad') || desc.includes('erro')) return 'error';
        if (desc.includes('urgente') || desc.includes('prazo') || desc.includes('vencimento')) return 'warning';
        
        return '';
    }

    /**
     * Formatação de data simplificada
     */
    formatDate(dateStr) {
        if (!dateStr) return 'Data não informada';
        
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateStr;
        }
    }

    /**
     * Gerar HTML da timeline
     */
    generateTimelineHtml(andamentos) {
        if (!andamentos || andamentos.length === 0) {
            return `
                <div class="empty-timeline">
                    <i data-lucide="inbox"></i>
                    <h3>Nenhum andamento encontrado</h3>
                    <p>Não há andamentos para esta página ou filtro aplicado.</p>
                </div>
            `;
        }

        let html = '';
        andamentos.forEach((andamento, index) => {
            const isImportant = andamento.descricao && andamento.descricao.toLowerCase().includes('homolog');
            const isSuccess = andamento.descricao && andamento.descricao.toLowerCase().includes('finaliz');
            
            let iconClass = '';
            if (isImportant) iconClass = 'timeline-icon-important';
            else if (isSuccess) iconClass = 'timeline-icon-success';
            
            html += `
                <div class="timeline-item fade-in" style="animation-delay: ${index * 0.1}s">
                    <div class="timeline-icon ${iconClass}">
                        <i data-lucide="${this.getIconForAndamento(andamento)}"></i>
                    </div>
                    <div class="timeline-card">
                        <div class="timeline-header">
                            <div class="timeline-meta">
                                <div class="timeline-date">
                                    <i data-lucide="calendar"></i>
                                    ${this.formatDateTime(andamento.data_hora)}
                                </div>
                                ${andamento.usuario ? `
                                <div class="timeline-user">
                                    <i data-lucide="user"></i>
                                    ${andamento.usuario}
                                </div>
                                ` : ''}
                            </div>
                            <div>
                                <span class="unidade-badge">${andamento.unidade || 'N/A'}</span>
                                <span class="tempo-badge">${this.getTempoEstimado(andamento)}</span>
                            </div>
                        </div>
                        <p class="timeline-description">${andamento.descricao || 'Sem descrição'}</p>
                    </div>
                </div>
            `;
        });

        return html;
    }

    /**
     * Gerar HTML dos filtros
     */
    generateFiltersHtml() {
        return `
            <div class="filters-container">
                <button class="filters-toggle" onclick="andamentosManager.toggleFilters()">
                    <span><i data-lucide="filter"></i> Filtros Avançados</span>
                    <i data-lucide="chevron-down" id="filter-chevron"></i>
                </button>
                
                <div class="filters-content" id="filters-content" style="display: none;">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Data Inicial</label>
                            <input type="date" id="filter-data-inicio" value="${this.currentFilters.dataInicio}">
                        </div>
                        
                        <div class="filter-group">
                            <label>Data Final</label>
                            <input type="date" id="filter-data-fim" value="${this.currentFilters.dataFim}">
                        </div>
                        
                        <div class="filter-group">
                            <label>Unidade</label>
                            <select id="filter-unidade">
                                <option value="">Todas as Unidades</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Buscar Texto</label>
                            <input type="text" id="filter-busca" placeholder="Buscar na descrição..." value="${this.currentFilters.busca}">
                        </div>
                        
                        <div class="filter-actions">
                            <button class="btn-primary" onclick="andamentosManager.applyFilters()">
                                <i data-lucide="search"></i> Aplicar
                            </button>
                            <button class="btn-secondary" onclick="andamentosManager.clearFilters()">
                                <i data-lucide="x"></i> Limpar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Gerar HTML do gráfico
     */
    generateChartHtml() {
        return `
            <div class="chart-section">
                <h4 style="margin: 0 0 15px 0; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="bar-chart"></i> Tempo por Unidade
                </h4>
                <canvas id="tempo-unidade-chart" width="400" height="200"></canvas>
            </div>
        `;
    }

    /**
     * Gerar HTML da paginação
     */
    generatePaginationHtml() {
        const showingStart = (this.currentPage - 1) * this.itemsPerPage + 1;
        const showingEnd = Math.min(this.currentPage * this.itemsPerPage, this.filteredAndamentos.length);
        
        return `
            <div class="pagination-controls">
                <div class="pagination-info">
                    Mostrando <strong>${showingStart}-${showingEnd}</strong> de <strong>${this.filteredAndamentos.length}</strong> andamentos
                </div>
                
                <div class="pagination-buttons">
                    <button class="btn-pagination" onclick="andamentosManager.previousPage()" ${this.currentPage <= 1 ? 'disabled' : ''}>
                        <i data-lucide="chevron-left"></i>
                        Anterior
                    </button>
                    
                    <span class="page-indicator">
                        Página <strong>${this.currentPage}</strong> de <strong>${this.totalPages}</strong>
                    </span>
                    
                    <button class="btn-pagination" onclick="andamentosManager.nextPage()" ${this.currentPage >= this.totalPages ? 'disabled' : ''}>
                        Próximo
                        <i data-lucide="chevron-right"></i>
                    </button>
                    
                    ${this.currentPage < this.totalPages ? `
                    <button class="btn-load-more" onclick="andamentosManager.loadMore()">
                        <i data-lucide="plus-circle"></i>
                        Carregar mais ${Math.min(this.itemsPerPage, this.filteredAndamentos.length - (this.currentPage * this.itemsPerPage))}
                    </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Funcionalidades de navegação
     */
    previousPage() {
        if (this.currentPage > 1) {
            this.renderPage(this.currentPage - 1);
        }
    }

    nextPage() {
        if (this.currentPage < this.totalPages) {
            this.renderPage(this.currentPage + 1);
        }
    }

    loadMore() {
        const nextPage = this.currentPage + 1;
        if (nextPage <= this.totalPages) {
            // Implementar carregamento incremental
            const startIndex = (nextPage - 1) * this.itemsPerPage;
            const endIndex = startIndex + this.itemsPerPage;
            const nextItems = this.filteredAndamentos.slice(startIndex, endIndex);
            
            this.appendToTimeline(nextItems);
            this.currentPage = nextPage;
            this.updatePaginationControls();
        }
    }

    /**
     * Adicionar itens à timeline existente
     */
    appendToTimeline(newItems) {
        const timelineScroll = document.querySelector('.timeline-scroll');
        if (timelineScroll && newItems.length > 0) {
            const newHtml = this.generateTimelineHtml(newItems);
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = newHtml;
            
            while (tempDiv.firstChild) {
                timelineScroll.appendChild(tempDiv.firstChild);
            }
            
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }

    /**
     * Atualizar controles de paginação
     */
    updatePaginationControls() {
        const paginationContainer = document.querySelector('.pagination-controls');
        if (paginationContainer) {
            paginationContainer.outerHTML = this.generatePaginationHtml();
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }

    /**
     * Funcionalidades de filtro
     */
    toggleFilters() {
        const content = document.getElementById('filters-content');
        const chevron = document.getElementById('filter-chevron');
        
        if (content) {
            const isVisible = content.style.display !== 'none';
            content.style.display = isVisible ? 'none' : 'block';
            
            if (chevron) {
                chevron.setAttribute('data-lucide', isVisible ? 'chevron-down' : 'chevron-up');
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }
    }

    applyFilters() {
        this.currentFilters = {
            dataInicio: document.getElementById('filter-data-inicio')?.value || '',
            dataFim: document.getElementById('filter-data-fim')?.value || '',
            unidade: document.getElementById('filter-unidade')?.value || '',
            busca: document.getElementById('filter-busca')?.value || ''
        };
        
        this.filteredAndamentos = this.allAndamentos.filter(andamento => {
            // Filtro por data
            if (this.currentFilters.dataInicio) {
                const dataAndamento = new Date(andamento.data_hora);
                const dataInicio = new Date(this.currentFilters.dataInicio);
                if (dataAndamento < dataInicio) return false;
            }
            
            if (this.currentFilters.dataFim) {
                const dataAndamento = new Date(andamento.data_hora);
                const dataFim = new Date(this.currentFilters.dataFim + 'T23:59:59');
                if (dataAndamento > dataFim) return false;
            }
            
            // Filtro por unidade
            if (this.currentFilters.unidade && andamento.unidade !== this.currentFilters.unidade) {
                return false;
            }
            
            // Filtro por busca
            if (this.currentFilters.busca) {
                const busca = this.currentFilters.busca.toLowerCase();
                const descricao = (andamento.descricao || '').toLowerCase();
                const usuario = (andamento.usuario || '').toLowerCase();
                if (!descricao.includes(busca) && !usuario.includes(busca)) {
                    return false;
                }
            }
            
            return true;
        });
        
        this.totalPages = Math.ceil(this.filteredAndamentos.length / this.itemsPerPage);
        this.renderPage(1);
    }

    clearFilters() {
        this.currentFilters = { dataInicio: '', dataFim: '', unidade: '', busca: '' };
        this.filteredAndamentos = [...this.allAndamentos];
        this.totalPages = Math.ceil(this.filteredAndamentos.length / this.itemsPerPage);
        this.renderPage(1);
    }

    // Métodos utilitários
    calculateTotalDaysFromFiltered() {
        return Math.ceil((this.filteredAndamentos.length * 2) / 3); // Estimativa
    }

    formatDateTime(dateTimeStr) {
        if (!dateTimeStr) return 'Data não informada';
        try {
            const date = new Date(dateTimeStr);
            return date.toLocaleDateString('pt-BR') + ' às ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        } catch (e) {
            return dateTimeStr;
        }
    }

    getIconForAndamento(andamento) {
        const desc = (andamento.descricao || '').toLowerCase();
        if (desc.includes('homolog') || desc.includes('aprovad')) return 'check-circle';
        if (desc.includes('analise') || desc.includes('análise')) return 'search';
        if (desc.includes('enviad') || desc.includes('encaminh')) return 'send';
        if (desc.includes('recebid') || desc.includes('entrada')) return 'inbox';
        return 'file-text';
    }

    getTempoEstimado(andamento) {
        // Estimativa baseada no tipo de andamento
        const desc = (andamento.descricao || '').toLowerCase();
        if (desc.includes('analise')) return '2-5 dias';
        if (desc.includes('enviad')) return '1 dia';
        if (desc.includes('homolog')) return '3-7 dias';
        return '1-3 dias';
    }

    initFilters(unidades) {
        const unidadeSelect = document.getElementById('filter-unidade');
        if (unidadeSelect && unidades) {
            unidades.forEach(unidade => {
                const option = document.createElement('option');
                option.value = unidade;
                option.textContent = unidade;
                unidadeSelect.appendChild(option);
            });
        }
    }

    initFilterEvents() {
        // Aplicar filtros em tempo real para busca
        const buscaInput = document.getElementById('filter-busca');
        if (buscaInput) {
            let timeout;
            buscaInput.addEventListener('input', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => this.applyFilters(), 500);
            });
        }
    }

    createTempoUnidadeChart(tempoData) {
        const canvas = document.getElementById('tempo-unidade-chart');
        if (canvas && typeof Chart !== 'undefined') {
            const ctx = canvas.getContext('2d');
            
            if (this.tempoUnidadeChart) {
                this.tempoUnidadeChart.destroy();
            }
            
            const labels = Object.keys(tempoData);
            const data = Object.values(tempoData).map(u => u.dias || 0);
            
            this.tempoUnidadeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Dias por Unidade',
                        data: data,
                        backgroundColor: [
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                        ],
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            title: { display: true, text: 'Dias' }
                        },
                        x: {
                            title: { display: true, text: 'Unidades' }
                        }
                    }
                }
            });
        }
    }

    showEmptyState() {
        const container = document.getElementById('conteudoAndamentos');
        if (container) {
            container.innerHTML = `
                <div class="empty-timeline">
                    <i data-lucide="inbox"></i>
                    <h3>Nenhum andamento encontrado</h3>
                    <p>Não foram encontrados andamentos para este processo.</p>
                </div>
            `;
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }

    showError(message) {
        const container = document.getElementById('conteudoAndamentos');
        if (container) {
            container.innerHTML = `
                <div class="empty-timeline">
                    <i data-lucide="alert-circle"></i>
                    <h3>Erro ao carregar</h3>
                    <p>${message}</p>
                </div>
            `;
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }
}

// Instância global do gerenciador de andamentos
let andamentosManager = new AndamentosManager();

/**
 * Função para consultar andamentos com nova interface (substituir a função existente)
 */
function consultarAndamentosModerno(nup) {
    console.log('Consultando andamentos para NUP:', nup);
    
    // Atualizar NUP no modal
    const nupDisplay = document.getElementById('nup-display');
    if (nupDisplay) {
        nupDisplay.textContent = `NUP: ${nup}`;
    }
    
    // Abrir modal moderno
    const modal = document.getElementById('modalVisualizarAndamentos');
    if (modal) {
        modal.classList.add('modern-modal');
        modal.style.display = 'block';
        
        // Carregar andamentos
        andamentosManager.loadAndamentos(nup);
    }
}

/**
 * Função para gerar relatório de andamentos
 */
function gerarRelatorioAndamentos() {
    const nupElement = document.getElementById('nup-display');
    if (!nupElement || !nupElement.textContent) {
        alert('Erro: NUP não encontrado. Abra primeiro a timeline de um processo.');
        return;
    }
    
    // Extrair NUP do texto "NUP: 25000.123456/2023-15"
    const nupText = nupElement.textContent;
    const nupMatch = nupText.match(/NUP:\s*(.+)/);
    if (!nupMatch) {
        alert('Erro: Não foi possível extrair o NUP.');
        return;
    }
    
    const nup = nupMatch[1].trim();
    
    // Verificar se o NUP não é "Carregando..." ou similar
    if (nup === 'Carregando...' || nup.toLowerCase().includes('carregando')) {
        alert('Erro: Aguarde o carregamento dos andamentos antes de gerar o relatório.');
        return;
    }
    
    // Criar modal de configuração do relatório
    const modal = document.createElement('div');
    modal.id = 'modalRelatorioAndamentos';
    modal.className = 'modal';
    modal.style.display = 'block';
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i data-lucide="file-text"></i> Relatório de Andamentos</h3>
                <span class="close" onclick="fecharModalRelatorioAndamentos()">&times;</span>
            </div>
            <div class="modal-body">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <strong>Processo:</strong> ${nup}
                </div>
                
                <form id="formRelatorioAndamentos">
                    <div class="form-group">
                        <label>Período (opcional)</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="font-size: 12px; color: #666;">Data Inicial</label>
                                <input type="date" name="data_inicial" id="rel_data_inicial_and">
                            </div>
                            <div>
                                <label style="font-size: 12px; color: #666;">Data Final</label>
                                <input type="date" name="data_final" id="rel_data_final_and">
                            </div>
                        </div>
                        <small style="color: #666;">Deixe em branco para incluir todos os andamentos</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Formato de Saída</label>
                        <select name="formato" required>
                            <option value="html">Visualizar (HTML)</option>
                            <option value="excel">Excel (CSV)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="incluir_graficos" checked> 
                            Incluir gráficos e estatísticas
                        </label>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                        <button type="button" onclick="fecharModalRelatorioAndamentos()" class="btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-primary">
                            <i data-lucide="download"></i>
                            Gerar Relatório
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Definir data final como hoje
    document.getElementById('rel_data_final_and').value = new Date().toISOString().split('T')[0];
    
    // Inicializar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Event listener para o form
    document.getElementById('formRelatorioAndamentos').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const params = new URLSearchParams();
        
        // Adicionar NUP
        params.append('nup', nup);
        
        // Adicionar outros parâmetros
        for (const [key, value] of formData) {
            if (value) {
                params.append(key, value);
            }
        }
        
        const formato = formData.get('formato');
        const url = 'relatorios/gerar_relatorio_andamentos.php?' + params.toString();
        
        // Abrir relatório
        if (formato === 'html') {
            window.open(url, '_blank');
        } else {
            // Para Excel, fazer download
            window.location.href = url;
        }
        
        // Fechar modal
        fecharModalRelatorioAndamentos();
    });
}

/**
 * Fechar modal de relatório de andamentos
 */
function fecharModalRelatorioAndamentos() {
    const modal = document.getElementById('modalRelatorioAndamentos');
    if (modal) {
        modal.remove();
    }
}