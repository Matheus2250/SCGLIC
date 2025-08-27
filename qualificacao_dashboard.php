<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// ========================================
// SISTEMA DE PAGINAÇÃO E FILTROS
// ========================================

// Configuração de paginação
$qualificacoes_por_pagina = isset($_GET['por_pagina']) ? max(10, min(100, intval($_GET['por_pagina']))) : 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_atual - 1) * $qualificacoes_por_pagina;

// Processar filtros
$filtro_status = $_GET['status_filtro'] ?? '';
$filtro_modalidade = $_GET['modalidade_filtro'] ?? '';
$filtro_busca = $_GET['busca'] ?? '';

$where_conditions = ['1=1'];
$params = [];

if (!empty($filtro_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filtro_status;
}

if (!empty($filtro_modalidade)) {
    $where_conditions[] = "modalidade = ?";
    $params[] = $filtro_modalidade;
}

if (!empty($filtro_busca)) {
    $where_conditions[] = "(nup LIKE ? OR responsavel LIKE ? OR palavras_chave LIKE ? OR objeto LIKE ?)";
    $busca_param = "%$filtro_busca%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Buscar estatísticas das qualificações
try {
    // Verificar se a tabela existe
    $check_table = $pdo->query("SHOW TABLES LIKE 'qualificacoes'");
    if ($check_table->rowCount() == 0) {
        // Tabela não existe - usar dados zerados
        $stats = [
            'total_qualificacoes' => 0,
            'em_andamento' => 0,
            'concluidas' => 0,
            'valor_total' => 0.00
        ];
        $qualificacoes_recentes = [];
        $total_qualificacoes = 0;
    } else {
        // Buscar estatísticas gerais
        $stats_sql = "SELECT 
            COUNT(*) as total_qualificacoes,
            SUM(CASE WHEN status = 'EM ANÁLISE' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN status = 'CONCLUÍDO' THEN 1 ELSE 0 END) as concluidas,
            SUM(valor_estimado) as valor_total,
            AVG(valor_estimado) as valor_medio,
            MIN(valor_estimado) as menor_valor,
            MAX(valor_estimado) as maior_valor
            FROM qualificacoes";
        $stmt_stats = $pdo->query($stats_sql);
        $stats = $stmt_stats->fetch();
        
        // Garantir que os valores não sejam null
        $stats['total_qualificacoes'] = intval($stats['total_qualificacoes']);
        $stats['em_andamento'] = intval($stats['em_andamento']);
        $stats['concluidas'] = intval($stats['concluidas']);
        $stats['valor_total'] = floatval($stats['valor_total'] ?? 0.00);
        $stats['valor_medio'] = floatval($stats['valor_medio'] ?? 0.00);
        $stats['menor_valor'] = floatval($stats['menor_valor'] ?? 0.00);
        $stats['maior_valor'] = floatval($stats['maior_valor'] ?? 0.00);
        
        // Estatísticas por modalidade
        $modalidades_sql = "SELECT 
            modalidade,
            COUNT(*) as quantidade,
            SUM(valor_estimado) as valor_total_modalidade,
            AVG(valor_estimado) as valor_medio_modalidade,
            SUM(CASE WHEN status = 'CONCLUÍDO' THEN 1 ELSE 0 END) as concluidos_modalidade,
            ROUND((SUM(CASE WHEN status = 'CONCLUÍDO' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as taxa_conclusao
            FROM qualificacoes 
            GROUP BY modalidade 
            ORDER BY quantidade DESC";
        $stmt_modalidades = $pdo->query($modalidades_sql);
        $stats_modalidades = $stmt_modalidades->fetchAll();
        
        // Estatísticas por área demandante (top 5)
        $areas_sql = "SELECT 
            area_demandante,
            COUNT(*) as quantidade,
            SUM(valor_estimado) as valor_total_area,
            AVG(valor_estimado) as valor_medio_area,
            SUM(CASE WHEN status = 'CONCLUÍDO' THEN 1 ELSE 0 END) as concluidos_area,
            ROUND((SUM(CASE WHEN status = 'CONCLUÍDO' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as taxa_conclusao_area
            FROM qualificacoes 
            GROUP BY area_demandante 
            ORDER BY quantidade DESC 
            LIMIT 5";
        $stmt_areas = $pdo->query($areas_sql);
        $stats_areas = $stmt_areas->fetchAll();
        
        // Estatísticas temporais (últimos 6 meses)
        $temporais_sql = "SELECT 
            DATE_FORMAT(criado_em, '%Y-%m') as mes_ano,
            DATE_FORMAT(criado_em, '%M/%Y') as mes_formatado,
            COUNT(*) as quantidade_mes,
            SUM(valor_estimado) as valor_mes,
            SUM(CASE WHEN status = 'CONCLUÍDO' THEN 1 ELSE 0 END) as concluidos_mes,
            AVG(DATEDIFF(
                CASE WHEN status = 'CONCLUÍDO' THEN atualizado_em ELSE CURDATE() END, 
                criado_em
            )) as tempo_medio_dias
            FROM qualificacoes 
            WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(criado_em, '%Y-%m')
            ORDER BY mes_ano DESC";
        $stmt_temporais = $pdo->query($temporais_sql);
        $stats_temporais = $stmt_temporais->fetchAll();
        
        // Estatísticas de performance (tempo médio, eficiência)
        $performance_sql = "SELECT 
            COUNT(*) as total_processadas,
            AVG(DATEDIFF(atualizado_em, criado_em)) as tempo_medio_processamento,
            MIN(DATEDIFF(atualizado_em, criado_em)) as tempo_min_processamento,
            MAX(DATEDIFF(atualizado_em, criado_em)) as tempo_max_processamento,
            COUNT(CASE WHEN DATEDIFF(atualizado_em, criado_em) <= 30 THEN 1 END) as processadas_30_dias,
            COUNT(CASE WHEN DATEDIFF(atualizado_em, criado_em) <= 60 THEN 1 END) as processadas_60_dias
            FROM qualificacoes 
            WHERE status = 'CONCLUÍDO'";
        $stmt_performance = $pdo->query($performance_sql);
        $stats_performance = $stmt_performance->fetch();
        
        // Top responsáveis (mais ativos)
        $responsaveis_sql = "SELECT 
            responsavel,
            COUNT(*) as quantidade_responsavel,
            SUM(valor_estimado) as valor_total_responsavel,
            SUM(CASE WHEN status = 'CONCLUÍDO' THEN 1 ELSE 0 END) as concluidos_responsavel,
            ROUND((SUM(CASE WHEN status = 'CONCLUÍDO' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as taxa_conclusao_responsavel,
            AVG(CASE WHEN status = 'CONCLUÍDO' THEN DATEDIFF(atualizado_em, criado_em) END) as tempo_medio_responsavel
            FROM qualificacoes 
            GROUP BY responsavel 
            HAVING COUNT(*) >= 2
            ORDER BY quantidade_responsavel DESC 
            LIMIT 5";
        $stmt_responsaveis = $pdo->query($responsaveis_sql);
        $stats_responsaveis = $stmt_responsaveis->fetchAll();
        
        // Contar total com filtros
        $sql_count = "SELECT COUNT(*) as total FROM qualificacoes WHERE $where_clause";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($params);
        $total_qualificacoes = $stmt_count->fetch()['total'];
        
        // Buscar qualificações com paginação e filtros
        $qualificacoes_sql = "SELECT * FROM qualificacoes 
                             WHERE $where_clause 
                             ORDER BY criado_em DESC 
                             LIMIT $qualificacoes_por_pagina OFFSET $offset";
        $stmt_qualificacoes = $pdo->prepare($qualificacoes_sql);
        $stmt_qualificacoes->execute($params);
        $qualificacoes_recentes = $stmt_qualificacoes->fetchAll();
    }
} catch (Exception $e) {
    // Em caso de erro, usar dados zerados
    $stats = [
        'total_qualificacoes' => 0,
        'em_andamento' => 0,
        'concluidas' => 0,
        'valor_total' => 0.00,
        'valor_medio' => 0.00,
        'menor_valor' => 0.00,
        'maior_valor' => 0.00
    ];
    $qualificacoes_recentes = [];
    $total_qualificacoes = 0;
    $stats_modalidades = [];
    $stats_areas = [];
    $stats_temporais = [];
    $stats_performance = [];
    $stats_responsaveis = [];
}

// Calcular informações de paginação
$total_paginas = ceil($total_qualificacoes / $qualificacoes_por_pagina);
$inicio_item = ($pagina_atual - 1) * $qualificacoes_por_pagina + 1;
$fim_item = min($pagina_atual * $qualificacoes_por_pagina, $total_qualificacoes);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qualificação - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/qualificacao-dashboard.css">
    <link rel="stylesheet" href="assets/dark-mode.css">
    <link rel="stylesheet" href="assets/mobile-improvements.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <style>
        /* FORÇAR BARRA DE ROLAGEM NA SIDEBAR */
        .sidebar-nav {
            overflow-y: scroll !important;
            overflow-x: hidden !important;
            height: calc(100vh - 180px) !important;
            max-height: calc(100vh - 180px) !important;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 8px !important;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1) !important;
            border-radius: 4px !important;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.4) !important;
            border-radius: 4px !important;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.6) !important;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i data-lucide="menu"></i>
                </button>
                <h2><i data-lucide="award"></i> Qualificação</h2>
            </div>
            
            <nav class="sidebar-nav">
                <!-- Navegação Principal -->
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="javascript:void(0)" class="nav-item active" onclick="showSection('dashboard')">
                        <i data-lucide="chart-line"></i>
                        <span>Painel Principal</span>
                    </a>
                </div>
                
                <!-- Qualificações -->
                <div class="nav-section">
                    <div class="nav-section-title">Qualificações</div>
                    <a href="javascript:void(0)" class="nav-item" onclick="showSection('lista-qualificacoes')">
                        <i data-lucide="list"></i>
                        <span>Qualificações</span>
                    </a>
                </div>
                
                <!-- Relatórios -->
                <div class="nav-section">
                    <div class="nav-section-title">Relatórios</div>
                    <a href="javascript:void(0)" class="nav-item" onclick="showSection('relatorios')">
                        <i data-lucide="file-text"></i>
                        <span>Relatórios</span>
                    </a>
                    <a href="javascript:void(0)" class="nav-item" onclick="showSection('estatisticas')">
                        <i data-lucide="bar-chart-3"></i>
                        <span>Estatísticas</span>
                    </a>
                </div>
                
                <!-- Navegação Geral -->
                <div class="nav-section">
                    <div class="nav-section-title">Sistema</div>
                    <a href="selecao_modulos.php" class="nav-item">
                        <i data-lucide="home"></i>
                        <span>Menu Principal</span>
                    </a>
                    <a href="dashboard.php" class="nav-item">
                        <i data-lucide="clipboard-check"></i>
                        <span>Planejamento</span>
                    </a>
                    <a href="licitacao_dashboard.php" class="nav-item">
                        <i data-lucide="gavel"></i>
                        <span>Licitações</span>
                    </a>
                    <a href="contratos_dashboard.php" class="nav-item">
                        <i data-lucide="file-text"></i>
                        <span>Contratos</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['usuario_nome'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></h4>
                        <p><?php echo htmlspecialchars($_SESSION['usuario_email']); ?></p>
                    </div>
                </div>
                <a href="perfil_usuario.php" class="logout-btn" style="text-decoration: none; margin-bottom: 10px; background: #27ae60 !important;">
                    <i data-lucide="user"></i> <span>Meu Perfil</span>
                </a>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i data-lucide="log-out"></i>
                    <span>Sair</span>
                </button>
            </div>
        </div>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            
            <!-- Dashboard Principal -->
            <section id="dashboard" class="content-section active">
                <!-- Header -->
                <div class="dashboard-header">
                    <h1><i data-lucide="award"></i> Painel de Qualificações</h1>
                    <p>Gerencie qualificações de fornecedores, avalie capacitação técnica e controle documentação</p>
                </div>
                
                <!-- Cards de Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-number"><?php echo number_format($stats['total_qualificacoes']); ?></div>
                        <div class="stat-label">Total Qualificações</div>
                    </div>
                    <div class="stat-card andamento">
                        <div class="stat-number"><?php echo number_format($stats['em_andamento']); ?></div>
                        <div class="stat-label">Em Análise</div>
                    </div>
                    <div class="stat-card aprovados">
                        <div class="stat-number"><?php echo number_format($stats['concluidas']); ?></div>
                        <div class="stat-label">Concluídas</div>
                    </div>
                    <div class="stat-card valor">
                        <div class="stat-number"><?php echo abreviarValor($stats['valor_total']); ?></div>
                        <div class="stat-label">Valor Total</div>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-title">
                            <i data-lucide="bar-chart"></i>
                            Status das Qualificações
                        </div>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-title">
                            <i data-lucide="trending-up"></i>
                            Performance Mensal
                        </div>
                        <div class="chart-container">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Seção removida - será recriada como modal seguindo padrão de licitações -->
            
            <!-- Lista de Qualificações -->
            <section id="lista-qualificacoes" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="list"></i> Lista de Qualificações</h1>
                    <p>Visualize e gerencie todas as qualificações cadastradas</p>
                </div>
                
                <div class="table-container">
                    <!-- Controles e Filtros -->
                    <div class="table-controls">
                        <div class="table-info">
                            <h3>Qualificações Cadastradas</h3>
                            <p>Total: <?php echo number_format($total_qualificacoes); ?> qualificações</p>
                        </div>
                        
                        <!-- Toggle de Visualização -->
                        <div class="view-toggle">
                            <button onclick="toggleQualificacaoView('lista')" class="view-toggle-btn active" id="btn-lista-qualif">
                                <i data-lucide="list"></i> Lista
                            </button>
                            <button onclick="toggleQualificacaoView('cards')" class="view-toggle-btn" id="btn-cards-qualif">
                                <i data-lucide="grid-3x3"></i> Cards
                            </button>
                        </div>
                        
                        <div class="table-actions">
                            <button onclick="abrirModal('modalCriarQualificacao')" class="btn-primary">
                                <i data-lucide="plus-circle"></i> Nova Qualificação
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filtros de Busca -->
                    <div class="filter-container">
                        <form method="GET" class="filter-form" id="filtroQualificacoes">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="busca">
                                        <i data-lucide="search"></i> Buscar:
                                    </label>
                                    <input type="text" id="busca" name="busca" 
                                           value="<?php echo htmlspecialchars($filtro_busca); ?>"
                                           placeholder="NUP, responsável, palavra-chave ou objeto..."
                                           class="filter-input">
                                </div>
                                
                                <div class="filter-group">
                                    <label for="status_filtro">
                                        <i data-lucide="check-circle"></i> Status:
                                    </label>
                                    <select id="status_filtro" name="status_filtro" class="filter-select">
                                        <option value="">Todos os status</option>
                                        <option value="EM ANÁLISE" <?php echo $filtro_status === 'EM ANÁLISE' ? 'selected' : ''; ?>>EM ANÁLISE</option>
                                        <option value="CONCLUÍDO" <?php echo $filtro_status === 'CONCLUÍDO' ? 'selected' : ''; ?>>CONCLUÍDO</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="modalidade_filtro">
                                        <i data-lucide="gavel"></i> Modalidade:
                                    </label>
                                    <select id="modalidade_filtro" name="modalidade_filtro" class="filter-select">
                                        <option value="">Todas as modalidades</option>
                                        <option value="PREGÃO" <?php echo $filtro_modalidade === 'PREGÃO' ? 'selected' : ''; ?>>PREGÃO</option>
                                        <option value="CONCURSO" <?php echo $filtro_modalidade === 'CONCURSO' ? 'selected' : ''; ?>>CONCURSO</option>
                                        <option value="CONCORRÊNCIA" <?php echo $filtro_modalidade === 'CONCORRÊNCIA' ? 'selected' : ''; ?>>CONCORRÊNCIA</option>
                                        <option value="INEXIGIBILIDADE" <?php echo $filtro_modalidade === 'INEXIGIBILIDADE' ? 'selected' : ''; ?>>INEXIGIBILIDADE</option>
                                        <option value="DISPENSA" <?php echo $filtro_modalidade === 'DISPENSA' ? 'selected' : ''; ?>>DISPENSA</option>
                                        <option value="PREGÃO SRP" <?php echo $filtro_modalidade === 'PREGÃO SRP' ? 'selected' : ''; ?>>PREGÃO SRP</option>
                                        <option value="ADESÃO" <?php echo $filtro_modalidade === 'ADESÃO' ? 'selected' : ''; ?>>ADESÃO</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="por_pagina">
                                        <i data-lucide="list"></i> Por página:
                                    </label>
                                    <select id="por_pagina" name="por_pagina" class="filter-select">
                                        <option value="10" <?php echo $qualificacoes_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="20" <?php echo $qualificacoes_por_pagina == 20 ? 'selected' : ''; ?>>20</option>
                                        <option value="50" <?php echo $qualificacoes_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $qualificacoes_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="btn-primary">
                                    <i data-lucide="search"></i> Filtrar
                                </button>
                                <a href="?" class="btn-secondary">
                                    <i data-lucide="x"></i> Limpar
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Visualização em Tabela -->
                    <div class="table-qualificacoes-view">
                        <table>
                        <thead>
                            <tr>
                                <th>NUP</th>
                                <th>Área Demandante</th>
                                <th>Responsável</th>
                                <th>Modalidade</th>
                                <th>Objeto</th>
                                <th>Status</th>
                                <th>Valor Estimado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($qualificacoes_recentes)): ?>
                                <?php foreach ($qualificacoes_recentes as $qualificacao): ?>
                                <tr>
                                    <td><strong class="nup-azul"><?php echo htmlspecialchars($qualificacao['nup']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($qualificacao['area_demandante']); ?></td>
                                    <td><?php echo htmlspecialchars($qualificacao['responsavel']); ?></td>
                                    <td>
                                        <?php
                                        $modalidade_class = '';
                                        switch($qualificacao['modalidade']) {
                                            case 'PREGÃO': $modalidade_class = 'badge-pregao'; break;
                                            case 'CONCURSO': $modalidade_class = 'badge-concurso'; break;
                                            case 'CONCORRÊNCIA': $modalidade_class = 'badge-concorrencia'; break;
                                            case 'INEXIGIBILIDADE': $modalidade_class = 'badge-inexigibilidade'; break;
                                            case 'DISPENSA': $modalidade_class = 'badge-dispensa'; break;
                                            case 'PREGÃO SRP': $modalidade_class = 'badge-pregao-srp'; break;
                                            case 'ADESÃO': $modalidade_class = 'badge-adesao'; break;
                                            default: $modalidade_class = 'badge-default';
                                        }
                                        ?>
                                        <span class="modalidade-badge <?php echo $modalidade_class; ?>">
                                            <?php echo htmlspecialchars($qualificacao['modalidade']); ?>
                                        </span>
                                    </td>
                                    <td class="titulo-cell"><?php echo htmlspecialchars(substr($qualificacao['objeto'], 0, 80) . (strlen($qualificacao['objeto']) > 80 ? '...' : '')); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch($qualificacao['status']) {
                                            case 'CONCLUÍDO': $status_class = 'status-aprovado'; break;
                                            case 'EM ANÁLISE': $status_class = 'status-em-andamento'; break;
                                            default: $status_class = 'status-pendente';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($qualificacao['status']); ?>
                                        </span>
                                    </td>
                                    <td><span class="valor-verde"><?php echo formatarMoeda($qualificacao['valor_estimado']); ?></span></td>
                                    <td class="table-actions">
                                        <?php if (empty($qualificacao['numero_dfd']) && empty($qualificacao['numero_contratacao'])): ?>
                                            <button onclick="abrirVinculacaoPCA(<?php echo $qualificacao['id']; ?>)" title="Vincular com o PCA" style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border: none; padding: 6px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px; font-size: 11px; font-weight: 600;">
                                                <i data-lucide="link" style="width: 12px; height: 12px;"></i> PCA
                                            </button>
                                        <?php else: ?>
                                            <span title="Vinculado ao PCA" style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; padding: 4px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; margin-right: 4px; display: inline-flex; align-items: center; gap: 3px;">
                                                <i data-lucide="check" style="width: 10px; height: 10px;"></i> PCA
                                            </span>
                                        <?php endif; ?>
                                        
                                        <button onclick="visualizarQualificacao(<?php echo $qualificacao['id']; ?>)" title="Ver Detalhes" style="background: #6c757d; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; margin-right: 4px;">
                                            <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                        </button>
                                        <button onclick="editarQualificacao(<?php echo $qualificacao['id']; ?>)" title="Editar" style="background: #f39c12; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; margin-right: 4px;">
                                            <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                                        </button>
                                        <button onclick="excluirQualificacao(<?php echo $qualificacao['id']; ?>)" title="Excluir" style="background: #e74c3c; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
                                            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #6c757d; font-style: italic;">
                                        <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 15px; opacity: 0.5;"></i><br>
                                        Nenhuma qualificação cadastrada ainda.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Paginação -->
                    <?php if ($total_qualificacoes > 0): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            <span>Mostrando <?php echo number_format($inicio_item); ?> a <?php echo number_format($fim_item); ?> de <?php echo number_format($total_qualificacoes); ?> qualificações</span>
                        </div>
                        
                        <?php if ($total_paginas > 1): ?>
                        <div class="pagination">
                            <?php
                            $query_params = $_GET;
                            $base_url = '?' . http_build_query(array_merge($query_params, ['pagina' => '']));
                            $base_url = rtrim($base_url, '=');
                            ?>
                            
                            <!-- Primeira página -->
                            <?php if ($pagina_atual > 1): ?>
                                <a href="<?php echo $base_url; ?>=1" class="pagination-btn">
                                    <i data-lucide="chevrons-left"></i>
                                </a>
                                <a href="<?php echo $base_url; ?>=<?php echo $pagina_atual - 1; ?>" class="pagination-btn">
                                    <i data-lucide="chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <!-- Páginas numeradas -->
                            <?php
                            $inicio_pag = max(1, $pagina_atual - 2);
                            $fim_pag = min($total_paginas, $pagina_atual + 2);
                            
                            for ($i = $inicio_pag; $i <= $fim_pag; $i++):
                            ?>
                                <a href="<?php echo $base_url; ?>=<?php echo $i; ?>" 
                                   class="pagination-btn <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <!-- Última página -->
                            <?php if ($pagina_atual < $total_paginas): ?>
                                <a href="<?php echo $base_url; ?>=<?php echo $pagina_atual + 1; ?>" class="pagination-btn">
                                    <i data-lucide="chevron-right"></i>
                                </a>
                                <a href="<?php echo $base_url; ?>=<?php echo $total_paginas; ?>" class="pagination-btn">
                                    <i data-lucide="chevrons-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    </div>
                    <!-- Fim da Visualização em Tabela -->
                    
                    <!-- Visualização em Cards -->
                    <div class="cards-qualificacoes-view" style="display: none;">
                        <?php if (!empty($qualificacoes_recentes)): ?>
                            <div class="qualificacoes-grid">
                                <?php foreach ($qualificacoes_recentes as $qualificacao): ?>
                                    <?php
                                    $status_class = '';
                                    switch($qualificacao['status']) {
                                        case 'EM ANÁLISE': $status_class = 'status-analise'; break;
                                        case 'CONCLUÍDO': $status_class = 'status-concluido'; break;
                                        default: $status_class = 'status-pendente'; break;
                                    }
                                    ?>
                                    <div class="qualificacao-card">
                                        <!-- Header do Card -->
                                        <div class="card-header">
                                            <div class="card-id">
                                                <strong><?php echo htmlspecialchars($qualificacao['nup']); ?></strong>
                                            </div>
                                            <div class="card-status">
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo htmlspecialchars($qualificacao['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Body do Card -->
                                        <div class="card-body">
                                            <h3 class="card-title"><?php echo htmlspecialchars($qualificacao['objeto']); ?></h3>
                                            
                                            <div class="card-details">
                                                <div class="card-detail-item">
                                                    <span class="card-detail-label">Área Demandante</span>
                                                    <span class="card-detail-value"><?php echo htmlspecialchars($qualificacao['area_demandante']); ?></span>
                                                </div>
                                                
                                                <div class="card-detail-item">
                                                    <span class="card-detail-label">Responsável</span>
                                                    <span class="card-detail-value"><?php echo htmlspecialchars($qualificacao['responsavel']); ?></span>
                                                </div>
                                                
                                                <div class="card-detail-item">
                                                    <span class="card-detail-label">Modalidade</span>
                                                    <span class="card-detail-value modalidade-badge badge-<?php echo strtolower($qualificacao['modalidade']); ?>">
                                                        <?php echo htmlspecialchars($qualificacao['modalidade']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="card-detail-item">
                                                    <span class="card-detail-label">Valor Estimado</span>
                                                    <span class="card-detail-value valor"><?php echo formatarMoeda($qualificacao['valor_estimado']); ?></span>
                                                </div>
                                                
                                                <?php if (!empty($qualificacao['numero_dfd']) || !empty($qualificacao['numero_contratacao'])): ?>
                                                <div class="vinculacao-pca">
                                                    <i data-lucide="link"></i>
                                                    <span>Vinculado ao PCA</span>
                                                    <?php if (!empty($qualificacao['numero_dfd'])): ?>
                                                        <small>DFD: <?php echo htmlspecialchars($qualificacao['numero_dfd']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Footer com Ações -->
                                        <div class="card-actions">
                                            <!-- Botão Vincular (se disponível) -->
                                            <?php if (empty($qualificacao['numero_dfd']) && empty($qualificacao['numero_contratacao'])): ?>
                                                <div class="vincular-container">
                                                    <button onclick="abrirVinculacaoPCA(<?php echo $qualificacao['id']; ?>)" 
                                                            class="btn-card btn-vincular" title="Vincular com o PCA">
                                                        <i data-lucide="link"></i> Vincular com o PCA
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Botões de Ação Secundários -->
                                            <div class="actions-secondary">
                                                <button onclick="visualizarQualificacao(<?php echo $qualificacao['id']; ?>)" 
                                                        class="btn-card btn-card-detalhes" title="Ver detalhes">
                                                    <i data-lucide="eye"></i> Detalhes
                                                </button>
                                                
                                                <button onclick="editarQualificacao(<?php echo $qualificacao['id']; ?>)"
                                                        class="btn-card btn-card-editar" title="Editar">
                                                    <i data-lucide="edit"></i> Editar
                                                </button>
                                                
                                                <button onclick="excluirQualificacao(<?php echo $qualificacao['id']; ?>)"
                                                        class="btn-card btn-card-excluir" title="Excluir">
                                                    <i data-lucide="trash-2"></i> Excluir
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="cards-empty">
                                <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                <p>Nenhuma qualificação cadastrada ainda.</p>
                                <p><small>Total de qualificações: <?php echo count($qualificacoes_recentes ?? []); ?></small></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Paginação para Cards (igual à da tabela) -->
                        <?php if ($total_qualificacoes > 0 && $total_paginas > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                <span>Mostrando <?php echo number_format($inicio_item); ?> a <?php echo number_format($fim_item); ?> de <?php echo number_format($total_qualificacoes); ?> qualificações</span>
                            </div>
                            
                            <div class="pagination">
                                <?php
                                $query_params = $_GET;
                                $base_url = '?' . http_build_query(array_merge($query_params, ['pagina' => '']));
                                $base_url = rtrim($base_url, '=');
                                ?>
                                
                                <!-- Primeira página -->
                                <?php if ($pagina_atual > 1): ?>
                                    <a href="<?php echo $base_url; ?>=1" class="pagination-btn">
                                        <i data-lucide="chevrons-left"></i>
                                    </a>
                                    <a href="<?php echo $base_url; ?>=<?php echo $pagina_atual - 1; ?>" class="pagination-btn">
                                        <i data-lucide="chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Páginas numeradas -->
                                <?php
                                $inicio_pag = max(1, $pagina_atual - 2);
                                $fim_pag = min($total_paginas, $pagina_atual + 2);
                                
                                for ($i = $inicio_pag; $i <= $fim_pag; $i++):
                                ?>
                                    <a href="<?php echo $base_url; ?>=<?php echo $i; ?>" 
                                       class="pagination-btn <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <!-- Última página -->
                                <?php if ($pagina_atual < $total_paginas): ?>
                                    <a href="<?php echo $base_url; ?>=<?php echo $pagina_atual + 1; ?>" class="pagination-btn">
                                        <i data-lucide="chevron-right"></i>
                                    </a>
                                    <a href="<?php echo $base_url; ?>=<?php echo $total_paginas; ?>" class="pagination-btn">
                                        <i data-lucide="chevrons-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- Fim da Visualização em Cards -->
                    
                </div>
            </section>
            
            
            <!-- Relatórios -->
            <section id="relatorios" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="file-text"></i> Relatórios de Qualificações</h1>
                    <p>Relatórios detalhados sobre o processo de qualificação</p>
                </div>

                <div class="stats-grid">
                    <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorioQualificacao('status')">
                        <h3 class="chart-title"><i data-lucide="check-circle"></i> Relatório por Status</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Análise detalhada das qualificações por status de aprovação</p>
                        <div style="text-align: center;">
                            <i data-lucide="pie-chart" style="width: 64px; height: 64px; color: #f59e0b; margin-bottom: 20px;"></i>
                            <button class="btn-primary">Gerar Relatório</button>
                        </div>
                    </div>

                    <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorioQualificacao('modalidade')">
                        <h3 class="chart-title"><i data-lucide="list"></i> Relatório por Modalidade</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Performance e distribuição por modalidade licitatória</p>
                        <div style="text-align: center;">
                            <i data-lucide="bar-chart-3" style="width: 64px; height: 64px; color: #d97706; margin-bottom: 20px;"></i>
                            <button class="btn-primary">Gerar Relatório</button>
                        </div>
                    </div>

                    <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorioQualificacao('area')">
                        <h3 class="chart-title"><i data-lucide="building"></i> Relatório por Área</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Desempenho por área demandante</p>
                        <div style="text-align: center;">
                            <i data-lucide="users" style="width: 64px; height: 64px; color: #b45309; margin-bottom: 20px;"></i>
                            <button class="btn-primary">Gerar Relatório</button>
                        </div>
                    </div>

                    <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorioQualificacao('financeiro')">
                        <h3 class="chart-title"><i data-lucide="dollar-sign"></i> Relatório Financeiro</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Análise financeira e evolução de valores</p>
                        <div style="text-align: center;">
                            <i data-lucide="trending-up" style="width: 64px; height: 64px; color: #92400e; margin-bottom: 20px;"></i>
                            <button class="btn-primary">Gerar Relatório</button>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Estatísticas Avançadas -->
            <section id="estatisticas" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="bar-chart-3"></i> Estatísticas Avançadas</h1>
                    <p>Análise detalhada e métricas de performance do processo de qualificação</p>
                </div>
                
                <!-- Estatísticas Gerais Expandidas -->
                <div class="stats-section">
                    <h2><i data-lucide="trending-up"></i> Visão Geral</h2>
                    <div class="stats-grid-expanded">
                        <div class="stat-card-expanded primary">
                            <div class="stat-icon"><i data-lucide="file-text"></i></div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($stats['total_qualificacoes']); ?></div>
                                <div class="stat-label">Total de Qualificações</div>
                                <div class="stat-trend">
                                    <?php 
                                    $taxa_conclusao = $stats['total_qualificacoes'] > 0 ? 
                                        round(($stats['concluidas'] / $stats['total_qualificacoes']) * 100, 1) : 0;
                                    ?>
                                    <span class="trend-positive"><?php echo $taxa_conclusao; ?>% concluídas</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card-expanded success">
                            <div class="stat-icon"><i data-lucide="check-circle"></i></div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($stats['concluidas']); ?></div>
                                <div class="stat-label">Concluídas</div>
                                <div class="stat-trend">
                                    <span class="trend-positive">
                                        <?php echo formatarMoeda($stats['valor_total'] * ($stats['concluidas'] / max($stats['total_qualificacoes'], 1))); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card-expanded warning">
                            <div class="stat-icon"><i data-lucide="clock"></i></div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($stats['em_andamento']); ?></div>
                                <div class="stat-label">Em Análise</div>
                                <div class="stat-trend">
                                    <?php 
                                    $percentual_andamento = $stats['total_qualificacoes'] > 0 ? 
                                        round(($stats['em_andamento'] / $stats['total_qualificacoes']) * 100, 1) : 0;
                                    ?>
                                    <span class="trend-neutral"><?php echo $percentual_andamento; ?>% do total</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card-expanded info">
                            <div class="stat-icon"><i data-lucide="dollar-sign"></i></div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo formatarMoeda($stats['valor_total']); ?></div>
                                <div class="stat-label">Valor Total</div>
                                <div class="stat-trend">
                                    <span class="trend-neutral">Média: <?php echo formatarMoeda($stats['valor_medio']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Análise de Valores -->
                <div class="stats-section">
                    <h2><i data-lucide="dollar-sign"></i> Análise Financeira</h2>
                    <div class="stats-grid">
                        <div class="stat-card valor-maximo">
                            <div class="stat-header">
                                <i data-lucide="trending-up"></i>
                                <span>Maior Valor</span>
                            </div>
                            <div class="stat-number big"><?php echo formatarMoeda($stats['maior_valor']); ?></div>
                        </div>
                        
                        <div class="stat-card valor-medio">
                            <div class="stat-header">
                                <i data-lucide="minus"></i>
                                <span>Valor Médio</span>
                            </div>
                            <div class="stat-number big"><?php echo formatarMoeda($stats['valor_medio']); ?></div>
                        </div>
                        
                        <div class="stat-card valor-minimo">
                            <div class="stat-header">
                                <i data-lucide="trending-down"></i>
                                <span>Menor Valor</span>
                            </div>
                            <div class="stat-number big"><?php echo formatarMoeda($stats['menor_valor']); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Estatísticas por Modalidade -->
                <?php if (!empty($stats_modalidades)): ?>
                <div class="stats-section">
                    <h2><i data-lucide="pie-chart"></i> Performance por Modalidade</h2>
                    <div class="table-responsive">
                        <table class="stats-table">
                            <thead>
                                <tr>
                                    <th>Modalidade</th>
                                    <th>Quantidade</th>
                                    <th>Valor Total</th>
                                    <th>Valor Médio</th>
                                    <th>Taxa de Conclusão</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_modalidades as $modalidade): ?>
                                <tr>
                                    <td>
                                        <span class="modalidade-badge badge-<?php echo strtolower(str_replace(['Ã', ' '], ['a', '-'], $modalidade['modalidade'])); ?>">
                                            <?php echo htmlspecialchars($modalidade['modalidade']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo number_format($modalidade['quantidade']); ?></strong></td>
                                    <td><span class="valor-verde"><?php echo formatarMoeda($modalidade['valor_total_modalidade']); ?></span></td>
                                    <td><?php echo formatarMoeda($modalidade['valor_medio_modalidade']); ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $modalidade['taxa_conclusao']; ?>%"></div>
                                            <span class="progress-text"><?php echo $modalidade['taxa_conclusao']; ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($modalidade['taxa_conclusao'] >= 80): ?>
                                            <span class="status-badge status-aprovado">Excelente</span>
                                        <?php elseif ($modalidade['taxa_conclusao'] >= 60): ?>
                                            <span class="status-badge status-em-andamento">Boa</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pendente">Atenção</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Top Áreas Demandantes -->
                <?php if (!empty($stats_areas)): ?>
                <div class="stats-section">
                    <h2><i data-lucide="building"></i> Top 5 Áreas Demandantes</h2>
                    <div class="ranking-cards">
                        <?php foreach ($stats_areas as $index => $area): ?>
                        <div class="ranking-card <?php echo $index < 3 ? 'top-' . ($index + 1) : ''; ?>">
                            <div class="ranking-position"><?php echo $index + 1; ?>º</div>
                            <div class="ranking-content">
                                <h4><?php echo htmlspecialchars($area['area_demandante']); ?></h4>
                                <div class="ranking-stats">
                                    <span><i data-lucide="file-text"></i> <?php echo $area['quantidade']; ?> qualificações</span>
                                    <span><i data-lucide="dollar-sign"></i> <?php echo formatarMoeda($area['valor_total_area']); ?></span>
                                    <span><i data-lucide="percent"></i> <?php echo $area['taxa_conclusao_area']; ?>% concluídas</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Performance de Processamento -->
                <?php if (!empty($stats_performance) && $stats_performance['total_processadas'] > 0): ?>
                <div class="stats-section">
                    <h2><i data-lucide="zap"></i> Performance de Processamento</h2>
                    <div class="performance-grid">
                        <div class="performance-card">
                            <div class="performance-icon"><i data-lucide="clock"></i></div>
                            <div class="performance-content">
                                <div class="performance-number"><?php echo round($stats_performance['tempo_medio_processamento']); ?></div>
                                <div class="performance-label">Dias Médios</div>
                                <div class="performance-detail">Para conclusão</div>
                            </div>
                        </div>
                        
                        <div class="performance-card">
                            <div class="performance-icon"><i data-lucide="zap"></i></div>
                            <div class="performance-content">
                                <div class="performance-number"><?php echo round($stats_performance['tempo_min_processamento']); ?></div>
                                <div class="performance-label">Mais Rápida</div>
                                <div class="performance-detail">Dias para conclusão</div>
                            </div>
                        </div>
                        
                        <div class="performance-card">
                            <div class="performance-icon"><i data-lucide="turtle"></i></div>
                            <div class="performance-content">
                                <div class="performance-number"><?php echo round($stats_performance['tempo_max_processamento']); ?></div>
                                <div class="performance-label">Mais Lenta</div>
                                <div class="performance-detail">Dias para conclusão</div>
                            </div>
                        </div>
                        
                        <div class="performance-card">
                            <div class="performance-icon"><i data-lucide="target"></i></div>
                            <div class="performance-content">
                                <div class="performance-number">
                                    <?php echo round(($stats_performance['processadas_30_dias'] / $stats_performance['total_processadas']) * 100); ?>%
                                </div>
                                <div class="performance-label">Em 30 Dias</div>
                                <div class="performance-detail"><?php echo $stats_performance['processadas_30_dias']; ?> de <?php echo $stats_performance['total_processadas']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Top Responsáveis -->
                <?php if (!empty($stats_responsaveis)): ?>
                <div class="stats-section">
                    <h2><i data-lucide="users"></i> Top Responsáveis (Performance)</h2>
                    <div class="table-responsive">
                        <table class="stats-table">
                            <thead>
                                <tr>
                                    <th>Responsável</th>
                                    <th>Qualificações</th>
                                    <th>Valor Gerenciado</th>
                                    <th>Taxa de Conclusão</th>
                                    <th>Tempo Médio</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_responsaveis as $responsavel): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($responsavel['responsavel']); ?></strong></td>
                                    <td><?php echo number_format($responsavel['quantidade_responsavel']); ?></td>
                                    <td><span class="valor-verde"><?php echo formatarMoeda($responsavel['valor_total_responsavel']); ?></span></td>
                                    <td>
                                        <div class="progress-bar small">
                                            <div class="progress-fill" style="width: <?php echo $responsavel['taxa_conclusao_responsavel']; ?>%"></div>
                                            <span class="progress-text"><?php echo $responsavel['taxa_conclusao_responsavel']; ?>%</span>
                                        </div>
                                    </td>
                                    <td><?php echo round($responsavel['tempo_medio_responsavel'] ?? 0); ?> dias</td>
                                    <td>
                                        <?php 
                                        $performance_score = ($responsavel['taxa_conclusao_responsavel'] >= 80 && 
                                                            ($responsavel['tempo_medio_responsavel'] ?? 999) <= 45) ? 'excelente' :
                                                           (($responsavel['taxa_conclusao_responsavel'] >= 60) ? 'boa' : 'regular');
                                        ?>
                                        <span class="performance-badge <?php echo $performance_score; ?>">
                                            <?php echo ucfirst($performance_score); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Evolução Temporal -->
                <?php if (!empty($stats_temporais)): ?>
                <div class="stats-section">
                    <h2><i data-lucide="calendar"></i> Evolução Temporal (Últimos 6 Meses)</h2>
                    <div class="timeline-stats">
                        <?php foreach ($stats_temporais as $periodo): ?>
                        <div class="timeline-item">
                            <div class="timeline-header">
                                <h4><?php echo htmlspecialchars($periodo['mes_formatado']); ?></h4>
                                <span class="timeline-quantity"><?php echo $periodo['quantidade_mes']; ?> qualificações</span>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-stat">
                                    <span class="label">Valor Total:</span>
                                    <span class="valor-verde"><?php echo formatarMoeda($periodo['valor_mes']); ?></span>
                                </div>
                                <div class="timeline-stat">
                                    <span class="label">Concluídas:</span>
                                    <span><?php echo $periodo['concluidos_mes']; ?> (<?php echo round(($periodo['concluidos_mes'] / $periodo['quantidade_mes']) * 100, 1); ?>%)</span>
                                </div>
                                <div class="timeline-stat">
                                    <span class="label">Tempo Médio:</span>
                                    <span><?php echo round($periodo['tempo_medio_dias']); ?> dias</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </section>
            
        </main>
    </div>

    <!-- Modal de Criação de Qualificação (baseado no modal de licitações) -->
    <div id="modalCriarQualificacao" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="plus-circle"></i> Criar Nova Qualificação
                </h3>
                <span class="close" onclick="fecharModal('modalCriarQualificacao')">&times;</span>
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

                    <form action="process.php" method="POST" id="formCriarQualificacao">
                        <input type="hidden" name="acao" value="criar_qualificacao">

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

                                <div class="form-group">
                                    <label>Número do DFD (opcional)</label>
                                    <input type="text" name="numero_dfd" id="numero_dfd_criar" placeholder="Ex: 12345.123456/2024-12" readonly>
                                    <button type="button" onclick="abrirSeletorContratacao()" class="btn-secondary" style="margin-top: 8px;">
                                        <i data-lucide="search"></i> Selecionar do PCA
                                    </button>
                                </div>

                                <div class="form-group">
                                    <label>Número da Contratação (opcional)</label>
                                    <input type="text" name="numero_contratacao" id="numero_contratacao_criar" placeholder="Título da contratação" readonly>
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
                            <button type="button" onclick="fecharModal('modalCriarQualificacao')" class="btn-secondary">
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

    <!-- Modal de Relatórios -->
    <div id="modalRelatorioQualificacao" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="file-text"></i> <span id="tituloRelatorioQualificacao">Configurar Relatório</span>
                </h3>
                <span class="close" onclick="fecharModal('modalRelatorioQualificacao')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formRelatorioQualificacao">
                    <input type="hidden" id="tipo_relatorio_qualificacao" name="tipo">

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                            Período
                        </label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="font-size: 12px; color: #6c757d;">Data Inicial</label>
                                <input type="date" name="data_inicial" id="qual_data_inicial" value="<?php echo date('Y-01-01'); ?>">
                            </div>
                            <div>
                                <label style="font-size: 12px; color: #6c757d;">Data Final</label>
                                <input type="date" name="data_final" id="qual_data_final" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="gavel" style="width: 16px; height: 16px;"></i>
                            Modalidade (Opcional)
                        </label>
                        <select name="modalidade" id="qual_modalidade">
                            <option value="">Todas as modalidades</option>
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
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                            Área Demandante (Opcional)
                        </label>
                        <input type="text" name="area_demandante" id="qual_area_demandante" placeholder="Digite parte do nome da área">
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                            Status (Opcional)
                        </label>
                        <select name="status" id="qual_status">
                            <option value="">Todos os status</option>
                            <option value="EM ANÁLISE">EM ANÁLISE</option>
                            <option value="CONCLUÍDO">CONCLUÍDO</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="file-type" style="width: 16px; height: 16px;"></i>
                            Formato
                        </label>
                        <select name="formato" id="qual_formato">
                            <option value="html">HTML (Visualização)</option>
                            <option value="csv">CSV (Excel)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="incluir_graficos" id="qual_incluir_graficos" checked>
                            <i data-lucide="bar-chart-3" style="width: 16px; height: 16px;"></i>
                            Incluir gráficos (apenas HTML)
                        </label>
                    </div>

                    <div class="modal-footer" style="display: flex; gap: 15px; justify-content: flex-end; padding: 20px 0 0 0; border-top: 1px solid #e5e7eb; margin-top: 25px;">
                        <button type="button" onclick="fecharModal('modalRelatorioQualificacao')" class="btn-secondary">
                            <i data-lucide="x"></i> Cancelar
                        </button>
                        <button type="submit" class="btn-primary">
                            <i data-lucide="file-text"></i> Gerar Relatório
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="assets/qualificacao-dashboard.js"></script>
    <script src="assets/dark-mode.js"></script>
    <script src="assets/mobile-improvements.js"></script>
    <script src="assets/ux-improvements.js"></script>
    <script src="assets/notifications.js"></script>
    
</body>
</html>