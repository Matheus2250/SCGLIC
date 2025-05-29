<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// Configuração de paginação
$limite = intval($_GET['limite'] ?? 20);
$pagina = intval($_GET['pagina'] ?? 1);
$offset = ($pagina - 1) * $limite;

// Buscar áreas para o filtro (agrupadas)
$areas_sql = "SELECT DISTINCT area_requisitante FROM pca_dados WHERE area_requisitante IS NOT NULL AND area_requisitante != '' ORDER BY area_requisitante";
$areas_result = $pdo->query($areas_sql);
$areas_agrupadas = [];

while ($row = $areas_result->fetch()) {
    $area_agrupada = agruparArea($row['area_requisitante']);
    if (!in_array($area_agrupada, $areas_agrupadas)) {
        $areas_agrupadas[] = $area_agrupada;
    }
}
sort($areas_agrupadas);

// Buscar dados com filtros
$where = [];
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

$whereClause = '';
if ($where) {
    $whereClause = 'AND ' . implode(' AND ', $where);
}

// Query para contar total de registros
$sqlCount = "SELECT COUNT(DISTINCT numero_dfd) as total FROM pca_dados p WHERE numero_dfd IS NOT NULL AND numero_dfd != '' $whereClause";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRegistros = $stmtCount->fetch()['total'];
$totalPaginas = ceil($totalRegistros / $limite);

// Query principal agrupando por número de contratação
// Query principal agrupando por número de contratação
$sql = "SELECT 
        MAX(p.numero_contratacao) as numero_contratacao,
        p.numero_dfd,
        MAX(p.status_contratacao) as status_contratacao,
        MAX(p.titulo_contratacao) as titulo_contratacao,
        MAX(p.categoria_contratacao) as categoria_contratacao,
        MAX(p.uasg_atual) as uasg_atual,
        MAX(p.valor_total_contratacao) as valor_total_contratacao,
        MAX(p.area_requisitante) as area_requisitante,
        MAX(p.prioridade) as prioridade,
        MAX(p.situacao_execucao) as situacao_execucao,
        MAX(p.data_inicio_processo) as data_inicio_processo,
        MAX(p.data_conclusao_processo) as data_conclusao_processo,
        DATEDIFF(MAX(p.data_conclusao_processo), CURDATE()) as dias_ate_conclusao,
        COUNT(*) as qtd_itens_pca,
        GROUP_CONCAT(p.id) as ids,
        MAX(p.id) as id,
        MAX((SELECT COUNT(*) FROM licitacoes WHERE pca_dados_id IN (
            SELECT id FROM pca_dados WHERE numero_dfd = p.numero_dfd
        ))) as tem_licitacao
        FROM pca_dados p 
        WHERE p.numero_dfd IS NOT NULL AND p.numero_dfd != ''
        $whereClause 
        GROUP BY p.numero_dfd
        ORDER BY p.numero_dfd DESC
        LIMIT " . intval($limite) . " OFFSET " . intval($offset);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dados = $stmt->fetchAll();

// Buscar listas únicas para os filtros
$situacao_lista = $pdo->query("SELECT DISTINCT situacao_execucao FROM pca_dados WHERE situacao_execucao IS NOT NULL AND situacao_execucao != '' ORDER BY situacao_execucao")->fetchAll(PDO::FETCH_COLUMN);

// Adicionar "Não iniciado" se não estiver na lista
if (!in_array('Não iniciado', $situacao_lista)) {
    array_unshift($situacao_lista, 'Não iniciado');
}

$categoria_lista = $pdo->query("SELECT DISTINCT categoria_contratacao FROM pca_dados WHERE categoria_contratacao IS NOT NULL ORDER BY categoria_contratacao")->fetchAll(PDO::FETCH_COLUMN);
$situacao_lista = $pdo->query("SELECT DISTINCT situacao_execucao FROM pca_dados WHERE situacao_execucao IS NOT NULL ORDER BY situacao_execucao")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<style>
.lucide {
    width: 16px;
    height: 16px;
    vertical-align: middle;
    color: currentColor;
}

.lucide-lg {
    width: 20px;
    height: 20px;
}

.lucide-xl {
    width: 24px;
    height: 24px;
}

.card-icon .lucide {
    width: 32px;
    height: 32px;
}
</style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1>Sistema de Informações CGLIC <i data-lucide="library-big" style="width: 32px; height: 32px;"></i></h1>
            <div class="nav-menu">
                <span>Olá, <?php echo $_SESSION['usuario_nome']; ?></span>
                <a href="logout.php">Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php echo getMensagem(); ?>

        <?php

// Buscar estatísticas para os cards
$stats_sql = "SELECT 
    COUNT(DISTINCT p.numero_dfd) as total_dfds,
    COUNT(DISTINCT p.numero_contratacao) as total_contratacoes,
    SUM(DISTINCT p.valor_total_contratacao) as valor_total,
    COUNT(DISTINCT CASE WHEN l.situacao = 'HOMOLOGADO' THEN p.numero_contratacao END) as homologadas,
    COUNT(DISTINCT CASE WHEN p.data_inicio_processo < CURDATE() AND p.situacao_execucao = 'Não iniciado' THEN p.numero_contratacao END) as atrasadas_inicio,
    COUNT(DISTINCT CASE WHEN p.data_conclusao_processo < CURDATE() AND p.situacao_execucao != 'Concluído' THEN p.numero_contratacao END) as atrasadas_conclusao,
    COUNT(DISTINCT CASE WHEN p.data_conclusao_processo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN p.numero_contratacao END) as vencendo_30_dias,
    COUNT(DISTINCT CASE WHEN l.situacao IN ('EM_ANDAMENTO') THEN p.numero_contratacao END) as em_andamento
    FROM pca_dados p
    LEFT JOIN licitacoes l ON l.pca_dados_id = p.id";

$stats = $pdo->query($stats_sql)->fetch();
$total_atrasadas = $stats['atrasadas_inicio'] + $stats['atrasadas_conclusao'];
?>

<!-- Cards de Estatísticas -->
<div class="cards-container">
    <div class="card card-info">
        <div class="card-icon"><i data-lucide="bar-chart"></i></div>
        <div class="card-content">
            <h3><?php echo number_format($stats['total_dfds']); ?></h3>
            <p>Total de DFDs</p>
        </div>
    </div>
    
    <div class="card card-primary">
        <div class="card-icon"><i data-lucide="clipboard-list"></i></div>
        <div class="card-content">
            <h3><?php echo number_format($stats['total_contratacoes']); ?></h3>
            <p>Total Contratações</p>
        </div>
    </div>
    
    <div class="card card-money">
        <div class="card-icon"><i data-lucide="dollar-sign"></i></div>
        <div class="card-content">
            <h3><?php echo abreviarValor($stats['valor_total']); ?></h3>
            <p>Valor Total (R$)</p>
        </div>
    </div>
    
    <div class="card card-success">
        <div class="card-icon"><i data-lucide="check-circle"></i></div>
        <div class="card-content">
            <h3><?php echo $stats['homologadas']; ?></h3>
            <p>Homologadas</p>
        </div>
    </div>
    
    <div class="card card-info">
        <div class="card-icon"><i data-lucide="settings"></i></div>
        <div class="card-content">
            <h3><?php echo $stats['em_andamento']; ?></h3>
            <p>Em Andamento</p>
        </div>
    </div>
</div>
        
<!-- Botão de Alertas -->
        <div style="margin-bottom: 30px;">
            <a href="contratacoes_atrasadas.php" style="display: flex; align-items: center; gap: 15px; background:rgb(245, 162, 39); color: white; text-decoration: none; padding: 20px 25px; border-radius: 10px; box-shadow: 0 3px 10px rgba(139, 92, 246, 0.3); transition: all 0.3s ease;">
                <span style="font-size: 28px; background: rgba(255, 255, 255, 0.2); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 8px;"><i data-lucide="alert-triangle" style="width: 32px; height: 32px;"></i></span>
                <div style="flex: 1;">
                    <strong style="display: block; font-size: 18px; margin-bottom: 2px;">Contratações Atrasadas</strong>
                    <small style="font-size: 14px; opacity: 0.9;">Visualizar pendências e atrasos</small>
                </div>
                <span style="font-size: 24px; opacity: 0.8;">→</span>
            </a>
        </div>

        <!-- Upload de Arquivo -->
        <div class="upload-area">
            <h3>Importar Planilha PCA</h3>
            <form action="process.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="importar_pca">
                <div class="upload-box">
                    <input type="file" name="arquivo_pca" accept=".csv,.xls,.xlsx" required>
                    <p class="text-muted">Selecione um arquivo CSV, XLS ou XLSX</p>
                    <button type="submit" class="btn btn-sucesso mt-20">Importar Arquivo</button>
                </div>
            </form>
        </div>
        
        <!-- Filtros -->
        <div class="filtros">
            <h3>Filtros</h3>
            <form method="GET" class="filtros-form">
                <input type="hidden" name="limite" value="<?php echo $limite; ?>">
                <div>
                    <input type="text" name="numero_contratacao" placeholder="Número do DFD"
                           value="<?php echo $_GET['numero_contratacao'] ?? ''; ?>">
                </div>
                <div>
                    <select name="situacao_execucao">
    <option value="">Todas as Situações</option>
    <?php foreach ($situacao_lista as $situacao): ?>
        <option value="<?php echo htmlspecialchars($situacao); ?>" 
                <?php echo ($_GET['situacao_execucao'] ?? '') == $situacao ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($situacao); ?>
        </option>
    <?php endforeach; ?>
</select>
                </div>
                <div>
                    <select name="categoria">
                        <option value="">Todas as Categorias</option>
                        <?php foreach ($categoria_lista as $categoria): ?>
                            <option value="<?php echo $categoria; ?>" 
                                    <?php echo ($_GET['categoria'] ?? '') == $categoria ? 'selected' : ''; ?>>
                                <?php echo $categoria; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <select name="area_requisitante" onchange="this.form.submit()">
    <option value="">Todas as áreas</option>
    <?php foreach ($areas_agrupadas as $area): ?>
        <option value="<?php echo htmlspecialchars($area); ?>" 
                <?php echo ($_GET['area_requisitante'] ?? '') == $area ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($area); ?>
        </option>
    <?php endforeach; ?>
</select>
                </div>
                <div>
                    <button type="submit" class="btn">Filtrar</button>
                    <a href="dashboard.php" class="btn btn-secundario">Limpar</a>
                </div>
            </form>
        </div>
        
        <!-- Tabela de Dados -->
        <div class="tabela-container">
            <h3>Dados do PCA</h3>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <p class="text-muted">Total: <?php echo $totalRegistros; ?> contratações</p>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <select onchange="window.location.href='?limite='+this.value+'&<?php echo http_build_query(array_diff_key($_GET, ['limite' => '', 'pagina' => ''])); ?>'" class="select-limite">
                        <option value="10" <?php echo $limite == 10 ? 'selected' : ''; ?>>10 por página</option>
                        <option value="20" <?php echo $limite == 20 ? 'selected' : ''; ?>>20 por página</option>
                        <option value="50" <?php echo $limite == 50 ? 'selected' : ''; ?>>50 por página</option>
                        <option value="100" <?php echo $limite == 100 ? 'selected' : ''; ?>>100 por página</option>
                    </select>
                    <a href="exportar.php?<?php echo http_build_query($_GET); ?>" 
   onclick="event.preventDefault(); window.location.href=this.href;" 
   class="btn-exportar" 
   style="background: #27ae60; color: white; padding: 3px 10px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
    
    <i data-lucide="download" style="width: 32px; height: 32px; margin-right: 10px;"></i> Exportar para Excel
</a>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Nº DFD</th>
                        <th>Situação</th>
                        <th>Título</th>
                        <th>Categoria</th>
                        <th>Valor Total</th>
                        <th>Área</th>
                        <th>Datas</th>
                        <th style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dados)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhum registro encontrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dados as $item): ?>
                        <?php
                            // Determinar classe CSS baseado na situação
                            $classeSituacao = '';
                            $textoAdicional = '';
                            
                            if ($item['data_inicio_processo'] < date('Y-m-d') && $item['situacao_execucao'] == 'Não iniciado') {
                                $classeSituacao = 'atrasado-inicio';

                            } elseif ($item['data_conclusao_processo'] < date('Y-m-d') && $item['situacao_execucao'] != 'Concluído') {
                                $classeSituacao = 'atrasado-conclusao';
                                
                            }
                        ?>
                        <tr class="<?php echo $classeSituacao ? 'linha-' . $classeSituacao : ''; ?>">
                            <td><strong><?php echo htmlspecialchars($item['numero_dfd']); ?></strong></td>
                            <td>
                                <span class="situacao-badge <?php echo $classeSituacao; ?>">
                                    <?php echo htmlspecialchars($item['situacao_execucao']) . $textoAdicional; ?>
                                </span>
                                <?php if ($item['dias_ate_conclusao'] !== null && $item['dias_ate_conclusao'] >= 0 && $item['dias_ate_conclusao'] <= 30): ?>
                                    <br><small class="badge-urgente"><?php echo $item['dias_ate_conclusao']; ?> dias</small>
                                <?php elseif ($item['dias_ate_conclusao'] !== null && $item['dias_ate_conclusao'] < 0): ?>
                                    <br><small class="badge-vencido">Vencido há <?php echo abs($item['dias_ate_conclusao']); ?> dias</small>
                                <?php endif; ?>
                            </td>
                            <td class="titulo-cell" title="<?php echo htmlspecialchars($item['titulo_contratacao']); ?>">
                                <?php echo htmlspecialchars(substr($item['titulo_contratacao'], 0, 60)) . '...'; ?>
                            </td>
                            <td><span class="categoria-badge"><?php echo htmlspecialchars($item['categoria_contratacao']); ?></span></td>
                            <td class="valor-cell"><?php echo formatarMoeda($item['valor_total_contratacao']); ?></td>
                            <td><?php echo htmlspecialchars($item['area_requisitante']); ?></td>
                            <td class="datas-cell">
                                <small>
                                    <strong>Início:</strong> <?php echo formatarData($item['data_inicio_processo']); ?><br>
                                    <strong>Fim:</strong> <?php echo formatarData($item['data_conclusao_processo']); ?>
                                </small>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <button onclick="verDetalhes('<?php echo $item['ids']; ?>')" 
                                            class="btn-acao btn-ver" title="Ver detalhes">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    <button onclick="verHistorico('<?php echo $item['numero_dfd']; ?>')"
                                            class="btn-acao btn-historico" title="Ver histórico">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                    </button>
                                    <?php if ($item['tem_licitacao'] == 0): ?>
                                        <button onclick="abrirModalLicitacao('<?php echo $item['ids']; ?>')" 
                                                class="btn-acao btn-licitar" title="Criar licitação">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                            </svg>
                                            <span>Licitar</span>
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #28a745; font-size: 13px; display: flex; align-items: center; gap: 4px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M9 11L12 14L22 4"></path>
                                                <path d="M21 12V19C21 20.1 20.1 21 19 21H5C3.89 21 3 20.1 3 19V5C3 3.9 3.9 3 5 3H16"></path>
                                            </svg>
                                            Licitado
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Paginação -->
            <?php if ($totalPaginas > 1): ?>
            <div class="paginacao">
                <?php if ($pagina > 1): ?>
                    <a href="?pagina=<?php echo $pagina-1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['pagina' => ''])); ?>" class="btn btn-pequeno">← Anterior</a>
                <?php endif; ?>
                
                <span>Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?></span>
                
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="?pagina=<?php echo $pagina+1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['pagina' => ''])); ?>" class="btn btn-pequeno">Próxima →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de Licitação -->
    <div id="modalLicitacao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Criar Licitação</h3>
                <span class="close" onclick="fecharModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form action="process.php" method="POST" id="formLicitacao">
                    <input type="hidden" name="acao" value="criar_licitacao">
                    <input type="hidden" name="pca_dados_ids" id="pca_dados_ids">
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label>NUP *</label>
                                <input type="text" name="nup" required placeholder="xxxxx.xxxxxx/xxxx-xx" 
                                       pattern="\d{5}\.\d{6}\/\d{4}-\d{2}">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Data Entrada DIPLI</label>
                                <input type="date" name="data_entrada_dipli">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Responsável Instrução</label>
                                <input type="text" name="resp_instrucao">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Área Demandante</label>
                                <input type="text" name="area_demandante">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Pregoeiro</label>
                                <input type="text" name="pregoeiro">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Modalidade *</label>
                                <select name="modalidade" required>
                                    <option value="">Selecione</option>
                                    <option value="DISPENSA">DISPENSA</option>
                                    <option value="PREGAO">PREGÃO</option>
                                    <option value="RDC">RDC</option>
                                    <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Tipo *</label>
                                <select name="tipo" required>
                                    <option value="">Selecione</option>
                                    <option value="TRADICIONAL">TRADICIONAL</option>
                                    <option value="COTACAO">COTAÇÃO</option>
                                    <option value="SRP">SRP</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Número</label>
                                <input type="number" name="numero">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Ano</label>
                                <input type="number" name="ano" value="<?php echo date('Y'); ?>">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Prioridade</label>
                                <input type="text" name="prioridade">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Item PGC</label>
                                <input type="text" name="item_pgc" placeholder="xxxx/xxxx" 
                                       pattern="\d{4}\/\d{4}">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Estimado PGC (R$)</label>
                                <input type="text" name="estimado_pgc" placeholder="0,00">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Ano PGC</label>
                                <input type="number" name="ano_pgc" value="<?php echo date('Y'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-grid-full">
                            <div class="form-group">
                                <label>Objeto *</label>
                                <textarea name="objeto" required rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Qtd Itens</label>
                                <input type="number" name="qtd_itens" value="1">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Valor Estimado (R$)</label>
                                <input type="text" name="valor_estimado" placeholder="0,00">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Data Abertura</label>
                                <input type="date" name="data_abertura">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Situação *</label>
                                <select name="situacao" required>
                                    <option value="EM_ANDAMENTO">EM ANDAMENTO</option>
                                    <option value="REVOGADO">REVOGADO</option>
                                    <option value="FRACASSADO">FRACASSADO</option>
                                    <option value="HOMOLOGADO">HOMOLOGADO</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid-full">
                            <div class="form-group">
                                <label>Andamentos</label>
                                <textarea name="andamentos" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="impugnado"> Impugnado?
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="pertinente" checked> Pertinente?
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-grid-full">
                            <div class="form-group">
                                <label>Motivo</label>
                                <input type="text" name="motivo">
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-20">
                        <button type="submit" class="btn btn-sucesso">Criar Licitação</button>
                        <button type="button" class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes -->
    <div id="modalDetalhes" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalhes da Contratação</h3>
                <span class="close" onclick="fecharModalDetalhes()">&times;</span>
            </div>
            <div class="modal-body" id="conteudoDetalhes">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
        console.log('✅ Ícones carregados!');
    } else {
        console.log('❌ Lucide não carregou');
    }
});
</script>
</body>
</html>