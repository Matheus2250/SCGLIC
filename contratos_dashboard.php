<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// ========================================
// SISTEMA DE PAGINAÇÃO E FILTROS
// ========================================

// Configuração de paginação
$contratos_por_pagina = isset($_GET['por_pagina']) ? max(10, min(100, intval($_GET['por_pagina']))) : 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_atual - 1) * $contratos_por_pagina;

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
    $where_conditions[] = "(numero_contrato LIKE ? OR fornecedor LIKE ? OR objeto LIKE ? OR responsavel LIKE ?)";
    $busca_param = "%$filtro_busca%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Buscar estatísticas dos contratos
try {
    // Verificar se a tabela existe
    $check_table = $pdo->query("SHOW TABLES LIKE 'contratos'");
    if ($check_table->rowCount() == 0) {
        // Tabela não existe - usar dados zerados
        $stats = [
            'total_contratos' => 0,
            'vigentes' => 0,
            'vencidos' => 0,
            'valor_total' => 0.00
        ];
        $contratos_recentes = [];
        $total_contratos = 0;
    } else {
        // Buscar estatísticas gerais
        $stats_sql = "SELECT 
            COUNT(*) as total_contratos,
            SUM(CASE WHEN status = 'VIGENTE' THEN 1 ELSE 0 END) as vigentes,
            SUM(CASE WHEN status = 'VENCIDO' THEN 1 ELSE 0 END) as vencidos,
            SUM(valor_contrato) as valor_total,
            AVG(valor_contrato) as valor_medio,
            MIN(valor_contrato) as menor_valor,
            MAX(valor_contrato) as maior_valor
            FROM contratos";
        $stmt_stats = $pdo->query($stats_sql);
        $stats = $stmt_stats->fetch();
        
        // Garantir que os valores não sejam null
        $stats['total_contratos'] = intval($stats['total_contratos']);
        $stats['vigentes'] = intval($stats['vigentes']);
        $stats['vencidos'] = intval($stats['vencidos']);
        $stats['valor_total'] = floatval($stats['valor_total'] ?? 0.00);
        $stats['valor_medio'] = floatval($stats['valor_medio'] ?? 0.00);
        $stats['menor_valor'] = floatval($stats['menor_valor'] ?? 0.00);
        $stats['maior_valor'] = floatval($stats['maior_valor'] ?? 0.00);
        
        // Contar contratos para paginação
        $count_sql = "SELECT COUNT(*) FROM contratos WHERE $where_clause";
        $stmt_count = $pdo->prepare($count_sql);
        $stmt_count->execute($params);
        $total_contratos = $stmt_count->fetchColumn();
        
        // Buscar contratos com paginação
        $contratos_sql = "SELECT * FROM contratos WHERE $where_clause ORDER BY criado_em DESC LIMIT $contratos_por_pagina OFFSET $offset";
        $stmt_contratos = $pdo->prepare($contratos_sql);
        $stmt_contratos->execute($params);
        $contratos_recentes = $stmt_contratos->fetchAll();
    }
} catch (Exception $e) {
    $stats = [
        'total_contratos' => 0,
        'vigentes' => 0,
        'vencidos' => 0,
        'valor_total' => 0.00
    ];
    $contratos_recentes = [];
    $total_contratos = 0;
}

// Calcular informações de paginação
$total_paginas = ceil($total_contratos / $contratos_por_pagina);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contratos - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/contratos-dashboard.css">
    <link rel="stylesheet" href="assets/dark-mode.css">
    <link rel="stylesheet" href="assets/mobile-improvements.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i data-lucide="menu"></i>
                </button>
                <h2><i data-lucide="file-contract"></i> Contratos</h2>
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
                
                <!-- Contratos -->
                <div class="nav-section">
                    <div class="nav-section-title">Contratos</div>
                    <a href="javascript:void(0)" class="nav-item" onclick="showSection('lista-contratos')">
                        <i data-lucide="list"></i>
                        <span>Contratos</span>
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
                        <i data-lucide="calendar-check"></i>
                        <span>Planejamento</span>
                    </a>
                    <a href="licitacao_dashboard.php" class="nav-item">
                        <i data-lucide="gavel"></i>
                        <span>Licitações</span>
                    </a>
                    <a href="qualificacao_dashboard.php" class="nav-item">
                        <i data-lucide="award"></i>
                        <span>Qualificação</span>
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
            <section id="dashboard-section" class="content-section active">
                <div class="dashboard-header">
                    <h1>Dashboard de Contratos</h1>
                    <p>Visão geral dos contratos administrativos</p>
                </div>

                <!-- Cards de Estatísticas -->
                <div class="dashboard-stats">
                    <div class="stat-card purple">
                        <div class="stat-icon">
                            <i data-lucide="file-contract"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_contratos']); ?></h3>
                            <p>Total de Contratos</p>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-icon">
                            <i data-lucide="check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['vigentes']); ?></h3>
                            <p>Contratos Vigentes</p>
                        </div>
                    </div>

                    <div class="stat-card red">
                        <div class="stat-icon">
                            <i data-lucide="alert-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['vencidos']); ?></h3>
                            <p>Contratos Vencidos</p>
                        </div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-icon">
                            <i data-lucide="dollar-sign"></i>
                        </div>
                        <div class="stat-info">
                            <h3>R$ <?php echo number_format($stats['valor_total'], 2, ',', '.'); ?></h3>
                            <p>Valor Total</p>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="charts-container">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Status dos Contratos</h3>
                        </div>
                        <div class="chart-content">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Performance Mensal</h3>
                        </div>
                        <div class="chart-content">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Lista de Contratos Recentes -->
                <div class="recent-contracts">
                    <div class="section-header">
                        <h3>Contratos Recentes</h3>
                        <button class="btn-primary" onclick="showSection('lista-contratos')">
                            <i data-lucide="eye"></i> Ver Todos
                        </button>
                    </div>
                    
                    <div class="contracts-table">
                        <?php if (empty($contratos_recentes)): ?>
                            <div class="empty-state">
                                <i data-lucide="file-contract"></i>
                                <h4>Nenhum contrato encontrado</h4>
                                <p>Ainda não há contratos cadastrados no sistema.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Fornecedor</th>
                                        <th>Objeto</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Vigência</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($contratos_recentes, 0, 5) as $contrato): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($contrato['numero_contrato']); ?></td>
                                        <td><?php echo htmlspecialchars($contrato['fornecedor']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($contrato['objeto'], 0, 60)) . '...'; ?></td>
                                        <td>R$ <?php echo number_format($contrato['valor_contrato'], 2, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($contrato['status']); ?>">
                                                <?php echo htmlspecialchars($contrato['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($contrato['data_fim'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Lista de Contratos -->
            <section id="lista-contratos-section" class="content-section">
                <div class="section-header">
                    <h2>Contratos</h2>
                    <button class="btn-primary" onclick="abrirModal('modalCriarContrato')">
                        <i data-lucide="plus"></i> Novo Contrato
                    </button>
                </div>

                <!-- Filtros -->
                <div class="filtros-container">
                    <form method="GET" class="filtros-form">
                        <div class="filtro-grupo">
                            <label>Status:</label>
                            <select name="status_filtro">
                                <option value="">Todos</option>
                                <option value="VIGENTE" <?php echo $filtro_status == 'VIGENTE' ? 'selected' : ''; ?>>VIGENTE</option>
                                <option value="VENCIDO" <?php echo $filtro_status == 'VENCIDO' ? 'selected' : ''; ?>>VENCIDO</option>
                                <option value="RESCINDIDO" <?php echo $filtro_status == 'RESCINDIDO' ? 'selected' : ''; ?>>RESCINDIDO</option>
                            </select>
                        </div>

                        <div class="filtro-grupo">
                            <label>Modalidade:</label>
                            <select name="modalidade_filtro">
                                <option value="">Todas</option>
                                <option value="PREGÃO" <?php echo $filtro_modalidade == 'PREGÃO' ? 'selected' : ''; ?>>PREGÃO</option>
                                <option value="CONCURSO" <?php echo $filtro_modalidade == 'CONCURSO' ? 'selected' : ''; ?>>CONCURSO</option>
                                <option value="INEXIGIBILIDADE" <?php echo $filtro_modalidade == 'INEXIGIBILIDADE' ? 'selected' : ''; ?>>INEXIGIBILIDADE</option>
                                <option value="DISPENSA" <?php echo $filtro_modalidade == 'DISPENSA' ? 'selected' : ''; ?>>DISPENSA</option>
                            </select>
                        </div>

                        <div class="filtro-grupo busca-grupo">
                            <label>Buscar:</label>
                            <input type="text" name="busca" placeholder="Número, fornecedor, objeto..." value="<?php echo htmlspecialchars($filtro_busca); ?>">
                        </div>

                        <div class="filtro-acoes">
                            <button type="submit" class="btn-primary">
                                <i data-lucide="search"></i> Filtrar
                            </button>
                            <a href="?" class="btn-secondary">
                                <i data-lucide="x"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Tabela de Contratos -->
                <div class="contracts-list">
                    <?php if (empty($contratos_recentes)): ?>
                        <div class="empty-state">
                            <i data-lucide="file-contract"></i>
                            <h4>Nenhum contrato encontrado</h4>
                            <p>Não há contratos que atendam aos filtros selecionados.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Fornecedor</th>
                                        <th>Objeto</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Vigência</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contratos_recentes as $contrato): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($contrato['numero_contrato']); ?></td>
                                        <td><?php echo htmlspecialchars($contrato['fornecedor']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($contrato['objeto'], 0, 80)) . '...'; ?></td>
                                        <td>R$ <?php echo number_format($contrato['valor_contrato'], 2, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($contrato['status']); ?>">
                                                <?php echo htmlspecialchars($contrato['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($contrato['data_fim'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-icon" title="Visualizar">
                                                    <i data-lucide="eye"></i>
                                                </button>
                                                <button class="btn-icon" title="Editar">
                                                    <i data-lucide="edit"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                        <div class="pagination">
                            <?php if ($pagina_atual > 1): ?>
                                <a href="?pagina=<?php echo $pagina_atual - 1; ?>&status_filtro=<?php echo $filtro_status; ?>&modalidade_filtro=<?php echo $filtro_modalidade; ?>&busca=<?php echo $filtro_busca; ?>" class="page-btn">
                                    <i data-lucide="chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                <a href="?pagina=<?php echo $i; ?>&status_filtro=<?php echo $filtro_status; ?>&modalidade_filtro=<?php echo $filtro_modalidade; ?>&busca=<?php echo $filtro_busca; ?>" 
                                   class="page-btn <?php echo $i == $pagina_atual ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagina_atual < $total_paginas): ?>
                                <a href="?pagina=<?php echo $pagina_atual + 1; ?>&status_filtro=<?php echo $filtro_status; ?>&modalidade_filtro=<?php echo $filtro_modalidade; ?>&busca=<?php echo $filtro_busca; ?>" class="page-btn">
                                    <i data-lucide="chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Relatórios -->
            <section id="relatorios-section" class="content-section">
                <div class="section-header">
                    <h2>Relatórios</h2>
                </div>
                
                <div class="reports-grid">
                    <div class="report-card">
                        <div class="report-icon">
                            <i data-lucide="file-text"></i>
                        </div>
                        <h3>Relatório Geral</h3>
                        <p>Relatório completo de todos os contratos</p>
                        <button class="btn-primary" onclick="gerarRelatorio('geral')">
                            <i data-lucide="download"></i> Gerar
                        </button>
                    </div>

                    <div class="report-card">
                        <div class="report-icon">
                            <i data-lucide="calendar"></i>
                        </div>
                        <h3>Contratos por Período</h3>
                        <p>Análise por período específico</p>
                        <button class="btn-primary" onclick="gerarRelatorio('periodo')">
                            <i data-lucide="download"></i> Gerar
                        </button>
                    </div>

                    <div class="report-card">
                        <div class="report-icon">
                            <i data-lucide="alert-triangle"></i>
                        </div>
                        <h3>Contratos Vencendo</h3>
                        <p>Contratos próximos ao vencimento</p>
                        <button class="btn-primary" onclick="gerarRelatorio('vencendo')">
                            <i data-lucide="download"></i> Gerar
                        </button>
                    </div>

                    <div class="report-card">
                        <div class="report-icon">
                            <i data-lucide="dollar-sign"></i>
                        </div>
                        <h3>Relatório Financeiro</h3>
                        <p>Análise de valores e execução</p>
                        <button class="btn-primary" onclick="gerarRelatorio('financeiro')">
                            <i data-lucide="download"></i> Gerar
                        </button>
                    </div>
                </div>
            </section>

            <!-- Estatísticas -->
            <section id="estatisticas-section" class="content-section">
                <div class="section-header">
                    <h2>Estatísticas</h2>
                </div>
                
                <div class="stats-content">
                    <div class="empty-state">
                        <i data-lucide="bar-chart-3"></i>
                        <h4>Estatísticas em Desenvolvimento</h4>
                        <p>As estatísticas detalhadas estarão disponíveis em breve.</p>
                    </div>
                </div>
            </section>
            
        </main>
    </div>

    <!-- Scripts -->
    <script src="assets/contratos-dashboard.js"></script>
    <script src="assets/dark-mode.js"></script>
    <script src="assets/mobile-improvements.js"></script>
    <script src="assets/ux-improvements.js"></script>
    <script src="assets/notifications.js"></script>
    
</body>
</html>