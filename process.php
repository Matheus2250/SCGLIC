<?php
require_once 'config.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$acao = $_POST['acao'] ?? '';
$pdo = conectarDB();

switch ($acao) {
    case 'login':
        $email = limpar($_POST['email']);
        $senha = $_POST['senha'];
        
        $sql = "SELECT id, nome, email, senha, tipo_usuario FROM usuarios WHERE email = ? AND ativo = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];
            
            registrarLog('LOGIN', 'Usuário fez login no sistema');
            header('Location: selecao_modulos.php');
        } else {
            setMensagem('E-mail ou senha incorretos!', 'erro');
            header('Location: index.php');
        }
        break;
        
    case 'cadastro':
        $nome = limpar($_POST['nome']);
        $email = limpar($_POST['email']);
        $senha = $_POST['senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        
        // Validações
        if (strlen($nome) < 3) {
            setMensagem('Nome deve ter pelo menos 3 caracteres!', 'erro');
            header('Location: index.php');
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setMensagem('E-mail inválido!', 'erro');
            header('Location: index.php');
            exit;
        }
        
        if (strlen($senha) < 6) {
            setMensagem('Senha deve ter pelo menos 6 caracteres!', 'erro');
            header('Location: index.php');
            exit;
        }
        
        if ($senha !== $confirmar_senha) {
            setMensagem('As senhas não coincidem!', 'erro');
            header('Location: index.php');
            exit;
        }
        
        // Verificar se e-mail já existe
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            setMensagem('E-mail já cadastrado!', 'erro');
            header('Location: index.php');
            exit;
        }
        
        // Inserir usuário
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$nome, $email, $senha_hash])) {
            setMensagem('Cadastro realizado com sucesso! Faça login.');
            header('Location: index.php');
        } else {
            setMensagem('Erro ao cadastrar. Tente novamente!', 'erro');
            header('Location: index.php');
        }
        break;
        
    case 'importar_pca':
        verificarLogin();
        
        if (!isset($_FILES['arquivo_pca'])) {
            setMensagem('Nenhum arquivo selecionado!', 'erro');
            header('Location: dashboard.php');
            exit;
        }
        
        $resultado = processarUpload($_FILES['arquivo_pca']);
        
        if (!$resultado['sucesso']) {
            setMensagem($resultado['mensagem'], 'erro');
            header('Location: dashboard.php');
            exit;
        }
        
        // Processar o arquivo CSV
$arquivo = $resultado['caminho'];

// Detectar e corrigir encoding antes de abrir
$conteudo_original = file_get_contents($arquivo);
$encoding = mb_detect_encoding($conteudo_original, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'CP1252'], true);

if ($encoding !== 'UTF-8') {
    $conteudo_utf8 = mb_convert_encoding($conteudo_original, 'UTF-8', $encoding);
    file_put_contents($arquivo, $conteudo_utf8);
}

$handle = fopen($arquivo, 'r');
if (!$handle) {
    setMensagem('Erro ao abrir arquivo!', 'erro');
    header('Location: dashboard.php');
    exit;
}
        
        // Criar registro de importação
        $sql = "INSERT INTO pca_importacoes (nome_arquivo, usuario_id) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$resultado['arquivo'], $_SESSION['usuario_id']]);
        $importacao_id = $pdo->lastInsertId();
        
        // Detectar separador automaticamente
$primeira_linha = fgets($handle);
rewind($handle);
$separador = ';';
if (substr_count($primeira_linha, ',') > substr_count($primeira_linha, ';')) {
    $separador = ',';
}

// Ler cabeçalho
$header = fgetcsv($handle, 0, $separador);
        
        // Processar linhas
        $linhas_processadas = 0;
        $linhas_novas = 0;
        $linhas_atualizadas = 0;
        $linhas_ignoradas = 0;
        $erros = [];
        
        // Buscar última importação para comparação
        $sql_ultima_importacao = "SELECT MAX(id) as ultima_id FROM pca_importacoes WHERE id < ?";
        $stmt_ultima = $pdo->prepare($sql_ultima_importacao);
        $stmt_ultima->execute([$importacao_id]);
        $ultima_importacao_id = $stmt_ultima->fetch()['ultima_id'];
        
        // Preparar statements
        $sql_verifica = "SELECT * FROM pca_dados 
                        WHERE numero_contratacao = ? 
                        AND codigo_material_servico = ?
                        AND importacao_id = ?";
        $stmt_verifica = $pdo->prepare($sql_verifica);
        
        $sql = "INSERT INTO pca_dados (
            importacao_id, numero_contratacao, status_contratacao, situacao_execucao,
            titulo_contratacao, categoria_contratacao, uasg_atual, valor_total_contratacao,
            data_inicio_processo, data_conclusao_processo, prazo_duracao_dias,
            area_requisitante, numero_dfd, prioridade, numero_item_dfd,
            data_conclusao_dfd, classificacao_contratacao, codigo_classe_grupo,
            nome_classe_grupo, codigo_pdm_material, nome_pdm_material,
            codigo_material_servico, descricao_material_servico, unidade_fornecimento,
            valor_unitario, quantidade, valor_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        // Histórico
        $sql_historico = "INSERT INTO pca_historico (numero_contratacao, campo_alterado, valor_anterior, valor_novo, importacao_id, usuario_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_historico = $pdo->prepare($sql_historico);
        
        // Estados
        $sql_estado = "INSERT INTO pca_estados_tempo (numero_contratacao, situacao_execucao, data_inicio) VALUES (?, ?, CURDATE())";
        $stmt_estado = $pdo->prepare($sql_estado);
        
        $sql_update_estado = "UPDATE pca_estados_tempo SET data_fim = CURDATE(), dias_no_estado = DATEDIFF(CURDATE(), data_inicio), ativo = FALSE WHERE numero_contratacao = ? AND ativo = TRUE";
        $stmt_update_estado = $pdo->prepare($sql_update_estado);
        
        while (($linha = fgetcsv($handle, 0, $separador)) !== FALSE) {
    // Limpar encoding de cada campo individualmente
    $linha = array_map(function($campo) {
        if (!is_string($campo)) return $campo;
        // Remove BOM se presente
        $campo = str_replace("\xEF\xBB\xBF", '', $campo);
        // Garante UTF-8 válido
        return mb_convert_encoding($campo, 'UTF-8', 'UTF-8');
    }, $linha);
            // Pular linhas vazias
            if (empty($linha[0])) continue;
            
            $linhas_processadas++;
            
            try {
                // Mapear dados das colunas com validação
                $numero_contratacao = trim($linha[0] ?? '');
                $status_contratacao = trim($linha[1] ?? '');
                $situacao_execucao = trim($linha[2] ?? '') ?: 'Não iniciado';
                $titulo_contratacao = trim($linha[3] ?? '');
                $categoria_contratacao = trim($linha[4] ?? '');
                $uasg_atual = trim($linha[5] ?? '');
                
                // Processar valor_total_contratacao (coluna 6)
                $valor_total_contratacao = processarValorMonetario($linha[6] ?? '');
                
                // Processar datas (colunas 7 e 8)
                $data_inicio = formatarDataDB($linha[7] ?? '');
                $data_conclusao = formatarDataDB($linha[8] ?? '');
                
                $prazo_duracao_dias = !empty($linha[9]) ? intval($linha[9]) : null;
                $area_requisitante = trim($linha[10] ?? '');
                $numero_dfd = trim($linha[11] ?? '');
                $prioridade = trim($linha[12] ?? '');
                $numero_item_dfd = trim($linha[13] ?? '');
                
                // Processar data_conclusao_dfd (coluna 14)
                $data_conclusao_dfd = formatarDataDB($linha[14] ?? '');
                
                $classificacao_contratacao = trim($linha[15] ?? '');
                $codigo_classe_grupo = trim($linha[16] ?? '');
                $nome_classe_grupo = trim($linha[17] ?? '');
                $codigo_pdm_material = trim($linha[18] ?? '');
                $nome_pdm_material = trim($linha[19] ?? '');
                $codigo_material_servico = trim($linha[20] ?? '');
                $descricao_material_servico = trim($linha[21] ?? '');
                $unidade_fornecimento = trim($linha[22] ?? '');
                
                // Processar valor_unitario (coluna 23)
                $valor_unitario = processarValorMonetario($linha[23] ?? '');
                
                $quantidade = !empty($linha[24]) ? intval($linha[24]) : null;
                
                // Processar valor_total (coluna 25)
                $valor_total = processarValorMonetario($linha[25] ?? '');
                
                // Verificar se existe na última importação
                $dados_anteriores = null;
                if ($ultima_importacao_id && !empty($codigo_material_servico)) {
                    $stmt_verifica->execute([$numero_contratacao, $codigo_material_servico, $ultima_importacao_id]);
                    $dados_anteriores = $stmt_verifica->fetch();
                }
                
                $deve_inserir = false;
                $mudancas = [];
                
                if (!$dados_anteriores) {
                    // É uma contratação nova
                    $deve_inserir = true;
                    $linhas_novas++;
                    
                    // Iniciar estado para nova contratação
                    if (!empty($numero_contratacao)) {
                        $stmt_estado->execute([$numero_contratacao, $situacao_execucao]);
                    }
                } else {
                    // Verificar o que mudou
                    $campos_verificar = [
                        'situacao_execucao' => ['atual' => $situacao_execucao, 'nome' => 'Situação'],
                        'status_contratacao' => ['atual' => $status_contratacao, 'nome' => 'Status'],
                        'valor_total_contratacao' => ['atual' => $valor_total_contratacao, 'nome' => 'Valor Total'],
                        'titulo_contratacao' => ['atual' => $titulo_contratacao, 'nome' => 'Título'],
                        'prioridade' => ['atual' => $prioridade, 'nome' => 'Prioridade'],
                        'data_inicio_processo' => ['atual' => $data_inicio, 'nome' => 'Data Início'],
                        'data_conclusao_processo' => ['atual' => $data_conclusao, 'nome' => 'Data Conclusão']
                    ];
                    
                    foreach ($campos_verificar as $campo => $info) {
                        if ($dados_anteriores[$campo] != $info['atual']) {
                            $mudancas[] = [
                                'campo' => $campo,
                                'nome' => $info['nome'],
                                'anterior' => $dados_anteriores[$campo],
                                'novo' => $info['atual']
                            ];
                            $deve_inserir = true;
                        }
                    }
                    
                    if ($deve_inserir) {
                        $linhas_atualizadas++;
                        
                        // Registrar mudanças no histórico
                        foreach ($mudancas as $mudanca) {
                            $stmt_historico->execute([
                                $numero_contratacao,
                                $mudanca['campo'],
                                $mudanca['anterior'],
                                $mudanca['novo'],
                                $importacao_id,
                                $_SESSION['usuario_id']
                            ]);
                            
                            // Se mudou situação, atualizar estados
                            if ($mudanca['campo'] == 'situacao_execucao') {
                                $stmt_update_estado->execute([$numero_contratacao]);
                                $stmt_estado->execute([$numero_contratacao, $mudanca['novo']]);
                            }
                        }
                    } else {
                        $linhas_ignoradas++;
                    }
                }
                
                // Inserir apenas se necessário
                if ($deve_inserir) {
                    $params = [
                        $importacao_id,
                        $numero_contratacao,
                        $status_contratacao,
                        $situacao_execucao,
                        $titulo_contratacao,
                        $categoria_contratacao,
                        $uasg_atual,
                        $valor_total_contratacao,
                        $data_inicio,
                        $data_conclusao,
                        $prazo_duracao_dias,
                        $area_requisitante,
                        $numero_dfd,
                        $prioridade,
                        $numero_item_dfd,
                        $data_conclusao_dfd,
                        $classificacao_contratacao,
                        $codigo_classe_grupo,
                        $nome_classe_grupo,
                        $codigo_pdm_material,
                        $nome_pdm_material,
                        $codigo_material_servico,
                        $descricao_material_servico,
                        $unidade_fornecimento,
                        $valor_unitario,
                        $quantidade,
                        $valor_total
                    ];
                    
                    if (!$stmt->execute($params)) {
                        $erros[] = "Linha $linhas_processadas: Erro ao inserir registro";
                    }
                }
                
            } catch (Exception $e) {
                $erros[] = "Linha $linhas_processadas: " . $e->getMessage();
                continue;
            }
        }
        
        fclose($handle);
        
        // Mensagem detalhada
        $mensagem = "Importação concluída! ";
        $mensagem .= "Processadas: $linhas_processadas | ";
        $mensagem .= "Novas: $linhas_novas | ";
        $mensagem .= "Atualizadas: $linhas_atualizadas | ";
        $mensagem .= "Sem alterações: $linhas_ignoradas";
        
        if (!empty($erros)) {
            $mensagem .= " | Erros: " . count($erros);
            // Log dos erros
            foreach (array_slice($erros, 0, 5) as $erro) {
                error_log("Importação PCA - $erro");
            }
        }
        
        registrarLog('IMPORTACAO_PCA', $mensagem, 'pca_importacoes', $importacao_id);
        setMensagem($mensagem);
        
        header('Location: dashboard.php');
        break;
        
    case 'criar_licitacao':
    verificarLogin();
    
    // Validar NUP
    if (!validarNUP($_POST['nup'])) {
        setMensagem('Formato do NUP inválido! Use: xxxxx.xxxxxx/xxxx-xx', 'erro');
        header('Location: licitacao_dashboard.php');
        exit;
    }
    
    // Buscar ID do PCA baseado no número da contratação
    $sql_pca = "SELECT id FROM pca_dados WHERE numero_contratacao = ? LIMIT 1";
    $stmt_pca = $pdo->prepare($sql_pca);
    $stmt_pca->execute([$_POST['numero_contratacao']]);
    $pca_dados = $stmt_pca->fetch();
    
    if (!$pca_dados) {
        setMensagem('Contratação não encontrada no PCA!', 'erro');
        header('Location: licitacao_dashboard.php');
        exit;
    }
    
    // Processar dados do formulário
    $nup = limpar($_POST['nup']);
    $data_entrada_dipli = formatarDataDB($_POST['data_entrada_dipli']);
    $resp_instrucao = limpar($_POST['resp_instrucao']);
    $area_demandante = limpar($_POST['area_demandante']);
    $pregoeiro = limpar($_POST['pregoeiro']);
    $modalidade = $_POST['modalidade'];
    $tipo = $_POST['tipo'];
    $numero = intval($_POST['numero'] ?? 0);
    $ano = intval($_POST['ano'] ?? date('Y'));
    $objeto = limpar($_POST['objeto']);
    $valor_estimado = formatarValorDB($_POST['valor_estimado']);
    $data_abertura = formatarDataDB($_POST['data_abertura']);
    $data_homologacao = formatarDataDB($_POST['data_homologacao']);
    $valor_homologado = formatarValorDB($_POST['valor_homologado']);
    $economia = formatarValorDB($_POST['economia']);
    $link = limpar($_POST['link']);
    $situacao = $_POST['situacao'];
    
    $sql = "INSERT INTO licitacoes (
        pca_dados_id, nup, data_entrada_dipli, resp_instrucao, area_demandante,
        pregoeiro, modalidade, tipo, numero, ano, objeto, valor_estimado,
        data_abertura, data_homologacao, valor_homologado, economia, link,
        situacao, usuario_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        $pca_dados['id'], $nup, $data_entrada_dipli, $resp_instrucao, $area_demandante,
        $pregoeiro, $modalidade, $tipo, $numero, $ano, $objeto, $valor_estimado,
        $data_abertura, $data_homologacao, $valor_homologado, $economia, $link,
        $situacao, $_SESSION['usuario_id']
    ];
    
    if ($stmt->execute($params)) {
        $licitacao_id = $pdo->lastInsertId();
        registrarLog('CRIAR_LICITACAO', "Criou licitação NUP: $nup", 'licitacoes', $licitacao_id);
        setMensagem('Licitação criada com sucesso!');
    } else {
        setMensagem('Erro ao criar licitação!', 'erro');
    }
    
    header('Location: licitacao_dashboard.php');
    break;
        
    case 'editar_licitacao':
        verificarLogin();
        
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];
        
        try {
            // Validar ID
            if (empty($_POST['id'])) {
                throw new Exception('ID da licitação não fornecido');
            }
            
            // Validar NUP
            if (!validarNUP($_POST['nup'])) {
                throw new Exception('Formato do NUP inválido! Use: xxxxx.xxxxxx/xxxx-xx');
            }
            
            // Processar dados
            $id = intval($_POST['id']);
            $nup = limpar($_POST['nup']);
            $data_entrada_dipli = formatarDataDB($_POST['data_entrada_dipli']);
            $resp_instrucao = limpar($_POST['resp_instrucao']);
            $area_demandante = limpar($_POST['area_demandante']);
            $pregoeiro = limpar($_POST['pregoeiro']);
            $modalidade = $_POST['modalidade'];
            $tipo = $_POST['tipo'];
            $numero = !empty($_POST['numero']) ? intval($_POST['numero']) : null;
            $ano = !empty($_POST['ano']) ? intval($_POST['ano']) : null;
            $valor_estimado = !empty($_POST['valor_estimado']) ? formatarValorDB($_POST['valor_estimado']) : null;
            $data_abertura = formatarDataDB($_POST['data_abertura']);
            $situacao = $_POST['situacao'];
            $objeto = limpar($_POST['objeto']);
            
            // Campos de homologação
            $data_homologacao = null;
            $qtd_homol = null;
            $valor_homologado = null;
            $economia = null;
            
            if ($situacao === 'HOMOLOGADO') {
                $data_homologacao = formatarDataDB($_POST['data_homologacao']);
                $qtd_homol = !empty($_POST['qtd_homol']) ? intval($_POST['qtd_homol']) : null;
                $valor_homologado = !empty($_POST['valor_homologado']) ? formatarValorDB($_POST['valor_homologado']) : null;
                $economia = !empty($_POST['economia']) ? formatarValorDB($_POST['economia']) : null;
            }
            
            // Atualizar no banco
            $sql = "UPDATE licitacoes SET 
                    nup = ?, data_entrada_dipli = ?, resp_instrucao = ?, area_demandante = ?,
                    pregoeiro = ?, modalidade = ?, tipo = ?, numero = ?, ano = ?,
                    valor_estimado = ?, data_abertura = ?, situacao = ?, objeto = ?,
                    data_homologacao = ?, qtd_homol = ?, valor_homologado = ?, economia = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nup, $data_entrada_dipli, $resp_instrucao, $area_demandante,
                $pregoeiro, $modalidade, $tipo, $numero, $ano,
                $valor_estimado, $data_abertura, $situacao, $objeto,
                $data_homologacao, $qtd_homol, $valor_homologado, $economia,
                $id
            ]);
            
            registrarLog('EDITAR_LICITACAO', "Editou licitação ID: $id - NUP: $nup", 'licitacoes', $id);
            
            $response['success'] = true;
            $response['message'] = 'Licitação atualizada com sucesso!';
            
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
        
        echo json_encode($response);
        break;
        
    default:
        setMensagem('Ação inválida!', 'erro');
        header('Location: index.php');
        break;
}
?>