<?php
require_once 'config.php';
require_once 'functions.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

verificarLogin();

$pdo = conectarDB();

// Buscar estatísticas para os cards e gráficos
$stats_sql = "SELECT
    COUNT(*) as total_licitacoes,
    COUNT(CASE WHEN situacao = 'EM_ANDAMENTO' THEN 1 END) as em_andamento,
    COUNT(CASE WHEN situacao = 'HOMOLOGADO' THEN 1 END) as homologadas,
    COUNT(CASE WHEN situacao = 'FRACASSADO' THEN 1 END) as fracassadas,
    COUNT(CASE WHEN situacao = 'REVOGADO' THEN 1 END) as revogadas,
    SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN valor_estimado ELSE 0 END) as valor_homologado
    FROM licitacoes";

$stats = $pdo->query($stats_sql)->fetch();

// Dados para gráficos
$dados_modalidade = $pdo->query("
    SELECT modalidade, COUNT(*) as quantidade
    FROM licitacoes
    GROUP BY modalidade
")->fetchAll();

$dados_pregoeiro = $pdo->query("
    SELECT
        CASE
            WHEN l.pregoeiro IS NULL OR l.pregoeiro = '' THEN 'Não Definido'
            ELSE l.pregoeiro
        END AS pregoeiro,
        COUNT(*) AS quantidade
    FROM licitacoes l
    GROUP BY l.pregoeiro
    ORDER BY quantidade DESC
    LIMIT 5
")->fetchAll();

$dados_mensal = $pdo->query("
    SELECT
        DATE_FORMAT(
            COALESCE(data_abertura, criado_em),
            '%Y-%m'
        ) as mes,
        COUNT(*) as quantidade,
        SUM(CASE WHEN data_abertura IS NOT NULL THEN 1 ELSE 0 END) as com_data_abertura,
        SUM(CASE WHEN data_abertura IS NULL THEN 1 ELSE 0 END) as sem_data_abertura
    FROM licitacoes
    WHERE (data_abertura IS NOT NULL AND data_abertura >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH))
    OR (data_abertura IS NULL AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH))
    GROUP BY DATE_FORMAT(
        COALESCE(data_abertura, criado_em),
        '%Y-%m'
    )
    ORDER BY mes
")->fetchAll();

// Configuração da paginação
$licitacoes_por_pagina = isset($_GET['por_pagina']) ? max(10, min(100, intval($_GET['por_pagina']))) : 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_atual - 1) * $licitacoes_por_pagina;

// Filtros opcionais
$filtro_situacao = $_GET['situacao_filtro'] ?? '';
$filtro_busca = $_GET['busca'] ?? '';

// Construir WHERE clause para filtros
$where_conditions = ['1=1'];
$params = [];

if (!empty($filtro_situacao)) {
    $where_conditions[] = "l.situacao = ?";
    $params[] = $filtro_situacao;
}

if (!empty($filtro_busca)) {
    $where_conditions[] = "(l.nup LIKE ? OR l.objeto LIKE ? OR l.pregoeiro LIKE ? OR COALESCE(l.numero_contratacao, p.numero_contratacao) LIKE ?)";
    $busca_param = "%$filtro_busca%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Contar total de licitações (para paginação)
$sql_count = "SELECT COUNT(*) as total 
              FROM licitacoes l 
              LEFT JOIN usuarios u ON l.usuario_id = u.id
              LEFT JOIN pca_dados p ON l.pca_dados_id = p.id
              WHERE $where_clause";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_licitacoes = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_licitacoes / $licitacoes_por_pagina);

// Buscar licitações da página atual
$sql = "SELECT 
            l.*, 
            u.nome as usuario_criador_nome,
            COALESCE(l.numero_contratacao, p.numero_contratacao) as numero_contratacao_final
        FROM licitacoes l 
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        LEFT JOIN pca_dados p ON l.pca_dados_id = p.id
        WHERE $where_clause
        ORDER BY l.criado_em DESC
        LIMIT $licitacoes_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$licitacoes_recentes = $stmt->fetchAll();

// Buscar contratações disponíveis do PCA para o dropdown - dos anos atuais (2025-2026)
$contratacoes_pca = $pdo->query("
    SELECT DISTINCT
        p.numero_contratacao,
        p.numero_dfd,
        p.titulo_contratacao,
        p.area_requisitante,
        p.valor_total_contratacao,
        pi.ano_pca
    FROM pca_dados p
    INNER JOIN pca_importacoes pi ON p.importacao_id = pi.id
    WHERE p.numero_contratacao IS NOT NULL
    AND p.numero_contratacao != ''
    AND TRIM(p.numero_contratacao) != ''
    AND pi.ano_pca IN (2025, 2026)
    ORDER BY p.numero_contratacao DESC
    LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

// Debug básico
echo "<script>console.log('Sistema carregado - Contratações disponíveis:', " . count($contratacoes_pca) . ");</script>";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Licitações - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/licitacao-dashboard.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/licitacao-dashboard.js"></script>

    <style>
    /* Garantir que modais funcionem */
    .modal {
        display: none !important;
        position: fixed !important;
        z-index: 1000 !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background-color: rgba(0,0,0,0.5) !important;
    }
    
    .modal.show {
        display: block !important;
    }
    
    /* Animação de spinner */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .search-input {
        width: 100% !important;
        padding: 12px 16px !important;
        border: 2px solid #e5e7eb !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        font-family: inherit !important;
        transition: all 0.2s ease !important;
        background: white !important;
        color: #374151 !important;
        outline: none !important;
    }

    .search-input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        transform: translateY(-1px) !important;
    }

    .search-input:hover {
        border-color: #d1d5db !important;
    }

    .search-suggestions {
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        right: 0 !important;
        background: white !important;
        border: 2px solid #e5e7eb !important;
        border-top: none !important;
        border-radius: 0 0 8px 8px !important;
        max-height: 280px !important;
        overflow-y: auto !important;
        z-index: 1000 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        margin-top: -1px !important;
    }

    .suggestion-item {
        padding: 12px 16px !important;
        border-bottom: 1px solid #f3f4f6 !important;
        cursor: pointer !important;
        transition: background 0.15s ease !important;
        font-size: 14px !important;
    }

    .suggestion-item:hover {
        background: #f8fafc !important;
    }

    .suggestion-item:last-child {
        border-bottom: none !important;
    }

    .suggestion-numero {
        font-weight: 600 !important;
        color: #1f2937 !important;
        margin-bottom: 4px !important;
    }

    .suggestion-titulo {
        font-size: 12px !important;
        color: #6b7280 !important;
        line-height: 1.4 !important;
    }

    .no-results {
        padding: 16px !important;
        text-align: center !important;
        color: #9ca3af !important;
        font-style: italic !important;
        font-size: 14px !important;
    }
        /* Estilos para detalhes */
        .detalhes-licitacao {
            font-family: inherit;
        }
        
        .detail-section {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detail-section h4 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 8px;
        }
        
        .detail-section p {
            margin: 8px 0;
            color: #6c757d;
            line-height: 1.5;
        }
        
        .detail-section strong {
            color: #495057;
            font-weight: 600;
        }
        
        /* Estilos para paginação */
        .pagination {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #495057;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .page-link:hover {
            background: #e9ecef;
            border-color: #adb5bd;
            color: #495057;
            text-decoration: none;
        }
        
        .page-link.active {
            background: #007cba;
            border-color: #007cba;
            color: white;
        }
        
        .page-link.active:hover {
            background: #006ba6;
            border-color: #006ba6;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i data-lucide="gavel"></i> Licitações</h2>
            </div>

            <nav class="sidebar-nav">
    <div class="nav-section">
        <div class="nav-section-title">Visão Geral</div>
        <button class="nav-item active" onclick="showSection('dashboard')">
            <i data-lucide="bar-chart-3"></i> Dashboard
        </button>
    </div>

    <div class="nav-section">
        <div class="nav-section-title">Gerenciar</div>
        <button class="nav-item" onclick="showSection('lista-licitacoes')">
            <i data-lucide="list"></i> Lista de Licitações
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

    <?php if (temPermissao('licitacao_relatorios')): ?>
    <div class="nav-section">
        <div class="nav-section-title">Relatórios</div>
        <button class="nav-item" onclick="showSection('relatorios')">
            <i data-lucide="file-text"></i> Relatórios
        </button>
    </div>
    <?php endif; ?>

    <div class="nav-section">
        <div class="nav-section-title">Navegação</div>
        <a href="dashboard.php" class="nav-item">
            <i data-lucide="calendar"></i> Dashboard Planejamento
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-section-title">Sistema</div>
        <a href="selecao_modulos.php" class="nav-item">
            <i data-lucide="arrow-left"></i> Voltar ao Menu
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
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i data-lucide="log-out"></i> Sair
                </button>
            </div>
        </div>

        <main class="main-content">
            <?php echo getMensagem(); ?>

            <div id="dashboard" class="content-section active">
                <div class="dashboard-header">
                    <h1><i data-lucide="bar-chart-3"></i> Dashboard de Licitações</h1>
                    <p>Visão geral do processo licitatório e indicadores de desempenho</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-number"><?php echo number_format($stats['total_licitacoes'] ?? 0); ?></div>
                        <div class="stat-label">Total de Licitações</div>
                    </div>
                    <div class="stat-card andamento">
                        <div class="stat-number"><?php echo $stats['em_andamento'] ?? 0; ?></div>
                        <div class="stat-label">Em Andamento</div>
                    </div>
                    <div class="stat-card homologadas">
                        <div class="stat-number"><?php echo $stats['homologadas'] ?? 0; ?></div>
                        <div class="stat-label">Homologadas</div>
                    </div>
                    <div class="stat-card fracassadas">
                        <div class="stat-number"><?php echo $stats['fracassadas'] ?? 0; ?></div>
                        <div class="stat-label">Fracassadas</div>
                    </div>
                    <div class="stat-card valor">
                        <div class="stat-number"><?php echo abreviarValor($stats['valor_homologado'] ?? 0); ?></div>
                        <div class="stat-label">Valor Homologado</div>
                    </div>
                </div>

                <div class="charts-grid">
    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="pie-chart"></i> Licitações por Modalidade</h3>
        <div class="chart-container">
            <canvas id="chartModalidade"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="users"></i> Licitações por Pregoeiro</h3>
        <div class="chart-container">
            <canvas id="chartPregoeiro"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="trending-up"></i> Evolução Mensal</h3>
        <div class="chart-container">
            <canvas id="chartMensal"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="activity"></i> Status das Licitações</h3>
        <div class="chart-container">
            <canvas id="chartStatus"></canvas>
        </div>
    </div>
</div>
            </div>

            <div id="lista-licitacoes" class="content-section">
    <div class="dashboard-header">
        <h1><i data-lucide="list"></i> Lista de Licitações</h1>
        <p>Visualize e gerencie todas as licitações cadastradas</p>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h3 class="table-title">Todas as Licitações</h3>
            <div class="table-filters">
                <?php if (temPermissao('licitacao_criar')): ?>
                <button onclick="abrirModalCriarLicitacao()" class="btn-primary" style="margin-right: 10px;">
                    <i data-lucide="plus-circle"></i> Nova Licitação
                </button>
                <?php endif; ?>
                <?php if (temPermissao('licitacao_exportar')): ?>
                <button onclick="exportarLicitacoes()" class="btn-primary">
                    <i data-lucide="download"></i> Exportar
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtros e Busca -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Buscar</label>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($filtro_busca); ?>" 
                           placeholder="NUP, objeto, pregoeiro ou nº contratação..." 
                           style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Situação</label>
                    <select name="situacao_filtro" style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px;">
                        <option value="">Todas as Situações</option>
                        <option value="EM_ANDAMENTO" <?php echo $filtro_situacao === 'EM_ANDAMENTO' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="HOMOLOGADO" <?php echo $filtro_situacao === 'HOMOLOGADO' ? 'selected' : ''; ?>>Homologadas</option>
                        <option value="FRACASSADO" <?php echo $filtro_situacao === 'FRACASSADO' ? 'selected' : ''; ?>>Fracassadas</option>
                        <option value="REVOGADO" <?php echo $filtro_situacao === 'REVOGADO' ? 'selected' : ''; ?>>Revogadas</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Por página</label>
                    <select name="por_pagina" onchange="this.form.submit()" style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px;">
                        <option value="10" <?php echo $licitacoes_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $licitacoes_por_pagina == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $licitacoes_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $licitacoes_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary" style="padding: 8px 16px;">
                        <i data-lucide="search"></i> Filtrar
                    </button>
                    <a href="licitacao_dashboard.php" class="btn-secondary" style="padding: 8px 16px; text-decoration: none;">
                        <i data-lucide="x"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <?php if (empty($licitacoes_recentes)): ?>
            <div style="text-align: center; padding: 60px; color: #7f8c8d;">
                <i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: 20px;"></i>
                <h3 style="margin: 0 0 10px 0;">Nenhuma licitação encontrada</h3>
                <p style="margin: 0;">Comece criando sua primeira licitação.</p>
                <?php if (temPermissao('licitacao_criar')): ?>
                <button onclick="abrirModalCriarLicitacao()" class="btn-primary" style="margin-top: 20px;">
                    <i data-lucide="plus-circle"></i> Criar Primeira Licitação
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table>
<thead>
<tr>
<th>NUP</th>
<th>Número da contratação</th>
<th>Modalidade</th>
<th>Objeto</th>
<th>Valor Estimado</th>
<th>Situação</th>
<th>Pregoeiro</th>
<th>Data Abertura</th>
<th>Ações</th>
</tr>
</thead>
<tbody>
<?php foreach ($licitacoes_recentes as $licitacao): ?>
<tr>
<td>
<a href="#" onclick="verDetalhes(<?php echo $licitacao['id']; ?>); return false;" title="Clique para ver os detalhes">
<strong><?php echo htmlspecialchars($licitacao['nup']); ?></strong>
</a>
</td>
<td><?php echo htmlspecialchars($licitacao['numero_contratacao_final'] ?? $licitacao['numero_contratacao'] ?? 'N/A'); ?></td>
 
                        <td><span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($licitacao['modalidade']); ?></span></td>
<td title="<?php echo htmlspecialchars($licitacao['objeto'] ?? ''); ?>">
<?php 
                            $objeto = $licitacao['objeto'] ?? '';
                            echo htmlspecialchars(strlen($objeto) > 80 ? substr($objeto, 0, 80) . '...' : $objeto); 
                            ?>
</td>
<td style="font-weight: 600; color: #27ae60;"><?php echo formatarMoeda($licitacao['valor_estimado'] ?? 0); ?></td>
<td>
<span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $licitacao['situacao'])); ?>">
<?php echo str_replace('_', ' ', $licitacao['situacao']); ?>
</span>
</td>
<td><?php echo htmlspecialchars($licitacao['pregoeiro'] ?: 'Não Definido'); ?></td>
<td><?php echo $licitacao['data_abertura'] ? formatarData($licitacao['data_abertura']) : '-'; ?></td>
<td>
<div style="display: flex; gap: 5px; flex-wrap: wrap;">
<?php if (temPermissao('licitacao_editar')): ?>
<button onclick="editarLicitacao(<?php echo $licitacao['id']; ?>)" title="Editar" style="background: #f39c12; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="edit" style="width: 14px; height: 14px;"></i>
</button>
<button onclick="abrirModalImportarAndamentos('<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Importar Andamentos" style="background: #3498db; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="upload" style="width: 14px; height: 14px;"></i>
</button>
<button onclick="consultarAndamentos('<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Ver Andamentos" style="background: #27ae60; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="clock" style="width: 14px; height: 14px;"></i>
</button>
<?php else: ?>
<button onclick="consultarAndamentos('<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Ver Andamentos" style="background: #27ae60; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="clock" style="width: 14px; height: 14px;"></i>
</button>
<span style="color: #7f8c8d; font-size: 12px; font-style: italic;">Somente leitura</span>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

            <!-- Informações de Paginação -->
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div style="color: #7f8c8d; font-size: 14px;">
                    <?php 
                    $inicio = ($pagina_atual - 1) * $licitacoes_por_pagina + 1;
                    $fim = min($pagina_atual * $licitacoes_por_pagina, $total_licitacoes);
                    ?>
                    Mostrando <?php echo $inicio; ?> a <?php echo $fim; ?> de <?php echo $total_licitacoes; ?> licitações<br>
                    Valor total estimado (página atual): <?php echo formatarMoeda(array_sum(array_column($licitacoes_recentes, 'valor_estimado'))); ?>
                </div>
                
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php
                    // Construir URL base preservando filtros
                    $url_params = [];
                    if (!empty($filtro_busca)) $url_params['busca'] = $filtro_busca;
                    if (!empty($filtro_situacao)) $url_params['situacao_filtro'] = $filtro_situacao;
                    if ($licitacoes_por_pagina != 10) $url_params['por_pagina'] = $licitacoes_por_pagina;
                    $url_base = 'licitacao_dashboard.php?' . http_build_query($url_params);
                    $url_base .= empty($url_params) ? '?' : '&';
                    ?>
                    
                    <!-- Primeira página -->
                    <?php if ($pagina_atual > 1): ?>
                        <a href="<?php echo $url_base; ?>pagina=1" class="page-link">
                            <i data-lucide="chevrons-left"></i>
                        </a>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_atual - 1; ?>" class="page-link">
                            <i data-lucide="chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Páginas numeradas -->
                    <?php
                    $inicio_pag = max(1, $pagina_atual - 2);
                    $fim_pag = min($total_paginas, $pagina_atual + 2);
                    
                    for ($i = $inicio_pag; $i <= $fim_pag; $i++):
                    ?>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $i; ?>" 
                           class="page-link <?php echo $i == $pagina_atual ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Última página -->
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_atual + 1; ?>" class="page-link">
                            <i data-lucide="chevron-right"></i>
                        </a>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $total_paginas; ?>" class="page-link">
                            <i data-lucide="chevrons-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="modalCriarLicitacao" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="plus-circle"></i> Criar Nova Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalCriarLicitacao')">&times;</span>
        </div>
        <div class="modal-body">
            <form action="process.php" method="POST">
                <input type="hidden" name="acao" value="criar_licitacao">
                <?php echo getCSRFInput(); ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>NUP *</label>
                        <input type="text" name="nup" id="nup_criar" required placeholder="xxxxx.xxxxxx/xxxx-xx" maxlength="20">
                    </div>

                    <div class="form-group">
                        <label>Data Entrada DIPLI</label>
                        <input type="date" name="data_entrada_dipli">
                    </div>

                    <div class="form-group">
                        <label>Responsável Instrução</label>
                        <input type="text" name="resp_instrucao">
                    </div>

                    <div class="form-group">
                        <label>Área Demandante</label>
                        <input type="text" name="area_demandante" id="area_demandante_criar">
                    </div>

                    <div class="form-group">
                        <label>Pregoeiro</label>
                        <input type="text" name="pregoeiro">
                    </div>

                    <div class="form-group">
                        <label>Modalidade *</label>
                        <select name="modalidade" required>
                            <option value="DISPENSA">DISPENSA</option>
                            <option value="PREGAO">PREGÃO</option>
                            <option value="RDC">RDC</option>
                            <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="tipo" required>
                            <option value="TRADICIONAL">TRADICIONAL</option>
                            <option value="COTACAO">COTAÇÃO</option>
                            <option value="SRP">SRP</option>
                        </select>
                    </div>

                    <div class="form-group">
    <label>Número da Contratação *</label>
    <div class="search-container" style="position: relative;">
        <input
            type="text"
            name="numero_contratacao"
            id="input_contratacao"
            required
            placeholder="Digite o número da contratação..."
            autocomplete="off"
            class="search-input"
            oninput="pesquisarContratacaoInline(this.value)"
            onfocus="mostrarSugestoesInline()"
            onblur="ocultarSugestoesInline()"
        >
        <div id="sugestoes_contratacao" class="search-suggestions" style="display: none;">
            </div>
    </div>

<input type="hidden" id="numero_dfd_selecionado" name="numero_dfd">
<input type="hidden" id="titulo_contratacao_selecionado" name="titulo_contratacao">

    <small style="color: #6b7280; font-size: 12px;">
        Digite o número da contratação ou parte do título para pesquisar
    </small>
</div>

                    <div class="form-group">
                        <label>Ano</label>
                        <input type="number" name="ano" value="<?php echo date('Y'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Valor Estimado (R$)</label>
                        <input type="text" name="valor_estimado" id="valor_estimado_criar" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Data Abertura</label>
                        <input type="date" name="data_abertura">
                    </div>

                    <div class="form-group">
                        <label>Data Homologação</label>
                        <input type="date" name="data_homologacao" id="data_homologacao_criar">
                    </div>

                    <div class="form-group">
                        <label>Valor Homologado (R$)</label>
                        <input type="text" name="valor_homologado" id="valor_homologado_criar" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Economia (R$)</label>
                        <input type="text" name="economia" id="economia_criar" placeholder="0,00" readonly style="background: #f8f9fa;">
                    </div>

                    <div class="form-group">
                        <label>Link</label>
                        <input type="url" name="link" placeholder="https://...">
                    </div>

                    <div class="form-group">
                        <label>Situação *</label>
                        <select name="situacao" required>
                            <option value="EM_ANDAMENTO">EM ANDAMENTO</option>
                            <option value="REVOGADO">REVOGADO</option>
                            <option value="FRACASSADO">FRACASSADO</option>
                            <option value="HOMOLOGADO">HOMOLOGADO</option>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label>Objeto *</label>
                        <textarea name="objeto" id="objeto_textarea" required rows="3" placeholder="Descreva o objeto da licitação..."></textarea>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalCriarLicitacao')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i data-lucide="refresh-cw"></i> Limpar Formulário
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="check"></i> Criar Licitação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

            <div id="relatorios" class="content-section">
    <div class="dashboard-header">
        <h1><i data-lucide="file-text"></i> Relatórios</h1>
        <p>Relatórios detalhados sobre o processo licitatório</p>
    </div>

    <div class="stats-grid">
        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('modalidade')">
            <h3 class="chart-title"><i data-lucide="pie-chart"></i> Relatório por Modalidade</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Análise detalhada das licitações por modalidade</p>
            <div style="text-align: center;">
                <i data-lucide="bar-chart-3" style="width: 64px; height: 64px; color: #e74c3c; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>

        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('pregoeiro')">
            <h3 class="chart-title"><i data-lucide="users"></i> Relatório por Pregoeiro</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Performance e distribuição por pregoeiro</p>
            <div style="text-align: center;">
                <i data-lucide="user-check" style="width: 64px; height: 64px; color: #3498db; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>

        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('prazos')">
            <h3 class="chart-title"><i data-lucide="clock"></i> Relatório de Prazos</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Análise de cumprimento de prazos</p>
            <div style="text-align: center;">
                <i data-lucide="calendar-check" style="width: 64px; height: 64px; color: #f39c12; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>

        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('financeiro')">
            <h3 class="chart-title"><i data-lucide="trending-up"></i> Relatório Financeiro</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Valores estimados vs homologados</p>
            <div style="text-align: center;">
                <i data-lucide="dollar-sign" style="width: 64px; height: 64px; color: #27ae60; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>
    </div>
</div>

<div id="modalRelatorio" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="file-text"></i> <span id="tituloRelatorio">Configurar Relatório</span>
            </h3>
            <span class="close" onclick="fecharModal('modalRelatorio')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formRelatorio">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="tipo_relatorio" name="tipo">

                <div class="form-group">
                    <label>Período</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Inicial</label>
                            <input type="date" name="data_inicial" id="rel_data_inicial">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Final</label>
                            <input type="date" name="data_final" id="rel_data_final" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group" id="filtroModalidade" style="display: none;">
                    <label>Modalidade</label>
                    <select name="modalidade" id="rel_modalidade">
                        <option value="">Todas</option>
                        <option value="DISPENSA">Dispensa</option>
                        <option value="PREGAO">Pregão</option>
                        <option value="RDC">RDC</option>
                        <option value="INEXIBILIDADE">Inexibilidade</option>
                    </select>
                </div>

                <div class="form-group" id="filtroPregoeiro" style="display: none;">
                    <label>Pregoeiro</label>
                    <select name="pregoeiro" id="rel_pregoeiro">
                        <option value="">Todos</option>
                        <?php
                        // Buscar pregoeiros únicos
                        $pregoeiros = $pdo->query("SELECT DISTINCT pregoeiro FROM licitacoes WHERE pregoeiro IS NOT NULL AND pregoeiro != '' ORDER BY pregoeiro")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($pregoeiros as $preg): ?>
                            <option value="<?php echo htmlspecialchars($preg); ?>"><?php echo htmlspecialchars($preg); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="filtroSituacao">
                    <label>Situação</label>
                    <select name="situacao" id="rel_situacao">
                        <option value="">Todas</option>
                        <option value="EM_ANDAMENTO">Em Andamento</option>
                        <option value="HOMOLOGADO">Homologado</option>
                        <option value="FRACASSADO">Fracassado</option>
                        <option value="REVOGADO">Revogado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Formato de Saída</label>
                    <select name="formato" id="rel_formato" required>
                        <option value="html">Visualizar (HTML)</option>
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="incluir_graficos" id="rel_graficos" checked>
                        Incluir gráficos no relatório
                    </label>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalRelatorio')" class="btn-secondary">
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

        </div>
    </div>

    <div id="modalDetalhes" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="file-text"></i> Detalhes da Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalDetalhes')">&times;</span>
        </div>
        <div class="modal-body" id="detalhesContent">
            </div>
    </div>
</div>

<div id="modalEdicao" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="edit"></i> Editar Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalEdicao')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formEditarLicitacao">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="edit_id" name="id">
                <input type="hidden" name="acao" value="editar_licitacao">

                <div class="form-grid">
                    <div class="form-group">
                        <label>NUP *</label>
                        <input type="text" name="nup" id="edit_nup" required placeholder="xxxxx.xxxxxx/xxxx-xx" maxlength="20">
                    </div>

                    <div class="form-group">
                        <label>Data Entrada DIPLI</label>
                        <input type="date" name="data_entrada_dipli" id="edit_data_entrada_dipli">
                    </div>

                    <div class="form-group">
                        <label>Responsável Instrução</label>
                        <input type="text" name="resp_instrucao" id="edit_resp_instrucao">
                    </div>

                    <div class="form-group">
                        <label>Área Demandante</label>
                        <input type="text" name="area_demandante" id="edit_area_demandante">
                    </div>

                    <div class="form-group">
                        <label>Pregoeiro</label>
                        <input type="text" name="pregoeiro" id="edit_pregoeiro">
                    </div>

                    <div class="form-group">
                        <label>Modalidade *</label>
                        <select name="modalidade" id="edit_modalidade" required>
                            <option value="">Selecione</option>
                            <option value="DISPENSA">DISPENSA</option>
                            <option value="PREGAO">PREGÃO</option>
                            <option value="RDC">RDC</option>
                            <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="tipo" id="edit_tipo" required>
                            <option value="">Selecione</option>
                            <option value="TRADICIONAL">TRADICIONAL</option>
                            <option value="COTACAO">COTAÇÃO</option>
                            <option value="SRP">SRP</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Número da Contratação *</label>
                        <div class="search-container" style="position: relative;">
                            <input
                                type="text"
                                name="numero_contratacao"
                                id="edit_input_contratacao"
                                required
                                placeholder="Digite o número da contratação..."
                                autocomplete="off"
                                class="search-input"
                                oninput="pesquisarContratacaoInlineEdit(this.value)"
                                onfocus="mostrarSugestoesInlineEdit()"
                                onblur="ocultarSugestoesInlineEdit()"
                            >
                            <div id="edit_sugestoes_contratacao" class="search-suggestions" style="display: none;">
                                </div>
                        </div>

                        <input type="hidden" id="edit_numero_dfd_selecionado" name="numero_dfd">
                        <input type="hidden" id="edit_titulo_contratacao_selecionado" name="titulo_contratacao">

                        <small style="color: #6b7280; font-size: 12px;">
                            Digite o número da contratação ou parte do título para pesquisar
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Ano</label>
                        <input type="number" name="ano" id="edit_ano" value="<?php echo date('Y'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Valor Estimado (R$)</label>
                        <input type="text" name="valor_estimado" id="edit_valor_estimado" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Data Abertura</label>
                        <input type="date" name="data_abertura" id="edit_data_abertura">
                    </div>

                    <div class="form-group">
                        <label>Data Homologação</label>
                        <input type="date" name="data_homologacao" id="edit_data_homologacao">
                    </div>

                    <div class="form-group">
                        <label>Valor Homologado (R$)</label>
                        <input type="text" name="valor_homologado" id="edit_valor_homologado" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Economia (R$)</label>
                        <input type="text" name="economia" id="edit_economia" placeholder="0,00" readonly style="background: #f8f9fa;">
                    </div>

                    <div class="form-group">
                        <label>Link</label>
                        <input type="url" name="link" id="edit_link" placeholder="https://...">
                    </div>

                    <div class="form-group">
                        <label>Situação *</label>
                        <select name="situacao" id="edit_situacao" required>
                            <option value="EM_ANDAMENTO">EM ANDAMENTO</option>
                            <option value="REVOGADO">REVOGADO</option>
                            <option value="FRACASSADO">FRACASSADO</option>
                            <option value="HOMOLOGADO">HOMOLOGADO</option>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label>Objeto *</label>
                        <textarea name="objeto" id="edit_objeto" required rows="3" placeholder="Descreva o objeto da licitação..."></textarea>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalEdicao')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i data-lucide="refresh-cw"></i> Restaurar Valores
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalExportar" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="download"></i> Exportar Dados
            </h3>
            <span class="close" onclick="fecharModal('modalExportar')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formExportar">
                <?php echo getCSRFInput(); ?>
                <div class="form-group">
                    <label>Formato de Exportação</label>
                    <select id="formato_export" name="formato" required>
                        <option value="csv">CSV (Excel)</option>
                        <option value="excel">Excel (XLS)</option>
                        <option value="json">JSON</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Filtrar por Situação</label>
                    <select id="situacao_export" name="situacao">
                        <option value="">Todas as Situações</option>
                        <option value="EM_ANDAMENTO">Em Andamento</option>
                        <option value="HOMOLOGADO">Homologadas</option>
                        <option value="FRACASSADO">Fracassadas</option>
                        <option value="REVOGADO">Revogadas</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Período de Criação</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Inicial</label>
                            <input type="date" id="data_inicio_export" name="data_inicio">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Final</label>
                            <input type="date" id="data_fim_export" name="data_fim">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Campos para Exportar</label>
                    <div style="margin-bottom: 10px;">
                        <button type="button" onclick="selecionarTodosCampos(true)" class="btn-secondary" style="margin-right: 10px; padding: 5px 10px; font-size: 12px;">
                            Selecionar Todos
                        </button>
                        <button type="button" onclick="selecionarTodosCampos(false)" class="btn-secondary" style="padding: 5px 10px; font-size: 12px;">
                            Desmarcar Todos
                        </button>
                    </div>
                    <div style="margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="nup" checked> NUP
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="numero_contratacao_final" checked> Número da Contratação
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="modalidade" checked> Modalidade
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="tipo" checked> Tipo
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="objeto" checked> Objeto
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="valor_estimado" checked> Valor Estimado
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="situacao" checked> Situação
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="pregoeiro" checked> Pregoeiro
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="data_abertura" checked> Data Abertura
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="data_homologacao"> Data Homologação
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="valor_homologado"> Valor Homologado
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="economia"> Economia
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="area_demandante"> Área Demandante
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="resp_instrucao"> Resp. Instrução
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="usuario_nome"> Criado por
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="criado_em"> Data de Criação
                        </label>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalExportar')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="download"></i> Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>

<!-- Modal para Importar Andamentos -->
<div id="modalImportarAndamentos" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="upload"></i> Importar Andamentos de Processo
            </h3>
            <span class="close" onclick="fecharModal('modalImportarAndamentos')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formImportarAndamentos" enctype="multipart/form-data">
                <?php echo getCSRFInput(); ?>
                
                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
                    <h4 style="margin: 0 0 10px 0; color: #1976d2;">
                        <i data-lucide="info" style="width: 16px; height: 16px;"></i> NUP Selecionado
                    </h4>
                    <p style="margin: 0; font-weight: 600; color: #1976d2;" id="nupSelecionado">-</p>
                </div>
                
                <div class="form-group">
                    <label>Arquivo JSON *</label>
                    <input type="file" 
                           name="arquivo_json" 
                           id="arquivo_json" 
                           accept=".json" 
                           required 
                           style="width: 100%; padding: 10px; border: 2px dashed #dee2e6; border-radius: 8px; background: #f8f9fa;">
                    <small style="color: #6c757d; font-size: 12px; display: block; margin-top: 5px;">
                        Selecione um arquivo .json com os dados de andamentos do processo.
                    </small>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f39c12;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;">
                        <i data-lucide="alert-triangle" style="width: 16px; height: 16px;"></i> Estrutura Esperada do JSON
                    </h4>
                    <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; overflow-x: auto;">{
  "nup": "12345.123456/2024-12",
  "processo_id": "SEI123456789",
  "timestamp": "2024-12-27 10:30:00",
  "total_andamentos": 3,
  "andamentos": [
    {
      "unidade": "DIPLI",
      "dias": 15,
      "descricao": "Análise técnica"
    },
    {
      "unidade": "DIPLAN", 
      "dias": 8,
      "descricao": "Revisão planejamento"
    }
  ]
}</pre>
                </div>
                
                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalImportarAndamentos')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="upload"></i> Importar Andamentos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Visualizar Andamentos -->
<div id="modalVisualizarAndamentos" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="clock"></i> Andamentos do Processo
            </h3>
            <span class="close" onclick="fecharModal('modalVisualizarAndamentos')">&times;</span>
        </div>
        <div class="modal-body" id="conteudoAndamentos">
            <div style="text-align: center; padding: 20px;">
                <i data-lucide="loader" style="width: 32px; height: 32px; animation: spin 1s linear infinite;"></i>
                <p>Carregando andamentos...</p>
            </div>
        </div>
    </div>
</div>

    </div>
        </div>
        </main>
    </div>

<!-- Botão de teste temporário -->
<div style="position: fixed; bottom: 20px; right: 20px; z-index: 2000;">
    <button onclick="testarModal()" style="background: red; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer;">
        🧪 Teste Modal
    </button>
</div>

    <script>
        // Dados passados do PHP para JavaScript
        window.dadosModalidade = <?php echo json_encode($dados_modalidade); ?>;
        window.dadosPregoeiro = <?php echo json_encode($dados_pregoeiro); ?>;
        window.dadosMensal = <?php echo json_encode($dados_mensal); ?>;
        window.stats = <?php echo json_encode($stats); ?>;
        window.dadosContratacoes = <?php echo json_encode($contratacoes_pca); ?>;
        
        // Compatibilidade com arquivo JS externo
        window.contratacoesPCA = window.dadosContratacoes;
    </script>
    <script src="assets/notifications.js"></script>
</body>
</html>