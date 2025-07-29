<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

if (!isset($_GET['numero'])) {
    echo '<div class="erro">Número não fornecido</div>';
    exit;
}

$pdo = conectarDB();
$numero_dfd = $_GET['numero'];

// Buscar dados básicos da contratação pelo DFD
$sql_contratacao = "SELECT DISTINCT 
    numero_contratacao,
    numero_dfd,
    titulo_contratacao,
    area_requisitante,
    situacao_execucao,
    data_inicio_processo,
    data_conclusao_processo,
    valor_total_contratacao
    FROM pca_dados 
    WHERE numero_dfd = ? 
    LIMIT 1";
$stmt_contratacao = $pdo->prepare($sql_contratacao);
$stmt_contratacao->execute([$numero_dfd]);
$contratacao = $stmt_contratacao->fetch();

if (!$contratacao) {
    echo '<div class="erro">Contratação não encontrada</div>';
    exit;
}

$numero_contratacao = $contratacao['numero_contratacao'];

// Buscar histórico de mudanças importantes (apenas campos relevantes)
$sql_historico = "SELECT 
    h.data_alteracao,
    h.campo_alterado,
    h.valor_anterior,
    h.valor_novo,
    u.nome as usuario_nome
    FROM pca_historico h
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    WHERE h.numero_contratacao = ?
    AND h.campo_alterado IN ('situacao_execucao', 'data_inicio_processo', 'data_conclusao_processo', 'valor_total_contratacao', 'prioridade')
    ORDER BY h.data_alteracao DESC
    LIMIT 20";
$stmt_historico = $pdo->prepare($sql_historico);
$stmt_historico->execute([$numero_contratacao]);
$historico = $stmt_historico->fetchAll();

// Buscar tempo em cada estado
$sql_estados = "SELECT * FROM pca_estados_tempo 
                WHERE numero_contratacao = ? 
                ORDER BY data_inicio DESC";
$stmt_estados = $pdo->prepare($sql_estados);
$stmt_estados->execute([$numero_contratacao]);
$estados = $stmt_estados->fetchAll();

// Buscar licitação se existir
$sql_licitacao = "SELECT l.*, u.nome as usuario_nome 
                  FROM licitacoes l 
                  LEFT JOIN usuarios u ON l.usuario_id = u.id
                  WHERE l.pca_dados_id IN (
                      SELECT id FROM pca_dados WHERE numero_contratacao = ?
                  )
                  ORDER BY l.id DESC LIMIT 1";
$stmt_licitacao = $pdo->prepare($sql_licitacao);
$stmt_licitacao->execute([$numero_contratacao]);
$licitacao = $stmt_licitacao->fetch();
?>

<div class="historico-container">
    <!-- Cabeçalho com informações básicas -->
    <div class="info-header">
        <h3 class="header-title">
            <i data-lucide="history"></i>
            Histórico - DFD <?php echo htmlspecialchars($numero_dfd); ?>
        </h3>
        <div class="info-grid">
            <div class="info-item">
                <label>Contratação:</label>
                <span><?php echo htmlspecialchars($contratacao['numero_contratacao']); ?></span>
            </div>
            <div class="info-item">
                <label>Situação Atual:</label>
                <span class="status-badge status-<?php echo $contratacao['situacao_execucao'] == 'Concluído' ? 'success' : 'warning'; ?>">
                    <?php echo htmlspecialchars($contratacao['situacao_execucao'] ?: 'Não iniciado'); ?>
                </span>
            </div>
            <div class="info-item">
                <label>Área:</label>
                <span><?php echo htmlspecialchars(agruparArea($contratacao['area_requisitante'])); ?></span>
            </div>
            <div class="info-item">
                <label>Valor Total:</label>
                <span><?php echo formatarMoeda($contratacao['valor_total_contratacao']); ?></span>
            </div>
        </div>
    </div>

    <!-- Timeline de Estados -->
    <?php if (!empty($estados)): ?>
    <h4 class="section-title">
        <i data-lucide="clock"></i>
        Linha do Tempo
    </h4>
    <div class="timeline-container">
        <?php 
        $total_dias = 0;
        foreach ($estados as $index => $estado): 
            $dias_no_estado = 0;
            
            if ($estado['ativo']) {
                $dias_no_estado = (new DateTime())->diff(new DateTime($estado['data_inicio']))->days;
                $cor = '#28a745';
                $icone = 'play-circle';
            } else {
                $dias_no_estado = $estado['dias_no_estado'];
                $total_dias += $dias_no_estado;
                $cor = '#6c757d';
                $icone = 'check-circle';
            }
        ?>
        
        <div class="timeline-item">
            <!-- Ícone e linha -->
            <div class="timeline-icon-container">
                <div class="timeline-icon" style="background: <?php echo $cor; ?>;">
                    <i data-lucide="<?php echo $icone; ?>"></i>
                </div>
                <?php if ($index < count($estados) - 1): ?>
                <div class="timeline-line"></div>
                <?php endif; ?>
            </div>
            
            <!-- Conteúdo -->
            <div class="timeline-content">
                <div class="timeline-info">
                    <div class="timeline-main">
                        <h5 class="timeline-title">
                            <?php echo htmlspecialchars($estado['situacao_execucao']); ?>
                        </h5>
                        <small class="timeline-date">
                            <?php echo formatarData($estado['data_inicio']); ?>
                            <?php if (!$estado['ativo'] && $estado['data_fim']): ?>
                                → <?php echo formatarData($estado['data_fim']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="timeline-duration">
                        <span class="duration-text" style="color: <?php echo $cor; ?>;">
                            <?php echo $dias_no_estado; ?> <?php echo $dias_no_estado == 1 ? 'dia' : 'dias'; ?>
                        </span>
                        <?php if ($estado['ativo']): ?>
                            <small class="status-active">(em andamento)</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Mudanças Relevantes -->
    <?php if (!empty($historico)): ?>
    <h4 class="section-title">
        <i data-lucide="activity"></i>
        Alterações Recentes
    </h4>
    <div class="changes-container">
        <?php 
        $campos_nome = [
            'situacao_execucao' => 'Situação',
            'data_inicio_processo' => 'Data de Início',
            'data_conclusao_processo' => 'Data de Conclusão',
            'valor_total_contratacao' => 'Valor Total',
            'prioridade' => 'Prioridade'
        ];
        
        foreach ($historico as $item): 
            $nome_campo = $campos_nome[$item['campo_alterado']] ?? $item['campo_alterado'];
            
            // Formatar valores conforme o tipo
            $valor_anterior = $item['valor_anterior'];
            $valor_novo = $item['valor_novo'];
            
            if ($item['campo_alterado'] == 'valor_total_contratacao') {
                $valor_anterior = formatarMoeda($valor_anterior);
                $valor_novo = formatarMoeda($valor_novo);
            } elseif (strpos($item['campo_alterado'], 'data_') !== false) {
                $valor_anterior = formatarData($valor_anterior);
                $valor_novo = formatarData($valor_novo);
            }
        ?>
        <div class="change-item">
            <div class="change-content">
                <div class="change-main">
                    <strong class="change-field"><?php echo $nome_campo; ?></strong>
                    <div class="change-values">
                        <span class="value-old">
                            <?php echo htmlspecialchars($valor_anterior ?: 'Vazio'); ?>
                        </span>
                        <span class="change-arrow">→</span>
                        <span class="value-new">
                            <?php echo htmlspecialchars($valor_novo ?: 'Vazio'); ?>
                        </span>
                    </div>
                </div>
                <div class="change-meta">
                    <small class="change-date">
                        <?php echo date('d/m/Y H:i', strtotime($item['data_alteracao'])); ?>
                        <?php if ($item['usuario_nome']): ?>
                            <br>por <?php echo htmlspecialchars($item['usuario_nome']); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i data-lucide="info"></i>
        <p>Nenhuma alteração registrada ainda.</p>
    </div>
    <?php endif; ?>

    <!-- Informações da Licitação -->
    <?php if ($licitacao): ?>
    <h4 class="section-title" style="margin-top: 30px;">
        <i data-lucide="gavel"></i>
        Licitação Vinculada
    </h4>
    <div class="licitacao-info">
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
                <label>Situação:</label>
                <span class="status-badge status-<?php echo $licitacao['situacao'] == 'HOMOLOGADO' ? 'success' : 'warning'; ?>">
                    <?php echo str_replace('_', ' ', $licitacao['situacao']); ?>
                </span>
            </div>
            <div class="info-item">
                <label>Criada em:</label>
                <span><?php echo formatarData($licitacao['criado_em']); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Carregar ícones Lucide
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>

<style>
/* Layout Responsivo e Dark Mode para Modal de Histórico */
.historico-container {
    padding: 20px;
    color: var(--text-primary);
}

/* Cabeçalho */
.info-header {
    background: var(--bg-secondary);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid var(--border-light);
}

.header-title {
    margin: 0 0 15px 0;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 20px;
    font-weight: 600;
}

.header-title i {
    width: 24px;
    height: 24px;
}

/* Grid de informações */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.info-item {
    padding: 12px;
    background: var(--bg-card);
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

/* Status badges */
.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-success {
    background: var(--status-success);
    color: white;
}

.status-warning {
    background: var(--status-warning);
    color: white;
}

/* Títulos de seção */
.section-title {
    margin: 30px 0 15px 0;
    color: var(--text-primary);
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 2px solid var(--border-light);
    padding-bottom: 8px;
}

.section-title i {
    width: 20px;
    height: 20px;
}

/* Timeline */
.timeline-container {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.timeline-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 20px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon-container {
    position: relative;
    margin-right: 20px;
    flex-shrink: 0;
}

.timeline-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.timeline-icon i {
    width: 20px;
    height: 20px;
}

.timeline-line {
    position: absolute;
    top: 40px;
    left: 19px;
    width: 2px;
    height: 40px;
    background: var(--border-color);
}

.timeline-content {
    flex: 1;
    min-width: 0;
}

.timeline-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}

.timeline-main {
    flex: 1;
    min-width: 0;
}

.timeline-title {
    margin: 0;
    font-size: 16px;
    color: var(--text-primary);
    font-weight: 600;
}

.timeline-date {
    color: var(--text-muted);
    font-size: 13px;
}

.timeline-duration {
    text-align: right;
    flex-shrink: 0;
}

.duration-text {
    font-size: 18px;
    font-weight: bold;
    display: block;
}

.status-active {
    color: var(--status-success);
    font-size: 11px;
    display: block;
    margin-top: 2px;
}

/* Mudanças */
.changes-container {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 16px;
}

.change-item {
    padding: 12px 0;
    border-bottom: 1px solid var(--border-light);
}

.change-item:last-child {
    border-bottom: none;
}

.change-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}

.change-main {
    flex: 1;
    min-width: 0;
}

.change-field {
    color: var(--text-primary);
    font-weight: 600;
    display: block;
    margin-bottom: 6px;
}

.change-values {
    font-size: 14px;
    line-height: 1.4;
}

.value-old {
    color: var(--status-danger);
    text-decoration: line-through;
}

.change-arrow {
    margin: 0 10px;
    color: var(--text-muted);
    font-weight: bold;
}

.value-new {
    color: var(--status-success);
    font-weight: 600;
}

.change-meta {
    text-align: right;
    flex-shrink: 0;
}

.change-date {
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.3;
}

/* Estado vazio */
.empty-state {
    background: var(--bg-secondary);
    padding: 30px;
    text-align: center;
    border-radius: 8px;
    color: var(--text-muted);
}

.empty-state i {
    width: 32px;
    height: 32px;
    margin-bottom: 10px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

/* Licitação vinculada */
.licitacao-info {
    background: linear-gradient(135deg, var(--status-success), rgba(39, 174, 96, 0.1));
    border: 1px solid var(--status-success);
    border-radius: 8px;
    padding: 16px;
}

[data-theme="dark"] .licitacao-info {
    background: linear-gradient(135deg, #2d5016, rgba(56, 161, 105, 0.1));
    border-color: #38a169;
}

/* Responsivo */
@media (max-width: 768px) {
    .historico-container {
        padding: 16px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .info-header {
        padding: 16px;
    }
    
    .timeline-info {
        flex-direction: column;
        gap: 8px;
    }
    
    .timeline-duration {
        text-align: left;
    }
    
    .change-content {
        flex-direction: column;
        gap: 8px;
    }
    
    .change-meta {
        text-align: left;
    }
    
    .section-title {
        font-size: 16px;
    }
    
    .header-title {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .timeline-icon-container {
        margin-right: 12px;
    }
    
    .timeline-icon {
        width: 32px;
        height: 32px;
    }
    
    .timeline-icon i {
        width: 16px;
        height: 16px;
    }
    
    .timeline-line {
        left: 15px;
        top: 32px;
    }
    
    .duration-text {
        font-size: 16px;
    }
}
</style>