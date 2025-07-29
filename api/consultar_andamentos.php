<?php
/**
 * API para consultar andamentos e calcular tempo por unidade
 * Integrada ao sistema CGLIC
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
verificarLogin();

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use GET.'
    ]);
    exit;
}

try {
    $pdo = conectarDB();
    
    // Parâmetros de consulta
    $nup = $_GET['nup'] ?? null;
    $processo_id = $_GET['processo_id'] ?? null;
    $calcular_tempo = isset($_GET['calcular_tempo']) && $_GET['calcular_tempo'] === 'true';
    
    if (!$nup && !$processo_id) {
        throw new Exception('Parâmetro nup ou processo_id é obrigatório.');
    }
    
    // Preparar consulta
    $where_conditions = [];
    $params = [];
    
    if ($nup) {
        $where_conditions[] = "nup = ?";
        $params[] = $nup;
    }
    
    if ($processo_id) {
        $where_conditions[] = "processo_id = ?";
        $params[] = $processo_id;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Buscar dados dos andamentos
    $stmt = $pdo->prepare("
        SELECT id, nup, processo_id, data_hora, unidade, usuario, descricao, importacao_timestamp
        FROM historico_andamentos 
        WHERE {$where_clause}
        ORDER BY data_hora DESC
    ");
    $stmt->execute($params);
    $andamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($andamentos)) {
        echo json_encode([
            'success' => true,
            'message' => 'Nenhum andamento encontrado.',
            'data' => [],
            'total' => 0
        ]);
        exit;
    }
    
    // Agrupar andamentos por NUP para análise
    $agrupados_por_nup = [];
    foreach ($andamentos as $andamento) {
        $nup_key = $andamento['nup'];
        if (!isset($agrupados_por_nup[$nup_key])) {
            $agrupados_por_nup[$nup_key] = [
                'nup' => $andamento['nup'],
                'processo_id' => $andamento['processo_id'],
                'andamentos' => [],
                'total_andamentos' => 0,
                'primeira_data' => null,
                'ultima_data' => null,
                'unidades_envolvidas' => []
            ];
        }
        
        $agrupados_por_nup[$nup_key]['andamentos'][] = $andamento;
        $agrupados_por_nup[$nup_key]['total_andamentos']++;
        
        // Acompanhar datas extremas
        $data_andamento = $andamento['data_hora'];
        if (!$agrupados_por_nup[$nup_key]['primeira_data'] || $data_andamento < $agrupados_por_nup[$nup_key]['primeira_data']) {
            $agrupados_por_nup[$nup_key]['primeira_data'] = $data_andamento;
        }
        if (!$agrupados_por_nup[$nup_key]['ultima_data'] || $data_andamento > $agrupados_por_nup[$nup_key]['ultima_data']) {
            $agrupados_por_nup[$nup_key]['ultima_data'] = $data_andamento;
        }
        
        // Acompanhar unidades envolvidas
        if (!in_array($andamento['unidade'], $agrupados_por_nup[$nup_key]['unidades_envolvidas'])) {
            $agrupados_por_nup[$nup_key]['unidades_envolvidas'][] = $andamento['unidade'];
        }
    }
    
    // Processar resultados
    $dados_processados = [];
    $tempo_por_unidade_geral = [];
    
    foreach ($agrupados_por_nup as $dados_nup) {
        $item = [
            'nup' => $dados_nup['nup'],
            'processo_id' => $dados_nup['processo_id'],
            'total_andamentos' => $dados_nup['total_andamentos'],
            'primeira_data' => $dados_nup['primeira_data'],
            'ultima_data' => $dados_nup['ultima_data'],
            'unidades_envolvidas' => $dados_nup['unidades_envolvidas'],
            'andamentos' => $dados_nup['andamentos']
        ];
        
        if ($calcular_tempo) {
            // Calcular tempo por unidade baseado nas datas reais
            $tempo_por_unidade = calcularTempoPorUnidade($dados_nup['andamentos']);
            $item['tempo_por_unidade'] = $tempo_por_unidade;
            
            // Acumular totais gerais
            foreach ($tempo_por_unidade as $unidade => $dados_tempo) {
                if (!isset($tempo_por_unidade_geral[$unidade])) {
                    $tempo_por_unidade_geral[$unidade] = ['dias' => 0, 'total_periodos' => 0];
                }
                $tempo_por_unidade_geral[$unidade]['dias'] += $dados_tempo['dias'];
                $tempo_por_unidade_geral[$unidade]['total_periodos'] += $dados_tempo['total_periodos'];
            }
        }
        
        $dados_processados[] = $item;
    }
    
    // Resposta
    $resposta = [
        'success' => true,
        'message' => 'Dados encontrados com sucesso.',
        'data' => $dados_processados,
        'total' => count($dados_processados),
        'total_andamentos_individuais' => count($andamentos)
    ];
    
    if ($calcular_tempo && !empty($tempo_por_unidade_geral)) {
        $resposta['resumo_tempo_por_unidade'] = $tempo_por_unidade_geral;
        $resposta['total_dias_geral'] = array_sum(array_column($tempo_por_unidade_geral, 'dias'));
    }
    
    echo json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Função para calcular tempo por unidade baseado nas datas reais dos andamentos
 */
function calcularTempoPorUnidade(array $andamentos): array
{
    if (empty($andamentos)) {
        return [];
    }
    
    // Ordenar andamentos por data (mais antigo primeiro)
    usort($andamentos, function($a, $b) {
        return strcmp($a['data_hora'], $b['data_hora']);
    });
    
    $tempo_por_unidade = [];
    $unidade_anterior = null;
    $data_anterior = null;
    
    foreach ($andamentos as $andamento) {
        $unidade_atual = $andamento['unidade'];
        $data_atual = new DateTime($andamento['data_hora']);
        
        // Se temos uma unidade anterior, calcular o tempo que ficou nela
        if ($unidade_anterior && $data_anterior) {
            $diferenca = $data_anterior->diff($data_atual);
            $dias = $diferenca->days;
            
            if (!isset($tempo_por_unidade[$unidade_anterior])) {
                $tempo_por_unidade[$unidade_anterior] = [
                    'dias' => 0,
                    'total_periodos' => 0,
                    'detalhes' => []
                ];
            }
            
            $tempo_por_unidade[$unidade_anterior]['dias'] += $dias;
            $tempo_por_unidade[$unidade_anterior]['total_periodos']++;
            $tempo_por_unidade[$unidade_anterior]['detalhes'][] = [
                'data_inicio' => $data_anterior->format('Y-m-d H:i:s'),
                'data_fim' => $data_atual->format('Y-m-d H:i:s'),
                'dias' => $dias
            ];
        }
        
        $unidade_anterior = $unidade_atual;
        $data_anterior = $data_atual;
    }
    
    // Calcular médias
    foreach ($tempo_por_unidade as $unidade => &$dados) {
        $dados['media_dias_por_periodo'] = $dados['total_periodos'] > 0 
            ? round($dados['dias'] / $dados['total_periodos'], 2) 
            : 0;
    }
    
    return $tempo_por_unidade;
}
?>