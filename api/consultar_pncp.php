<?php
/**
 * API para Consulta de Dados do PNCP
 * 
 * Endpoint específico para consultas, filtros e listagem dos dados sincronizados do PNCP
 */

require_once '../config.php';
require_once '../functions.php';

verificarLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = conectarDB();

// Parâmetros da consulta
$acao = $_GET['acao'] ?? '';
$ano = intval($_GET['ano'] ?? 2026);
$pagina = intval($_GET['pagina'] ?? 1);
$limite = intval($_GET['limite'] ?? 20);

// Filtros
$filtros = [
    'uasg' => $_GET['uasg'] ?? '',
    'categoria' => $_GET['categoria'] ?? '',
    'identificador' => $_GET['identificador'] ?? '',
    'busca' => $_GET['busca'] ?? ''
];

try {
    switch ($acao) {
        case 'listar':
            $resultado = listarDadosPNCP($ano, $pagina, $limite, $filtros);
            break;
            
        case 'estatisticas':
            $resultado = obterEstatisticasPNCP($ano);
            break;
            
        case 'exportar':
            exportarDadosPNCP($ano, $filtros);
            exit;
            
        case 'filtros':
            $resultado = obterOpcoesFiltroPNCP($ano);
            break;
            
        default:
            throw new Exception("Ação não reconhecida: {$acao}");
    }
    
    echo json_encode(['sucesso' => true, 'dados' => $resultado], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Listar dados do PNCP com filtros e paginação
 */
function listarDadosPNCP($ano, $pagina, $limite, $filtros) {
    global $pdo;
    
    // Construir WHERE clause
    $where = ["ano_pca = ?"];
    $params = [$ano];
    
    if (!empty($filtros['uasg'])) {
        $where[] = "uasg = ?";
        $params[] = $filtros['uasg'];
    }
    
    if (!empty($filtros['categoria'])) {
        $where[] = "categoria_item LIKE ?";
        $params[] = '%' . $filtros['categoria'] . '%';
    }
    
    if (!empty($filtros['identificador'])) {
        $where[] = "identificador_futura_contratacao LIKE ?";
        $params[] = '%' . $filtros['identificador'] . '%';
    }
    
    if (!empty($filtros['busca'])) {
        $where[] = "(descricao_item_fornecimento LIKE ? OR identificador_futura_contratacao LIKE ? OR nome_futura_contratacao LIKE ?)";
        $params[] = '%' . $filtros['busca'] . '%';
        $params[] = '%' . $filtros['busca'] . '%';
        $params[] = '%' . $filtros['busca'] . '%';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total de registros
    $sqlCount = "SELECT COUNT(*) as total FROM pca_pncp WHERE {$whereClause}";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Calcular offset
    $offset = ($pagina - 1) * $limite;
    
    // Buscar dados com paginação
    $sql = "SELECT 
                unidade_responsavel,
                uasg,
                id_item_pca,
                categoria_item,
                identificador_futura_contratacao,
                nome_futura_contratacao,
                catalogo_utilizado,
                classificacao_catalogo,
                codigo_classificacao_superior,
                nome_classificacao_superior,
                codigo_pdm_item,
                nome_pdm_item,
                codigo_item,
                descricao_item_fornecimento,
                unidade,
                quantidade_estimada,
                valor_unitario_estimado,
                valor_total_estimado,
                valor_orcamentario_exercicio,
                data_desejada,
                data_sincronizacao
            FROM pca_pncp 
            WHERE {$whereClause}
            ORDER BY id_item_pca ASC
            LIMIT {$limite} OFFSET {$offset}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estatísticas da página
    $totalPaginas = ceil($totalRegistros / $limite);
    $valorTotalPagina = array_sum(array_column($dados, 'valor_total_estimado'));
    
    return [
        'dados' => $dados,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => $totalPaginas,
            'total_registros' => $totalRegistros,
            'limite' => $limite,
            'offset' => $offset
        ],
        'estatisticas' => [
            'total_registros_pagina' => count($dados),
            'valor_total_pagina' => $valorTotalPagina
        ],
        'filtros_aplicados' => $filtros
    ];
}

/**
 * Obter estatísticas dos dados do PNCP
 */
function obterEstatisticasPNCP($ano) {
    global $pdo;
    
    $sql = "SELECT 
                COUNT(*) as total_registros,
                COUNT(DISTINCT categoria_item) as total_categorias,
                COUNT(DISTINCT modalidade_licitacao) as total_modalidades,
                COUNT(DISTINCT unidade_requisitante) as total_unidades,
                SUM(valor_estimado) as valor_total,
                AVG(valor_estimado) as valor_medio,
                MAX(valor_estimado) as maior_valor,
                MIN(valor_estimado) as menor_valor,
                COUNT(CASE WHEN situacao_item = 'Planejado' THEN 1 END) as total_planejados,
                COUNT(CASE WHEN situacao_item = 'Em andamento' THEN 1 END) as total_andamento,
                COUNT(CASE WHEN situacao_item = 'Concluído' THEN 1 END) as total_concluidos,
                COUNT(CASE WHEN situacao_item = 'Cancelado' THEN 1 END) as total_cancelados,
                COUNT(CASE WHEN trimestre_previsto = 1 THEN 1 END) as trimestre_1,
                COUNT(CASE WHEN trimestre_previsto = 2 THEN 1 END) as trimestre_2,
                COUNT(CASE WHEN trimestre_previsto = 3 THEN 1 END) as trimestre_3,
                COUNT(CASE WHEN trimestre_previsto = 4 THEN 1 END) as trimestre_4,
                MAX(data_sincronizacao) as ultima_sincronizacao,
                MAX(data_ultima_atualizacao) as ultima_atualizacao_pncp
            FROM pca_pncp 
            WHERE ano_pca = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ano]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar distribuição por categoria
    $sqlCategorias = "SELECT 
                        categoria_item,
                        COUNT(*) as quantidade,
                        SUM(valor_estimado) as valor_total
                      FROM pca_pncp 
                      WHERE ano_pca = ? AND categoria_item IS NOT NULL
                      GROUP BY categoria_item 
                      ORDER BY valor_total DESC 
                      LIMIT 10";
    
    $stmtCat = $pdo->prepare($sqlCategorias);
    $stmtCat->execute([$ano]);
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar distribuição por modalidade
    $sqlModalidades = "SELECT 
                         modalidade_licitacao,
                         COUNT(*) as quantidade,
                         SUM(valor_estimado) as valor_total
                       FROM pca_pncp 
                       WHERE ano_pca = ? AND modalidade_licitacao IS NOT NULL
                       GROUP BY modalidade_licitacao 
                       ORDER BY quantidade DESC";
    
    $stmtMod = $pdo->prepare($sqlModalidades);
    $stmtMod->execute([$ano]);
    $modalidades = $stmtMod->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'geral' => $stats,
        'por_categoria' => $categorias,
        'por_modalidade' => $modalidades
    ];
}

/**
 * Obter opções disponíveis para filtros
 */
function obterOpcoesFiltroPNCP($ano) {
    global $pdo;
    
    // Categorias
    $sqlCat = "SELECT DISTINCT categoria_item FROM pca_pncp WHERE ano_pca = ? AND categoria_item IS NOT NULL ORDER BY categoria_item";
    $stmtCat = $pdo->prepare($sqlCat);
    $stmtCat->execute([$ano]);
    $categorias = $stmtCat->fetchAll(PDO::FETCH_COLUMN);
    
    // Modalidades
    $sqlMod = "SELECT DISTINCT modalidade_licitacao FROM pca_pncp WHERE ano_pca = ? AND modalidade_licitacao IS NOT NULL ORDER BY modalidade_licitacao";
    $stmtMod = $pdo->prepare($sqlMod);
    $stmtMod->execute([$ano]);
    $modalidades = $stmtMod->fetchAll(PDO::FETCH_COLUMN);
    
    // Situações
    $sqlSit = "SELECT DISTINCT situacao_item FROM pca_pncp WHERE ano_pca = ? AND situacao_item IS NOT NULL ORDER BY situacao_item";
    $stmtSit = $pdo->prepare($sqlSit);
    $stmtSit->execute([$ano]);
    $situacoes = $stmtSit->fetchAll(PDO::FETCH_COLUMN);
    
    // Unidades
    $sqlUni = "SELECT DISTINCT unidade_requisitante FROM pca_pncp WHERE ano_pca = ? AND unidade_requisitante IS NOT NULL ORDER BY unidade_requisitante LIMIT 50";
    $stmtUni = $pdo->prepare($sqlUni);
    $stmtUni->execute([$ano]);
    $unidades = $stmtUni->fetchAll(PDO::FETCH_COLUMN);
    
    return [
        'categorias' => $categorias,
        'modalidades' => $modalidades,
        'situacoes' => $situacoes,
        'unidades' => $unidades
    ];
}

/**
 * Exportar dados do PNCP em CSV
 */
function exportarDadosPNCP($ano, $filtros) {
    global $pdo;
    
    // Construir WHERE clause (mesmo código da função listar)
    $where = ["ano_pca = ?"];
    $params = [$ano];
    
    if (!empty($filtros['categoria'])) {
        $where[] = "categoria_item LIKE ?";
        $params[] = '%' . $filtros['categoria'] . '%';
    }
    
    if (!empty($filtros['modalidade'])) {
        $where[] = "modalidade_licitacao = ?";
        $params[] = $filtros['modalidade'];
    }
    
    if (!empty($filtros['trimestre'])) {
        $where[] = "trimestre_previsto = ?";
        $params[] = intval($filtros['trimestre']);
    }
    
    if (!empty($filtros['situacao'])) {
        $where[] = "situacao_item = ?";
        $params[] = $filtros['situacao'];
    }
    
    if (!empty($filtros['busca'])) {
        $where[] = "(descricao_item LIKE ? OR codigo_pncp LIKE ?)";
        $params[] = '%' . $filtros['busca'] . '%';
        $params[] = '%' . $filtros['busca'] . '%';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Buscar todos os dados (sem limite)
    $sql = "SELECT 
                sequencial,
                categoria_item,
                subcategoria_item,
                descricao_item,
                justificativa,
                valor_estimado,
                unidade_medida,
                quantidade,
                modalidade_licitacao,
                trimestre_previsto,
                mes_previsto,
                situacao_item,
                codigo_pncp,
                unidade_requisitante,
                responsavel_demanda,
                email_responsavel,
                telefone_responsavel,
                observacoes,
                data_ultima_atualizacao,
                data_sincronizacao
            FROM pca_pncp 
            WHERE {$whereClause}
            ORDER BY sequencial ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Configurar headers para download
    $filename = "pca_pncp_{$ano}_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Criar output stream
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 (para Excel reconhecer acentos)
    fwrite($output, chr(239) . chr(187) . chr(191));
    
    // Cabeçalhos CSV
    fputcsv($output, [
        'Sequencial',
        'Categoria',
        'Subcategoria',
        'Descrição',
        'Justificativa',
        'Valor Estimado',
        'Unidade Medida',
        'Quantidade',
        'Modalidade',
        'Trimestre',
        'Mês',
        'Situação',
        'Código PNCP',
        'Unidade Requisitante',
        'Responsável',
        'Email',
        'Telefone',
        'Observações',
        'Última Atualização PNCP',
        'Data Sincronização'
    ], ';');
    
    // Dados
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['sequencial'],
            $row['categoria_item'],
            $row['subcategoria_item'],
            $row['descricao_item'],
            $row['justificativa'],
            number_format($row['valor_estimado'], 2, ',', '.'),
            $row['unidade_medida'],
            $row['quantidade'],
            $row['modalidade_licitacao'],
            $row['trimestre_previsto'],
            $row['mes_previsto'],
            $row['situacao_item'],
            $row['codigo_pncp'],
            $row['unidade_requisitante'],
            $row['responsavel_demanda'],
            $row['email_responsavel'],
            $row['telefone_responsavel'],
            $row['observacoes'],
            $row['data_ultima_atualizacao'],
            $row['data_sincronizacao']
        ], ';');
    }
    
    fclose($output);
}
?>