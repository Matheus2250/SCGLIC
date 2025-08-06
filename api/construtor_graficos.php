<?php
require_once '../config.php';
require_once '../functions.php';

verificarLogin();

// Verificar permissões
if (!temPermissao('pca_relatorios')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão para acessar relatórios']);
    exit;
}

$pdo = conectarDB();

// Verificar se é uma requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Apenas requisições AJAX são permitidas']);
    exit;
}

// Configurar resposta JSON
header('Content-Type: application/json; charset=utf-8');

try {
    $acao = $_GET['acao'] ?? '';
    
    // Debug: log da ação
    error_log("Construtor Graficos - Ação: $acao");
    error_log("Construtor Graficos - Parâmetros: " . json_encode($_GET));
    
    switch ($acao) {
        case 'obter_dados':
            error_log("Construtor Graficos - Executando obterDadosGrafico()");
            obterDadosGrafico();
            break;
        case 'salvar_configuracao':
            error_log("Construtor Graficos - Executando salvarConfiguracao()");
            salvarConfiguracao();
            break;
        case 'carregar_configuracoes':
            carregarConfiguracoes();
            break;
        case 'excluir_configuracao':
            excluirConfiguracao();
            break;
        default:
            throw new Exception('Ação não válida: ' . $acao);
    }
    
} catch (Exception $e) {
    error_log("Construtor Graficos - Erro detalhado: " . $e->getMessage());
    error_log("Construtor Graficos - Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(), 
        'trace' => $e->getTraceAsString(),
        'acao' => $acao,
        'parametros' => $_GET
    ]);
}

/**
 * Obter dados para construção do gráfico
 */
function obterDadosGrafico() {
    global $pdo;
    
    $campoX = $_GET['campoX'] ?? 'categoria_contratacao';
    $campoY = $_GET['campoY'] ?? 'categoria_contratacao';
    $filtroAno = $_GET['filtroAno'] ?? '';
    $filtroSituacao = $_GET['filtroSituacao'] ?? '';
    $filtroDataInicio = $_GET['filtroDataInicio'] ?? '';
    $filtroDataFim = $_GET['filtroDataFim'] ?? '';
    
    error_log("obterDadosGrafico - Parâmetros recebidos:");
    error_log("  campoX: $campoX");
    error_log("  campoY: $campoY");
    error_log("  filtroAno: $filtroAno");
    error_log("  filtroSituacao: $filtroSituacao");
    error_log("  filtroDataInicio: $filtroDataInicio");
    error_log("  filtroDataFim: $filtroDataFim");
    
    // Validar campos permitidos
    $camposXPermitidos = [
        'categoria_contratacao', 'area_requisitante', 'situacao_execucao', 
        'prioridade', 'urgente', 'valor_total_contratacao', 'quantidade_dfds'
    ];
    
    $camposYPermitidos = [
        'categoria_contratacao', 'area_requisitante', 'situacao_execucao', 
        'prioridade', 'urgente', 'valor_total_contratacao', 'quantidade_dfds'
    ];
    
    if (!in_array($campoX, $camposXPermitidos) || !in_array($campoY, $camposYPermitidos)) {
        error_log("obterDadosGrafico - Erro: Campos não permitidos - X: $campoX, Y: $campoY");
        throw new Exception("Campos não permitidos - X: $campoX, Y: $campoY");
    }
    
    // Construir query base
    $where = [];
    $params = [];
    
    // Filtro por ano através das importações
    if (!empty($filtroAno)) {
        $where[] = "pi.ano_pca = ?";
        $params[] = $filtroAno;
    }
    
    // Filtro por situação
    if (!empty($filtroSituacao)) {
        $where[] = "pd.situacao_execucao = ?";
        $params[] = $filtroSituacao;
    }
    
    // Filtro por data de início (De)
    if (!empty($filtroDataInicio)) {
        $where[] = "pd.data_inicio_processo >= ?";
        $params[] = $filtroDataInicio;
    }
    
    // Filtro por data de início (Até)
    if (!empty($filtroDataFim)) {
        $where[] = "pd.data_inicio_processo <= ?";
        $params[] = $filtroDataFim;
    }
    
    // Filtros básicos
    $where[] = "pd.numero_dfd IS NOT NULL AND pd.numero_dfd != ''";
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir SELECT baseado nos campos
    $selectX = construirSelectCampoX($campoX);
    $groupBy = obterGroupBy($campoX);
    $orderBy = obterOrderBy($campoY);
    
    // Para o valor Y, usar lógica diferente baseado no tipo de campo
    if ($campoY === 'quantidade_dfds') {
        $selectY = "COUNT(*)";
    } else if ($campoY === 'valor_total_contratacao') {
        $selectY = "COALESCE(SUM(DISTINCT pd.valor_total), 0)";
    } else if ($campoX === $campoY) {
        // Se X e Y são iguais, Y deve ser uma contagem
        $selectY = "COUNT(*)";
    } else {
        // Campos diferentes - usar construção padrão
        $selectY = construirSelectCampoY($campoY);
    }
    
    error_log("obterDadosGrafico - SELECT construído:");
    error_log("  selectX (categoria): $selectX");
    error_log("  selectY (valor): $selectY");
    error_log("  groupBy: $groupBy");
    
    // Usar dados únicos por DFD para evitar duplicação de valores
    $sql = "
        SELECT 
            {$selectX} as categoria,
            {$selectY} as valor,
            COUNT(*) as total_registros,
            COALESCE(SUM(DISTINCT pd.valor_total), 0) as valor_total_real
        FROM (
            SELECT DISTINCT 
                numero_dfd, 
                valor_total, 
                categoria_contratacao, 
                area_requisitante, 
                situacao_execucao, 
                prioridade, 
                urgente,
                data_inicio_processo,
                importacao_id
            FROM pca_dados
        ) pd
        INNER JOIN pca_importacoes pi ON pd.importacao_id = pi.id
        {$whereClause}
        GROUP BY {$groupBy}
        HAVING categoria IS NOT NULL AND categoria != ''
        ORDER BY {$orderBy}
        LIMIT 50
    ";
    
    error_log("obterDadosGrafico - SQL Final: " . $sql);
    error_log("obterDadosGrafico - Parâmetros SQL: " . json_encode($params));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("obterDadosGrafico - Registros encontrados: " . count($dados));
    if (count($dados) > 0) {
        error_log("obterDadosGrafico - Primeiro registro: " . json_encode($dados[0]));
    }
    
    // Preparar dados para Chart.js
    $labels = [];
    $valores = [];
    $cores = gerarCoresPadrao(count($dados));
    
    $totalGeral = 0;
    $valorTotalGeral = 0;
    
    foreach ($dados as $item) {
        $categoria = $item['categoria'];
        $valor = $item['valor'];
        
        // Garantir que categoria é string
        if (is_null($categoria) || $categoria === '') {
            $categoria = 'Sem categoria';
        }
        
        // Garantir que valor é numérico
        if (is_null($valor) || $valor === '') {
            $valor = 0;
        }
        
        $labels[] = strval($categoria);
        $valores[] = floatval($valor);
        $totalGeral += intval($item['total_registros']);
        $valorTotalGeral += floatval($item['valor_total_real']);
    }
    
    // Estatísticas adicionais
    $maiorCategoria = !empty($dados) ? $dados[0]['categoria'] : 'N/A';
    
    echo json_encode([
        'success' => true,
        'dados' => [
            'labels' => $labels,
            'datasets' => [[
                'data' => $valores,
                'backgroundColor' => $cores,
                'borderColor' => array_map(function($cor) {
                    return str_replace('0.8', '1', $cor);
                }, $cores),
                'borderWidth' => 2
            ]]
        ],
        'estatisticas' => [
            'total_registros' => $totalGeral,
            'valor_total' => $valorTotalGeral,
            'maior_categoria' => $maiorCategoria
        ]
    ]);
}

/**
 * Construir SELECT para campo X baseado no tipo
 */
function construirSelectCampoX($campo) {
    switch ($campo) {
        case 'urgente':
            return "CASE WHEN pd.urgente = 1 THEN 'Urgente' ELSE 'Normal' END";
        case 'valor_total_contratacao':
            return "pd.valor_total";
        case 'quantidade_dfds':
            return "COUNT(*)";
        default:
            return "pd.{$campo}";
    }
}

/**
 * Construir SELECT para campo Y baseado no tipo
 */
function construirSelectCampoY($campo) {
    switch ($campo) {
        // Campos categóricos permitidos no Y
        case 'categoria_contratacao':
        case 'area_requisitante':
        case 'situacao_execucao':
        case 'prioridade':
            return "pd.{$campo}";
            
        case 'urgente':
            return "CASE WHEN pd.urgente = 1 THEN 'Urgente' ELSE 'Normal' END";
            
        // Campos de valores
        case 'valor_total_contratacao':
            return "pd.valor_total";
            
        case 'quantidade_dfds':
            return "COUNT(*)";
            
        default:
            return "pd.categoria_contratacao";
    }
}

/**
 * Obter GROUP BY baseado no campo X
 */
function obterGroupBy($campoX) {
    $campoY = $_GET['campoY'] ?? 'categoria_contratacao';
    
    error_log("obterGroupBy - campoX: $campoX, campoY: $campoY");
    
    // Se X e Y são iguais, agrupar apenas por X (evita duplicações)
    if ($campoX === $campoY) {
        $groupBy = construirSelectCampoX($campoX);
        error_log("obterGroupBy - Campos iguais, agrupando por: $groupBy");
        return $groupBy;
    }
    
    // Campos categóricos - quando diferentes, podem precisar de agrupamento duplo
    $camposCategoricos = [
        'categoria_contratacao', 'area_requisitante', 'situacao_execucao', 
        'prioridade', 'urgente'
    ];
    
    // Se ambos são categóricos mas diferentes, agrupar por ambos
    if (in_array($campoX, $camposCategoricos) && in_array($campoY, $camposCategoricos)) {
        $groupByX = construirSelectCampoX($campoX);
        $groupByY = construirSelectCampoY($campoY);
        $groupBy = "$groupByX, $groupByY";
        error_log("obterGroupBy - Ambos categóricos diferentes, agrupando por: $groupBy");
        return $groupBy;
    }
    
    // Se Y é valor_total_contratacao, não agrupar (usar todas as linhas)
    if ($campoY === 'valor_total_contratacao') {
        $groupBy = construirSelectCampoX($campoX);
        error_log("obterGroupBy - Y é valor, agrupando apenas por X: $groupBy");
        return $groupBy;
    }
    
    // Se Y é quantidade_dfds, agrupar por X
    if ($campoY === 'quantidade_dfds') {
        $groupBy = construirSelectCampoX($campoX);
        error_log("obterGroupBy - Y é quantidade DFDs, agrupando por X: $groupBy");
        return $groupBy;
    }
    
    // Padrão: agrupar apenas por X
    $groupBy = construirSelectCampoX($campoX);
    error_log("obterGroupBy - Padrão, agrupando por X: $groupBy");
    return $groupBy;
}

/**
 * Obter ORDER BY baseado no campo Y
 */
function obterOrderBy($campoY) {
    if ($campoY === 'count') {
        return "valor DESC";
    } else {
        return "valor DESC";
    }
}

/**
 * Gerar cores padrão para gráficos
 */
function gerarCoresPadrao($quantidade) {
    $coresPadrao = [
        'rgba(54, 162, 235, 0.8)',   // Azul
        'rgba(255, 99, 132, 0.8)',   // Vermelho
        'rgba(255, 205, 86, 0.8)',   // Amarelo
        'rgba(75, 192, 192, 0.8)',   // Verde-água
        'rgba(153, 102, 255, 0.8)',  // Roxo
        'rgba(255, 159, 64, 0.8)',   // Laranja
        'rgba(199, 199, 199, 0.8)',  // Cinza
        'rgba(83, 102, 255, 0.8)',   // Azul-escuro
        'rgba(255, 99, 255, 0.8)',   // Rosa
        'rgba(99, 255, 132, 0.8)'    // Verde-claro
    ];
    
    $cores = [];
    for ($i = 0; $i < $quantidade; $i++) {
        $cores[] = $coresPadrao[$i % count($coresPadrao)];
    }
    
    return $cores;
}

/**
 * Salvar configuração de gráfico
 */
function salvarConfiguracao() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados JSON inválidos');
    }
    
    $nome = trim($input['nome'] ?? '');
    $tipoGrafico = $input['tipoGrafico'] ?? '';
    $campoX = $input['campoX'] ?? '';
    $campoY = $input['campoY'] ?? '';
    $filtroAno = $input['filtroAno'] ?? '';
    $filtroSituacao = $input['filtroSituacao'] ?? '';
    $filtroDataInicio = $input['filtroDataInicio'] ?? '';
    $filtroDataFim = $input['filtroDataFim'] ?? '';
    
    if (empty($nome)) {
        throw new Exception('Nome do gráfico é obrigatório');
    }
    
    // Verificar se usuário está logado
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('Usuário não está logado');
    }
    
    // Verificar se a tabela existe, se não, criar
    criarTabelaGraficosSalvos();
    
    $configuracao = json_encode([
        'tipoGrafico' => $tipoGrafico,
        'campoX' => $campoX,
        'campoY' => $campoY,
        'filtroAno' => $filtroAno,
        'filtroSituacao' => $filtroSituacao,
        'filtroDataInicio' => $filtroDataInicio,
        'filtroDataFim' => $filtroDataFim
    ]);
    
    $sql = "INSERT INTO graficos_salvos (usuario_id, nome, configuracao, criado_em, atualizado_em) 
            VALUES (?, ?, ?, NOW(), NOW())";
    
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([$_SESSION['usuario_id'], $nome, $configuracao]);
    
    if (!$resultado) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception('Erro ao inserir no banco: ' . $errorInfo[2]);
    }
    
    $novoId = $pdo->lastInsertId();
    
    // Verificar se realmente foi inserido
    $verificarStmt = $pdo->prepare("SELECT COUNT(*) FROM graficos_salvos WHERE id = ?");
    $verificarStmt->execute([$novoId]);
    $inserido = $verificarStmt->fetchColumn() > 0;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Gráfico salvo com sucesso', 
        'id' => $novoId,
        'verificado' => $inserido,
        'usuario_id' => $_SESSION['usuario_id'],
        'nome_salvo' => $nome
    ]);
}

/**
 * Criar tabela graficos_salvos se não existir
 */
function criarTabelaGraficosSalvos() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS graficos_salvos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        configuracao TEXT NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

/**
 * Carregar configurações salvas do usuário
 */
function carregarConfiguracoes() {
    global $pdo;
    
    // Verificar se usuário está logado
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('Usuário não está logado');
    }
    
    $sql = "SELECT id, nome, configuracao, criado_em, atualizado_em 
            FROM graficos_salvos 
            WHERE usuario_id = ? 
            ORDER BY atualizado_em DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['usuario_id']]);
    $configuracoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($configuracoes as &$config) {
        $config['configuracao'] = json_decode($config['configuracao'], true);
    }
    
    echo json_encode(['success' => true, 'configuracoes' => $configuracoes, 'total' => count($configuracoes), 'usuario_id' => $_SESSION['usuario_id']]);
}

/**
 * Excluir configuração
 */
function excluirConfiguracao() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Método não permitido');
    }
    
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        throw new Exception('ID da configuração é obrigatório');
    }
    
    $sql = "DELETE FROM graficos_salvos WHERE id = ? AND usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $_SESSION['usuario_id']]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Configuração não encontrada ou sem permissão');
    }
    
    echo json_encode(['success' => true, 'message' => 'Configuração excluída com sucesso']);
}
?>