<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

header('Content-Type: application/json');

$pdo = conectarDB();
$response = ['success' => false, 'message' => ''];

try {
    $acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
    
    switch ($acao) {
        case 'adicionar':
            // Validar dados obrigatórios
            $required = ['numero_dfd', 'demanda', 'evento_risco', 'causa_risco', 'consequencia_risco', 'probabilidade', 'impacto'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            
            // Inserir risco
            $sql = "INSERT INTO pca_riscos (
                numero_dfd, demanda, evento_risco, causa_risco, consequencia_risco,
                probabilidade, impacto, acao_preventiva, responsavel_preventiva,
                acao_contingencia, responsavel_contingencia, mes_relatorio, usuario_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['numero_dfd'],
                $_POST['demanda'],
                $_POST['evento_risco'],
                $_POST['causa_risco'],
                $_POST['consequencia_risco'],
                intval($_POST['probabilidade']),
                intval($_POST['impacto']),
                $_POST['acao_preventiva'] ?? '',
                $_POST['responsavel_preventiva'] ?? '',
                $_POST['acao_contingencia'] ?? '',
                $_POST['responsavel_contingencia'] ?? '',
                $_POST['mes_relatorio'],
                $_SESSION['usuario_id']
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Risco cadastrado com sucesso!';
            registrarLog('ADICIONAR_RISCO', "Adicionou risco para DFD: {$_POST['numero_dfd']}", 'pca_riscos', $pdo->lastInsertId());
            break;
            
        case 'editar':
            if (empty($_POST['risco_id'])) {
                throw new Exception("ID do risco não fornecido");
            }
            
            // Atualizar risco
            $sql = "UPDATE pca_riscos SET 
                numero_dfd = ?, demanda = ?, evento_risco = ?, causa_risco = ?, 
                consequencia_risco = ?, probabilidade = ?, impacto = ?, 
                acao_preventiva = ?, responsavel_preventiva = ?,
                acao_contingencia = ?, responsavel_contingencia = ?
                WHERE id = ? AND usuario_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['numero_dfd'],
                $_POST['demanda'],
                $_POST['evento_risco'],
                $_POST['causa_risco'],
                $_POST['consequencia_risco'],
                intval($_POST['probabilidade']),
                intval($_POST['impacto']),
                $_POST['acao_preventiva'] ?? '',
                $_POST['responsavel_preventiva'] ?? '',
                $_POST['acao_contingencia'] ?? '',
                $_POST['responsavel_contingencia'] ?? '',
                $_POST['risco_id'],
                $_SESSION['usuario_id']
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Risco atualizado com sucesso!';
            registrarLog('EDITAR_RISCO', "Editou risco ID: {$_POST['risco_id']}", 'pca_riscos', $_POST['risco_id']);
            break;
            
        case 'excluir':
            if (empty($_GET['id'])) {
                throw new Exception("ID do risco não fornecido");
            }
            
            $sql = "DELETE FROM pca_riscos WHERE id = ? AND usuario_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_GET['id'], $_SESSION['usuario_id']]);
            
            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'Risco excluído com sucesso!';
                registrarLog('EXCLUIR_RISCO', "Excluiu risco ID: {$_GET['id']}", 'pca_riscos', $_GET['id']);
                
                // Redirecionar para a página de riscos
                header('Location: relatorio_riscos.php');
                exit;
            } else {
                throw new Exception("Risco não encontrado ou sem permissão para excluir");
            }
            break;
            
        case 'buscar':
            if (empty($_GET['id'])) {
                throw new Exception("ID do risco não fornecido");
            }
            
            $sql = "SELECT * FROM pca_riscos WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_GET['id']]);
            $risco = $stmt->fetch();
            
            if ($risco) {
                $response['success'] = true;
                $response['data'] = $risco;
            } else {
                throw new Exception("Risco não encontrado");
            }
            break;
            
        default:
            throw new Exception("Ação inválida");
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);