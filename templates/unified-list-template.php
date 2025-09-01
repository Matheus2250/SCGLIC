<?php
/**
 * Template Unificado para Listas - Sistema CGLIC
 * 
 * Variáveis necessárias:
 * - $listType: 'qualificacoes', 'planejamento', 'licitacoes', 'contratos'
 * - $listTitle: Título da lista
 * - $listIcon: Ícone Lucide para o título
 * - $createPermission: Permissão para criar novos registros
 * - $exportPermission: Permissão para exportar dados
 * - $items: Array de itens para exibir
 * - $totalItems: Total de registros
 * - $currentPage: Página atual
 * - $itemsPerPage: Itens por página
 * - $filters: Array com configuração dos filtros
 */

// Valores padrão se não definidos
$listType = $listType ?? 'default';
$listTitle = $listTitle ?? 'Lista';
$listIcon = $listIcon ?? 'list';
$createPermission = $createPermission ?? false;
$exportPermission = $exportPermission ?? false;
$items = $items ?? [];
$totalItems = $totalItems ?? 0;
$currentPage = $currentPage ?? 1;
$itemsPerPage = $itemsPerPage ?? 10;
$filters = $filters ?? [];
?>

<!-- CSS Unificado -->
<link rel="stylesheet" href="assets/unified-lists.css">

<!-- Container Principal -->
<div class="unified-list-container">
    
    <!-- Header -->
    <div class="unified-list-header">
        <h2 class="unified-list-title">
            <i data-lucide="<?php echo htmlspecialchars($listIcon); ?>"></i>
            <?php echo htmlspecialchars($listTitle); ?>
        </h2>
        
        <div class="unified-list-actions">
            <!-- Toggle Lista/Cards -->
            <div class="unified-view-toggle">
                <button id="btn-lista-<?php echo $listType; ?>" 
                        class="unified-toggle-btn active" 
                        onclick="unifiedListManager.switchView('lista')">
                    <i data-lucide="list"></i>
                    Lista
                </button>
                <button id="btn-cards-<?php echo $listType; ?>" 
                        class="unified-toggle-btn" 
                        onclick="unifiedListManager.switchView('cards')">
                    <i data-lucide="grid-3x3"></i>
                    Cards
                </button>
            </div>
            
            <!-- Botões de Ação -->
            <?php if ($createPermission): ?>
            <button onclick="abrirModalCriar<?php echo ucfirst($listType); ?>()" 
                    class="unified-btn unified-btn-primary">
                <i data-lucide="plus-circle"></i>
                <?php 
                switch ($listType) {
                    case 'qualificacoes': echo 'Nova Qualificação'; break;
                    case 'planejamento': echo 'Importar PCA'; break;
                    case 'licitacoes': echo 'Nova Licitação'; break;
                    case 'contratos': echo 'Novo Contrato'; break;
                    default: echo 'Novo Registro'; break;
                }
                ?>
            </button>
            <?php endif; ?>
            
            <?php if ($exportPermission): ?>
            <button onclick="unifiedListManager.exportData('csv')" 
                    class="unified-btn unified-btn-success">
                <i data-lucide="download"></i>
                Exportar
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Seção de Filtros -->
    <?php if (!empty($filters)): ?>
    <div class="unified-filters-section">
        <form id="filtros-<?php echo $listType; ?>" method="GET">
            <div class="unified-filters-container">
                
                <?php foreach ($filters as $filter): ?>
                <div class="unified-filter-group">
                    <label class="unified-filter-label">
                        <?php echo htmlspecialchars($filter['label']); ?>
                    </label>
                    
                    <?php if ($filter['type'] === 'text'): ?>
                        <input type="text" 
                               name="<?php echo $filter['name']; ?>" 
                               class="unified-filter-input"
                               placeholder="<?php echo htmlspecialchars($filter['placeholder'] ?? ''); ?>"
                               value="<?php echo htmlspecialchars($_GET[$filter['name']] ?? ''); ?>">
                    
                    <?php elseif ($filter['type'] === 'select'): ?>
                        <select name="<?php echo $filter['name']; ?>" class="unified-filter-select">
                            <option value="">Todos</option>
                            <?php foreach ($filter['options'] as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"
                                        <?php echo ($_GET[$filter['name']] ?? '') === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    
                    <?php elseif ($filter['type'] === 'date-range'): ?>
                        <div class="unified-date-range">
                            <input type="date" 
                                   name="<?php echo $filter['name']; ?>_inicio" 
                                   class="unified-filter-input"
                                   value="<?php echo htmlspecialchars($_GET[$filter['name'] . '_inicio'] ?? ''); ?>">
                            <span class="unified-date-separator">até</span>
                            <input type="date" 
                                   name="<?php echo $filter['name']; ?>_fim" 
                                   class="unified-filter-input"
                                   value="<?php echo htmlspecialchars($_GET[$filter['name'] . '_fim'] ?? ''); ?>">
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <!-- Ações dos Filtros -->
                <div class="unified-filter-group">
                    <label class="unified-filter-label">&nbsp;</label>
                    <div class="unified-filter-actions">
                        <button type="button" 
                                id="btn-filtrar-<?php echo $listType; ?>" 
                                class="unified-btn unified-btn-filter">
                            <i data-lucide="search"></i>
                            Filtrar
                        </button>
                        <button type="button" 
                                id="btn-limpar-<?php echo $listType; ?>" 
                                class="unified-btn unified-btn-clear">
                            <i data-lucide="x"></i>
                            Limpar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Área de Conteúdo -->
    <div class="unified-content-area">
        
        <!-- Visualização em Tabela -->
        <div class="unified-table-view">
            <div style="overflow-x: auto;">
                <table class="unified-table">
                    <thead>
                        <tr>
                            <?php 
                            // Headers específicos por tipo de lista
                            switch ($listType) {
                                case 'qualificacoes':
                                    echo '<th>NUP</th>
                                          <th>Área Demandante</th>
                                          <th>Responsável</th>
                                          <th>Modalidade</th>
                                          <th>Objeto</th>
                                          <th>Status</th>
                                          <th>Contratação PCA</th>
                                          <th>Valor Estimado</th>
                                          <th>Ações</th>';
                                    break;
                                case 'planejamento':
                                    echo '<th>DFD</th>
                                          <th>Número Contratação</th>
                                          <th>Título</th>
                                          <th>Situação</th>
                                          <th>Área Requisitante</th>
                                          <th>Valor Total</th>
                                          <th>Data Início</th>
                                          <th>Ações</th>';
                                    break;
                                case 'licitacoes':
                                    echo '<th>NUP</th>
                                          <th>Número da Contratação</th>
                                          <th>Modalidade</th>
                                          <th>Objeto</th>
                                          <th>Valor Homologado</th>
                                          <th>Situação</th>
                                          <th>Pregoeiro</th>
                                          <th>Data Abertura</th>
                                          <th>Andamentos</th>
                                          <th>Ações</th>';
                                    break;
                                case 'contratos':
                                    echo '<th>Número do Contrato</th>
                                          <th>NUP Origem</th>
                                          <th>Contratado</th>
                                          <th>Objeto</th>
                                          <th>Valor</th>
                                          <th>Status</th>
                                          <th>Data Assinatura</th>
                                          <th>Vigência</th>
                                          <th>Ações</th>';
                                    break;
                                default:
                                    echo '<th>Registro</th>
                                          <th>Descrição</th>
                                          <th>Status</th>
                                          <th>Ações</th>';
                                    break;
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Conteúdo será carregado via JavaScript -->
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="100%" class="unified-empty-state">
                                <i data-lucide="inbox"></i>
                                <h3>Nenhum registro encontrado</h3>
                                <p>Comece adicionando um novo registro ou ajuste os filtros.</p>
                                <?php if ($createPermission): ?>
                                <button onclick="abrirModalCriar<?php echo ucfirst($listType); ?>()" 
                                        class="unified-btn unified-btn-primary" style="margin-top: 20px;">
                                    <i data-lucide="plus-circle"></i> 
                                    <?php 
                                    switch ($listType) {
                                        case 'qualificacoes': echo 'Criar Primeira Qualificação'; break;
                                        case 'planejamento': echo 'Importar Primeiro PCA'; break;
                                        case 'licitacoes': echo 'Criar Primeira Licitação'; break;
                                        case 'contratos': echo 'Criar Primeiro Contrato'; break;
                                        default: echo 'Criar Primeiro Registro'; break;
                                    }
                                    ?>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Visualização em Cards -->
        <div class="unified-cards-view" style="display: none;">
            <div class="unified-cards-grid">
                <!-- Conteúdo será carregado via JavaScript -->
                <?php if (empty($items)): ?>
                <div class="unified-empty-state" style="grid-column: 1 / -1;">
                    <i data-lucide="inbox"></i>
                    <h3>Nenhum registro encontrado</h3>
                    <p>Comece adicionando um novo registro ou ajuste os filtros.</p>
                    <?php if ($createPermission): ?>
                    <button onclick="abrirModalCriar<?php echo ucfirst($listType); ?>()" 
                            class="unified-btn unified-btn-primary" style="margin-top: 20px;">
                        <i data-lucide="plus-circle"></i> 
                        <?php 
                        switch ($listType) {
                            case 'qualificacoes': echo 'Criar Primeira Qualificação'; break;
                            case 'planejamento': echo 'Importar Primeiro PCA'; break;
                            case 'licitacoes': echo 'Criar Primeira Licitação'; break;
                            case 'contratos': echo 'Criar Primeiro Contrato'; break;
                            default: echo 'Criar Primeiro Registro'; break;
                        }
                        ?>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Paginação -->
    <?php if ($totalItems > 0): ?>
    <div class="unified-pagination-section">
        <div class="unified-pagination-container">
            
            <!-- Informações -->
            <div class="unified-pagination-info">
                <div class="unified-pagination-summary">
                    Mostrando <?php echo (($currentPage - 1) * $itemsPerPage) + 1; ?> a 
                    <?php echo min($currentPage * $itemsPerPage, $totalItems); ?> de 
                    <?php echo number_format($totalItems); ?> registros
                </div>
                
                <div class="unified-items-per-page">
                    <label for="items-per-page-<?php echo $listType; ?>">Itens por página:</label>
                    <select id="items-per-page-<?php echo $listType; ?>">
                        <option value="10" <?php echo $itemsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $itemsPerPage == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $itemsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $itemsPerPage == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
            
            <!-- Navegação -->
            <div class="unified-pagination-nav">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript Unificado -->
<script src="assets/unified-lists.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando sistema unificado para: <?php echo $listType; ?>');
    
    // Configurações específicas por tipo de lista
    const listOptions = {
        itemsPerPage: <?php echo $itemsPerPage; ?>,
        defaultView: 'lista',
        enableFilters: <?php echo !empty($filters) ? 'true' : 'false'; ?>,
        enablePagination: true,
        enableExport: <?php echo $exportPermission ? 'true' : 'false'; ?>
    };
    
    // Inicializar o sistema
    window.unifiedListManager = initUnifiedList('<?php echo $listType; ?>', listOptions);
    
    // Implementar métodos específicos do tipo de lista
    <?php if ($listType === 'qualificacoes'): ?>
    window.unifiedListManager.renderTableRow = function(item) {
        return `
            <tr>
                <td><strong style="color: #3498db;">${item.nup}</strong></td>
                <td>${item.area_demandante}</td>
                <td>${item.responsavel}</td>
                <td><span class="unified-modalidade-badge badge-${item.modalidade.toLowerCase()}">${item.modalidade}</span></td>
                <td title="${item.objeto}">${item.objeto.length > 80 ? item.objeto.substring(0, 80) + '...' : item.objeto}</td>
                <td><span class="unified-status-badge status-${item.status.toLowerCase().replace(' ', '-')}">${item.status}</span></td>
                <td>${item.numero_contratacao ? `<span style="color: #27ae60;"><i data-lucide="link"></i> ${item.numero_contratacao}</span>` : '<span style="color: #95a5a6;">Não vinculado</span>'}</td>
                <td style="color: #27ae60; font-weight: 600;">${formatMoney(item.valor_estimado)}</td>
                <td>
                    <button onclick="visualizarQualificacao(${item.id})" class="unified-card-btn unified-card-btn-view" title="Visualizar">
                        <i data-lucide="eye"></i>
                    </button>
                    <button onclick="editarQualificacao(${item.id})" class="unified-card-btn unified-card-btn-edit" title="Editar">
                        <i data-lucide="edit"></i>
                    </button>
                    <button onclick="excluirQualificacao(${item.id})" class="unified-card-btn unified-card-btn-delete" title="Excluir">
                        <i data-lucide="trash-2"></i>
                    </button>
                </td>
            </tr>
        `;
    };
    
    window.unifiedListManager.renderCard = function(item) {
        return `
            <div class="unified-card">
                <div class="unified-card-header">
                    <div class="unified-card-id">${item.nup}</div>
                    <div class="unified-card-status">
                        <span class="unified-status-badge status-${item.status.toLowerCase().replace(' ', '-')}">${item.status}</span>
                    </div>
                </div>
                <div class="unified-card-body">
                    <h3 class="unified-card-title">${item.objeto}</h3>
                    <div class="unified-card-details">
                        <div class="unified-card-detail-item">
                            <span class="unified-card-detail-label">Área Demandante</span>
                            <span class="unified-card-detail-value">${item.area_demandante}</span>
                        </div>
                        <div class="unified-card-detail-item">
                            <span class="unified-card-detail-label">Responsável</span>
                            <span class="unified-card-detail-value">${item.responsavel}</span>
                        </div>
                        <div class="unified-card-detail-item">
                            <span class="unified-card-detail-label">Modalidade</span>
                            <span class="unified-card-detail-value">
                                <span class="unified-modalidade-badge badge-${item.modalidade.toLowerCase()}">${item.modalidade}</span>
                            </span>
                        </div>
                        <div class="unified-card-detail-item">
                            <span class="unified-card-detail-label">Valor Estimado</span>
                            <span class="unified-card-detail-value valor">${formatMoney(item.valor_estimado)}</span>
                        </div>
                        ${item.numero_contratacao ? `
                        <div class="unified-card-detail-item">
                            <span class="unified-card-detail-label">Contratação PCA</span>
                            <span class="unified-card-detail-value" style="color: #27ae60;">
                                <i data-lucide="link"></i> ${item.numero_contratacao}
                            </span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="unified-card-actions">
                    <button onclick="visualizarQualificacao(${item.id})" class="unified-card-btn unified-card-btn-view">
                        <i data-lucide="eye"></i> Detalhes
                    </button>
                    <button onclick="editarQualificacao(${item.id})" class="unified-card-btn unified-card-btn-edit">
                        <i data-lucide="edit"></i> Editar
                    </button>
                    <button onclick="excluirQualificacao(${item.id})" class="unified-card-btn unified-card-btn-delete">
                        <i data-lucide="trash-2"></i> Excluir
                    </button>
                </div>
            </div>
        `;
    };
    <?php endif; ?>
    
    console.log('✅ Sistema unificado inicializado com sucesso!');
});

// Função auxiliar para formatação monetária
function formatMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value || 0);
}
</script>