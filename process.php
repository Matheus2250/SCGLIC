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
        $handle = fopen($arquivo, 'r');
        
        if (!$handle) {
            setMensagem('Erro ao abrir arquivo!', 'erro');
            header('Location: dashboard.php');
            exit;
        }
        
        // Detectar e converter encoding se necessário
        $primeiraLinha = fgets($handle);
        rewind($handle);
        
        // Detectar se precisa conversão de encoding
        if (!mb_detect_encoding($primeiraLinha, 'UTF-8', true)) {
            stream_filter_append($handle, 'convert.iconv.ISO-8859-1/UTF-8');
        }
        
        // Criar registro de importação
        $sql = "INSERT INTO pca_importacoes (nome_arquivo, usuario_id) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$resultado['arquivo'], $_SESSION['usuario_id']]);
        $importacao_id = $pdo->lastInsertId();
        
        // Ler cabeçalho
        $header = fgetcsv($handle, 0, ';');
        
        // Processar linhas
        $linhas_processadas = 0;
        $linhas_novas = 0;
        $linhas_atualizadas = 0;
        $linhas_ignoradas = 0;
        
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
        
        while (($linha = fgetcsv($handle, 0, ';')) !== FALSE) {
            // Pular linhas vazias
            if (empty($linha[0])) continue;
            
            $linhas_processadas++;
            $numero_contratacao = $linha[0];
            $codigo_material = $linha[20];
            
            // Processar dados
$pca_dados_ids = $_POST['pca_dados_ids'] ?? '0';
$ids_array = explode(',', $pca_dados_ids);
$primeiro_id = intval($ids_array[0]); // Usar o primeiro ID como referência

/// Se não tem ID válido, criar um registro temporário ou pegar o primeiro ID disponível
if ($primeiro_id == 0) {
    $stmt_primeiro_id = $pdo->query("SELECT MIN(id) as primeiro_id FROM pca_dados WHERE id IS NOT NULL");
    $result = $stmt_primeiro_id->fetch();
    $primeiro_id = $result['primeiro_id'] ?? 1;
}
            
            // Verificar se existe na última importação
            $dados_anteriores = null;
            if ($ultima_importacao_id) {
                $stmt_verifica->execute([$numero_contratacao, $codigo_material, $ultima_importacao_id]);
                $dados_anteriores = $stmt_verifica->fetch();
            }
            
            $deve_inserir = false;
            $mudancas = [];
            
            if (!$dados_anteriores) {
                // É uma contratação nova
                $deve_inserir = true;
                $linhas_novas++;
                
                // Iniciar estado para nova contratação
                $stmt_estado->execute([$numero_contratacao, $situacao_execucao]);
            } else {
                // Verificar o que mudou
                $campos_verificar = [
                    'situacao_execucao' => ['atual' => $situacao_execucao, 'nome' => 'Situação'],
                    'status_contratacao' => ['atual' => $linha[1], 'nome' => 'Status'],
                    'valor_total_contratacao' => ['atual' => $valor_total_contratacao, 'nome' => 'Valor Total'],
                    'titulo_contratacao' => ['atual' => $linha[3], 'nome' => 'Título'],
                    'prioridade' => ['atual' => $linha[12], 'nome' => 'Prioridade'],
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
                    $linha[1], // status_contratacao
                    $situacao_execucao,
                    $linha[3], // titulo_contratacao
                    $linha[4], // categoria_contratacao
                    $linha[5], // uasg_atual
                    $valor_total_contratacao,
                    $data_inicio,
                    $data_conclusao,
                    intval($linha[9]), // prazo_duracao_dias
                    $linha[10], // area_requisitante
                    $linha[11], // numero_dfd
                    $linha[12], // prioridade
                    $linha[13], // numero_item_dfd
                    $data_conclusao_dfd,
                    $linha[15], // classificacao_contratacao
                    $linha[16], // codigo_classe_grupo
                    $linha[17], // nome_classe_grupo
                    $linha[18], // codigo_pdm_material
                    $linha[19], // nome_pdm_material
                    $linha[20], // codigo_material_servico
                    $linha[21], // descricao_material_servico
                    $linha[22], // unidade_fornecimento
                    $valor_unitario,
                    intval($linha[24]), // quantidade
                    $valor_total
                ];
                
                $stmt->execute($params);
            }
        }
        
        fclose($handle);
        
        // Mensagem detalhada
        $mensagem = "Importação concluída! ";
        $mensagem .= "Processadas: $linhas_processadas | ";
        $mensagem .= "Novas: $linhas_novas | ";
        $mensagem .= "Atualizadas: $linhas_atualizadas | ";
        $mensagem .= "Sem alterações: $linhas_ignoradas";
        
        registrarLog('IMPORTACAO_PCA', $mensagem, 'pca_importacoes', $importacao_id);
        setMensagem($mensagem);

        $mensagem_importacao = "Arquivo importado com sucesso! $linhas_importadas registros adicionados.";
        if ($mudancas_detectadas > 0) {
            $mensagem_importacao .= " $mudancas_detectadas mudanças detectadas e registradas.";
        }
        
        registrarLog('IMPORTACAO_PCA', "$mensagem_importacao Arquivo: $resultado[arquivo]", 'pca_importacoes', $importacao_id);
        setMensagem($mensagem_importacao);
        
        registrarLog('IMPORTACAO_PCA', "Importou $linhas_importadas registros do arquivo $resultado[arquivo]", 'pca_importacoes', $importacao_id);
        setMensagem("Arquivo importado com sucesso! $linhas_importadas registros adicionados.");
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
    
    // Validar Item PGC
    if (!empty($_POST['item_pgc']) && !validarItemPGC($_POST['item_pgc'])) {
        setMensagem('Formato do Item PGC inválido! Use: xxxx/xxxx', 'erro');
        header('Location: licitacao_dashboard.php');
        exit;
    }
    
    // Verificar se existe pelo menos um registro em pca_dados
    $stmt_check = $pdo->query("SELECT MIN(id) as primeiro_id FROM pca_dados WHERE id IS NOT NULL");
    $primeiro_registro = $stmt_check->fetch();
    
    if (!$primeiro_registro['primeiro_id']) {
        // Criar registro temporário se não existir nenhum
        $pdo->exec("INSERT INTO pca_dados (numero_contratacao, titulo_contratacao, categoria_contratacao, valor_total_contratacao, situacao_execucao) 
                   VALUES ('TEMP-001', 'Registro temporário para licitações', 'OUTROS', 0, 'Temporário')");
        $primeiro_id = $pdo->lastInsertId();
    } else {
        $primeiro_id = $primeiro_registro['primeiro_id'];
    }
    
    // Processar dados do formulário
    $nup = limpar($_POST['nup']);
    $data_entrada_dipli = formatarDataDB($_POST['data_entrada_dipli']);
    $resp_instrucao = limpar($_POST['resp_instrucao']);
    $area_demandante = limpar($_POST['area_demandante']);
    $pregoeiro = limpar($_POST['pregoeiro']);
    $modalidade = $_POST['modalidade'];
    $tipo = $_POST['tipo'];
    $numero = intval($_POST['numero']);
    $ano = intval($_POST['ano']);
    $prioridade = limpar($_POST['prioridade']);
    $item_pgc = limpar($_POST['item_pgc']);
    $estimado_pgc = formatarValorDB($_POST['estimado_pgc']);
    $ano_pgc = intval($_POST['ano_pgc']);
    $objeto = limpar($_POST['objeto']);
    $qtd_itens = intval($_POST['qtd_itens']);
    $valor_estimado = formatarValorDB($_POST['valor_estimado']);
    $data_abertura = formatarDataDB($_POST['data_abertura']);
    $situacao = $_POST['situacao'];
    $andamentos = limpar($_POST['andamentos']);
    $impugnado = isset($_POST['impugnado']) ? 1 : 0;
    $pertinente = isset($_POST['pertinente']) ? 1 : 0;
    $motivo = limpar($_POST['motivo']);
    
    $sql = "INSERT INTO licitacoes (
        pca_dados_id, nup, data_entrada_dipli, resp_instrucao, area_demandante,
        pregoeiro, modalidade, tipo, numero, ano, prioridade, item_pgc,
        estimado_pgc, ano_pgc, objeto, qtd_itens, valor_estimado,
        data_abertura, situacao, andamentos, impugnado, pertinente, motivo,
        usuario_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        $primeiro_id, $nup, $data_entrada_dipli, $resp_instrucao, $area_demandante,
        $pregoeiro, $modalidade, $tipo, $numero, $ano, $prioridade, $item_pgc,
        $estimado_pgc, $ano_pgc, $objeto, $qtd_itens, $valor_estimado,
        $data_abertura, $situacao, $andamentos, $impugnado, $pertinente, $motivo,
        $_SESSION['usuario_id']
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
}
?>