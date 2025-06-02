<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID não fornecido');
    }
    
    $id = intval($_GET['id']);
    $pdo = conectarDB();
    
    $sql = "SELECT l.*, u.nome as usuario_nome 
            FROM licitacoes l 
            LEFT JOIN usuarios u ON l.usuario_id = u.id 
            WHERE l.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $licitacao = $stmt->fetch();
    
    if ($licitacao) {
        $response['success'] = true;
        $response['data'] = $licitacao;
    } else {
        throw new Exception('Licitação não encontrada');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);