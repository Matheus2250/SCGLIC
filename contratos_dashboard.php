<?php
/**
 * Dashboard do Módulo de Contratos
 * Sistema CGLIC - Ministério da Saúde
 * 
 * Integração com API Comprasnet (UASG 250110)
 */

require_once 'config.php';
require_once 'functions.php';

// Verificar login
if (!verificarLogin()) {
    header('Location: index.php');
    exit;
}

// Verificar permissões para o módulo de contratos
$nivel = $_SESSION['nivel_acesso'];
$podeEditar = in_array($nivel, [1]); // Apenas Coordenador pode editar inicialmente
$podeVisualizar = in_array($nivel, [1, 2, 3, 4]); // Todos podem visualizar

if (!$podeVisualizar) {
    header('Location: selecao_modulos.php?erro=sem_permissao');
    exit;
}

// Parâmetros de filtro
$filtroStatus = $_GET['status'] ?? '';
$filtroModalidade = $_GET['modalidade'] ?? '';
$filtroVencimento = $_GET['vencimento'] ?? '';
$busca = $_GET['busca'] ?? '';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$limite = 20;
$offset = ($pagina - 1) * $limite;

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

// Buscar contratos (com tratamento de erro caso tabela não exista ainda)
$contratos = [];
$total = 0;
$stats = [];
$alertas = [];
$historicoSync = [];

try {
    // Buscar contratos
    $query = "
        SELECT c.*, 
               COUNT(ca.id) as total_aditivos,
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

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $contratos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Contar total para paginação
    $countQuery = "SELECT COUNT(DISTINCT c.id) as total FROM contratos c WHERE {$whereClause}";
    $stmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Buscar estatísticas do dashboard
    $stats = $conn->query("SELECT * FROM vw_contratos_dashboard")->fetch_assoc() ?? [];

    // Buscar alertas ativos
    $alertas = $conn->query("
        SELECT c.numero_contrato, c.objeto, c.contratado_nome, 
               c.data_fim_vigencia, c.valor_total,
               'vencimento' as tipo_alerta,
               DATEDIFF(c.data_fim_vigencia, CURDATE()) as dias_restantes
        FROM contratos c 
        WHERE c.data_fim_vigencia <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          AND c.status_contrato = 'vigente'
        ORDER BY c.data_fim_vigencia ASC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);

    // Buscar histórico de sincronização
    $historicoSync = $conn->query("
        SELECT * FROM contratos_sync_log 
        ORDER BY inicio_sync DESC 
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    // Tabelas ainda não foram criadas - mostrar mensagem de setup
    $needsSetup = true;
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
    <link rel="stylesheet" href="assets/mobile-improvements.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <i data-lucide="menu"></i>
                </button>
                <h1><i data-lucide="file-text"></i> Contratos</h1>
            </div>
            <div class="header-right">
                <span class="user-info">
                    <i data-lucide="user"></i>
                    <?= htmlspecialchars($_SESSION['nome']) ?>
                    (Nível <?= $_SESSION['nivel_acesso'] ?>)
                </span>
                <a href="logout.php" class="btn-logout">
                    <i data-lucide="log-out"></i> Sair
                </a>
            </div>
        </header>

        <div class="dashboard-content">
            <!-- Sidebar -->
            <aside class="dashboard-sidebar">
                <nav class="sidebar-nav">
                    <a href="selecao_modulos.php" class="nav-item">
                        <i data-lucide="home"></i> Menu Principal
                    </a>
                    <a href="dashboard.php" class="nav-item">
                        <i data-lucide="calendar-check"></i> Planejamento
                    </a>
                    <a href="licitacao_dashboard.php" class="nav-item">
                        <i data-lucide="gavel"></i> Licitações
                    </a>
                    <a href="#" class="nav-item active">
                        <i data-lucide="file-text"></i> Contratos
                    </a>
                    <a href="#configuracao-api" class="nav-item" onclick="showConfigModal()">
                        <i data-lucide="settings"></i> Configuração API
                    </a>
                    <a href="#sincronizacao" class="nav-item" onclick="showSyncModal()">
                        <i data-lucide="refresh-cw"></i> Sincronização
                    </a>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="dashboard-main">
                <?php if (isset($needsSetup)): ?>
                <!-- Setup inicial -->
                <div class="setup-section">
                    <div class="setup-card">
                        <div class="setup-icon">
                            <i data-lucide="database"></i>
                        </div>
                        <div class="setup-content">
                            <h2>Módulo de Contratos - Configuração Inicial</h2>
                            <p>O módulo de Contratos precisa ser configurado. Execute os seguintes passos:</p>
                            <ol>
                                <li>Execute o script SQL: <code>database/modulo_contratos.sql</code></li>
                                <li>Configure as credenciais da API Comprasnet</li>
                                <li>Execute a primeira sincronização</li>
                            </ol>
                            <?php if ($podeEditar): ?>
                            <div class="setup-actions">
                                <button onclick="executarSetup()" class="btn btn-primary">
                                    <i data-lucide="play"></i> Executar Setup
                                </button>
                                <button onclick="showConfigModal()" class="btn btn-secondary">
                                    <i data-lucide="settings"></i> Configurar API
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>

                <!-- Cards de Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i data-lucide="file-text"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['total_contratos'] ?? 0) ?></div>
                            <div class="stat-label">Total de Contratos</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon vigente">
                            <i data-lucide="check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['contratos_vigentes'] ?? 0) ?></div>
                            <div class="stat-label">Contratos Vigentes</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon valor">
                            <i data-lucide="dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">R$ <?= number_format($stats['valor_total_contratos'] ?? 0, 2, ',', '.') ?></div>
                            <div class="stat-label">Valor Total</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon alerta">
                            <i data-lucide="alert-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['vencem_30_dias'] ?? 0) ?></div>
                            <div class="stat-label">Vencem em 30 dias</div>
                        </div>
                    </div>
                </div>

                <!-- Alertas -->
                <?php if (!empty($alertas)): ?>
                <div class="alertas-section">
                    <h3><i data-lucide="bell"></i> Alertas Importantes</h3>
                    <div class="alertas-list">
                        <?php foreach ($alertas as $alerta): ?>
                        <div class="alerta-item <?= $alerta['dias_restantes'] <= 0 ? 'urgente' : 'atencao' ?>">
                            <div class="alerta-icon">
                                <i data-lucide="<?= $alerta['dias_restantes'] <= 0 ? 'alert-circle' : 'clock' ?>"></i>
                            </div>
                            <div class="alerta-content">
                                <div class="alerta-titulo">
                                    Contrato <?= htmlspecialchars($alerta['numero_contrato']) ?>
                                </div>
                                <div class="alerta-descricao">
                                    <?= substr(htmlspecialchars($alerta['objeto']), 0, 80) ?>...
                                </div>
                                <div class="alerta-meta">
                                    Contratado: <?= htmlspecialchars($alerta['contratado_nome']) ?> |
                                    Vencimento: <?= date('d/m/Y', strtotime($alerta['data_fim_vigencia'])) ?>
                                    <?php if ($alerta['dias_restantes'] <= 0): ?>
                                        | <strong>VENCIDO</strong>
                                    <?php else: ?>
                                        | <?= $alerta['dias_restantes'] ?> dias restantes
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="alerta-valor">
                                R$ <?= number_format($alerta['valor_total'], 2, ',', '.') ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <input type="text" name="busca" placeholder="Buscar por número, objeto ou contratado..." 
                                   value="<?= htmlspecialchars($busca) ?>" class="filter-input">
                        </div>
                        
                        <div class="filter-group">
                            <select name="status" class="filter-select">
                                <option value="">Todos os Status</option>
                                <option value="vigente" <?= $filtroStatus === 'vigente' ? 'selected' : '' ?>>Vigente</option>
                                <option value="encerrado" <?= $filtroStatus === 'encerrado' ? 'selected' : '' ?>>Encerrado</option>
                                <option value="suspenso" <?= $filtroStatus === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
                                <option value="rescindido" <?= $filtroStatus === 'rescindido' ? 'selected' : '' ?>>Rescindido</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <select name="vencimento" class="filter-select">
                                <option value="">Prazo de Vencimento</option>
                                <option value="vencidos" <?= $filtroVencimento === 'vencidos' ? 'selected' : '' ?>>Vencidos</option>
                                <option value="30_dias" <?= $filtroVencimento === '30_dias' ? 'selected' : '' ?>>Vencem em 30 dias</option>
                                <option value="90_dias" <?= $filtroVencimento === '90_dias' ? 'selected' : '' ?>>Vencem em 90 dias</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="search"></i> Filtrar
                            </button>
                            <a href="contratos_dashboard.php" class="btn btn-secondary">
                                <i data-lucide="x"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Ações principais -->
                <?php if ($podeEditar): ?>
                <div class="actions-section">
                    <button onclick="sincronizarContratos()" class="btn btn-primary">
                        <i data-lucide="refresh-cw"></i> Sincronizar Agora
                    </button>
                    <button onclick="showConfigModal()" class="btn btn-secondary">
                        <i data-lucide="settings"></i> Configurar API
                    </button>
                    <button onclick="gerarRelatorio()" class="btn btn-info">
                        <i data-lucide="file-down"></i> Relatório
                    </button>
                </div>
                <?php endif; ?>

                <!-- Lista de Contratos -->
                <div class="contratos-section">
                    <div class="section-header">
                        <h3><i data-lucide="list"></i> Lista de Contratos</h3>
                        <div class="section-meta">
                            Mostrando <?= count($contratos) ?> de <?= $total ?> contratos
                        </div>
                    </div>

                    <div class="contratos-table-container">
                        <table class="contratos-table">
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
                                <?php if (empty($contratos)): ?>
                                <tr>
                                    <td colspan="7" class="no-data">
                                        <i data-lucide="inbox"></i>
                                        <p>Nenhum contrato encontrado</p>
                                        <?php if ($podeEditar): ?>
                                        <button onclick="sincronizarContratos()" class="btn btn-primary">
                                            <i data-lucide="refresh-cw"></i> Sincronizar Contratos
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($contratos as $contrato): ?>
                                <tr class="contrato-row" data-id="<?= $contrato['id'] ?>">
                                    <td>
                                        <div class="contrato-numero">
                                            <?= htmlspecialchars($contrato['numero_contrato']) ?>
                                        </div>
                                        <?php if ($contrato['total_aditivos'] > 0): ?>
                                        <div class="contrato-aditivos">
                                            <i data-lucide="plus-circle"></i> <?= $contrato['total_aditivos'] ?> aditivo(s)
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="contrato-objeto" title="<?= htmlspecialchars($contrato['objeto']) ?>">
                                            <?= substr(htmlspecialchars($contrato['objeto']), 0, 80) ?>...
                                        </div>
                                        <div class="contrato-meta">
                                            <?= htmlspecialchars($contrato['modalidade']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contratado-nome">
                                            <?= htmlspecialchars($contrato['contratado_nome']) ?>
                                        </div>
                                        <div class="contratado-cnpj">
                                            <?= formatarCNPJ($contrato['contratado_cnpj']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="valor-total">
                                            R$ <?= number_format($contrato['valor_total'], 2, ',', '.') ?>
                                        </div>
                                        <?php if ($contrato['valor_aditivos'] > 0): ?>
                                        <div class="valor-aditivos">
                                            +R$ <?= number_format($contrato['valor_aditivos'], 2, ',', '.') ?> (aditivos)
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="vigencia-periodo">
                                            <?= date('d/m/Y', strtotime($contrato['data_inicio_vigencia'])) ?> -
                                            <?= date('d/m/Y', strtotime($contrato['data_fim_vigencia'])) ?>
                                        </div>
                                        <?php if ($contrato['alerta_vencimento'] !== 'vigente'): ?>
                                        <div class="vigencia-alerta <?= $contrato['alerta_vencimento'] ?>">
                                            <i data-lucide="alert-triangle"></i>
                                            <?php if ($contrato['alerta_vencimento'] === 'vencido'): ?>
                                                Vencido
                                            <?php elseif ($contrato['alerta_vencimento'] === 'vence_30_dias'): ?>
                                                Vence em breve
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
                                <?php endif; ?>
                            </tbody>
                        </table>
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
                    <h3><i data-lucide="activity"></i> Histórico de Sincronização</h3>
                    <div class="sync-history-list">
                        <?php foreach ($historicoSync as $sync): ?>
                        <div class="sync-item status-<?= $sync['status'] ?>">
                            <div class="sync-icon">
                                <i data-lucide="<?= $sync['status'] === 'sucesso' ? 'check' : ($sync['status'] === 'erro' ? 'x' : 'clock') ?>"></i>
                            </div>
                            <div class="sync-content">
                                <div class="sync-title">
                                    Sincronização <?= ucfirst($sync['tipo_sync']) ?>
                                </div>
                                <div class="sync-stats">
                                    <?php if ($sync['status'] === 'sucesso'): ?>
                                        <?= $sync['contratos_novos'] ?> novos, 
                                        <?= $sync['contratos_atualizados'] ?> atualizados
                                        <?php if ($sync['contratos_erro'] > 0): ?>
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
            </main>
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
                    </div>
                    <div class="form-group">
                        <label for="clientSecret">Client Secret</label>
                        <input type="password" id="clientSecret" name="client_secret" required
                               placeholder="Seu Client Secret da API Comprasnet">
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
                <h3><i data-lucide="file-text"></i> Detalhes do Contrato</h3>
                <button class="modal-close" onclick="closeModal('detalhesModal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body" id="detalhesContent">
                <!-- Conteúdo carregado via AJAX -->
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/script.js"></script>
    <script src="assets/notifications.js"></script>
    <script src="assets/mobile-improvements.js"></script>
    <script src="assets/ux-improvements.js"></script>
    
    <script>
    // Inicializar Lucide icons
    lucide.createIcons();

    // Setup inicial
    async function executarSetup() {
        if (!confirm('Deseja executar o setup do módulo de Contratos? Esta operação criará as tabelas necessárias.')) {
            return;
        }
        
        try {
            showLoading('Executando setup...');
            
            const response = await fetch('api/setup_contratos.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'setup'})
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Setup executado com sucesso!', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Erro no setup: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Erro de conexão: ' + error.message, 'error');
        } finally {
            hideLoading();
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
            showLoading('Autenticando...');
            
            const response = await fetch('api/comprasnet_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Autenticação realizada com sucesso!', 'success');
                closeModal('configModal');
            } else {
                showNotification('Erro na autenticação: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Erro de conexão: ' + error.message, 'error');
        } finally {
            hideLoading();
        }
    });

    // Testar conexão
    async function testarConexao() {
        try {
            showLoading('Testando conexão...');
            
            const response = await fetch('api/comprasnet_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'test_connection'})
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Conexão com a API está funcionando!', 'success');
            } else {
                showNotification('Erro na conexão: ' + result.response.error, 'error');
            }
        } catch (error) {
            showNotification('Erro ao testar conexão: ' + error.message, 'error');
        } finally {
            hideLoading();
        }
    }

    // Sincronizar contratos
    async function sincronizarContratos(tipo = 'incremental') {
        if (!confirm('Deseja iniciar a sincronização de contratos? Esta operação pode demorar alguns minutos.')) {
            return;
        }
        
        try {
            showLoading('Sincronizando contratos...');
            
            const response = await fetch('api/contratos_sync.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({tipo: tipo})
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification(
                    `Sincronização concluída! ${result.stats.novos} novos, ${result.stats.atualizados} atualizados`,
                    'success'
                );
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Erro na sincronização: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Erro de conexão: ' + error.message, 'error');
        } finally {
            hideLoading();
        }
    }

    // Ver detalhes do contrato
    async function verDetalhes(contratoId) {
        try {
            showLoading('Carregando detalhes...');
            
            const response = await fetch(`api/get_contrato_detalhes.php?id=${contratoId}`);
            const html = await response.text();
            
            document.getElementById('detalhesContent').innerHTML = html;
            document.getElementById('detalhesModal').style.display = 'block';
        } catch (error) {
            showNotification('Erro ao carregar detalhes: ' + error.message, 'error');
        } finally {
            hideLoading();
        }
    }

    // Gerar relatório
    function gerarRelatorio() {
        const params = new URLSearchParams(window.location.search);
        params.set('formato', 'pdf');
        window.open(`relatorios/relatorio_contratos.php?${params.toString()}`, '_blank');
    }

    // Utilitários
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function showLoading(message = 'Carregando...') {
        // Implementar loading spinner
        console.log(message);
    }

    function hideLoading() {
        // Remover loading spinner
        console.log('Loading hidden');
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

    // Auto-refresh a cada 5 minutos para alertas
    setInterval(() => {
        // Verificar apenas os alertas sem recarregar a página toda
        fetch('api/get_alertas.php')
            .then(response => response.json())
            .then(data => {
                if (data.alertas && data.alertas.length > 0) {
                    // Atualizar seção de alertas se necessário
                }
            })
            .catch(error => console.log('Erro ao verificar alertas:', error));
    }, 300000); // 5 minutos
    </script>
</body>
</html>