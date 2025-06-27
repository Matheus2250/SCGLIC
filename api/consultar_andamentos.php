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
    
    // Buscar dados
    $stmt = $pdo->prepare("
        SELECT id, nup, processo_id, timestamp, total_andamentos, andamentos_json, 
               criado_em, atualizado_em
        FROM processo_andamentos 
        WHERE {$where_clause}
        ORDER BY atualizado_em DESC
    ");
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($resultados)) {
        echo json_encode([
            'success' => true,
            'message' => 'Nenhum andamento encontrado.',
            'data' => [],
            'total' => 0
        ]);
        exit;
    }
    
    // Processar resultados
    $dados_processados = [];
    $total_dias_geral = [];
    
    foreach ($resultados as $registro) {
        $andamentos = json_decode($registro['andamentos_json'], true);
        
        $item = [
            'id' => $registro['id'],
            'nup' => $registro['nup'],
            'processo_id' => $registro['processo_id'],
            'timestamp' => $registro['timestamp'],
            'total_andamentos' => $registro['total_andamentos'],
            'criado_em' => $registro['criado_em'],
            'atualizado_em' => $registro['atualizado_em']
        ];
        
        if ($calcular_tempo && is_array($andamentos)) {
            // Calcular tempo por unidade usando a função do arquivo original
            $tempo_por_unidade = calcularDiasPorUnidade($andamentos);
            $item['tempo_por_unidade'] = $tempo_por_unidade;
            
            // Acumular totais gerais
            foreach ($tempo_por_unidade as $unidade => $dias) {
                if (!isset($total_dias_geral[$unidade])) {
                    $total_dias_geral[$unidade] = 0;
                }
                $total_dias_geral[$unidade] += $dias;
            }
        } else {
            $item['andamentos'] = $andamentos;
        }
        
        $dados_processados[] = $item;
    }
    
    // Resposta
    $resposta = [
        'success' => true,
        'message' => 'Dados encontrados com sucesso.',
        'data' => $dados_processados,
        'total' => count($dados_processados)
    ];
    
    if ($calcular_tempo && !empty($total_dias_geral)) {
        $resposta['resumo_tempo_por_unidade'] = $total_dias_geral;
        $resposta['total_dias_geral'] = array_sum($total_dias_geral);
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
 * Função para calcular dias por unidade (copiada do arquivo original)
 */
function calcularDiasPorUnidade(array $andamentos): array
{
    $totais = [];
    foreach ($andamentos as $item) {
        if (!isset($item['unidade']) || !isset($item['dias'])) {
            continue;
        }
        $unidade = $item['unidade'];
        $dias = (int)$item['dias'];
        if (!isset($totais[$unidade])) {
            $totais[$unidade] = 0;
        }
        $totais[$unidade] += $dias;
    }
    return $totais;
}
?>