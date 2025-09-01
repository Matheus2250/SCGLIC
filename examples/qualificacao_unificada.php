<?php
/**
 * EXEMPLO DE IMPLEMENTAÇÃO - QUALIFICAÇÕES COM SISTEMA UNIFICADO
 * Este arquivo demonstra como aplicar o padrão unificado na página de qualificações
 */

require_once '../config.php';
require_once '../functions.php';

verificarLogin();

// Configurações da lista
$listType = 'qualificacoes';
$listTitle = 'Qualificações Cadastradas';
$listIcon = 'award';
$createPermission = temPermissao('criar_qualificacao');
$exportPermission = temPermissao('exportar_qualificacao');

// Paginação
$itemsPerPage = $_GET['por_pagina'] ?? 10;
$currentPage = $_GET['pagina'] ?? 1;

// Buscar dados
$pdo = conectarDB();

// Aplicar filtros
$where = [];
$params = [];

if (!empty($_GET['busca'])) {
    $where[] = "(nup LIKE ? OR responsavel LIKE ? OR objeto LIKE ?)";
    $busca = "%{$_GET['busca']}%";
    $params[] = $busca;
    $params[] = $busca;
    $params[] = $busca;
}

if (!empty($_GET['status'])) {
    $where[] = "status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['modalidade'])) {
    $where[] = "modalidade = ?";
    $params[] = $_GET['modalidade'];
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Contar total
$sqlCount = "SELECT COUNT(*) as total FROM qualificacoes $whereClause";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalItems = $stmtCount->fetch()['total'];

// Buscar itens
$offset = ($currentPage - 1) * $itemsPerPage;
$sql = "SELECT * FROM qualificacoes $whereClause ORDER BY criado_em DESC LIMIT $itemsPerPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Configurar filtros
$filters = [
    [
        'type' => 'text',
        'name' => 'busca',
        'label' => '🔍 Buscar',
        'placeholder' => 'NUP, responsável, palavra-chave ou objeto...'
    ],
    [
        'type' => 'select',
        'name' => 'status',
        'label' => '✅ Status',
        'options' => [
            'EM ANÁLISE' => 'Em Análise',
            'CONCLUÍDO' => 'Concluído'
        ]
    ],
    [
        'type' => 'select',
        'name' => 'modalidade',
        'label' => '⚖️ Modalidade',
        'options' => [
            'PREGÃO' => 'Pregão',
            'CONCURSO' => 'Concurso',
            'CONCORRÊNCIA' => 'Concorrência',
            'INEXIGIBILIDADE' => 'Inexigibilidade',
            'DISPENSA' => 'Dispensa',
            'PREGÃO SRP' => 'Pregão SRP',
            'ADESÃO' => 'Adesão'
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qualificações - Sistema CGLIC</title>
    
    <!-- CSS Base do Sistema -->
    <link rel="stylesheet" href="../assets/style.css">
    
    <!-- CSS Unificado para Listas -->
    <link rel="stylesheet" href="../assets/unified-lists.css">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar (mantém o existente) -->
        <div class="sidebar">
            <!-- Conteúdo da sidebar existente -->
        </div>
        
        <!-- Main Content -->
        <main class="main-content">
            
            <!-- IMPLEMENTAÇÃO DO TEMPLATE UNIFICADO -->
            <div class="unified-list-container">
                
                <!-- HEADER UNIFICADO -->
                <div class="unified-list-header">
                    <div class="unified-header-left">
                        <h2 class="unified-list-title">
                            <i data-lucide="award"></i>
                            Qualificações Cadastradas
                        </h2>
                        <span class="unified-total-badge">
                            Total: <strong><?php echo number_format($totalItems); ?> qualificações</strong>
                        </span>
                    </div>
                    
                    <div class="unified-header-actions">
                        <!-- Toggle Vista -->
                        <div class="unified-view-toggle">
                            <button class="unified-toggle-btn active" onclick="unifiedList.setView('list')" data-view="list">
                                <i data-lucide="list"></i>
                                <span>Lista</span>
                            </button>
                            <button class="unified-toggle-btn" onclick="unifiedList.setView('cards')" data-view="cards">
                                <i data-lucide="grid-3x3"></i>
                                <span>Cards</span>
                            </button>
                        </div>
                        
                        <!-- Botão Nova Qualificação -->
                        <button class="unified-btn unified-btn-warning" onclick="abrirModalCriarQualificacao()">
                            <i data-lucide="plus-circle"></i>
                            <span>Nova Qualificação</span>
                        </button>
                        
                        <!-- Botão Exportar -->
                        <button class="unified-btn unified-btn-success" onclick="exportarQualificacoes()">
                            <i data-lucide="download"></i>
                            <span>Exportar</span>
                        </button>
                    </div>
                </div>
                
                <!-- FILTROS UNIFICADOS -->
                <div class="unified-filters-section">
                    <form method="GET" class="unified-filters-form">
                        <div class="unified-filters-main">
                            
                            <!-- Campo de Busca -->
                            <div class="unified-filter-group unified-filter-search">
                                <label class="unified-filter-label">
                                    <i data-lucide="search"></i>
                                    Buscar
                                </label>
                                <input type="text" 
                                       name="busca" 
                                       class="unified-filter-input"
                                       placeholder="NUP, responsável, palavra-chave ou objeto..."
                                       value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>">
                            </div>
                            
                            <!-- Filtro Status -->
                            <div class="unified-filter-group">
                                <label class="unified-filter-label">
                                    <i data-lucide="check-circle"></i>
                                    Status
                                </label>
                                <select name="status" class="unified-filter-select">
                                    <option value="">Todos os status</option>
                                    <option value="EM ANÁLISE" <?php echo ($_GET['status'] ?? '') === 'EM ANÁLISE' ? 'selected' : ''; ?>>Em Análise</option>
                                    <option value="CONCLUÍDO" <?php echo ($_GET['status'] ?? '') === 'CONCLUÍDO' ? 'selected' : ''; ?>>Concluído</option>
                                </select>
                            </div>
                            
                            <!-- Filtro Modalidade -->
                            <div class="unified-filter-group">
                                <label class="unified-filter-label">
                                    <i data-lucide="gavel"></i>
                                    Modalidade
                                </label>
                                <select name="modalidade" class="unified-filter-select">
                                    <option value="">Todas as modalidades</option>
                                    <option value="PREGÃO" <?php echo ($_GET['modalidade'] ?? '') === 'PREGÃO' ? 'selected' : ''; ?>>Pregão</option>
                                    <option value="CONCURSO" <?php echo ($_GET['modalidade'] ?? '') === 'CONCURSO' ? 'selected' : ''; ?>>Concurso</option>
                                    <option value="CONCORRÊNCIA" <?php echo ($_GET['modalidade'] ?? '') === 'CONCORRÊNCIA' ? 'selected' : ''; ?>>Concorrência</option>
                                    <option value="INEXIGIBILIDADE" <?php echo ($_GET['modalidade'] ?? '') === 'INEXIGIBILIDADE' ? 'selected' : ''; ?>>Inexigibilidade</option>
                                    <option value="DISPENSA" <?php echo ($_GET['modalidade'] ?? '') === 'DISPENSA' ? 'selected' : ''; ?>>Dispensa</option>
                                    <option value="PREGÃO SRP" <?php echo ($_GET['modalidade'] ?? '') === 'PREGÃO SRP' ? 'selected' : ''; ?>>Pregão SRP</option>
                                    <option value="ADESÃO" <?php echo ($_GET['modalidade'] ?? '') === 'ADESÃO' ? 'selected' : ''; ?>>Adesão</option>
                                </select>
                            </div>
                            
                            <!-- Itens por Página -->
                            <div class="unified-filter-group unified-filter-perpage">
                                <label class="unified-filter-label">
                                    <i data-lucide="list"></i>
                                    Por página
                                </label>
                                <select name="por_pagina" class="unified-filter-select" onchange="this.form.submit()">
                                    <option value="10" <?php echo $itemsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="20" <?php echo $itemsPerPage == 20 ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo $itemsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $itemsPerPage == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                            
                            <!-- Botões de Ação -->
                            <div class="unified-filter-actions">
                                <button type="submit" class="unified-filter-btn unified-filter-btn-primary">
                                    <i data-lucide="search"></i>
                                    Filtrar
                                </button>
                                <a href="?" class="unified-filter-btn unified-filter-btn-clear">
                                    <i data-lucide="x"></i>
                                    Limpar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- CONTEÚDO -->
                <div class="unified-list-content">
                    
                    <!-- Vista em Tabela -->
                    <div class="unified-table-view">
                        <table class="unified-table">
                            <thead>
                                <tr>
                                    <th>NUP</th>
                                    <th>Área Demandante</th>
                                    <th>Responsável</th>
                                    <th>Modalidade</th>
                                    <th>Objeto</th>
                                    <th>Status</th>
                                    <th>Contratação PCA</th>
                                    <th>Valor Estimado</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><strong class="unified-text-primary"><?php echo htmlspecialchars($item['nup']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['area_demandante']); ?></td>
                                    <td><?php echo htmlspecialchars($item['responsavel']); ?></td>
                                    <td>
                                        <span class="unified-badge unified-badge-info">
                                            <?php echo htmlspecialchars($item['modalidade']); ?>
                                        </span>
                                    </td>
                                    <td class="unified-text-truncate" title="<?php echo htmlspecialchars($item['objeto']); ?>">
                                        <?php echo htmlspecialchars(substr($item['objeto'], 0, 80)); ?>...
                                    </td>
                                    <td>
                                        <span class="unified-badge <?php echo $item['status'] === 'CONCLUÍDO' ? 'unified-badge-success' : 'unified-badge-warning'; ?>">
                                            <?php echo htmlspecialchars($item['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['numero_contratacao'])): ?>
                                            <span class="unified-text-success">
                                                <i data-lucide="link" style="width: 14px; height: 14px;"></i>
                                                <?php echo htmlspecialchars($item['numero_contratacao']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="unified-text-muted">Não vinculado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="unified-text-success">
                                        <strong><?php echo formatarMoeda($item['valor_estimado']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="unified-table-actions">
                                            <button class="unified-action-btn unified-action-view" title="Visualizar">
                                                <i data-lucide="eye"></i>
                                            </button>
                                            <button class="unified-action-btn unified-action-edit" title="Editar">
                                                <i data-lucide="edit"></i>
                                            </button>
                                            <button class="unified-action-btn unified-action-delete" title="Excluir">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Vista em Cards (será preenchida via JS) -->
                    <div class="unified-cards-view" style="display: none;">
                        <div class="unified-cards-grid">
                            <!-- Cards serão gerados via JavaScript -->
                        </div>
                    </div>
                </div>
                
                <!-- PAGINAÇÃO UNIFICADA -->
                <?php 
                $totalPages = ceil($totalItems / $itemsPerPage);
                $startItem = ($currentPage - 1) * $itemsPerPage + 1;
                $endItem = min($currentPage * $itemsPerPage, $totalItems);
                ?>
                
                <div class="unified-pagination-container">
                    <div class="unified-pagination-info">
                        Mostrando <strong><?php echo $startItem; ?></strong> a <strong><?php echo $endItem; ?></strong> 
                        de <strong><?php echo number_format($totalItems); ?></strong> registros
                    </div>
                    
                    <div class="unified-pagination">
                        <?php if ($currentPage > 1): ?>
                        <a href="?pagina=1" class="unified-page-btn">
                            <i data-lucide="chevrons-left"></i>
                        </a>
                        <a href="?pagina=<?php echo $currentPage - 1; ?>" class="unified-page-btn">
                            <i data-lucide="chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                        <a href="?pagina=<?php echo $i; ?>" class="unified-page-btn <?php echo $i == $currentPage ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                        <a href="?pagina=<?php echo $currentPage + 1; ?>" class="unified-page-btn">
                            <i data-lucide="chevron-right"></i>
                        </a>
                        <a href="?pagina=<?php echo $totalPages; ?>" class="unified-page-btn">
                            <i data-lucide="chevrons-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </main>
    </div>
    
    <!-- Scripts -->
    <script src="../assets/unified-lists.js"></script>
    <script>
    // Inicializar ícones Lucide
    lucide.createIcons();
    
    // Sistema de toggle
    const unifiedList = {
        currentView: 'list',
        
        setView: function(view) {
            const tableView = document.querySelector('.unified-table-view');
            const cardsView = document.querySelector('.unified-cards-view');
            const btnList = document.querySelector('[data-view="list"]');
            const btnCards = document.querySelector('[data-view="cards"]');
            
            if (view === 'cards') {
                tableView.style.display = 'none';
                cardsView.style.display = 'block';
                btnList.classList.remove('active');
                btnCards.classList.add('active');
            } else {
                tableView.style.display = 'block';
                cardsView.style.display = 'none';
                btnList.classList.add('active');
                btnCards.classList.remove('active');
            }
            
            this.currentView = view;
            localStorage.setItem('preferredView', view);
            
            // Reinicializar ícones
            lucide.createIcons();
        }
    };
    
    // Restaurar preferência de visualização
    const savedView = localStorage.getItem('preferredView') || 'list';
    if (savedView === 'cards') {
        unifiedList.setView('cards');
    }
    </script>
</body>
</html>