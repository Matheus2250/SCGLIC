<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

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

// Filtros
$filtro_area = $_GET['area'] ?? '';

// Construir WHERE para área
// Construir WHERE para área
$where_area = '';
$params_area = [];
if (!empty($filtro_area)) {
    if ($filtro_area === 'GM.') {
        // Para GM, buscar todas as variações que começam com GM
        $where_area = " AND (area_requisitante LIKE 'GM%' OR area_requisitante LIKE 'GM.%')";
    } else {
        $where_area = " AND area_requisitante LIKE ?";
        $params_area[] = $filtro_area . '%';
    }
}

// CONTRATAÇÕES VENCIDAS (ultrapassaram data de conclusão)
// CONTRATAÇÕES VENCIDAS (agrupadas por DFD)
$sql_vencidas = "SELECT DISTINCT 
    numero_contratacao,
    numero_dfd,
    titulo_contratacao,
    area_requisitante,
    data_inicio_processo,
    data_conclusao_processo,
    situacao_execucao,
    valor_total_contratacao,
    prioridade,
    DATEDIFF(CURDATE(), data_conclusao_processo) as dias_atraso
    FROM pca_dados 
    WHERE data_conclusao_processo < CURDATE()
    AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado')
    AND numero_dfd IS NOT NULL 
    AND numero_dfd != ''
    $where_area
    GROUP BY numero_dfd
    ORDER BY dias_atraso DESC";

$stmt_vencidas = $pdo->prepare($sql_vencidas);
$stmt_vencidas->execute($params_area);
$contratacoes_vencidas = $stmt_vencidas->fetchAll();

// CONTRATAÇÕES NÃO INICIADAS (agrupadas por DFD)  
$sql_nao_iniciadas = "SELECT DISTINCT 
    numero_contratacao,
    numero_dfd,
    titulo_contratacao,
    area_requisitante,
    data_inicio_processo,
    data_conclusao_processo,
    situacao_execucao,
    valor_total_contratacao,
    prioridade,
    DATEDIFF(CURDATE(), data_inicio_processo) as dias_atraso_inicio
    FROM pca_dados 
    WHERE data_inicio_processo < CURDATE() 
    AND (situacao_execucao IS NULL OR situacao_execucao = '' OR situacao_execucao = 'Não iniciado')
    AND data_conclusao_processo >= CURDATE()
    AND numero_dfd IS NOT NULL 
    AND numero_dfd != ''
    $where_area
    GROUP BY numero_dfd
    ORDER BY dias_atraso_inicio DESC";

$stmt_nao_iniciadas = $pdo->prepare($sql_nao_iniciadas);
$stmt_nao_iniciadas->execute($params_area);
$contratacoes_nao_iniciadas = $stmt_nao_iniciadas->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contratações Atrasadas - Sistema de Licitações</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<style>
.lucide {
    width: 16px;
    height: 16px;
    vertical-align: middle;
    color: currentColor;
}
.lucide-lg { width: 20px; height: 20px; }
.card-icon .lucide { width: 32px; height: 32px; }
.header h1 .lucide { width: 28px; height: 28px; margin-right: 10px; }
.btn .lucide { margin-right: 8px; }
</style>
</head>
<body>
    <div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; background: #2c3e50; color: white; padding: 20px; margin-bottom: 10px; border-radius: 8px;">
        <h1 style="margin: 0;">📅 Contratações Atrasadas</h1>
        <div style="display: flex; align-items: center; gap: 15px;">
            <span>Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?></span>
            <a href="dashboard.php" class="btn btn-secundario">← Voltar</a>
            <a href="logout.php" class="btn btn-danger">Sair</a>
        </div>
    </div>

        <!-- Filtros -->
        <div class="filtros-container" style="margin-bottom: 40px;">
            <form method="GET" class="filtros-form">
                <div class="filtro-item">
                    <label>Área:</label>
                    <select name="area" onchange="this.form.submit()">
                        <option value="">Todas as áreas</option>
                        <?php foreach ($areas_agrupadas as $area): ?>
                        <option value="<?php echo htmlspecialchars($area); ?>" 
                                <?php echo ($filtro_area === $area) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($area); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primario">Filtrar</button>
                <a href="?" class="btn btn-secundario">Limpar</a>
            </form>
        </div>

        <!-- Cards de Resumo -->
        <div class="cards-container">
            <div class="card card-danger">
                <div class="card-icon"><i data-lucide="clock"></i></div>
                <div class="card-content">
                    <h3><?php echo count($contratacoes_vencidas); ?></h3>
                    <p>Vencidas</p>
                </div>
            </div>
            
            <div class="card card-warning">
                <div class="card-icon"><i data-lucide="alert-triangle"></i></div>
                <div class="card-content">
                    <h3><?php echo count($contratacoes_nao_iniciadas); ?></h3>
                    <p>Não Iniciadas</p>
                </div>
            </div>
            
            <div class="card card-info">
                <div class="card-icon"><i data-lucide="bar-chart"></i></div>
                <div class="card-content">
                    <h3><?php echo count($contratacoes_vencidas) + count($contratacoes_nao_iniciadas); ?></h3>
                    <p>Total Atrasadas</p>
                </div>
            </div>
        </div>

<div style="text-align: right; margin-bottom: 20px;">
    <button onclick="exportarAtrasadas()" class="btn" style="background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
        <i data-lucide="download"></i> Exportar para Excel
    </button>
</div>

        <!-- Abas -->
        <div class="abas">
            <button class="aba ativa" onclick="
                document.getElementById('aba-vencidas').style.display='block';
                document.getElementById('aba-nao-iniciadas').style.display='none';
                this.classList.add('ativa');
                this.parentNode.querySelector('.aba:nth-child(2)').classList.remove('ativa');
            ">Vencidas (<?php echo count($contratacoes_vencidas); ?>)</button>
            
            <button class="aba" onclick="
                document.getElementById('aba-vencidas').style.display='none';
                document.getElementById('aba-nao-iniciadas').style.display='block';
                this.classList.add('ativa');
                this.parentNode.querySelector('.aba:nth-child(1)').classList.remove('ativa');
            ">Não Iniciadas (<?php echo count($contratacoes_nao_iniciadas); ?>)</button>
        </div>

        <!-- Conteúdo Vencidas -->
        <div id="aba-vencidas" class="conteudo-aba">
            <h3 class="text-danger"><i data-lucide="clock"></i> Contratações Vencidas - Não Iniciadas (<?php echo count($contratacoes_vencidas); ?>)</h3>
<p class="text-muted">Contratações que ultrapassaram a data de conclusão e ainda não foram iniciadas</p>
            
            <?php if (!empty($contratacoes_vencidas)): ?>
            <div class="tabela-container">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>Nº DFD</th>
                            <th>Título</th>
                            <th>Área</th>
                            <th>Data Conclusão</th>
                            <th>Dias Atraso</th>
                            <th>Valor (R$)</th>
                            <th>Situação</th>
                            <th>Prioridade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratacoes_vencidas as $contratacao): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($contratacao['numero_dfd']); ?></strong></td>
                            <td class="titulo-cell"><?php echo htmlspecialchars($contratacao['titulo_contratacao']); ?></td>
                            <td><?php echo htmlspecialchars(agruparArea($contratacao['area_requisitante'])); ?></td>
                            <td><?php echo formatarData($contratacao['data_conclusao_processo']); ?></td>
                            <td><span class="badge badge-danger"><?php echo $contratacao['dias_atraso']; ?> dias</span></td>
                            <td><?php echo formatarMoeda($contratacao['valor_total_contratacao']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo empty($contratacao['situacao_execucao']) ? 'warning' : 'info'; ?>">
                                    <?php echo empty($contratacao['situacao_execucao']) ? 'Não iniciado' : htmlspecialchars($contratacao['situacao_execucao']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($contratacao['prioridade']) == 'alta' ? 'danger' : (strtolower($contratacao['prioridade']) == 'media' ? 'warning' : 'info'); ?>">
                                    <?php echo htmlspecialchars($contratacao['prioridade']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="mensagem-vazia">
                <p>🎉 Nenhuma contratação vencida encontrada!</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Conteúdo Não Iniciadas -->
        <div id="aba-nao-iniciadas" class="conteudo-aba" style="display: none;">
            <h3 class="text-warning"><i data-lucide="alert-triangle"></i> Contratações Não Iniciadas (<?php echo count($contratacoes_nao_iniciadas); ?>)</h3>
            <p class="text-muted">Contratações que já deveriam ter iniciado mas ainda não começaram</p>
            
            <?php if (!empty($contratacoes_nao_iniciadas)): ?>
            <div class="tabela-container">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>Nº DFD</th>
                            <th>Título</th>
                            <th>Área</th>
                            <th>Data Início</th>
                            <th>Dias Atraso</th>
                            <th>Valor (R$)</th>
                            <th>Situação</th>
                            <th>Prioridade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratacoes_nao_iniciadas as $contratacao): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($contratacao['numero_contratacao']); ?></strong></td>
                            <td class="titulo-cell"><?php echo htmlspecialchars($contratacao['titulo_contratacao']); ?></td>
                            <td><?php echo htmlspecialchars(agruparArea($contratacao['area_requisitante'])); ?></td>
                            <td><?php echo formatarData($contratacao['data_inicio_processo']); ?></td>
                            <td><span class="badge badge-warning"><?php echo $contratacao['dias_atraso_inicio']; ?> dias</span></td>
                            <td><?php echo formatarMoeda($contratacao['valor_total_contratacao']); ?></td>
                            <td>
                                <span class="badge badge-warning">
                                    <?php echo empty($contratacao['situacao_execucao']) ? 'Não iniciado' : htmlspecialchars($contratacao['situacao_execucao']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($contratacao['prioridade']) == 'alta' ? 'danger' : (strtolower($contratacao['prioridade']) == 'media' ? 'warning' : 'info'); ?>">
                                    <?php echo htmlspecialchars($contratacao['prioridade']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="mensagem-vazia">
                <p>🎉 Nenhuma contratação com atraso no início encontrada!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
function exportarAtrasadas() {
    // Descobrir qual aba está ativa
    var abaVencidas = document.getElementById('aba-vencidas');
    var tipo = 'todos';
    
    if (abaVencidas.style.display !== 'none') {
        tipo = 'vencidas';
    } else {
        tipo = 'nao-iniciadas';
    }
    
    // Pegar filtro de área
    var area = document.querySelector('select[name="area"]').value;
    
    // Construir URL de exportação
    var url = 'exportar_atrasadas_novo.php?tipo=' + tipo;
    if (area) {
        url += '&area=' + encodeURIComponent(area);
    }
    
    // Abrir link de download
    window.open(url, '_blank');
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    console.log('✅ Ícones Lucide carregados!');
});
</script>
</body>
</html>