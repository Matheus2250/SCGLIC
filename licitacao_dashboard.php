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

// Buscar contratações disponíveis do PCA para o dropdown - COM ENCODING CORRETO
// Buscar contratações disponíveis do PCA para o dropdown - APENAS NÚMEROS
$contratacoes_pca = $pdo->query("
    SELECT DISTINCT 
        numero_contratacao, 
        numero_dfd,
        'Contratação' as titulo_contratacao
    FROM pca_dados 
    WHERE numero_contratacao IS NOT NULL 
    AND numero_contratacao != ''
    ORDER BY numero_contratacao DESC
    LIMIT 500
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

.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background-color: white;
    margin: 50px auto;
    padding: 0;
    border-radius: 16px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    animation: slideIn 0.3s ease;
}

.modal-header {
    padding: 25px 30px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 16px 16px 0 0;
}

.modal-header h3 {
    color: #1f2937;
    font-size: 20px;
}

.modal-body {
    padding: 30px;
}

.close {
    font-size: 28px;
    font-weight: bold;
    color: #9ca3af;
    cursor: pointer;
    transition: color 0.3s;
}

.close:hover {
    color: #374151;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
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
                    <label>Número da Contratação *</label>
                    <select name="numero_contratacao" id="select_contratacao" required onchange="preencherDadosPCA()">
                        <option value="">Selecione uma contratação do PCA...</option>
                        <?php foreach ($contratacoes_pca as $contratacao): ?>
                            <option value="<?php echo htmlspecialchars($contratacao['numero_contratacao'], ENT_QUOTES, 'UTF-8'); ?>" data-dfd="<?php echo htmlspecialchars($contratacao['numero_dfd']); ?>">
                                <?php 
                                // Limpar caracteres especiais problemáticos
                                $titulo = preg_replace('/[^\p{L}\p{N}\s\-\.\/]/u', '', $contratacao['titulo_contratacao']);
                                echo htmlspecialchars($contratacao['numero_contratacao'] . ' - ' . $titulo, ENT_QUOTES, 'UTF-8'); 
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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

<!-- Modal de Parâmetros do Relatório -->
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

    <!-- Modal de Detalhes -->
<div id="modalDetalhes" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="file-text"></i> Detalhes da Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalDetalhes')">&times;</span>
        </div>
        <div class="modal-body" id="detalhesContent">
            <!-- Conteúdo será carregado via AJAX -->
        </div>
    </div>
</div>

<!-- Modal de Edição -->
<div id="modalEdicao" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="edit"></i> Editar Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalEdicao')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formEditarLicitacao">
                <input type="hidden" id="edit_id" name="id">
                <input type="hidden" name="acao" value="editar_licitacao">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>NUP *</label>
                        <input type="text" id="edit_nup" name="nup" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Data Entrada DIPLI</label>
                        <input type="date" id="edit_data_entrada_dipli" name="data_entrada_dipli">
                    </div>
                    
                    <div class="form-group">
                        <label>Responsável Instrução</label>
                        <input type="text" id="edit_resp_instrucao" name="resp_instrucao">
                    </div>
                    
                    <div class="form-group">
                        <label>Área Demandante</label>
                        <input type="text" id="edit_area_demandante" name="area_demandante">
                    </div>
                    
                    <div class="form-group">
                        <label>Pregoeiro</label>
                        <input type="text" id="edit_pregoeiro" name="pregoeiro">
                    </div>
                    
                    <div class="form-group">
                        <label>Modalidade *</label>
                        <select id="edit_modalidade" name="modalidade" required>
                            <option value="DISPENSA">DISPENSA</option>
                            <option value="PREGAO">PREGÃO</option>
                            <option value="RDC">RDC</option>
                            <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo *</label>
                        <select id="edit_tipo" name="tipo" required>
                            <option value="TRADICIONAL">TRADICIONAL</option>
                            <option value="COTACAO">COTAÇÃO</option>
                            <option value="SRP">SRP</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Número</label>
                        <input type="number" id="edit_numero" name="numero">
                    </div>
                    
                    <div class="form-group">
                        <label>Ano</label>
                        <input type="number" id="edit_ano" name="ano">
                    </div>
                    
                    <div class="form-group">
                        <label>Valor Estimado (R$)</label>
                        <input type="text" id="edit_valor_estimado" name="valor_estimado">
                    </div>
                    
                    <div class="form-group">
                        <label>Data Abertura</label>
                        <input type="date" id="edit_data_abertura" name="data_abertura">
                    </div>
                    
                    <div class="form-group">
                        <label>Situação *</label>
                        <select id="edit_situacao" name="situacao" required>
                            <option value="EM_ANDAMENTO">EM ANDAMENTO</option>
                            <option value="REVOGADO">REVOGADO</option>
                            <option value="FRACASSADO">FRACASSADO</option>
                            <option value="HOMOLOGADO">HOMOLOGADO</option>
                        </select>
                    </div>
                    
                    <div class="form-group form-full">
                        <label>Objeto *</label>
                        <textarea id="edit_objeto" name="objeto" required rows="3"></textarea>
                    </div>
                    
                    <!-- Campos adicionais para homologação -->
                    <div id="camposHomologacao" style="display: none;" class="form-full">
                        <h4 style="margin-top: 20px; color: #2c3e50;">Dados da Homologação</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Data Homologação</label>
                                <input type="date" id="edit_data_homologacao" name="data_homologacao">
                            </div>
                            <div class="form-group">
                                <label>Qtd Homologada</label>
                                <input type="number" id="edit_qtd_homol" name="qtd_homol">
                            </div>
                            <div class="form-group">
                                <label>Valor Homologado</label>
                                <input type="text" id="edit_valor_homologado" name="valor_homologado">
                            </div>
                            <div class="form-group">
                                <label>Economia</label>
                                <input type="text" id="edit_economia" name="economia" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalEdicao')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Exportação -->
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
                <div class="form-group">
                    <label>Formato de Exportação</label>
                    <select id="formato_export" name="formato" required>
                        <option value="csv">CSV (Excel)</option>
                        <option value="pdf">PDF</option>
                        <option value="json">JSON</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Filtros</label>
                    <div style="margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                            <input type="checkbox" id="export_filtros" checked>
                            Aplicar filtros atuais da tabela
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Campos para Exportar</label>
                    <div style="margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="nup" checked> NUP
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="modalidade" checked> Modalidade
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="tipo" checked> Tipo
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="numero_ano" checked> Número/Ano
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
                            <input type="checkbox" name="campos[]" value="area_demandante"> Área Demandante
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="resp_instrucao"> Resp. Instrução
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

        // Função para abrir modal de relatório
function gerarRelatorio(tipo) {
    const modal = document.getElementById('modalRelatorio');
    const titulo = document.getElementById('tituloRelatorio');
    document.getElementById('tipo_relatorio').value = tipo;
    
    // Resetar formulário
    document.getElementById('formRelatorio').reset();
    document.getElementById('rel_data_final').value = new Date().toISOString().split('T')[0];
    
    // Configurar título e campos específicos
    switch(tipo) {
        case 'modalidade':
            titulo.textContent = 'Relatório por Modalidade';
            document.getElementById('filtroModalidade').style.display = 'none';
            document.getElementById('filtroPregoeiro').style.display = 'none';
            break;
            
        case 'pregoeiro':
            titulo.textContent = 'Relatório por Pregoeiro';
            document.getElementById('filtroModalidade').style.display = 'block';
            document.getElementById('filtroPregoeiro').style.display = 'block';
            break;
            
        case 'prazos':
            titulo.textContent = 'Relatório de Prazos';
            document.getElementById('filtroModalidade').style.display = 'block';
            document.getElementById('filtroPregoeiro').style.display = 'none';
            break;
            
        case 'financeiro':
            titulo.textContent = 'Relatório Financeiro';
            document.getElementById('filtroModalidade').style.display = 'block';
            document.getElementById('filtroPregoeiro').style.display = 'none';
            break;
    }
    
    modal.style.display = 'block';
}

// Submit do formulário de relatório
document.getElementById('formRelatorio').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const params = new URLSearchParams();
    
    for (const [key, value] of formData) {
        if (value) params.append(key, value);
    }
    
    const formato = formData.get('formato');
    const url = 'gerar_relatorio_licitacao.php?' + params.toString();
    
    if (formato === 'html') {
        // Abrir em nova aba
        window.open(url, '_blank');
    } else {
        // Download direto
        window.location.href = url;
    }
    
    fecharModal('modalRelatorio');
});

        // Função para ver detalhes
function verDetalhes(id) {
    const modal = document.getElementById('modalDetalhes');
    const content = document.getElementById('detalhesContent');
    
    // Mostrar loading
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i> Carregando...</div>';
    modal.style.display = 'block';
    
    // Buscar dados via AJAX
    fetch('get_licitacao.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const lic = data.data;
                content.innerHTML = `
                    <div style="display: grid; gap: 25px;">
                        <!-- Informações Principais -->
                        <div>
                            <h4 style="margin: 0 0 20px 0; color: #2c3e50; padding-bottom: 10px; border-bottom: 2px solid #f8f9fa;">
                                <i data-lucide="info"></i> Informações Gerais
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">NUP</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.nup}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Modalidade</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">
                                        <span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 14px; font-weight: 600;">${lic.modalidade}</span>
                                    </div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Tipo</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.tipo}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Número/Ano</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.numero || '-'}/${lic.ano || '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Situação</label>
                                    <div style="font-size: 16px; margin-top: 5px;">
                                        <span class="status-badge status-${lic.situacao.toLowerCase().replace('_', '-')}">${lic.situacao.replace('_', ' ')}</span>
                                    </div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Valor Estimado</label>
                                    <div style="font-size: 16px; color: #27ae60; font-weight: 600; margin-top: 5px;">R$ ${parseFloat(lic.valor_estimado).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Objeto -->
                        <div>
                            <h4 style="margin: 0 0 15px 0; color: #2c3e50;">
                                <i data-lucide="file-text"></i> Objeto
                            </h4>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; line-height: 1.6;">
                                ${lic.objeto}
                            </div>
                        </div>
                        
                        <!-- Datas e Responsáveis -->
                        <div>
                            <h4 style="margin: 0 0 20px 0; color: #2c3e50; padding-bottom: 10px; border-bottom: 2px solid #f8f9fa;">
                                <i data-lucide="calendar"></i> Datas e Responsáveis
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Data Entrada DIPLI</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.data_entrada_dipli ? new Date(lic.data_entrada_dipli).toLocaleDateString('pt-BR') : '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Data Abertura</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.data_abertura ? new Date(lic.data_abertura).toLocaleDateString('pt-BR') : '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Pregoeiro</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.pregoeiro || '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Área Demandante</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.area_demandante || '-'}</div>
                                </div>
                            </div>
                        </div>
                        
                        ${lic.situacao === 'HOMOLOGADO' ? `
                        <!-- Dados da Homologação -->
                        <div>
                            <h4 style="margin: 0 0 20px 0; color: #27ae60; padding-bottom: 10px; border-bottom: 2px solid #d4edda;">
                                <i data-lucide="check-circle"></i> Dados da Homologação
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Data Homologação</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.data_homologacao ? new Date(lic.data_homologacao).toLocaleDateString('pt-BR') : '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Qtd Homologada</label>
                                    <div style="font-size: 16px; color: #2c3e50; margin-top: 5px;">${lic.qtd_homol || '-'}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Valor Homologado</label>
                                    <div style="font-size: 16px; color: #27ae60; font-weight: 600; margin-top: 5px;">R$ ${parseFloat(lic.valor_homologado || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #6c757d; font-size: 12px; text-transform: uppercase;">Economia</label>
                                    <div style="font-size: 16px; color: #3498db; font-weight: 600; margin-top: 5px;">R$ ${parseFloat(lic.economia || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Metadados -->
                        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; color: #6c757d; font-size: 14px;">
                            <p style="margin: 0;">Criado por: <strong>${lic.usuario_nome}</strong> em ${new Date(lic.criado_em).toLocaleString('pt-BR')}</p>
                            ${lic.atualizado_em !== lic.criado_em ? `<p style="margin: 5px 0 0 0;">Última atualização: ${new Date(lic.atualizado_em).toLocaleString('pt-BR')}</p>` : ''}
                        </div>
                    </div>
                `;
                
                // Recriar ícones Lucide
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            } else {
                content.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;">Erro ao carregar detalhes da licitação</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;">Erro ao conectar com o servidor</div>';
        });
}

        // Função para editar licitação
function editarLicitacao(id) {
    const modal = document.getElementById('modalEdicao');
    
    // Buscar dados via AJAX
    fetch('get_licitacao.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const lic = data.data;
                
                // Preencher formulário
                document.getElementById('edit_id').value = lic.id;
                document.getElementById('edit_nup').value = lic.nup;
                document.getElementById('edit_data_entrada_dipli').value = lic.data_entrada_dipli;
                document.getElementById('edit_resp_instrucao').value = lic.resp_instrucao || '';
                document.getElementById('edit_area_demandante').value = lic.area_demandante || '';
                document.getElementById('edit_pregoeiro').value = lic.pregoeiro || '';
                document.getElementById('edit_modalidade').value = lic.modalidade;
                document.getElementById('edit_tipo').value = lic.tipo;
                document.getElementById('edit_numero').value = lic.numero || '';
                document.getElementById('edit_ano').value = lic.ano || '';
                document.getElementById('edit_valor_estimado').value = lic.valor_estimado ? parseFloat(lic.valor_estimado).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '';
                document.getElementById('edit_data_abertura').value = lic.data_abertura;
                document.getElementById('edit_situacao').value = lic.situacao;
                document.getElementById('edit_objeto').value = lic.objeto;
                
                // Se for homologado, mostrar campos extras
                if (lic.situacao === 'HOMOLOGADO') {
                    document.getElementById('camposHomologacao').style.display = 'block';
                    document.getElementById('edit_data_homologacao').value = lic.data_homologacao || '';
                    document.getElementById('edit_qtd_homol').value = lic.qtd_homol || '';
                    document.getElementById('edit_valor_homologado').value = lic.valor_homologado ? parseFloat(lic.valor_homologado).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '';
                    document.getElementById('edit_economia').value = lic.economia ? parseFloat(lic.economia).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '';
                }
                
                modal.style.display = 'block';
            } else {
                alert('Erro ao carregar dados da licitação');
            }
        })
        .catch(error => {
            alert('Erro ao conectar com o servidor');
        });
}

        // Função para exportar dados
function exportarDados() {
    document.getElementById('modalExportar').style.display = 'block';
}

// Função genérica para fechar modais
function fecharModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Event listeners
document.getElementById('edit_situacao').addEventListener('change', function() {
    if (this.value === 'HOMOLOGADO') {
        document.getElementById('camposHomologacao').style.display = 'block';
    } else {
        document.getElementById('camposHomologacao').style.display = 'none';
    }
});

// Calcular economia automaticamente
document.getElementById('edit_valor_homologado').addEventListener('input', function() {
    const valorEstimado = parseFloat(document.getElementById('edit_valor_estimado').value.replace(/\./g, '').replace(',', '.')) || 0;
    const valorHomologado = parseFloat(this.value.replace(/\./g, '').replace(',', '.')) || 0;
    const economia = valorEstimado - valorHomologado;
    document.getElementById('edit_economia').value = economia.toLocaleString('pt-BR', {minimumFractionDigits: 2});
});

// Submit do formulário de edição
document.getElementById('formEditarLicitacao').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Converter valores monetários
    ['valor_estimado', 'valor_homologado', 'economia'].forEach(field => {
        const value = formData.get(field);
        if (value) {
            formData.set(field, value.replace(/\./g, '').replace(',', '.'));
        }
    });
    
    fetch('process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Erro ao salvar alterações');
        }
    })
    .catch(error => {
        alert('Erro ao processar requisição');
    });
});

// Submit do formulário de exportação
document.getElementById('formExportar').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formato = document.getElementById('formato_export').value;
    const campos = Array.from(document.querySelectorAll('input[name="campos[]"]:checked')).map(cb => cb.value);
    const aplicarFiltros = document.getElementById('export_filtros').checked;
    
    // Pegar situação atual do filtro se aplicável
    let situacao = '';
    if (aplicarFiltros) {
        const filtroAtual = document.querySelector('#lista-licitacoes select').value;
        if (filtroAtual) situacao = filtroAtual;
    }
    
    // Construir URL
    const params = new URLSearchParams({
        formato: formato,
        campos: campos.join(','),
        situacao: situacao
    });
    
    // Abrir download
    window.open('exportar_licitacoes.php?' + params.toString(), '_blank');
    fecharModal('modalExportar');
});

// Fechar modal ao clicar fora
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Função para carregar dados do PCA
function carregarDadosPCA(numeroContratacao) {
    if (!numeroContratacao) {
        document.getElementById('area_demandante_criar').value = '';
        document.getElementById('valor_estimado_criar').value = '';
        return;
    }
    
    // Buscar dados via AJAX
    fetch('get_pca_data.php?numero_contratacao=' + encodeURIComponent(numeroContratacao))
        .then(response => response.json())
        .then(data => {
            if (!data.erro) {
                // Preencher campos automaticamente
                document.getElementById('area_demandante_criar').value = data.area_requisitante || '';
                
                // Formatar valor estimado
                if (data.valor_total_contratacao) {
                    const valor = parseFloat(data.valor_total_contratacao);
                    document.getElementById('valor_estimado_criar').value = valor.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                } else {
                    document.getElementById('valor_estimado_criar').value = '';
                }
            }
        })
        .catch(error => {
            console.log('Erro ao buscar dados do PCA:', error);
        });
}

// Função para formatar NUP
function formatarNUP(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 0) {
        value = value.substring(0, 17);
        let formatted = '';
        
        if (value.length > 0) {
            formatted = value.substring(0, 5);
        }
        if (value.length > 5) {
            formatted += '.' + value.substring(5, 11);
        }
        if (value.length > 11) {
            formatted += '/' + value.substring(11, 15);
        }
        if (value.length > 15) {
            formatted += '-' + value.substring(15, 17);
        }
        
        input.value = formatted;
    }
}

// Função para formatar valores monetários
function formatarValorMonetario(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 0) {
        value = (parseInt(value) / 100);
        input.value = value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        // Calcular economia após formatar o valor
        calcularEconomia();
    }
}

// Função para calcular economia
function calcularEconomia() {
    const valorEstimadoField = document.getElementById('valor_estimado_criar');
    const valorHomologadoField = document.getElementById('valor_homologado_criar');
    const economiaField = document.getElementById('economia_criar');
    
    if (!valorEstimadoField || !valorHomologadoField || !economiaField) {
        return;
    }
    
    // Converter valores para números
    const valorEstimadoStr = valorEstimadoField.value.replace(/\./g, '').replace(',', '.');
    const valorHomologadoStr = valorHomologadoField.value.replace(/\./g, '').replace(',', '.');
    
    const valorEstimado = parseFloat(valorEstimadoStr) || 0;
    const valorHomologado = parseFloat(valorHomologadoStr) || 0;
    
    if (valorEstimado > 0 && valorHomologado > 0) {
        const economia = valorEstimado - valorHomologado;
        economiaField.value = economia.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        economiaField.value = '';
    }
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

            // Máscaras e formatação para formulário de criação
const nupInput = document.getElementById('nup_criar');
if (nupInput) {
    nupInput.addEventListener('input', function() {
        formatarNUP(this);
    });
}

const valorEstimadoInput = document.getElementById('valor_estimado_criar');
if (valorEstimadoInput) {
    valorEstimadoInput.addEventListener('input', function() {
        formatarValorMonetario(this);
    });
    
    valorEstimadoInput.addEventListener('blur', function() {
        calcularEconomia();
    });
}

const valorHomologadoInput = document.getElementById('valor_homologado_criar');
if (valorHomologadoInput) {
    valorHomologadoInput.addEventListener('input', function() {
        formatarValorMonetario(this);
    });
    
    valorHomologadoInput.addEventListener('blur', function() {
        calcularEconomia();
    });
}
        });

document.getElementById('nup_criar').addEventListener('input', function (e) {
    let v = e.target.value.replace(/\D/g, ''); // Remove tudo que não for número

    if (v.length > 17) v = v.slice(0, 17); // Limita a 17 dígitos

    // Aplica a máscara
    let formatado = '';
    if (v.length > 0) formatado += v.slice(0, 5);
    if (v.length > 5) formatado += '.' + v.slice(5, 11);
    if (v.length > 11) formatado += '/' + v.slice(11, 15);
    if (v.length > 15) formatado += '-' + v.slice(15, 17);

    e.target.value = formatado;
});

// Função para preencher dados do PCA selecionado
function preencherDadosPCA() {
    const select = document.getElementById('select_contratacao');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value && selectedOption.getAttribute('data-titulo')) {
        // Preencher o objeto com o título da contratação
        document.getElementById('objeto_textarea').value = selectedOption.getAttribute('data-titulo');
    } else {
        document.getElementById('objeto_textarea').value = '';
    }
}

    </script>
</body>
</html>