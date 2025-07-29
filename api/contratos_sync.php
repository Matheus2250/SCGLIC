<?php
/**
 * Sistema de Sincronização de Contratos
 * Rotina automatizada para sincronizar contratos do Comprasnet
 * 
 * Uso:
 * - Via web: http://localhost/sistema_licitacao/api/contratos_sync.php
 * - Via CLI: php contratos_sync.php --tipo=[completa|incremental]
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/comprasnet_api.php';

class ContratosSyncService {
    
    private $db;
    private $api;
    private $logId;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
        $this->api = new ComprasnetAPI();
    }
    
    /**
     * Executa sincronização completa
     */
    public function syncCompleta() {
        $this->logId = $this->iniciarLog('completa');
        
        try {
            if (!$this->api->isTokenValid()) {
                throw new Exception('Token de acesso inválido ou expirado');
            }
            
            $stats = [
                'total' => 0,
                'novos' => 0,
                'atualizados' => 0,
                'erro' => 0
            ];
            
            $pagina = 1;
            $limite = 100;
            $totalPaginas = 1;
            
            do {
                $response = $this->api->getContratos([
                    'pagina' => $pagina,
                    'limite' => $limite
                ]);
                
                if (isset($response['error'])) {
                    throw new Exception('Erro na API: ' . $response['error']);
                }
                
                if ($pagina === 1) {
                    $stats['total'] = $response['total'] ?? 0;
                    $totalPaginas = ceil($stats['total'] / $limite);
                }
                
                $contratos = $response['dados'] ?? $response['contratos'] ?? [];
                
                foreach ($contratos as $contrato) {
                    try {
                        $resultado = $this->processarContrato($contrato);
                        if ($resultado === 'novo') {
                            $stats['novos']++;
                        } elseif ($resultado === 'atualizado') {
                            $stats['atualizados']++;
                        }
                    } catch (Exception $e) {
                        $stats['erro']++;
                        error_log("Erro ao processar contrato {$contrato['id']}: " . $e->getMessage());
                    }
                }
                
                $pagina++;
                
                // Pausa entre requisições para respeitar rate limit
                usleep(100000); // 100ms
                
            } while ($pagina <= $totalPaginas);
            
            $this->finalizarLog('sucesso', $stats, "Sincronização completa finalizada");
            
            return [
                'success' => true,
                'stats' => $stats,
                'message' => 'Sincronização completa realizada com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->finalizarLog('erro', $stats ?? [], $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Executa sincronização incremental (últimas 24h)
     */
    public function syncIncremental() {
        $this->logId = $this->iniciarLog('incremental');
        
        try {
            if (!$this->api->isTokenValid()) {
                throw new Exception('Token de acesso inválido ou expirado');
            }
            
            // Buscar contratos alterados nas últimas 24h
            $dataInicio = date('Y-m-d', strtotime('-1 day'));
            
            $response = $this->api->getContratos([
                'data_alteracao_inicio' => $dataInicio,
                'limite' => 1000
            ]);
            
            if (isset($response['error'])) {
                throw new Exception('Erro na API: ' . $response['error']);
            }
            
            $contratos = $response['dados'] ?? $response['contratos'] ?? [];
            $stats = [
                'total' => count($contratos),
                'novos' => 0,
                'atualizados' => 0,
                'erro' => 0
            ];
            
            foreach ($contratos as $contrato) {
                try {
                    $resultado = $this->processarContrato($contrato);
                    if ($resultado === 'novo') {
                        $stats['novos']++;
                    } elseif ($resultado === 'atualizado') {
                        $stats['atualizados']++;
                    }
                } catch (Exception $e) {
                    $stats['erro']++;
                    error_log("Erro ao processar contrato {$contrato['id']}: " . $e->getMessage());
                }
            }
            
            $this->finalizarLog('sucesso', $stats, "Sincronização incremental finalizada");
            
            return [
                'success' => true,
                'stats' => $stats,
                'message' => 'Sincronização incremental realizada com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->finalizarLog('erro', $stats ?? [], $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Processa um contrato individual
     */
    private function processarContrato($contratoData) {
        // Verificar se contrato já existe
        $stmt = $this->db->prepare("SELECT id FROM contratos WHERE comprasnet_id = ?");
        $stmt->bind_param("s", $contratoData['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $contratoExiste = $result->fetch_assoc();
        
        if ($contratoExiste) {
            // Atualizar contrato existente
            $this->atualizarContrato($contratoExiste['id'], $contratoData);
            return 'atualizado';
        } else {
            // Criar novo contrato
            $contratoId = $this->criarContrato($contratoData);
            
            // Buscar dados detalhados
            $this->sincronizarDetalhesContrato($contratoId, $contratoData['id']);
            
            return 'novo';
        }
    }
    
    /**
     * Cria um novo contrato
     */
    private function criarContrato($data) {
        $stmt = $this->db->prepare("
            INSERT INTO contratos (
                numero_contrato, comprasnet_id, objeto, orgao_contratante, uasg,
                contratado_nome, contratado_cnpj, valor_total,
                data_assinatura, data_inicio_vigencia, data_fim_vigencia, data_publicacao,
                modalidade, tipo_contrato, numero_processo, situacao, status_contrato,
                link_comprasnet, url_documento, ultima_sincronizacao, sincronizado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $statusContrato = $this->determinarStatusContrato($data);
        $userId = $_SESSION['user_id'] ?? 1;
        
        $stmt->bind_param(
            "sssssssdsssssssssssi",
            $data['numero'],
            $data['id'],
            $data['objeto'],
            $data['orgao_contratante'] ?? 'Ministério da Saúde',
            $data['uasg'] ?? '250110',
            $data['fornecedor']['nome'] ?? $data['contratado_nome'],
            $data['fornecedor']['cnpj'] ?? $data['contratado_cnpj'],
            $data['valor_total'] ?? $data['valor'],
            $this->formatarData($data['data_assinatura']),
            $this->formatarData($data['data_inicio_vigencia']),
            $this->formatarData($data['data_fim_vigencia']),
            $this->formatarData($data['data_publicacao']),
            $data['modalidade'],
            $data['tipo'] ?? $data['tipo_contrato'],
            $data['numero_processo'],
            $data['situacao'],
            $statusContrato,
            $data['link'] ?? '',
            $data['url_documento'] ?? '',
            $userId
        );
        
        $stmt->execute();
        return $this->db->insert_id;
    }
    
    /**
     * Atualiza um contrato existente
     */
    private function atualizarContrato($contratoId, $data) {
        $stmt = $this->db->prepare("
            UPDATE contratos SET
                objeto = ?, valor_total = ?, data_assinatura = ?, data_inicio_vigencia = ?,
                data_fim_vigencia = ?, situacao = ?, status_contrato = ?,
                ultima_sincronizacao = NOW(), sincronizado_por = ?
            WHERE id = ?
        ");
        
        $statusContrato = $this->determinarStatusContrato($data);
        $userId = $_SESSION['user_id'] ?? 1;
        
        $stmt->bind_param(
            "sdssssii",
            $data['objeto'],
            $data['valor_total'] ?? $data['valor'],
            $this->formatarData($data['data_assinatura']),
            $this->formatarData($data['data_inicio_vigencia']),
            $this->formatarData($data['data_fim_vigencia']),
            $data['situacao'],
            $statusContrato,
            $userId,
            $contratoId
        );
        
        return $stmt->execute();
    }
    
    /**
     * Sincroniza detalhes completos do contrato
     */
    private function sincronizarDetalhesContrato($contratoId, $comprasnetId) {
        try {
            // Buscar aditivos
            $aditivos = $this->api->getContratoAditivos($comprasnetId);
            if (!isset($aditivos['error']) && !empty($aditivos)) {
                $this->processarAditivos($contratoId, $aditivos);
            }
            
            // Buscar empenhos
            $empenhos = $this->api->getContratoEmpenhos($comprasnetId);
            if (!isset($empenhos['error']) && !empty($empenhos)) {
                $this->processarEmpenhos($contratoId, $empenhos);
            }
            
            // Buscar pagamentos
            $pagamentos = $this->api->getContratoPagamentos($comprasnetId);
            if (!isset($pagamentos['error']) && !empty($pagamentos)) {
                $this->processarPagamentos($contratoId, $pagamentos);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao sincronizar detalhes do contrato {$contratoId}: " . $e->getMessage());
        }
    }
    
    /**
     * Processa aditivos do contrato
     */
    private function processarAditivos($contratoId, $aditivos) {
        foreach ($aditivos as $aditivo) {
            $stmt = $this->db->prepare("
                INSERT INTO contratos_aditivos 
                (contrato_id, numero_aditivo, tipo_aditivo, objeto_aditivo, valor_aditivo,
                 data_assinatura, data_inicio_vigencia, data_fim_vigencia, situacao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                valor_aditivo = VALUES(valor_aditivo),
                situacao = VALUES(situacao)
            ");
            
            $tipoAditivo = $this->determinarTipoAditivo($aditivo);
            
            $stmt->bind_param(
                "isssdsssss",
                $contratoId,
                $aditivo['numero'],
                $tipoAditivo,
                $aditivo['objeto'],
                $aditivo['valor'] ?? 0,
                $this->formatarData($aditivo['data_assinatura']),
                $this->formatarData($aditivo['data_inicio_vigencia']),
                $this->formatarData($aditivo['data_fim_vigencia']),
                $aditivo['situacao']
            );
            
            $stmt->execute();
        }
    }
    
    /**
     * Processa empenhos do contrato
     */
    private function processarEmpenhos($contratoId, $empenhos) {
        foreach ($empenhos as $empenho) {
            $stmt = $this->db->prepare("
                INSERT INTO contratos_empenhos 
                (contrato_id, numero_empenho, tipo_empenho, valor_empenho, data_empenho, situacao)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                valor_empenho = VALUES(valor_empenho),
                situacao = VALUES(situacao)
            ");
            
            $stmt->bind_param(
                "isssds",
                $contratoId,
                $empenho['numero'],
                $empenho['tipo'],
                $empenho['valor'],
                $this->formatarData($empenho['data']),
                $empenho['situacao']
            );
            
            $stmt->execute();
        }
        
        // Atualizar valor empenhado no contrato
        $this->atualizarValorEmpenhado($contratoId);
    }
    
    /**
     * Processa pagamentos do contrato
     */
    private function processarPagamentos($contratoId, $pagamentos) {
        foreach ($pagamentos as $pagamento) {
            $stmt = $this->db->prepare("
                INSERT INTO contratos_pagamentos 
                (contrato_id, numero_documento, tipo_pagamento, valor_pagamento, data_pagamento, situacao)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                valor_pagamento = VALUES(valor_pagamento),
                situacao = VALUES(situacao)
            ");
            
            $stmt->bind_param(
                "isssds",
                $contratoId,
                $pagamento['documento'],
                $pagamento['tipo'],
                $pagamento['valor'],
                $this->formatarData($pagamento['data']),
                $pagamento['situacao']
            );
            
            $stmt->execute();
        }
        
        // Atualizar valor pago no contrato
        $this->atualizarValorPago($contratoId);
    }
    
    /**
     * Utilitários
     */
    private function formatarData($data) {
        if (empty($data)) return null;
        
        // Tentar diferentes formatos de data
        $formatos = ['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s', 'd-m-Y'];
        
        foreach ($formatos as $formato) {
            $dateObj = DateTime::createFromFormat($formato, $data);
            if ($dateObj !== false) {
                return $dateObj->format('Y-m-d');
            }
        }
        
        return null;
    }
    
    private function determinarStatusContrato($data) {
        $dataFim = $this->formatarData($data['data_fim_vigencia']);
        if (!$dataFim) return 'vigente';
        
        if (strtotime($dataFim) < time()) {
            return 'encerrado';
        }
        
        return 'vigente';
    }
    
    private function determinarTipoAditivo($aditivo) {
        $objeto = strtolower($aditivo['objeto'] ?? '');
        
        if (strpos($objeto, 'prazo') !== false) return 'prazo';
        if (strpos($objeto, 'valor') !== false) return 'valor';
        if (strpos($objeto, 'objeto') !== false) return 'objeto';
        
        return 'misto';
    }
    
    private function atualizarValorEmpenhado($contratoId) {
        $this->db->query("
            UPDATE contratos c SET 
                valor_empenhado = (
                    SELECT COALESCE(SUM(valor_empenho), 0) 
                    FROM contratos_empenhos e 
                    WHERE e.contrato_id = c.id
                )
            WHERE c.id = {$contratoId}
        ");
    }
    
    private function atualizarValorPago($contratoId) {
        $this->db->query("
            UPDATE contratos c SET 
                valor_pago = (
                    SELECT COALESCE(SUM(valor_pagamento), 0) 
                    FROM contratos_pagamentos p 
                    WHERE p.contrato_id = c.id
                )
            WHERE c.id = {$contratoId}
        ");
    }
    
    private function iniciarLog($tipo) {
        $stmt = $this->db->prepare("
            INSERT INTO contratos_sync_log (tipo_sync, status, executado_por)
            VALUES (?, 'iniciado', ?)
        ");
        
        $userId = $_SESSION['user_id'] ?? 1;
        $stmt->bind_param("si", $tipo, $userId);
        $stmt->execute();
        
        return $this->db->insert_id;
    }
    
    private function finalizarLog($status, $stats, $mensagem) {
        $stmt = $this->db->prepare("
            UPDATE contratos_sync_log SET
                status = ?, fim_sync = NOW(),
                total_contratos_api = ?, contratos_novos = ?,
                contratos_atualizados = ?, contratos_erro = ?, mensagem = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param(
            "siiiisi",
            $status,
            $stats['total'],
            $stats['novos'],
            $stats['atualizados'],
            $stats['erro'],
            $mensagem,
            $this->logId
        );
        
        $stmt->execute();
    }
}

// ============================================================================
// EXECUÇÃO
// ============================================================================

// Via linha de comando
if (php_sapi_name() === 'cli') {
    $options = getopt("", ["tipo:"]);
    $tipo = $options['tipo'] ?? 'incremental';
    
    $sync = new ContratosSyncService();
    
    if ($tipo === 'completa') {
        $resultado = $sync->syncCompleta();
    } else {
        $resultado = $sync->syncIncremental();
    }
    
    echo json_encode($resultado, JSON_PRETTY_PRINT) . "\n";
    exit;
}

// Via web
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarLogin()) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        exit;
    }
    
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $tipo = $input['tipo'] ?? 'incremental';
    
    $sync = new ContratosSyncService();
    
    if ($tipo === 'completa') {
        $resultado = $sync->syncCompleta();
    } else {
        $resultado = $sync->syncIncremental();
    }
    
    echo json_encode($resultado);
    exit;
}
?>