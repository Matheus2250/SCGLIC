<?php
/**
 * Reprocessar dados PNCP com mapeamento corrigido
 */

require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

if ($_POST['acao'] ?? '' === 'reprocessar') {
    try {
        // Limpar dados antigos
        $pdo->beginTransaction();
        
        $sql1 = "DELETE FROM pca_pncp WHERE ano_pca = 2026";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute();
        
        $sql2 = "DELETE FROM pca_pncp_sincronizacoes WHERE ano_pca = 2026";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute();
        
        $pdo->commit();
        
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<strong>✅ Dados limpos!</strong> Agora faça uma nova sincronização.";
        echo "</div>";
        
        // Redirecionar para sincronização
        echo "<script>";
        echo "setTimeout(function() {";
        echo "  window.location.href = 'teste_sync_pncp.php';";
        echo "}, 2000);";
        echo "</script>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<strong>❌ Erro:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
}

// Status atual
$count = $pdo->query("SELECT COUNT(*) FROM pca_pncp WHERE ano_pca = 2026")->fetchColumn();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Reprocessar PNCP</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .info { background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🔄 Reprocessar Dados PNCP</h1>
    
    <div class="info">
        <strong>📊 Status Atual:</strong><br>
        • Registros PNCP 2026: <strong><?php echo number_format($count); ?></strong><br>
        • Problema identificado: <strong>Mapeamento incorreto de campos</strong>
    </div>
    
    <div class="warning">
        <strong>🔧 Correções Aplicadas:</strong><br>
        ✅ Mapeamento correto dos 20 campos do CSV do PNCP<br>
        ✅ Processamento adequado de valores monetários brasileiros<br>
        ✅ Tratamento de datas no formato dd/mm/yyyy<br>
        ✅ Validação melhorada de campos obrigatórios
    </div>
    
    <?php if ($count > 0): ?>
    <div class="warning">
        <strong>⚠️ Ação Necessária:</strong><br>
        Os dados atuais estão com formatação incorreta (categorias como "0000;0", valores zerados).<br>
        É necessário limpar e reprocessar com o mapeamento corrigido.
    </div>
    
    <form method="POST" onsubmit="return confirm('Limpar dados e reprocessar? Esta ação não pode ser desfeita.')">
        <input type="hidden" name="acao" value="reprocessar">
        <button type="submit" class="btn btn-danger">🗑️ Limpar e Reprocessar</button>
    </form>
    <?php else: ?>
    <div class="info">
        <strong>✅ Pronto para sincronização!</strong><br>
        Os dados foram limpos. Agora execute uma nova sincronização.
    </div>
    
    <a href="teste_sync_pncp.php" class="btn btn-primary">🔄 Fazer Nova Sincronização</a>
    <?php endif; ?>
    
    <div style="margin-top: 40px; padding: 20px; background: #e7f3ff; border-radius: 8px;">
        <strong>📋 O que será corrigido:</strong>
        <ul>
            <li>✅ <strong>Categoria:</strong> "Material de Consumo" em vez de "0000;0"</li>
            <li>✅ <strong>Descrição:</strong> Nome real da contratação em vez de "Item PNCP"</li>
            <li>✅ <strong>Valores:</strong> Valores reais em R$ em vez de 0,00</li>
            <li>✅ <strong>Unidade:</strong> Nome da unidade requisitante real</li>
            <li>✅ <strong>Código:</strong> Identificador único da contratação</li>
        </ul>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="debug_pncp.php" class="btn" style="background: #6c757d; color: white;">🔍 Debug</a>
        <a href="dashboard.php?secao=pncp-integration" class="btn" style="background: #28a745; color: white;">📊 Dashboard</a>
    </div>
</body>
</html>