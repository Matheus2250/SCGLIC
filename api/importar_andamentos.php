<?php
/**
 * API para importação de andamentos de processos
 * Versão integrada ao sistema CGLIC com autenticação e permissões
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
verificarLogin();

// Verificar permissões - apenas DIPLI e Coordenador podem importar andamentos
if (!temPermissao('licitacao_editar')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Apenas usuários DIPLI e Coordenador podem importar andamentos.'
    ]);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit;
}

// Verificar CSRF
if (!verificarCSRF()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Token CSRF inválido.'
    ]);
    exit;
}

try {
    $pdo = conectarDB();
    
    // Verificar se arquivo foi enviado
    if (!isset($_FILES['arquivo_json']) || $_FILES['arquivo_json']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Arquivo JSON não enviado ou erro no upload.');
    }
    
    $arquivo = $_FILES['arquivo_json'];
    
    // Validar tipo de arquivo
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if ($extensao !== 'json') {
        throw new Exception('Arquivo deve ter extensão .json');
    }
    
    // Validar tamanho (máximo 10MB)
    if ($arquivo['size'] > 10 * 1024 * 1024) {
        throw new Exception('Arquivo muito grande. Máximo permitido: 10MB');
    }
    
    // Ler conteúdo do arquivo
    $json_content = file_get_contents($arquivo['tmp_name']);
    if ($json_content === false) {
        throw new Exception('Erro ao ler arquivo JSON.');
    }
    
    // Validar JSON
    $data = json_decode($json_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Arquivo JSON inválido: ' . json_last_error_msg());
    }
    
    // Validar estrutura obrigatória
    $campos_obrigatorios = ['nup', 'processo_id', 'timestamp', 'total_andamentos', 'andamentos'];
    foreach ($campos_obrigatorios as $campo) {
        if (!isset($data[$campo])) {
            throw new Exception("Campo obrigatório ausente: {$campo}");
        }
    }
    
    // Validar NUP
    if (empty($data['nup']) || !is_string($data['nup'])) {
        throw new Exception('NUP deve ser uma string não vazia.');
    }
    
    // Validar processo_id
    if (empty($data['processo_id']) || !is_string($data['processo_id'])) {
        throw new Exception('processo_id deve ser uma string não vazia.');
    }
    
    // Validar total_andamentos
    if (!is_numeric($data['total_andamentos']) || $data['total_andamentos'] < 0) {
        throw new Exception('total_andamentos deve ser um número maior ou igual a zero.');
    }
    
    // Validar andamentos (deve ser array)
    if (!is_array($data['andamentos'])) {
        throw new Exception('andamentos deve ser um array.');
    }
    
    // Preparar dados para inserção
    $nup = trim($data['nup']);
    $processo_id = trim($data['processo_id']);
    $timestamp = $data['timestamp'];
    $total_andamentos = (int)$data['total_andamentos'];
    $andamentos_json = json_encode($data['andamentos'], JSON_UNESCAPED_UNICODE);
    
    // Verificar se já existe registro para este NUP/processo_id
    $stmt_check = $pdo->prepare("
        SELECT id, timestamp, total_andamentos 
        FROM processo_andamentos 
        WHERE nup = ? AND processo_id = ?
    ");
    $stmt_check->execute([$nup, $processo_id]);
    $registro_existente = $stmt_check->fetch();
    
    $acao = 'inserido';
    
    if ($registro_existente) {
        // Atualizar registro existente
        $stmt = $pdo->prepare("
            UPDATE processo_andamentos 
            SET timestamp = ?, total_andamentos = ?, andamentos_json = ?, atualizado_em = NOW()
            WHERE nup = ? AND processo_id = ?
        ");
        $stmt->execute([$timestamp, $total_andamentos, $andamentos_json, $nup, $processo_id]);
        $acao = 'atualizado';
        $processo_andamento_id = $registro_existente['id'];
    } else {
        // Inserir novo registro
        $stmt = $pdo->prepare("
            INSERT INTO processo_andamentos (nup, processo_id, timestamp, total_andamentos, andamentos_json)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nup, $processo_id, $timestamp, $total_andamentos, $andamentos_json]);
        $processo_andamento_id = $pdo->lastInsertId();
    }
    
    // Log da operação
    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';
    $log_message = "Andamentos {$acao} para NUP: {$nup}, Processo: {$processo_id}, Total: {$total_andamentos}";
    
    // Registrar log se função existir
    if (function_exists('registrarLog')) {
        registrarLog('IMPORTACAO_ANDAMENTOS', $log_message, $usuario_nome);
    }
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => "Andamentos {$acao} com sucesso!",
        'data' => [
            'id' => $processo_andamento_id,
            'nup' => $nup,
            'processo_id' => $processo_id,
            'total_andamentos' => $total_andamentos,
            'acao' => $acao,
            'timestamp_importacao' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log do erro
    if (function_exists('registrarLog')) {
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';
        registrarLog('ERRO_IMPORTACAO_ANDAMENTOS', 'Erro: ' . $e->getMessage(), $usuario_nome);
    }
}
?>