<?php
/**
 * Gerador de Relatórios de Andamentos
 * Sistema CGLIC - Ministério da Saúde
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Verificar autenticação
verificarLogin();

// Verificar se usuário tem permissão para gerar relatórios
if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], [1, 2, 3, 4])) {
    die('Acesso negado. Você não tem permissão para gerar relatórios.');
}


try {
    $pdo = conectarDB();
    
    // Parâmetros do relatório
    $nup = $_GET['nup'] ?? '';
    $data_inicial = $_GET['data_inicial'] ?? '';
    $data_final = $_GET['data_final'] ?? '';
    $formato = $_GET['formato'] ?? 'html';
    $incluir_graficos = isset($_GET['incluir_graficos']) ? true : false;
    
    // Validação básica
    if (empty($nup)) {
        die('NUP é obrigatório para gerar o relatório de andamentos.');
    }
    
    // Construir consulta
    $where_conditions = ['h.nup = ?'];
    $params = [$nup];
    
    if (!empty($data_inicial)) {
        $where_conditions[] = 'h.data_hora >= ?';
        $params[] = $data_inicial . ' 00:00:00';
    }
    
    if (!empty($data_final)) {
        $where_conditions[] = 'h.data_hora <= ?';
        $params[] = $data_final . ' 23:59:59';
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Buscar dados dos andamentos
    $sql_andamentos = "
        SELECT 
            h.id,
            h.nup,
            h.processo_id,
            h.data_hora,
            h.unidade,
            h.usuario,
            h.descricao,
            h.importacao_timestamp,
            l.objeto,
            l.modalidade,
            l.situacao as situacao_licitacao,
            l.valor_estimado
        FROM historico_andamentos h
        LEFT JOIN licitacoes l ON l.nup = h.nup
        WHERE {$where_clause}
        ORDER BY h.data_hora ASC
    ";
    
    $stmt = $pdo->prepare($sql_andamentos);
    $stmt->execute($params);
    $andamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($andamentos)) {
        die('Nenhum andamento encontrado para os critérios especificados.');
    }
    
    // Calcular estatísticas
    $stats = calcularEstatisticasAndamentos($andamentos);
    
    // Calcular dados por usuário
    $dados_usuarios = calcularDadosPorUsuario($andamentos);
    
    // Gerar relatório baseado no formato
    switch ($formato) {
        case 'html':
            gerarRelatorioHTML($andamentos, $stats, $dados_usuarios, $incluir_graficos);
            break;
        case 'pdf':
            gerarRelatorioPDF($andamentos, $stats);
            break;
        case 'excel':
            gerarRelatorioExcel($andamentos, $stats);
            break;
        default:
            die('Formato de relatório inválido.');
    }
    
} catch (Exception $e) {
    error_log("Erro ao gerar relatório de andamentos: " . $e->getMessage());
    die('Erro interno: ' . $e->getMessage());
}

/**
 * Calcular estatísticas dos andamentos
 */
function calcularEstatisticasAndamentos($andamentos) {
    $stats = [
        'total_andamentos' => count($andamentos),
        'primeira_data' => $andamentos[0]['data_hora'] ?? null,
        'ultima_data' => end($andamentos)['data_hora'] ?? null,
        'unidades_envolvidas' => [],
        'usuarios_envolvidos' => [],
        'tempo_por_unidade' => [],
        'dias_tramitacao' => 0
    ];
    
    // Coletar unidades e usuários únicos
    foreach ($andamentos as $andamento) {
        if (!in_array($andamento['unidade'], $stats['unidades_envolvidas'])) {
            $stats['unidades_envolvidas'][] = $andamento['unidade'];
        }
        if (!in_array($andamento['usuario'], $stats['usuarios_envolvidos'])) {
            $stats['usuarios_envolvidos'][] = $andamento['usuario'];
        }
    }
    
    // Calcular tempo total de tramitação
    if ($stats['primeira_data'] && $stats['ultima_data']) {
        $primeira = new DateTime($stats['primeira_data']);
        $ultima = new DateTime($stats['ultima_data']);
        $stats['dias_tramitacao'] = $primeira->diff($ultima)->days;
    }
    
    // Calcular tempo por unidade
    $stats['tempo_por_unidade'] = calcularTempoPorUnidade($andamentos);
    
    return $stats;
}

/**
 * Calcular tempo gasto em cada unidade
 */
function calcularTempoPorUnidade($andamentos) {
    $tempo_unidades = [];
    $unidade_anterior = null;
    $data_anterior = null;
    
    foreach ($andamentos as $andamento) {
        $unidade_atual = $andamento['unidade'];
        $data_atual = new DateTime($andamento['data_hora']);
        
        if ($unidade_anterior && $data_anterior && $unidade_anterior !== $unidade_atual) {
            $diferenca = $data_anterior->diff($data_atual);
            $dias = $diferenca->days;
            
            if (!isset($tempo_unidades[$unidade_anterior])) {
                $tempo_unidades[$unidade_anterior] = ['dias' => 0, 'tramitacoes' => 0];
            }
            
            $tempo_unidades[$unidade_anterior]['dias'] += $dias;
            $tempo_unidades[$unidade_anterior]['tramitacoes']++;
        }
        
        $unidade_anterior = $unidade_atual;
        $data_anterior = $data_atual;
    }
    
    return $tempo_unidades;
}

/**
 * Calcular dados por usuário
 */
function calcularDadosPorUsuario($andamentos) {
    $dados_usuarios = [];
    $usuario_anterior = null;
    $data_anterior = null;
    
    foreach ($andamentos as $andamento) {
        $usuario_atual = $andamento['usuario'] ?: 'Não informado';
        $data_atual = new DateTime($andamento['data_hora']);
        
        // Inicializar usuário se não existir
        if (!isset($dados_usuarios[$usuario_atual])) {
            $dados_usuarios[$usuario_atual] = [
                'usuario' => $usuario_atual,
                'total_andamentos' => 0,
                'total_dias' => 0,
                'primeira_acao' => $andamento['data_hora'],
                'ultima_acao' => $andamento['data_hora'],
                'unidades' => [],
                'periodos' => 0
            ];
        }
        
        // Contar andamento
        $dados_usuarios[$usuario_atual]['total_andamentos']++;
        
        // Atualizar datas extremas
        if ($andamento['data_hora'] < $dados_usuarios[$usuario_atual]['primeira_acao']) {
            $dados_usuarios[$usuario_atual]['primeira_acao'] = $andamento['data_hora'];
        }
        if ($andamento['data_hora'] > $dados_usuarios[$usuario_atual]['ultima_acao']) {
            $dados_usuarios[$usuario_atual]['ultima_acao'] = $andamento['data_hora'];
        }
        
        // Coletar unidades
        if (!in_array($andamento['unidade'], $dados_usuarios[$usuario_atual]['unidades'])) {
            $dados_usuarios[$usuario_atual]['unidades'][] = $andamento['unidade'];
        }
        
        // Calcular tempo que ficou com o processo (se mudou de usuário)
        if ($usuario_anterior && $data_anterior && $usuario_anterior !== $usuario_atual) {
            $diferenca = $data_anterior->diff($data_atual);
            $dias = $diferenca->days;
            
            if (isset($dados_usuarios[$usuario_anterior])) {
                $dados_usuarios[$usuario_anterior]['total_dias'] += $dias;
                $dados_usuarios[$usuario_anterior]['periodos']++;
            }
        }
        
        $usuario_anterior = $usuario_atual;
        $data_anterior = $data_atual;
    }
    
    // Calcular médias
    foreach ($dados_usuarios as $usuario => &$dados) {
        $dados['media_dias_por_periodo'] = $dados['periodos'] > 0 
            ? round($dados['total_dias'] / $dados['periodos'], 1) 
            : 0;
        $dados['unidades_texto'] = implode(', ', $dados['unidades']);
    }
    
    // Ordenar por total de dias (decrescente)
    uasort($dados_usuarios, function($a, $b) {
        return $b['total_dias'] <=> $a['total_dias'];
    });
    
    return $dados_usuarios;
}

/**
 * Gerar relatório em HTML
 */
function gerarRelatorioHTML($andamentos, $stats, $dados_usuarios, $incluir_graficos) {
    $nup = $andamentos[0]['nup'];
    $objeto = $andamentos[0]['objeto'] ?? 'Não informado';
    $modalidade = $andamentos[0]['modalidade'] ?? 'Não informado';
    
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Relatório de Andamentos - <?php echo htmlspecialchars($nup); ?></title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                background: #f8f9fa;
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                border-radius: 15px;
                margin-bottom: 30px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            .header h1 {
                margin: 0 0 10px 0;
                font-size: 28px;
            }
            .header p {
                margin: 5px 0;
                opacity: 0.9;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            .stat-card {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                border-left: 4px solid #667eea;
            }
            .stat-card h3 {
                margin: 0 0 10px 0;
                color: #667eea;
                font-size: 16px;
            }
            .stat-card .value {
                font-size: 24px;
                font-weight: bold;
                color: #2c3e50;
            }
            .grafico-container {
                background: white;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                margin-bottom: 30px;
            }
            .chart-wrapper {
                position: relative;
                height: 400px;
                margin-top: 20px;
            }
            .tabela-usuarios {
                background: white;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                margin-bottom: 30px;
            }
            .tabela-usuarios table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .tabela-usuarios th,
            .tabela-usuarios td {
                padding: 12px 15px;
                text-align: left;
                border-bottom: 1px solid #e5e7eb;
            }
            .tabela-usuarios th {
                background: #f8f9fa;
                font-weight: 600;
                color: #374151;
                border-top: 1px solid #e5e7eb;
            }
            .tabela-usuarios tr:hover {
                background: #f8f9fa;
            }
            .usuario-badge {
                background: #667eea;
                color: white;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
            }
            .tempo-badge {
                background: #e3f2fd;
                color: #1976d2;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
            }
            @media print {
                body { background: white; }
                .header { background: #667eea !important; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><i data-lucide="file-text"></i> Relatório de Andamentos</h1>
            <p><strong><i data-lucide="hash"></i> NUP:</strong> <?php echo htmlspecialchars($nup); ?></p>
            <p><strong><i data-lucide="target"></i> Objeto:</strong> <?php echo htmlspecialchars($objeto); ?></p>
            <p><strong><i data-lucide="tag"></i> Modalidade:</strong> <?php echo htmlspecialchars($modalidade); ?></p>
            <p><strong><i data-lucide="calendar"></i> Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Andamentos</h3>
                <div class="value"><?php echo $stats['total_andamentos']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Dias de Tramitação</h3>
                <div class="value"><?php echo $stats['dias_tramitacao']; ?> dias</div>
            </div>
            <div class="stat-card">
                <h3>Unidades Envolvidas</h3>
                <div class="value"><?php echo count($stats['unidades_envolvidas']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Usuários Envolvidos</h3>
                <div class="value"><?php echo count($stats['usuarios_envolvidos']); ?></div>
            </div>
        </div>

        <?php if (!empty($stats['tempo_por_unidade'])): ?>
        <div class="grafico-container">
            <h2><i data-lucide="bar-chart-3"></i> Tempo por Unidade</h2>
            <div class="chart-wrapper">
                <canvas id="graficoTempoPorUnidade"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($dados_usuarios)): ?>
        <div class="tabela-usuarios">
            <h2><i data-lucide="users"></i> Análise por Usuário</h2>
            <table>
                <thead>
                    <tr>
                        <th><i data-lucide="user"></i> Usuário</th>
                        <th><i data-lucide="hash"></i> Andamentos</th>
                        <th><i data-lucide="clock"></i> Total de Dias</th>
                        <th><i data-lucide="trending-up"></i> Média por Período</th>
                        <th><i data-lucide="calendar-days"></i> Primeira Ação</th>
                        <th><i data-lucide="calendar-check"></i> Última Ação</th>
                        <th><i data-lucide="building-2"></i> Unidades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados_usuarios as $dados): ?>
                    <tr>
                        <td>
                            <span class="usuario-badge"><?php echo htmlspecialchars($dados['usuario']); ?></span>
                        </td>
                        <td><?php echo $dados['total_andamentos']; ?></td>
                        <td>
                            <span class="tempo-badge"><?php echo $dados['total_dias']; ?> dias</span>
                        </td>
                        <td><?php echo $dados['media_dias_por_periodo']; ?> dias</td>
                        <td><?php echo date('d/m/Y H:i', strtotime($dados['primeira_acao'])); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($dados['ultima_acao'])); ?></td>
                        <td><?php echo htmlspecialchars($dados['unidades_texto']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 40px; color: #7f8c8d; font-size: 14px;">
            <p>Sistema CGLIC - Ministério da Saúde | Relatório gerado automaticamente</p>
        </div>

        <?php if (!empty($stats['tempo_por_unidade'])): ?>
        <script>
            // Dados para o gráfico
            const dadosGrafico = {
                labels: <?php echo json_encode(array_keys($stats['tempo_por_unidade'])); ?>,
                datasets: [{
                    label: 'Dias de Tramitação',
                    data: <?php echo json_encode(array_column($stats['tempo_por_unidade'], 'dias')); ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(118, 75, 162, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            };

            // Configuração do gráfico
            const config = {
                type: 'bar',
                data: dadosGrafico,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Tempo de Tramitação por Unidade',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            color: '#2c3e50'
                        },
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    const unidade = context.label;
                                    const tramitacoes = <?php echo json_encode(array_column($stats['tempo_por_unidade'], 'tramitacoes')); ?>[context.dataIndex];
                                    return `Tramitações: ${tramitacoes}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Dias',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Unidades',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            };

            // Criar o gráfico
            const ctx = document.getElementById('graficoTempoPorUnidade');
            if (ctx) {
                new Chart(ctx, config);
            }
        </script>
        <?php endif; ?>

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

/**
 * Gerar relatório em PDF (placeholder)
 */
function gerarRelatorioPDF($andamentos, $stats) {
    // Para implementar com TCPDF se disponível
    die('Geração de PDF não está disponível no momento. Use o formato HTML.');
}

/**
 * Gerar relatório em Excel (CSV)
 */
function gerarRelatorioExcel($andamentos, $stats) {
    $nup = $andamentos[0]['nup'];
    $filename = "relatorio_andamentos_{$nup}_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 no Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos
    fputcsv($output, [
        'Data/Hora',
        'Unidade',
        'Usuário', 
        'Descrição',
        'NUP',
        'Processo ID'
    ], ';');
    
    // Dados
    foreach ($andamentos as $andamento) {
        fputcsv($output, [
            date('d/m/Y H:i:s', strtotime($andamento['data_hora'])),
            $andamento['unidade'],
            $andamento['usuario'],
            $andamento['descricao'],
            $andamento['nup'],
            $andamento['processo_id']
        ], ';');
    }
    
    fclose($output);
}
?>