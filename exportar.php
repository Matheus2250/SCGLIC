<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// Aplicar os mesmos filtros do dashboard
$where = ["p.numero_dfd IS NOT NULL", "p.numero_dfd != ''"];
$params = [];

if (!empty($_GET['numero_contratacao'])) {
    $where[] = "p.numero_dfd LIKE ?";
    $params[] = '%' . $_GET['numero_contratacao'] . '%';
}

if (!empty($_GET['situacao_execucao'])) {
    if ($_GET['situacao_execucao'] === 'Não iniciado') {
        $where[] = "(p.situacao_execucao IS NULL OR p.situacao_execucao = '' OR p.situacao_execucao = 'Não iniciado')";
    } else {
        $where[] = "p.situacao_execucao = ?";
        $params[] = $_GET['situacao_execucao'];
    }
}

if (!empty($_GET['categoria'])) {
    $where[] = "p.categoria_contratacao = ?";
    $params[] = $_GET['categoria'];
}

if (!empty($_GET['area_requisitante'])) {
    $filtro_area = $_GET['area_requisitante'];
    if ($filtro_area === 'GM.') {
        $where[] = "(p.area_requisitante LIKE 'GM%' OR p.area_requisitante LIKE 'GM.%')";
    } else {
        $where[] = "p.area_requisitante LIKE ?";
        $params[] = $filtro_area . '%';
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Query para buscar dados agrupados
$sql = "SELECT 
        MAX(p.numero_contratacao) as numero_contratacao,
        p.numero_dfd,
        MAX(p.status_contratacao) as status_contratacao,
        MAX(p.situacao_execucao) as situacao_execucao,
        MAX(p.titulo_contratacao) as titulo_contratacao,
        MAX(p.categoria_contratacao) as categoria_contratacao,
        MAX(p.uasg_atual) as uasg_atual,
        MAX(p.valor_total_contratacao) as valor_total_contratacao,
        MAX(p.data_inicio_processo) as data_inicio_processo,
        MAX(p.data_conclusao_processo) as data_conclusao_processo,
        MAX(p.prazo_duracao_dias) as prazo_duracao_dias,
        MAX(p.area_requisitante) as area_requisitante,
        MAX(p.prioridade) as prioridade,
        COUNT(*) as qtd_itens,
        MAX(p.classificacao_contratacao) as classificacao_contratacao,
        MAX(p.codigo_classe_grupo) as codigo_classe_grupo,
        MAX(p.nome_classe_grupo) as nome_classe_grupo,
        DATEDIFF(MAX(p.data_conclusao_processo), CURDATE()) as dias_ate_conclusao,
        MAX((SELECT COUNT(*) FROM licitacoes WHERE pca_dados_id IN (
            SELECT id FROM pca_dados WHERE numero_dfd = p.numero_dfd
        ))) as tem_licitacao,
        MAX((SELECT nup FROM licitacoes WHERE pca_dados_id IN (
            SELECT id FROM pca_dados WHERE numero_dfd = p.numero_dfd
        ) LIMIT 1)) as nup_licitacao,
        MAX((SELECT situacao FROM licitacoes WHERE pca_dados_id IN (
            SELECT id FROM pca_dados WHERE numero_dfd = p.numero_dfd
        ) LIMIT 1)) as situacao_licitacao
        FROM pca_dados p 
        $whereClause 
        GROUP BY p.numero_dfd
        ORDER BY p.numero_dfd DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Configurar headers para download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="exportacao_pca_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Adicionar BOM para UTF-8
echo "\xEF\xBB\xBF";

// Abrir output stream
$output = fopen('php://output', 'w');

// Escrever cabeçalho
$cabecalho = [
    'Número do DFD',          
    'Número da Contratação', 
    'Status da Contratação',
    'Situação da Execução',
    'Título da Contratação',
    'Categoria',
    'UASG Atual',
    'Valor Total',
    'Data Início',
    'Data Conclusão',
    'Prazo (dias)',
    'Dias até Conclusão',
    'Área Requisitante',
    'Prioridade',
    'Qtd Itens',
    'Classificação',
    'Código Classe/Grupo',
    'Nome Classe/Grupo',
    'Licitado',
    'NUP Licitação',
    'Situação Licitação'
];

fputcsv($output, $cabecalho, ';');

// Escrever dados
while ($row = $stmt->fetch()) {
    $linha = [
    $row['numero_dfd'],                                    // MUDANÇA: primeiro campo é DFD
    $row['numero_contratacao'],                            // ADICIONAR número da contratação como segundo campo
    $row['status_contratacao'],
    $row['situacao_execucao'],
    $row['titulo_contratacao'],
    $row['categoria_contratacao'],
    $row['uasg_atual'],
    number_format($row['valor_total_contratacao'], 2, ',', '.'),
    $row['data_inicio_processo'] ? date('d/m/Y', strtotime($row['data_inicio_processo'])) : '',
    $row['data_conclusao_processo'] ? date('d/m/Y', strtotime($row['data_conclusao_processo'])) : '',
    $row['prazo_duracao_dias'],
    $row['dias_ate_conclusao'] !== null ? $row['dias_ate_conclusao'] : '',
    $row['area_requisitante'],
    $row['prioridade'],
    $row['qtd_itens'],
    $row['classificacao_contratacao'],
    $row['codigo_classe_grupo'],
    $row['nome_classe_grupo'],
    $row['tem_licitacao'] > 0 ? 'Sim' : 'Não',
    $row['nup_licitacao'] ?: '',
    $row['situacao_licitacao'] ? str_replace('_', ' ', $row['situacao_licitacao']) : ''
];
    
    fputcsv($output, $linha, ';');
}

fclose($output);

// Registrar log
registrarLog('EXPORTACAO', 'Exportou dados do PCA com filtros: ' . json_encode($_GET));
exit;
?>