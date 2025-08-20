/**
 * CONTRATOS DASHBOARD - JavaScript
 * Sistema CGLIC - Ministério da Saúde
 */

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Configurar sistema
    setupNavigation();
    setupSidebar();
});

// Função para inicializar Lucide de forma simples e confiável
function initializeLucide() {
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        try {
            lucide.createIcons();
        } catch (error) {
            // Silently retry once
            setTimeout(() => {
                try {
                    lucide.createIcons();
                } catch (e) {
                    // Fail silently
                }
            }, 500);
        }
    }
}

// Configurar navegação - SIMPLIFICADO
function setupNavigation() {
    // Garantir função global
    window.showSection = showSection;
}

// Configurar sidebar - SIMPLIFICADO
function setupSidebar() {
    window.toggleSidebar = toggleSidebar;
}

// Navegação entre seções - VERSÃO SIMPLIFICADA
function showSection(sectionId) {
    // Esconder todas as seções
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Mostrar seção selecionada
    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    // Atualizar navegação ativa
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Marcar item ativo
    const activeItem = document.querySelector(`[onclick*="showSection('${sectionId}')"]`);
    if (activeItem) {
        activeItem.classList.add('active');
    }
    
    // Auto-colapsar sidebar em mobile
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    }
    
    // Recriar ícones
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Toggle sidebar - VERSÃO SIMPLIFICADA baseada no dashboard principal
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleIcon = document.querySelector('#sidebarToggle i');
    
    if (sidebar) {
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            // Mobile: toggle mobile-open class
            sidebar.classList.toggle('mobile-open');
            
            if (toggleIcon) {
                if (sidebar.classList.contains('mobile-open')) {
                    toggleIcon.setAttribute('data-lucide', 'x');
                } else {
                    toggleIcon.setAttribute('data-lucide', 'menu');
                }
            }
        } else {
            // Desktop: toggle collapsed class
            sidebar.classList.toggle('collapsed');
            
            if (toggleIcon) {
                if (sidebar.classList.contains('collapsed')) {
                    toggleIcon.setAttribute('data-lucide', 'panel-left-open');
                } else {
                    toggleIcon.setAttribute('data-lucide', 'menu');
                }
            }
        }
        
        // Atualizar ícones
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

// Garantir navegação funcione globalmente
window.showSection = showSection;
window.toggleSidebar = toggleSidebar;

// Inicializar Lucide também no window.load como backup
window.addEventListener('load', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

// Setup inicial do módulo
async function executarSetup() {
    if (!confirm('Deseja executar o setup do módulo de Contratos?\n\nEsta operação irá:\n- Criar as tabelas necessárias\n- Configurar views e índices\n- Preparar o sistema para gestão de contratos')) {
        return;
    }
    
    try {
        showNotification('Executando setup do módulo...', 'info');
        
        const response = await fetch('api/contratos_setup.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'setup'})
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Setup executado com sucesso! Recarregando página...', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('Erro no setup: ' + (result.error || 'Erro desconhecido'), 'error');
        }
    } catch (error) {
        showNotification('Erro de conexão: ' + error.message, 'error');
    }
}

// Modal para adicionar contrato
function abrirModalAdicionar() {
    document.getElementById('tituloModalContrato').textContent = 'Criar Novo Contrato';
    document.getElementById('btnTextoSalvar').textContent = 'Salvar Contrato';
    document.getElementById('contratoId').value = '';
    const acaoInput = document.querySelector('input[name="acao"]');
    if (acaoInput) acaoInput.value = 'criar_contrato';
    
    // Limpar formulário
    const form = document.getElementById('formContrato');
    if (form) {
        form.reset();
        const anoInput = document.getElementById('ano_contrato');
        const statusInput = document.getElementById('status_contrato');
        if (anoInput) anoInput.value = new Date().getFullYear();
        if (statusInput) statusInput.value = 'ativo';
    }
    
    // Abrir modal
    const modal = document.getElementById('modalCriarContrato');
    if (modal) {
        modal.style.display = 'block';
    }
    
    // Reinicializar ícones
    setTimeout(() => {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }, 100);
}

// Modal para editar contrato
async function editarContrato(id) {
    try {
        showNotification('Carregando dados do contrato...', 'info');
        
        const response = await fetch(`api/contratos_crud.php?action=get&id=${id}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            const contrato = result.data;
            
            // Configurar modal para edição
            document.getElementById('tituloModalContrato').textContent = 'Editar Contrato';
            document.getElementById('btnTextoSalvar').textContent = 'Atualizar Contrato';
            document.getElementById('contratoId').value = contrato.id;
            const acaoInput = document.querySelector('input[name="acao"]');
            if (acaoInput) acaoInput.value = 'editar_contrato';
            
            // Preencher campos
            const campos = [
                'numero_contrato', 'ano_contrato', 'numero_sei', 'nome_empresa',
                'cnpj_cpf', 'modalidade', 'objeto_servico', 'valor_inicial',
                'valor_atual', 'data_assinatura', 'data_inicio', 'data_fim',
                'status_contrato', 'area_gestora', 'finalidade', 'fiscais', 'observacoes'
            ];
            
            campos.forEach(campo => {
                const element = document.getElementById(campo);
                if (element) {
                    element.value = contrato[campo] || '';
                }
            });
            
            // Abrir modal
            const modal = document.getElementById('modalCriarContrato');
            if (modal) {
                modal.style.display = 'block';
            }
            
            // Reinicializar ícones
            setTimeout(() => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 100);
            
            showNotification('Dados carregados com sucesso!', 'success');
            
        } else {
            showNotification('Erro ao carregar contrato: ' + (result.error || 'Erro desconhecido'), 'error');
        }
    } catch (error) {
        showNotification('Erro ao carregar contrato: ' + error.message, 'error');
    }
}

// Excluir contrato
async function excluirContrato(id) {
    if (!confirm('Tem certeza que deseja excluir este contrato?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    showNotification('Exclusão em desenvolvimento', 'info');
    // TODO: Implementar exclusão
}


// Ver detalhes do contrato
async function verDetalhes(contratoId) {
    try {
        showNotification('Carregando detalhes do contrato...', 'info');
        
        const response = await fetch(`api/contratos_detalhes.php?id=${contratoId}`);
        
        if (!response.ok) {
            throw new Error('Erro ao carregar detalhes');
        }
        
        const html = await response.text();
        
        const detalhesContent = document.getElementById('detalhesContent');
        const detalhesModal = document.getElementById('detalhesModal');
        
        if (detalhesContent) detalhesContent.innerHTML = html;
        if (detalhesModal) detalhesModal.style.display = 'block';
        
        // Reinicializar ícones Lucide no modal
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        
    } catch (error) {
        showNotification('Erro ao carregar detalhes: ' + error.message, 'error');
    }
}

// Gerar relatório
function gerarRelatorio(tipo = 'geral') {
    showNotification('Relatórios em desenvolvimento', 'info');
    // TODO: Implementar relatórios
}

// Função para confirmar exclusão
function confirmarExclusao(contratoId, numeroContrato) {
    const confirmacao = confirm(`Tem certeza de que deseja excluir o contrato ${numeroContrato}?\n\nEsta ação não pode ser desfeita.`);
    
    if (confirmacao) {
        excluirContratoFunc(contratoId);
    }
}

// Função para excluir contrato
async function excluirContratoFunc(contratoId) {
    try {
        showNotification('Excluindo contrato...', 'info');
        
        const formData = new FormData();
        formData.append('acao', 'excluir_contrato');
        formData.append('id', contratoId);
        
        const response = await fetch('process.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Contrato excluído com sucesso!', 'success');
            // Recarregar página após 2 segundos
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showNotification('Erro ao excluir contrato: ' + result.message, 'error');
        }
        
    } catch (error) {
        console.error('Erro ao excluir contrato:', error);
        showNotification('Erro ao excluir contrato', 'error');
    }
}

// Função duplicada removida - usando apenas a versão completa acima

// Exportar contratos - função atualizada
function exportarContratos(formato = 'csv') {
    // Pegar filtros atuais da página
    const params = new URLSearchParams(window.location.search);
    const busca = params.get('busca') || '';
    const status = params.get('status') || '';
    const vencimento = params.get('vencimento') || '';
    
    // Construir URL de exportação
    const exportUrl = new URL('api/exportar_contratos.php', window.location.origin + '/sistema_licitacao/');
    exportUrl.searchParams.set('formato', formato);
    exportUrl.searchParams.set('busca', busca);
    exportUrl.searchParams.set('status', status);
    exportUrl.searchParams.set('vencimento', vencimento);
    
    showNotification('Preparando exportação...', 'info');
    
    // Abrir em nova aba
    window.open(exportUrl.href, '_blank');
    
    setTimeout(() => {
        showNotification('Exportação iniciada! Verifique os downloads.', 'success');
    }, 1000);
}

// Utilitários de modal - SIMPLIFICADOS
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

function fecharModal(modalId) {
    closeModal(modalId);
}

// Função para abrir modal - SIMPLES
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        
        // Focar no primeiro input
        setTimeout(() => {
            const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        }, 100);
    }
}

// Sistema básico de validação
function validateField(field) {
    const value = field.value.trim();
    const isValid = value !== '';
    
    field.classList.toggle('error', !isValid);
    field.classList.toggle('valid', isValid);
    
    return isValid;
}


// Configurar submit do formulário quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formContrato');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const contratoIdInput = document.getElementById('contratoId');
            const isEdit = contratoIdInput && contratoIdInput.value !== '';
            
            try {
                showNotification(isEdit ? 'Atualizando contrato...' : 'Salvando contrato...', 'info');
                
                const response = await fetch('api/contratos_crud.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(isEdit ? 'Contrato atualizado com sucesso!' : 'Contrato criado com sucesso!', 'success');
                    fecharModal('modalCriarContrato');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification('Erro ao salvar: ' + (result.error || 'Erro desconhecido'), 'error');
                }
            } catch (error) {
                showNotification('Erro de conexão: ' + error.message, 'error');
            }
        });
    }

    // Fechamento de modais
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const visibleModal = document.querySelector('.modal[style*="block"]');
            if (visibleModal) {
                visibleModal.style.display = 'none';
            }
        }
    });

    // Auto-refresh para alertas (a cada 10 minutos)
    setInterval(() => {
        fetch('api/get_alertas.php?modulo=contratos')
            .then(response => response.json())
            .then(data => {
                if (data.alertas && data.alertas.length > 0) {
                    // Atualizar badge de alertas se necessário
                    const badge = document.querySelector('.alerts-section .badge');
                    if (badge) {
                        badge.textContent = data.alertas.length;
                    }
                }
            })
            .catch(error => console.log('Erro ao verificar alertas:', error));
    }, 600000); // 10 minutos

    // Atalhos de teclado
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            switch(e.key) {
                case 'n':
                    e.preventDefault();
                    if (typeof abrirModalAdicionar === 'function') {
                        abrirModalAdicionar();
                    }
                    break;
                case 'f':
                    e.preventDefault();
                    const buscaInput = document.querySelector('input[name="busca"]');
                    if (buscaInput) buscaInput.focus();
                    break;
            }
        }
    });
});