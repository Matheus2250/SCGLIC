<?php
/**
 * API de Integração com Comprasnet
 * Sistema CGLIC - Ministério da Saúde
 * 
 * Gerencia a comunicação com a API oficial do Comprasnet
 * URL Base: https://contratos.comprasnet.gov.br/api
 * UASG: 250110
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

class ComprasnetAPI {
    
    private $baseUrl;
    private $uasg;
    private $accessToken;
    private $clientId;
    private $clientSecret;
    private $db;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
        $this->loadConfig();
    }
    
    /**
     * Carrega configurações da API do banco de dados
     */
    private function loadConfig() {
        $query = "SELECT * FROM contratos_api_config ORDER BY id DESC LIMIT 1";
        $result = $this->db->query($query);
        
        if ($result && $config = $result->fetch_assoc()) {
            $this->baseUrl = $config['base_url'];
            $this->uasg = $config['uasg'];
            $this->accessToken = $config['access_token'];
            $this->clientId = $config['client_id'];
            $this->clientSecret = $config['client_secret'];
        } else {
            // Configuração padrão caso não exista no banco
            $this->baseUrl = 'https://contratos.comprasnet.gov.br/api';
            $this->uasg = '250110';
        }
    }
    
    /**
     * Salva configurações no banco de dados
     */
    public function saveConfig($config) {
        $stmt = $this->db->prepare("
            UPDATE contratos_api_config SET 
                client_id = ?,
                client_secret = ?,
                access_token = ?,
                refresh_token = ?,
                token_expires_at = ?,
                atualizado_em = NOW(),
                atualizado_por = ?
            ORDER BY id DESC LIMIT 1
        ");
        
        $stmt->bind_param(
            "sssssi",
            $config['client_id'],
            $config['client_secret'],
            $config['access_token'],
            $config['refresh_token'],
            $config['expires_at'],
            $_SESSION['user_id']
        );
        
        return $stmt->execute();
    }
    
    /**
     * Autentica via OAuth2
     */
    public function authenticate($clientId, $clientSecret) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        
        $tokenUrl = $this->baseUrl . '/oauth/token';
        
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'read'
        ];
        
        $response = $this->makeRequest('POST', $tokenUrl, $data, false);
        
        if ($response && isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            
            $expiresAt = null;
            if (isset($response['expires_in'])) {
                $expiresAt = date('Y-m-d H:i:s', time() + $response['expires_in']);
            }
            
            // Salvar token no banco
            $this->saveConfig([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'] ?? null,
                'expires_at' => $expiresAt
            ]);
            
            return [
                'success' => true,
                'token' => $response['access_token'],
                'expires_at' => $expiresAt
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Falha na autenticação: ' . ($response['error_description'] ?? 'Erro desconhecido')
        ];
    }
    
    /**
     * Verifica se o token ainda é válido
     */
    public function isTokenValid() {
        if (!$this->accessToken) {
            return false;
        }
        
        $query = "SELECT token_expires_at FROM contratos_api_config ORDER BY id DESC LIMIT 1";
        $result = $this->db->query($query);
        
        if ($result && $config = $result->fetch_assoc()) {
            $expiresAt = $config['token_expires_at'];
            if ($expiresAt && strtotime($expiresAt) > time() + 300) { // 5 min de margem
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Busca todos os contratos da UASG
     */
    public function getContratos($params = []) {
        if (!$this->isTokenValid()) {
            return ['error' => 'Token inválido ou expirado'];
        }
        
        $defaultParams = [
            'uasg' => $this->uasg,
            'limite' => 100,
            'pagina' => 1
        ];
        
        $params = array_merge($defaultParams, $params);
        $url = $this->baseUrl . '/contratos?' . http_build_query($params);
        
        return $this->makeRequest('GET', $url);
    }
    
    /**
     * Busca detalhes de um contrato específico
     */
    public function getContrato($contratoId) {
        if (!$this->isTokenValid()) {
            return ['error' => 'Token inválido ou expirado'];
        }
        
        $url = $this->baseUrl . '/contratos/' . $contratoId;
        return $this->makeRequest('GET', $url);
    }
    
    /**
     * Busca aditivos de um contrato
     */
    public function getContratoAditivos($contratoId) {
        if (!$this->isTokenValid()) {
            return ['error' => 'Token inválido ou expirado'];
        }
        
        $url = $this->baseUrl . '/contratos/' . $contratoId . '/aditivos';
        return $this->makeRequest('GET', $url);
    }
    
    /**
     * Busca empenhos de um contrato
     */
    public function getContratoEmpenhos($contratoId) {
        if (!$this->isTokenValid()) {
            return ['error' => 'Token inválido ou expirado'];
        }
        
        $url = $this->baseUrl . '/contratos/' . $contratoId . '/empenhos';
        return $this->makeRequest('GET', $url);
    }
    
    /**
     * Busca pagamentos de um contrato
     */
    public function getContratoPagamentos($contratoId) {
        if (!$this->isTokenValid()) {
            return ['error' => 'Token inválido ou expirado'];
        }
        
        $url = $this->baseUrl . '/contratos/' . $contratoId . '/pagamentos';
        return $this->makeRequest('GET', $url);
    }
    
    /**
     * Faz uma requisição HTTP
     */
    private function makeRequest($method, $url, $data = null, $useAuth = true) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: CGLIC-MS/1.0'
        ];
        
        if ($useAuth && $this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("ComprasnetAPI cURL Error: " . $error);
            return ['error' => 'Erro de conexão: ' . $error];
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            error_log("ComprasnetAPI HTTP Error {$httpCode}: " . $response);
            return [
                'error' => 'Erro HTTP ' . $httpCode,
                'details' => $decoded['message'] ?? $response
            ];
        }
        
        return $decoded;
    }
    
    /**
     * Testa a conectividade com a API
     */
    public function testConnection() {
        $url = $this->baseUrl . '/status';
        $response = $this->makeRequest('GET', $url, null, false);
        
        return [
            'success' => !isset($response['error']),
            'response' => $response
        ];
    }
    
    /**
     * Registra log de sincronização
     */
    public function logSync($tipo, $status, $stats = [], $mensagem = '', $erro = '') {
        $stmt = $this->db->prepare("
            INSERT INTO contratos_sync_log 
            (tipo_sync, status, total_contratos_api, contratos_novos, contratos_atualizados, 
             contratos_erro, fim_sync, mensagem, detalhes_erro, executado_por)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "ssiiiissi",
            $tipo,
            $status,
            $stats['total'] ?? 0,
            $stats['novos'] ?? 0,
            $stats['atualizados'] ?? 0,
            $stats['erro'] ?? 0,
            $mensagem,
            $erro,
            $_SESSION['user_id'] ?? 1
        );
        
        return $stmt->execute();
    }
}

// ============================================================================
// ENDPOINTS DA API
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? '';
    
    $api = new ComprasnetAPI();
    
    switch ($action) {
        case 'authenticate':
            $clientId = $input['client_id'] ?? '';
            $clientSecret = $input['client_secret'] ?? '';
            
            if (empty($clientId) || empty($clientSecret)) {
                echo json_encode(['error' => 'Client ID e Client Secret são obrigatórios']);
                exit;
            }
            
            $result = $api->authenticate($clientId, $clientSecret);
            echo json_encode($result);
            break;
            
        case 'test_connection':
            $result = $api->testConnection();
            echo json_encode($result);
            break;
            
        case 'get_contratos':
            $params = $input['params'] ?? [];
            $result = $api->getContratos($params);
            echo json_encode($result);
            break;
            
        case 'get_contrato':
            $contratoId = $input['contrato_id'] ?? '';
            if (empty($contratoId)) {
                echo json_encode(['error' => 'ID do contrato é obrigatório']);
                exit;
            }
            
            $result = $api->getContrato($contratoId);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['error' => 'Ação não encontrada']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? '';
    $api = new ComprasnetAPI();
    
    switch ($action) {
        case 'status':
            echo json_encode([
                'status' => 'online',
                'token_valid' => $api->isTokenValid(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Ação não especificada']);
    }
    exit;
}
?>