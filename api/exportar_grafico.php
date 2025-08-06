<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

// Verificar permissões
if (!temPermissao('pca_relatorios')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão para acessar relatórios']);
    exit;
}

$pdo = conectarDB();

$formato = $_GET['formato'] ?? 'html';
$id = $_GET['id'] ?? '';
$todos = $_GET['todos'] ?? '';

try {
    if ($todos === '1') {
        // Exportar todos os gráficos do usuário
        exportarTodosGraficos($formato);
    } else if (!empty($id)) {
        // Exportar gráfico específico
        exportarGraficoIndividual($id, $formato);
    } else {
        throw new Exception('Parâmetros inválidos');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    if ($formato === 'html') {
        echo "<div style='padding: 20px; color: red;'>Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
    } else {
        echo "Erro: " . $e->getMessage();
    }
}

/**
 * Exportar gráfico individual
 */
function exportarGraficoIndividual($id, $formato) {
    global $pdo;
    
    // Buscar configuração do gráfico
    $sql = "SELECT * FROM graficos_salvos WHERE id = ? AND usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $_SESSION['usuario_id']]);
    $grafico = $stmt->fetch();
    
    if (!$grafico) {
        throw new Exception('Gráfico não encontrado');
    }
    
    $config = json_decode($grafico['configuracao'], true);
    
    // Buscar dados do gráfico usando a mesma lógica da API
    $dados = buscarDadosGrafico($config);
    
    if ($formato === 'html') {
        exportarHTML([$grafico], [$dados]);
    } else {
        exportarPDF([$grafico], [$dados]);
    }
}

/**
 * Exportar todos os gráficos
 */
function exportarTodosGraficos($formato) {
    global $pdo;
    
    // Buscar todos os gráficos do usuário
    $sql = "SELECT * FROM graficos_salvos WHERE usuario_id = ? ORDER BY nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['usuario_id']]);
    $graficos = $stmt->fetchAll();
    
    if (empty($graficos)) {
        throw new Exception('Nenhum gráfico encontrado');
    }
    
    $dadosGraficos = [];
    foreach ($graficos as $grafico) {
        $config = json_decode($grafico['configuracao'], true);
        $dadosGraficos[] = buscarDadosGrafico($config);
    }
    
    if ($formato === 'html') {
        exportarHTML($graficos, $dadosGraficos);
    } else {
        exportarPDF($graficos, $dadosGraficos);
    }
}

/**
 * Buscar dados do gráfico
 */
function buscarDadosGrafico($config) {
    global $pdo;
    
    $campoX = $config['campoX'] ?? 'categoria_contratacao';
    $campoY = $config['campoY'] ?? 'categoria_contratacao';
    $filtroAno = $config['filtroAno'] ?? '';
    $filtroSituacao = $config['filtroSituacao'] ?? '';
    $filtroDataInicio = $config['filtroDataInicio'] ?? '';
    $filtroDataFim = $config['filtroDataFim'] ?? '';
    
    // Reutilizar lógica da API construtor_graficos.php
    $where = [];
    $params = [];
    
    if (!empty($filtroAno)) {
        $where[] = "pi.ano_pca = ?";
        $params[] = $filtroAno;
    }
    
    if (!empty($filtroSituacao)) {
        $where[] = "pd.situacao_execucao = ?";
        $params[] = $filtroSituacao;
    }
    
    if (!empty($filtroDataInicio)) {
        $where[] = "pd.data_inicio_processo >= ?";
        $params[] = $filtroDataInicio;
    }
    
    if (!empty($filtroDataFim)) {
        $where[] = "pd.data_inicio_processo <= ?";
        $params[] = $filtroDataFim;
    }
    
    $where[] = "pd.numero_dfd IS NOT NULL AND pd.numero_dfd != ''";
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    $selectX = construirSelectCampoX($campoX);
    $selectY = construirSelectCampoY($campoY);
    $groupBy = obterGroupBy($campoX, $campoY);
    
    $sql = "
        SELECT 
            {$selectX} as categoria,
            {$selectY} as valor,
            COUNT(*) as total_registros
        FROM pca_dados pd
        INNER JOIN pca_importacoes pi ON pd.importacao_id = pi.id
        {$whereClause}
        GROUP BY {$groupBy}
        HAVING categoria IS NOT NULL AND categoria != ''
        ORDER BY valor DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $valores = [];
    
    foreach ($dados as $item) {
        $labels[] = strval($item['categoria']);
        $valores[] = floatval($item['valor']);
    }
    
    return [
        'labels' => $labels,
        'valores' => $valores,
        'config' => $config
    ];
}

/**
 * Funções auxiliares (simplificadas da API original)
 */
function construirSelectCampoX($campo) {
    switch ($campo) {
        case 'urgente':
            return "CASE WHEN pd.urgente = 1 THEN 'Urgente' ELSE 'Normal' END";
        case 'valor_total_contratacao':
            return "pd.valor_total_contratacao";
        case 'quantidade_dfds':
            return "COUNT(DISTINCT pd.numero_dfd)";
        default:
            return "pd.{$campo}";
    }
}

function construirSelectCampoY($campo) {
    switch ($campo) {
        case 'urgente':
            return "CASE WHEN pd.urgente = 1 THEN 'Urgente' ELSE 'Normal' END";
        case 'valor_total_contratacao':
            return "pd.valor_total_contratacao";
        case 'quantidade_dfds':
            return "COUNT(DISTINCT pd.numero_dfd)";
        default:
            return "pd.{$campo}";
    }
}

function obterGroupBy($campoX, $campoY) {
    if ($campoX === 'quantidade_dfds' || $campoY === 'quantidade_dfds') {
        if ($campoX === 'quantidade_dfds' && $campoY === 'quantidade_dfds') {
            return "pd.categoria_contratacao";
        }
        if ($campoX === 'quantidade_dfds') {
            return construirSelectCampoY($campoY);
        }
        if ($campoY === 'quantidade_dfds') {
            return construirSelectCampoX($campoX);
        }
    }
    return construirSelectCampoX($campoX);
}

/**
 * Exportar em HTML
 */
function exportarHTML($graficos, $dadosGraficos) {
    header('Content-Type: text/html; charset=utf-8');
    
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Gráficos - Sistema CGLIC</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 40px; background: #f8f9fa; }
        .header { text-align: center; margin-bottom: 40px; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .grafico-container { background: white; margin-bottom: 40px; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .grafico-titulo { color: #2c3e50; font-size: 20px; font-weight: 600; margin-bottom: 20px; }
        .grafico-canvas { max-width: 100%; height: 400px; margin-bottom: 20px; }
        .grafico-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 14px; }
        .info-item { padding: 10px; background: #f8f9fa; border-radius: 6px; }
        .info-label { font-weight: 600; color: #6c757d; }
        @media print { body { background: white; } .grafico-container { box-shadow: none; border: 1px solid #dee2e6; } }
    </style>
</head>
<body>';
    
    $html .= '<div class="header">
        <h1>Relatório de Gráficos Personalizados</h1>
        <p><strong>Sistema CGLIC - Ministério da Saúde</strong></p>
        <p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>
        <p>Usuário: ' . htmlspecialchars($_SESSION['usuario_nome']) . '</p>
    </div>';
    
    foreach ($graficos as $index => $grafico) {
        $dados = $dadosGraficos[$index];
        $config = $dados['config'];
        
        $tipoGrafico = ucfirst($config['tipoGrafico'] ?? 'bar');
        $tipoChartJs = $config['tipoGrafico'] === 'horizontalBar' ? 'bar' : $config['tipoGrafico'];
        
        $html .= '<div class="grafico-container">
            <h2 class="grafico-titulo">' . htmlspecialchars($grafico['nome']) . '</h2>
            <canvas class="grafico-canvas" id="grafico' . $index . '"></canvas>
            
            <div class="grafico-info">
                <div class="info-item">
                    <div class="info-label">Tipo de Gráfico:</div>
                    <div>' . $tipoGrafico . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Eixo X:</div>
                    <div>' . obterNomeCampo($config['campoX']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Eixo Y:</div>
                    <div>' . obterNomeCampo($config['campoY']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Total de Registros:</div>
                    <div>' . count($dados['labels']) . '</div>
                </div>
            </div>
        </div>';
    }
    
    $html .= '<script>';
    
    foreach ($graficos as $index => $grafico) {
        $dados = $dadosGraficos[$index];
        $config = $dados['config'];
        $tipoChartJs = $config['tipoGrafico'] === 'horizontalBar' ? 'bar' : $config['tipoGrafico'];
        
        $labelsJson = json_encode($dados['labels']);
        $valoresJson = json_encode($dados['valores']);
        
        $indexAxis = $config['tipoGrafico'] === 'horizontalBar' ? 'indexAxis: "y",' : '';
        
        $html .= "
        new Chart(document.getElementById('grafico{$index}'), {
            type: '{$tipoChartJs}',
            data: {
                labels: {$labelsJson},
                datasets: [{
                    data: {$valoresJson},
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)', 'rgba(255, 99, 132, 0.8)', 'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)', 'rgba(153, 102, 255, 0.8)', 'rgba(255, 159, 64, 0.8)',
                        'rgba(199, 199, 199, 0.8)', 'rgba(83, 102, 255, 0.8)', 'rgba(255, 99, 255, 0.8)',
                        'rgba(99, 255, 132, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                {$indexAxis}
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });";
    }
    
    $html .= '</script></body></html>';
    
    echo $html;
}

/**
 * Exportar em PDF (placeholder - requer biblioteca PDF)
 */
function exportarPDF($graficos, $dadosGraficos) {
    // Para implementação completa, seria necessário instalar TCPDF ou similar
    // Por enquanto, vamos gerar um HTML para conversão
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="graficos_' . date('Y-m-d_H-i-s') . '.pdf"');
    
    // Simulação de PDF - retorna HTML otimizado para impressão
    echo "PDF: Funcionalidade em desenvolvimento. Use a exportação HTML e imprima como PDF pelo navegador.";
}

/**
 * Obter nome amigável do campo
 */
function obterNomeCampo($campo) {
    $nomes = [
        'categoria_contratacao' => 'Categoria',
        'area_requisitante' => 'Área',
        'situacao_execucao' => 'Situação',
        'prioridade' => 'Prioridade',
        'urgente' => 'Urgência',
        'valor_total_contratacao' => 'Valor Total',
        'quantidade_dfds' => 'Quantidade DFDs'
    ];
    
    return $nomes[$campo] ?? $campo;
}
?>