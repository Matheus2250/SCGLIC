<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

$pdo = conectarDB();

// Verificar se as tabelas existem
$stmt = $pdo->query("SHOW TABLES LIKE 'contratos'");
$tablesExist = $stmt && $stmt->rowCount() > 0;

if (!$tablesExist) {
    die('Módulo de contratos não foi configurado. Execute o setup primeiro.');
}

// Parâmetros
$tipo = $_GET['tipo'] ?? '';
$data_inicial = $_GET['data_inicial'] ?? date('Y-01-01');
$data_final = $_GET['data_final'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$modalidade = $_GET['modalidade'] ?? '';
$formato = $_GET['formato'] ?? 'html';
$incluir_graficos = isset($_GET['incluir_graficos']);

// Construir WHERE
$where = ['c.data_assinatura BETWEEN ? AND ?'];
$params = [$data_inicial, $data_final];

if (!empty($status)) {
    $where[] = 'c.status_contrato = ?';
    $params[] = $status;
}

if (!empty($modalidade)) {
    $where[] = 'c.modalidade LIKE ?';
    $params[] = '%' . $modalidade . '%';
}

$whereClause = implode(' AND ', $where);

// Gerar relatório baseado no tipo
switch ($tipo) {
    case 'modalidade':
        gerarRelatorioModalidade($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'status':
        gerarRelatorioStatus($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'prazos':
        gerarRelatorioPrazos($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    case 'financeiro':
        gerarRelatorioFinanceiro($pdo, $whereClause, $params, $formato, $incluir_graficos);
        break;
        
    default:
        die('Tipo de relatório inválido');
}

// Função: Relatório por Modalidade
function gerarRelatorioModalidade($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
        c.modalidade,
        COUNT(*) as total_contratos,
        SUM(c.valor_total) as valor_total,
        AVG(c.valor_total) as valor_medio,
        COUNT(CASE WHEN c.status_contrato = 'vigente' THEN 1 END) as vigentes,
        COUNT(CASE WHEN c.status_contrato = 'encerrado' THEN 1 END) as encerrados,
        COUNT(CASE WHEN c.data_fim_vigencia < CURDATE() AND c.status_contrato = 'vigente' THEN 1 END) as vencidos,
        COUNT(CASE WHEN c.data_fim_vigencia BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as vencendo_30_dias,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM contratos c2 WHERE $where), 2) as percentual
        FROM contratos c 
        WHERE $where AND c.modalidade IS NOT NULL
        GROUP BY c.modalidade
        ORDER BY valor_total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLModalidade($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFModalidade($dados, $incluir_graficos);
    } else {
        gerarExcelModalidade($dados);
    }
}

// Função: Relatório por Status
function gerarRelatorioStatus($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
        c.status_contrato,
        COUNT(*) as total_contratos,
        SUM(c.valor_total) as valor_total,
        AVG(c.valor_total) as valor_medio,
        SUM(c.valor_empenhado) as valor_empenhado,
        SUM(c.valor_pago) as valor_pago,
        AVG(DATEDIFF(c.data_fim_vigencia, c.data_inicio_vigencia)) as prazo_medio_dias,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM contratos c2 WHERE $where), 2) as percentual
        FROM contratos c 
        WHERE $where
        GROUP BY c.status_contrato
        ORDER BY valor_total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLStatus($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFStatus($dados, $incluir_graficos);
    } else {
        gerarExcelStatus($dados);
    }
}

// Função: Relatório de Prazos
function gerarRelatorioPrazos($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
        c.modalidade,
        COUNT(*) as total_contratos,
        COUNT(CASE WHEN c.data_fim_vigencia < CURDATE() AND c.status_contrato = 'vigente' THEN 1 END) as vencidos,
        COUNT(CASE WHEN c.data_fim_vigencia BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as vencendo_30_dias,
        COUNT(CASE WHEN c.data_fim_vigencia BETWEEN DATE_ADD(CURDATE(), INTERVAL 31 DAY) AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 1 END) as vencendo_90_dias,
        AVG(DATEDIFF(c.data_fim_vigencia, c.data_inicio_vigencia)) as prazo_medio_dias,
        MIN(c.data_fim_vigencia) as proxima_vigencia,
        SUM(CASE WHEN c.data_fim_vigencia < CURDATE() AND c.status_contrato = 'vigente' THEN c.valor_total ELSE 0 END) as valor_vencidos
        FROM contratos c 
        WHERE $where AND c.modalidade IS NOT NULL
        GROUP BY c.modalidade
        ORDER BY vencidos DESC, vencendo_30_dias DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLPrazos($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFPrazos($dados, $incluir_graficos);
    } else {
        gerarExcelPrazos($dados);
    }
}

// Função: Relatório Financeiro
function gerarRelatorioFinanceiro($pdo, $where, $params, $formato, $incluir_graficos) {
    $sql = "SELECT 
        DATE_FORMAT(c.data_assinatura, '%Y-%m') as mes,
        COUNT(*) as total_contratos,
        SUM(c.valor_total) as valor_total,
        SUM(c.valor_empenhado) as valor_empenhado,
        SUM(c.valor_pago) as valor_pago,
        AVG(c.valor_total) as valor_medio,
        COUNT(CASE WHEN c.status_contrato = 'vigente' THEN 1 END) as contratos_vigentes,
        COUNT(CASE WHEN c.status_contrato = 'encerrado' THEN 1 END) as contratos_encerrados
        FROM contratos c 
        WHERE $where
        GROUP BY DATE_FORMAT(c.data_assinatura, '%Y-%m')
        ORDER BY mes";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if ($formato === 'html') {
        gerarHTMLFinanceiro($dados, $incluir_graficos, $params);
    } elseif ($formato === 'pdf') {
        gerarPDFFinanceiro($dados, $incluir_graficos);
    } else {
        gerarExcelFinanceiro($dados);
    }
}

// Função: Gerar HTML - Modalidade
function gerarHTMLModalidade($dados, $incluir_graficos, $params) {
    $total_contratos = array_sum(array_column($dados, 'total_contratos'));
    $valor_total = array_sum(array_column($dados, 'valor_total'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório por Modalidade - Contratos</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #7c3aed; }
            .summary-card h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; color: #7c3aed; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
            .status-vigente { background: #27ae60; color: white; }
            .status-encerrado { background: #95a5a6; color: white; }
            .status-vencido { background: #e74c3c; color: white; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório de Contratos por Modalidade</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Contratos:</strong> <?php echo $total_contratos; ?></p>
                <p><strong>Valor Total:</strong> R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-card">
                    <h3>Modalidades</h3>
                    <div class="value"><?php echo count($dados); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Valor Médio</h3>
                    <div class="value">R$ <?php echo number_format($valor_total / max($total_contratos, 1), 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Contratos Vigentes</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'vigentes')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Vencidos</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'vencidos')); ?></div>
                </div>
            </div>

            <?php if ($incluir_graficos): ?>
            <div class="chart-container">
                <canvas id="modalidadeChart"></canvas>
            </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Modalidade</th>
                        <th>Total</th>
                        <th>Valor Total</th>
                        <th>Valor Médio</th>
                        <th>Vigentes</th>
                        <th>Encerrados</th>
                        <th>Vencidos</th>
                        <th>%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['modalidade']); ?></td>
                        <td><?php echo number_format($row['total_contratos']); ?></td>
                        <td>R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($row['valor_medio'], 2, ',', '.'); ?></td>
                        <td><span class="status-badge status-vigente"><?php echo $row['vigentes']; ?></span></td>
                        <td><span class="status-badge status-encerrado"><?php echo $row['encerrados']; ?></span></td>
                        <td><span class="status-badge status-vencido"><?php echo $row['vencidos']; ?></span></td>
                        <td><?php echo $row['percentual']; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($incluir_graficos): ?>
            <script>
                const ctxModalidade = document.getElementById('modalidadeChart').getContext('2d');
                new Chart(ctxModalidade, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($dados, 'modalidade')); ?>,
                        datasets: [{
                            label: 'Valor Total (R$)',
                            data: <?php echo json_encode(array_column($dados, 'valor_total')); ?>,
                            backgroundColor: 'rgba(124, 58, 237, 0.8)',
                            borderColor: 'rgba(124, 58, 237, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
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
    </body>
    </html>
    <?php
}

// Função: Gerar HTML - Status
function gerarHTMLStatus($dados, $incluir_graficos, $params) {
    $total_contratos = array_sum(array_column($dados, 'total_contratos'));
    $valor_total = array_sum(array_column($dados, 'valor_total'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório por Status - Contratos</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #e74c3c; }
            .summary-card h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; color: #e74c3c; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório de Contratos por Status</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Contratos:</strong> <?php echo $total_contratos; ?></p>
                <p><strong>Valor Total:</strong> R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>

            <?php if ($incluir_graficos): ?>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Valor Total</th>
                        <th>Valor Médio</th>
                        <th>Empenhado</th>
                        <th>Pago</th>
                        <th>Prazo Médio (dias)</th>
                        <th>%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): ?>
                    <tr>
                        <td><?php echo ucfirst($row['status_contrato']); ?></td>
                        <td><?php echo number_format($row['total_contratos']); ?></td>
                        <td>R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($row['valor_medio'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($row['valor_empenhado'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($row['valor_pago'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($row['prazo_medio_dias']); ?></td>
                        <td><?php echo $row['percentual']; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($incluir_graficos): ?>
            <script>
                const ctxStatus = document.getElementById('statusChart').getContext('2d');
                new Chart(ctxStatus, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode(array_map('ucfirst', array_column($dados, 'status_contrato'))); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($dados, 'total_contratos')); ?>,
                            backgroundColor: [
                                'rgba(39, 174, 96, 0.8)',
                                'rgba(149, 165, 166, 0.8)',
                                'rgba(231, 76, 60, 0.8)',
                                'rgba(241, 196, 15, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            </script>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

// Função: Gerar HTML - Prazos
function gerarHTMLPrazos($dados, $incluir_graficos, $params) {
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório de Prazos - Contratos</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #f39c12; }
            .summary-card h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; color: #f39c12; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .warning { color: #e74c3c; font-weight: bold; }
            .alert-high { background: #fee; color: #e74c3c; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório de Análise de Prazos - Contratos</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Modalidades:</strong> <?php echo count($dados); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-card">
                    <h3>Total de Vencidos</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'vencidos')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Vencem em 30 dias</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'vencendo_30_dias')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Vencem em 90 dias</h3>
                    <div class="value"><?php echo array_sum(array_column($dados, 'vencendo_90_dias')); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Valor Vencidos</h3>
                    <div class="value">R$ <?php echo number_format(array_sum(array_column($dados, 'valor_vencidos')), 2, ',', '.'); ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Modalidade</th>
                        <th>Total</th>
                        <th>Vencidos</th>
                        <th>Vence 30d</th>
                        <th>Vence 90d</th>
                        <th>Prazo Médio</th>
                        <th>Próxima Vigência</th>
                        <th>Valor Vencidos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): ?>
                    <tr <?php echo $row['vencidos'] > 0 ? 'class="alert-high"' : ''; ?>>
                        <td><?php echo htmlspecialchars($row['modalidade']); ?></td>
                        <td><?php echo number_format($row['total_contratos']); ?></td>
                        <td class="warning"><?php echo $row['vencidos']; ?></td>
                        <td><?php echo $row['vencendo_30_dias']; ?></td>
                        <td><?php echo $row['vencendo_90_dias']; ?></td>
                        <td><?php echo number_format($row['prazo_medio_dias']); ?> dias</td>
                        <td><?php echo $row['proxima_vigencia'] ? date('d/m/Y', strtotime($row['proxima_vigencia'])) : '-'; ?></td>
                        <td>R$ <?php echo number_format($row['valor_vencidos'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </body>
    </html>
    <?php
}

// Função: Gerar HTML - Financeiro
function gerarHTMLFinanceiro($dados, $incluir_graficos, $params) {
    $valor_total = array_sum(array_column($dados, 'valor_total'));
    $valor_empenhado = array_sum(array_column($dados, 'valor_empenhado'));
    $valor_pago = array_sum(array_column($dados, 'valor_pago'));
    $total_contratos = array_sum(array_column($dados, 'total_contratos'));
    $data_inicial = date('d/m/Y', strtotime($params[0]));
    $data_final = date('d/m/Y', strtotime($params[1]));
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório Financeiro - Contratos</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
            .info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
            .info p { margin: 5px 0; }
            .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
            .summary-card h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 16px; }
            .summary-card .value { font-size: 24px; font-weight: bold; }
            .card-total { border-left: 4px solid #3498db; }
            .card-total .value { color: #3498db; }
            .card-empenhado { border-left: 4px solid #f39c12; }
            .card-empenhado .value { color: #f39c12; }
            .card-pago { border-left: 4px solid #27ae60; }
            .card-pago .value { color: #27ae60; }
            .card-pendente { border-left: 4px solid #e74c3c; }
            .card-pendente .value { color: #e74c3c; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .chart-container { width: 100%; margin: 30px 0; height: 400px; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Relatório Financeiro de Contratos</h1>
            
            <div class="info">
                <p><strong>Período:</strong> <?php echo $data_inicial; ?> a <?php echo $data_final; ?></p>
                <p><strong>Total de Meses:</strong> <?php echo count($dados); ?></p>
                <p><strong>Data de Geração:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-card card-total">
                    <h3>Valor Total</h3>
                    <div class="value">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card card-empenhado">
                    <h3>Valor Empenhado</h3>
                    <div class="value">R$ <?php echo number_format($valor_empenhado, 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card card-pago">
                    <h3>Valor Pago</h3>
                    <div class="value">R$ <?php echo number_format($valor_pago, 2, ',', '.'); ?></div>
                </div>
                <div class="summary-card card-pendente">
                    <h3>Valor Pendente</h3>
                    <div class="value">R$ <?php echo number_format($valor_empenhado - $valor_pago, 2, ',', '.'); ?></div>
                </div>
            </div>

            <?php if ($incluir_graficos): ?>
            <div class="grid">
                <div class="chart-container">
                    <canvas id="evolucaoChart"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="financeiroChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th>Contratos</th>
                        <th>Valor Total</th>
                        <th>Empenhado</th>
                        <th>Pago</th>
                        <th>Valor Médio</th>
                        <th>Vigentes</th>
                        <th>Encerrados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $row): ?>
                    <tr>
                        <td><?php echo date('m/Y', strtotime($row['mes'] . '-01')); ?></td>
                        <td><?php echo number_format($row['total_contratos']); ?></td>
                        <td>R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($row['valor_empenhado'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($row['valor_pago'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($row['valor_medio'], 2, ',', '.'); ?></td>
                        <td><?php echo $row['contratos_vigentes']; ?></td>
                        <td><?php echo $row['contratos_encerrados']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($incluir_graficos): ?>
            <script>
                // Gráfico de Evolução
                const ctxEvolucao = document.getElementById('evolucaoChart').getContext('2d');
                new Chart(ctxEvolucao, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_map(function($m) { return date('m/Y', strtotime($m['mes'] . '-01')); }, $dados)); ?>,
                        datasets: [{
                            label: 'Valor Total',
                            data: <?php echo json_encode(array_column($dados, 'valor_total')); ?>,
                            borderColor: 'rgba(52, 152, 219, 1)',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
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

                // Gráfico Financeiro
                const ctxFinanceiro = document.getElementById('financeiroChart').getContext('2d');
                new Chart(ctxFinanceiro, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pago', 'Empenhado (não pago)', 'Não empenhado'],
                        datasets: [{
                            data: [
                                <?php echo $valor_pago; ?>,
                                <?php echo $valor_empenhado - $valor_pago; ?>,
                                <?php echo $valor_total - $valor_empenhado; ?>
                            ],
                            backgroundColor: [
                                'rgba(39, 174, 96, 0.8)',
                                'rgba(241, 196, 15, 0.8)',
                                'rgba(231, 76, 60, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            </script>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

// Funções para PDF e Excel (stubs - implementar conforme necessário)
function gerarPDFModalidade($dados, $incluir_graficos) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exportação PDF não implementada ainda']);
}

function gerarExcelModalidade($dados) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exportação Excel não implementada ainda']);
}

function gerarPDFStatus($dados, $incluir_graficos) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exportação PDF não implementada ainda']);
}

function gerarExcelStatus($dados) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exportação Excel não implementada ainda']);
}

function gerarPDFPrazos($dados, $incluir_graficos) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exportação PDF não implementada ainda']);
}

function gerarExcelPrazos($dados) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exportação Excel não implementada ainda']);
}

function gerarPDFFinanceiro($dados, $incluir_graficos) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exportação PDF não implementada ainda']);
}

function gerarExcelFinanceiro($dados) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exportação Excel não implementada ainda']);
}
?>