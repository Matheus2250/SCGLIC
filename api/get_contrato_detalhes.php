<?php
/**
 * API para buscar detalhes completos de um contrato
 * Inclui informações de aditivos, empenhos e pagamentos
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Verificar login
if (!verificarLogin()) {
    http_response_code(401);
    echo 'Não autorizado';
    exit;
}

$contratoId = intval($_GET['id'] ?? 0);

if (!$contratoId) {
    echo '<div class="error">ID do contrato não fornecido</div>';
    exit;
}

try {
    // Buscar dados principais do contrato
    $stmt = $conn->prepare("
        SELECT c.*, 
               u.nome as sincronizado_por_nome
        FROM contratos c
        LEFT JOIN usuarios u ON c.sincronizado_por = u.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $contratoId);
    $stmt->execute();
    $contrato = $stmt->get_result()->fetch_assoc();
    
    if (!$contrato) {
        echo '<div class="error">Contrato não encontrado</div>';
        exit;
    }
    
    // Buscar aditivos
    $stmt = $conn->prepare("
        SELECT * FROM contratos_aditivos 
        WHERE contrato_id = ? 
        ORDER BY data_assinatura DESC
    ");
    $stmt->bind_param("i", $contratoId);
    $stmt->execute();
    $aditivos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Buscar empenhos
    $stmt = $conn->prepare("
        SELECT * FROM contratos_empenhos 
        WHERE contrato_id = ? 
        ORDER BY data_empenho DESC
    ");
    $stmt->bind_param("i", $contratoId);
    $stmt->execute();
    $empenhos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Buscar pagamentos
    $stmt = $conn->prepare("
        SELECT p.*, e.numero_empenho
        FROM contratos_pagamentos p
        LEFT JOIN contratos_empenhos e ON p.empenho_id = e.id
        WHERE p.contrato_id = ? 
        ORDER BY p.data_pagamento DESC
    ");
    $stmt->bind_param("i", $contratoId);
    $stmt->execute();
    $pagamentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Buscar documentos
    // Tentar buscar documentos com tratamento de erro
    try {
        $stmt = $conn->prepare("
            SELECT d.*, u.nome as criado_por_nome
            FROM contratos_documentos d
            LEFT JOIN usuarios u ON d.criado_por = u.id
            WHERE d.contrato_id = ? 
            ORDER BY COALESCE(d.criado_em, d.id) DESC
        ");
        $stmt->bind_param("i", $contratoId);
        $stmt->execute();
        $documentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        // Tabela pode não existir ou campo pode não existir
        $documentos = [];
    }
    
} catch (Exception $e) {
    echo '<div class="error">Erro ao carregar dados: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

?>

<div class="contrato-detalhes">
    <!-- Abas -->
    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="informacoes">
                <i data-lucide="info"></i> Informações
            </button>
            <button class="tab-btn" data-tab="financeiro">
                <i data-lucide="dollar-sign"></i> Financeiro
            </button>
            <button class="tab-btn" data-tab="aditivos">
                <i data-lucide="plus-circle"></i> Aditivos (<?= count($aditivos) ?>)
            </button>
            <button class="tab-btn" data-tab="documentos">
                <i data-lucide="paperclip"></i> Documentos (<?= count($documentos) ?>)
            </button>
        </div>
        
        <!-- Aba Informações -->
        <div class="tab-content active" id="informacoes">
            <div class="info-grid">
                <div class="info-section">
                    <h4><i data-lucide="file-text"></i> Dados do Contrato</h4>
                    <div class="info-row">
                        <label>Número:</label>
                        <span><?= htmlspecialchars($contrato['numero_contrato']) ?></span>
                    </div>
                    <div class="info-row">
                        <label>UASG:</label>
                        <span><?= htmlspecialchars($contrato['uasg']) ?></span>
                    </div>
                    <div class="info-row">
                        <label>Modalidade:</label>
                        <span><?= htmlspecialchars($contrato['modalidade']) ?></span>
                    </div>
                    <div class="info-row">
                        <label>Tipo:</label>
                        <span><?= htmlspecialchars($contrato['tipo_contrato']) ?></span>
                    </div>
                    <div class="info-row">
                        <label>Status:</label>
                        <span class="status-badge status-<?= $contrato['status_contrato'] ?>">
                            <?= ucfirst($contrato['status_contrato']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h4><i data-lucide="building"></i> Contratado</h4>
                    <div class="info-row">
                        <label>Nome:</label>
                        <span><?= htmlspecialchars($contrato['contratado_nome']) ?></span>
                    </div>
                    <div class="info-row">
                        <label>CNPJ:</label>
                        <span><?= formatarCNPJ($contrato['contratado_cnpj']) ?></span>
                    </div>
                    <div class="info-row">
                        <label>Tipo:</label>
                        <span><?= $contrato['contratado_tipo'] === 'PJ' ? 'Pessoa Jurídica' : 'Pessoa Física' ?></span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h4><i data-lucide="calendar"></i> Prazos</h4>
                    <div class="info-row">
                        <label>Assinatura:</label>
                        <span><?= $contrato['data_assinatura'] ? date('d/m/Y', strtotime($contrato['data_assinatura'])) : '-' ?></span>
                    </div>
                    <div class="info-row">
                        <label>Início Vigência:</label>
                        <span><?= $contrato['data_inicio_vigencia'] ? date('d/m/Y', strtotime($contrato['data_inicio_vigencia'])) : '-' ?></span>
                    </div>
                    <div class="info-row">
                        <label>Fim Vigência:</label>
                        <span><?= $contrato['data_fim_vigencia'] ? date('d/m/Y', strtotime($contrato['data_fim_vigencia'])) : '-' ?></span>
                    </div>
                    <div class="info-row">
                        <label>Publicação:</label>
                        <span><?= $contrato['data_publicacao'] ? date('d/m/Y', strtotime($contrato['data_publicacao'])) : '-' ?></span>
                    </div>
                </div>
                
                <div class="info-section full-width">
                    <h4><i data-lucide="file"></i> Objeto</h4>
                    <div class="objeto-content">
                        <?= nl2br(htmlspecialchars($contrato['objeto'])) ?>
                    </div>
                </div>
                
                <div class="info-section">
                    <h4><i data-lucide="link"></i> Processos</h4>
                    <div class="info-row">
                        <label>Processo:</label>
                        <span><?= htmlspecialchars($contrato['numero_processo']) ?: '-' ?></span>
                    </div>
                    <div class="info-row">
                        <label>SEI:</label>
                        <span><?= htmlspecialchars($contrato['numero_sei']) ?: '-' ?></span>
                    </div>
                    <?php if ($contrato['link_comprasnet']): ?>
                    <div class="info-row">
                        <label>Comprasnet:</label>
                        <a href="<?= htmlspecialchars($contrato['link_comprasnet']) ?>" target="_blank" class="link-external">
                            <i data-lucide="external-link"></i> Abrir no Comprasnet
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="info-section">
                    <h4><i data-lucide="clock"></i> Controle</h4>
                    <div class="info-row">
                        <label>Última Sincronização:</label>
                        <span><?= date('d/m/Y H:i', strtotime($contrato['ultima_sincronizacao'])) ?></span>
                    </div>
                    <div class="info-row">
                        <label>Sincronizado por:</label>
                        <span><?= htmlspecialchars($contrato['sincronizado_por_nome'] ?: 'Sistema') ?></span>
                    </div>
                    <div class="info-row">
                        <label>Criado em:</label>
                        <span><?= date('d/m/Y H:i', strtotime($contrato['criado_em'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Aba Financeiro -->
        <div class="tab-content" id="financeiro">
            <div class="financeiro-grid">
                <div class="valor-card total">
                    <div class="valor-icon">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div class="valor-content">
                        <div class="valor-label">Valor Total</div>
                        <div class="valor-amount">R$ <?= number_format($contrato['valor_total'], 2, ',', '.') ?></div>
                    </div>
                </div>
                
                <div class="valor-card empenhado">
                    <div class="valor-icon">
                        <i data-lucide="credit-card"></i>
                    </div>
                    <div class="valor-content">
                        <div class="valor-label">Valor Empenhado</div>
                        <div class="valor-amount">R$ <?= number_format($contrato['valor_empenhado'], 2, ',', '.') ?></div>
                        <div class="valor-percent">
                            <?= $contrato['valor_total'] > 0 ? number_format(($contrato['valor_empenhado'] / $contrato['valor_total']) * 100, 1) : 0 ?>%
                        </div>
                    </div>
                </div>
                
                <div class="valor-card pago">
                    <div class="valor-icon">
                        <i data-lucide="check-circle"></i>
                    </div>
                    <div class="valor-content">
                        <div class="valor-label">Valor Pago</div>
                        <div class="valor-amount">R$ <?= number_format($contrato['valor_pago'], 2, ',', '.') ?></div>
                        <div class="valor-percent">
                            <?= $contrato['valor_total'] > 0 ? number_format(($contrato['valor_pago'] / $contrato['valor_total']) * 100, 1) : 0 ?>%
                        </div>
                    </div>
                </div>
                
                <div class="valor-card disponivel">
                    <div class="valor-icon">
                        <i data-lucide="wallet"></i>
                    </div>
                    <div class="valor-content">
                        <div class="valor-label">Valor Disponível</div>
                        <div class="valor-amount">R$ <?= number_format($contrato['valor_disponivel'], 2, ',', '.') ?></div>
                        <div class="valor-percent">
                            <?= $contrato['valor_total'] > 0 ? number_format(($contrato['valor_disponivel'] / $contrato['valor_total']) * 100, 1) : 0 ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Empenhos -->
            <?php if (!empty($empenhos)): ?>
            <div class="empenhos-section">
                <h4><i data-lucide="credit-card"></i> Empenhos (<?= count($empenhos) ?>)</h4>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Data</th>
                                <th>Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empenhos as $empenho): ?>
                            <tr>
                                <td><?= htmlspecialchars($empenho['numero_empenho']) ?></td>
                                <td><?= htmlspecialchars($empenho['tipo_empenho']) ?></td>
                                <td>R$ <?= number_format($empenho['valor_empenho'], 2, ',', '.') ?></td>
                                <td><?= date('d/m/Y', strtotime($empenho['data_empenho'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= strtolower($empenho['situacao']) ?>">
                                        <?= htmlspecialchars($empenho['situacao']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Pagamentos -->
            <?php if (!empty($pagamentos)): ?>
            <div class="pagamentos-section">
                <h4><i data-lucide="check-circle"></i> Pagamentos (<?= count($pagamentos) ?>)</h4>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Empenho</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Data</th>
                                <th>Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagamentos as $pagamento): ?>
                            <tr>
                                <td><?= htmlspecialchars($pagamento['numero_documento']) ?></td>
                                <td><?= htmlspecialchars($pagamento['numero_empenho'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($pagamento['tipo_pagamento']) ?></td>
                                <td>R$ <?= number_format($pagamento['valor_pagamento'], 2, ',', '.') ?></td>
                                <td><?= date('d/m/Y', strtotime($pagamento['data_pagamento'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= strtolower($pagamento['situacao']) ?>">
                                        <?= htmlspecialchars($pagamento['situacao']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Aba Aditivos -->
        <div class="tab-content" id="aditivos">
            <?php if (empty($aditivos)): ?>
            <div class="empty-state">
                <i data-lucide="plus-circle"></i>
                <h4>Nenhum aditivo encontrado</h4>
                <p>Este contrato não possui aditivos registrados.</p>
            </div>
            <?php else: ?>
            <div class="aditivos-list">
                <?php foreach ($aditivos as $aditivo): ?>
                <div class="aditivo-card">
                    <div class="aditivo-header">
                        <div class="aditivo-numero">
                            <i data-lucide="plus-circle"></i>
                            Aditivo <?= htmlspecialchars($aditivo['numero_aditivo']) ?>
                        </div>
                        <div class="aditivo-tipo">
                            <span class="badge badge-<?= $aditivo['tipo_aditivo'] ?>">
                                <?= ucfirst($aditivo['tipo_aditivo']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="aditivo-content">
                        <?php if ($aditivo['objeto_aditivo']): ?>
                        <div class="aditivo-objeto">
                            <strong>Objeto:</strong> <?= nl2br(htmlspecialchars($aditivo['objeto_aditivo'])) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="aditivo-info">
                            <?php if ($aditivo['valor_aditivo'] > 0): ?>
                            <div class="info-item">
                                <label>Valor:</label>
                                <span>R$ <?= number_format($aditivo['valor_aditivo'], 2, ',', '.') ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                <label>Assinatura:</label>
                                <span><?= date('d/m/Y', strtotime($aditivo['data_assinatura'])) ?></span>
                            </div>
                            
                            <div class="info-item">
                                <label>Vigência:</label>
                                <span>
                                    <?= date('d/m/Y', strtotime($aditivo['data_inicio_vigencia'])) ?> - 
                                    <?= date('d/m/Y', strtotime($aditivo['data_fim_vigencia'])) ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <label>Situação:</label>
                                <span class="badge badge-<?= strtolower($aditivo['situacao']) ?>">
                                    <?= htmlspecialchars($aditivo['situacao']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Aba Documentos -->
        <div class="tab-content" id="documentos">
            <?php if (empty($documentos)): ?>
            <div class="empty-state">
                <i data-lucide="paperclip"></i>
                <h4>Nenhum documento encontrado</h4>
                <p>Este contrato não possui documentos anexados.</p>
                <button class="btn btn-primary" onclick="uploadDocumento(<?= $contratoId ?>)">
                    <i data-lucide="upload"></i> Anexar Documento
                </button>
            </div>
            <?php else: ?>
            <div class="documentos-list">
                <div class="documentos-header">
                    <h4>Documentos Anexados</h4>
                    <button class="btn btn-primary" onclick="uploadDocumento(<?= $contratoId ?>)">
                        <i data-lucide="upload"></i> Anexar Documento
                    </button>
                </div>
                
                <?php foreach ($documentos as $documento): ?>
                <div class="documento-item">
                    <div class="documento-icon">
                        <i data-lucide="file"></i>
                    </div>
                    <div class="documento-info">
                        <div class="documento-nome">
                            <?= htmlspecialchars($documento['nome_documento']) ?>
                        </div>
                        <div class="documento-meta">
                            Tipo: <?= htmlspecialchars($documento['tipo_documento']) ?> |
                            Tamanho: <?= formatarTamanho($documento['tamanho_arquivo']) ?> |
                            Criado por: <?= htmlspecialchars($documento['criado_por_nome']) ?> em
                            <?= date('d/m/Y H:i', strtotime($documento['criado_em'])) ?>
                        </div>
                        <?php if ($documento['descricao']): ?>
                        <div class="documento-descricao">
                            <?= nl2br(htmlspecialchars($documento['descricao'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="documento-actions">
                        <button class="btn-icon" onclick="downloadDocumento(<?= $documento['id'] ?>)" title="Download">
                            <i data-lucide="download"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
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

// Funções para upload e download de documentos
function uploadDocumento(contratoId) {
    alert('Funcionalidade de upload em desenvolvimento');
}

function downloadDocumento(documentoId) {
    window.open(`api/download_documento.php?id=${documentoId}`, '_blank');
}
</script>

<style>
.contrato-detalhes {
    max-width: 100%;
}

.tabs-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.tabs-nav {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.tab-btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.tab-btn:hover {
    background: #e9ecef;
}

.tab-btn.active {
    background: white;
    border-bottom: 2px solid #007bff;
    color: #007bff;
}

.tab-content {
    display: none;
    padding: 24px;
}

.tab-content.active {
    display: block;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
}

.info-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.info-section.full-width {
    grid-column: 1 / -1;
}

.info-section h4 {
    margin: 0 0 16px 0;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    align-items-start;
}

.info-row label {
    font-weight: 600;
    color: #6c757d;
    min-width: 120px;
}

.objeto-content {
    background: white;
    padding: 16px;
    border-radius: 4px;
    border-left: 4px solid #007bff;
    line-height: 1.6;
}

.financeiro-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.valor-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.valor-card.total {
    border-left: 4px solid #6c757d;
}

.valor-card.empenhado {
    border-left: 4px solid #007bff;
}

.valor-card.pago {
    border-left: 4px solid #28a745;
}

.valor-card.disponivel {
    border-left: 4px solid #ffc107;
}

.valor-icon {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 50%;
}

.valor-amount {
    font-size: 1.5em;
    font-weight: 600;
    color: #495057;
}

.valor-percent {
    font-size: 0.9em;
    color: #6c757d;
}

.aditivos-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.aditivo-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    border-left: 4px solid #007bff;
}

.aditivo-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.aditivo-numero {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #495057;
}

.aditivo-objeto {
    margin-bottom: 16px;
    padding: 12px;
    background: white;
    border-radius: 4px;
}

.aditivo-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-item label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.9em;
}

.documentos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.documentos-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.documento-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
}

.documento-icon {
    background: #007bff;
    color: white;
    padding: 12px;
    border-radius: 4px;
}

.documento-info {
    flex: 1;
}

.documento-nome {
    font-weight: 600;
    color: #495057;
    margin-bottom: 4px;
}

.documento-meta {
    font-size: 0.9em;
    color: #6c757d;
    margin-bottom: 8px;
}

.documento-descricao {
    font-size: 0.9em;
    color: #495057;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .tabs-nav {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        min-width: 50%;
    }
    
    .financeiro-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .info-row {
        flex-direction: column;
        gap: 4px;
    }
    
    .info-row label {
        min-width: auto;
    }
}
</style>

<?php
function formatarTamanho($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>