<?php
require_once 'config.php';
require_once 'functions.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

verificarLogin();

$pdo = conectarDB();

// Buscar anos disponíveis para o filtro
$anos_disponiveis = [];
try {
    $sql_anos = "SELECT DISTINCT YEAR(data_abertura) as ano 
                 FROM licitacoes 
                 WHERE data_abertura IS NOT NULL 
                 ORDER BY ano DESC";
    $stmt_anos = $pdo->query($sql_anos);
    $anos_disponiveis = $stmt_anos->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Se houver erro, manter array vazio
    $anos_disponiveis = [];
}


// Verificar se é uma requisição AJAX para filtros
if (isset($_GET['ajax']) && $_GET['ajax'] === 'filtrar_licitacoes') {
    // Processar filtros (mesmo código que já existe)
    $licitacoes_por_pagina = isset($_GET['por_pagina']) ? max(10, min(100, intval($_GET['por_pagina']))) : 10;
    $pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $offset = ($pagina_atual - 1) * $licitacoes_por_pagina;

    $filtro_situacao = $_GET['situacao_filtro'] ?? '';
    $filtro_busca = $_GET['busca'] ?? '';
    $filtro_ano = $_GET['ano_filtro'] ?? '';

    $where_conditions = ['1=1'];
    $params = [];

    if (!empty($filtro_situacao)) {
        $where_conditions[] = "l.situacao = ?";
        $params[] = $filtro_situacao;
    }

    if (!empty($filtro_busca)) {
        $where_conditions[] = "(l.nup LIKE ? OR l.objeto LIKE ? OR l.pregoeiro LIKE ? OR l.numero_contratacao LIKE ?)";
        $busca_param = "%$filtro_busca%";
        $params[] = $busca_param;
        $params[] = $busca_param;
        $params[] = $busca_param;
        $params[] = $busca_param;
    }

    if (!empty($filtro_ano)) {
        $where_conditions[] = "YEAR(l.data_abertura) = ?";
        $params[] = intval($filtro_ano);
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Contar total - NOVA ABORDAGEM sem JOIN problemático
    $sql_count = "SELECT COUNT(*) as total 
                  FROM licitacoes l 
                  LEFT JOIN usuarios u ON l.usuario_id = u.id
                  WHERE $where_clause";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_licitacoes = $stmt_count->fetch()['total'];

    // Buscar licitações - NOVA ABORDAGEM sem JOIN problemático
    $sql_licitacoes = "SELECT 
        l.id,
        l.nup,
        l.numero_contratacao,
        l.modalidade,
        l.tipo,
        l.objeto,
        l.valor_estimado,
        l.valor_homologado,
        l.economia,
        l.situacao,
        l.pregoeiro,
        l.data_entrada_dipli,
        l.data_abertura,
        l.data_homologacao,
        l.data_publicacao,
        l.resp_instrucao,
        l.area_demandante,
        l.qtd_itens,
        l.link,
        l.observacoes,
        l.usuario_id,
        l.pca_dados_id,
        l.criado_em,
        l.atualizado_em,
        u.nome as usuario_nome,
        COALESCE(l.numero_contratacao, 
                (SELECT p.numero_contratacao FROM pca_dados p WHERE p.id = l.pca_dados_id LIMIT 1)
        ) as numero_contratacao_final
        FROM licitacoes l
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        WHERE $where_clause
        ORDER BY l.id DESC
        LIMIT $licitacoes_por_pagina OFFSET $offset";
    
    $stmt_licitacoes = $pdo->prepare($sql_licitacoes);
    $stmt_licitacoes->execute($params);
    $licitacoes_recentes = $stmt_licitacoes->fetchAll();
    

    // Buscar contagem de andamentos separadamente (igual à seção principal)
    if (!empty($licitacoes_recentes)) {
        $nups = array_column($licitacoes_recentes, 'nup');
        $placeholders = str_repeat('?,', count($nups) - 1) . '?';
        
        try {
            $sql_andamentos = "SELECT nup, COUNT(*) as total 
                              FROM historico_andamentos 
                              WHERE nup IN ($placeholders) 
                              GROUP BY nup";
            $stmt_andamentos = $pdo->prepare($sql_andamentos);
            $stmt_andamentos->execute($nups);
            $contagens_andamentos = $stmt_andamentos->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Adicionar contagem aos resultados
            foreach ($licitacoes_recentes as $index => &$licitacao) {
                $licitacao['total_andamentos'] = $contagens_andamentos[$licitacao['nup']] ?? 0;
            }
            unset($licitacao); // Limpar referência para evitar bugs
        } catch (Exception $e) {
            // Se houver erro com a tabela de andamentos, definir como 0
            foreach ($licitacoes_recentes as $index => &$licitacao) {
                $licitacao['total_andamentos'] = 0;
            }
            unset($licitacao); // Limpar referência para evitar bugs
        }
    }

    // Calcular paginação
    $total_paginas = ceil($total_licitacoes / $licitacoes_por_pagina);

    // Garantir que não há duplicações
    $temp_array = [];
    $ids_vistos = [];
    foreach ($licitacoes_recentes as $licitacao) {
        if (!in_array($licitacao['id'], $ids_vistos)) {
            $temp_array[] = $licitacao;
            $ids_vistos[] = $licitacao['id'];
        }
    }
    $licitacoes_recentes = $temp_array;
    
    // Retornar apenas o HTML dos resultados
    ob_start();
    include_once 'partials/lista_licitacoes_ajax.php'; // Vamos criar este arquivo
    $html = ob_get_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'html' => $html,
        'total' => $total_licitacoes,
        'pagina_atual' => $pagina_atual,
        'total_paginas' => $total_paginas
    ]);
    exit;
}

// Buscar estatísticas para os cards e gráficos
$stats_sql = "SELECT
    COUNT(*) as total_licitacoes,
    COUNT(CASE WHEN situacao = 'EM_ANDAMENTO' THEN 1 END) as em_andamento,
    COUNT(CASE WHEN situacao = 'HOMOLOGADO' THEN 1 END) as homologadas,
    COUNT(CASE WHEN situacao = 'FRACASSADO' THEN 1 END) as fracassadas,
    COUNT(CASE WHEN situacao = 'REVOGADO' THEN 1 END) as revogadas,
    SUM(CASE WHEN situacao = 'HOMOLOGADO' THEN valor_estimado ELSE 0 END) as valor_homologado
    FROM licitacoes";

$stats = $pdo->query($stats_sql)->fetch();

// Dados para gráficos
$dados_modalidade = $pdo->query("
    SELECT modalidade, COUNT(*) as quantidade
    FROM licitacoes
    GROUP BY modalidade
")->fetchAll();

$dados_pregoeiro = $pdo->query("
    SELECT
        CASE
            WHEN l.pregoeiro IS NULL OR l.pregoeiro = '' THEN 'Não Definido'
            ELSE l.pregoeiro
        END AS pregoeiro,
        COUNT(*) AS quantidade
    FROM licitacoes l
    GROUP BY l.pregoeiro
    ORDER BY quantidade DESC
    LIMIT 5
")->fetchAll();

$dados_mensal = $pdo->query("
    SELECT
        DATE_FORMAT(
            COALESCE(data_abertura, criado_em),
            '%Y-%m'
        ) as mes,
        COUNT(*) as quantidade,
        SUM(CASE WHEN data_abertura IS NOT NULL THEN 1 ELSE 0 END) as com_data_abertura,
        SUM(CASE WHEN data_abertura IS NULL THEN 1 ELSE 0 END) as sem_data_abertura
    FROM licitacoes
    WHERE (data_abertura IS NOT NULL AND data_abertura >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH))
    OR (data_abertura IS NULL AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH))
    GROUP BY DATE_FORMAT(
        COALESCE(data_abertura, criado_em),
        '%Y-%m'
    )
    ORDER BY mes
")->fetchAll();

// Configuração da paginação
$licitacoes_por_pagina = isset($_GET['por_pagina']) ? max(10, min(100, intval($_GET['por_pagina']))) : 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_atual - 1) * $licitacoes_por_pagina;

// Detectar seção ativa baseada na URL ou seção padrão
$secao_ativa = $_GET['secao'] ?? 'lista-licitacoes';

// Filtros opcionais
$filtro_situacao = $_GET['situacao_filtro'] ?? '';
$filtro_busca = $_GET['busca'] ?? '';
$filtro_ano = $_GET['ano_filtro'] ?? '';

// Construir WHERE clause para filtros
$where_conditions = ['1=1'];
$params = [];

if (!empty($filtro_situacao)) {
    $where_conditions[] = "l.situacao = ?";
    $params[] = $filtro_situacao;
}

if (!empty($filtro_busca)) {
    $where_conditions[] = "(l.nup LIKE ? OR l.objeto LIKE ? OR l.pregoeiro LIKE ? OR l.numero_contratacao LIKE ?)";
    $busca_param = "%$filtro_busca%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
}

if (!empty($filtro_ano)) {
    $where_conditions[] = "YEAR(l.data_abertura) = ?";
    $params[] = intval($filtro_ano);
}

$where_clause = implode(' AND ', $where_conditions);

// Contar total de licitações (para paginação) - NOVA ABORDAGEM sem JOIN problemático
$sql_count = "SELECT COUNT(*) as total 
              FROM licitacoes l 
              LEFT JOIN usuarios u ON l.usuario_id = u.id
              WHERE $where_clause";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_licitacoes = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_licitacoes / $licitacoes_por_pagina);

// Buscar licitações da página atual - NOVA ABORDAGEM sem JOIN problemático
$sql = "SELECT 
            l.id,
            l.nup,
            l.numero_contratacao,
            l.modalidade,
            l.tipo,
            l.objeto,
            l.valor_estimado,
            l.valor_homologado,
            l.economia,
            l.situacao,
            l.pregoeiro,
            l.data_entrada_dipli,
            l.data_abertura,
            l.data_homologacao,
            l.data_publicacao,
            l.resp_instrucao,
            l.area_demandante,
            l.qtd_itens,
            l.link,
            l.observacoes,
            l.usuario_id,
            l.pca_dados_id,
            l.criado_em,
            l.atualizado_em,
            u.nome as usuario_criador_nome,
            COALESCE(l.numero_contratacao, 
                    (SELECT p.numero_contratacao FROM pca_dados p WHERE p.id = l.pca_dados_id LIMIT 1)
            ) as numero_contratacao_final
        FROM licitacoes l 
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        WHERE $where_clause
        ORDER BY l.id DESC
        LIMIT $licitacoes_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$licitacoes_recentes = $stmt->fetchAll();

// Garantir que não há duplicações
if (!empty($licitacoes_recentes)) {
    $temp_array = [];
    $ids_vistos = [];
    foreach ($licitacoes_recentes as $licitacao) {
        if (!in_array($licitacao['id'], $ids_vistos)) {
            $temp_array[] = $licitacao;
            $ids_vistos[] = $licitacao['id'];
        }
    }
    $licitacoes_recentes = $temp_array;
}

// Buscar contagem de andamentos separadamente para evitar problema de collation
if (!empty($licitacoes_recentes)) {
    $nups = array_column($licitacoes_recentes, 'nup');
    $placeholders = str_repeat('?,', count($nups) - 1) . '?';
    
    try {
        $sql_andamentos = "SELECT nup, COUNT(*) as total 
                          FROM historico_andamentos 
                          WHERE nup IN ($placeholders) 
                          GROUP BY nup";
        $stmt_andamentos = $pdo->prepare($sql_andamentos);
        $stmt_andamentos->execute($nups);
        $contagens_andamentos = $stmt_andamentos->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Adicionar contagem aos resultados
        foreach ($licitacoes_recentes as $index => &$licitacao) {
            $licitacao['total_andamentos'] = $contagens_andamentos[$licitacao['nup']] ?? 0;
        }
        unset($licitacao); // Limpar referência para evitar bugs
    } catch (Exception $e) {
        // Se houver erro com a tabela de andamentos, definir como 0
        foreach ($licitacoes_recentes as $index => &$licitacao) {
            $licitacao['total_andamentos'] = 0;
        }
        unset($licitacao); // Limpar referência para evitar bugs
    }
}

// Buscar contratações disponíveis do PCA para o dropdown - todos os anos (2022-2026)
$contratacoes_pca = $pdo->query("
    SELECT DISTINCT
        p.numero_contratacao,
        p.numero_dfd,
        p.titulo_contratacao,
        p.area_requisitante,
        p.valor_total_contratacao,
        pi.ano_pca
    FROM pca_dados p
    INNER JOIN pca_importacoes pi ON p.importacao_id = pi.id
    WHERE p.numero_contratacao IS NOT NULL
    AND p.numero_contratacao != ''
    AND TRIM(p.numero_contratacao) != ''
    AND pi.ano_pca IN (2022, 2023, 2024, 2025, 2026)
    ORDER BY pi.ano_pca DESC, p.numero_contratacao ASC
    LIMIT 2000
")->fetchAll(PDO::FETCH_ASSOC);

// Sistema carregado
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Licitações - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/licitacao-dashboard.css">
    <link rel="stylesheet" href="assets/dark-mode.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    /* Garantir que modais funcionem */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        overflow: auto;
    }
    
    .modal.show {
        display: block !important;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 20px 15px 20px;
        border-bottom: 1px solid #e5e7eb;
        background: #f8f9fa;
        border-radius: 8px 8px 0 0;
    }
    
    .modal-body {
        padding: 20px;
        max-height: calc(90vh - 120px);
        overflow-y: auto;
    }
    
    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
        background: none;
        border: none;
        padding: 0;
        margin: 0;
    }
    
    .close:hover,
    .close:focus {
        color: #000;
        text-decoration: none;
    }
    
    .modal-content {
        position: relative;
        background-color: #fefefe;
        margin: 5% auto;
        padding: 0;
        border: none;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        animation: modalFadeIn 0.3s;
        overflow: hidden;
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-50px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Animação de spinner */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .search-input {
        width: 100% !important;
        padding: 12px 16px !important;
        border: 2px solid #e5e7eb !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        font-family: inherit !important;
        transition: all 0.2s ease !important;
        background: white !important;
        color: #374151 !important;
        outline: none !important;
    }

    .search-input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        transform: translateY(-1px) !important;
    }

    .search-input:hover {
        border-color: #d1d5db !important;
    }

    .search-suggestions {
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        right: 0 !important;
        background: white !important;
        border: 2px solid #e5e7eb !important;
        border-top: none !important;
        border-radius: 0 0 8px 8px !important;
        max-height: 280px !important;
        overflow-y: auto !important;
        z-index: 1000 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        margin-top: -1px !important;
    }

    .suggestion-item {
        padding: 12px 16px !important;
        border-bottom: 1px solid #f3f4f6 !important;
        cursor: pointer !important;
        transition: background 0.15s ease !important;
        font-size: 14px !important;
    }

    .suggestion-item:hover {
        background: #f8fafc !important;
    }

    .suggestion-item:last-child {
        border-bottom: none !important;
    }

    .suggestion-numero {
        font-weight: 600 !important;
        color: #1f2937 !important;
        margin-bottom: 4px !important;
    }

    .suggestion-titulo {
        font-size: 12px !important;
        color: #6b7280 !important;
        line-height: 1.4 !important;
    }

    .no-results {
        padding: 16px !important;
        text-align: center !important;
        color: #9ca3af !important;
        font-style: italic !important;
        font-size: 14px !important;
    }
        /* Estilos para detalhes */
        .detalhes-licitacao {
            font-family: inherit;
        }
        
        .detail-section {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detail-section h4 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 8px;
        }
        
        .detail-section p {
            margin: 8px 0;
            color: #6c757d;
            line-height: 1.5;
        }
        
        .detail-section strong {
            color: #495057;
            font-weight: 600;
        }
        
        /* Estilos para paginação */
        .pagination {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #495057;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .page-link:hover {
            background: #e9ecef;
            border-color: #adb5bd;
            color: #495057;
            text-decoration: none;
        }
        
        .page-link.active {
            background: #007cba;
            border-color: #007cba;
            color: white;
        }
        
        .page-link.active:hover {
            background: #006ba6;
            border-color: #006ba6;
        }
        
        /* Estilos para o modal de Ver Andamentos */
        .modal.modern-modal .btn-report {
            background: rgba(34, 197, 94, 0.1);
            border: 2px solid rgb(34, 197, 94);
            color: rgb(34, 197, 94);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .modal.modern-modal .btn-report:hover {
            background: rgba(34, 197, 94, 0.2);
            border-color: rgb(22, 163, 74);
            color: rgb(22, 163, 74);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .modal.modern-modal .btn-report:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(34, 197, 94, 0.2);
        }
        
        .modal.modern-modal .close-button {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid rgb(239, 68, 68);
            color: rgb(239, 68, 68);
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            transition: all 0.3s ease;
        }
        
        .modal.modern-modal .close-button:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgb(220, 38, 38);
            color: rgb(220, 38, 38);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .modal.modern-modal .close-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2);
        }
        
        .modal.modern-modal .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Header discreto para o modal de Ver Andamentos */
        .modal.modern-modal .modal-header.gradient-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 20px 25px;
            border-radius: 8px 8px 0 0;
        }
        
        .modal.modern-modal .header-info {
            flex: 1;
        }
        
        .modal.modern-modal .modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal.modern-modal .modal-subtitle {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: #6c757d;
            font-weight: 500;
        }

        /* ==================== TIMELINE MELHORADA ==================== */
        
        /* Container da Timeline Melhorada */
        .timeline-container-improved {
            flex: 1;
            background: #f8fafc;
            position: relative;
            max-height: 600px;
            overflow-y: auto;
            padding: 20px;
        }

        /* Timeline Compacta - Estilos Base */
        .timeline-compact {
            position: relative;
            padding-left: 30px;
        }

        .timeline-compact::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #3b82f6, #06b6d4, #10b981);
            border-radius: 2px;
        }

        /* Item da Timeline Compacta */
        .timeline-item-compact {
            position: relative;
            margin-bottom: 16px;
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 3px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .timeline-item-compact:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            border-left-color: #3b82f6;
        }

        .timeline-item-compact::before {
            content: '';
            position: absolute;
            left: -33px;
            top: 20px;
            width: 12px;
            height: 12px;
            background: white;
            border: 3px solid #3b82f6;
            border-radius: 50%;
            z-index: 2;
        }

        /* Estados Especiais */
        .timeline-item-compact.important::before {
            border-color: #f59e0b;
            background: #fef3c7;
        }

        .timeline-item-compact.success::before {
            border-color: #10b981;
            background: #d1fae5;
        }

        .timeline-item-compact.error::before {
            border-color: #ef4444;
            background: #fee2e2;
        }

        /* Header do Item */
        .timeline-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .timeline-item-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
            margin: 0;
        }

        .timeline-item-date {
            font-size: 12px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Conteúdo do Item */
        .timeline-item-content {
            color: #4b5563;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        /* Meta informações */
        .timeline-item-meta {
            display: flex;
            gap: 12px;
            font-size: 11px;
            color: #9ca3af;
        }

        .timeline-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .timeline-meta-item i {
            width: 12px;
            height: 12px;
        }

        /* Estados de Loading */
        .timeline-loading {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .timeline-empty {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .timeline-empty i {
            width: 48px;
            height: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Botão Carregar Mais */
        .load-more-timeline {
            width: 100%;
            padding: 12px 24px;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .load-more-timeline:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }

        .load-more-timeline:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Filtros da Timeline */
        .timeline-filters-compact {
            background: white;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
        }

        .timeline-filters-compact.collapsed {
            padding: 8px 16px;
        }

        .filters-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .filters-content {
            margin-top: 16px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-field label {
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
        }

        .filter-field input,
        .filter-field select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            transition: border-color 0.2s ease;
        }

        .filter-field input:focus,
        .filter-field select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .timeline-item-compact {
            animation: fadeInUp 0.4s ease-out forwards;
            opacity: 0;
        }

        .timeline-filters-compact {
            transition: all 0.3s ease;
        }

        .timeline-filters-compact.collapsed .filters-content {
            display: none;
        }

        .filters-toggle i:last-child {
            transition: transform 0.3s ease;
        }

        .timeline-filters-compact.collapsed .filters-toggle i:last-child {
            transform: rotate(-90deg);
        }

        /* Scrollbar customizada */
        .timeline-container-improved::-webkit-scrollbar {
            width: 8px;
        }

        .timeline-container-improved::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .timeline-container-improved::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .timeline-container-improved::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .timeline-container-improved {
                max-height: 500px;
                padding: 16px;
            }

            .timeline-compact {
                padding-left: 20px;
            }

            .timeline-item-compact::before {
                left: -23px;
            }

            .timeline-filters-compact .filters-content {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
                    <i data-lucide="menu"></i>
                </button>
                <h2><i data-lucide="gavel"></i> Licitações</h2>
            </div>

            <nav class="sidebar-nav">
    <div class="nav-section">
        <div class="nav-section-title">Visão Geral</div>
        <button class="nav-item <?php echo $secao_ativa === 'dashboard' ? 'active' : ''; ?>" onclick="showSection('dashboard')">
            <i data-lucide="bar-chart-3"></i> <span>Dashboard</span>
        </button>
    </div>

    <div class="nav-section">
        <div class="nav-section-title">Gerenciar</div>
        <button class="nav-item <?php echo $secao_ativa === 'lista-licitacoes' ? 'active' : ''; ?>" onclick="showSection('lista-licitacoes')">
            <i data-lucide="list"></i> <span>Lista de Licitações</span>
        </button>
        <?php if (isVisitante()): ?>
        <div style="margin: 10px 15px; padding: 8px; background: #fff3cd; border-radius: 6px; border-left: 3px solid #f39c12;">
            <small style="color: #856404; font-size: 11px; font-weight: 600;">
                <i data-lucide="eye" style="width: 12px; height: 12px;"></i> MODO VISITANTE<br>
                Somente visualização e exportação
            </small>
        </div>
        <?php endif; ?>
    </div>

    <?php if (temPermissao('licitacao_relatorios')): ?>
    <div class="nav-section">
        <div class="nav-section-title">Relatórios</div>
        <button class="nav-item <?php echo $secao_ativa === 'relatorios' ? 'active' : ''; ?>" onclick="showSection('relatorios')">
            <i data-lucide="file-text"></i> <span>Relatórios</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Navegação Geral -->
    <div class="nav-section">
        <div class="nav-section-title">Sistema</div>
        <a href="selecao_modulos.php" class="nav-item">
            <i data-lucide="home"></i>
            <span>Menu Principal</span>
        </a>
        <a href="dashboard.php" class="nav-item">
            <i data-lucide="calendar-check"></i>
            <span>Planejamento</span>
        </a>
        <a href="qualificacao_dashboard.php" class="nav-item">
            <i data-lucide="award"></i>
            <span>Qualificações</span>
        </a>
    </div>
</nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['usuario_nome'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></h4>
                        <p><?php echo htmlspecialchars($_SESSION['usuario_email']); ?></p>
                        <small style="color: #3498db; font-weight: 600;">
                            <?php echo getNomeNivel($_SESSION['usuario_nivel'] ?? 3); ?> - <?php echo htmlspecialchars($_SESSION['usuario_departamento'] ?? ''); ?>
                        </small>
                        <?php if (isVisitante()): ?>
                        <small style="color: #f39c12; font-weight: 600; display: block; margin-top: 4px;">
                            <i data-lucide="eye" style="width: 12px; height: 12px;"></i> Modo Somente Leitura
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="perfil_usuario.php" class="logout-btn" style="text-decoration: none; margin-bottom: 10px; background: #27ae60 !important;">
                    <i data-lucide="user"></i> <span>Meu Perfil</span>
                </a>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i data-lucide="log-out"></i> <span>Sair</span>
                </button>
            </div>
        </div>

        <main class="main-content" id="mainContent">
            <?php echo getMensagem(); ?>

            <div id="dashboard" class="content-section <?php echo $secao_ativa === 'dashboard' ? 'active' : ''; ?>">
                <div class="dashboard-header">
                    <h1><i data-lucide="bar-chart-3"></i> Painel de Licitações</h1>
                    <p>Visão geral do processo licitatório e indicadores de desempenho</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-number"><?php echo number_format($stats['total_licitacoes'] ?? 0); ?></div>
                        <div class="stat-label">Total de Licitações</div>
                    </div>
                    <div class="stat-card andamento">
                        <div class="stat-number"><?php echo $stats['em_andamento'] ?? 0; ?></div>
                        <div class="stat-label">Em Andamento</div>
                    </div>
                    <div class="stat-card homologadas">
                        <div class="stat-number"><?php echo $stats['homologadas'] ?? 0; ?></div>
                        <div class="stat-label">Homologadas</div>
                    </div>
                    <div class="stat-card fracassadas">
                        <div class="stat-number"><?php echo $stats['fracassadas'] ?? 0; ?></div>
                        <div class="stat-label">Fracassadas</div>
                    </div>
                    <div class="stat-card valor">
                        <div class="stat-number"><?php echo abreviarValor($stats['valor_homologado'] ?? 0); ?></div>
                        <div class="stat-label">Valor Homologado</div>
                    </div>
                </div>

                <div class="charts-grid">
    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="pie-chart"></i> Licitações por Modalidade</h3>
        <div class="chart-container">
            <canvas id="chartModalidade"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="users"></i> Licitações por Pregoeiro</h3>
        <div class="chart-container">
            <canvas id="chartPregoeiro"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="trending-up"></i> Evolução Mensal</h3>
        <div class="chart-container">
            <canvas id="chartMensal"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title"><i data-lucide="activity"></i> Status das Licitações</h3>
        <div class="chart-container">
            <canvas id="chartStatus"></canvas>
        </div>
    </div>
</div>
            </div>

            <div id="lista-licitacoes" class="content-section <?php echo $secao_ativa === 'lista-licitacoes' ? 'active' : ''; ?>">
    <div class="dashboard-header">
        <h1><i data-lucide="list"></i> Lista de Licitações</h1>
        <p>Visualize e gerencie todas as licitações cadastradas</p>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h3 class="table-title">Todas as Licitações</h3>
            
            
            <div class="table-filters">
                <?php if (temPermissao('licitacao_criar')): ?>
                <button onclick="abrirModalCriarLicitacao()" class="btn-primary" style="margin-right: 10px;">
                    <i data-lucide="plus-circle"></i> Nova Licitação
                </button>
                <?php endif; ?>
                <?php if (temPermissao('licitacao_exportar')): ?>
                <button onclick="exportarLicitacoes()" class="btn-primary">
                    <i data-lucide="download"></i> Exportar
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtros e Busca -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <form id="formFiltrosLicitacao" method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Buscar</label>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($filtro_busca); ?>" 
                           placeholder="NUP, objeto, pregoeiro ou nº contratação..." 
                           style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Situação</label>
                    <select name="situacao_filtro" style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px;">
                        <option value="">Todas as Situações</option>
                        <option value="EM_ANDAMENTO" <?php echo $filtro_situacao === 'EM_ANDAMENTO' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="HOMOLOGADO" <?php echo $filtro_situacao === 'HOMOLOGADO' ? 'selected' : ''; ?>>Homologadas</option>
                        <option value="FRACASSADO" <?php echo $filtro_situacao === 'FRACASSADO' ? 'selected' : ''; ?>>Fracassadas</option>
                        <option value="REVOGADO" <?php echo $filtro_situacao === 'REVOGADO' ? 'selected' : ''; ?>>Revogadas</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Ano de Abertura</label>
                    <select name="ano_filtro" style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px;">
                        <option value="">Todos os Anos</option>
                        <?php foreach ($anos_disponiveis as $ano): ?>
                            <option value="<?php echo $ano; ?>" <?php echo $filtro_ano == $ano ? 'selected' : ''; ?>>
                                <?php echo $ano; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary" style="padding: 8px 16px;">
                        <i data-lucide="search"></i> Filtrar
                    </button>
                    <a href="licitacao_dashboard.php" class="btn-secondary" style="padding: 8px 16px; text-decoration: none;">
                        <i data-lucide="x"></i> Limpar
                    </a>
                </div>
                
                <!-- Campo oculto para preservar o valor de por_pagina -->
                <input type="hidden" name="por_pagina" value="<?php echo $licitacoes_por_pagina; ?>">
            </form>
        </div>

        <div id="resultadosLicitacoes">
        <?php if (empty($licitacoes_recentes)): ?>
            <div style="text-align: center; padding: 60px; color: #7f8c8d;">
                <i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: 20px;"></i>
                <h3 style="margin: 0 0 10px 0;">Nenhuma licitação encontrada</h3>
                <p style="margin: 0;">Comece criando sua primeira licitação.</p>
                <?php if (temPermissao('licitacao_criar')): ?>
                <button onclick="abrirModalCriarLicitacao()" class="btn-primary" style="margin-top: 20px;">
                    <i data-lucide="plus-circle"></i> Criar Primeira Licitação
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table>
<thead>
<tr>
<th>NUP</th>
<th>Número da contratação</th>
<th>Modalidade</th>
<th>Objeto</th>
<th>Valor Estimado</th>
<th>Situação</th>
<th>Pregoeiro</th>
<th>Data Abertura</th>
<th>Andamentos</th>
<th>Ações</th>
</tr>
</thead>
<tbody>
<?php 
$contador_linha = 0;
foreach ($licitacoes_recentes as $licitacao): 
    $contador_linha++;
?>
<tr>
<td>
<strong><?php echo htmlspecialchars($licitacao['nup']); ?></strong>
</td>
<td><?php echo htmlspecialchars($licitacao['numero_contratacao_final'] ?? $licitacao['numero_contratacao'] ?? 'N/A'); ?></td>
 
                        <td><span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($licitacao['modalidade']); ?></span></td>
<td title="<?php echo htmlspecialchars($licitacao['objeto'] ?? ''); ?>">
<?php 
                            $objeto = $licitacao['objeto'] ?? '';
                            echo htmlspecialchars(strlen($objeto) > 80 ? substr($objeto, 0, 80) . '...' : $objeto); 
                            ?>
</td>
<td style="font-weight: 600; color: #27ae60;"><?php echo formatarMoeda($licitacao['valor_estimado'] ?? 0); ?></td>
<td>
<span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $licitacao['situacao'])); ?>">
<?php echo str_replace('_', ' ', $licitacao['situacao']); ?>
</span>
</td>
<td><?php echo htmlspecialchars($licitacao['pregoeiro'] ?: 'Não Definido'); ?></td>
<td><?php echo $licitacao['data_abertura'] ? formatarData($licitacao['data_abertura']) : '-'; ?></td>
<td style="text-align: center;">
    <?php if ($licitacao['total_andamentos'] > 0): ?>
        <span style="background: #e8f5e8; color: #2e7d32; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
            <?php echo $licitacao['total_andamentos']; ?>
        </span>
    <?php else: ?>
        <span style="color: #bbb; font-size: 12px;">-</span>
    <?php endif; ?>
</td>
<td>
<div style="display: flex; gap: 5px; flex-wrap: wrap;">
<!-- Botão Ver Detalhes (sempre visível) -->
<button onclick="verDetalhes(<?php echo $licitacao['id']; ?>)" title="Ver Detalhes" style="background: #6c757d; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="eye" style="width: 14px; height: 14px;"></i>
</button>

<?php if (temPermissao('licitacao_editar')): ?>
<button onclick="editarLicitacao(<?php echo $licitacao['id']; ?>)" title="Editar" style="background: #f39c12; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="edit" style="width: 14px; height: 14px;"></i>
</button>
<?php if (temPermissao('licitacao_excluir')): ?>
<button onclick="excluirLicitacao(<?php echo $licitacao['id']; ?>, '<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Excluir" style="background: #e74c3c; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
</button>
<?php endif; ?>
<button onclick="abrirModalImportarAndamentos('<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Importar Andamentos" style="background: #3498db; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="upload" style="width: 14px; height: 14px;"></i>
</button>
<button onclick="consultarAndamentos('<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Ver Andamentos" style="background: #27ae60; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="clock" style="width: 14px; height: 14px;"></i>
</button>
<?php else: ?>
<button onclick="consultarAndamentos('<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Ver Andamentos" style="background: #27ae60; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="clock" style="width: 14px; height: 14px;"></i>
</button>
<span style="color: #7f8c8d; font-size: 12px; font-style: italic;">Somente leitura</span>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

            <!-- Informações de Paginação -->
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="color: #7f8c8d; font-size: 14px;">
                        <?php 
                        $inicio = ($pagina_atual - 1) * $licitacoes_por_pagina + 1;
                        $fim = min($pagina_atual * $licitacoes_por_pagina, $total_licitacoes);
                        ?>
                        Mostrando <?php echo $inicio; ?> a <?php echo $fim; ?> de <?php echo $total_licitacoes; ?> licitações<br>
                        Valor total estimado (página atual): <?php echo formatarMoeda(array_sum(array_column($licitacoes_recentes, 'valor_estimado'))); ?>
                    </div>
                    
                    <!-- Seletor de itens por página -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="font-size: 14px; color: #495057; font-weight: 600;">Itens por página:</label>
                        <select onchange="alterarItensPorPagina(this.value)" style="padding: 6px 8px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px;">
                            <option value="10" <?php echo $licitacoes_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $licitacoes_por_pagina == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $licitacoes_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $licitacoes_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>
                
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php
                    // Construir URL base preservando filtros e seção ativa
                    $url_params = [];
                    if (!empty($filtro_busca)) $url_params['busca'] = $filtro_busca;
                    if (!empty($filtro_situacao)) $url_params['situacao_filtro'] = $filtro_situacao;
                    if (!empty($filtro_ano)) $url_params['ano_filtro'] = $filtro_ano;
                    if ($licitacoes_por_pagina != 10) $url_params['por_pagina'] = $licitacoes_por_pagina;
                    $url_params['secao'] = $secao_ativa; // Manter seção ativa na paginação
                    $url_base = 'licitacao_dashboard.php?' . http_build_query($url_params);
                    $url_base .= empty($url_params) ? '?' : '&';
                    ?>
                    
                    <!-- Primeira página -->
                    <?php if ($pagina_atual > 1): ?>
                        <a href="<?php echo $url_base; ?>pagina=1" class="page-link">
                            <i data-lucide="chevrons-left"></i>
                        </a>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_atual - 1; ?>" class="page-link">
                            <i data-lucide="chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Páginas numeradas -->
                    <?php
                    $inicio_pag = max(1, $pagina_atual - 2);
                    $fim_pag = min($total_paginas, $pagina_atual + 2);
                    
                    for ($i = $inicio_pag; $i <= $fim_pag; $i++):
                    ?>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $i; ?>" 
                           class="page-link <?php echo $i == $pagina_atual ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Última página -->
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_atual + 1; ?>" class="page-link">
                            <i data-lucide="chevron-right"></i>
                        </a>
                        <a href="<?php echo $url_base; ?>pagina=<?php echo $total_paginas; ?>" class="page-link">
                            <i data-lucide="chevrons-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div> <!-- fim resultadosLicitacoes -->
    </div>
</div>

<div id="modalCriarLicitacao" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="plus-circle"></i> Criar Nova Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalCriarLicitacao')">&times;</span>
        </div>
        <div class="modal-body">
            <!-- Sistema de Abas -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button type="button" class="tab-button active" onclick="mostrarAba('vinculacao-pca')">
                        <i data-lucide="link"></i> Vinculação PCA
                    </button>
                    <button type="button" class="tab-button" onclick="mostrarAba('informacoes-gerais')">
                        <i data-lucide="info"></i> Informações Gerais
                    </button>
                    <button type="button" class="tab-button" onclick="mostrarAba('prazos-datas')">
                        <i data-lucide="clock"></i> Prazos e Datas
                    </button>
                    <button type="button" class="tab-button" onclick="mostrarAba('valores-financeiro')">
                        <i data-lucide="wallet"></i> Valores e Financeiro
                    </button>
                    <button type="button" class="tab-button" onclick="mostrarAba('responsaveis')">
                        <i data-lucide="users"></i> Responsáveis
                    </button>
                </div>

                <form action="process.php" method="POST" id="formCriarLicitacao">
                    <input type="hidden" name="acao" value="criar_licitacao">
                    <?php echo getCSRFInput(); ?>

                    <!-- Aba 1: Vinculação PCA -->
                    <div id="aba-vinculacao-pca" class="tab-content active">
                        <h4 style="margin: 0 0 15px 0; color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 8px;">
                            <i data-lucide="link"></i> Vinculação com PCA
                        </h4>
                        <div class="form-grid">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Número da Contratação *</label>
                                <div class="search-container" style="position: relative;">
                                    <input
                                        type="text"
                                        name="numero_contratacao"
                                        id="input_contratacao"
                                        required
                                        placeholder="Digite o número da contratação..."
                                        autocomplete="off"
                                        class="search-input"
                                        oninput="pesquisarContratacaoInline(this.value)"
                                        onfocus="mostrarSugestoesInline()"
                                        onblur="ocultarSugestoesInline()"
                                    >
                                    <div id="sugestoes_contratacao" class="search-suggestions" style="display: none;">
                                    </div>
                                </div>

                                <input type="hidden" id="numero_dfd_selecionado" name="numero_dfd">
                                <input type="hidden" id="titulo_contratacao_selecionado" name="titulo_contratacao">

                                <small style="color: #6b7280; font-size: 12px; margin-top: 5px; display: block;">
                                    <i data-lucide="info" style="width: 12px; height: 12px;"></i>
                                    Digite o número da contratação ou parte do título para pesquisar
                                </small>
                            </div>

                            <div id="info_contratacao_selecionada" style="grid-column: 1 / -1; display: none; background: #e8f5e9; padding: 15px; border-radius: 8px; margin-top: 10px;">
                                <h5 style="margin: 0 0 10px 0; color: #388e3c;">
                                    <i data-lucide="check-circle"></i> Contratação Selecionada
                                </h5>
                                <div id="detalhes_contratacao"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Aba 2: Informações Gerais -->
                    <div id="aba-informacoes-gerais" class="tab-content">
                        <h4 style="margin: 0 0 15px 0; color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 8px;">
                            <i data-lucide="file"></i> Informações Básicas
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>NUP *</label>
                                <input type="text" name="nup" id="nup_criar" required placeholder="xxxxx.xxxxxx/xxxx-xx" maxlength="20">
                            </div>

                            <div class="form-group">
                                <label>Modalidade *</label>
                                <select name="modalidade" required>
                                    <option value="">Selecione a modalidade</option>
                                    <option value="DISPENSA">DISPENSA</option>
                                    <option value="PREGAO">PREGÃO</option>
                                    <option value="RDC">RDC</option>
                                    <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Tipo *</label>
                                <select name="tipo" required>
                                    <option value="">Selecione o tipo</option>
                                    <option value="TRADICIONAL">TRADICIONAL</option>
                                    <option value="COTACAO">COTAÇÃO</option>
                                    <option value="SRP">SRP</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Ano</label>
                                <input type="number" name="ano" value="<?php echo date('Y'); ?>" min="2020" max="2030">
                            </div>

                            <div class="form-group">
                                <label>Situação *</label>
                                <select name="situacao" required>
                                    <option value="">Selecione a situação</option>
                                    <option value="EM_ANDAMENTO" selected>EM ANDAMENTO</option>
                                    <option value="REVOGADO">REVOGADO</option>
                                    <option value="FRACASSADO">FRACASSADO</option>
                                    <option value="HOMOLOGADO">HOMOLOGADO</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Link (Documentos/Edital)</label>
                                <input type="url" name="link" placeholder="https://...">
                            </div>

                            <div class="form-group form-full">
                                <label>Objeto *</label>
                                <textarea name="objeto" id="objeto_textarea" required rows="4" placeholder="Descreva detalhadamente o objeto da licitação..."></textarea>
                            </div>
                        </div>
                    </div>


                    <!-- Aba 3: Prazos e Datas -->
                    <div id="aba-prazos-datas" class="tab-content">
                        <h4 style="margin: 0 0 15px 0; color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 8px;">
                            <i data-lucide="calendar"></i> Cronograma do Processo
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Data Entrada DIPLI</label>
                                <input type="date" name="data_entrada_dipli">
                                <small style="color: #6b7280; font-size: 12px;">Data de entrada do processo na DIPLI</small>
                            </div>

                            <div class="form-group">
                                <label>Data Abertura</label>
                                <input type="date" name="data_abertura">
                                <small style="color: #6b7280; font-size: 12px;">Data prevista para abertura das propostas</small>
                            </div>

                            <div class="form-group">
                                <label>Data Homologação</label>
                                <input type="date" name="data_homologacao" id="data_homologacao_criar">
                                <small style="color: #6b7280; font-size: 12px;">Data de homologação do resultado</small>
                            </div>

                            <div class="form-group">
                                <label>Data Publicação</label>
                                <input type="date" name="data_publicacao">
                                <small style="color: #6b7280; font-size: 12px;">Data de publicação do edital</small>
                            </div>
                        </div>
                    </div>

                    <!-- Aba 4: Valores e Financeiro -->
                    <div id="aba-valores-financeiro" class="tab-content">
                        <h4 style="margin: 0 0 15px 0; color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 8px;">
                            <i data-lucide="banknote"></i> Valores Financeiros
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Valor Estimado (R$) *</label>
                                <input type="text" name="valor_estimado" id="valor_estimado_criar" placeholder="0,00" required>
                                <small style="color: #6b7280; font-size: 12px;">Valor estimado para a contratação</small>
                            </div>

                            <div class="form-group">
                                <label>Valor Homologado (R$)</label>
                                <input type="text" name="valor_homologado" id="valor_homologado_criar" placeholder="0,00">
                                <small style="color: #6b7280; font-size: 12px;">Valor final homologado</small>
                            </div>

                            <div class="form-group">
                                <label>Economia (R$)</label>
                                <input type="text" name="economia" id="economia_criar" placeholder="0,00" readonly style="background: #f8f9fa;">
                                <small style="color: #6b7280; font-size: 12px;">Calculado automaticamente (Estimado - Homologado)</small>
                            </div>

                            <div class="form-group">
                                <label>Quantidade de Itens</label>
                                <input type="number" name="qtd_itens" min="1" placeholder="1">
                                <small style="color: #6b7280; font-size: 12px;">Número de itens da licitação</small>
                            </div>
                        </div>
                    </div>

                    <!-- Aba 5: Responsáveis -->
                    <div id="aba-responsaveis" class="tab-content">
                        <h4 style="margin: 0 0 15px 0; color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 8px;">
                            <i data-lucide="users"></i> Responsáveis pelo Processo
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Pregoeiro/Responsável</label>
                                <input type="text" name="pregoeiro" placeholder="Nome do pregoeiro">
                                <small style="color: #6b7280; font-size: 12px;">Pregoeiro responsável pela condução</small>
                            </div>

                            <div class="form-group">
                                <label>Responsável Instrução</label>
                                <input type="text" name="resp_instrucao" placeholder="Nome do responsável">
                                <small style="color: #6b7280; font-size: 12px;">Responsável pela instrução do processo</small>
                            </div>

                            <div class="form-group">
                                <label>Área Demandante</label>
                                <input type="text" name="area_demandante" id="area_demandante_criar" placeholder="Área que solicitou">
                                <small style="color: #6b7280; font-size: 12px;">Área que demandou a licitação</small>
                            </div>

                            <div class="form-group">
                                <label>Observações</label>
                                <textarea name="observacoes" rows="3" placeholder="Observações gerais sobre responsabilidades..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Botões de Ação -->
                    <div style="margin-top: 20px; display: flex; gap: 15px; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 2px solid #e9ecef;">
                        <div class="tab-navigation">
                            <button type="button" id="btn-anterior" onclick="abaAnterior()" class="btn-secondary" style="display: none;">
                                <i data-lucide="chevron-left"></i> Anterior
                            </button>
                            <button type="button" id="btn-proximo" onclick="proximaAba()" class="btn-primary">
                                Próximo <i data-lucide="chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" onclick="fecharModal('modalCriarLicitacao')" class="btn-secondary">
                                <i data-lucide="x"></i> Cancelar
                            </button>
                            <button type="reset" class="btn-secondary" onclick="resetarFormulario()">
                                <i data-lucide="refresh-cw"></i> Limpar
                            </button>
                            <button type="submit" class="btn-success" id="btn-criar" style="display: none;">
                                <i data-lucide="check"></i> Criar Licitação
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

            <div id="relatorios" class="content-section <?php echo $secao_ativa === 'relatorios' ? 'active' : ''; ?>">
    <div class="dashboard-header">
        <h1><i data-lucide="file-text"></i> Relatórios</h1>
        <p>Relatórios detalhados sobre o processo licitatório</p>
    </div>

    <div class="stats-grid">
        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('modalidade')">
            <h3 class="chart-title"><i data-lucide="pie-chart"></i> Relatório por Modalidade</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Análise detalhada das licitações por modalidade</p>
            <div style="text-align: center;">
                <i data-lucide="bar-chart-3" style="width: 64px; height: 64px; color: #e74c3c; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>

        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('pregoeiro')">
            <h3 class="chart-title"><i data-lucide="users"></i> Relatório por Pregoeiro</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Performance e distribuição por pregoeiro</p>
            <div style="text-align: center;">
                <i data-lucide="user-check" style="width: 64px; height: 64px; color: #3498db; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>

        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('prazos')">
            <h3 class="chart-title"><i data-lucide="clock"></i> Relatório de Prazos</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Análise de cumprimento de prazos</p>
            <div style="text-align: center;">
                <i data-lucide="calendar-check" style="width: 64px; height: 64px; color: #f39c12; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>

        <div class="chart-card" style="cursor: pointer;" onclick="gerarRelatorio('financeiro')">
            <h3 class="chart-title"><i data-lucide="trending-up"></i> Relatório Financeiro</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Valores estimados vs homologados</p>
            <div style="text-align: center;">
                <i data-lucide="dollar-sign" style="width: 64px; height: 64px; color: #27ae60; margin-bottom: 20px;"></i>
                <button class="btn-primary">Gerar Relatório</button>
            </div>
        </div>
    </div>
</div>

<div id="modalRelatorio" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="file-text"></i> <span id="tituloRelatorio">Configurar Relatório</span>
            </h3>
            <span class="close" onclick="fecharModal('modalRelatorio')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formRelatorio">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="tipo_relatorio" name="tipo">

                <div class="form-group">
                    <label>Período</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Inicial</label>
                            <input type="date" name="data_inicial" id="rel_data_inicial">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Final</label>
                            <input type="date" name="data_final" id="rel_data_final" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group" id="filtroModalidade" style="display: none;">
                    <label>Modalidade</label>
                    <select name="modalidade" id="rel_modalidade">
                        <option value="">Todas</option>
                        <option value="DISPENSA">Dispensa</option>
                        <option value="PREGAO">Pregão</option>
                        <option value="RDC">RDC</option>
                        <option value="INEXIBILIDADE">Inexibilidade</option>
                    </select>
                </div>

                <div class="form-group" id="filtroPregoeiro" style="display: none;">
                    <label>Pregoeiro</label>
                    <select name="pregoeiro" id="rel_pregoeiro">
                        <option value="">Todos</option>
                        <?php
                        // Buscar pregoeiros únicos
                        $pregoeiros = $pdo->query("SELECT DISTINCT pregoeiro FROM licitacoes WHERE pregoeiro IS NOT NULL AND pregoeiro != '' ORDER BY pregoeiro")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($pregoeiros as $preg): ?>
                            <option value="<?php echo htmlspecialchars($preg); ?>"><?php echo htmlspecialchars($preg); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="filtroSituacao">
                    <label>Situação</label>
                    <select name="situacao" id="rel_situacao">
                        <option value="">Todas</option>
                        <option value="EM_ANDAMENTO">Em Andamento</option>
                        <option value="HOMOLOGADO">Homologado</option>
                        <option value="FRACASSADO">Fracassado</option>
                        <option value="REVOGADO">Revogado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Formato de Saída</label>
                    <select name="formato" id="rel_formato" required>
                        <option value="html">Visualizar (HTML)</option>
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="incluir_graficos" id="rel_graficos" checked>
                        Incluir gráficos no relatório
                    </label>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalRelatorio')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="file-text"></i> Gerar Relatório
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

        </div>
    </div>

    <div id="modalDetalhes" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="file-text"></i> Detalhes da Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalDetalhes')">&times;</span>
        </div>
        <div class="modal-body" id="detalhesContent">
            </div>
    </div>
</div>

<div id="modalEdicao" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="edit"></i> Editar Licitação
            </h3>
            <span class="close" onclick="fecharModal('modalEdicao')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formEditarLicitacao">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="edit_id" name="id">
                <input type="hidden" name="acao" value="editar_licitacao">

                <div class="form-grid">
                    <div class="form-group">
                        <label>NUP *</label>
                        <input type="text" name="nup" id="edit_nup" required placeholder="xxxxx.xxxxxx/xxxx-xx" maxlength="20">
                    </div>

                    <div class="form-group">
                        <label>Data Entrada DIPLI</label>
                        <input type="date" name="data_entrada_dipli" id="edit_data_entrada_dipli">
                    </div>

                    <div class="form-group">
                        <label>Responsável Instrução</label>
                        <input type="text" name="resp_instrucao" id="edit_resp_instrucao">
                    </div>

                    <div class="form-group">
                        <label>Área Demandante</label>
                        <input type="text" name="area_demandante" id="edit_area_demandante">
                    </div>

                    <div class="form-group">
                        <label>Pregoeiro</label>
                        <input type="text" name="pregoeiro" id="edit_pregoeiro">
                    </div>

                    <div class="form-group">
                        <label>Modalidade *</label>
                        <select name="modalidade" id="edit_modalidade" required>
                            <option value="">Selecione</option>
                            <option value="DISPENSA">DISPENSA</option>
                            <option value="PREGAO">PREGÃO</option>
                            <option value="RDC">RDC</option>
                            <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="tipo" id="edit_tipo" required>
                            <option value="">Selecione</option>
                            <option value="TRADICIONAL">TRADICIONAL</option>
                            <option value="COTACAO">COTAÇÃO</option>
                            <option value="SRP">SRP</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Número da Contratação *</label>
                        <div class="search-container" style="position: relative;">
                            <input
                                type="text"
                                name="numero_contratacao"
                                id="edit_input_contratacao"
                                required
                                placeholder="Digite o número da contratação..."
                                autocomplete="off"
                                class="search-input"
                                oninput="pesquisarContratacaoInlineEdit(this.value)"
                                onfocus="mostrarSugestoesInlineEdit()"
                                onblur="ocultarSugestoesInlineEdit()"
                            >
                            <div id="edit_sugestoes_contratacao" class="search-suggestions" style="display: none;">
                                </div>
                        </div>

                        <input type="hidden" id="edit_numero_dfd_selecionado" name="numero_dfd">
                        <input type="hidden" id="edit_titulo_contratacao_selecionado" name="titulo_contratacao">

                        <small style="color: #6b7280; font-size: 12px;">
                            Digite o número da contratação ou parte do título para pesquisar
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Ano</label>
                        <input type="number" name="ano" id="edit_ano" value="<?php echo date('Y'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Valor Estimado (R$)</label>
                        <input type="text" name="valor_estimado" id="edit_valor_estimado" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Data Abertura</label>
                        <input type="date" name="data_abertura" id="edit_data_abertura">
                    </div>

                    <div class="form-group">
                        <label>Data Homologação</label>
                        <input type="date" name="data_homologacao" id="edit_data_homologacao">
                    </div>

                    <div class="form-group">
                        <label>Valor Homologado (R$)</label>
                        <input type="text" name="valor_homologado" id="edit_valor_homologado" placeholder="0,00">
                    </div>

                    <div class="form-group">
                        <label>Economia (R$)</label>
                        <input type="text" name="economia" id="edit_economia" placeholder="0,00" readonly style="background: #f8f9fa;">
                    </div>

                    <div class="form-group">
                        <label>Link</label>
                        <input type="url" name="link" id="edit_link" placeholder="https://...">
                    </div>

                    <div class="form-group">
                        <label>Situação *</label>
                        <select name="situacao" id="edit_situacao" required>
                            <option value="EM_ANDAMENTO">EM ANDAMENTO</option>
                            <option value="REVOGADO">REVOGADO</option>
                            <option value="FRACASSADO">FRACASSADO</option>
                            <option value="HOMOLOGADO">HOMOLOGADO</option>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label>Objeto *</label>
                        <textarea name="objeto" id="edit_objeto" required rows="3" placeholder="Descreva o objeto da licitação..."></textarea>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalEdicao')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i data-lucide="refresh-cw"></i> Restaurar Valores
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalExportar" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="download"></i> Exportar Dados
            </h3>
            <span class="close" onclick="fecharModal('modalExportar')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formExportar">
                <?php echo getCSRFInput(); ?>
                <div class="form-group">
                    <label>Formato de Exportação</label>
                    <select id="formato_export" name="formato" required>
                        <option value="csv">CSV (Excel)</option>
                        <option value="excel">Excel (XLS)</option>
                        <option value="json">JSON</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Filtrar por Situação</label>
                    <select id="situacao_export" name="situacao">
                        <option value="">Todas as Situações</option>
                        <option value="EM_ANDAMENTO">Em Andamento</option>
                        <option value="HOMOLOGADO">Homologadas</option>
                        <option value="FRACASSADO">Fracassadas</option>
                        <option value="REVOGADO">Revogadas</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Período de Criação</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Inicial</label>
                            <input type="date" id="data_inicio_export" name="data_inicio">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #6c757d;">Data Final</label>
                            <input type="date" id="data_fim_export" name="data_fim">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Campos para Exportar</label>
                    <div style="margin-bottom: 10px;">
                        <button type="button" onclick="selecionarTodosCampos(true)" class="btn-secondary" style="margin-right: 10px; padding: 5px 10px; font-size: 12px;">
                            Selecionar Todos
                        </button>
                        <button type="button" onclick="selecionarTodosCampos(false)" class="btn-secondary" style="padding: 5px 10px; font-size: 12px;">
                            Desmarcar Todos
                        </button>
                    </div>
                    <div style="margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="nup" checked> NUP
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="numero_contratacao_final" checked> Número da Contratação
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="modalidade" checked> Modalidade
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="tipo" checked> Tipo
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="objeto" checked> Objeto
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="valor_estimado" checked> Valor Estimado
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="situacao" checked> Situação
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="pregoeiro" checked> Pregoeiro
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="data_abertura" checked> Data Abertura
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="data_homologacao"> Data Homologação
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="valor_homologado"> Valor Homologado
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="economia"> Economia
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="area_demandante"> Área Demandante
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="resp_instrucao"> Resp. Instrução
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="usuario_nome"> Criado por
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <input type="checkbox" name="campos[]" value="criado_em"> Data de Criação
                        </label>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalExportar')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="download"></i> Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Importar Andamentos -->
<div id="modalImportarAndamentos" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="upload"></i> Importar Andamentos de Processo
            </h3>
            <span class="close" onclick="fecharModal('modalImportarAndamentos')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formImportarAndamentos" enctype="multipart/form-data">
                <?php echo getCSRFInput(); ?>
                
                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
                    <h4 style="margin: 0 0 10px 0; color: #1976d2;">
                        <i data-lucide="info" style="width: 16px; height: 16px;"></i> NUP Selecionado
                    </h4>
                    <p style="margin: 0; font-weight: 600; color: #1976d2;" id="nupSelecionado">-</p>
                </div>
                
                <div class="form-group">
                    <label>Arquivo JSON *</label>
                    <input type="file" 
                           name="arquivo_json" 
                           id="arquivo_json" 
                           accept=".json" 
                           required 
                           style="width: 100%; padding: 10px; border: 2px dashed #dee2e6; border-radius: 8px; background: #f8f9fa;">
                    <small style="color: #6c757d; font-size: 12px; display: block; margin-top: 5px;">
                        Selecione um arquivo .json com os dados de andamentos do processo.
                    </small>
                </div>
                
                <details style="background: #fff3cd; padding: 10px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #f39c12;">
                    <summary style="cursor: pointer; font-weight: 600; color: #856404; padding: 5px 0;">
                        <i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i> Estrutura Esperada do JSON (clique para expandir)
                    </summary>
                    <pre style="background: #f8f9fa; padding: 8px; border-radius: 4px; font-size: 11px; overflow-x: auto; margin-top: 10px;">{
  "nup": "12345.123456/2024-12",
  "processo_id": "SEI123456789",
  "timestamp": "2024-12-27 10:30:00",
  "total_andamentos": 3,
  "andamentos": [
    {"unidade": "DIPLI", "dias": 15, "descricao": "Análise técnica"},
    {"unidade": "DIPLAN", "dias": 8, "descricao": "Revisão planejamento"}
  ]
}</pre>
                </details>
                
                <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="fecharModal('modalImportarAndamentos')" class="btn-secondary">
                        <i data-lucide="x"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-lucide="upload"></i> Importar Andamentos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Visualizar Andamentos - REDESENHADO -->
<div id="modalVisualizarAndamentos" class="modal andamentos-modal" style="display: none;">
    <div class="andamentos-modal-content">
        <!-- Header Expandido -->
        <div class="andamentos-header">
            <div class="header-left">
                <div class="processo-info">
                    <div class="processo-titulo">
                        <i data-lucide="activity"></i>
                        <h2>Timeline do Processo</h2>
                    </div>
                    <div class="processo-detalhes">
                        <span class="nup-badge" id="nup-display">NUP: Carregando...</span>
                        <span class="status-info" id="status-display">Status: Carregando...</span>
                    </div>
                </div>
            </div>
            <div class="header-actions">
                <button class="action-btn export-btn" onclick="gerarRelatorioAndamentos()" title="Exportar Timeline">
                    <i data-lucide="download"></i>
                    <span>Exportar PDF</span>
                </button>
                <button class="action-btn refresh-btn" onclick="recarregarAndamentos()" title="Atualizar Dados">
                    <i data-lucide="refresh-ccw"></i>
                    <span>Atualizar</span>
                </button>
                <button class="action-btn close-btn" onclick="fecharModal('modalVisualizarAndamentos')" title="Fechar">
                    <i data-lucide="x"></i>
                </button>
            </div>
        </div>

        <!-- Corpo Principal com Layout em Duas Colunas -->
        <div class="andamentos-body">
            <!-- Coluna Principal - Timeline -->
            <div class="timeline-section">
                <div class="timeline-header">
                    <h3><i data-lucide="clock"></i> Histórico de Andamentos</h3>
                    <div class="timeline-controls">
                        <button class="filter-btn" onclick="toggleFiltrosAndamentos()">
                            <i data-lucide="filter"></i> Filtros
                        </button>
                    </div>
                </div>
                
                <!-- Filtros Expansíveis -->
                <div class="timeline-filters" id="filtrosAndamentos" style="display: none;">
                    <div class="filter-group">
                        <label>Período:</label>
                        <select id="filtroPerioodo">
                            <option value="">Todos</option>
                            <option value="30">Últimos 30 dias</option>
                            <option value="60">Últimos 60 dias</option>
                            <option value="90">Últimos 90 dias</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Unidade:</label>
                        <select id="filtroUnidade">
                            <option value="">Todas</option>
                            <option value="DIPLI">DIPLI</option>
                            <option value="DIPLAN">DIPLAN</option>
                            <option value="CGLIC">CGLIC</option>
                        </select>
                    </div>
                </div>

                <!-- Container da Timeline Melhorada -->
                <div class="timeline-container-improved" id="conteudoAndamentos">
                    <div class="loading-timeline">
                        <div class="loading-spinner">
                            <i data-lucide="loader-2"></i>
                        </div>
                        <p>Carregando timeline do processo...</p>
                    </div>
                </div>
            </div>

            <!-- Coluna Lateral - Informações e Estatísticas -->
            <div class="info-sidebar">
                <div class="info-card">
                    <h4><i data-lucide="bar-chart-3"></i> Estatísticas</h4>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value" id="totalAndamentos">-</span>
                            <span class="stat-label">Total de Andamentos</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value" id="tempoMedio">-</span>
                            <span class="stat-label">Tempo Médio</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value" id="unidadesEnvolvidas">-</span>
                            <span class="stat-label">Unidades</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value" id="ultimaAtualizacao">-</span>
                            <span class="stat-label">Última Atualização</span>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <h4><i data-lucide="info"></i> Informações do Processo</h4>
                    <div class="process-details">
                        <div class="detail-row">
                            <span class="detail-label">Modalidade:</span>
                            <span class="detail-value" id="modalidadeInfo">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Pregoeiro:</span>
                            <span class="detail-value" id="pregoeiroInfo">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Valor Estimado:</span>
                            <span class="detail-value" id="valorInfo">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Data de Abertura:</span>
                            <span class="detail-value" id="dataAberturaInfo">-</span>
                        </div>
                    </div>
                </div>

                <div class="info-card actions-card">
                    <h4><i data-lucide="settings"></i> Ações Rápidas</h4>
                    <div class="quick-actions">
                        <button class="quick-action-btn" onclick="verDetalhesCompletos()">
                            <i data-lucide="eye"></i> Ver Detalhes Completos
                        </button>
                        <button class="quick-action-btn" onclick="editarProcesso()">
                            <i data-lucide="edit"></i> Editar Processo
                        </button>
                        <button class="quick-action-btn" onclick="adicionarAndamento()">
                            <i data-lucide="plus"></i> Adicionar Andamento
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    </div>
        </div>
        </main>
    </div>


    <script>
        
        // Dados passados do PHP para JavaScript
        window.dadosModalidade = <?php echo json_encode($dados_modalidade); ?>;
        window.dadosPregoeiro = <?php echo json_encode($dados_pregoeiro); ?>;
        window.dadosMensal = <?php echo json_encode($dados_mensal); ?>;
        window.stats = <?php echo json_encode($stats); ?>;
        window.dadosContratacoes = <?php echo json_encode($contratacoes_pca); ?>;
        
        // Compatibilidade com arquivo JS externo
        window.contratacoesPCA = window.dadosContratacoes;
        
        /**
         * Alterar quantidade de itens por página
         */
        function alterarItensPorPagina(novoValor) {
            const url = new URL(window.location);
            url.searchParams.set('por_pagina', novoValor);
            url.searchParams.set('pagina', '1'); // Voltar para a primeira página
            window.location.href = url.toString();
        }

        /**
         * Funções para o novo modal de andamentos
         */
        function toggleFiltrosAndamentos() {
            const filtros = document.getElementById('filtrosAndamentos');
            if (filtros) {
                filtros.style.display = filtros.style.display === 'none' ? 'flex' : 'none';
            }
        }

        function recarregarAndamentos() {
            const nupElement = document.getElementById('nup-display');
            if (nupElement) {
                const nup = nupElement.textContent.replace('NUP: ', '');
                if (nup && nup !== 'Carregando...') {
                    consultarAndamentos(nup);
                }
            }
        }

        function verDetalhesCompletos() {
            // Função placeholder - pode ser implementada para mostrar detalhes completos
            console.log('Ver detalhes completos - funcionalidade a ser implementada');
        }

        function editarProcesso() {
            // Função placeholder - pode ser implementada para editar o processo
            console.log('Editar processo - funcionalidade a ser implementada');
        }

        function adicionarAndamento() {
            // Função placeholder - pode ser implementada para adicionar novo andamento
            console.log('Adicionar andamento - funcionalidade a ser implementada');
        }
    </script>
    <script src="assets/dark-mode.js"></script>
    <script src="assets/licitacao-dashboard.js"></script>
    <script src="assets/notifications.js"></script>
</body>
</html>