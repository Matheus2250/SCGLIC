<?php
/**
 * Dashboard do Módulo de Contratos
 * Sistema CGLIC - Ministério da Saúde
 * 
 * Integração com API Comprasnet (UASG 250110)
 * Gestão completa de contratos administrativos
 */

require_once 'config.php';
require_once 'functions.php';

// Verificar login
if (!verificarLogin()) {
    header('Location: index.php');
    exit;
}

// Conectar ao banco usando PDO
$pdo = conectarDB();

// Verificar permissões para o módulo de contratos
$nivel = $_SESSION['usuario_nivel'] ?? $_SESSION['nivel_acesso'] ?? null;
$podeEditar = in_array($nivel, [1, 3]); // Coordenador e DIPLI podem editar contratos
$podeVisualizar = in_array($nivel, [1, 2, 3, 4]); // Todos podem visualizar

if (!$podeVisualizar) {
    header('Location: selecao_modulos.php?erro=sem_permissao');
    exit;
}

// Verificar se o módulo de contratos foi configurado
$moduloConfigurado = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'contratos'");
    $moduloConfigurado = ($stmt && $stmt->rowCount() > 0);
} catch (Exception $e) {
    $moduloConfigurado = false;
}

// Parâmetros de filtro e paginação
$filtroStatus = $_GET['status'] ?? '';
$filtroModalidade = $_GET['modalidade'] ?? '';
$filtroVencimento = $_GET['vencimento'] ?? '';
$busca = $_GET['busca'] ?? '';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$limite = 20;
$offset = ($pagina - 1) * $limite;

// Inicializar variáveis
$contratos = [];
$total = 0;
$stats = [
    'total_contratos' => 0,
    'contratos_vigentes' => 0,
    'contratos_encerrados' => 0,
    'valor_total_contratos' => 0,
    'valor_total_empenhado' => 0,
    'valor_total_pago' => 0,
    'vencem_30_dias' => 0,
    'vencidos' => 0
];
$alertas = [];
$historicoSync = [];

if ($moduloConfigurado) {
    // Construir query de filtros  
    $whereConditions = ["1=1"];
    $params = [];
    $types = '';

    if ($filtroStatus) {
        $whereConditions[] = "c.status_contrato = ?";
        $params[] = $filtroStatus;
        $types .= 's';
    }

    if ($filtroModalidade) {
        $whereConditions[] = "c.modalidade LIKE ?";
        $params[] = "%{$filtroModalidade}%";
        $types .= 's';
    }

    if ($filtroVencimento) {
        switch ($filtroVencimento) {
            case 'vencidos':
                $whereConditions[] = "c.data_fim < CURDATE()";
                break;
            case '30_dias':
                $whereConditions[] = "c.data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                break;
            case '90_dias':
                $whereConditions[] = "c.data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
                break;
        }
    }

    if ($busca) {
        $whereConditions[] = "(c.numero_contrato LIKE ? OR c.objeto_servico LIKE ? OR c.nome_empresa LIKE ? OR c.numero_sei LIKE ?)";
        $params[] = "%{$busca}%";
        $params[] = "%{$busca}%";
        $params[] = "%{$busca}%";
        $params[] = "%{$busca}%";
        $types .= 'ssss';
    }

    $whereClause = implode(' AND ', $whereConditions);

    try {
        // Buscar contratos
        $query = "
            SELECT c.*, 
                   CASE 
                       WHEN c.data_fim <= CURDATE() THEN 'vencido'
                       WHEN c.data_fim <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'vence_30_dias'
                       WHEN c.data_fim <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'vence_90_dias'
                       ELSE 'vigente'
                   END as alerta_vencimento,
                   DATEDIFF(c.data_fim, CURDATE()) as dias_para_vencimento
            FROM contratos c
            WHERE {$whereClause}
            ORDER BY c.criado_em DESC
            LIMIT {$limite} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($query);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        $contratos = $stmt->fetchAll();

        // Contar total para paginação
        $countQuery = "SELECT COUNT(DISTINCT c.id) as total FROM contratos c WHERE {$whereClause}";
        $stmt = $pdo->prepare($countQuery);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        $total = $stmt->fetch()['total'];

        // Buscar estatísticas
        $statsQuery = "
            SELECT 
                COUNT(*) as total_contratos,
                COUNT(CASE WHEN status_contrato = 'ativo' THEN 1 END) as contratos_vigentes,
                COUNT(CASE WHEN status_contrato = 'encerrado' THEN 1 END) as contratos_encerrados,
                COALESCE(SUM(COALESCE(valor_atual, valor_inicial, 0)), 0) as valor_total_contratos,
                0 as valor_total_empenhado,
                0 as valor_total_pago,
                COUNT(CASE WHEN data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                           AND status_contrato = 'ativo' THEN 1 END) as vencem_30_dias,
                COUNT(CASE WHEN data_fim < CURDATE() AND status_contrato = 'ativo' THEN 1 END) as vencidos
            FROM contratos
        ";
        $stmt = $pdo->query($statsQuery);
        if ($stmt) {
            $stats = $stmt->fetch();
        }

        // Dados para gráficos
        $dados_modalidade = $pdo->query("
            SELECT modalidade, COUNT(*) as quantidade
            FROM contratos
            WHERE modalidade IS NOT NULL AND modalidade != ''
            GROUP BY modalidade
        ")->fetchAll();

        $dados_area_gestora = $pdo->query("
            SELECT 
                CASE
                    WHEN c.area_gestora IS NULL OR c.area_gestora = '' THEN 'Não Definido'
                    ELSE c.area_gestora
                END AS area_gestora,
                COUNT(*) AS quantidade
            FROM contratos c
            GROUP BY c.area_gestora
            ORDER BY quantidade DESC
            LIMIT 5
        ")->fetchAll();

        $dados_mensal = $pdo->query("
            SELECT
                DATE_FORMAT(
                    COALESCE(data_assinatura, criado_em),
                    '%Y-%m'
                ) as mes,
                COUNT(*) as quantidade
            FROM contratos
            WHERE (data_assinatura IS NOT NULL AND data_assinatura >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH))
            OR (data_assinatura IS NULL AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH))
            GROUP BY DATE_FORMAT(
                COALESCE(data_assinatura, criado_em),
                '%Y-%m'
            )
            ORDER BY mes
        ")->fetchAll();

        // Buscar alertas ativos
        $alertasQuery = "
            SELECT c.numero_contrato, c.objeto_servico as objeto, c.nome_empresa as contratado_nome, 
                   c.data_fim as data_fim_vigencia, COALESCE(c.valor_atual, c.valor_inicial) as valor_total,
                   'vencimento' as tipo_alerta,
                   DATEDIFF(c.data_fim, CURDATE()) as dias_restantes
            FROM contratos c 
            WHERE c.data_fim <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND c.status_contrato = 'ativo'
            ORDER BY c.data_fim ASC
            LIMIT 10
        ";
        $stmt = $pdo->query($alertasQuery);
        if ($stmt) {
            $alertas = $stmt->fetchAll();
        }

        // Buscar histórico de sincronização (se a tabela existir)
        try {
            $syncQuery = "
                SELECT * FROM contratos_historico 
                WHERE acao = 'criado'
                ORDER BY data_alteracao DESC 
                LIMIT 5
            ";
            $stmt = $pdo->query($syncQuery);
            if ($stmt) {
                $historicoSync = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            // Tabela pode não existir ainda
            $historicoSync = [];
        }

    } catch (Exception $e) {
        // Em caso de erro, definir valores padrão
        error_log("Erro no dashboard de contratos: " . $e->getMessage());
    }
}

$totalPaginas = ceil($total / $limite);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contratos - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/dashboard.css">
    <link rel="stylesheet" href="assets/contratos-dashboard.css">
    <link rel="stylesheet" href="assets/mobile-improvements.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
                    <i data-lucide="menu"></i>
                </button>
                <h2><i data-lucide="file-contract"></i> Contratos</h2>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Visão Geral</div>
                    <button class="nav-item active" onclick="showSection('dashboard')">
                        <i data-lucide="bar-chart-3"></i> <span>Dashboard</span>
                    </button>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Gerenciar & Relatórios</div>
                    <button class="nav-item" onclick="showSection('lista-contratos')">
                        <i data-lucide="list"></i> <span>Lista de Contratos</span>
                    </button>
                    <?php if ($podeEditar): ?>
                    <button class="nav-item" onclick="abrirImportacao()">
                        <i data-lucide="upload"></i> <span>Importar CSV</span>
                    </button>
                    <?php endif; ?>
                    <button class="nav-item" onclick="gerarRelatorio()">
                        <i data-lucide="file-text"></i> <span>Relatórios</span>
                    </button>
                    <?php if (isVisitante()): ?>
                    <div style="margin: 10px 15px; padding: 8px; background: #fff3cd; border-radius: 6px; border-left: 3px solid #f39c12;">
                        <small style="color: #856404; font-size: 11px; font-weight: 600;">
                            <i data-lucide="eye" style="width: 12px; height: 12px;"></i> MODO VISITANTE<br>
                            Somente visualização e exportação
                        </small>
                    </div>
                    <?php endif; ?>
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
                    <a href="qualificacao_dashboard.php" class="nav-item">
                        <i data-lucide="award"></i>
                        <span>Qualificações</span>
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
                        <small style="color: #3498db; font-weight: 600;">
                            <?php echo getNomeNivel($_SESSION['usuario_nivel'] ?? 3); ?> - <?php echo htmlspecialchars($_SESSION['usuario_departamento'] ?? ''); ?>
                        </small>
                        <?php if (isVisitante()): ?>
                        <small style="color: #f39c12; font-weight: 600; display: block; margin-top: 4px;">
                            <i data-lucide="eye" style="width: 12px; height: 12px;"></i> Modo Somente Leitura
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="perfil_usuario.php" class="logout-btn" style="text-decoration: none; margin-bottom: 10px; background: #27ae60 !important;">
                    <i data-lucide="user"></i> <span>Meu Perfil</span>
                </a>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i data-lucide="log-out"></i> <span>Sair</span>
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <?php if (!$moduloConfigurado): ?>
            <!-- Setup inicial -->
            <div id="setup" class="content-section active">
                <div class="dashboard-header">
                    <h1><i data-lucide="database"></i> Configuração Inicial do Módulo</h1>
                    <p>Configure o módulo de Contratos para começar a usar</p>
                </div>
                
                <div class="setup-section">
                    <div class="setup-card">
                        <div class="setup-icon">
                            <i data-lucide="database"></i>
                        </div>
                        <div class="setup-content">
                            <h2>Módulo de Contratos - Configuração Inicial</h2>
                            <p>O módulo de Contratos precisa ser configurado antes do uso. Este módulo permite:</p>
                            <ul>
                                <li><strong>Gestão completa de contratos</strong> - Controle de vigências, valores e aditivos</li>
                                <li><strong>Importação de dados CSV</strong> - Carregamento em lote de contratos existentes</li>
                                <li><strong>Alertas inteligentes</strong> - Notificações de vencimento e irregularidades</li>
                                <li><strong>Relatórios gerenciais</strong> - Análises financeiras e operacionais</li>
                            </ul>
                            
                            <div class="setup-steps">
                                <h3>Passos para configuração:</h3>
                                <ol>
                                    <li>Execute o script SQL para criar as tabelas necessárias</li>
                                    <li>Importe os dados de contratos existentes (CSV)</li>
                                    <li>Configure alertas e notificações</li>
                                </ol>
                            </div>
                            
                            <?php if ($podeEditar): ?>
                            <div class="setup-actions">
                                <button onclick="executarSetup()" class="btn btn-primary">
                                    <i data-lucide="play"></i> Executar Setup
                                </button>
                                <button onclick="abrirImportacao()" class="btn btn-secondary">
                                    <i data-lucide="upload"></i> Importar Contratos
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i data-lucide="info"></i>
                                <p>Entre em contato com o administrador do sistema para configurar o módulo.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Dashboard Section -->
            <div id="dashboard" class="content-section active">
                <div class="dashboard-header">
                    <h1><i data-lucide="bar-chart-3"></i> Dashboard de Contratos</h1>
                    <p>Visão geral dos contratos administrativos da UASG 250110</p>
                </div>

                <!-- Cards de Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card info">
                        <div class="stat-number"><?= number_format($stats['total_contratos']) ?></div>
                        <div class="stat-label">Total de Contratos</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-number"><?= number_format($stats['contratos_vigentes']) ?></div>
                        <div class="stat-label">Contratos Vigentes</div>
                    </div>
                    
                    <div class="stat-card money">
                        <div class="stat-number">R$ <?= number_format($stats['valor_total_contratos'], 2, ',', '.') ?></div>
                        <div class="stat-label">Valor Total</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-number"><?= number_format($stats['vencem_30_dias']) ?></div>
                        <div class="stat-label">Vencem em 30 dias</div>
                    </div>
                </div>

                <!-- Gráficos de Análise -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="pie-chart"></i> Contratos por Modalidade</h3>
                        <div class="chart-container">
                            <canvas id="chartModalidade"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="building"></i> Contratos por Área Gestora</h3>
                        <div class="chart-container">
                            <canvas id="chartAreaGestora"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="trending-up"></i> Evolução Mensal</h3>
                        <div class="chart-container">
                            <canvas id="chartMensal"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="activity"></i> Status dos Contratos</h3>
                        <div class="chart-container">
                            <canvas id="chartStatus"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Alertas Importantes -->
                <?php if (!empty($alertas)): ?>
                <div class="alerts-section">
                    <div class="section-header">
                        <h3><i data-lucide="bell"></i> Alertas Importantes</h3>
                        <span class="badge badge-warning"><?= count($alertas) ?></span>
                    </div>
                    <div class="alerts-list">
                        <?php foreach ($alertas as $alerta): ?>
                        <div class="alert-item <?= $alerta['dias_restantes'] <= 0 ? 'alert-danger' : ($alerta['dias_restantes'] <= 7 ? 'alert-warning' : 'alert-info') ?>">
                            <div class="alert-icon">
                                <i data-lucide="<?= $alerta['dias_restantes'] <= 0 ? 'alert-circle' : 'clock' ?>"></i>
                            </div>
                            <div class="alert-content">
                                <div class="alert-title">
                                    Contrato <?= htmlspecialchars($alerta['numero_contrato']) ?>
                                </div>
                                <div class="alert-description">
                                    <?= substr(htmlspecialchars($alerta['objeto']), 0, 100) ?>...
                                </div>
                                <div class="alert-meta">
                                    <span><strong>Contratado:</strong> <?= htmlspecialchars($alerta['contratado_nome']) ?></span>
                                    <span><strong>Vencimento:</strong> <?= date('d/m/Y', strtotime($alerta['data_fim_vigencia'])) ?></span>
                                    <?php if ($alerta['dias_restantes'] <= 0): ?>
                                        <span class="status-vencido"><strong>VENCIDO</strong></span>
                                    <?php else: ?>
                                        <span class="dias-restantes"><?= $alerta['dias_restantes'] ?> dias restantes</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="alert-value">
                                R$ <?= number_format($alerta['valor_total'], 2, ',', '.') ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Lista de Contratos Section -->
            <div id="lista-contratos" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="list"></i> Lista de Contratos</h1>
                    <p>Visualize e gerencie todos os contratos da UASG 250110</p>
                </div>

                <!-- Filtros e Ações -->
                <div class="filter-section" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
                    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">
                        <input type="hidden" name="secao" value="lista-contratos">
                        
                        <div style="flex: 1; min-width: 250px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #1e3c72;">
                                <i data-lucide="search" style="width: 16px; height: 16px;"></i> Buscar:
                            </label>
                            <input type="text" name="busca" placeholder="Número, objeto ou contratado..." 
                                   value="<?= htmlspecialchars($busca) ?>" 
                                   style="width: 100%; padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 8px;">
                        </div>
                        
                        <div style="min-width: 150px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #1e3c72;">Status:</label>
                            <select name="status" style="width: 100%; padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="">Todos os Status</option>
                                <option value="ativo" <?= $filtroStatus === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inativo" <?= $filtroStatus === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                <option value="suspenso" <?= $filtroStatus === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
                                <option value="encerrado" <?= $filtroStatus === 'encerrado' ? 'selected' : '' ?>>Encerrado</option>
                            </select>
                        </div>

                        <div style="min-width: 150px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #1e3c72;">Vencimento:</label>
                            <select name="vencimento" style="width: 100%; padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="">Todos os Prazos</option>
                                <option value="vencidos" <?= $filtroVencimento === 'vencidos' ? 'selected' : '' ?>>Vencidos</option>
                                <option value="30_dias" <?= $filtroVencimento === '30_dias' ? 'selected' : '' ?>>Vencem em 30 dias</option>
                                <option value="90_dias" <?= $filtroVencimento === '90_dias' ? 'selected' : '' ?>>Vencem em 90 dias</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" style="background: #dc2626; color: white; padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                <i data-lucide="search" style="width: 16px; height: 16px;"></i> Filtrar
                            </button>
                            <a href="contratos_dashboard.php?secao=lista-contratos" style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 8px; text-decoration: none; font-weight: 600;">
                                <i data-lucide="x" style="width: 16px; height: 16px;"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Ações Principais -->
                <div class="actions-section">
                    <?php if ($podeEditar): ?>
                    <div class="actions-group">
                        <button onclick="abrirModalAdicionar()" class="btn btn-success">
                            <i data-lucide="plus"></i> Novo Contrato
                        </button>
                        <button onclick="abrirImportacao()" class="btn btn-info">
                            <i data-lucide="upload"></i> Importar CSV
                        </button>
                    </div>
                    <?php endif; ?>
                    <div class="actions-group">
                        <button onclick="gerarRelatorio()" class="btn btn-primary">
                            <i data-lucide="file-text"></i> Relatórios
                        </button>
                        <button onclick="exportarContratos()" class="btn btn-secondary">
                            <i data-lucide="download"></i> Exportar
                        </button>
                    </div>
                </div>

                <!-- Lista de Contratos -->
                <div class="contracts-section">
                    <div class="section-header">
                        <h3><i data-lucide="list"></i> Lista de Contratos</h3>
                        <div class="section-meta">
                            Mostrando <?= count($contratos) ?> de <?= number_format($total) ?> contratos
                        </div>
                    </div>

                    <div class="contracts-table-container">
                        <?php if (empty($contratos)): ?>
                        <div class="empty-state">
                            <i data-lucide="inbox"></i>
                            <h4>Nenhum contrato encontrado</h4>
                            <p>
                                <?php if ($busca || $filtroStatus || $filtroModalidade || $filtroVencimento): ?>
                                    Nenhum contrato atende aos filtros aplicados.
                                <?php else: ?>
                                    Ainda não há contratos cadastrados no sistema.
                                <?php endif; ?>
                            </p>
                            <?php if ($podeEditar && !$busca && !$filtroStatus && !$filtroModalidade && !$filtroVencimento): ?>
                            <button onclick="abrirModalAdicionar()" class="btn btn-primary">
                                <i data-lucide="plus"></i> Cadastrar Primeiro Contrato
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Número/Ano</th>
                                    <th>SEI</th>
                                    <th>Modalidade</th>
                                    <th>Objeto</th>
                                    <th>Valor Atual</th>
                                    <th>Status</th>
                                    <th>Empresa</th>
                                    <th>Vigência</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $contador_linha = 0;
                                foreach ($contratos as $contrato): 
                                    $contador_linha++;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($contrato['numero_contrato'] ?: 'N/I') ?></strong>
                                        <?php if ($contrato['ano_contrato']): ?>
                                        <br><small style="color: #6b7280;"><?= $contrato['ano_contrato'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($contrato['numero_sei'] ?: 'N/I') ?>
                                    </td>
                                    <td>
                                        <span style="background: #fee2e2; color: #dc2626; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                            <?= htmlspecialchars($contrato['modalidade'] ?: 'N/I') ?>
                                        </span>
                                    </td>
                                    <td title="<?= htmlspecialchars($contrato['objeto_servico'] ?: '') ?>">
                                        <?php 
                                        $objeto = $contrato['objeto_servico'] ?: '';
                                        echo htmlspecialchars(strlen($objeto) > 80 ? substr($objeto, 0, 80) . '...' : $objeto); 
                                        ?>
                                    </td>
                                    <td style="font-weight: 600; color: #dc2626;">
                                        <?php 
                                        $valor = $contrato['valor_atual'] ?: $contrato['valor_inicial'] ?: 0;
                                        echo 'R$ ' . number_format($valor, 2, ',', '.');
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(str_replace('_', '-', $contrato['status_contrato'])) ?>">
                                            <?= ucfirst($contrato['status_contrato']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($contrato['nome_empresa']) ?></td>
                                    <td>
                                        <?php if ($contrato['data_fim']): ?>
                                        <?= date('d/m/Y', strtotime($contrato['data_fim'])) ?>
                                        <?php if ($contrato['alerta_vencimento'] !== 'vigente'): ?>
                                        <br><small style="color: <?= $contrato['alerta_vencimento'] === 'vencido' ? '#dc2626' : '#f59e0b' ?>;">
                                            <?php if ($contrato['alerta_vencimento'] === 'vencido'): ?>
                                                Vencido
                                            <?php elseif ($contrato['alerta_vencimento'] === 'vence_30_dias'): ?>
                                                <?= $contrato['dias_para_vencimento'] ?> dias
                                            <?php endif; ?>
                                        </small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        N/I
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <button onclick="verDetalhes(<?= $contrato['id'] ?>)" 
                                                style="background: #3b82f6; color: white; border: none; padding: 6px 8px; border-radius: 4px; cursor: pointer; margin: 0 2px;" 
                                                title="Ver detalhes">
                                            <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                        </button>
                                        <?php if ($podeEditar): ?>
                                        <button onclick="editarContrato(<?= $contrato['id'] ?>)" 
                                                style="background: #f59e0b; color: white; border: none; padding: 6px 8px; border-radius: 4px; cursor: pointer; margin: 0 2px;" 
                                                title="Editar">
                                            <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                                        </button>
                                        <button onclick="excluirContrato(<?= $contrato['id'] ?>)" 
                                                style="background: #ef4444; color: white; border: none; padding: 6px 8px; border-radius: 4px; cursor: pointer; margin: 0 2px;" 
                                                title="Excluir">
                                            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>

                    <!-- Paginação -->
                    <?php if ($totalPaginas > 1): ?>
                    <div class="pagination">
                        <?php
                        $currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
                        $params = $_GET;
                        ?>
                        
                        <?php if ($pagina > 1): ?>
                        <a href="<?= $currentUrl ?>?<?= http_build_query(array_merge($params, ['pagina' => $pagina - 1])) ?>" 
                           class="pagination-btn">
                            <i data-lucide="chevron-left"></i> Anterior
                        </a>
                        <?php endif; ?>

                        <span class="pagination-info">
                            Página <?= $pagina ?> de <?= $totalPaginas ?>
                        </span>

                        <?php if ($pagina < $totalPaginas): ?>
                        <a href="<?= $currentUrl ?>?<?= http_build_query(array_merge($params, ['pagina' => $pagina + 1])) ?>" 
                           class="pagination-btn">
                            Próxima <i data-lucide="chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Histórico de Sincronização -->
                <?php if (!empty($historicoSync)): ?>
                <div class="sync-history-section">
                    <div class="section-header">
                        <h3><i data-lucide="activity"></i> Últimas Sincronizações</h3>
                    </div>
                    <div class="sync-history-list">
                        <?php foreach ($historicoSync as $sync): ?>
                        <div class="sync-item sync-<?= $sync['status'] ?>">
                            <div class="sync-icon">
                                <i data-lucide="<?= $sync['status'] === 'sucesso' ? 'check-circle' : ($sync['status'] === 'erro' ? 'x-circle' : 'clock') ?>"></i>
                            </div>
                            <div class="sync-content">
                                <div class="sync-title">
                                    Sincronização <?= ucfirst($sync['tipo_sync'] ?? 'Geral') ?>
                                </div>
                                <div class="sync-stats">
                                    <?php if ($sync['status'] === 'sucesso'): ?>
                                        <?= $sync['contratos_novos'] ?? 0 ?> novos, 
                                        <?= $sync['contratos_atualizados'] ?? 0 ?> atualizados
                                        <?php if (($sync['contratos_erro'] ?? 0) > 0): ?>
                                            , <?= $sync['contratos_erro'] ?> erros
                                        <?php endif; ?>
                                    <?php elseif ($sync['mensagem']): ?>
                                        <?= htmlspecialchars($sync['mensagem']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="sync-time">
                                <?= date('d/m/Y H:i', strtotime($sync['inicio_sync'])) ?>
                                <?php if ($sync['duracao_segundos']): ?>
                                    <br><small><?= $sync['duracao_segundos'] ?>s</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Criar/Editar Contrato -->
    <div id="modalCriarContrato" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="plus-circle"></i> <span id="tituloModalContrato">Criar Novo Contrato</span>
                </h3>
                <span class="close" onclick="fecharModal('modalCriarContrato')">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Sistema de Abas -->
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button type="button" class="tab-button active" onclick="mostrarAba('dados-basicos')">
                            <i data-lucide="file-text"></i> Dados Básicos
                        </button>
                        <button type="button" class="tab-button" onclick="mostrarAba('valores-datas')">
                            <i data-lucide="calendar"></i> Valores e Datas
                        </button>
                        <button type="button" class="tab-button" onclick="mostrarAba('gestao-controle')">
                            <i data-lucide="users"></i> Gestão e Controle
                        </button>
                    </div>

                    <form id="formContrato">
                        <input type="hidden" id="contratoId" name="id">
                        <input type="hidden" name="acao" value="criar_contrato">

                        <!-- Aba Dados Básicos -->
                        <div class="tab-content active" id="dados-basicos">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Número do Contrato *</label>
                                    <input type="text" name="numero_contrato" id="numero_contrato" required 
                                           placeholder="Número do contrato">
                                </div>

                                <div class="form-group">
                                    <label>Ano do Contrato *</label>
                                    <input type="number" name="ano_contrato" id="ano_contrato" required 
                                           min="2020" max="2030" value="2025">
                                </div>

                                <div class="form-group">
                                    <label>Número SEI</label>
                                    <input type="text" name="numero_sei" id="numero_sei" 
                                           placeholder="Número do processo SEI">
                                </div>

                                <div class="form-group full-width">
                                    <label>Nome da Empresa *</label>
                                    <input type="text" name="nome_empresa" id="nome_empresa" required 
                                           placeholder="Nome completo da empresa contratada">
                                </div>

                                <div class="form-group">
                                    <label>CNPJ/CPF</label>
                                    <input type="text" name="cnpj_cpf" id="cnpj_cpf" 
                                           placeholder="00.000.000/0001-00">
                                </div>

                                <div class="form-group">
                                    <label>Modalidade</label>
                                    <select name="modalidade" id="modalidade">
                                        <option value="">Selecione...</option>
                                        <option value="Pregão Eletrônico">Pregão Eletrônico</option>
                                        <option value="Pregão Presencial">Pregão Presencial</option>
                                        <option value="Concorrência">Concorrência</option>
                                        <option value="Tomada de Preços">Tomada de Preços</option>
                                        <option value="Convite">Convite</option>
                                        <option value="Inexigibilidade">Inexigibilidade</option>
                                        <option value="Dispensa">Dispensa</option>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label>Objeto/Serviço *</label>
                                    <textarea name="objeto_servico" id="objeto_servico" required 
                                              placeholder="Descreva detalhadamente o objeto do contrato" 
                                              rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Aba Valores e Datas -->
                        <div class="tab-content" id="valores-datas">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Valor Inicial</label>
                                    <input type="number" name="valor_inicial" id="valor_inicial" 
                                           step="0.01" min="0" placeholder="0,00">
                                </div>

                                <div class="form-group">
                                    <label>Valor Atual</label>
                                    <input type="number" name="valor_atual" id="valor_atual" 
                                           step="0.01" min="0" placeholder="0,00">
                                </div>

                                <div class="form-group">
                                    <label>Data de Assinatura</label>
                                    <input type="date" name="data_assinatura" id="data_assinatura">
                                </div>

                                <div class="form-group">
                                    <label>Data de Início da Vigência</label>
                                    <input type="date" name="data_inicio" id="data_inicio">
                                </div>

                                <div class="form-group">
                                    <label>Data de Fim da Vigência</label>
                                    <input type="date" name="data_fim" id="data_fim">
                                </div>

                                <div class="form-group">
                                    <label>Status do Contrato</label>
                                    <select name="status_contrato" id="status_contrato">
                                        <option value="ativo">Ativo</option>
                                        <option value="inativo">Inativo</option>
                                        <option value="suspenso">Suspenso</option>
                                        <option value="encerrado">Encerrado</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Aba Gestão e Controle -->
                        <div class="tab-content" id="gestao-controle">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Área Gestora</label>
                                    <input type="text" name="area_gestora" id="area_gestora" 
                                           placeholder="Área responsável">
                                </div>

                                <div class="form-group">
                                    <label>Finalidade</label>
                                    <input type="text" name="finalidade" id="finalidade" 
                                           placeholder="Finalidade do contrato">
                                </div>

                                <div class="form-group full-width">
                                    <label>Fiscais Responsáveis</label>
                                    <textarea name="fiscais" id="fiscais" 
                                              placeholder="Nome dos fiscais responsáveis pelo contrato" 
                                              rows="2"></textarea>
                                </div>

                                <div class="form-group full-width">
                                    <label>Observações</label>
                                    <textarea name="observacoes" id="observacoes" 
                                              placeholder="Observações gerais sobre o contrato" 
                                              rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="save"></i> <span id="btnTextoSalvar">Salvar Contrato</span>
                            </button>
                            <button type="button" onclick="fecharModal('modalCriarContrato')" class="btn btn-secondary">
                                <i data-lucide="x"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes do Contrato -->
    <div id="detalhesModal" class="modal modal-large">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i data-lucide="file-contract"></i> Detalhes do Contrato</h3>
                <button class="modal-close" onclick="closeModal('detalhesModal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body" id="detalhesContent">
                <!-- Conteúdo carregado via AJAX -->
            </div>
        </div>
    </div>

    <!-- Modal de Histórico de Sincronização -->
    <div id="syncModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i data-lucide="activity"></i> Histórico de Sincronização</h3>
                <button class="modal-close" onclick="closeModal('syncModal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body" id="syncContent">
                <!-- Conteúdo carregado via AJAX -->
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/script.js"></script>
    <script src="assets/notifications.js"></script>
    <script src="assets/mobile-improvements.js"></script>
    <script src="assets/ux-improvements.js"></script>
    <script src="assets/contratos-dashboard.js"></script>
    
    <!-- Dados dos Gráficos -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Aguardar Chart.js estar disponível
        if (typeof Chart === 'undefined') {
            console.error('Chart.js não está carregado');
            return;
        }

        // Dados para os gráficos
        const dadosModalidade = <?php echo json_encode($dados_modalidade); ?>;
        const dadosAreaGestora = <?php echo json_encode($dados_area_gestora); ?>;
        const dadosMensal = <?php echo json_encode($dados_mensal); ?>;
        const dadosStats = <?php echo json_encode($stats); ?>;

        // Cores do tema vermelho para os gráficos
        const coresVermelhas = [
            '#dc2626', '#b91c1c', '#991b1b', '#7f1d1d', '#fca5a5',
            '#f87171', '#ef4444', '#fee2e2', '#fecaca', '#dc2626cc'
        ];

        // Gráfico por Modalidade
        if (dadosModalidade && dadosModalidade.length > 0) {
            const ctx1 = document.getElementById('chartModalidade');
            if (ctx1) {
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: dadosModalidade.map(item => item.modalidade),
                        datasets: [{
                            data: dadosModalidade.map(item => parseInt(item.quantidade)),
                            backgroundColor: coresVermelhas.slice(0, dadosModalidade.length),
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
            }
        }

        // Gráfico por Área Gestora
        if (dadosAreaGestora && dadosAreaGestora.length > 0) {
            const ctx2 = document.getElementById('chartAreaGestora');
            if (ctx2) {
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: dadosAreaGestora.map(item => item.area_gestora),
                        datasets: [{
                            label: 'Contratos',
                            data: dadosAreaGestora.map(item => parseInt(item.quantidade)),
                            backgroundColor: '#dc2626',
                            borderColor: '#b91c1c',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        }

        // Gráfico Evolução Mensal
        if (dadosMensal && dadosMensal.length > 0) {
            const ctx3 = document.getElementById('chartMensal');
            if (ctx3) {
                new Chart(ctx3, {
                    type: 'line',
                    data: {
                        labels: dadosMensal.map(item => {
                            const [ano, mes] = item.mes.split('-');
                            return new Date(ano, mes - 1).toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
                        }),
                        datasets: [{
                            label: 'Contratos Assinados',
                            data: dadosMensal.map(item => parseInt(item.quantidade)),
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#dc2626',
                            pointBorderColor: '#b91c1c',
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        }

        // Gráfico Status dos Contratos
        const ctx4 = document.getElementById('chartStatus');
        if (ctx4 && dadosStats) {
            new Chart(ctx4, {
                type: 'doughnut',
                data: {
                    labels: ['Vigentes', 'Encerrados', 'Vencidos'],
                    datasets: [{
                        data: [
                            parseInt(dadosStats.contratos_vigentes || 0),
                            parseInt(dadosStats.contratos_encerrados || 0),
                            parseInt(dadosStats.vencidos || 0)
                        ],
                        backgroundColor: ['#10b981', '#dc2626', '#f59e0b'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }
    });
    </script>
    
</body>
</html>