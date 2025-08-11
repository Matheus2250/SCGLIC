<?php
/**
 * Script para limpar dados do PNCP e refazer sincronização
 */

require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

if ($_POST['acao'] ?? '' === 'limpar') {
    try {
        $pdo->beginTransaction();
        
        // Limpar dados do PNCP
        $sql1 = "DELETE FROM pca_pncp WHERE ano_pca = 2026";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute();
        $registros_removidos = $stmt1->rowCount();
        
        // Limpar histórico de sincronizações
        $sql2 = "DELETE FROM pca_pncp_sincronizacoes WHERE ano_pca = 2026";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute();
        $sync_removidas = $stmt2->rowCount();
        
        $pdo->commit();
        
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
        echo "<strong>✅ Limpeza concluída!</strong><br>";
        echo "📊 Registros PNCP removidos: {$registros_removidos}<br>";
        echo "🗂️ Sincronizações removidas: {$sync_removidas}";
        echo "</div>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
        echo "<strong>❌ Erro na limpeza:</strong><br>";
        echo htmlspecialchars($e->getMessage());
        echo "</div>";
    }
}

// Verificar estado atual
$count_pncp = $pdo->query("SELECT COUNT(*) FROM pca_pncp WHERE ano_pca = 2026")->fetchColumn();
$count_sync = $pdo->query("SELECT COUNT(*) FROM pca_pncp_sincronizacoes WHERE ano_pca = 2026")->fetchColumn();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Limpar Dados PNCP</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Limpar Dados PNCP</h1>
        
        <div class="info">
            <strong>📊 Estado Atual:</strong><br>
            • Registros PNCP 2026: <strong><?php echo number_format($count_pncp); ?></strong><br>
            • Sincronizações 2026: <strong><?php echo $count_sync; ?></strong>
        </div>
        
        <?php if ($count_pncp > 0): ?>
        <div class="warning">
            <strong>⚠️ Atenção:</strong><br>
            Esta ação irá remover TODOS os dados do PNCP 2026 e histórico de sincronizações.<br>
            Isso permitirá que a próxima sincronização processe os dados corretamente com o mapeamento atualizado.
        </div>
        
        <form method="POST" onsubmit="return confirm('Tem certeza que deseja limpar todos os dados PNCP 2026? Esta ação não pode ser desfeita.')">
            <input type="hidden" name="acao" value="limpar">
            <button type="submit" class="btn btn-danger">🗑️ Limpar Dados PNCP 2026</button>
        </form>
        <?php else: ?>
        <div class="info">
            <strong>✅ Dados já estão limpos!</strong><br>
            Não há dados PNCP para remover.
        </div>
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        
        <div>
            <a href="teste_sync_pncp.php" class="btn btn-success">🔄 Ir para Sincronização</a>
            <a href="dashboard.php?secao=pncp-integration" class="btn" style="background: #007bff; color: white; margin-left: 10px;">📊 Voltar ao Dashboard</a>
        </div>
    </div>
</body>
</html>