<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

$pdo = conectarDB();

// Parâmetros
$tipo = $_GET['tipo'] ?? '';
$data_inicial = $_GET['data_inicial'] ?? date('Y-01-01');
$data_final = $_GET['data_final'] ?? date('Y-m-d');
$modalidade = $_GET['modalidade'] ?? '';
$area_demandante = $_GET['area_demandante'] ?? '';
$status = $_GET['status'] ?? '';
$formato = $_GET['formato'] ?? 'html';
$incluir_graficos = isset($_GET['incluir_graficos']);

// Construir WHERE
$where = ['q.criado_em BETWEEN ? AND ?'];
$params = [$data_inicial . ' 00:00:00', $data_final . ' 23:59:59'];

if (!empty($modalidade)) {
    $where[] = 'q.modalidade = ?';
    $params[] = $modalidade;
}

if (!empty($area_demandante)) {
    $where[] = 'q.area_demandante LIKE ?';
    $params[] = '%' . $area_demandante . '%';
}

if (!empty($status)) {
    $where[] = 'q.status = ?';
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

// Gerar relatório baseado no tipo
switch ($tipo) {
    case 'status':
        gerarRelatorioStatus($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'modalidade':
        gerarRelatorioModalidade($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'area':
        gerarRelatorioArea($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'financeiro':
        gerarRelatorioFinanceiro($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    default:
        http_response_code(400);
        echo "Tipo de relatório inválido.";
        exit;
}

// ==================== FUNÇÕES DE RELATÓRIO ====================

function gerarRelatorioStatus($pdo, $where, $params, $formato, $incluir_graficos) {
    // Dados por status
    $sql = "SELECT 
            status,
            COUNT(*) as total,
            SUM(valor_estimado) as valor_total,
            AVG(valor_estimado) as valor_medio,
            MIN(valor_estimado) as valor_minimo,
            MAX(valor_estimado) as valor_maximo,
            COUNT(DISTINCT area_demandante) as areas_envolvidas,
            COUNT(DISTINCT modalidade) as modalidades_utilizadas,
            AVG(DATEDIFF(atualizado_em, criado_em)) as tempo_medio_processamento
            FROM qualificacoes q
            WHERE $where
            GROUP BY status
            ORDER BY total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLStatus($dados, $incluir_graficos, $params);
    } else {
        gerarCSVStatus($dados);
    }
}

function gerarRelatorioModalidade($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
            modalidade,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Aprovado' THEN 1 ELSE 0 END) as aprovadas,
            SUM(CASE WHEN status = 'Reprovado' THEN 1 ELSE 0 END) as reprovadas,
            SUM(CASE WHEN status = 'Em Análise' THEN 1 ELSE 0 END) as em_analise,
            SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(valor_estimado) as valor_total_estimado,
            AVG(valor_estimado) as valor_medio,
            ROUND(SUM(CASE WHEN status = 'Aprovado' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as taxa_aprovacao,
            AVG(CASE WHEN status IN ('Aprovado', 'Reprovado') AND atualizado_em != criado_em 
                THEN DATEDIFF(atualizado_em, criado_em) END) as tempo_medio_decisao
            FROM qualificacoes q
            WHERE $where
            GROUP BY modalidade
            ORDER BY total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLModalidade($dados, $incluir_graficos, $params);
    } else {
        gerarCSVModalidade($dados);
    }
}

function gerarRelatorioArea($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
            area_demandante,
            COUNT(*) as total_qualificacoes,
            AVG(valor_estimado) as valor_medio,
            SUM(CASE WHEN status = 'Aprovado' THEN 1 ELSE 0 END) as aprovadas,
            ROUND(SUM(CASE WHEN status = 'Aprovado' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as taxa_sucesso,
            SUM(valor_estimado) as valor_total_estimado,
            COUNT(DISTINCT modalidade) as modalidades_utilizadas,
            COUNT(DISTINCT responsavel) as responsaveis_envolvidos,
            AVG(CASE WHEN status IN ('Aprovado', 'Reprovado') AND atualizado_em != criado_em 
                THEN DATEDIFF(atualizado_em, criado_em) END) as tempo_medio_processamento
            FROM qualificacoes q
            WHERE $where
            GROUP BY area_demandante
            ORDER BY total_qualificacoes DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLArea($dados, $incluir_graficos, $params);
    } else {
        gerarCSVArea($dados);
    }
}

function gerarRelatorioFinanceiro($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
            DATE_FORMAT(criado_em, '%Y-%m') as mes,
            COUNT(*) as total_qualificacoes,
            SUM(valor_estimado) as valor_estimado_total,
            AVG(valor_estimado) as valor_medio,
            SUM(CASE WHEN status = 'Aprovado' THEN valor_estimado ELSE 0 END) as valor_aprovado_total,
            SUM(CASE WHEN status = 'Reprovado' THEN valor_estimado ELSE 0 END) as valor_reprovado_total,
            COUNT(DISTINCT modalidade) as modalidades_mes,
            COUNT(DISTINCT area_demandante) as areas_envolvidas_mes
            FROM qualificacoes q
            WHERE $where
            GROUP BY DATE_FORMAT(criado_em, '%Y-%m')
            ORDER BY mes DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLFinanceiro($dados, $incluir_graficos, $params);
    } else {
        gerarCSVFinanceiro($dados);
    }
}

// ==================== FUNÇÕES HTML ====================

function gerarHTMLStatus($dados, $incluir_graficos, $params) {
    $total_geral = array_sum(array_column($dados, 'total'));
    $valor_total = array_sum(array_column($dados, 'valor_total'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório por Status - Qualificações</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #f59e0b; text-align: center; margin-bottom: 30px; }
            .info { background: #fef3c7; padding: 15px; border-radius: 5px; margin-bottom: 30px; border-left: 4px solid #f59e0b; }
            .info p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #f59e0b; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #fef3c7; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #fef3c7; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #f59e0b; }
            .summary-card h3 { margin: 0 0 10px 0; color: #92400e; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; color: #b45309; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 15px;">
                <i data-lucide="pie-chart" style="width: 32px; height: 32px;"></i>
                Relatório de Qualificações por Status
            </h1>
            
            <div class="info">
                <p><strong><i data-lucide="calendar" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong><i data-lucide="file-text" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Total de Qualificações:</strong> <?php echo number_format($total_geral); ?></p>
                <p><strong><i data-lucide="dollar-sign" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Valor Total:</strong> <?php echo formatarMoeda($valor_total); ?></p>
                <p><strong><i data-lucide="clock" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>

            <div class="summary">
                <?php foreach ($dados as $item): ?>
                <div class="summary-card">
                    <h3><?php echo htmlspecialchars($item['status']); ?></h3>
                    <div class="value"><?php echo number_format($item['total']); ?></div>
                    <p><?php echo formatarMoeda($item['valor_total']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Valor Total</th>
                        <th>Valor Médio</th>
                        <th>Valor Mínimo</th>
                        <th>Valor Máximo</th>
                        <th>Áreas Envolvidas</th>
                        <th>Modalidades</th>
                        <th>Tempo Médio (dias)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['status']); ?></strong></td>
                        <td><?php echo number_format($item['total']); ?></td>
                        <td><?php echo formatarMoeda($item['valor_total']); ?></td>
                        <td><?php echo formatarMoeda($item['valor_medio']); ?></td>
                        <td><?php echo formatarMoeda($item['valor_minimo']); ?></td>
                        <td><?php echo formatarMoeda($item['valor_maximo']); ?></td>
                        <td><?php echo number_format($item['areas_envolvidas']); ?></td>
                        <td><?php echo number_format($item['modalidades_utilizadas']); ?></td>
                        <td><?php echo $item['tempo_medio_processamento'] ? number_format($item['tempo_medio_processamento'], 1) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($incluir_graficos): ?>
            <div class="grid">
                <div class="chart-container">
                    <canvas id="chartQuantidades"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="chartValores"></canvas>
                </div>
            </div>

            <script>
                // Gráfico de Quantidades
                const ctxQtd = document.getElementById('chartQuantidades').getContext('2d');
                new Chart(ctxQtd, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_column($dados, 'status')); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($dados, 'total')); ?>,
                            backgroundColor: ['#f59e0b', '#d97706', '#b45309', '#92400e', '#78350f']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: 'Distribuição por Status' }
                        }
                    }
                });

                // Gráfico de Valores
                const ctxVal = document.getElementById('chartValores').getContext('2d');
                new Chart(ctxVal, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($dados, 'status')); ?>,
                        datasets: [{
                            label: 'Valor Total (R$)',
                            data: <?php echo json_encode(array_column($dados, 'valor_total')); ?>,
                            backgroundColor: '#f59e0b'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: 'Valores por Status' }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR');
                                    }
                                }
                            }
                        }
                    }
                });
            </script>
            <?php endif; ?>
        </div>
        <script>
            // Inicializar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        </script>
    </body>
    </html>
    <?php
}

function gerarHTMLModalidade($dados, $incluir_graficos, $params) {
    $total_geral = array_sum(array_column($dados, 'total'));
    $valor_total = array_sum(array_column($dados, 'valor_total_estimado'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório por Modalidade - Qualificações</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #f59e0b; text-align: center; margin-bottom: 30px; }
            .info { background: #fef3c7; padding: 15px; border-radius: 5px; margin-bottom: 30px; border-left: 4px solid #f59e0b; }
            .info p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #f59e0b; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #fef3c7; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
            .success { color: #27ae60; font-weight: bold; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 15px;">
                <i data-lucide="bar-chart-3" style="width: 32px; height: 32px;"></i>
                Relatório de Qualificações por Modalidade
            </h1>
            
            <div class="info">
                <p><strong><i data-lucide="calendar" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong><i data-lucide="file-text" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Total de Qualificações:</strong> <?php echo number_format($total_geral); ?></p>
                <p><strong><i data-lucide="dollar-sign" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Valor Total:</strong> <?php echo formatarMoeda($valor_total); ?></p>
                <p><strong><i data-lucide="clock" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Modalidade</th>
                        <th>Total</th>
                        <th>Aprovadas</th>
                        <th>Reprovadas</th>
                        <th>Em Análise</th>
                        <th>Pendentes</th>
                        <th>Taxa Aprovação</th>
                        <th>Valor Total</th>
                        <th>Valor Médio</th>
                        <th>Tempo Médio Decisão</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['modalidade']); ?></strong></td>
                        <td><?php echo number_format($item['total']); ?></td>
                        <td><?php echo number_format($item['aprovadas']); ?></td>
                        <td><?php echo number_format($item['reprovadas']); ?></td>
                        <td><?php echo number_format($item['em_analise']); ?></td>
                        <td><?php echo number_format($item['pendentes']); ?></td>
                        <td class="success"><?php echo number_format($item['taxa_aprovacao'], 2); ?>%</td>
                        <td><?php echo formatarMoeda($item['valor_total_estimado']); ?></td>
                        <td><?php echo formatarMoeda($item['valor_medio']); ?></td>
                        <td><?php echo $item['tempo_medio_decisao'] ? number_format($item['tempo_medio_decisao'], 1) . ' dias' : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($incluir_graficos): ?>
            <div class="grid">
                <div class="chart-container">
                    <canvas id="chartModalidades"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="chartTaxas"></canvas>
                </div>
            </div>

            <script>
                // Gráfico por Modalidade
                const ctxMod = document.getElementById('chartModalidades').getContext('2d');
                new Chart(ctxMod, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($dados, 'modalidade')); ?>,
                        datasets: [{
                            label: 'Total Qualificações',
                            data: <?php echo json_encode(array_column($dados, 'total')); ?>,
                            backgroundColor: '#f59e0b'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: 'Qualificações por Modalidade' }
                        }
                    }
                });

                // Gráfico de Taxas de Aprovação
                const ctxTax = document.getElementById('chartTaxas').getContext('2d');
                new Chart(ctxTax, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_column($dados, 'modalidade')); ?>,
                        datasets: [{
                            label: 'Taxa de Aprovação (%)',
                            data: <?php echo json_encode(array_column($dados, 'taxa_aprovacao')); ?>,
                            borderColor: '#27ae60',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: 'Taxa de Aprovação por Modalidade' }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            </script>
            <?php endif; ?>
        </div>
        <script>
            // Inicializar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        </script>
    </body>
    </html>
    <?php
}

function gerarHTMLArea($dados, $incluir_graficos, $params) {
    $total_geral = array_sum(array_column($dados, 'total_qualificacoes'));
    $valor_total = array_sum(array_column($dados, 'valor_total_estimado'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório por Área Demandante - Qualificações</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #f59e0b; text-align: center; margin-bottom: 30px; }
            .info { background: #fef3c7; padding: 15px; border-radius: 5px; margin-bottom: 30px; border-left: 4px solid #f59e0b; }
            .info p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #f59e0b; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #fef3c7; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .performance { color: #27ae60; font-weight: bold; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 15px;">
                <i data-lucide="users" style="width: 32px; height: 32px;"></i>
                Relatório de Performance por Área Demandante
            </h1>
            
            <div class="info">
                <p><strong><i data-lucide="calendar" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong><i data-lucide="file-text" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Total de Qualificações:</strong> <?php echo number_format($total_geral); ?></p>
                <p><strong><i data-lucide="dollar-sign" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Valor Total:</strong> <?php echo formatarMoeda($valor_total); ?></p>
                <p><strong><i data-lucide="clock" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Área Demandante</th>
                        <th>Total Qualificações</th>
                        <th>Aprovadas</th>
                        <th>Taxa Sucesso</th>
                        <th>Valor Total</th>
                        <th>Valor Médio</th>
                        <th>Modalidades</th>
                        <th>Responsáveis</th>
                        <th>Tempo Médio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['area_demandante']); ?></strong></td>
                        <td><?php echo number_format($item['total_qualificacoes']); ?></td>
                        <td><?php echo number_format($item['aprovadas']); ?></td>
                        <td class="performance"><?php echo number_format($item['taxa_sucesso'], 2); ?>%</td>
                        <td><?php echo formatarMoeda($item['valor_total_estimado']); ?></td>
                        <td><?php echo formatarMoeda($item['valor_medio']); ?></td>
                        <td><?php echo number_format($item['modalidades_utilizadas']); ?></td>
                        <td><?php echo number_format($item['responsaveis_envolvidos']); ?></td>
                        <td><?php echo $item['tempo_medio_processamento'] ? number_format($item['tempo_medio_processamento'], 1) . ' dias' : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($incluir_graficos): ?>
            <div class="chart-container">
                <canvas id="chartAreas"></canvas>
            </div>

            <script>
                const ctx = document.getElementById('chartAreas').getContext('2d');
                new Chart(ctx, {
                    type: 'horizontalBar',
                    data: {
                        labels: <?php echo json_encode(array_map(function($item) { 
                            return strlen($item['area_demandante']) > 30 ? 
                                   substr($item['area_demandante'], 0, 30) . '...' : 
                                   $item['area_demandante']; 
                        }, $dados)); ?>,
                        datasets: [{
                            label: 'Qualificações por Área',
                            data: <?php echo json_encode(array_column($dados, 'total_qualificacoes')); ?>,
                            backgroundColor: '#f59e0b'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: 'Desempenho por Área Demandante' }
                        }
                    }
                });
            </script>
            <?php endif; ?>
        </div>
        <script>
            // Inicializar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        </script>
    </body>
    </html>
    <?php
}

function gerarHTMLFinanceiro($dados, $incluir_graficos, $params) {
    $valor_total_estimado = array_sum(array_column($dados, 'valor_estimado_total'));
    $valor_aprovado_total = array_sum(array_column($dados, 'valor_aprovado_total'));
    $valor_reprovado_total = array_sum(array_column($dados, 'valor_reprovado_total'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório Financeiro - Qualificações</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #f59e0b; text-align: center; margin-bottom: 30px; }
            .info { background: #fef3c7; padding: 15px; border-radius: 5px; margin-bottom: 30px; border-left: 4px solid #f59e0b; }
            .info p { margin: 5px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #fef3c7; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #f59e0b; }
            .summary-card h3 { margin: 0 0 10px 0; color: #92400e; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; color: #b45309; }
            .summary-card.success .value { color: #27ae60; }
            .summary-card.danger .value { color: #e74c3c; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #f59e0b; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #fef3c7; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 15px;">
                <i data-lucide="trending-up" style="width: 32px; height: 32px;"></i>
                Relatório Financeiro de Qualificações
            </h1>
            
            <div class="info">
                <p><strong><i data-lucide="calendar" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong><i data-lucide="clock" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></i>Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>

            <div class="summary">
                <div class="summary-card">
                    <h3><i data-lucide="dollar-sign" style="width: 20px; height: 20px; display: inline-block; margin-right: 8px;"></i>Valor Total Estimado</h3>
                    <div class="value"><?php echo formatarMoeda($valor_total_estimado); ?></div>
                </div>
                <div class="summary-card success">
                    <h3><i data-lucide="check-circle" style="width: 20px; height: 20px; display: inline-block; margin-right: 8px;"></i>Valor Aprovado</h3>
                    <div class="value"><?php echo formatarMoeda($valor_aprovado_total); ?></div>
                    <small><?php echo $valor_total_estimado > 0 ? number_format(($valor_aprovado_total / $valor_total_estimado) * 100, 2) : 0; ?>%</small>
                </div>
                <div class="summary-card danger">
                    <h3><i data-lucide="x-circle" style="width: 20px; height: 20px; display: inline-block; margin-right: 8px;"></i>Valor Reprovado</h3>
                    <div class="value"><?php echo formatarMoeda($valor_reprovado_total); ?></div>
                    <small><?php echo $valor_total_estimado > 0 ? number_format(($valor_reprovado_total / $valor_total_estimado) * 100, 2) : 0; ?>%</small>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th>Total Qualificações</th>
                        <th>Valor Estimado</th>
                        <th>Valor Médio</th>
                        <th>Valor Aprovado</th>
                        <th>Valor Reprovado</th>
                        <th>Modalidades</th>
                        <th>Áreas Envolvidas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $item): ?>
                    <tr>
                        <td><strong><?php echo date('m/Y', strtotime($item['mes'] . '-01')); ?></strong></td>
                        <td><?php echo number_format($item['total_qualificacoes']); ?></td>
                        <td><?php echo formatarMoeda($item['valor_estimado_total']); ?></td>
                        <td><?php echo formatarMoeda($item['valor_medio']); ?></td>
                        <td style="color: #27ae60;"><?php echo formatarMoeda($item['valor_aprovado_total']); ?></td>
                        <td style="color: #e74c3c;"><?php echo formatarMoeda($item['valor_reprovado_total']); ?></td>
                        <td><?php echo number_format($item['modalidades_mes']); ?></td>
                        <td><?php echo number_format($item['areas_envolvidas_mes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($incluir_graficos): ?>
            <div class="grid">
                <div class="chart-container">
                    <canvas id="chartEvolucao"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="chartDistribuicao"></canvas>
                </div>
            </div>

            <script>
                // Gráfico de Evolução Mensal
                const ctxEvol = document.getElementById('chartEvolucao').getContext('2d');
                new Chart(ctxEvol, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_map(function($item) { 
                            return date('m/Y', strtotime($item['mes'] . '-01')); 
                        }, array_reverse($dados))); ?>,
                        datasets: [{
                            label: 'Valor Estimado (R$)',
                            data: <?php echo json_encode(array_column(array_reverse($dados), 'valor_estimado_total')); ?>,
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: 'Evolução Mensal dos Valores' }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR');
                                    }
                                }
                            }
                        }
                    }
                });

                // Gráfico de Distribuição
                const ctxDist = document.getElementById('chartDistribuicao').getContext('2d');
                new Chart(ctxDist, {
                    type: 'doughnut',
                    data: {
                        labels: ['Aprovado', 'Reprovado', 'Outros'],
                        datasets: [{
                            data: [
                                <?php echo $valor_aprovado_total; ?>,
                                <?php echo $valor_reprovado_total; ?>,
                                <?php echo $valor_total_estimado - $valor_aprovado_total - $valor_reprovado_total; ?>
                            ],
                            backgroundColor: ['#27ae60', '#e74c3c', '#f39c12']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: 'Distribuição por Status' }
                        }
                    }
                });
            </script>
            <?php endif; ?>
        </div>
        <script>
            // Inicializar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        </script>
    </body>
    </html>
    <?php
}

// ==================== FUNÇÕES CSV ====================

function gerarCSVStatus($dados) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_qualificacao_status_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Status', 'Total', 'Valor Total', 'Valor Médio', 'Áreas Envolvidas', 'Modalidades', 'Tempo Médio']);
    
    foreach ($dados as $item) {
        fputcsv($output, [
            $item['status'],
            $item['total'],
            $item['valor_total'],
            $item['valor_medio'],
            $item['areas_envolvidas'],
            $item['modalidades_utilizadas'],
            $item['tempo_medio_processamento']
        ]);
    }
    
    fclose($output);
}

function gerarCSVModalidade($dados) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_qualificacao_modalidade_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Modalidade', 'Total', 'Aprovadas', 'Reprovadas', 'Taxa Aprovação', 'Valor Total', 'Tempo Médio']);
    
    foreach ($dados as $item) {
        fputcsv($output, [
            $item['modalidade'],
            $item['total'],
            $item['aprovadas'],
            $item['reprovadas'],
            $item['taxa_aprovacao'],
            $item['valor_total_estimado'],
            $item['tempo_medio_decisao']
        ]);
    }
    
    fclose($output);
}

function gerarCSVArea($dados) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_qualificacao_area_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Área Demandante', 'Total', 'Aprovadas', 'Taxa Sucesso', 'Valor Total', 'Modalidades', 'Responsáveis']);
    
    foreach ($dados as $item) {
        fputcsv($output, [
            $item['area_demandante'],
            $item['total_qualificacoes'],
            $item['aprovadas'],
            $item['taxa_sucesso'],
            $item['valor_total_estimado'],
            $item['modalidades_utilizadas'],
            $item['responsaveis_envolvidos']
        ]);
    }
    
    fclose($output);
}

function gerarCSVFinanceiro($dados) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_qualificacao_financeiro_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Mês', 'Total', 'Valor Estimado', 'Valor Aprovado', 'Valor Reprovado', 'Modalidades', 'Areas']);
    
    foreach ($dados as $item) {
        fputcsv($output, [
            $item['mes'],
            $item['total_qualificacoes'],
            $item['valor_estimado_total'],
            $item['valor_aprovado_total'],
            $item['valor_reprovado_total'],
            $item['modalidades_mes'],
            $item['areas_envolvidas_mes']
        ]);
    }
    
    fclose($output);
}
?>