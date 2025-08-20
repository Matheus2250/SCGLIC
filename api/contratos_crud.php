<?php
/**
 * API CRUD para Contratos - Sistema CGLIC
 * Operações: get, create, update, delete
 */

require_once '../config.php';
require_once '../functions.php';

// Verificar login
verificarLogin();

// Headers para API
header('Content-Type: application/json; charset=utf-8');

$pdo = conectarDB();

// Pegar action e dados
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

try {
    switch ($action) {
        case 'get':
            if (!$id) {
                throw new Exception('ID do contrato é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                SELECT *
                FROM contratos 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contrato) {
                throw new Exception('Contrato não encontrado');
            }
            
            // Formatear datas para input HTML
            $dateFields = ['data_assinatura', 'data_inicio', 'data_fim'];
            foreach ($dateFields as $field) {
                if (!empty($contrato[$field])) {
                    $contrato[$field] = date('Y-m-d', strtotime($contrato[$field]));
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $contrato
            ]);
            break;
            
        case 'create':
        case 'criar_contrato':
            $data = [
                'numero_contrato' => $_POST['numero_contrato'] ?? '',
                'ano_contrato' => intval($_POST['ano_contrato'] ?? date('Y')),
                'numero_sei' => $_POST['numero_sei'] ?? '',
                'nome_empresa' => $_POST['nome_empresa'] ?? '',
                'cnpj_cpf' => $_POST['cnpj_cpf'] ?? '',
                'modalidade' => $_POST['modalidade'] ?? '',
                'objeto_servico' => $_POST['objeto_servico'] ?? '',
                'valor_inicial' => floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_inicial'] ?? '0')),
                'valor_atual' => floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_atual'] ?? '0')),
                'data_assinatura' => $_POST['data_assinatura'] ?? null,
                'data_inicio' => $_POST['data_inicio'] ?? null,
                'data_fim' => $_POST['data_fim'] ?? null,
                'status_contrato' => $_POST['status_contrato'] ?? 'ativo',
                'area_gestora' => $_POST['area_gestora'] ?? '',
                'finalidade' => $_POST['finalidade'] ?? '',
                'fiscais' => $_POST['fiscais'] ?? '',
                'observacoes' => $_POST['observacoes'] ?? '',
                'criado_por' => $_SESSION['usuario_id'] ?? null,
                'criado_em' => date('Y-m-d H:i:s')
            ];
            
            // Validações básicas
            if (empty($data['numero_contrato'])) {
                throw new Exception('Número do contrato é obrigatório');
            }
            if (empty($data['nome_empresa'])) {
                throw new Exception('Nome da empresa é obrigatório');
            }
            if (empty($data['objeto_servico'])) {
                throw new Exception('Objeto/serviço é obrigatório');
            }
            
            // Verificar se contrato já existe
            $stmt = $pdo->prepare("
                SELECT id FROM contratos 
                WHERE numero_contrato = ? AND ano_contrato = ?
            ");
            $stmt->execute([$data['numero_contrato'], $data['ano_contrato']]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe um contrato com esse número/ano');
            }
            
            // Inserir
            $fields = array_keys($data);
            $placeholders = str_repeat('?,', count($fields) - 1) . '?';
            $sql = "INSERT INTO contratos (" . implode(',', $fields) . ") VALUES ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            
            echo json_encode([
                'success' => true,
                'message' => 'Contrato criado com sucesso',
                'id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'update':
        case 'editar_contrato':
            if (!$id) {
                $id = intval($_POST['contratoId'] ?? 0);
            }
            if (!$id) {
                throw new Exception('ID do contrato é obrigatório');
            }
            
            $data = [
                'numero_contrato' => $_POST['numero_contrato'] ?? '',
                'ano_contrato' => intval($_POST['ano_contrato'] ?? date('Y')),
                'numero_sei' => $_POST['numero_sei'] ?? '',
                'nome_empresa' => $_POST['nome_empresa'] ?? '',
                'cnpj_cpf' => $_POST['cnpj_cpf'] ?? '',
                'modalidade' => $_POST['modalidade'] ?? '',
                'objeto_servico' => $_POST['objeto_servico'] ?? '',
                'valor_inicial' => floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_inicial'] ?? '0')),
                'valor_atual' => floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_atual'] ?? '0')),
                'data_assinatura' => $_POST['data_assinatura'] ?: null,
                'data_inicio' => $_POST['data_inicio'] ?: null,
                'data_fim' => $_POST['data_fim'] ?: null,
                'status_contrato' => $_POST['status_contrato'] ?? 'ativo',
                'area_gestora' => $_POST['area_gestora'] ?? '',
                'finalidade' => $_POST['finalidade'] ?? '',
                'fiscais' => $_POST['fiscais'] ?? '',
                'observacoes' => $_POST['observacoes'] ?? '',
                'atualizado_em' => date('Y-m-d H:i:s')
            ];
            
            // Verificar se contrato existe
            $stmt = $pdo->prepare("SELECT id FROM contratos WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                throw new Exception('Contrato não encontrado');
            }
            
            // Verificar duplicação (exceto o próprio)
            $stmt = $pdo->prepare("
                SELECT id FROM contratos 
                WHERE numero_contrato = ? AND ano_contrato = ? AND id != ?
            ");
            $stmt->execute([$data['numero_contrato'], $data['ano_contrato'], $id]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe outro contrato com esse número/ano');
            }
            
            // Atualizar
            $setParts = [];
            $values = [];
            foreach ($data as $field => $value) {
                $setParts[] = "$field = ?";
                $values[] = $value;
            }
            $values[] = $id;
            
            $sql = "UPDATE contratos SET " . implode(', ', $setParts) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            
            echo json_encode([
                'success' => true,
                'message' => 'Contrato atualizado com sucesso'
            ]);
            break;
            
        case 'delete':
        case 'excluir_contrato':
            if (!$id) {
                throw new Exception('ID do contrato é obrigatório');
            }
            
            // Verificar se contrato existe
            $stmt = $pdo->prepare("SELECT numero_contrato FROM contratos WHERE id = ?");
            $stmt->execute([$id]);
            $contrato = $stmt->fetch();
            if (!$contrato) {
                throw new Exception('Contrato não encontrado');
            }
            
            // Excluir (soft delete seria melhor, mas por ora excluir mesmo)
            $stmt = $pdo->prepare("DELETE FROM contratos WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => "Contrato {$contrato['numero_contrato']} excluído com sucesso"
            ]);
            break;
            
        default:
            throw new Exception('Ação não reconhecida: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}