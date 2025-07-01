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
var_dump($_SESSION);
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
    
    // Gerar relatório baseado no formato
    switch ($formato) {
        case 'html':
            gerarRelatorioHTML($andamentos, $stats, $incluir_graficos);
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
                $tempo_unidades[$unidade_anterior] = ['dias' => 0, 'periodos' => 0];
            }
            
            $tempo_unidades[$unidade_anterior]['dias'] += $dias;
            $tempo_unidades[$unidade_anterior]['periodos']++;
        }
        
        $unidade_anterior = $unidade_atual;
        $data_anterior = $data_atual;
    }
    
    return $tempo_unidades;
}

/**
 * Gerar relatório em HTML
 */
function gerarRelatorioHTML($andamentos, $stats, $incluir_graficos) {
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
            .timeline-container {
                background: white;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                margin-bottom: 30px;
            }
            .timeline-item {
                display: flex;
                margin-bottom: 25px;
                padding-bottom: 25px;
                border-bottom: 1px solid #eee;
                position: relative;
            }
            .timeline-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            .timeline-date {
                min-width: 140px;
                font-weight: bold;
                color: #667eea;
                font-size: 14px;
            }
            .timeline-content {
                flex: 1;
                margin-left: 20px;
            }
            .timeline-unidade {
                background: #667eea;
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                display: inline-block;
                margin-bottom: 8px;
            }
            .timeline-usuario {
                color: #7f8c8d;
                font-size: 13px;
                margin-bottom: 5px;
            }
            .timeline-descricao {
                color: #2c3e50;
                line-height: 1.5;
            }
            .tempo-unidades {
                background: white;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                margin-bottom: 30px;
            }
            .tempo-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px;
                margin-bottom: 10px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #667eea;
            }
            .tempo-unidade {
                font-weight: bold;
                color: #2c3e50;
            }
            .tempo-dias {
                color: #667eea;
                font-weight: bold;
            }
            @media print {
                body { background: white; }
                .header { background: #667eea !important; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>📋 Relatório de Andamentos</h1>
            <p><strong>NUP:</strong> <?php echo htmlspecialchars($nup); ?></p>
            <p><strong>Objeto:</strong> <?php echo htmlspecialchars($objeto); ?></p>
            <p><strong>Modalidade:</strong> <?php echo htmlspecialchars($modalidade); ?></p>
            <p><strong>Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
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
        <div class="tempo-unidades">
            <h2>⏱️ Tempo por Unidade</h2>
            <?php foreach ($stats['tempo_por_unidade'] as $unidade => $tempo): ?>
            <div class="tempo-item">
                <div class="tempo-unidade"><?php echo htmlspecialchars($unidade); ?></div>
                <div class="tempo-dias">
                    <?php echo $tempo['dias']; ?> dias 
                    (<?php echo $tempo['periodos']; ?> período<?php echo $tempo['periodos'] > 1 ? 's' : ''; ?>)
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="timeline-container">
            <h2>📅 Timeline Detalhada</h2>
            <?php foreach ($andamentos as $andamento): ?>
            <div class="timeline-item">
                <div class="timeline-date">
                    <?php echo date('d/m/Y H:i', strtotime($andamento['data_hora'])); ?>
                </div>
                <div class="timeline-content">
                    <div class="timeline-unidade"><?php echo htmlspecialchars($andamento['unidade']); ?></div>
                    <div class="timeline-usuario">👤 <?php echo htmlspecialchars($andamento['usuario']); ?></div>
                    <div class="timeline-descricao"><?php echo htmlspecialchars($andamento['descricao']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 40px; color: #7f8c8d; font-size: 14px;">
            <p>Sistema CGLIC - Ministério da Saúde | Relatório gerado automaticamente</p>
        </div>
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