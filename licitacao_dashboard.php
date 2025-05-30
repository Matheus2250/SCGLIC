<?php
require_once 'config.php';
require_once 'functions.php';

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
        CASE WHEN pregoeiro IS NULL OR pregoeiro = '' THEN 'Não Definido' ELSE pregoeiro END as pregoeiro,
        COUNT(*) as quantidade 
    FROM licitacoes 
    GROUP BY pregoeiro
    ORDER BY quantidade DESC
    LIMIT 5
")->fetchAll();

$dados_mensal = $pdo->query("
    SELECT 
        DATE_FORMAT(criado_em, '%Y-%m') as mes,
        COUNT(*) as quantidade
    FROM licitacoes 
    WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(criado_em, '%Y-%m')
    ORDER BY mes
")->fetchAll();

// Buscar todas as licitações para a lista
$licitacoes_recentes = $pdo->query("
    SELECT l.*, u.nome as usuario_nome 
    FROM licitacoes l 
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    ORDER BY l.criado_em DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Licitações - Sistema CGLIC</title>
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

.sidebar-nav::-webkit-scrollbar {
    width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: #2c3e50;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: #34495e;
    border-radius: 3px;
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

.nav-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #2980b9;
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
    min-width: 0;
}

.user-details h4 {
    margin: 0 0 2px 0;
    font-size: 14px;
    font-weight: 600;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-details p {
    margin: 0;
    font-size: 12px;
    color: #bdc3c7;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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
    box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
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
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    padding: 30px 35px;
    border-radius: 16px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100%;
    background: linear-gradient(45deg, rgba(255,255,255,0.1), transparent);
    transform: skewX(-15deg);
}

.dashboard-header h1 {
    margin: 0 0 8px 0;
    font-size: 32px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    z-index: 1;
}

.dashboard-header p {
    margin: 0;
    opacity: 0.95;
    font-size: 16px;
    font-weight: 400;
    position: relative;
    z-index: 1;
}

/* ==================== CARDS ESTATÍSTICAS ==================== */
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
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    opacity: 0.1;
    transform: translate(20px, -20px);
}

.stat-card:hover { 
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.stat-card.total { 
    border-left-color: #3498db;
}
.stat-card.total::before {
    background: #3498db;
}

.stat-card.andamento { 
    border-left-color: #f39c12;
}
.stat-card.andamento::before {
    background: #f39c12;
}

.stat-card.homologadas { 
    border-left-color: #27ae60;
}
.stat-card.homologadas::before {
    background: #27ae60;
}

.stat-card.fracassadas { 
    border-left-color: #e74c3c;
}
.stat-card.fracassadas::before {
    background: #e74c3c;
}

.stat-card.valor { 
    border-left-color: #9b59b6;
}
.stat-card.valor::before {
    background: #9b59b6;
}

.stat-number {
    font-size: 36px;
    font-weight: 800;
    color: #2c3e50;
    margin: 10px 0 8px 0;
    position: relative;
    z-index: 1;
}

.stat-label {
    color: #7f8c8d;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    z-index: 1;
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

/* ==================== FORMULÁRIOS ==================== */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    box-sizing: border-box;
    background: white;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #e74c3c;
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
    transform: translateY(-1px);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.form-full {
    grid-column: 1 / -1;
}

/* ==================== BOTÕES ==================== */
.btn-primary {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
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
    box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
}

.btn-secondary {
    background: linear-gradient(135deg, #95a5a6, #7f8c8d);
    color: white;
    padding: 14px 28px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s ease;
    margin-left: 15px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
}

/* ==================== TABELAS ==================== */
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

.table-filters {
    display: flex;
    gap: 15px;
    align-items: center;
}

.table-filters select {
    padding: 10px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    cursor: pointer;
    transition: border-color 0.3s ease;
}

.table-filters select:focus {
    outline: none;
    border-color: #e74c3c;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    margin-top: 20px;
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
    position: sticky;
    top: 0;
    z-index: 10;
}

tbody tr {
    transition: all 0.2s ease;
}

tbody tr:hover {
    background: #f8f9fa;
    transform: scale(1.01);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

td:first-child {
    font-weight: 600;
    color: #2c3e50;
}

/* ==================== STATUS BADGES ==================== */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
}

.status-em-andamento { 
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    color: #856404;
    border: 1px solid #f39c12;
}

.status-homologado { 
    background: linear-gradient(135deg, #d4edda, #a8e6cf);
    color: #155724;
    border: 1px solid #27ae60;
}

.status-fracassado { 
    background: linear-gradient(135deg, #f8d7da, #ffb3ba);
    color: #721c24;
    border: 1px solid #e74c3c;
}

.status-revogado { 
    background: linear-gradient(135deg, #e2e3e5, #d1d8e0);
    color: #383d41;
    border: 1px solid #6c757d;
}

/* ==================== BOTÕES DE AÇÃO ==================== */
.table-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.table-actions button {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 8px 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.table-actions button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.table-actions button.btn-view {
    border-color: #3498db;
    color: #3498db;
}

.table-actions button.btn-view:hover {
    background: #3498db;
    color: white;
}

.table-actions button.btn-edit {
    border-color: #f39c12;
    color: #f39c12;
}

.table-actions button.btn-edit:hover {
    background: #f39c12;
    color: white;
}

.table-actions button.btn-delete {
    border-color: #e74c3c;
    color: #e74c3c;
}

.table-actions button.btn-delete:hover {
    background: #e74c3c;
    color: white;
}

/* ==================== RESPONSIVO ==================== */
@media (max-width: 1200px) {
    .charts-grid {
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    }
    
    .chart-card {
        height: 350px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
        padding: 20px;
    }
    
    .dashboard-header {
        padding: 25px;
    }
    
    .dashboard-header h1 {
        font-size: 28px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .chart-card {
        height: 320px;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .table-filters {
        justify-content: center;
    }
    
    table {
        font-size: 13px;
    }
    
    th, td {
        padding: 12px 8px;
    }
}

@media (max-width: 480px) {
    .main-content {
        padding: 15px;
    }
    
    .dashboard-header h1 {
        font-size: 24px;
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .stat-card {
        padding: 20px;
        text-align: center;
    }
    
    .stat-number {
        font-size: 28px;
    }
    
    .chart-card {
        padding: 20px;
        height: 280px;
    }
    
    .btn-primary,
    .btn-secondary {
        width: 100%;
        justify-content: center;
        margin-left: 0;
        margin-top: 10px;
    }
}

/* ==================== SCROLLBAR PERSONALIZADA ==================== */
.main-content::-webkit-scrollbar {
    width: 8px;
}

.main-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.main-content::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.main-content::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* ==================== LOADING E ESTADOS ==================== */
.loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #7f8c8d;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #7f8c8d;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    color: #2c3e50;
}

.empty-state p {
    margin: 0;
    font-size: 16px;
}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
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
                    <button class="nav-item" onclick="showSection('criar-licitacao')">
                        <i data-lucide="plus-circle"></i> Criar Licitação
                    </button>
                    <button class="nav-item" onclick="showSection('lista-licitacoes')">
                        <i data-lucide="list"></i> Lista de Licitações
                    </button>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Relatórios</div>
                    <button class="nav-item" onclick="showSection('relatorios')">
                        <i data-lucide="file-text"></i> Relatórios
                    </button>
                    <button class="nav-item" onclick="showSection('exportar')">
                        <i data-lucide="download"></i> Exportar Dados
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
                    <h1><i data-lucide="bar-chart-3"></i> Dashboard de Licitações</h1>
                    <p>Visão geral do processo licitatório e indicadores de desempenho</p>
                </div>

                <!-- Cards de Estatísticas -->
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

                <!-- Gráficos -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="pie-chart"></i> Licitações por Modalidade</h3>
                        <canvas id="chartModalidade" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="users"></i> Licitações por Pregoeiro</h3>
                        <canvas id="chartPregoeiro" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="trending-up"></i> Evolução Mensal</h3>
                        <canvas id="chartMensal" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title"><i data-lucide="activity"></i> Status das Licitações</h3>
                        <canvas id="chartStatus" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Criar Licitação Section -->
            <div id="criar-licitacao" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="plus-circle"></i> Criar Nova Licitação</h1>
                    <p>Preencha os dados para criar uma nova licitação</p>
                </div>

                <div class="table-container">
                    <form action="process.php" method="POST">
                        <input type="hidden" name="acao" value="criar_licitacao">
                        <input type="hidden" name="pca_dados_ids" value="0">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>NUP *</label>
                                <input type="text" name="nup" required placeholder="xxxxx.xxxxxx/xxxx-xx">
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
                                <input type="text" name="area_demandante">
                            </div>
                            
                            <div class="form-group">
                                <label>Pregoeiro</label>
                                <input type="text" name="pregoeiro">
                            </div>
                            
                            <div class="form-group">
                                <label>Modalidade *</label>
                                <select name="modalidade" required>
                                    <option value="">Selecione</option>
                                    <option value="DISPENSA">DISPENSA</option>
                                    <option value="PREGAO">PREGÃO</option>
                                    <option value="RDC">RDC</option>
                                    <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Tipo *</label>
                                <select name="tipo" required>
                                    <option value="">Selecione</option>
                                    <option value="TRADICIONAL">TRADICIONAL</option>
                                    <option value="COTACAO">COTAÇÃO</option>
                                    <option value="SRP">SRP</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Número</label>
                                <input type="number" name="numero">
                            </div>
                            
                            <div class="form-group">
                                <label>Ano</label>
                                <input type="number" name="ano" value="<?php echo date('Y'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Valor Estimado (R$)</label>
                                <input type="text" name="valor_estimado" placeholder="0,00">
                            </div>
                            
                            <div class="form-group">
                                <label>Data Abertura</label>
                                <input type="date" name="data_abertura">
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
                                <textarea name="objeto" required rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <button type="submit" class="btn-primary">
                                <i data-lucide="check"></i> Criar Licitação
                            </button>
                            <button type="reset" class="btn-secondary">
                                <i data-lucide="x"></i> Limpar Formulário
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Licitações Section -->
            <div id="lista-licitacoes" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="list"></i> Lista de Licitações</h1>
                    <p>Visualize e gerencie todas as licitações cadastradas</p>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Todas as Licitações</h3>
                        <div class="table-filters">
                            <select onchange="filtrarLicitacoes(this.value)">
                                <option value="">Todas as Situações</option>
                                <option value="EM_ANDAMENTO">Em Andamento</option>
                                <option value="HOMOLOGADO">Homologadas</option>
                                <option value="FRACASSADO">Fracassadas</option>
                                <option value="REVOGADO">Revogadas</option>
                            </select>
                            <button onclick="exportarLicitacoes()" class="btn-primary">
                                <i data-lucide="download"></i> Exportar
                            </button>
                        </div>
                    </div>

                    <?php if (empty($licitacoes_recentes)): ?>
                        <div style="text-align: center; padding: 60px; color: #7f8c8d;">
                            <i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: 20px;"></i>
                            <h3 style="margin: 0 0 10px 0;">Nenhuma licitação encontrada</h3>
                            <p style="margin: 0;">Comece criando sua primeira licitação.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>NUP</th>
                                    <th>Modalidade</th>
                                    <th>Número/Ano</th>
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
                                    <td><strong><?php echo htmlspecialchars($licitacao['nup']); ?></strong></td>
                                    <td><span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($licitacao['modalidade']); ?></span></td>
                                    <td><?php echo htmlspecialchars($licitacao['numero']); ?>/<?php echo $licitacao['ano']; ?></td>
                                    <td title="<?php echo htmlspecialchars($licitacao['objeto']); ?>">
                                        <?php echo htmlspecialchars(substr($licitacao['objeto'], 0, 80)) . '...'; ?>
                                    </td>
                                    <td style="font-weight: 600; color: #27ae60;"><?php echo formatarMoeda($licitacao['valor_estimado']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $licitacao['situacao'])); ?>">
                                            <?php echo str_replace('_', ' ', $licitacao['situacao']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($licitacao['pregoeiro'] ?: '-'); ?></td>
                                    <td><?php echo $licitacao['data_abertura'] ? formatarData($licitacao['data_abertura']) : '-'; ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button onclick="verDetalhes(<?php echo $licitacao['id']; ?>)" title="Ver detalhes" style="background: #3498db; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
                                                <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                            </button>
                                            <button onclick="editarLicitacao(<?php echo $licitacao['id']; ?>)" title="Editar" style="background: #f39c12; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
                                                <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e9ecef; color: #7f8c8d; font-size: 14px;">
                            Total: <?php echo count($licitacoes_recentes); ?> licitações | 
                            Valor total estimado: <?php echo formatarMoeda(array_sum(array_column($licitacoes_recentes, 'valor_estimado'))); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Relatórios Section -->
            <div id="relatorios" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="file-text"></i> Relatórios</h1>
                    <p>Relatórios detalhados sobre o processo licitatório</p>
                </div>

                <div class="stats-grid">
                    <div class="chart-card">
                        <h3 class="chart-title">Relatório por Modalidade</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Análise detalhada das licitações por modalidade</p>
                        <button class="btn-primary">Gerar Relatório</button>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title">Relatório por Pregoeiro</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Performance e distribuição por pregoeiro</p>
                        <button class="btn-primary">Gerar Relatório</button>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title">Relatório de Prazos</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Análise de cumprimento de prazos</p>
                        <button class="btn-primary">Gerar Relatório</button>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title">Relatório Financeiro</h3>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Valores estimados vs homologados</p>
                        <button class="btn-primary">Gerar Relatório</button>
                    </div>
                </div>
            </div>

            <!-- Exportar Section -->
            <div id="exportar" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="download"></i> Exportar Dados</h1>
                    <p>Exporte dados das licitações em diferentes formatos</p>
                </div>

                <div class="table-container">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Formato de Exportação</label>
                            <select id="formato-export">
                                <option value="csv">CSV (Excel)</option>
                                <option value="pdf">PDF</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Filtrar por Situação</label>
                            <select id="situacao-export">
                                <option value="">Todas</option>
                                <option value="EM_ANDAMENTO">Em Andamento</option>
                                <option value="HOMOLOGADO">Homologadas</option>
                                <option value="FRACASSADO">Fracassadas</option>
                                <option value="REVOGADO">Revogadas</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Data Início</label>
                            <input type="date" id="data-inicio-export">
                        </div>
                        
                        <div class="form-group">
                            <label>Data Fim</label>
                            <input type="date" id="data-fim-export">
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

    <script>
        // Navegação da Sidebar
        function showSection(sectionId) {
            // Esconder todas as seções
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remover classe ativa de todos os nav-items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Mostrar seção selecionada
            document.getElementById(sectionId).classList.add('active');
            
            // Ativar nav-item clicado
            event.target.classList.add('active');
        }

        // Funções dos Gráficos
        function initCharts() {
    setTimeout(() => {
        const dadosModalidade = <?php echo json_encode($dados_modalidade); ?>;
        const dadosPregoeiro = <?php echo json_encode($dados_pregoeiro); ?>;
        const dadosMensal = <?php echo json_encode($dados_mensal); ?>;
        const stats = <?php echo json_encode($stats); ?>;

        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Gráfico de Modalidades
        if (document.getElementById('chartModalidade')) {
            new Chart(document.getElementById('chartModalidade'), {
                type: 'doughnut',
                data: {
                    labels: dadosModalidade.map(item => item.modalidade),
                    datasets: [{
                        data: dadosModalidade.map(item => item.quantidade),
                        backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6']
                    }]
                },
                options: {
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // Gráfico de Pregoeiros
        if (document.getElementById('chartPregoeiro')) {
            new Chart(document.getElementById('chartPregoeiro'), {
                type: 'bar',
                data: {
                    labels: dadosPregoeiro.map(item => item.pregoeiro),
                    datasets: [{
                        label: 'Licitações',
                        data: dadosPregoeiro.map(item => item.quantidade),
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
                        label: 'Licitações Criadas',
                        data: dadosMensal.map(item => item.quantidade),
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
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
                    labels: ['Em Andamento', 'Homologadas', 'Fracassadas', 'Revogadas'],
                    datasets: [{
                        data: [
                            stats.em_andamento || 0,
                            stats.homologadas || 0,
                            stats.fracassadas || 0,
                            stats.revogadas || 0
                        ],
                        backgroundColor: ['#f39c12', '#27ae60', '#e74c3c', '#95a5a6']
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
        function filtrarLicitacoes(situacao) {
            const rows = document.querySelectorAll('#lista-licitacoes tbody tr');
            
            rows.forEach(row => {
                if (situacao === '') {
                    row.style.display = '';
                } else {
                    const statusCell = row.querySelector('.status-badge');
                    const status = statusCell.textContent.trim().toUpperCase().replace(' ', '_');
                    
                    if (status === situacao) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }

        function exportarLicitacoes() {
            const dados = [];
            const rows = document.querySelectorAll('#lista-licitacoes tbody tr');
            
            dados.push(['NUP', 'Modalidade', 'Número/Ano', 'Objeto', 'Valor Estimado', 'Situação', 'Pregoeiro', 'Data Abertura']);
            
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    dados.push([
                        cells[0].textContent.trim(),
                        cells[1].textContent.trim(),
                        cells[2].textContent.trim(),
                        cells[3].textContent.trim(),
                        cells[4].textContent.trim(),
                        cells[5].textContent.trim(),
                        cells[6].textContent.trim(),
                        cells[7].textContent.trim()
                    ]);
                }
            });
            
            let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
            dados.forEach(row => {
                csvContent += row.map(cell => '"' + cell + '"').join(';') + '\n';
            });
            
            const link = document.createElement('a');
            link.setAttribute('href', encodeURI(csvContent));
            link.setAttribute('download', 'licitacoes_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function verDetalhes(id) {
            alert('Ver detalhes da licitação ' + id + ' - Funcionalidade em desenvolvimento');
        }

        function editarLicitacao(id) {
            alert('Editar licitação ' + id + ' - Funcionalidade em desenvolvimento');
        }

        function exportarDados() {
            alert('Exportar dados personalizados - Funcionalidade em desenvolvimento');
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            // Inicializar gráficos apenas se Chart.js estiver carregado
            if (typeof Chart !== 'undefined') {
                initCharts();
            }
        });
    </script>
</body>
</html>