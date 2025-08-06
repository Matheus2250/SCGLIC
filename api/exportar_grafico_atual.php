<?php
require_once '../config.php';
require_once '../functions.php';

// Debug da sessão
error_log("Exportar Gráfico - Verificando sessão:");
error_log("  SESSION: " . print_r($_SESSION, true));

verificarLogin();

error_log("Exportar Gráfico - Após verificarLogin():");
error_log("  usuario_id: " . ($_SESSION['usuario_id'] ?? 'não definido'));
error_log("  usuario_nivel: " . ($_SESSION['usuario_nivel'] ?? 'não definido'));

// Verificar permissões
$temPermissao = temPermissao('pca_relatorios');
error_log("Exportar Gráfico - temPermissao('pca_relatorios'): " . ($temPermissao ? 'SIM' : 'NÃO'));

if (!$temPermissao) {
    error_log("Exportar Gráfico - ERRO: Sem permissão");
    http_response_code(403);
    if ($_GET['formato'] === 'html') {
        echo "<div style='padding: 20px; color: red;'>❌ Erro: Sem permissão para acessar relatórios</div>";
    } else {
        echo "Erro: Sem permissão para acessar relatórios";
    }
    exit;
}

$pdo = conectarDB();

// Apenas HTML é suportado agora
$formato = 'html';
$tipoGrafico = $_GET['tipoGrafico'] ?? 'bar';
$campoX = $_GET['campoX'] ?? 'categoria_contratacao';
$campoY = $_GET['campoY'] ?? 'categoria_contratacao';
$filtroAno = $_GET['filtroAno'] ?? '';
$filtroSituacao = $_GET['filtroSituacao'] ?? '';
$filtroDataInicio = $_GET['filtroDataInicio'] ?? '';
$filtroDataFim = $_GET['filtroDataFim'] ?? '';

// Log dos parâmetros recebidos
error_log("Exportar Gráfico HTML - Parâmetros recebidos:");
error_log("  tipoGrafico: $tipoGrafico");
error_log("  campoX: $campoX");
error_log("  campoY: $campoY");
error_log("  filtroAno: $filtroAno");
error_log("  filtroSituacao: $filtroSituacao");
error_log("  filtroDataInicio: $filtroDataInicio");
error_log("  filtroDataFim: $filtroDataFim");

try {
    // Buscar dados do gráfico
    error_log("Exportar Gráfico - Buscando dados...");
    $dados = buscarDadosGrafico();
    error_log("Exportar Gráfico - Dados encontrados: " . count($dados['labels']) . " itens");
    
    // Sempre exportar como HTML
    exportarHTML($dados);
    
} catch (Exception $e) {
    http_response_code(500);
    echo "<div style='padding: 20px; color: red; font-family: Arial, sans-serif;'>";
    echo "<h3>❌ Erro ao Exportar Gráfico</h3>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Data:</strong> " . date('d/m/Y H:i:s') . "</p>";
    echo "</div>";
}

/**
 * Buscar dados do gráfico
 */
function buscarDadosGrafico() {
    global $pdo, $campoX, $campoY, $filtroAno, $filtroSituacao, $filtroDataInicio, $filtroDataFim;
    
    // Construir WHERE
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
    
    $selectX = construirSelectCampo($campoX);
    
    // Usar mesma lógica da API principal para Y
    if ($campoY === 'quantidade_dfds') {
        $selectY = "COUNT(*)";
    } else if ($campoY === 'valor_total_contratacao') {
        $selectY = "COALESCE(SUM(DISTINCT pd.valor_total), 0)";
    } else if ($campoX === $campoY) {
        // Se X e Y são iguais, Y deve ser uma contagem
        $selectY = "COUNT(*)";
    } else {
        $selectY = construirSelectCampo($campoY);
    }
    
    // Usar mesma lógica de agrupamento
    if ($campoX === $campoY) {
        $groupBy = $selectX;
    } else {
        $groupBy = $selectX;
    }
    
    // Usar dados únicos por DFD para evitar duplicação de valores
    $sql = "
        SELECT 
            {$selectX} as categoria,
            {$selectY} as valor,
            COUNT(*) as total_registros
        FROM (
            SELECT DISTINCT 
                numero_dfd, 
                valor_total, 
                categoria_contratacao, 
                area_requisitante, 
                situacao_execucao, 
                prioridade, 
                urgente,
                data_inicio_processo,
                importacao_id
            FROM pca_dados
        ) pd
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
        'valores' => $valores
    ];
}

/**
 * Construir SELECT para campo
 */
function construirSelectCampo($campo) {
    switch ($campo) {
        case 'urgente':
            return "CASE WHEN pd.urgente = 1 THEN 'Urgente' ELSE 'Normal' END";
        case 'valor_total_contratacao':
            return "pd.valor_total";
        case 'quantidade_dfds':
            return "COUNT(*)";
        default:
            return "pd.{$campo}";
    }
}

/**
 * Exportar em HTML
 */
function exportarHTML($dados) {
    global $tipoGrafico, $campoX, $campoY, $filtroAno, $filtroSituacao, $filtroDataInicio, $filtroDataFim;
    
    header('Content-Type: text/html; charset=utf-8');
    
    $tipoChartJs = $tipoGrafico === 'horizontalBar' ? 'bar' : $tipoGrafico;
    $labelsJson = json_encode($dados['labels']);
    $valoresJson = json_encode($dados['valores']);
    
    $indexAxis = $tipoGrafico === 'horizontalBar' ? 'indexAxis: "y",' : '';
    
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gráfico Personalizado - Sistema CGLIC</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; 
            margin: 0; 
            background: #f8f9fa;
            overflow-x: auto;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            padding: 20px; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
        }
        .header h1 { 
            margin: 0 0 10px 0; 
            font-size: 24px; 
        }
        .header p { 
            margin: 5px 0; 
            color: #666; 
        }
        .grafico-container { 
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
        }
        .grafico-wrapper {
            position: relative;
            width: 100%;
            height: 400px;
            max-height: 400px;
        }
        .grafico-canvas { 
            width: 100% !important;
            height: 100% !important;
            max-width: 100% !important;
            max-height: 400px !important;
        }
        .info-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 10px; 
            margin-top: 20px; 
        }
        .info-item { 
            padding: 12px; 
            background: #f8f9fa; 
            border-radius: 6px; 
            text-align: center; 
        }
        .info-label { 
            font-weight: 600; 
            color: #6c757d; 
            font-size: 11px; 
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .info-value { 
            font-size: 16px; 
            font-weight: 600; 
            color: #2c3e50; 
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .header { padding: 15px; }
            .grafico-container { padding: 20px; }
            .grafico-wrapper { height: 300px; }
            .info-grid { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); }
        }
        
        /* Impressão */
        @media print { 
            body { 
                background: white; 
                -webkit-print-color-adjust: exact;
            }
            .container {
                max-width: none;
                padding: 0;
            }
            .grafico-container, .header { 
                box-shadow: none; 
                border: 1px solid #dee2e6; 
                page-break-inside: avoid;
            }
            .grafico-wrapper {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gráfico Personalizado</h1>
            <p><strong>Sistema CGLIC - Ministério da Saúde</strong></p>
            <p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>
            <p>Usuário: ' . htmlspecialchars($_SESSION['usuario_nome']) . '</p>
        </div>
        
        <div class="grafico-container">
            <h3 style="margin-top: 0; margin-bottom: 20px; color: #2c3e50;">Gráfico Gerado</h3>
            <div class="grafico-wrapper">
                <canvas class="grafico-canvas" id="grafico"></canvas>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">TIPO DE GRÁFICO</div>
                    <div class="info-value">' . ucfirst($tipoGrafico) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">EIXO X</div>
                    <div class="info-value">' . obterNomeCampo($campoX) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">EIXO Y</div>
                    <div class="info-value">' . obterNomeCampo($campoY) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">REGISTROS</div>
                    <div class="info-value">' . count($dados['labels']) . '</div>
                </div>';
                
    if (!empty($filtroAno)) {
        $html .= '<div class="info-item">
                    <div class="info-label">ANO</div>
                    <div class="info-value">' . $filtroAno . '</div>
                </div>';
    }
    
    if (!empty($filtroSituacao)) {
        $html .= '<div class="info-item">
                    <div class="info-label">SITUAÇÃO</div>
                    <div class="info-value">' . htmlspecialchars($filtroSituacao) . '</div>
                </div>';
    }
    
    $html .= '
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById("grafico");
            if (ctx) {
                new Chart(ctx, {
                    type: "' . $tipoChartJs . '",
                    data: {
                        labels: ' . $labelsJson . ',
                        datasets: [{
                            data: ' . $valoresJson . ',
                            backgroundColor: [
                                "rgba(54, 162, 235, 0.8)", "rgba(255, 99, 132, 0.8)", "rgba(255, 205, 86, 0.8)",
                                "rgba(75, 192, 192, 0.8)", "rgba(153, 102, 255, 0.8)", "rgba(255, 159, 64, 0.8)",
                                "rgba(199, 199, 199, 0.8)", "rgba(83, 102, 255, 0.8)", "rgba(255, 99, 255, 0.8)",
                                "rgba(99, 255, 132, 0.8)"
                            ],
                            borderColor: [
                                "rgba(54, 162, 235, 1)", "rgba(255, 99, 132, 1)", "rgba(255, 205, 86, 1)",
                                "rgba(75, 192, 192, 1)", "rgba(153, 102, 255, 1)", "rgba(255, 159, 64, 1)",
                                "rgba(199, 199, 199, 1)", "rgba(83, 102, 255, 1)", "rgba(255, 99, 255, 1)",
                                "rgba(99, 255, 132, 1)"
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ' . $indexAxis . '
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                display: ' . (in_array($tipoGrafico, ['pie', 'doughnut']) ? 'true' : 'false') . ',
                                position: "bottom"
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let value = context.parsed;
                                        if (typeof value === "object") {
                                            value = value.y || value.x || value;
                                        }
                                        
                                        if (typeof value === "number") {
                                            if (value > 1000000) {
                                                return context.label + ": " + (value / 1000000).toFixed(1) + "M";
                                            } else if (value > 1000) {
                                                return context.label + ": " + (value / 1000).toFixed(0) + "k";
                                            } else {
                                                return context.label + ": " + value.toLocaleString("pt-BR");
                                            }
                                        }
                                        return context.label + ": " + value;
                                    }
                                }
                            }
                        },
                        scales: ' . (in_array($tipoGrafico, ['pie', 'doughnut']) ? '{}' : '{
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        if (value > 1000000) {
                                            return (value / 1000000).toFixed(1) + "M";
                                        } else if (value > 1000) {
                                            return (value / 1000).toFixed(0) + "k";
                                        }
                                        return value.toLocaleString("pt-BR");
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45
                                }
                            }
                        }') . '
                    }
                });
            }
        });
    </script>
</body>
</html>';
    
    echo $html;
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