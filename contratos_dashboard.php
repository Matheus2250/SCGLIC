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
$podeEditar = in_array($nivel, [1, 2, 3]); // Coordenador, DIPLAN, DIPLI podem editar
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
    $whereConditions = ["c.uasg = '250110'"];
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
                $whereConditions[] = "c.data_fim_vigencia < CURDATE()";
                break;
            case '30_dias':
                $whereConditions[] = "c.data_fim_vigencia BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                break;
            case '90_dias':
                $whereConditions[] = "c.data_fim_vigencia BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
                break;
        }
    }

    if ($busca) {
        $whereConditions[] = "(c.numero_contrato LIKE ? OR c.objeto LIKE ? OR c.contratado_nome LIKE ?)";
        $params[] = "%{$busca}%";
        $params[] = "%{$busca}%";
        $params[] = "%{$busca}%";
        $types .= 'sss';
    }

    $whereClause = implode(' AND ', $whereConditions);

    try {
        // Buscar contratos
        $query = "
            SELECT c.*, 
                   COALESCE(COUNT(ca.id), 0) as total_aditivos,
                   COALESCE(SUM(ca.valor_aditivo), 0) as valor_aditivos,
                   CASE 
                       WHEN c.data_fim_vigencia <= CURDATE() THEN 'vencido'
                       WHEN c.data_fim_vigencia <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'vence_30_dias'
                       WHEN c.data_fim_vigencia <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'vence_90_dias'
                       ELSE 'vigente'
                   END as alerta_vencimento
            FROM contratos c
            LEFT JOIN contratos_aditivos ca ON c.id = ca.contrato_id
            WHERE {$whereClause}
            GROUP BY c.id
            ORDER BY c.data_assinatura DESC
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
                COUNT(CASE WHEN status_contrato = 'vigente' THEN 1 END) as contratos_vigentes,
                COUNT(CASE WHEN status_contrato = 'encerrado' THEN 1 END) as contratos_encerrados,
                COALESCE(SUM(valor_total), 0) as valor_total_contratos,
                COALESCE(SUM(valor_empenhado), 0) as valor_total_empenhado,
                COALESCE(SUM(valor_pago), 0) as valor_total_pago,
                COUNT(CASE WHEN data_fim_vigencia BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                           AND status_contrato = 'vigente' THEN 1 END) as vencem_30_dias,
                COUNT(CASE WHEN data_fim_vigencia < CURDATE() AND status_contrato = 'vigente' THEN 1 END) as vencidos
            FROM contratos 
            WHERE uasg = '250110'
        ";
        $stmt = $pdo->query($statsQuery);
        if ($stmt) {
            $stats = $stmt->fetch();
        }

        // Buscar alertas ativos
        $alertasQuery = "
            SELECT c.numero_contrato, c.objeto, c.contratado_nome, 
                   c.data_fim_vigencia, c.valor_total,
                   'vencimento' as tipo_alerta,
                   DATEDIFF(c.data_fim_vigencia, CURDATE()) as dias_restantes
            FROM contratos c 
            WHERE c.data_fim_vigencia <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND c.status_contrato = 'vigente'
              AND c.uasg = '250110'
            ORDER BY c.data_fim_vigencia ASC
            LIMIT 10
        ";
        $stmt = $pdo->query($alertasQuery);
        if ($stmt) {
            $alertas = $stmt->fetchAll();
        }

        // Buscar histórico de sincronização
        $syncQuery = "
            SELECT * FROM contratos_sync_log 
            ORDER BY inicio_sync DESC 
            LIMIT 5
        ";
        $stmt = $pdo->query($syncQuery);
        if ($stmt) {
            $historicoSync = $stmt->fetchAll();
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
                    <?php if ($podeEditar): ?>
                    <button class="nav-item" onclick="showConfigModal()">
                        <i data-lucide="settings"></i> <span>Configuração API</span>
                    </button>
                    <button class="nav-item" onclick="sincronizarContratos()">
                        <i data-lucide="refresh-cw"></i> <span>Sincronização</span>
                    </button>
                    <?php endif; ?>
                    <button class="nav-item" onclick="showSection('lista-contratos')">
                        <i data-lucide="list"></i> <span>Lista de Contratos</span>
                    </button>
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
                                <li><strong>Integração com API Comprasnet</strong> - Sincronização automática de contratos da UASG 250110</li>
                                <li><strong>Gestão completa</strong> - Controle de vigências, valores, aditivos e pagamentos</li>
                                <li><strong>Alertas inteligentes</strong> - Notificações de vencimento e irregularidades</li>
                                <li><strong>Relatórios gerenciais</strong> - Análises financeiras e operacionais</li>
                            </ul>
                            
                            <div class="setup-steps">
                                <h3>Passos para configuração:</h3>
                                <ol>
                                    <li>Execute o setup inicial do banco de dados</li>
                                    <li>Configure as credenciais da API Comprasnet</li>
                                    <li>Execute a primeira sincronização de contratos</li>
                                </ol>
                            </div>
                            
                            <?php if ($podeEditar): ?>
                            <div class="setup-actions">
                                <button onclick="executarSetup()" class="btn btn-primary">
                                    <i data-lucide="play"></i> Executar Setup
                                </button>
                                <button onclick="showConfigModal()" class="btn btn-secondary">
                                    <i data-lucide="settings"></i> Configurar API
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
                                <option value="vigente" <?= $filtroStatus === 'vigente' ? 'selected' : '' ?>>Vigente</option>
                                <option value="encerrado" <?= $filtroStatus === 'encerrado' ? 'selected' : '' ?>>Encerrado</option>
                                <option value="suspenso" <?= $filtroStatus === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
                                <option value="rescindido" <?= $filtroStatus === 'rescindido' ? 'selected' : '' ?>>Rescindido</option>
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
                <?php if ($podeEditar): ?>
                <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                    <button onclick="sincronizarContratos()" style="background: #28a745; color: white; padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i data-lucide="refresh-cw" style="width: 16px; height: 16px;"></i> Sincronizar Agora
                    </button>
                    <button onclick="showConfigModal()" style="background: #17a2b8; color: white; padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i data-lucide="settings" style="width: 16px; height: 16px;"></i> Configurar API
                    </button>
                    <button onclick="gerarRelatorio()" style="background: #007bff; color: white; padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i data-lucide="file-down" style="width: 16px; height: 16px;"></i> Relatório
                    </button>
                </div>
                <?php endif; ?>

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
                                    Ainda não há contratos sincronizados do Comprasnet.
                                <?php endif; ?>
                            </p>
                            <?php if ($podeEditar && !$busca && !$filtroStatus && !$filtroModalidade && !$filtroVencimento): ?>
                            <button onclick="sincronizarContratos()" class="btn btn-primary">
                                <i data-lucide="refresh-cw"></i> Sincronizar Contratos
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <table class="contracts-table">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Objeto</th>
                                    <th>Contratado</th>
                                    <th>Valor Total</th>
                                    <th>Vigência</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contratos as $contrato): ?>
                                <tr class="contract-row" data-id="<?= $contrato['id'] ?>">
                                    <td>
                                        <div class="contract-number">
                                            <?= htmlspecialchars($contrato['numero_contrato']) ?>
                                        </div>
                                        <?php if ($contrato['total_aditivos'] > 0): ?>
                                        <div class="contract-additives">
                                            <i data-lucide="plus-circle"></i> <?= $contrato['total_aditivos'] ?> aditivo(s)
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="contract-object" title="<?= htmlspecialchars($contrato['objeto']) ?>">
                                            <?= substr(htmlspecialchars($contrato['objeto']), 0, 80) ?>...
                                        </div>
                                        <div class="contract-meta">
                                            <?= htmlspecialchars($contrato['modalidade'] ?? 'N/I') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contractor-name">
                                            <?= htmlspecialchars($contrato['contratado_nome']) ?>
                                        </div>
                                        <?php if ($contrato['contratado_cnpj']): ?>
                                        <div class="contractor-cnpj">
                                            <?= formatarCNPJ($contrato['contratado_cnpj']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="contract-value">
                                            R$ <?= number_format($contrato['valor_total'], 2, ',', '.') ?>
                                        </div>
                                        <?php if ($contrato['valor_aditivos'] > 0): ?>
                                        <div class="additive-value">
                                            +R$ <?= number_format($contrato['valor_aditivos'], 2, ',', '.') ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="contract-period">
                                            <?= date('d/m/Y', strtotime($contrato['data_inicio_vigencia'])) ?> -
                                            <?= date('d/m/Y', strtotime($contrato['data_fim_vigencia'])) ?>
                                        </div>
                                        <?php if ($contrato['alerta_vencimento'] !== 'vigente'): ?>
                                        <div class="contract-alert <?= $contrato['alerta_vencimento'] ?>">
                                            <i data-lucide="alert-triangle"></i>
                                            <?php if ($contrato['alerta_vencimento'] === 'vencido'): ?>
                                                Vencido
                                            <?php elseif ($contrato['alerta_vencimento'] === 'vence_30_dias'): ?>
                                                Vence em breve
                                            <?php else: ?>
                                                Vencimento próximo
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $contrato['status_contrato'] ?>">
                                            <?= ucfirst($contrato['status_contrato']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions-group">
                                            <button onclick="verDetalhes(<?= $contrato['id'] ?>)" 
                                                    class="btn-icon" title="Ver detalhes">
                                                <i data-lucide="eye"></i>
                                            </button>
                                            <?php if ($contrato['link_comprasnet']): ?>
                                            <a href="<?= htmlspecialchars($contrato['link_comprasnet']) ?>" 
                                               target="_blank" class="btn-icon" title="Ver no Comprasnet">
                                                <i data-lucide="external-link"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
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

    <!-- Modal de Configuração da API -->
    <div id="configModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i data-lucide="settings"></i> Configuração da API Comprasnet</h3>
                <button class="modal-close" onclick="closeModal('configModal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="configForm">
                    <div class="form-group">
                        <label for="clientId">Client ID</label>
                        <input type="text" id="clientId" name="client_id" required
                               placeholder="Seu Client ID da API Comprasnet">
                        <small>Obtido no portal do Comprasnet</small>
                    </div>
                    <div class="form-group">
                        <label for="clientSecret">Client Secret</label>
                        <input type="password" id="clientSecret" name="client_secret" required
                               placeholder="Seu Client Secret da API Comprasnet">
                        <small>Chave secreta para autenticação</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i> Salvar e Autenticar
                        </button>
                        <button type="button" onclick="testarConexao()" class="btn btn-secondary">
                            <i data-lucide="wifi"></i> Testar Conexão
                        </button>
                    </div>
                </form>
                <div id="configResult" class="result-message"></div>
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
    
    <script>
    // Inicializar Lucide icons
    lucide.createIcons();

    // Navegação entre seções
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
        const activeItem = document.querySelector(`[onclick="showSection('${sectionId}')"]`);
        if (activeItem) {
            activeItem.classList.add('active');
        }
    }

    // Toggle sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
    }

    // Setup inicial do módulo
    async function executarSetup() {
        if (!confirm('Deseja executar o setup do módulo de Contratos?\n\nEsta operação irá:\n- Criar as tabelas necessárias\n- Configurar views e índices\n- Preparar o sistema para sincronização')) {
            return;
        }
        
        try {
            showNotification('Executando setup do módulo...', 'info');
            
            const response = await fetch('api/setup_contratos.php', {
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

    // Configuração da API
    function showConfigModal() {
        document.getElementById('configModal').style.display = 'block';
    }

    document.getElementById('configForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = {
            action: 'authenticate',
            client_id: formData.get('client_id'),
            client_secret: formData.get('client_secret')
        };
        
        try {
            showNotification('Validando credenciais...', 'info');
            
            const response = await fetch('api/comprasnet_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Credenciais salvas com sucesso!', 'success');
                closeModal('configModal');
            } else {
                showNotification('Erro na validação: ' + (result.error || 'Credenciais inválidas'), 'error');
            }
        } catch (error) {
            showNotification('Erro de conexão: ' + error.message, 'error');
        }
    });

    // Testar conexão
    async function testarConexao() {
        try {
            showNotification('Testando conexão com API...', 'info');
            
            const response = await fetch('api/comprasnet_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'test_connection'})
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Conexão com API funcionando!', 'success');
            } else {
                showNotification('Erro na conexão: ' + (result.error || 'Falha na comunicação'), 'error');
            }
        } catch (error) {
            showNotification('Erro ao testar conexão: ' + error.message, 'error');
        }
    }

    // Sincronizar contratos
    async function sincronizarContratos(tipo = 'incremental') {
        if (!confirm('Deseja iniciar a sincronização de contratos?\n\nTipo: ' + (tipo === 'completa' ? 'Sincronização completa' : 'Sincronização incremental') + '\n\nEsta operação pode demorar alguns minutos.')) {
            return;
        }
        
        try {
            showNotification('Iniciando sincronização de contratos...', 'info');
            
            const response = await fetch('api/contratos_sync.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({tipo: tipo})
            });
            
            const result = await response.json();
            
            if (result.success) {
                const stats = result.stats || {};
                showNotification(
                    `Sincronização concluída!\n${stats.novos || 0} novos contratos\n${stats.atualizados || 0} contratos atualizados`, 
                    'success'
                );
                setTimeout(() => location.reload(), 3000);
            } else {
                showNotification('Erro na sincronização: ' + (result.error || 'Falha na operação'), 'error');
            }
        } catch (error) {
            showNotification('Erro de conexão: ' + error.message, 'error');
        }
    }

    // Ver detalhes do contrato
    async function verDetalhes(contratoId) {
        try {
            showNotification('Carregando detalhes do contrato...', 'info');
            
            const response = await fetch(`api/get_contrato_detalhes.php?id=${contratoId}`);
            
            if (!response.ok) {
                throw new Error('Erro ao carregar detalhes');
            }
            
            const html = await response.text();
            
            document.getElementById('detalhesContent').innerHTML = html;
            document.getElementById('detalhesModal').style.display = 'block';
            
            // Reinicializar ícones Lucide no modal
            lucide.createIcons();
            
        } catch (error) {
            showNotification('Erro ao carregar detalhes: ' + error.message, 'error');
        }
    }

    // Mostrar histórico de sincronização
    function showSyncModal() {
        document.getElementById('syncModal').style.display = 'block';
        // Carregar histórico via AJAX se necessário
    }

    // Gerar relatório
    function gerarRelatorio(tipo = 'geral') {
        const params = new URLSearchParams(window.location.search);
        params.set('relatorio', tipo);
        params.set('formato', 'pdf');
        
        const url = `relatorios/relatorio_contratos.php?${params.toString()}`;
        window.open(url, '_blank');
    }

    // Utilitários de modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Fechar modais clicando fora
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

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
                case 'r':
                    e.preventDefault();
                    sincronizarContratos();
                    break;
                case 'f':
                    e.preventDefault();
                    document.querySelector('input[name="busca"]').focus();
                    break;
            }
        }
    });
    </script>
</body>
</html>