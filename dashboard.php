<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// Configuração de paginação
$limite = intval($_GET['limite'] ?? 20);
$pagina = intval($_GET['pagina'] ?? 1);
$offset = ($pagina - 1) * $limite;

// Buscar áreas para o filtro (agrupadas)
$areas_sql = "SELECT DISTINCT area_requisitante FROM pca_dados WHERE area_requisitante IS NOT NULL AND area_requisitante != '' ORDER BY area_requisitante";
$areas_result = $pdo->query($areas_sql);
$areas_agrupadas = [];

while ($row = $areas_result->fetch()) {
    $area_agrupada = agruparArea($row['area_requisitante']);
    if (!in_array($area_agrupada, $areas_agrupadas)) {
        $areas_agrupadas[] = $area_agrupada;
    }
}
sort($areas_agrupadas);

// Buscar dados com filtros
$where = [];
$params = [];

if (!empty($_GET['numero_contratacao'])) {
    $where[] = "p.numero_dfd LIKE ?";
    $params[] = '%' . $_GET['numero_contratacao'] . '%';
}

if (!empty($_GET['situacao_execucao'])) {
    if ($_GET['situacao_execucao'] === 'Não iniciado') {
        $where[] = "(p.situacao_execucao IS NULL OR p.situacao_execucao = '' OR p.situacao_execucao = 'Não iniciado')";
    } else {
        $where[] = "p.situacao_execucao = ?";
        $params[] = $_GET['situacao_execucao'];
    }
}

if (!empty($_GET['categoria'])) {
    $where[] = "p.categoria_contratacao = ?";
    $params[] = $_GET['categoria'];
}

if (!empty($_GET['area_requisitante'])) {
    $filtro_area = $_GET['area_requisitante'];
    if ($filtro_area === 'GM.') {
        $where[] = "(p.area_requisitante LIKE 'GM%' OR p.area_requisitante LIKE 'GM.%')";
    } else {
        $where[] = "p.area_requisitante LIKE ?";
        $params[] = $filtro_area . '%';
    }
}

$whereClause = '';
if ($where) {
    $whereClause = 'AND ' . implode(' AND ', $where);
}

// Query para contar total de registros
$sqlCount = "SELECT COUNT(DISTINCT numero_dfd) as total FROM pca_dados p WHERE numero_dfd IS NOT NULL AND numero_dfd != '' $whereClause";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRegistros = $stmtCount->fetch()['total'];
$totalPaginas = ceil($totalRegistros / $limite);

// Query principal agrupando por número de contratação
$sql = "SELECT 
        MAX(p.numero_contratacao) as numero_contratacao,
        p.numero_dfd,
        MAX(p.status_contratacao) as status_contratacao,
        MAX(p.titulo_contratacao) as titulo_contratacao,
        MAX(p.categoria_contratacao) as categoria_contratacao,
        MAX(p.uasg_atual) as uasg_atual,
        MAX(p.valor_total_contratacao) as valor_total_contratacao,
        MAX(p.area_requisitante) as area_requisitante,
        MAX(p.prioridade) as prioridade,
        MAX(p.situacao_execucao) as situacao_execucao,
        MAX(p.data_inicio_processo) as data_inicio_processo,
        MAX(p.data_conclusao_processo) as data_conclusao_processo,
        DATEDIFF(MAX(p.data_conclusao_processo), CURDATE()) as dias_ate_conclusao,
        COUNT(*) as qtd_itens_pca,
        GROUP_CONCAT(p.id) as ids,
        MAX(p.id) as id,
        MAX((SELECT COUNT(*) FROM licitacoes WHERE pca_dados_id IN (
            SELECT id FROM pca_dados WHERE numero_dfd = p.numero_dfd
        ))) as tem_licitacao
        FROM pca_dados p 
        WHERE p.numero_dfd IS NOT NULL AND p.numero_dfd != ''
        $whereClause 
        GROUP BY p.numero_dfd
        ORDER BY p.numero_dfd DESC
        LIMIT " . intval($limite) . " OFFSET " . intval($offset);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dados = $stmt->fetchAll();

// Buscar listas únicas para os filtros
$situacao_lista = $pdo->query("SELECT DISTINCT situacao_execucao FROM pca_dados WHERE situacao_execucao IS NOT NULL AND situacao_execucao != '' ORDER BY situacao_execucao")->fetchAll(PDO::FETCH_COLUMN);

// Adicionar "Não iniciado" se não estiver na lista
if (!in_array('Não iniciado', $situacao_lista)) {
    array_unshift($situacao_lista, 'Não iniciado');
}

$categoria_lista = $pdo->query("SELECT DISTINCT categoria_contratacao FROM pca_dados WHERE categoria_contratacao IS NOT NULL ORDER BY categoria_contratacao")->fetchAll(PDO::FETCH_COLUMN);

// Buscar estatísticas para os cards e gráficos
$stats_sql = "SELECT 
    COUNT(DISTINCT p.numero_dfd) as total_dfds,
    COUNT(DISTINCT p.numero_contratacao) as total_contratacoes,
    SUM(DISTINCT p.valor_total_contratacao) as valor_total,
    COUNT(DISTINCT CASE WHEN l.situacao = 'HOMOLOGADO' THEN p.numero_contratacao END) as homologadas,
    COUNT(DISTINCT CASE WHEN p.data_inicio_processo < CURDATE() AND (p.situacao_execucao IS NULL OR p.situacao_execucao = '' OR p.situacao_execucao = 'Não iniciado') THEN p.numero_contratacao END) as atrasadas_inicio,
    COUNT(DISTINCT CASE WHEN p.data_conclusao_processo < CURDATE() AND p.situacao_execucao != 'Concluído' THEN p.numero_contratacao END) as atrasadas_conclusao,
    COUNT(DISTINCT CASE WHEN p.data_conclusao_processo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN p.numero_contratacao END) as vencendo_30_dias,
    COUNT(DISTINCT CASE WHEN l.situacao IN ('EM_ANDAMENTO') THEN p.numero_contratacao END) as em_andamento
    FROM pca_dados p
    LEFT JOIN licitacoes l ON l.pca_dados_id = p.id";

$stats = $pdo->query($stats_sql)->fetch();

// Dados para gráficos
$dados_categoria = $pdo->query("
    SELECT categoria_contratacao, COUNT(DISTINCT numero_dfd) as quantidade 
    FROM pca_dados 
    WHERE categoria_contratacao IS NOT NULL
    GROUP BY categoria_contratacao
    ORDER BY quantidade DESC
    LIMIT 5
")->fetchAll();

$dados_area = $pdo->query("
    SELECT area_requisitante, COUNT(DISTINCT numero_dfd) as quantidade 
    FROM pca_dados 
    WHERE area_requisitante IS NOT NULL
    GROUP BY area_requisitante
    ORDER BY quantidade DESC
    LIMIT 5
")->fetchAll();

$dados_mensal_pca = $pdo->query("
    SELECT 
        DATE_FORMAT(data_inicio_processo, '%Y-%m') as mes,
        COUNT(DISTINCT numero_dfd) as quantidade
    FROM pca_dados 
    WHERE data_inicio_processo >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    AND data_inicio_processo IS NOT NULL
    GROUP BY DATE_FORMAT(data_inicio_processo, '%Y-%m')
    ORDER BY mes
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Planejamento - Sistema CGLIC</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow: hidden;
        }

        .dashboard-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ==================== SIDEBAR FIXA ==================== */
        .sidebar {
            width: 280px;
            background: #2c3e50;
            color: white;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid #34495e;
            text-align: center;
            flex-shrink: 0;
            background: #2c3e50;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: white;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #34495e #2c3e50;
        }

        .nav-section {
            margin-bottom: 25px;
        }

        .nav-section-title {
            padding: 0 20px 12px 20px;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: #95a5a6;
            letter-spacing: 1.2px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 16px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            gap: 15px;
            position: relative;
        }

        .nav-item:hover {
            background: #34495e;
            color: white;
            padding-left: 25px;
        }

        .nav-item.active {
            background: #3498db;
            color: white;
            border-right: 4px solid #2980b9;
            padding-left: 25px;
        }

        .nav-item i {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid #34495e;
            background: #2c3e50;
            flex-shrink: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
            color: white;
            border: 2px solid rgba(255,255,255,0.2);
        }

        .user-details {
    flex: 1;
    min-width: 0; /* Adicionar isto */
    overflow: hidden; /* Adicionar isto */
}

.user-details h4 {
    margin: 0 0 2px 0;
    font-size: 14px;
    font-weight: 600;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis; /* Adicionar reticências quando o texto for muito longo */
}

.user-details p {
    margin: 0;
    font-size: 12px;
    color: #bdc3c7;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis; /* Adicionar reticências quando o texto for muito longo */
}

        .logout-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-1px);
        }

        /* ==================== MAIN CONTENT ==================== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            overflow-y: auto;
            height: 100vh;
            background: #f5f5f5;
        }

        .content-section {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .content-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ==================== DASHBOARD HEADER ==================== */
        .dashboard-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 30px 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        .dashboard-header h1 {
            margin: 0 0 8px 0;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header p {
            margin: 0;
            opacity: 0.95;
            font-size: 16px;
        }

        /* ==================== CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-left: 5px solid;
            transition: all 0.3s ease;
        }

        .stat-card:hover { 
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .stat-card.info { border-left-color: #3498db; }
        .stat-card.primary { border-left-color: #9b59b6; }
        .stat-card.money { border-left-color: #16a085; }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: #2c3e50;
            margin: 10px 0 8px 0;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ==================== GRÁFICOS ==================== */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            height: 380px;
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .chart-title {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }

        .chart-card canvas {
            max-height: 300px !important;
            width: 100% !important;
        }

        /* ==================== UPLOAD AREA ==================== */
        .upload-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            border: 3px dashed #e9ecef;
            transition: all 0.3s ease;
        }

        .upload-card:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }

        .upload-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 24px;
        }

        .upload-card input[type="file"] {
            margin: 20px 0;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            transform: translateY(-2px);
        }

        /* ==================== FILTROS ==================== */
        .filtros-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .filtros-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filtros-form input,
        .filtros-form select {
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .filtros-form input:focus,
        .filtros-form select:focus {
            outline: none;
            border-color: #3498db;
        }

        /* ==================== TABELA ==================== */
        .table-container {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }

        .table-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }

        .table-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 18px 15px;
            text-align: left;
            border-bottom: 1px solid #f1f2f6;
        }

        th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            font-weight: 700;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .situacao-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .btn-acao {
            border: 2px solid #e9ecef;
            background: white;
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 5px;
        }

        .btn-acao:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-ver {
            border-color: #3498db;
            color: #3498db;
        }

        .btn-ver:hover {
            background: #3498db;
            color: white;
        }

        .btn-historico {
            border-color: #17a2b8;
            color: #17a2b8;
        }

        .btn-historico:hover {
            background: #17a2b8;
            color: white;
        }

        /* ==================== PAGINAÇÃO ==================== */
        .paginacao {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .paginacao a {
            padding: 8px 16px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s ease;
        }

        .paginacao a:hover {
            background: #2980b9;
        }

        /* ==================== RESPONSIVO ==================== */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .filtros-form {
                grid-template-columns: 1fr;
            }
        }

        /* Correção para gráficos */
.chart-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    height: 380px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden; /* Adicionar isto */
}

.chart-card canvas {
    max-height: 280px !important; /* Reduzir altura máxima */
    width: 100% !important;
}

/* Container para o canvas */
.chart-container {
    position: relative;
    height: 280px;
    width: 100%;
    margin: 0 auto;
}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i data-lucide="clipboard-check"></i> Planejamento</h2>
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
                    <button class="nav-item" onclick="showSection('importar-pca')">
                        <i data-lucide="upload"></i> Importar PCA
                    </button>
                    <button class="nav-item" onclick="showSection('lista-contratacoes')">
                        <i data-lucide="list"></i> Lista de Contratações
                    </button>
                    <a href="contratacoes_atrasadas.php" class="nav-item">
                        <i data-lucide="alert-triangle"></i> Contratações Atrasadas
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Relatórios</div>
                    <button class="nav-item" onclick="showSection('relatorios')">
                        <i data-lucide="file-text"></i> Relatórios
                    </button>
                    <button class="nav-item" onclick="showSection('exportar')">
                        <i data-lucide="download"></i> Exportar Dados
                    </button>
                    <button class="nav-item" onclick="window.location.href='relatorio_riscos.php'">
    <i data-lucide="shield-alert"></i> Gestão de Riscos
</button>
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
                    </div>
                </div>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i data-lucide="log-out"></i> Sair
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <?php echo getMensagem(); ?>

            <!-- Dashboard Section -->
            <div id="dashboard" class="content-section active">
                <div class="dashboard-header">
                    <h1><i data-lucide="bar-chart-3"></i> Dashboard de Planejamento</h1>
                    <p>Visão geral do Plano de Contratações Anual (PCA) e indicadores de desempenho</p>
                </div>

                <!-- Cards de Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card info">
                        <div class="stat-number"><?php echo number_format($stats['total_dfds']); ?></div>
                        <div class="stat-label">Total de DFDs</div>
                    </div>
                    
                    <div class="stat-card primary">
                        <div class="stat-number"><?php echo number_format($stats['total_contratacoes']); ?></div>
                        <div class="stat-label">Total Contratações</div>
                    </div>
                    
                    <div class="stat-card money">
                        <div class="stat-number"><?php echo abreviarValor($stats['valor_total']); ?></div>
                        <div class="stat-label">Valor Total (R$)</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-number"><?php echo $stats['homologadas']; ?></div>
                        <div class="stat-label">Homologadas</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-number"><?php echo $stats['atrasadas_inicio'] + $stats['atrasadas_conclusao']; ?></div>
                        <div class="stat-label">Atrasadas</div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="users"></i> Contratações por Área</h3>
                        <canvas id="chartArea" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="trending-up"></i> Evolução Mensal</h3>
                        <canvas id="chartMensal" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="activity"></i> Status das Contratações</h3>
                        <canvas id="chartStatus" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Importar PCA Section -->
            <div id="importar-pca" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="upload"></i> Importar Planilha PCA</h1>
                    <p>Faça upload da planilha do Plano de Contratações Anual</p>
                </div>

                <div class="upload-card">
                    <h3>Importar Planilha PCA</h3>
                    <p style="color: #7f8c8d; margin-bottom: 20px;">Selecione um arquivo CSV, XLS ou XLSX para importar</p>
                    
                    <form action="process.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="importar_pca">
                        <input type="file" name="arquivo_pca" accept=".csv,.xls,.xlsx" required>
                        <br><br>
                        <button type="submit" class="btn-primary">
                            <i data-lucide="upload"></i> Importar Arquivo
                        </button>
                    </form>
                </div>
            </div>

            <!-- Lista de Contratações Section -->
            <div id="lista-contratacoes" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="list"></i> Lista de Contratações</h1>
                    <p>Visualize e gerencie todas as contratações do PCA</p>
                </div>

                <!-- Filtros -->
                <div class="filtros-card">
                    <h3 style="margin: 0 0 20px 0; color: #2c3e50;">Filtros</h3>
                    <form method="GET" class="filtros-form">
                        <input type="hidden" name="limite" value="<?php echo $limite; ?>">
                        <div>
                            <input type="text" name="numero_contratacao" placeholder="Número do DFD"
                                   value="<?php echo $_GET['numero_contratacao'] ?? ''; ?>">
                        </div>
                        <div>
                            <select name="situacao_execucao">
                                <option value="">Todas as Situações</option>
                                <?php foreach ($situacao_lista as $situacao): ?>
                                    <option value="<?php echo htmlspecialchars($situacao); ?>" 
                                            <?php echo ($_GET['situacao_execucao'] ?? '') == $situacao ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($situacao); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select name="categoria">
                                <option value="">Todas as Categorias</option>
                                <?php foreach ($categoria_lista as $categoria): ?>
                                    <option value="<?php echo $categoria; ?>" 
                                            <?php echo ($_GET['categoria'] ?? '') == $categoria ? 'selected' : ''; ?>>
                                        <?php echo $categoria; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select name="area_requisitante">
                                <option value="">Todas as áreas</option>
                                <?php foreach ($areas_agrupadas as $area): ?>
                                    <option value="<?php echo htmlspecialchars($area); ?>" 
                                            <?php echo ($_GET['area_requisitante'] ?? '') == $area ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($area); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn-primary">Filtrar</button>
                        </div>
                    </form>
                </div>

                <!-- Tabela -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Dados do PCA</h3>
                        <div class="table-actions">
                            <span style="color: #7f8c8d;">Total: <?php echo $totalRegistros; ?> contratações</span>
                            <select onchange="window.location.href='?limite='+this.value+'&<?php echo http_build_query(array_diff_key($_GET, ['limite' => '', 'pagina' => ''])); ?>'" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="10" <?php echo $limite == 10 ? 'selected' : ''; ?>>10 por página</option>
                                <option value="20" <?php echo $limite == 20 ? 'selected' : ''; ?>>20 por página</option>
                                <option value="50" <?php echo $limite == 50 ? 'selected' : ''; ?>>50 por página</option>
                                <option value="100" <?php echo $limite == 100 ? 'selected' : ''; ?>>100 por página</option>
                            </select>
                            <a href="exportar.php?<?php echo http_build_query($_GET); ?>" class="btn-primary">
                                <i data-lucide="download"></i> Exportar Excel
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($dados)): ?>
                        <div style="text-align: center; padding: 60px; color: #7f8c8d;">
                            <i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: 20px;"></i>
                            <h3 style="margin: 0 0 10px 0;">Nenhum registro encontrado</h3>
                            <p style="margin: 0;">Importe uma planilha PCA para começar.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nº DFD</th>
                                    <th>Situação</th>
                                    <th>Título</th>
                                    <th>Categoria</th>
                                    <th>Valor Total</th>
                                    <th>Área</th>
                                    <th>Datas</th>
                                    <th style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dados as $item): ?>
                                <?php
                                    $classeSituacao = '';
                                    if ($item['data_inicio_processo'] < date('Y-m-d') && $item['situacao_execucao'] == 'Não iniciado') {
                                        $classeSituacao = 'atrasado-inicio';
                                    } elseif ($item['data_conclusao_processo'] < date('Y-m-d') && $item['situacao_execucao'] != 'Concluído') {
                                        $classeSituacao = 'atrasado-conclusao';
                                    }
                                ?>
                                <tr class="<?php echo $classeSituacao ? 'linha-' . $classeSituacao : ''; ?>">
                                    <td><strong><?php echo htmlspecialchars($item['numero_dfd']); ?></strong></td>
                                    <td>
                                        <span class="situacao-badge <?php echo $classeSituacao; ?>">
                                            <?php echo htmlspecialchars($item['situacao_execucao']); ?>
                                        </span>
                                        <?php if ($item['dias_ate_conclusao'] !== null && $item['dias_ate_conclusao'] >= 0 && $item['dias_ate_conclusao'] <= 30): ?>
                                            <br><small style="color: #f39c12; font-weight: 600;"><?php echo $item['dias_ate_conclusao']; ?> dias</small>
                                        <?php elseif ($item['dias_ate_conclusao'] !== null && $item['dias_ate_conclusao'] < 0): ?>
                                            <br><small style="color: #e74c3c; font-weight: 600;">Vencido há <?php echo abs($item['dias_ate_conclusao']); ?> dias</small>
                                        <?php endif; ?>
                                    </td>
                                    <td title="<?php echo htmlspecialchars($item['titulo_contratacao']); ?>">
                                        <?php echo htmlspecialchars(substr($item['titulo_contratacao'], 0, 60)) . '...'; ?>
                                    </td>
                                    <td><span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($item['categoria_contratacao']); ?></span></td>
                                    <td style="font-weight: 600; color: #27ae60;"><?php echo formatarMoeda($item['valor_total_contratacao']); ?></td>
                                    <td><?php echo htmlspecialchars($item['area_requisitante']); ?></td>
                                    <td style="font-size: 12px;">
                                        <strong>Início:</strong> <?php echo formatarData($item['data_inicio_processo']); ?><br>
                                        <strong>Fim:</strong> <?php echo formatarData($item['data_conclusao_processo']); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px; align-items: center;">
                                            <button onclick="verDetalhes('<?php echo $item['ids']; ?>')" 
                                                    class="btn-acao btn-ver" title="Ver detalhes">
                                                <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                            </button>
                                            <button onclick="verHistorico('<?php echo $item['numero_dfd']; ?>')"
                                                    class="btn-acao btn-historico" title="Ver histórico">
                                                <i data-lucide="history" style="width: 14px; height: 14px;"></i>
                                            </button>
                                            <?php if ($item['tem_licitacao'] > 0): ?>
                                                <span style="color: #28a745; font-size: 13px; display: flex; align-items: center; gap: 4px;">
                                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                                    Licitado
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Paginação -->
                        <?php if ($totalPaginas > 1): ?>
                        <div class="paginacao">
                            <?php if ($pagina > 1): ?>
                                <a href="?pagina=<?php echo $pagina-1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['pagina' => ''])); ?>">← Anterior</a>
                            <?php endif; ?>
                            
                            <span>Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?></span>
                            
                            <?php if ($pagina < $totalPaginas): ?>
                                <a href="?pagina=<?php echo $pagina+1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['pagina' => ''])); ?>">Próxima →</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Relatórios Section -->
            <div id="relatorios" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="file-text"></i> Relatórios</h1>
                    <p>Relatórios detalhados sobre o planejamento de contratações</p>
                </div>

                <div class="stats-grid">
                    <div class="upload-card">
                        <h3>Relatório por Categoria</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Análise detalhada das contratações por categoria</p>
                        <button class="btn-primary">Gerar Relatório</button>
                    </div>
                    
                    <div class="upload-card">
                        <h3>Relatório por Área</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Performance e distribuição por área requisitante</p>
                        <button class="btn-primary">Gerar Relatório</button>
                    </div>
                    
                    <div class="upload-card">
                        <h3>Relatório de Prazos</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Análise de cumprimento de cronogramas</p>
                        <button class="btn-primary">Gerar Relatório</button>
                    </div>
                    
                    <div class="upload-card">
                        <h3>Relatório Financeiro</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Valores planejados vs executados</p>
                        <button class="btn-primary">Gerar Relatório</button>
                    </div>
                </div>
            </div>

            <!-- Exportar Section -->
            <div id="exportar" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="download"></i> Exportar Dados</h1>
                    <p>Exporte dados das contratações em diferentes formatos</p>
                </div>

                <div class="table-container">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Formato de Exportação</label>
                            <select id="formato-export" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px;">
                                <option value="csv">CSV (Excel)</option>
                                <option value="pdf">PDF</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Filtrar por Situação</label>
                            <select id="situacao-export" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px;">
                                <option value="">Todas</option>
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Concluído">Concluído</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Data Início</label>
                            <input type="date" id="data-inicio-export" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Data Fim</label>
                            <input type="date" id="data-fim-export" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px;">
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <button onclick="exportarDados()" class="btn-primary">
                            <i data-lucide="download"></i> Exportar Dados
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div id="modalDetalhes" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 50px auto; padding: 0; border-radius: 12px; max-width: 900px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div style="padding: 20px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;">
                <h3 style="margin: 0; color: #2c3e50;">Detalhes da Contratação</h3>
                <span onclick="fecharModalDetalhes()" style="font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; transition: color 0.3s;">&times;</span>
            </div>
            <div id="conteudoDetalhes" style="padding: 20px;">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
        </div>
    </div>

    <script>
        // Navegação da Sidebar
        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            document.getElementById(sectionId).classList.add('active');
            event.target.classList.add('active');
        }

        // Funções dos Gráficos
        function initCharts() {
            setTimeout(() => {
                const dadosCategoria = <?php echo json_encode($dados_categoria); ?>;
                const dadosArea = <?php echo json_encode($dados_area); ?>;
                const dadosMensal = <?php echo json_encode($dados_mensal_pca); ?>;
                const stats = <?php echo json_encode($stats); ?>;

                Chart.defaults.responsive = true;
                Chart.defaults.maintainAspectRatio = false;

                // Gráfico de Categorias
                if (document.getElementById('chartCategoria')) {
                    new Chart(document.getElementById('chartCategoria'), {
                        type: 'doughnut',
                        data: {
                            labels: dadosCategoria.map(item => item.categoria_contratacao),
                            datasets: [{
                                data: dadosCategoria.map(item => item.quantidade),
                                backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6']
                            }]
                        },
                        options: {
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }

                // Gráfico de Áreas
                if (document.getElementById('chartArea')) {
                    new Chart(document.getElementById('chartArea'), {
                        type: 'bar',
                        data: {
                            labels: dadosArea.map(item => item.area_requisitante),
                            datasets: [{
                                label: 'Contratações',
                                data: dadosArea.map(item => item.quantidade),
                                backgroundColor: '#3498db'
                            }]
                        },
                        options: {
                            plugins: { legend: { display: false } }
                        }
                    });
                }

                // Gráfico Mensal
                if (document.getElementById('chartMensal')) {
                    new Chart(document.getElementById('chartMensal'), {
                        type: 'line',
                        data: {
                            labels: dadosMensal.map(item => {
                                const [ano, mes] = item.mes.split('-');
                                return new Date(ano, mes - 1).toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
                            }),
                            datasets: [{
                                label: 'Contratações Iniciadas',
                                data: dadosMensal.map(item => item.quantidade),
                                borderColor: '#3498db',
                                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                tension: 0.4
                            }]
                        },
                        options: {
                            plugins: { legend: { display: false } }
                        }
                    });
                }

                // Gráfico de Status
                if (document.getElementById('chartStatus')) {
                    new Chart(document.getElementById('chartStatus'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Homologadas', 'Em Andamento', 'Atrasadas'],
                            datasets: [{
                                data: [
                                    stats.homologadas || 0,
                                    stats.em_andamento || 0,
                                    (stats.atrasadas_inicio + stats.atrasadas_conclusao) || 0
                                ],
                                backgroundColor: ['#27ae60', '#f39c12', '#e74c3c']
                            }]
                        },
                        options: {
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            }, 500);
        }

        // Funções da Tabela
        function verDetalhes(ids) {
            const modal = document.getElementById('modalDetalhes');
            const conteudo = document.getElementById('conteudoDetalhes');
            
            conteudo.innerHTML = '<div style="text-align: center; padding: 40px;"><p>Carregando...</p></div>';
            modal.style.display = 'block';
            
            fetch('detalhes.php?ids=' + ids)
                .then(response => response.text())
                .then(html => {
                    conteudo.innerHTML = html;
                })
                .catch(() => {
                    conteudo.innerHTML = '<div style="padding: 40px; text-align: center;">Erro ao carregar detalhes</div>';
                });
        }

        function fecharModalDetalhes() {
            document.getElementById('modalDetalhes').style.display = 'none';
            document.getElementById('conteudoDetalhes').innerHTML = '';
        }

        function verHistorico(numero) {
            const modal = document.getElementById('modalDetalhes');
            const conteudo = document.getElementById('conteudoDetalhes');
            
            conteudo.innerHTML = '<div style="text-align: center; padding: 40px;"><p>Carregando histórico...</p></div>';
            modal.style.display = 'block';
            
            fetch('historico_contratacao.php?numero=' + encodeURIComponent(numero))
                .then(response => response.text())
                .then(html => {
                    conteudo.innerHTML = html;
                })
                .catch(() => {
                    conteudo.innerHTML = '<div style="padding: 40px; text-align: center;">Erro ao carregar histórico</div>';
                });
        }

        function exportarDados() {
            alert('Exportar dados personalizados - Funcionalidade em desenvolvimento');
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('modalDetalhes');
            if (event.target == modal) {
                fecharModalDetalhes();
            }
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            if (typeof Chart !== 'undefined') {
                initCharts();
            }
        });
    </script>
</body>
</html>