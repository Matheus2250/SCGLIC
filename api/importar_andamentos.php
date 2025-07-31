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

// Verificar CSRF - Desabilitado temporariamente
// if (!verificarCSRF()) {
//     http_response_code(403);
//     echo json_encode([
//         'success' => false,
//         'message' => 'Token CSRF inválido.'
//     ]);
//     exit;
// }

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
    $total_andamentos = (int)$data['total_andamentos'];
    $andamentos = $data['andamentos'];
    
    // Verificar se já existem andamentos para este NUP
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM historico_andamentos 
        WHERE nup = ?
    ");
    $stmt_check->execute([$nup]);
    $total_existente = $stmt_check->fetch()['total'];
    
    $pdo->beginTransaction();
    
    try {
        $acao = 'inseridos';
        $andamentos_processados = 0;
        
        if ($total_existente > 0) {
            // Limpar andamentos existentes para este NUP antes de inserir novos
            $stmt_delete = $pdo->prepare("DELETE FROM historico_andamentos WHERE nup = ?");
            $stmt_delete->execute([$nup]);
            $acao = 'atualizados';
        }
        
        // Preparar statement para inserção
        $stmt_insert = $pdo->prepare("
            INSERT INTO historico_andamentos (nup, processo_id, data_hora, unidade, usuario, descricao) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        // Processar cada andamento individualmente
        foreach ($andamentos as $andamento) {
            // Validar campos obrigatórios do andamento
            if (!isset($andamento['data_hora']) || !isset($andamento['unidade']) || !isset($andamento['descricao'])) {
                throw new Exception('Andamento com campos obrigatórios ausentes (data_hora, unidade, descricao)');
            }
            
            // Converter data_hora para formato MySQL
            $data_hora_original = $andamento['data_hora'];
            try {
                $data_obj = null;
                
                // Tentar diferentes formatos de data
                $formatos = [
                    'Y-m-d\TH:i:s.v\Z',     // ISO 8601 com milissegundos: 2025-07-18T12:31:00.000Z
                    'Y-m-d\TH:i:s\Z',       // ISO 8601 sem milissegundos: 2025-07-18T12:31:00Z
                    'Y-m-d H:i:s',          // MySQL: 2025-07-18 12:31:00
                    'd/m/Y H:i',            // Brasileiro: 18/07/2025 12:31
                    'Y-m-d'                 // Apenas data: 2025-07-18
                ];
                
                foreach ($formatos as $formato) {
                    $data_obj = DateTime::createFromFormat($formato, $data_hora_original);
                    if ($data_obj && $data_obj->format($formato) === $data_hora_original) {
                        break;
                    }
                    $data_obj = null;
                }
                
                if (!$data_obj) {
                    throw new Exception("Formato de data inválido: {$data_hora_original}");
                }
                
                $data_hora_mysql = $data_obj->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                throw new Exception("Erro ao converter data '{$data_hora_original}': " . $e->getMessage());
            }
            
            // Inserir andamento
            $stmt_insert->execute([
                $nup,
                $processo_id,
                $data_hora_mysql,
                trim($andamento['unidade']),
                isset($andamento['usuario']) ? trim($andamento['usuario']) : null,
                trim($andamento['descricao'])
            ]);
            
            $andamentos_processados++;
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
    // Log da operação
    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';
    $log_message = "Andamentos {$acao} para NUP: {$nup}, Processo: {$processo_id}, Total processados: {$andamentos_processados}";
    
    // Registrar log se função existir
    if (function_exists('registrarLog')) {
        registrarLog('IMPORTACAO_ANDAMENTOS', $log_message, $usuario_nome);
    }
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => "Andamentos {$acao} com sucesso!",
        'data' => [
            'nup' => $nup,
            'processo_id' => $processo_id,
            'total_esperados' => $total_andamentos,
            'total_processados' => $andamentos_processados,
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