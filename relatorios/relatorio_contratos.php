<?php
/**
 * Relatório Gerencial de Contratos
 * Sistema CGLIC - Ministério da Saúde
 * 
 * Gera relatórios com estatísticas e gráficos dos contratos
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Verificar login
if (!verificarLogin()) {
    header('Location: ../index.php');
    exit;
}

// Parâmetros do relatório
$formato = $_GET['formato'] ?? 'html';
$tipoRelatorio = $_GET['tipo'] ?? 'geral';
$dataInicio = $_GET['data_inicio'] ?? date('Y-01-01');
$dataFim = $_GET['data_fim'] ?? date('Y-12-31');
$status = $_GET['status'] ?? '';
$modalidade = $_GET['modalidade'] ?? '';

// Validar datas
$dataInicio = date('Y-m-d', strtotime($dataInicio));
$dataFim = date('Y-m-d', strtotime($dataFim));

try {
    // Verificar se as tabelas existem
    $tablesExist = $conn->query("SHOW TABLES LIKE 'contratos'")->num_rows > 0;
    
    if (!$tablesExist) {
        throw new Exception('Módulo de contratos não foi configurado. Execute o setup primeiro.');
    }
    
    // Construir filtros
    $whereConditions = ["c.uasg = '250110'"];
    $params = [];
    $types = '';
    
    // Filtro por data de assinatura
    $whereConditions[] = "c.data_assinatura BETWEEN ? AND ?";
    $params[] = $dataInicio;
    $params[] = $dataFim;
    $types .= 'ss';
    
    if ($status) {
        $whereConditions[] = "c.status_contrato = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if ($modalidade) {
        $whereConditions[] = "c.modalidade LIKE ?";
        $params[] = "%{$modalidade}%";
        $types .= 's';
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // 1. Estatísticas Gerais
    $statsQuery = "
        SELECT 
            COUNT(*) as total_contratos,
            COUNT(CASE WHEN c.status_contrato = 'vigente' THEN 1 END) as contratos_vigentes,
            COUNT(CASE WHEN c.status_contrato = 'encerrado' THEN 1 END) as contratos_encerrados,
            COUNT(CASE WHEN c.data_fim_vigencia < CURDATE() AND c.status_contrato = 'vigente' THEN 1 END) as contratos_vencidos,
            SUM(c.valor_total) as valor_total,
            SUM(c.valor_empenhado) as valor_empenhado,
            SUM(c.valor_pago) as valor_pago,
            AVG(c.valor_total) as valor_medio,
            MIN(c.valor_total) as menor_valor,
            MAX(c.valor_total) as maior_valor,
            COUNT(CASE WHEN c.valor_total >= 1000000 THEN 1 END) as contratos_grandes,
            COUNT(CASE WHEN c.valor_total < 100000 THEN 1 END) as contratos_pequenos
        FROM contratos c 
        WHERE {$whereClause}
    ";
    
    $stmt = $conn->prepare($statsQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // 2. Distribuição por Modalidade
    $modalidadeQuery = "
        SELECT 
            c.modalidade,
            COUNT(*) as quantidade,
            SUM(c.valor_total) as valor_total,
            AVG(c.valor_total) as valor_medio,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM contratos c2 WHERE {$whereClause})), 2) as percentual
        FROM contratos c 
        WHERE {$whereClause}
        GROUP BY c.modalidade
        ORDER BY quantidade DESC
    ";
    
    $stmt = $conn->prepare($modalidadeQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $modalidades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // 3. Distribuição por Status
    $statusQuery = "
        SELECT 
            c.status_contrato,
            COUNT(*) as quantidade,
            SUM(c.valor_total) as valor_total,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM contratos c2 WHERE {$whereClause})), 2) as percentual
        FROM contratos c 
        WHERE {$whereClause}
        GROUP BY c.status_contrato
        ORDER BY quantidade DESC
    ";
    
    $stmt = $conn->prepare($statusQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $statusData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // 4. Evolução Mensal
    $evolucaoQuery = "
        SELECT 
            DATE_FORMAT(c.data_assinatura, '%Y-%m') as mes,
            COUNT(*) as quantidade,
            SUM(c.valor_total) as valor_total
        FROM contratos c 
        WHERE {$whereClause}
        GROUP BY DATE_FORMAT(c.data_assinatura, '%Y-%m')
        ORDER BY mes
    ";
    
    $stmt = $conn->prepare($evolucaoQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $evolucao = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // 5. Top 10 Contratados
    $contratadosQuery = "
        SELECT 
            c.contratado_nome,
            c.contratado_cnpj,
            COUNT(*) as quantidade_contratos,
            SUM(c.valor_total) as valor_total,
            AVG(c.valor_total) as valor_medio
        FROM contratos c 
        WHERE {$whereClause}
        GROUP BY c.contratado_nome, c.contratado_cnpj
        ORDER BY valor_total DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($contratadosQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $topContratados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // 6. Contratos próximos ao vencimento
    $vencimentoQuery = "
        SELECT 
            c.numero_contrato,
            c.objeto,
            c.contratado_nome,
            c.valor_total,
            c.data_fim_vigencia,
            DATEDIFF(c.data_fim_vigencia, CURDATE()) as dias_restantes
        FROM contratos c 
        WHERE c.status_contrato = 'vigente'
          AND c.data_fim_vigencia BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        ORDER BY c.data_fim_vigencia ASC
        LIMIT 20
    ";
    
    $vencimentos = $conn->query($vencimentoQuery)->fetch_all(MYSQLI_ASSOC);
    
    // 7. Indicadores de Performance
    $indicadores = [
        'taxa_execucao' => $stats['valor_total'] > 0 ? ($stats['valor_pago'] / $stats['valor_total']) * 100 : 0,
        'taxa_empenho' => $stats['valor_total'] > 0 ? ($stats['valor_empenhado'] / $stats['valor_total']) * 100 : 0,
        'prazo_medio' => 0, // Calcular separadamente
        'economia_media' => 0 // Se houver dados de economia
    ];
    
    // Calcular prazo médio
    $prazoQuery = "
        SELECT AVG(DATEDIFF(c.data_fim_vigencia, c.data_inicio_vigencia)) as prazo_medio
        FROM contratos c 
        WHERE {$whereClause}
          AND c.data_inicio_vigencia IS NOT NULL 
          AND c.data_fim_vigencia IS NOT NULL
    ";
    
    $stmt = $conn->prepare($prazoQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $prazoResult = $stmt->get_result()->fetch_assoc();
    $indicadores['prazo_medio'] = $prazoResult['prazo_medio'] ?? 0;
    
} catch (Exception $e) {
    $erro = $e->getMessage();
    $stats = [];
    $modalidades = [];
    $statusData = [];
    $evolucao = [];
    $topContratados = [];
    $vencimentos = [];
    $indicadores = [];
}

// Gerar saída baseada no formato
if ($formato === 'pdf') {
    // TODO: Implementar geração de PDF se biblioteca estiver disponível
    $formato = 'html'; // Fallback para HTML
}

if ($formato === 'csv') {
    // Gerar CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_contratos_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos
    fputcsv($output, ['Relatório de Contratos - ' . date('d/m/Y')], ';');
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim))], ';');
    fputcsv($output, [], ';'); // Linha vazia
    
    // Estatísticas gerais
    fputcsv($output, ['ESTATÍSTICAS GERAIS'], ';');
    fputcsv($output, ['Total de Contratos', number_format($stats['total_contratos'] ?? 0)], ';');
    fputcsv($output, ['Contratos Vigentes', number_format($stats['contratos_vigentes'] ?? 0)], ';');
    fputcsv($output, ['Valor Total', 'R$ ' . number_format($stats['valor_total'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Valor Empenhado', 'R$ ' . number_format($stats['valor_empenhado'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Valor Pago', 'R$ ' . number_format($stats['valor_pago'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, [], ';'); // Linha vazia
    
    // Distribuição por modalidade
    fputcsv($output, ['DISTRIBUIÇÃO POR MODALIDADE'], ';');
    fputcsv($output, ['Modalidade', 'Quantidade', 'Valor Total', 'Percentual'], ';');
    foreach ($modalidades as $mod) {
        fputcsv($output, [
            $mod['modalidade'],
            number_format($mod['quantidade']),
            'R$ ' . number_format($mod['valor_total'], 2, ',', '.'),
            $mod['percentual'] . '%'
        ], ';');
    }
    
    fclose($output);
    exit;
}

// Formato HTML (padrão)
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Contratos - Sistema CGLIC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .relatorio-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }
        
        .relatorio-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        
        .relatorio-header h1 {
            color: #007bff;
            margin: 0;
        }
        
        .relatorio-header .periodo {
            color: #6c757d;
            margin-top: 10px;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .chart-section {
            margin: 40px 0;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        
        .table-section {
            margin: 40px 0;
        }
        
        .table-section h3 {
            color: #007bff;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .data-table th {
            background: #007bff;
            color: white;
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .indicador-card {
            background: white;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .indicador-valor {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .indicador-valor.bom { color: #28a745; }
        .indicador-valor.regular { color: #ffc107; }
        .indicador-valor.ruim { color: #dc3545; }
        
        .actions-bar {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 10px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        @media print {
            .actions-bar {
                display: none;
            }
            
            .relatorio-container {
                padding: 0;
            }
            
            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="relatorio-container">
        <!-- Cabeçalho -->
        <div class="relatorio-header">
            <h1><i data-lucide="file-text"></i> Relatório Gerencial de Contratos</h1>
            <div class="periodo">
                Período: <?= date('d/m/Y', strtotime($dataInicio)) ?> a <?= date('d/m/Y', strtotime($dataFim)) ?>
            </div>
            <div class="periodo">
                Gerado em: <?= date('d/m/Y H:i') ?> | UASG: 250110 | Ministério da Saúde
            </div>
        </div>
        
        <!-- Ações -->
        <div class="actions-bar">
            <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'csv'])) ?>" class="btn">
                <i data-lucide="download"></i> Exportar CSV
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i data-lucide="printer"></i> Imprimir
            </button>
            <a href="../contratos_dashboard.php" class="btn btn-secondary">
                <i data-lucide="arrow-left"></i> Voltar
            </a>
        </div>
        
        <?php if (isset($erro)): ?>
        <div class="alert alert-error">
            <strong>Erro:</strong> <?= htmlspecialchars($erro) ?>
        </div>
        <?php else: ?>
        
        <!-- Estatísticas Gerais -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_contratos']) ?></div>
                <div class="stat-label">Total de Contratos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['contratos_vigentes']) ?></div>
                <div class="stat-label">Contratos Vigentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= number_format($stats['valor_total'], 0, ',', '.') ?></div>
                <div class="stat-label">Valor Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= number_format($stats['valor_pago'], 0, ',', '.') ?></div>
                <div class="stat-label">Valor Pago</div>
            </div>
        </div>
        
        <!-- Indicadores de Performance -->
        <div class="chart-section">
            <h3><i data-lucide="trending-up"></i> Indicadores de Performance</h3>
            <div class="stats-overview">
                <div class="indicador-card">
                    <div class="indicador-valor <?= $indicadores['taxa_execucao'] >= 80 ? 'bom' : ($indicadores['taxa_execucao'] >= 60 ? 'regular' : 'ruim') ?>">
                        <?= number_format($indicadores['taxa_execucao'], 1) ?>%
                    </div>
                    <div class="stat-label">Taxa de Execução</div>
                </div>
                <div class="indicador-card">
                    <div class="indicador-valor <?= $indicadores['taxa_empenho'] >= 90 ? 'bom' : ($indicadores['taxa_empenho'] >= 70 ? 'regular' : 'ruim') ?>">
                        <?= number_format($indicadores['taxa_empenho'], 1) ?>%
                    </div>
                    <div class="stat-label">Taxa de Empenho</div>
                </div>
                <div class="indicador-card">
                    <div class="indicador-valor">
                        <?= number_format($indicadores['prazo_medio']) ?>
                    </div>
                    <div class="stat-label">Prazo Médio (dias)</div>
                </div>
                <div class="indicador-card">
                    <div class="indicador-valor">
                        R$ <?= number_format($stats['valor_medio'], 0, ',', '.') ?>
                    </div>
                    <div class="stat-label">Valor Médio</div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de Distribuição por Modalidade -->
        <?php if (!empty($modalidades)): ?>
        <div class="chart-section">
            <h3><i data-lucide="pie-chart"></i> Distribuição por Modalidade</h3>
            <div class="chart-container">
                <canvas id="modalidadeChart"></canvas>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Gráfico de Evolução Mensal -->
        <?php if (!empty($evolucao)): ?>
        <div class="chart-section">
            <h3><i data-lucide="trending-up"></i> Evolução Mensal</h3>
            <div class="chart-container">
                <canvas id="evolucaoChart"></canvas>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tabela de Modalidades -->
        <?php if (!empty($modalidades)): ?>
        <div class="table-section">
            <h3><i data-lucide="list"></i> Distribuição por Modalidade</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Modalidade</th>
                        <th>Quantidade</th>
                        <th>Valor Total</th>
                        <th>Valor Médio</th>
                        <th>Percentual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modalidades as $mod): ?>
                    <tr>
                        <td><?= htmlspecialchars($mod['modalidade']) ?></td>
                        <td><?= number_format($mod['quantidade']) ?></td>
                        <td>R$ <?= number_format($mod['valor_total'], 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($mod['valor_medio'], 2, ',', '.') ?></td>
                        <td><?= $mod['percentual'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Top 10 Contratados -->
        <?php if (!empty($topContratados)): ?>
        <div class="table-section">
            <h3><i data-lucide="users"></i> Top 10 Contratados</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Contratado</th>
                        <th>CNPJ</th>
                        <th>Contratos</th>
                        <th>Valor Total</th>
                        <th>Valor Médio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topContratados as $contratado): ?>
                    <tr>
                        <td><?= htmlspecialchars($contratado['contratado_nome']) ?></td>
                        <td><?= formatarCNPJ($contratado['contratado_cnpj']) ?></td>
                        <td><?= number_format($contratado['quantidade_contratos']) ?></td>
                        <td>R$ <?= number_format($contratado['valor_total'], 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($contratado['valor_medio'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Contratos Próximos ao Vencimento -->
        <?php if (!empty($vencimentos)): ?>
        <div class="table-section">
            <h3><i data-lucide="clock"></i> Contratos Próximos ao Vencimento (90 dias)</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Contratado</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Dias Restantes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vencimentos as $venc): ?>
                    <tr>
                        <td><?= htmlspecialchars($venc['numero_contrato']) ?></td>
                        <td><?= htmlspecialchars($venc['contratado_nome']) ?></td>
                        <td>R$ <?= number_format($venc['valor_total'], 2, ',', '.') ?></td>
                        <td><?= date('d/m/Y', strtotime($venc['data_fim_vigencia'])) ?></td>
                        <td style="color: <?= $venc['dias_restantes'] <= 30 ? '#dc3545' : ($venc['dias_restantes'] <= 60 ? '#ffc107' : '#28a745') ?>">
                            <?= $venc['dias_restantes'] ?> dias
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
        
        <!-- Rodapé -->
        <div style="text-align: center; margin-top: 50px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 0.9em;">
            Sistema CGLIC - Coordenação Geral de Licitações<br>
            Ministério da Saúde - UASG 250110<br>
            Relatório gerado automaticamente em <?= date('d/m/Y \à\s H:i') ?>
        </div>
    </div>
    
    <script>
    // Inicializar Lucide icons
    lucide.createIcons();
    
    <?php if (!empty($modalidades) && !isset($erro)): ?>
    // Gráfico de Modalidades
    const modalidadeCtx = document.getElementById('modalidadeChart').getContext('2d');
    new Chart(modalidadeCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($modalidades, 'modalidade')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($modalidades, 'quantidade')) ?>,
                backgroundColor: [
                    '#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8',
                    '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if (!empty($evolucao) && !isset($erro)): ?>
    // Gráfico de Evolução
    const evolucaoCtx = document.getElementById('evolucaoChart').getContext('2d');
    new Chart(evolucaoCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(function($item) {
                return date('m/Y', strtotime($item['mes'] . '-01'));
            }, $evolucao)) ?>,
            datasets: [{
                label: 'Quantidade de Contratos',
                data: <?= json_encode(array_column($evolucao, 'quantidade')) ?>,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>