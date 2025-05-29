<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

if (!isset($_GET['numero'])) {
    echo '<div class="erro">Número não fornecido</div>';
    exit;
}

$pdo = conectarDB();
$numero_recebido = $_GET['numero'];

// Buscar o número da contratação baseado no DFD
$sql_busca = "SELECT DISTINCT numero_contratacao FROM pca_dados WHERE numero_dfd = ? LIMIT 1";
$stmt_busca = $pdo->prepare($sql_busca);
$stmt_busca->execute([$numero_recebido]);
$resultado = $stmt_busca->fetch();

if ($resultado) {
    $numero_contratacao = $resultado['numero_contratacao'];
} else {
    // Se não encontrou pelo DFD, assume que já é número de contratação
    $numero_contratacao = $numero_recebido;
}

// Resto permanece igual (consultas do histórico e estados)

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
<h4 style="margin-top: 20px;">⏱️ Tempo em cada Estado</h4>
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    
    <?php 
    $total_dias = 0;
    foreach ($estados as $index => $estado): 
        $dias_no_estado = 0;
        
        if ($estado['ativo']) {
            // Estado atual - calcular dias até hoje
            $dias_no_estado = (new DateTime())->diff(new DateTime($estado['data_inicio']))->days;
            $status_classe = 'atual';
            $status_texto = 'Estado Atual';
        } else {
            // Estado finalizado
            $dias_no_estado = $estado['dias_no_estado'];
            $status_classe = 'finalizado';
            $status_texto = 'Finalizado';
            $total_dias += $dias_no_estado;
        }
    ?>
    
    <div style="display: flex; align-items: center; margin-bottom: 15px; padding: 15px; background: white; border-radius: 6px; border-left: 4px solid <?php echo $estado['ativo'] ? '#28a745' : '#6c757d'; ?>;">
        
        <div style="flex: 1;">
            <h5 style="margin: 0; color: #2c3e50;">
                <?php echo htmlspecialchars($estado['situacao_execucao']); ?>
                <span style="font-size: 12px; color: <?php echo $estado['ativo'] ? '#28a745' : '#6c757d'; ?>; font-weight: normal;">
                    (<?php echo $status_texto; ?>)
                </span>
            </h5>
            <small style="color: #666;">
                <strong>Início:</strong> <?php echo formatarData($estado['data_inicio']); ?>
                <?php if (!$estado['ativo']): ?>
                    | <strong>Fim:</strong> <?php echo formatarData($estado['data_fim']); ?>
                <?php endif; ?>
            </small>
        </div>
        
        <div style="text-align: right;">
            <span style="font-size: 24px; font-weight: bold; color: <?php echo $estado['ativo'] ? '#28a745' : '#2c3e50'; ?>;">
                <?php echo $dias_no_estado; ?>
            </span>
            <br>
            <small style="color: #666;">
                <?php echo $dias_no_estado == 1 ? 'dia' : 'dias'; ?>
                <?php echo $estado['ativo'] ? '(em andamento)' : ''; ?>
            </small>
        </div>
        
    </div>
    <?php endforeach; ?>
    
    <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #dee2e6;">
        <span style="font-size: 18px; font-weight: bold; color: #2c3e50;">
            Total de dias finalizados: <?php echo $total_dias; ?> dias
        </span>
    </div>
    
</div>
<?php endif; ?>

<h4>📋 Histórico de Mudanças</h4>
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
    <?php if (!empty($historico)): ?>
        <?php foreach ($historico as $item): ?>
        <div style="display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #e9ecef;">
            <div style="width: 120px; font-size: 12px; color: #666;">
                <?php echo date('d/m/Y H:i', strtotime($item['data_mudanca'])); ?>
            </div>
            <div style="flex: 1; margin-left: 15px;">
                <strong><?php echo htmlspecialchars($item['valor_anterior']); ?></strong>
                <span style="color: #666; margin: 0 10px;">→</span>
                <strong style="color: #28a745;"><?php echo htmlspecialchars($item['valor_novo']); ?></strong>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; color: #666; margin: 20px 0;">
            Nenhuma mudança registrada ainda.
        </p>
    <?php endif; ?>
</div>
