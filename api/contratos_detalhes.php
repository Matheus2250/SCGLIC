<?php
/**
 * API para buscar detalhes de contratos - Sistema CGLIC
 * Sistema de Contratos Administrativos
 */

require_once '../config.php';
require_once '../functions.php';

verificarLogin();

$contratoId = intval($_GET['id'] ?? 0);

if (!$contratoId) {
    echo '<div class="error-message"><i data-lucide="alert-circle"></i> ID do contrato não fornecido</div>';
    exit;
}

try {
    $pdo = conectarDB();
    
    // Buscar dados principais do contrato
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u.nome as criado_por_nome,
               DATE_FORMAT(c.criado_em, '%d/%m/%Y %H:%i') as criado_em_formatado,
               DATE_FORMAT(c.atualizado_em, '%d/%m/%Y %H:%i') as atualizado_em_formatado,
               DATE_FORMAT(c.data_assinatura, '%d/%m/%Y') as data_assinatura_formatada,
               DATE_FORMAT(c.data_inicio, '%d/%m/%Y') as data_inicio_formatada,
               DATE_FORMAT(c.data_fim, '%d/%m/%Y') as data_fim_formatada
        FROM contratos c
        LEFT JOIN usuarios u ON c.criado_por = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$contratoId]);
    $contrato = $stmt->fetch();
    
    if (!$contrato) {
        echo '<div class="error-message"><i data-lucide="alert-circle"></i> Contrato não encontrado</div>';
        exit;
    }
    
    // Buscar histórico se existir
    $stmt_historico = $pdo->prepare("
        SELECT h.*, u.nome as usuario_nome,
               DATE_FORMAT(h.data_alteracao, '%d/%m/%Y %H:%i') as data_alteracao_formatada
        FROM contratos_historico h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.contrato_id = ?
        ORDER BY h.data_alteracao DESC
        LIMIT 10
    ");
    $stmt_historico->execute([$contratoId]);
    $historico = $stmt_historico->fetchAll();
    
    // Buscar anexos se existirem
    $stmt_anexos = $pdo->prepare("
        SELECT a.*, u.nome as criado_por_nome,
               DATE_FORMAT(a.criado_em, '%d/%m/%Y %H:%i') as criado_em_formatado
        FROM contratos_anexos a
        LEFT JOIN usuarios u ON a.criado_por = u.id
        WHERE a.contrato_id = ?
        ORDER BY a.criado_em DESC
    ");
    $stmt_anexos->execute([$contratoId]);
    $anexos = $stmt_anexos->fetchAll();
    
} catch (Exception $e) {
    echo '<div class="error-message"><i data-lucide="alert-circle"></i> Erro ao carregar dados: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Função para calcular dias restantes
function calcularDiasRestantes($dataFim) {
    if (!$dataFim) return null;
    $hoje = new DateTime();
    $fim = new DateTime($dataFim);
    $diff = $hoje->diff($fim);
    return $diff->invert ? -$diff->days : $diff->days;
}

$diasRestantes = calcularDiasRestantes($contrato['data_fim']);
?>

<div class="contrato-detalhes">
    <!-- Abas -->
    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="informacoes">
                <i data-lucide="info"></i> Informações Gerais
            </button>
            <button class="tab-btn" data-tab="vigencia">
                <i data-lucide="calendar"></i> Vigência & Valores
            </button>
            <?php if (!empty($historico)): ?>
            <button class="tab-btn" data-tab="historico">
                <i data-lucide="clock"></i> Histórico (<?= count($historico) ?>)
            </button>
            <?php endif; ?>
            <?php if (!empty($anexos)): ?>
            <button class="tab-btn" data-tab="anexos">
                <i data-lucide="paperclip"></i> Anexos (<?= count($anexos) ?>)
            </button>
            <?php endif; ?>
        </div>
        
        <!-- Aba Informações Gerais -->
        <div class="tab-content active" id="informacoes">
            <div class="info-grid">
                <!-- Dados Básicos -->
                <div class="info-section">
                    <h4><i data-lucide="file-contract"></i> Dados do Contrato</h4>
                    <div class="info-row">
                        <label>Número/Ano:</label>
                        <span class="highlight"><?= htmlspecialchars($contrato['numero_contrato']) ?>/<?= htmlspecialchars($contrato['ano_contrato']) ?></span>
                    </div>
                    <div class="info-row">
                        <label>Número SEI:</label>
                        <span><?= htmlspecialchars($contrato['numero_sei']) ?: '-' ?></span>
                    </div>
                    <div class="info-row">
                        <label>Modalidade:</label>
                        <span class="badge badge-blue"><?= htmlspecialchars($contrato['modalidade']) ?></span>
                    </div>
                    <div class="info-row">
                        <label>Status:</label>
                        <span class="status-badge status-<?= strtolower($contrato['status_contrato']) ?>">
                            <?= ucfirst($contrato['status_contrato']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <label>Área Gestora:</label>
                        <span><?= htmlspecialchars($contrato['area_gestora']) ?: 'Não informado' ?></span>
                    </div>
                </div>
                
                <!-- Contratado -->
                <div class="info-section">
                    <h4><i data-lucide="building"></i> Empresa Contratada</h4>
                    <div class="info-row">
                        <label>Nome:</label>
                        <span class="highlight"><?= htmlspecialchars($contrato['nome_empresa']) ?></span>
                    </div>
                    <div class="info-row">
                        <label>CNPJ/CPF:</label>
                        <span><?= htmlspecialchars($contrato['cnpj_cpf']) ?: 'Não informado' ?></span>
                    </div>
                    <div class="info-row">
                        <label>Fiscais:</label>
                        <span><?= htmlspecialchars($contrato['fiscais']) ?: 'Não informado' ?></span>
                    </div>
                </div>
                
                <!-- Objeto Completo -->
                <div class="info-section full-width">
                    <h4><i data-lucide="file-text"></i> Objeto do Contrato</h4>
                    <div class="objeto-content">
                        <?= nl2br(htmlspecialchars($contrato['objeto_servico'])) ?>
                    </div>
                </div>
                
                <!-- Finalidade -->
                <?php if (!empty($contrato['finalidade'])): ?>
                <div class="info-section full-width">
                    <h4><i data-lucide="target"></i> Finalidade</h4>
                    <div class="finalidade-content">
                        <?= nl2br(htmlspecialchars($contrato['finalidade'])) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Observações -->
                <?php if (!empty($contrato['observacoes'])): ?>
                <div class="info-section full-width">
                    <h4><i data-lucide="message-square"></i> Observações</h4>
                    <div class="observacoes-content">
                        <?= nl2br(htmlspecialchars($contrato['observacoes'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Aba Vigência & Valores -->
        <div class="tab-content" id="vigencia">
            <!-- Cards de Valores -->
            <div class="valores-grid">
                <div class="valor-card inicial">
                    <div class="valor-icon">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div class="valor-content">
                        <div class="valor-label">Valor Inicial</div>
                        <div class="valor-amount">R$ <?= number_format($contrato['valor_inicial'], 2, ',', '.') ?></div>
                    </div>
                </div>
                
                <div class="valor-card atual">
                    <div class="valor-icon">
                        <i data-lucide="trending-up"></i>
                    </div>
                    <div class="valor-content">
                        <div class="valor-label">Valor Atual</div>
                        <div class="valor-amount">R$ <?= number_format($contrato['valor_atual'], 2, ',', '.') ?></div>
                        <?php 
                        $percentual = $contrato['valor_inicial'] > 0 ? (($contrato['valor_atual'] - $contrato['valor_inicial']) / $contrato['valor_inicial']) * 100 : 0;
                        if (abs($percentual) > 0.01):
                        ?>
                        <div class="valor-percent <?= $percentual >= 0 ? 'positive' : 'negative' ?>">
                            <?= $percentual >= 0 ? '+' : '' ?><?= number_format($percentual, 1) ?>%
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="valor-card economia">
                    <div class="valor-icon">
                        <i data-lucide="piggy-bank"></i>
                    </div>
                    <div class="valor-content">
                        <div class="valor-label">Economia</div>
                        <div class="valor-amount">R$ <?= number_format(max(0, $contrato['valor_inicial'] - $contrato['valor_atual']), 2, ',', '.') ?></div>
                    </div>
                </div>
                
                <?php if ($diasRestantes !== null): ?>
                <div class="valor-card prazo <?= $diasRestantes <= 30 ? 'warning' : 'normal' ?>">
                    <div class="valor-icon">
                        <i data-lucide="<?= $diasRestantes <= 0 ? 'alert-circle' : 'clock' ?>"></i>
                    </div>
                    <div class="valor-content">
                        <div class="valor-label"><?= $diasRestantes <= 0 ? 'Vencido' : 'Dias Restantes' ?></div>
                        <div class="valor-amount"><?= abs($diasRestantes) ?></div>
                        <?php if ($diasRestantes <= 30 && $diasRestantes > 0): ?>
                        <div class="valor-percent warning">Atenção</div>
                        <?php elseif ($diasRestantes <= 0): ?>
                        <div class="valor-percent negative">Vencido</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Informações de Vigência -->
            <div class="vigencia-section">
                <h4><i data-lucide="calendar"></i> Cronograma de Vigência</h4>
                <div class="cronograma-grid">
                    <div class="cronograma-item">
                        <div class="cronograma-label">Data de Assinatura</div>
                        <div class="cronograma-value">
                            <?= $contrato['data_assinatura_formatada'] ?: 'Não informado' ?>
                        </div>
                    </div>
                    <div class="cronograma-item">
                        <div class="cronograma-label">Início da Vigência</div>
                        <div class="cronograma-value">
                            <?= $contrato['data_inicio_formatada'] ?: 'Não informado' ?>
                        </div>
                    </div>
                    <div class="cronograma-item">
                        <div class="cronograma-label">Fim da Vigência</div>
                        <div class="cronograma-value <?= $diasRestantes !== null && $diasRestantes <= 30 ? 'warning' : '' ?>">
                            <?= $contrato['data_fim_formatada'] ?: 'Não informado' ?>
                        </div>
                    </div>
                    <?php if ($contrato['data_assinatura'] && $contrato['data_fim']): ?>
                    <div class="cronograma-item">
                        <div class="cronograma-label">Prazo Total</div>
                        <div class="cronograma-value">
                            <?php
                            $inicio = new DateTime($contrato['data_assinatura']);
                            $fim = new DateTime($contrato['data_fim']);
                            $prazoTotal = $inicio->diff($fim)->days;
                            echo $prazoTotal . ' dias (' . number_format($prazoTotal / 365, 1) . ' anos)';
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Aba Histórico -->
        <?php if (!empty($historico)): ?>
        <div class="tab-content" id="historico">
            <div class="historico-section">
                <h4><i data-lucide="clock"></i> Histórico de Alterações</h4>
                <div class="timeline">
                    <?php foreach ($historico as $item): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker">
                            <i data-lucide="edit"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-action"><?= htmlspecialchars($item['acao']) ?></span>
                                <span class="timeline-date"><?= $item['data_alteracao_formatada'] ?></span>
                            </div>
                            <div class="timeline-user">
                                Por: <?= htmlspecialchars($item['usuario_nome'] ?: 'Sistema') ?>
                            </div>
                            <?php if (!empty($item['detalhes'])): ?>
                            <div class="timeline-details">
                                <?= nl2br(htmlspecialchars($item['detalhes'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Aba Anexos -->
        <?php if (!empty($anexos)): ?>
        <div class="tab-content" id="anexos">
            <div class="anexos-section">
                <h4><i data-lucide="paperclip"></i> Documentos Anexados</h4>
                <div class="anexos-list">
                    <?php foreach ($anexos as $anexo): ?>
                    <div class="anexo-item">
                        <div class="anexo-icon">
                            <i data-lucide="file"></i>
                        </div>
                        <div class="anexo-info">
                            <div class="anexo-nome">
                                <?= htmlspecialchars($anexo['nome_arquivo']) ?>
                            </div>
                            <div class="anexo-meta">
                                Tipo: <?= htmlspecialchars($anexo['tipo_arquivo']) ?> |
                                Enviado por: <?= htmlspecialchars($anexo['criado_por_nome'] ?: 'Sistema') ?> em
                                <?= $anexo['criado_em_formatado'] ?>
                            </div>
                            <?php if (!empty($anexo['descricao'])): ?>
                            <div class="anexo-descricao">
                                <?= nl2br(htmlspecialchars($anexo['descricao'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="anexo-actions">
                            <button class="btn-icon" onclick="downloadAnexo(<?= $anexo['id'] ?>)" title="Download">
                                <i data-lucide="download"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Rodapé com informações de controle -->
        <div class="info-footer">
            <div class="footer-info">
                <small>
                    <i data-lucide="user"></i> 
                    Criado por: <?= htmlspecialchars($contrato['criado_por_nome'] ?: 'Sistema') ?> em 
                    <?= $contrato['criado_em_formatado'] ?>
                </small>
                <?php if ($contrato['atualizado_em'] != $contrato['criado_em']): ?>
                <small>
                    <i data-lucide="edit-3"></i>
                    Última atualização: <?= $contrato['atualizado_em_formatado'] ?>
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Inicializar Lucide icons
lucide.createIcons();

// Sistema de abas
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabId = this.dataset.tab;
        
        // Remover classe active de todas as abas
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Ativar aba clicada
        this.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    });
});

// Função para download de anexos
function downloadAnexo(anexoId) {
    window.open(`api/download_anexo.php?id=${anexoId}`, '_blank');
}
</script>

<style>
/* ========================================
   DETALHES DO CONTRATO - TEMA VERMELHO
======================================== */

.contrato-detalhes {
    max-width: 100%;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.error-message {
    padding: 20px;
    background: #fee;
    color: #c33;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 4px solid #dc2626;
}

/* Tabs */
.tabs-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.tabs-nav {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.tab-btn {
    flex: 1;
    padding: 16px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
    font-weight: 500;
    color: #6c757d;
}

.tab-btn:hover {
    background: #e9ecef;
    color: #495057;
}

.tab-btn.active {
    background: white;
    border-bottom: 3px solid #dc2626;
    color: #dc2626;
}

.tab-content {
    display: none;
    padding: 32px;
}

.tab-content.active {
    display: block;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
    margin-bottom: 20px;
}

.info-section {
    background: #f8f9fa;
    padding: 24px;
    border-radius: 12px;
    border-left: 4px solid #dc2626;
}

.info-section.full-width {
    grid-column: 1 / -1;
}

.info-section h4 {
    margin: 0 0 20px 0;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.info-section h4 i {
    color: #dc2626;
    width: 18px;
    height: 18px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    align-items: flex-start;
    gap: 16px;
}

.info-row label {
    font-weight: 600;
    color: #6c757d;
    min-width: 140px;
    font-size: 14px;
}

.info-row span {
    flex: 1;
    text-align: right;
    color: #495057;
}

.highlight {
    font-weight: 600;
    color: #dc2626 !important;
}

/* Content Blocks */
.objeto-content,
.finalidade-content,
.observacoes-content {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #dc2626;
    line-height: 1.6;
    color: #495057;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-ativo {
    background: #d4edda;
    color: #155724;
}

.status-encerrado {
    background: #f8d7da;
    color: #721c24;
}

.status-suspenso {
    background: #fff3cd;
    color: #856404;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-blue {
    background: #cce5ff;
    color: #0066cc;
}

/* Valores Grid */
.valores-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.valor-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s ease;
}

.valor-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.valor-card.inicial {
    border-left: 4px solid #6c757d;
}

.valor-card.atual {
    border-left: 4px solid #dc2626;
}

.valor-card.economia {
    border-left: 4px solid #28a745;
}

.valor-card.prazo.normal {
    border-left: 4px solid #007bff;
}

.valor-card.prazo.warning {
    border-left: 4px solid #ffc107;
    background: #fff9e6;
}

.valor-icon {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.valor-card.atual .valor-icon {
    background: #fef2f2;
    color: #dc2626;
}

.valor-card.economia .valor-icon {
    background: #f0f9f0;
    color: #28a745;
}

.valor-card.prazo.warning .valor-icon {
    background: #fff9e6;
    color: #ffc107;
}

.valor-content {
    flex: 1;
}

.valor-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.valor-amount {
    font-size: 18px;
    font-weight: 700;
    color: #495057;
    line-height: 1.2;
}

.valor-percent {
    font-size: 12px;
    font-weight: 600;
    margin-top: 4px;
}

.valor-percent.positive {
    color: #dc3545;
}

.valor-percent.negative {
    color: #28a745;
}

.valor-percent.warning {
    color: #ffc107;
}

/* Vigência Section */
.vigencia-section {
    margin-top: 32px;
}

.vigencia-section h4 {
    margin-bottom: 20px;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
}

.cronograma-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    background: #f8f9fa;
    padding: 24px;
    border-radius: 12px;
    border-left: 4px solid #dc2626;
}

.cronograma-item {
    text-align: center;
}

.cronograma-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.cronograma-value {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    padding: 8px 12px;
    background: white;
    border-radius: 6px;
}

.cronograma-value.warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 24px;
    padding-left: 24px;
}

.timeline-marker {
    position: absolute;
    left: -19px;
    width: 32px;
    height: 32px;
    background: #dc2626;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 16px 20px;
    border-radius: 8px;
    border-left: 4px solid #dc2626;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.timeline-action {
    font-weight: 600;
    color: #495057;
    text-transform: capitalize;
}

.timeline-date {
    font-size: 12px;
    color: #6c757d;
}

.timeline-user {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 8px;
}

.timeline-details {
    font-size: 14px;
    color: #495057;
    line-height: 1.5;
}

/* Anexos */
.anexos-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.anexo-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
    border-left: 4px solid #dc2626;
}

.anexo-icon {
    background: #dc2626;
    color: white;
    padding: 12px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.anexo-info {
    flex: 1;
}

.anexo-nome {
    font-weight: 600;
    color: #495057;
    margin-bottom: 6px;
    font-size: 14px;
}

.anexo-meta {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 8px;
}

.anexo-descricao {
    font-size: 13px;
    color: #495057;
    line-height: 1.5;
}

.anexo-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    background: #dc2626;
    color: white;
    border: none;
    padding: 8px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background: #b91c1c;
    transform: scale(1.05);
}

/* Footer */
.info-footer {
    margin-top: 32px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.footer-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.footer-info small {
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.footer-info small i {
    width: 12px;
    height: 12px;
}

/* Responsividade */
@media (max-width: 768px) {
    .tabs-nav {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        min-width: 50%;
        font-size: 14px;
        padding: 12px 16px;
    }
    
    .tab-content {
        padding: 20px;
    }
    
    .valores-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .info-row {
        flex-direction: column;
        gap: 6px;
    }
    
    .info-row label {
        min-width: auto;
    }
    
    .info-row span {
        text-align: left;
    }
    
    .cronograma-grid {
        grid-template-columns: 1fr;
        padding: 16px;
    }
    
    .timeline {
        padding-left: 20px;
    }
    
    .timeline-marker {
        width: 24px;
        height: 24px;
        left: -12px;
    }
    
    .timeline-item {
        padding-left: 16px;
    }
    
    .timeline-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
}

@media (max-width: 480px) {
    .tab-btn {
        font-size: 12px;
        padding: 10px 12px;
        gap: 4px;
    }
    
    .info-section {
        padding: 16px;
    }
    
    .valor-card {
        padding: 16px;
    }
    
    .anexo-item {
        padding: 16px;
    }
    
    .anexo-item {
        flex-direction: column;
        text-align: center;
    }
}
</style>