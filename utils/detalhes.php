<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

if (!isset($_GET['ids'])) {
    echo '<div class="erro">IDs não fornecidos</div>';
    exit;
}

$pdo = conectarDB();
$ids = $_GET['ids'];
$ids_array = explode(',', $ids);
$placeholders = implode(',', array_fill(0, count($ids_array), '?'));

// Buscar dados do PCA
$sql = "SELECT * FROM pca_dados WHERE id IN ($placeholders) ORDER BY id";
$stmt = $pdo->prepare($sql);
$stmt->execute($ids_array);
$dados_pca = $stmt->fetchAll();

if (empty($dados_pca)) {
    echo '<div class="erro">Dados não encontrados</div>';
    exit;
}

// Buscar dados da licitação se existir
$numero_contratacao = $dados_pca[0]['numero_contratacao'];
$sql_licitacao = "SELECT l.*, u.nome as usuario_nome 
                  FROM licitacoes l 
                  LEFT JOIN usuarios u ON l.usuario_id = u.id
                  WHERE l.pca_dados_id IN ($placeholders)
                  ORDER BY l.id DESC LIMIT 1";
$stmt_licitacao = $pdo->prepare($sql_licitacao);
$stmt_licitacao->execute($ids_array);
$licitacao = $stmt_licitacao->fetch();
?>

<!-- Abas -->
<div class="abas-container">
    <div class="abas">
        <button class="aba ativa" onclick="trocarAba('pca', this)">
            <i data-lucide="file-text"></i> Dados do PCA
        </button>
        <?php if ($licitacao): ?>
        <button class="aba" onclick="trocarAba('licitacao', this)">
            <i data-lucide="gavel"></i> Dados da Licitação
        </button>
        <?php endif; ?>
    </div>
</div>
    
    <!-- Conteúdo PCA -->
    <div id="aba-pca" class="conteudo-aba" style="display: block;">
        <h4>Informações Gerais</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>Número da Contratação:</label>
                <span><?php echo htmlspecialchars($numero_contratacao); ?></span>
            </div>
            <div class="info-item">
                <label>Status:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['status_contratacao']); ?></span>
            </div>
            <div class="info-item">
                <label>Categoria:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['categoria_contratacao']); ?></span>
            </div>
            <div class="info-item">
                <label>UASG:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['uasg_atual']); ?></span>
            </div>
            <div class="info-item">
                <label>Área Requisitante:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['area_requisitante']); ?></span>
            </div>
            <div class="info-item">
                <label>Prioridade:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['prioridade']); ?></span>
            </div>
            <div class="info-item">
                <label>Nº DFD:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['numero_dfd']); ?></span>
            </div>
            <div class="info-item">
                <label>Prazo Estimado:</label>
                <span><?php echo $dados_pca[0]['prazo_duracao_dias']; ?> dias</span>
            </div>
            <div class="info-item">
                <label>Data Início:</label>
                <span><?php echo formatarData($dados_pca[0]['data_inicio_processo']); ?></span>
            </div>
            <div class="info-item">
                <label>Data Conclusão:</label>
                <span><?php echo formatarData($dados_pca[0]['data_conclusao_processo']); ?></span>
            </div>
        </div>
        
        <h4 class="mt-20">Título da Contratação</h4>
        <p class="texto-completo"><?php echo htmlspecialchars($dados_pca[0]['titulo_contratacao']); ?></p>
        
        <h4 class="mt-20">Itens da Contratação</h4>
        <table class="tabela-detalhes">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Código Material/Serviço</th>
                    <th>Descrição</th>
                    <th>Unidade</th>
                    <th>Quantidade</th>
                    <th>Valor Unit.</th>
                    <th>Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_geral = 0;
                foreach ($dados_pca as $index => $item): 
                    $total_geral += $item['valor_total'];
                ?>
                <tr>
                    <td data-label="Item"><?php echo $index + 1; ?></td>
                    <td data-label="Código"><?php echo htmlspecialchars($item['codigo_material_servico']); ?></td>
                    <td data-label="Descrição"><?php echo htmlspecialchars($item['descricao_material_servico']); ?></td>
                    <td data-label="Unidade"><?php echo htmlspecialchars($item['unidade_fornecimento']); ?></td>
                    <td data-label="Quantidade"><?php echo $item['quantidade']; ?></td>
                    <td data-label="Valor Unit."><?php echo formatarMoeda($item['valor_unitario']); ?></td>
                    <td data-label="Valor Total"><?php echo formatarMoeda($item['valor_total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6">Total Geral:</th>
                    <th><?php echo formatarMoeda($total_geral); ?></th>
                </tr>
            </tfoot>
        </table>
        
        <h4 class="mt-20">Classificação</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>Classificação:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['classificacao_contratacao']); ?></span>
            </div>
            <div class="info-item">
                <label>Código Classe/Grupo:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['codigo_classe_grupo']); ?></span>
            </div>
            <div class="info-item">
                <label>Nome Classe/Grupo:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['nome_classe_grupo']); ?></span>
            </div>
            <?php if (!empty($dados_pca[0]['codigo_pdm_material'])): ?>
            <div class="info-item">
                <label>Código PDM:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['codigo_pdm_material']); ?></span>
            </div>
            <div class="info-item">
                <label>Nome PDM:</label>
                <span><?php echo htmlspecialchars($dados_pca[0]['nome_pdm_material']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Conteúdo Licitação -->
    <?php if ($licitacao): ?>
    <div id="aba-licitacao" class="conteudo-aba" style="display: none;">
        <h4>Informações da Licitação</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>NUP:</label>
                <span><?php echo htmlspecialchars($licitacao['nup']); ?></span>
            </div>
            <div class="info-item">
                <label>Modalidade:</label>
                <span><?php echo htmlspecialchars($licitacao['modalidade']); ?></span>
            </div>
            <div class="info-item">
                <label>Tipo:</label>
                <span><?php echo htmlspecialchars($licitacao['tipo']); ?></span>
            </div>
            <div class="info-item">
                <label>Número/Ano:</label>
                <span><?php echo $licitacao['numero'] . '/' . $licitacao['ano']; ?></span>
            </div>
            <div class="info-item">
                <label>Situação:</label>
                <span class="badge badge-<?php echo strtolower($licitacao['situacao']); ?>">
                    <?php echo str_replace('_', ' ', $licitacao['situacao']); ?>
                </span>
            </div>
            <div class="info-item">
                <label>Pregoeiro:</label>
                <span><?php echo htmlspecialchars($licitacao['pregoeiro']); ?></span>
            </div>
            <div class="info-item">
                <label>Data Entrada DIPLI:</label>
                <span><?php echo formatarData($licitacao['data_entrada_dipli']); ?></span>
            </div>
            <div class="info-item">
                <label>Data Abertura:</label>
                <span><?php echo formatarData($licitacao['data_abertura']); ?></span>
            </div>
            <div class="info-item">
                <label>Valor Estimado:</label>
                <span><?php echo formatarMoeda($licitacao['valor_estimado']); ?></span>
            </div>
            <div class="info-item">
                <label>Qtd Itens:</label>
                <span><?php echo $licitacao['qtd_itens']; ?></span>
            </div>
        </div>
        
        <h4 class="mt-20">Objeto</h4>
        <p class="texto-completo"><?php echo htmlspecialchars($licitacao['objeto']); ?></p>
        
        <?php if (!empty($licitacao['andamentos'])): ?>
        <h4 class="mt-20">Andamentos</h4>
        <p class="texto-completo"><?php echo nl2br(htmlspecialchars($licitacao['andamentos'])); ?></p>
        <?php endif; ?>
        
        <h4 class="mt-20">Informações Adicionais</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>Impugnado:</label>
                <span><?php echo $licitacao['impugnado'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <div class="info-item">
                <label>Pertinente:</label>
                <span><?php echo $licitacao['pertinente'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <?php if (!empty($licitacao['motivo'])): ?>
            <div class="info-item">
                <label>Motivo:</label>
                <span><?php echo htmlspecialchars($licitacao['motivo']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <label>Item PGC:</label>
                <span><?php echo htmlspecialchars($licitacao['item_pgc']); ?></span>
            </div>
            <div class="info-item">
                <label>Estimado PGC:</label>
                <span><?php echo formatarMoeda($licitacao['estimado_pgc']); ?></span>
            </div>
            <div class="info-item">
                <label>Ano PGC:</label>
                <span><?php echo $licitacao['ano_pgc']; ?></span>
            </div>
        </div>
        
        <?php if ($licitacao['situacao'] == 'HOMOLOGADO'): ?>
        <h4 class="mt-20">Resultado</h4>
        <div class="info-grid">
            <div class="info-item">
                <label>Qtd Homologada:</label>
                <span><?php echo $licitacao['qtd_homol'] ?: '-'; ?></span>
            </div>
            <div class="info-item">
                <label>Valor Homologado:</label>
                <span><?php echo $licitacao['valor_homologado'] ? formatarMoeda($licitacao['valor_homologado']) : '-'; ?></span>
            </div>
            <div class="info-item">
                <label>Economia:</label>
                <span><?php echo $licitacao['economia'] ? formatarMoeda($licitacao['economia']) : '-'; ?></span>
            </div>
            <div class="info-item">
                <label>Data Homologação:</label>
                <span><?php echo formatarData($licitacao['data_homologacao']); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-rodape">
            <small>Criado por: <?php echo htmlspecialchars($licitacao['usuario_nome']); ?> em <?php echo formatarData($licitacao['criado_em']); ?></small>
            <?php if ($licitacao['atualizado_em'] != $licitacao['criado_em']): ?>
            <small>Última atualização: <?php echo formatarData($licitacao['atualizado_em']); ?></small>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function trocarAba(aba, botao) {
    // Esconder todas as abas
    var conteudos = document.querySelectorAll('.conteudo-aba');
    for(var i = 0; i < conteudos.length; i++) {
        conteudos[i].style.display = 'none';
    }
    
    // Remover classe ativa de todos os botões
    var botoes = document.querySelectorAll('.aba');
    for(var i = 0; i < botoes.length; i++) {
        botoes[i].classList.remove('ativa');
    }
    
    // Mostrar aba selecionada
    document.getElementById('aba-' + aba).style.display = 'block';
    
    // Ativar botão clicado
    botao.classList.add('ativa');
}
</script>

<style>
/* Layout Responsivo e Dark Mode para Modal de Detalhes */
.abas-container {
    margin-bottom: 20px;
}

.abas {
    display: flex;
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 4px;
    gap: 4px;
    border: 1px solid var(--border-light);
}

.aba {
    flex: 1;
    padding: 12px 16px;
    background: transparent;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
    min-height: 44px;
}

.aba:hover {
    background: var(--border-light);
    color: var(--text-secondary);
}

.aba.ativa {
    background: var(--button-primary);
    color: white;
    box-shadow: 0 2px 4px rgba(0,123,255,0.3);
}

.conteudo-aba {
    display: none;
    background: var(--bg-card);
    border-radius: 8px;
    padding: 20px;
    border: 1px solid var(--border-light);
}

.conteudo-aba.ativo {
    display: block;
}

.conteudo-aba h4 {
    margin: 0 0 16px 0;
    color: var(--text-primary);
    font-size: 18px;
    border-bottom: 2px solid var(--border-light);
    padding-bottom: 8px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.info-item {
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: 6px;
    border-left: 3px solid var(--button-primary);
}

.info-item label {
    display: block;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.info-item span {
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 500;
}

.texto-completo {
    background: var(--bg-secondary);
    padding: 16px;
    border-radius: 6px;
    border-left: 3px solid var(--status-success);
    margin: 16px 0;
    line-height: 1.6;
    color: var(--text-primary);
}

.info-rodape {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--border-light);
}

.info-rodape small {
    color: var(--text-muted);
    display: block;
    margin-bottom: 4px;
}

.mt-20 {
    margin-top: 20px;
}

/* Tabela de Detalhes */
.conteudo-aba .tabela-detalhes {
    width: 100%;
    border-collapse: collapse;
    margin: 16px 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    background: var(--bg-card) !important;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border-light);
    box-shadow: 0 2px 4px var(--shadow-card);
    position: relative;
    left: 0 !important;
    right: 0 !important;
}

.conteudo-aba .tabela-detalhes th,
.conteudo-aba .tabela-detalhes td {
    padding: 10px 8px;
    text-align: left;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-primary) !important;
    font-size: 13px;
    background: var(--bg-card) !important;
}

.conteudo-aba .tabela-detalhes th {
    background: var(--bg-table-header) !important;
    font-weight: 600;
    color: var(--text-primary) !important;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.conteudo-aba .tabela-detalhes tbody tr {
    background: var(--bg-card) !important;
}

.conteudo-aba .tabela-detalhes tbody tr:hover {
    background: var(--bg-table-row-hover) !important;
}

.conteudo-aba .tabela-detalhes tbody tr:hover td {
    background: var(--bg-table-row-hover) !important;
}

.conteudo-aba .tabela-detalhes tfoot th {
    background: var(--bg-secondary) !important;
    font-weight: 700;
    border-top: 2px solid var(--button-primary);
    color: var(--text-primary) !important;
}

/* Reset para garantir alinhamento correto */
.conteudo-aba h4 + .tabela-detalhes {
    margin-left: 0;
    margin-right: 0;
    clear: both;
    display: table;
}

/* Responsivo para tabela */
@media (max-width: 768px) {
    .conteudo-aba .tabela-detalhes {
        font-size: 12px;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    .tabela-detalhes th,
    .tabela-detalhes td {
        padding: 8px 6px;
    }
    
    .tabela-detalhes th:nth-child(2),
    .tabela-detalhes td:nth-child(2) {
        display: none; /* Ocultar código em mobile */
    }
    
    .tabela-detalhes th:nth-child(3),
    .tabela-detalhes td:nth-child(3) {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}

@media (max-width: 480px) {
    .tabela-detalhes {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
        border: none;
    }
    
    .tabela-detalhes thead,
    .tabela-detalhes tbody,
    .tabela-detalhes th,
    .tabela-detalhes td,
    .tabela-detalhes tr {
        display: block;
    }
    
    .tabela-detalhes thead tr {
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
    
    .tabela-detalhes tr {
        border: 1px solid var(--border-light);
        border-radius: 8px;
        margin-bottom: 10px;
        padding: 12px;
        background: var(--bg-card);
    }
    
    .tabela-detalhes td {
        border: none;
        position: relative;
        padding-left: 25%;
        padding-bottom: 8px;
        padding-top: 8px;
        white-space: normal;
    }
    
    .tabela-detalhes td:before {
        content: attr(data-label) ": ";
        position: absolute;
        left: 6px;
        width: 20%;
        text-align: left;
        font-weight: 600;
        color: var(--text-muted);
        font-size: 11px;
        text-transform: uppercase;
    }
}

/* Dark Mode Específico para Tabela */
[data-theme="dark"] .conteudo-aba .tabela-detalhes {
    background: #2d3748 !important;
    border-color: #4a5568 !important;
}

[data-theme="dark"] .conteudo-aba .tabela-detalhes th {
    background: linear-gradient(135deg, #2d3748, #4a5568) !important;
    color: #ffffff !important;
    border-bottom-color: #4a5568 !important;
}

[data-theme="dark"] .conteudo-aba .tabela-detalhes td {
    background: #2d3748 !important;
    color: #f7fafc !important;
    border-bottom-color: #4a5568 !important;
}

[data-theme="dark"] .conteudo-aba .tabela-detalhes tbody tr {
    background: #2d3748 !important;
}

[data-theme="dark"] .conteudo-aba .tabela-detalhes tbody tr:hover {
    background: #4a5568 !important;
}

[data-theme="dark"] .conteudo-aba .tabela-detalhes tbody tr:hover td {
    background: #4a5568 !important;
}

[data-theme="dark"] .conteudo-aba .tabela-detalhes tfoot th {
    background: #4a5568 !important;
    color: #ffffff !important;
    border-top-color: #3182ce !important;
    border-bottom-color: #4a5568 !important;
}

/* Dark Mode */
[data-theme="dark"] .abas {
    background: #2d3748;
    border-color: #4a5568;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-homologado,
.badge-success {
    background: var(--status-success);
    color: white;
}

.badge-em_andamento,
.badge-andamento,
.badge-warning {
    background: var(--status-warning);
    color: white;
}

.badge-cancelado,
.badge-suspenso,
.badge-danger {
    background: var(--status-danger);
    color: white;
}

.badge-publicado,
.badge-info {
    background: var(--status-info);
    color: white;
}

/* Responsivo */
@media (max-width: 768px) {
    .abas {
        flex-direction: column;
    }
    
    .aba {
        justify-content: flex-start;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .conteudo-aba {
        padding: 16px;
    }
    
    .info-item {
        padding: 10px;
    }
}
</style>