/**
 * Construtor de Gráficos PowerBI - Sistema CGLIC
 * Sistema completo para criação de gráficos personalizados
 */

class ConstrutorGraficos {
    constructor() {
        this.graficoAtual = null;
        this.configuracoesSalvas = [];
        this.init();
    }

    init() {
        // Aguardar o DOM carregar
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.inicializar());
        } else {
            this.inicializar();
        }
    }

    inicializar() {
        console.log('=== Inicializando instância do Construtor de Gráficos ===');
        
        // Verificar se os elementos existem
        const tipoGrafico = document.getElementById('tipoGrafico');
        const campoX = document.getElementById('campoX');
        const campoY = document.getElementById('campoY');
        const grupoValorY = document.getElementById('grupoValorY');
        const canvas = document.getElementById('graficoPersonalizado');
        
        console.log('Elementos encontrados:', {
            tipoGrafico: !!tipoGrafico,
            campoX: !!campoX,
            campoY: !!campoY,
            grupoValorY: !!grupoValorY,
            canvas: !!canvas
        });
        
        if (!tipoGrafico || !campoX || !campoY || !canvas) {
            console.error('Elementos essenciais não encontrados, abortando inicialização');
            return;
        }
        
        // Garantir que o campo Y está visível
        if (grupoValorY) {
            grupoValorY.style.display = 'block';
            grupoValorY.style.visibility = 'visible';
            console.log('Campo Y forçado para visível');
        }
        
        if (campoY) {
            campoY.style.display = 'block';
            campoY.style.visibility = 'visible';
            console.log('Select Y forçado para visível');
        }
        
        // Configurar eventos
        this.configurarEventos();
        
        // Atualizar título inicial
        this.atualizarTituloGrafico();
        
        // Gerar primeiro gráfico com configuração padrão (sem timeout)
        console.log('Gerando gráfico inicial...');
        this.atualizarGrafico().catch(error => {
            console.error('Erro ao gerar gráfico inicial:', error);
        });
        
        console.log('=== Inicialização concluída ===');
    }

    configurarEventos() {
        // Eventos dos seletores
        const tipoGrafico = document.getElementById('tipoGrafico');
        const campoX = document.getElementById('campoX');
        const campoY = document.getElementById('campoY');
        
        if (tipoGrafico) {
            tipoGrafico.addEventListener('change', () => this.atualizarOpcoesCampos());
        }
        
        if (campoX) {
            campoX.addEventListener('change', () => this.atualizarGrafico());
        }
        
        if (campoY) {
            campoY.addEventListener('change', () => this.atualizarGrafico());
        }
        
        // Eventos dos filtros
        const filtroAno = document.getElementById('filtroAno');
        const filtroSituacao = document.getElementById('filtroSituacao');
        const filtroDataInicio = document.getElementById('filtroDataInicio');
        const filtroDataFim = document.getElementById('filtroDataFim');
        
        if (filtroAno) {
            filtroAno.addEventListener('change', () => this.atualizarGrafico());
        }
        
        if (filtroSituacao) {
            filtroSituacao.addEventListener('change', () => this.atualizarGrafico());
        }
        
        if (filtroDataInicio) {
            filtroDataInicio.addEventListener('change', () => this.atualizarGrafico());
        }
        
        if (filtroDataFim) {
            filtroDataFim.addEventListener('change', () => this.atualizarGrafico());
        }
    }

    /**
     * Atualizar opções de campos baseado no tipo de gráfico
     */
    atualizarOpcoesCampos() {
        const tipoGrafico = document.getElementById('tipoGrafico')?.value;
        const grupoValorY = document.getElementById('grupoValorY');
        const campoY = document.getElementById('campoY');
        
        if (!tipoGrafico || !grupoValorY) return;
        
        // Para gráficos de pizza e rosca, o campo Y ainda é importante para determinar o valor das fatias
        // Então vamos sempre mostrar o campo Y, mas com diferentes opções
        grupoValorY.style.display = 'block';
        
        // Ajustar label baseado no tipo de gráfico
        const labelY = grupoValorY.querySelector('label');
        if (labelY) {
            if (['pie', 'doughnut'].includes(tipoGrafico)) {
                labelY.textContent = 'Valores das Fatias';
            } else if (['bar', 'horizontalBar'].includes(tipoGrafico)) {
                labelY.textContent = 'Eixo Y (Valores)';
            } else if (['line', 'area'].includes(tipoGrafico)) {
                labelY.textContent = 'Eixo Y (Valores)';
            } else {
                labelY.textContent = 'Campo Y (Valores)';
            }
        }
        
        // Atualizar título do gráfico
        this.atualizarTituloGrafico();
        
        // Regenerar gráfico
        this.atualizarGrafico();
        
        // Reinicializar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    /**
     * Atualizar título do gráfico baseado nas seleções
     */
    atualizarTituloGrafico() {
        const tipoGrafico = document.getElementById('tipoGrafico')?.value;
        const campoX = document.getElementById('campoX')?.value;
        const campoY = document.getElementById('campoY')?.value;
        const tituloElement = document.getElementById('tituloGrafico');
        
        if (!tituloElement) return;
        
        const tipos = {
            bar: 'Barras',
            line: 'Linha',
            pie: 'Pizza',
            doughnut: 'Rosca',
            horizontalBar: 'Barras Horizontais',
            area: 'Área'
        };
        
        const campos = {
            categoria_contratacao: 'por Categoria',
            area_requisitante: 'por Área',
            situacao_execucao: 'por Situação',
            prioridade: 'por Prioridade',
            urgente: 'por Urgência',
            valor_total_contratacao: 'por Valor Total',
            quantidade_dfds: 'por Quantidade de DFDs'
        };
        
        const nomeY = this.obterNomeValorY(campoX, campoY);
        const titulo = `${tipos[tipoGrafico] || 'Gráfico'}: ${nomeY} ${campos[campoX] || ''}`;
        tituloElement.textContent = titulo;
        
        // Reinicializar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    /**
     * Resetar canvas completamente
     */
    resetarCanvas() {
        const canvas = document.getElementById('graficoPersonalizado');
        if (!canvas) return;
        
        // Destruir todos os gráficos do Chart.js
        Chart.helpers.each(Chart.instances, (instance) => {
            if (instance.canvas === canvas) {
                instance.destroy();
            }
        });
        
        // Limpar o canvas
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Resetar a instância atual
        this.graficoAtual = null;
    }

    /**
     * Atualizar gráfico com os dados atuais
     */
    async atualizarGrafico() {
        console.log('=== Iniciando atualizarGrafico ===');
        
        const loadingElement = document.getElementById('loadingGrafico');
        const estatisticasElement = document.getElementById('estatisticasGrafico');
        const canvasElement = document.getElementById('graficoPersonalizado');
        
        console.log('Elementos encontrados:', {
            loading: !!loadingElement,
            estatisticas: !!estatisticasElement,
            canvas: !!canvasElement
        });
        
        if (!loadingElement || !canvasElement) {
            console.error('Elementos essenciais não encontrados!');
            this.mostrarErro('Elementos do gráfico não encontrados na página');
            return;
        }
        
        try {
            // Mostrar loading
            loadingElement.style.display = 'block';
            if (estatisticasElement) {
                estatisticasElement.style.display = 'none';
            }
            
            // Resetar canvas completamente
            this.resetarCanvas();
            
            // Obter configurações atuais
            const config = this.obterConfiguracaoAtual();
            console.log('Configuração atual:', config);
            
            // Construir URL da API
            const url = `api/construtor_graficos.php?acao=obter_dados&${new URLSearchParams(config)}`;
            console.log('URL da API:', url);
            
            // Buscar dados da API
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('Resposta da API:', {
                ok: response.ok,
                status: response.status,
                statusText: response.statusText
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Erro HTTP:', errorText);
                throw new Error(`Erro na API: ${response.status} - ${errorText}`);
            }
            
            const data = await response.json();
            console.log('Dados completos da API:', data);
            
            if (!data.success) {
                console.error('API retornou erro:', data.error);
                throw new Error(data.error || 'Erro desconhecido da API');
            }
            
            // Verificar se os dados estão válidos
            if (!data.dados || !data.dados.labels || !data.dados.datasets) {
                console.error('Estrutura de dados inválida:', data.dados);
                throw new Error('Dados do gráfico com estrutura inválida');
            }
            
            console.log('Dados válidos recebidos:', {
                labels: data.dados.labels.length,
                datasets: data.dados.datasets.length
            });
            
            // Criar novo gráfico
            this.criarGrafico(config.tipoGrafico, data.dados);
            
            // Atualizar estatísticas
            if (data.estatisticas) {
                this.atualizarEstatisticas(data.estatisticas);
            }
            
            console.log('=== Gráfico atualizado com sucesso ===');
            
        } catch (error) {
            console.error('=== Erro ao atualizar gráfico ===', error);
            this.mostrarErro('Erro ao carregar dados do gráfico: ' + error.message);
            
            // Mostrar erro no canvas
            const ctx = canvasElement.getContext('2d');
            ctx.clearRect(0, 0, canvasElement.width, canvasElement.height);
            ctx.fillStyle = '#e74c3c';
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Erro ao carregar gráfico', canvasElement.width / 2, canvasElement.height / 2);
            
        } finally {
            // Esconder loading
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }
        }
    }

    /**
     * Criar gráfico usando Chart.js
     */
    criarGrafico(tipo, dados) {
        const canvas = document.getElementById('graficoPersonalizado');
        if (!canvas) {
            console.error('Canvas não encontrado');
            return;
        }
        
        // Verificar se já existe um gráfico no canvas e destruir
        const existingChart = Chart.getChart(canvas);
        if (existingChart) {
            existingChart.destroy();
        }
        
        const ctx = canvas.getContext('2d');
        
        // Debug dos dados antes de criar o gráfico
        console.log('Tipo do gráfico:', tipo);
        console.log('Dados para o gráfico:', dados);
        console.log('Labels:', dados.labels);
        console.log('Datasets:', dados.datasets);
        
        // Configurações base
        const config = {
            type: tipo === 'horizontalBar' ? 'bar' : tipo,
            data: dados,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: ['pie', 'doughnut'].includes(tipo),
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                let value;
                                
                                // Extrair valor corretamente baseado no tipo de gráfico
                                if (context.parsed && typeof context.parsed === 'object') {
                                    // Para gráficos de barra/linha (parsed é objeto {x, y})
                                    value = context.parsed.y || context.parsed.x || context.parsed;
                                } else {
                                    // Para gráficos de pizza/rosca (parsed é número direto)
                                    value = context.parsed || context.raw;
                                }
                                
                                // Se ainda for objeto, tentar pegar a propriedade mais provável
                                if (typeof value === 'object' && value !== null) {
                                    value = value.y || value.x || value.value || value._value || 0;
                                }
                                
                                // Formatação baseada no tipo de dado
                                if (typeof value === 'number') {
                                    if (value > 1000000) {
                                        return `${label}: ${(value / 1000000).toFixed(1)}M`;
                                    } else if (value > 1000) {
                                        return `${label}: ${(value / 1000).toFixed(0)}k`;
                                    } else {
                                        return `${label}: ${value.toLocaleString('pt-BR')}`;
                                    }
                                }
                                
                                // Fallback para strings ou outros tipos
                                return `${label}: ${value}`;
                            }
                        }
                    }
                },
                scales: this.obterConfiguracoesEscala(tipo)
            }
        };
        
        // Configurações específicas para barra horizontal
        if (tipo === 'horizontalBar') {
            config.options.indexAxis = 'y';
        }
        
        // Criar gráfico
        this.graficoAtual = new Chart(ctx, config);
    }

    /**
     * Obter configurações de escala baseado no tipo de gráfico
     */
    obterConfiguracoesEscala(tipo) {
        if (['pie', 'doughnut'].includes(tipo)) {
            return {};
        }
        
        const config = {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        if (value > 1000000) {
                            return (value / 1000000).toFixed(1) + 'M';
                        } else if (value > 1000) {
                            return (value / 1000).toFixed(0) + 'k';
                        }
                        return value.toLocaleString('pt-BR');
                    }
                }
            },
            x: {
                ticks: {
                    maxRotation: 45
                }
            }
        };
        
        // Para barra horizontal, inverter x e y
        if (tipo === 'horizontalBar') {
            return {
                x: config.y,
                y: config.x
            };
        }
        
        return config;
    }

    /**
     * Atualizar estatísticas do gráfico
     */
    atualizarEstatisticas(stats) {
        const estatisticasElement = document.getElementById('estatisticasGrafico');
        const totalElement = document.getElementById('totalRegistros');
        const valorElement = document.getElementById('valorTotal');
        const maiorElement = document.getElementById('maiorCategoria');
        
        if (!estatisticasElement) return;
        
        if (totalElement) {
            totalElement.textContent = stats.total_registros.toLocaleString('pt-BR');
        }
        
        if (valorElement) {
            valorElement.textContent = this.formatarMoeda(stats.valor_total);
        }
        
        if (maiorElement) {
            maiorElement.textContent = stats.maior_categoria;
        }
        
        estatisticasElement.style.display = 'block';
    }

    /**
     * Obter configuração atual dos controles
     */
    obterConfiguracaoAtual() {
        return {
            tipoGrafico: document.getElementById('tipoGrafico')?.value || 'bar',
            campoX: document.getElementById('campoX')?.value || 'categoria_contratacao',
            campoY: document.getElementById('campoY')?.value || 'categoria_contratacao',
            filtroAno: document.getElementById('filtroAno')?.value || '',
            filtroSituacao: document.getElementById('filtroSituacao')?.value || '',
            filtroDataInicio: document.getElementById('filtroDataInicio')?.value || '',
            filtroDataFim: document.getElementById('filtroDataFim')?.value || ''
        };
    }

    /**
     * Exportar gráfico atual em HTML
     */
    exportarGraficoAtualHTML() {
        console.log('=== Iniciando exportação HTML ===');
        try {
            const config = this.obterConfiguracaoAtual();
            console.log('Configuração obtida para HTML:', config);
            this.exportarGraficoConfig(config);
        } catch (error) {
            console.error('Erro na exportação HTML:', error);
            this.mostrarErro('Erro ao exportar HTML: ' + error.message);
        }
    }
    
    
    /**
     * Exportar gráfico em HTML
     */
    exportarGraficoConfig(config) {
        console.log('=== Exportando gráfico HTML ===');
        console.log('Config recebida:', config);
        
        try {
            const params = new URLSearchParams({
                formato: 'html',
                tipoGrafico: config.tipoGrafico,
                campoX: config.campoX,
                campoY: config.campoY,
                filtroAno: config.filtroAno,
                filtroSituacao: config.filtroSituacao,
                filtroDataInicio: config.filtroDataInicio,
                filtroDataFim: config.filtroDataFim
            });
            
            const url = `api/exportar_grafico_atual.php?${params.toString()}`;
            console.log('URL gerada:', url);
            
            console.log('Abrindo HTML em nova janela...');
            const newWindow = window.open(url, '_blank');
            if (!newWindow) {
                throw new Error('Pop-up bloqueado. Permita pop-ups para este site.');
            }
            
        } catch (error) {
            console.error('Erro na exportação:', error);
            this.mostrarErro('Erro na exportação: ' + error.message);
        }
    }

    /**
     * Obter nome amigável do campo
     */
    obterNomeCampo(campo) {
        const nomes = {
            // Campos categóricos
            categoria_contratacao: 'Categoria',
            area_requisitante: 'Área',
            situacao_execucao: 'Situação',
            prioridade: 'Prioridade',
            urgente: 'Urgência',
            
            // Campos de valores
            valor_total_contratacao: 'Valor Total',
            quantidade_dfds: 'Quantidade DFDs'
        };
        
        return nomes[campo] || campo;
    }

    /**
     * Obter nome do valor Y baseado nos campos X e Y
     */
    obterNomeValorY(campoX, campoY) {
        if (campoX === campoY) {
            // Quando X e Y são iguais, Y representa contagem
            return 'Quantidade de Registros';
        }
        
        if (campoY === 'quantidade_dfds') {
            return 'Quantidade Única de DFDs';
        }
        
        if (campoY === 'valor_total_contratacao') {
            return 'Soma dos Valores';
        }
        
        return this.obterNomeCampo(campoY);
    }

    /**
     * Carregar configuração específica
     */
    carregarConfiguracao(id) {
        const config = this.configuracoesSalvas.find(c => c.id === id);
        if (!config) return;
        
        const conf = config.configuracao;
        
        // Aplicar configurações aos controles
        this.setElementValue('tipoGrafico', conf.tipoGrafico);
        this.setElementValue('campoX', conf.campoX);
        this.setElementValue('campoY', conf.campoY);
        this.setElementValue('filtroAno', conf.filtroAno);
        this.setElementValue('filtroSituacao', conf.filtroSituacao);
        this.setElementValue('filtroDataInicio', conf.filtroDataInicio);
        this.setElementValue('filtroDataFim', conf.filtroDataFim);
        
        // Atualizar opções e gráfico
        this.atualizarOpcoesCampos();
        this.atualizarGrafico();
        
        this.mostrarSucesso(`Configuração "${config.nome}" carregada!`);
    }

    /**
     * Excluir configuração
     */
    async excluirConfiguracao(id) {
        const config = this.configuracoesSalvas.find(c => c.id === id);
        if (!config) return;
        
        if (!confirm(`Tem certeza que deseja excluir a configuração "${config.nome}"?`)) {
            return;
        }
        
        try {
            const response = await fetch(`api/construtor_graficos.php?acao=excluir_configuracao&id=${id}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.mostrarSucesso('Configuração excluída com sucesso!');
                this.carregarConfiguracoes();
            } else {
                throw new Error(data.error || 'Erro ao excluir');
            }
            
        } catch (error) {
            console.error('Erro ao excluir configuração:', error);
            this.mostrarErro('Erro ao excluir configuração: ' + error.message);
        }
    }


    /**
     * Utilities
     */
    setElementValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.value = value;
        }
    }

    formatarMoeda(valor) {
        if (valor > 1000000) {
            return `R$ ${(valor / 1000000).toFixed(1)}M`;
        } else if (valor > 1000) {
            return `R$ ${(valor / 1000).toFixed(0)}k`;
        } else {
            return `R$ ${valor.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
        }
    }

    mostrarSucesso(mensagem) {
        if (typeof showNotification === 'function') {
            showNotification(mensagem, 'success');
        } else {
            alert(mensagem);
        }
    }

    mostrarErro(mensagem) {
        if (typeof showNotification === 'function') {
            showNotification(mensagem, 'error');
        } else {
            alert(mensagem);
        }
    }

    mostrarInfo(mensagem) {
        if (typeof showNotification === 'function') {
            showNotification(mensagem, 'info');
        } else {
            alert(mensagem);
        }
    }
}

// Instância global
let construtorGraficos;

// Funções globais para compatibilidade
function atualizarOpcoesCampos() {
    if (construtorGraficos) {
        construtorGraficos.atualizarOpcoesCampos();
    }
}

function atualizarGrafico() {
    if (construtorGraficos) {
        construtorGraficos.atualizarGrafico();
    }
}

function exportarGraficoAtualHTML() {
    console.log('=== Exportar HTML clicado ===');
    if (window.construtorGraficos) {
        console.log('Construtor encontrado, chamando exportação HTML');
        window.construtorGraficos.exportarGraficoAtualHTML();
    } else {
        console.error('Construtor não encontrado!');
        alert('Erro: Sistema de gráficos não inicializado. Recarregue a página.');
    }
}

// Inicializar apenas uma vez quando DOM estiver pronto
function initConstrutorGraficos() {
    // Verificar se já foi inicializado
    if (window.construtorGraficos) {
        console.log('Construtor já inicializado, ignorando...');
        return;
    }
    
    // Verificar se os elementos existem
    const tipoGrafico = document.getElementById('tipoGrafico');
    if (!tipoGrafico) {
        console.log('Elementos do construtor não encontrados, tentando novamente em 500ms...');
        setTimeout(initConstrutorGraficos, 500);
        return;
    }
    
    console.log('Inicializando Construtor de Gráficos...');
    
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
        console.log('Opções do campo Y:', campoY.options.length);
    }
    
    // Criar instância
    window.construtorGraficos = new ConstrutorGraficos();
    console.log('Construtor de Gráficos inicializado com sucesso!');
}

// Inicializar quando DOM estiver carregado
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initConstrutorGraficos);
} else {
    // DOM já carregado, inicializar imediatamente
    initConstrutorGraficos();
}