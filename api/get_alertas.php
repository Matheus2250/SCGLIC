<?php
/**
 * API para buscar alertas de contratos
 * Retorna alertas de vencimento, pagamentos e execução
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Verificar login
if (!verificarLogin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

header('Content-Type: application/json');

try {
    $alertas = [];
    
    // Verificar se as tabelas existem
    $tablesExist = $conn->query("SHOW TABLES LIKE 'contratos'")->num_rows > 0;
    
    if (!$tablesExist) {
        echo json_encode(['alertas' => [], 'total' => 0]);
        exit;
    }
    
    // 1. Contratos vencendo em 30 dias
    $alertasVencimento = $conn->query("
        SELECT 
            'vencimento' as tipo,
            'urgente' as prioridade,
            c.numero_contrato,
            c.objeto,
            c.contratado_nome,
            c.data_fim_vigencia,
            c.valor_total,
            DATEDIFF(c.data_fim_vigencia, CURDATE()) as dias_restantes,
            CONCAT('Contrato ', c.numero_contrato, ' vence em ', DATEDIFF(c.data_fim_vigencia, CURDATE()), ' dias') as mensagem
        FROM contratos c 
        WHERE c.data_fim_vigencia <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          AND c.data_fim_vigencia >= CURDATE()
          AND c.status_contrato = 'vigente'
        ORDER BY c.data_fim_vigencia ASC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);
    
    // 2. Contratos já vencidos
    $alertasVencidos = $conn->query("
        SELECT 
            'vencido' as tipo,
            'critico' as prioridade,
            c.numero_contrato,
            c.objeto,
            c.contratado_nome,
            c.data_fim_vigencia,
            c.valor_total,
            ABS(DATEDIFF(c.data_fim_vigencia, CURDATE())) as dias_vencido,
            CONCAT('Contrato ', c.numero_contrato, ' vencido há ', ABS(DATEDIFF(c.data_fim_vigencia, CURDATE())), ' dias') as mensagem
        FROM contratos c 
        WHERE c.data_fim_vigencia < CURDATE()
          AND c.status_contrato = 'vigente'
        ORDER BY c.data_fim_vigencia ASC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    // 3. Contratos sem pagamento há mais de 90 dias
    $alertasPagamento = $conn->query("
        SELECT 
            'pagamento' as tipo,
            'atencao' as prioridade,
            c.numero_contrato,
            c.objeto,
            c.contratado_nome,
            c.data_fim_vigencia,
            c.valor_total,
            DATEDIFF(CURDATE(), COALESCE(MAX(p.data_pagamento), c.data_inicio_vigencia)) as dias_sem_pagamento,
            CONCAT('Contrato ', c.numero_contrato, ' sem pagamento há ', DATEDIFF(CURDATE(), COALESCE(MAX(p.data_pagamento), c.data_inicio_vigencia)), ' dias') as mensagem
        FROM contratos c
        LEFT JOIN contratos_pagamentos p ON c.id = p.contrato_id
        WHERE c.status_contrato = 'vigente'
          AND c.valor_empenhado > 0
        GROUP BY c.id
        HAVING dias_sem_pagamento > 90
        ORDER BY dias_sem_pagamento DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    // 4. Contratos com execução longa sem aditivo
    $alertasExecucao = $conn->query("
        SELECT 
            'execucao_longa' as tipo,
            'atencao' as prioridade,
            c.numero_contrato,
            c.objeto,
            c.contratado_nome,
            c.data_fim_vigencia,
            c.valor_total,
            DATEDIFF(CURDATE(), c.data_inicio_vigencia) as dias_execucao,
            CONCAT('Contrato ', c.numero_contrato, ' em execução há ', DATEDIFF(CURDATE(), c.data_inicio_vigencia), ' dias sem aditivo') as mensagem
        FROM contratos c
        WHERE c.status_contrato = 'vigente'
          AND DATEDIFF(CURDATE(), c.data_inicio_vigencia) > 365
          AND NOT EXISTS (
              SELECT 1 FROM contratos_aditivos ca 
              WHERE ca.contrato_id = c.id
          )
        ORDER BY dias_execucao DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    // 5. Contratos com valor empenhado acima do valor total
    $alertasValor = $conn->query("
        SELECT 
            'valor_excedido' as tipo,
            'critico' as prioridade,
            c.numero_contrato,
            c.objeto,
            c.contratado_nome,
            c.data_fim_vigencia,
            c.valor_total,
            c.valor_empenhado,
            CONCAT('Contrato ', c.numero_contrato, ' com valor empenhado superior ao valor total') as mensagem
        FROM contratos c
        WHERE c.valor_empenhado > c.valor_total
          AND c.status_contrato = 'vigente'
        ORDER BY (c.valor_empenhado - c.valor_total) DESC
        LIMIT 3
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Consolidar todos os alertas
    $alertas = array_merge(
        $alertasVencidos,
        $alertasVencimento, 
        $alertasPagamento, 
        $alertasExecucao,
        $alertasValor
    );
    
    // Adicionar timestamp e ID único para cada alerta
    foreach ($alertas as &$alerta) {
        $alerta['id'] = uniqid();
        $alerta['timestamp'] = date('Y-m-d H:i:s');
        $alerta['valor_formatado'] = 'R$ ' . number_format($alerta['valor_total'], 2, ',', '.');
        
        // Definir ícone baseado no tipo
        switch ($alerta['tipo']) {
            case 'vencimento':
                $alerta['icone'] = 'clock';
                break;
            case 'vencido':
                $alerta['icone'] = 'alert-circle';
                break;
            case 'pagamento':
                $alerta['icone'] = 'credit-card';
                break;
            case 'execucao_longa':
                $alerta['icone'] = 'calendar';
                break;
            case 'valor_excedido':
                $alerta['icone'] = 'dollar-sign';
                break;
            default:
                $alerta['icone'] = 'alert-triangle';
        }
        
        // Definir cor baseado na prioridade
        switch ($alerta['prioridade']) {
            case 'critico':
                $alerta['cor'] = '#dc3545';
                break;
            case 'urgente':
                $alerta['cor'] = '#fd7e14';
                break;
            case 'atencao':
                $alerta['cor'] = '#ffc107';
                break;
            default:
                $alerta['cor'] = '#6c757d';
        }
    }
    
    // Ordenar por prioridade e data
    usort($alertas, function($a, $b) {
        $prioridadeOrdem = ['critico' => 1, 'urgente' => 2, 'atencao' => 3];
        
        $prioA = $prioridadeOrdem[$a['prioridade']] ?? 4;
        $prioB = $prioridadeOrdem[$b['prioridade']] ?? 4;
        
        if ($prioA === $prioB) {
            // Se mesma prioridade, ordenar por data de vencimento
            return strtotime($a['data_fim_vigencia']) - strtotime($b['data_fim_vigencia']);
        }
        
        return $prioA - $prioB;
    });
    
    // Limitar a 20 alertas mais importantes
    $alertas = array_slice($alertas, 0, 20);
    
    // Estatísticas dos alertas
    $stats = [
        'total' => count($alertas),
        'criticos' => count(array_filter($alertas, fn($a) => $a['prioridade'] === 'critico')),
        'urgentes' => count(array_filter($alertas, fn($a) => $a['prioridade'] === 'urgente')),
        'atencao' => count(array_filter($alertas, fn($a) => $a['prioridade'] === 'atencao')),
        'tipos' => [
            'vencimento' => count(array_filter($alertas, fn($a) => $a['tipo'] === 'vencimento')),
            'vencido' => count(array_filter($alertas, fn($a) => $a['tipo'] === 'vencido')),
            'pagamento' => count(array_filter($alertas, fn($a) => $a['tipo'] === 'pagamento')),
            'execucao_longa' => count(array_filter($alertas, fn($a) => $a['tipo'] === 'execucao_longa')),
            'valor_excedido' => count(array_filter($alertas, fn($a) => $a['tipo'] === 'valor_excedido'))
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'alertas' => $alertas,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'alertas' => [],
        'stats' => ['total' => 0]
    ]);
}
?>