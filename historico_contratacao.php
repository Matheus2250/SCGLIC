<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

if (!isset($_GET['numero'])) {
    echo '<div class="erro">Número da contratação não fornecido</div>';
    exit;
}

$pdo = conectarDB();
$numero_contratacao = $_GET['numero'];

// Buscar histórico
$sql = "SELECT h.*, u.nome as usuario_nome, i.data_importacao 
        FROM pca_historico h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        LEFT JOIN pca_importacoes i ON h.importacao_id = i.id
        WHERE h.numero_contratacao = ?
        ORDER BY h.data_mudanca DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$numero_contratacao]);
$historico = $stmt->fetchAll();

// Buscar tempo em cada estado
$sql_estados = "SELECT * FROM pca_estados_tempo 
                WHERE numero_contratacao = ? 
                ORDER BY data_inicio DESC";
$stmt_estados = $pdo->prepare($sql_estados);
$stmt_estados->execute([$numero_contratacao]);
$estados = $stmt_estados->fetchAll();
?>

<div style="padding: 20px;">
    <h3>Histórico da Contratação <?php echo htmlspecialchars($numero_contratacao); ?></h3>
    
    <?php if (!empty($estados)): ?>
    <h4 style="margin-top: 20px;">Tempo em cada Estado</h4>
    <table style="width: 100%; font-size: 13px; margin-bottom: 20px;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="padding: 8px;">Situação</th>
                <th style="padding: 8px;">Data Início</th>
                <th style="padding: 8px;">Data Fim</th>
                <th style="padding: 8px;">Dias no Estado</th>
                <th style="padding: 8px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($estados as $estado): ?>
            <tr>
                <td style="padding: 8px;"><?php echo htmlspecialchars($estado['situacao_execucao']); ?></td>
                <td style="padding: 8px;"><?php echo formatarData($estado['data_inicio']); ?></td>
                <td style="padding: 8px;"><?php echo $estado['data_fim'] ? formatarData($estado['data_fim']) : '-'; ?></td>
                <td style="padding: 8px; font-weight: bold;">
                    <?php 
                    if ($estado['dias_no_estado']) {
                        echo $estado['dias_no_estado'] . ' dias';
                    } elseif ($estado['ativo']) {
                        $dias = (new DateTime())->diff(new DateTime($estado['data_inicio']))->days;
                        echo $dias . ' dias (em andamento)';
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td style="padding: 8px;">
                    <?php if ($estado['ativo']): ?>
                        <span style="color: #28a745;">Atual</span>
                    <?php else: ?>
                        <span style="color: #6c757d;">Finalizado</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <?php if (!empty($historico)): ?>
    <h4>Histórico de Mudanças</h4>
    <table style="width: 100%; font-size: 13px;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="padding: 8px;">Data</th>
                <th style="padding: 8px;">Campo</th>
                <th style="padding: 8px;">Valor Anterior</th>
                <th style="padding: 8px;">Valor Novo</th>
                <th style="padding: 8px;">Usuário</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historico as $item): ?>
            <tr>
                <td style="padding: 8px;"><?php echo date('d/m/Y H:i', strtotime($item['data_mudanca'])); ?></td>
                <td style="padding: 8px;">
                    <?php 
                    $campos = [
                        'situacao_execucao' => 'Situação',
                        'valor_total_contratacao' => 'Valor Total',
                        'status_contratacao' => 'Status'
                    ];
                    echo $campos[$item['campo_alterado']] ?? $item['campo_alterado'];
                    ?>
                </td>
                <td style="padding: 8px;"><?php echo htmlspecialchars($item['valor_anterior']); ?></td>
                <td style="padding: 8px; font-weight: bold;"><?php echo htmlspecialchars($item['valor_novo']); ?></td>
                <td style="padding: 8px;"><?php echo htmlspecialchars($item['usuario_nome']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="color: #6c757d;">Nenhuma mudança registrada ainda.</p>
    <?php endif; ?>
</div>