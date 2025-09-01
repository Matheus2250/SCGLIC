<?php
/**
 * API para buscar dados de uma qualificação
 * Retorna os dados em formato JSON para preenchimento do formulário de licitação
 */

// Configurar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../functions.php';

// Verificar se usuário está logado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Comentado temporariamente para debug - em produção, descomentar
// if (!isset($_SESSION['usuario_id'])) {
//     header('Content-Type: application/json');
//     echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
//     exit;
// }

// Verificar se ID foi fornecido
if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID da qualificação não fornecido']);
    exit;
}

$qualificacao_id = intval($_GET['id']);

try {
    $pdo = conectarDB();
    
    // Buscar dados da qualificação (removendo restrição de status para debug)
    $sql = "SELECT 
            q.id,
            q.nup,
            q.area_demandante,
            q.responsavel,
            q.modalidade,
            q.objeto,
            q.palavras_chave,
            q.valor_estimado,
            q.status,
            q.observacoes,
            q.numero_contratacao,
            q.criado_em,
            q.atualizado_em
        FROM qualificacoes q
        WHERE q.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$qualificacao_id]);
    $qualificacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$qualificacao) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Qualificação não encontrada']);
        exit;
    }
    
    // Verificar se está concluída
    if ($qualificacao['status'] !== 'CONCLUÍDO') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Qualificação não está concluída. Status atual: ' . $qualificacao['status']]);
        exit;
    }
    
    // Verificar se já existe licitação para este NUP (apenas para informação)
    $check_sql = "SELECT COUNT(*) as total FROM licitacoes WHERE TRIM(nup) = TRIM(?)";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$qualificacao['nup']]);
    $existe_licitacao = $check_stmt->fetch()['total'] > 0;
    
    // Retornar dados da qualificação
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'ja_licitada' => $existe_licitacao,
        'qualificacao' => [
            'id' => $qualificacao['id'],
            'nup' => $qualificacao['nup'],
            'area_demandante' => $qualificacao['area_demandante'],
            'responsavel' => $qualificacao['responsavel'],
            'modalidade' => $qualificacao['modalidade'],
            'objeto' => $qualificacao['objeto'],
            'palavras_chave' => $qualificacao['palavras_chave'],
            'valor_estimado' => $qualificacao['valor_estimado'],
            'status' => $qualificacao['status'],
            'observacoes' => $qualificacao['observacoes'],
            'numero_contratacao' => $qualificacao['numero_contratacao']
        ]
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar qualificação: ' . $e->getMessage()
    ]);
}