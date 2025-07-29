<?php
/**
 * API de Setup do Módulo de Contratos
 * Executa a criação das tabelas e configuração inicial
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Verificar login e permissões
if (!verificarLogin() || $_SESSION['nivel_acesso'] != 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'setup') {
        try {
            // Ler e executar o SQL de setup
            $sqlFile = __DIR__ . '/../database/modulo_contratos.sql';
            
            if (!file_exists($sqlFile)) {
                throw new Exception('Arquivo SQL não encontrado: ' . $sqlFile);
            }
            
            $sql = file_get_contents($sqlFile);
            
            if (!$sql) {
                throw new Exception('Erro ao ler arquivo SQL');
            }
            
            // Dividir em comandos individuais
            $commands = array_filter(
                array_map('trim', explode(';', $sql)),
                function($cmd) {
                    return !empty($cmd) && !preg_match('/^\s*--/', $cmd);
                }
            );
            
            $executed = 0;
            $errors = [];
            
            foreach ($commands as $command) {
                if (trim($command)) {
                    try {
                        $conn->query($command);
                        $executed++;
                    } catch (Exception $e) {
                        // Ignorar erros de tabela já existente
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            $errors[] = $e->getMessage();
                        }
                    }
                }
            }
            
            if (empty($errors)) {
                echo json_encode([
                    'success' => true,
                    'message' => "Setup executado com sucesso! {$executed} comandos processados.",
                    'executed' => $executed
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Alguns erros ocorreram durante o setup',
                    'errors' => $errors,
                    'executed' => $executed
                ]);
            }
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    } else {
        echo json_encode(['error' => 'Ação não encontrada']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método não permitido']);
?>