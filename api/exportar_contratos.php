<?php
/**
 * API para exportar contratos em diferentes formatos
 * Sistema CGLIC - Módulo Contratos
 */

require_once '../config.php';
require_once '../functions.php';

verificarLogin();

$formato = $_GET['formato'] ?? 'csv';
$busca = $_GET['busca'] ?? '';
$filtroStatus = $_GET['status'] ?? '';
$filtroVencimento = $_GET['vencimento'] ?? '';

try {
    $pdo = conectarDB();
    
    // Construir query com filtros
    $where = ["1=1"];
    $params = [];
    
    if (!empty($busca)) {
        $where[] = "(numero_contrato LIKE ? OR objeto_servico LIKE ? OR nome_empresa LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }
    
    if (!empty($filtroStatus)) {
        $where[] = "status_contrato = ?";
        $params[] = $filtroStatus;
    }
    
    if (!empty($filtroVencimento)) {
        switch ($filtroVencimento) {
            case 'vencidos':
                $where[] = "data_fim < CURDATE() AND status_contrato = 'ativo'";
                break;
            case '30_dias':
                $where[] = "data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status_contrato = 'ativo'";
                break;
            case '90_dias':
                $where[] = "data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status_contrato = 'ativo'";
                break;
        }
    }
    
    $sql = "SELECT 
                numero_contrato,
                ano_contrato,
                numero_sei,
                modalidade,
                objeto_servico,
                nome_empresa,
                cnpj_cpf,
                status_contrato,
                valor_inicial,
                valor_atual,
                DATE_FORMAT(data_assinatura, '%d/%m/%Y') as data_assinatura,
                DATE_FORMAT(data_inicio, '%d/%m/%Y') as data_inicio,
                DATE_FORMAT(data_fim, '%d/%m/%Y') as data_fim,
                area_gestora,
                fiscais,
                DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i') as criado_em
            FROM contratos 
            WHERE " . implode(" AND ", $where) . "
            ORDER BY numero_contrato DESC, ano_contrato DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contratos = $stmt->fetchAll();
    
    if (empty($contratos)) {
        echo '<script>alert("Nenhum contrato encontrado com os filtros aplicados."); window.close();</script>';
        exit;
    }
    
    // Gerar arquivo baseado no formato
    $filename = 'contratos_' . date('Y-m-d_H-i-s');
    
    if ($formato === 'csv') {
        // Exportar CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        // BOM para UTF-8 (para Excel reconhecer acentos)
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Cabeçalhos
        fputcsv($output, [
            'Número/Ano',
            'SEI',
            'Modalidade',
            'Objeto',
            'Empresa',
            'CNPJ/CPF',
            'Status',
            'Valor Inicial',
            'Valor Atual',
            'Assinatura',
            'Início',
            'Fim',
            'Área Gestora',
            'Fiscais',
            'Criado em'
        ], ';');
        
        // Dados
        foreach ($contratos as $contrato) {
            fputcsv($output, [
                $contrato['numero_contrato'] . '/' . $contrato['ano_contrato'],
                $contrato['numero_sei'],
                $contrato['modalidade'],
                $contrato['objeto_servico'],
                $contrato['nome_empresa'],
                $contrato['cnpj_cpf'],
                ucfirst($contrato['status_contrato']),
                'R$ ' . number_format($contrato['valor_inicial'], 2, ',', '.'),
                'R$ ' . number_format($contrato['valor_atual'], 2, ',', '.'),
                $contrato['data_assinatura'],
                $contrato['data_inicio'],
                $contrato['data_fim'],
                $contrato['area_gestora'],
                $contrato['fiscais'],
                $contrato['criado_em']
            ], ';');
        }
        
        fclose($output);
        
    } elseif ($formato === 'json') {
        // Exportar JSON
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        echo json_encode([
            'exportado_em' => date('Y-m-d H:i:s'),
            'total_registros' => count($contratos),
            'filtros' => [
                'busca' => $busca,
                'status' => $filtroStatus,
                'vencimento' => $filtroVencimento
            ],
            'contratos' => $contratos
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Formato não suportado');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo '<script>alert("Erro na exportação: ' . addslashes($e->getMessage()) . '"); window.close();</script>';
}
?>