<?php
/**
 * API de Integração com o PNCP (Portal Nacional de Contratações Públicas)
 * 
 * Funcionalidades:
 * - Sincronização de dados do PCA via API do PNCP
 * - Processamento de CSV da API
 * - Controle de duplicatas e atualizações
 * - Log de operações
 * 
 * URL da API: https://pncp.gov.br/api/pncp/v1/orgaos/00394544000185/pca/2026/csv
 */

require_once '../config.php';
require_once '../functions.php';

// Configurações da API do PNCP
define('PNCP_API_BASE_URL', 'https://pncp.gov.br/api/pncp/v1');
define('PNCP_ORGAO_CNPJ', '00394544000185'); // Ministério da Saúde
define('PNCP_TIMEOUT', 60); // Timeout em segundos

/**
 * Classe para integração com API do PNCP
 */
class PNCPIntegration {
    
    private $pdo;
    private $log = [];
    
    public function __construct() {
        $this->pdo = conectarDB();
    }
    
    /**
     * Sincronizar dados do PCA de um ano específico
     */
    public function sincronizarPCA($ano, $usuario_id = null, $tipo = 'manual') {
        $inicio = microtime(true);
        $sincronizacao_id = $this->iniciarSincronizacao($ano, $usuario_id, $tipo);
        
        try {
            // Construir URL da API
            $url = PNCP_API_BASE_URL . "/orgaos/" . PNCP_ORGAO_CNPJ . "/pca/{$ano}/csv";
            
            $this->log("Iniciando sincronização do PCA {$ano} via API do PNCP");
            $this->log("URL: {$url}");
            
            // Fazer download do CSV
            $csv_data = $this->baixarCSV($url);
            
            if (!$csv_data) {
                throw new Exception("Falha ao baixar dados da API do PNCP");
            }
            
            $this->log("CSV baixado com sucesso. Tamanho: " . strlen($csv_data) . " bytes");
            
            // Processar CSV
            $resultado = $this->processarCSV($csv_data, $ano);
            
            $tempo_total = round(microtime(true) - $inicio, 2);
            
            // Finalizar sincronização
            $this->finalizarSincronizacao($sincronizacao_id, 'concluida', $resultado, $tempo_total, strlen($csv_data));
            
            $this->log("Sincronização concluída em {$tempo_total} segundos");
            
            return [
                'sucesso' => true,
                'sincronizacao_id' => $sincronizacao_id,
                'total_processados' => $resultado['total_processados'],
                'novos' => $resultado['novos'],
                'atualizados' => $resultado['atualizados'],
                'ignorados' => $resultado['ignorados'],
                'tempo' => $tempo_total,
                'tamanho_csv' => strlen($csv_data),
                'log' => $this->log
            ];
            
        } catch (Exception $e) {
            $tempo_total = round(microtime(true) - $inicio, 2);
            $this->log("ERRO: " . $e->getMessage());
            
            $this->finalizarSincronizacao($sincronizacao_id, 'erro', null, $tempo_total, 0, $e->getMessage());
            
            return [
                'sucesso' => false,
                'sincronizacao_id' => $sincronizacao_id,
                'erro' => $e->getMessage(),
                'tempo' => $tempo_total,
                'log' => $this->log
            ];
        }
    }
    
    /**
     * Baixar CSV da API do PNCP
     */
    private function baixarCSV($url) {
        $this->log("Fazendo requisição para: {$url}");
        
        // Configurar contexto HTTP
        $context = stream_context_create([
            'http' => [
                'timeout' => PNCP_TIMEOUT,
                'user_agent' => 'Sistema CGLIC/2.0 (Ministerio da Saude)',
                'header' => [
                    'Accept: text/csv,application/csv,*/*',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: close'
                ]
            ]
        ]);
        
        // Tentar download
        $csv_data = @file_get_contents($url, false, $context);
        
        if ($csv_data === false) {
            // Tentar com cURL como fallback
            return $this->baixarCSVcomCURL($url);
        }
        
        return $csv_data;
    }
    
    /**
     * Fallback para download via cURL
     */
    private function baixarCSVcomCURL($url) {
        if (!function_exists('curl_init')) {
            throw new Exception("cURL não está disponível e file_get_contents falhou");
        }
        
        $this->log("Tentando download via cURL...");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, PNCP_TIMEOUT);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Sistema CGLIC/2.0 (Ministerio da Saude)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/csv,application/csv,*/*'
        ]);
        
        $csv_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($csv_data === false || !empty($error)) {
            throw new Exception("Erro cURL: " . $error);
        }
        
        if ($http_code !== 200) {
            throw new Exception("API retornou código HTTP: {$http_code}");
        }
        
        return $csv_data;
    }
    
    /**
     * Processar dados do CSV
     */
    private function processarCSV($csv_data, $ano) {
        $this->log("Processando dados do CSV...");
        
        // Detectar encoding e converter se necessário
        $csv_data = $this->detectarEConverterEncoding($csv_data);
        
        // Converter CSV em array
        $linhas = str_getcsv($csv_data, "\n");
        
        if (empty($linhas)) {
            throw new Exception("CSV vazio ou inválido");
        }
        
        // Primeira linha deve conter os cabeçalhos
        $cabecalhos = str_getcsv(array_shift($linhas));
        $this->log("Cabeçalhos encontrados: " . implode(', ', $cabecalhos));
        
        // Mapear cabeçalhos para campos da tabela
        $mapeamento = $this->mapearCamposPNCP($cabecalhos);
        
        $total_processados = 0;
        $novos = 0;
        $atualizados = 0;
        $ignorados = 0;
        
        $this->pdo->beginTransaction();
        
        try {
            foreach ($linhas as $numero_linha => $linha) {
                if (empty(trim($linha))) continue;
                
                $campos = str_getcsv($linha);
                
                // Log da primeira linha para debug
                if ($total_processados === 0) {
                    $this->log("DEBUG - Primeira linha de dados:");
                    $this->log("Campos: " . implode(' | ', array_slice($campos, 0, 5)));
                }
                
                // Processar linha
                $resultado_linha = $this->processarLinhaPNCP($campos, $mapeamento, $ano);
                
                // Log detalhado das primeiras 3 linhas
                if ($total_processados < 3) {
                    $this->log("DEBUG - Linha {$total_processados}: resultado = {$resultado_linha}");
                }
                
                switch ($resultado_linha) {
                    case 'novo':
                        $novos++;
                        break;
                    case 'atualizado':
                        $atualizados++;
                        break;
                    case 'ignorado':
                        $ignorados++;
                        break;
                }
                
                $total_processados++;
                
                // Log de progresso a cada 1000 registros (menos frequente)
                if ($total_processados % 1000 === 0) {
                    $this->log("Processados {$total_processados} registros... (Novos: {$novos}, Atualizados: {$atualizados}, Ignorados: {$ignorados})");
                }
            }
            
            $this->pdo->commit();
            
            $this->log("Processamento concluído:");
            $this->log("- Total processados: {$total_processados}");
            $this->log("- Novos registros: {$novos}");
            $this->log("- Registros atualizados: {$atualizados}");
            $this->log("- Registros ignorados: {$ignorados}");
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        
        return [
            'total_processados' => $total_processados,
            'novos' => $novos,
            'atualizados' => $atualizados,
            'ignorados' => $ignorados
        ];
    }
    
    /**
     * Processar uma linha individual do CSV
     */
    private function processarLinhaPNCP($campos, $mapeamento, $ano) {
        // Criar array associativo com os dados
        $dados = [];
        
        foreach ($mapeamento as $indice => $campo_bd) {
            $dados[$campo_bd] = isset($campos[$indice]) ? trim($campos[$indice]) : null;
        }
        
        // Debug: verificar se temos dados mapeados
        $campos_preenchidos = 0;
        foreach ($dados as $key => $value) {
            if (!empty($value)) {
                $campos_preenchidos++;
            }
        }
        
        // Se não conseguiu mapear nenhum campo, criar sequencial a partir do índice da linha
        if ($campos_preenchidos < 2) {
            // Usar índice da linha como sequencial temporário
            static $contador_linha = 0;
            $contador_linha++;
            $dados['sequencial'] = $contador_linha;
            
            // Pegar dados diretamente dos campos originais (fallback)
            // Baseado nos cabeçalhos reais: Unidade Responsável;UASG;Id do item no PCA;Categoria do Item;Identificador da Futura Contratação;Nome da Futura Contratação;Catálogo Utilizado;Classificação do Catálogo;Código da Classificação Superior (Classe/Grupo);Nome da Classificação Superior (Classe/Grupo);Código do PDM do Item;Nome do PDM do Item;Código do Item;Descrição do Item;Unidade de Fornecimento;Quantidade Estimada;Valor Unitário Estimado (R$);Valor Total Estimado (R$);Valor orçamentário estimado para o exercício (R$);Data Desejada
            if (count($campos) >= 18) {
                $dados['sequencial'] = $campos[2] ?? $contador_linha; // Id do item no PCA
                $dados['categoria_item'] = $campos[3] ?? 'N/A'; // Categoria do Item
                $dados['codigo_pncp'] = $campos[4] ?? ''; // Identificador da Futura Contratação
                $dados['descricao_item'] = $campos[5] ?? 'Item PNCP'; // Nome da Futura Contratação
                $dados['subcategoria_item'] = $campos[6] ?? ''; // Catálogo Utilizado
                $dados['justificativa'] = $campos[13] ?? ''; // Descrição do Item
                $dados['unidade_medida'] = $campos[14] ?? ''; // Unidade de Fornecimento
                $dados['quantidade'] = floatval($campos[15] ?? 0); // Quantidade Estimada
                
                // Processar valores monetários (formato brasileiro: 1.234.567,89)
                $valor_unitario = str_replace(['.', ','], ['', '.'], $campos[16] ?? '0');
                $valor_total = str_replace(['.', ','], ['', '.'], $campos[17] ?? '0');
                $dados['valor_estimado'] = floatval($valor_total) > 0 ? floatval($valor_total) : floatval($valor_unitario);
                
                $dados['unidade_requisitante'] = $campos[0] ?? 'PNCP'; // Unidade Responsável
                $dados['endereco_unidade'] = $campos[1] ?? ''; // UASG
                $dados['observacoes'] = $campos[7] ?? ''; // Classificação do Catálogo
                
                // Data desejada
                if (!empty($campos[19])) {
                    try {
                        $data_desejada = DateTime::createFromFormat('d/m/Y', $campos[19]);
                        if ($data_desejada) {
                            $dados['data_ultima_atualizacao'] = $data_desejada->format('Y-m-d');
                        }
                    } catch (Exception $e) {
                        // Ignorar erro de data
                    }
                }
            }
        }
        
        // Validação mínima - apenas verificar se tem sequencial e descrição
        if (empty($dados['sequencial']) && empty($dados['descricao_item'])) {
            return 'ignorado';
        }
        
        // Garantir que sequencial existe
        if (empty($dados['sequencial'])) {
            $dados['sequencial'] = uniqid('pncp_');
        }
        
        // Adicionar dados fixos
        $dados['orgao_cnpj'] = PNCP_ORGAO_CNPJ;
        $dados['ano_pca'] = $ano;
        $dados['data_sincronizacao'] = date('Y-m-d H:i:s');
        
        // Gerar hash dos dados para controle de mudanças
        $dados['hash_dados'] = md5(json_encode($dados));
        
        // Salvar dados originais em JSON
        $dados['dados_originais_json'] = json_encode($campos, JSON_UNESCAPED_UNICODE);
        
        // Verificar se registro já existe
        $sql_check = "SELECT id, hash_dados FROM pca_pncp 
                      WHERE orgao_cnpj = ? AND ano_pca = ? AND sequencial = ?";
        $stmt_check = $this->pdo->prepare($sql_check);
        $stmt_check->execute([PNCP_ORGAO_CNPJ, $ano, $dados['sequencial']]);
        $registro_existente = $stmt_check->fetch();
        
        if ($registro_existente) {
            // Verificar se houve mudanças
            if ($registro_existente['hash_dados'] === $dados['hash_dados']) {
                return 'ignorado'; // Sem mudanças
            }
            
            // Atualizar registro existente
            $this->atualizarRegistroPNCP($registro_existente['id'], $dados);
            return 'atualizado';
            
        } else {
            // Inserir novo registro
            $this->inserirRegistroPNCP($dados);
            return 'novo';
        }
    }
    
    /**
     * Inserir novo registro na tabela pca_pncp
     */
    private function inserirRegistroPNCP($dados) {
        $campos_sql = implode(', ', array_keys($dados));
        $placeholders = ':' . implode(', :', array_keys($dados));
        
        $sql = "INSERT INTO pca_pncp ({$campos_sql}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($dados);
    }
    
    /**
     * Atualizar registro existente na tabela pca_pncp
     */
    private function atualizarRegistroPNCP($id, $dados) {
        unset($dados['criado_em']); // Não atualizar data de criação
        $dados['atualizado_em'] = date('Y-m-d H:i:s');
        
        $sets = [];
        foreach (array_keys($dados) as $campo) {
            $sets[] = "{$campo} = :{$campo}";
        }
        
        $sql = "UPDATE pca_pncp SET " . implode(', ', $sets) . " WHERE id = :id";
        $dados['id'] = $id;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($dados);
    }
    
    /**
     * Mapear cabeçalhos do CSV para campos da tabela
     */
    private function mapearCamposPNCP($cabecalhos) {
        // Mapeamento dos cabeçalhos REAIS da API do PNCP baseado na resposta obtida
        $mapeamento_padrao = [
            // Campos identificados no CSV do PNCP
            'id do item no pca' => 'sequencial',
            'identificador da futura contratacao' => 'codigo_pncp',
            'categoria do item' => 'categoria_item',
            'nome da futura contratacao' => 'descricao_item',
            'descricao do item' => 'justificativa',
            'unidade de fornecimento' => 'unidade_medida',
            'quantidade estimada' => 'quantidade',
            'valor unitario estimado (r$)' => 'valor_estimado',
            'valor total estimado (r$)' => 'valor_estimado',
            'valor orcamentario estimado para o exercicio (r$)' => 'valor_estimado',
            'data desejada' => 'data_ultima_atualizacao',
            'unidade responsavel' => 'unidade_requisitante',
            'uasg' => 'endereco_unidade',
            'catalogo utilizado' => 'subcategoria_item',
            'classificacao do catalogo' => 'observacoes',
            'codigo da classificacao superior (classe/grupo)' => 'codigo_pncp',
            'nome da classificacao superior (classe/grupo)' => 'categoria_item',
            'codigo do pdm do item' => 'codigo_pncp',
            'nome do pdm do item' => 'subcategoria_item',
            'codigo do item' => 'codigo_pncp',
            
            // Mapeamentos alternativos (fallback)
            'sequencial' => 'sequencial',
            'item' => 'sequencial',
            'categoria' => 'categoria_item',
            'subcategoria' => 'subcategoria_item',
            'descrição' => 'descricao_item',
            'descricao' => 'descricao_item',
            'justificativa' => 'justificativa',
            'valor' => 'valor_estimado',
            'valor_estimado' => 'valor_estimado',
            'unidade' => 'unidade_medida',
            'quantidade' => 'quantidade',
            'modalidade' => 'modalidade_licitacao',
            'trimestre' => 'trimestre_previsto',
            'mês' => 'mes_previsto',
            'mes' => 'mes_previsto',
            'situação' => 'situacao_item',
            'situacao' => 'situacao_item',
            'código' => 'codigo_pncp',
            'codigo' => 'codigo_pncp',
            'unidade_requisitante' => 'unidade_requisitante',
            'endereço' => 'endereco_unidade',
            'endereco' => 'endereco_unidade',
            'responsável' => 'responsavel_demanda',
            'responsavel' => 'responsavel_demanda',
            'email' => 'email_responsavel',
            'telefone' => 'telefone_responsavel',
            'observações' => 'observacoes',
            'observacoes' => 'observacoes'
        ];
        
        $mapeamento = [];
        
        foreach ($cabecalhos as $indice => $cabecalho) {
            $cabecalho_limpo = strtolower(trim($cabecalho));
            $cabecalho_limpo = preg_replace('/[^a-záéíóúàèìòùâêîôûãõç_]/u', '', $cabecalho_limpo);
            
            if (isset($mapeamento_padrao[$cabecalho_limpo])) {
                $mapeamento[$indice] = $mapeamento_padrao[$cabecalho_limpo];
            }
        }
        
        $this->log("Mapeamento de campos: " . json_encode($mapeamento, JSON_UNESCAPED_UNICODE));
        
        return $mapeamento;
    }
    
    /**
     * Detectar e converter encoding do CSV
     */
    private function detectarEConverterEncoding($data) {
        $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252'];
        
        foreach ($encodings as $encoding) {
            if (mb_check_encoding($data, $encoding)) {
                if ($encoding !== 'UTF-8') {
                    $this->log("Convertendo encoding de {$encoding} para UTF-8");
                    return mb_convert_encoding($data, 'UTF-8', $encoding);
                }
                return $data;
            }
        }
        
        // Fallback: assumir ISO-8859-1
        $this->log("Encoding não detectado, assumindo ISO-8859-1");
        return mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
    }
    
    /**
     * Iniciar registro de sincronização
     */
    private function iniciarSincronizacao($ano, $usuario_id, $tipo) {
        $url = PNCP_API_BASE_URL . "/orgaos/" . PNCP_ORGAO_CNPJ . "/pca/{$ano}/csv";
        
        $sql = "INSERT INTO pca_pncp_sincronizacoes 
                (orgao_cnpj, ano_pca, url_api, tipo_sincronizacao, status, usuario_id, ip_origem) 
                VALUES (?, ?, ?, ?, 'iniciada', ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            PNCP_ORGAO_CNPJ,
            $ano,
            $url,
            $tipo,
            $usuario_id,
            $_SERVER['REMOTE_ADDR'] ?? 'localhost'
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Finalizar registro de sincronização
     */
    private function finalizarSincronizacao($id, $status, $resultado, $tempo, $tamanho_csv, $erro = null) {
        $sql = "UPDATE pca_pncp_sincronizacoes SET 
                status = ?, 
                total_registros_api = ?,
                registros_processados = ?,
                registros_novos = ?,
                registros_atualizados = ?,
                registros_ignorados = ?,
                tempo_processamento = ?,
                tamanho_arquivo_csv = ?,
                mensagem_erro = ?,
                detalhes_execucao = ?,
                finalizada_em = NOW()
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $status,
            $resultado ? $resultado['total_processados'] : 0,
            $resultado ? $resultado['total_processados'] : 0,
            $resultado ? $resultado['novos'] : 0,
            $resultado ? $resultado['atualizados'] : 0,
            $resultado ? $resultado['ignorados'] : 0,
            $tempo,
            $tamanho_csv,
            $erro,
            json_encode($this->log, JSON_UNESCAPED_UNICODE),
            $id
        ]);
    }
    
    /**
     * Obter estatísticas dos dados do PNCP
     */
    public function obterEstatisticasPNCP($ano) {
        $sql = "SELECT 
                    COUNT(*) as total_registros,
                    COUNT(DISTINCT categoria_item) as total_categorias,
                    COUNT(DISTINCT modalidade_licitacao) as total_modalidades,
                    SUM(valor_estimado) as valor_total,
                    COUNT(CASE WHEN situacao_item = 'Planejado' THEN 1 END) as planejados,
                    COUNT(CASE WHEN situacao_item = 'Em andamento' THEN 1 END) as em_andamento,
                    COUNT(CASE WHEN situacao_item = 'Concluído' THEN 1 END) as concluidos,
                    MAX(data_sincronizacao) as ultima_sincronizacao
                FROM pca_pncp 
                WHERE ano_pca = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ano]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Comparar dados internos com dados do PNCP
     */
    public function compararComDadosInternos($ano) {
        $sql = "SELECT 
                    'PNCP' as origem,
                    COUNT(*) as total_registros,
                    SUM(valor_estimado) as valor_total
                FROM pca_pncp WHERE ano_pca = ?
                
                UNION ALL
                
                SELECT 
                    'Interno' as origem,
                    COUNT(DISTINCT numero_dfd) as total_registros,
                    SUM(DISTINCT valor_total_contratacao) as valor_total
                FROM pca_dados 
                WHERE importacao_id IN (
                    SELECT id FROM pca_importacoes WHERE ano_pca = ?
                )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ano, $ano]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Adicionar mensagem ao log
     */
    private function log($mensagem) {
        $timestamp = date('H:i:s');
        $this->log[] = "[{$timestamp}] {$mensagem}";
    }
    
    /**
     * Obter histórico de sincronizações
     */
    public function obterHistoricoSincronizacoes($limite = 20) {
        $sql = "SELECT * FROM pca_pncp_sincronizacoes 
                ORDER BY iniciada_em DESC 
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limite]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Resposta da API via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarLogin();
    verifyCSRFToken();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $acao = $_POST['acao'] ?? '';
    $ano = intval($_POST['ano'] ?? 2026);
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    
    $pncp = new PNCPIntegration();
    
    try {
        switch ($acao) {
            case 'sincronizar':
                if (!temPermissao('pca_importar')) {
                    throw new Exception("Sem permissão para sincronizar dados");
                }
                
                $resultado = $pncp->sincronizarPCA($ano, $usuario_id, 'manual');
                echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'estatisticas':
                $stats = $pncp->obterEstatisticasPNCP($ano);
                echo json_encode(['sucesso' => true, 'estatisticas' => $stats], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'comparar':
                $comparacao = $pncp->compararComDadosInternos($ano);
                echo json_encode(['sucesso' => true, 'comparacao' => $comparacao], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'historico':
                $historico = $pncp->obterHistoricoSincronizacoes(20);
                echo json_encode(['sucesso' => true, 'historico' => $historico], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception("Ação não reconhecida: {$acao}");
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'sucesso' => false,
            'erro' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

// Resposta da API via GET (apenas consultas)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    verificarLogin();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $acao = $_GET['acao'] ?? '';
    $ano = intval($_GET['ano'] ?? 2026);
    
    $pncp = new PNCPIntegration();
    
    try {
        switch ($acao) {
            case 'estatisticas':
                $stats = $pncp->obterEstatisticasPNCP($ano);
                echo json_encode(['sucesso' => true, 'estatisticas' => $stats], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'historico':
                $historico = $pncp->obterHistoricoSincronizacoes(20);
                echo json_encode(['sucesso' => true, 'historico' => $historico], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                echo json_encode(['sucesso' => false, 'erro' => 'Ação não permitida via GET'], JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'sucesso' => false,
            'erro' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
?>