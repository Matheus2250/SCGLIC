<?php
require_once 'config.php';

// Verificar se usuário está logado
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: index.php');
        exit;
    }
}

// Sanitizar entrada
function limpar($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Formatar data para exibição
function formatarData($data) {
    if (empty($data)) return '';
    return date('d/m/Y', strtotime($data));
}

// Formatar data para banco
function formatarDataDB($data) {
    if (empty($data)) return null;
    $d = DateTime::createFromFormat('d/m/Y', $data);
    return $d ? $d->format('Y-m-d') : null;
}

// Formatar valor monetário
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Formatar valor para banco
function formatarValorDB($valor) {
    $valor = str_replace('R$', '', $valor);
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return floatval(trim($valor));
}

// Gerar mensagem de alerta
function setMensagem($mensagem, $tipo = 'success') {
    $_SESSION['mensagem'] = $mensagem;
    $_SESSION['tipo_mensagem'] = $tipo;
}

// Exibir mensagem de alerta
function getMensagem() {
    if (isset($_SESSION['mensagem'])) {
        $tipo = $_SESSION['tipo_mensagem'] ?? 'success';
        $classe = $tipo === 'success' ? 'sucesso' : 'erro';
        $mensagem = '<div class="mensagem ' . $classe . '">' . $_SESSION['mensagem'] . '</div>';
        unset($_SESSION['mensagem']);
        unset($_SESSION['tipo_mensagem']);
        return $mensagem;
    }
    return '';
}

// Validar formato NUP
function validarNUP($nup) {
    $pattern = '/^\d{5}\.\d{6}\/\d{4}-\d{2}$/';
    return preg_match($pattern, $nup);
}

// Validar formato Item PGC
function validarItemPGC($item) {
    $pattern = '/^\d{4}\/\d{4}$/';
    return preg_match($pattern, $item);
}

// Log de ações do sistema
function registrarLog($acao, $descricao, $tabela = null, $registro_id = null) {
    if (!isset($_SESSION['usuario_id'])) return;
    
    $pdo = conectarDB();
    $sql = "INSERT INTO logs_sistema (usuario_id, acao, descricao, tabela_afetada, registro_id) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['usuario_id'], $acao, $descricao, $tabela, $registro_id]);
}

// Função para processar upload de arquivo
function processarUpload($arquivo, $pasta = 'uploads/') {
    $uploadOk = 1;
    $mensagem = '';
    
    // Verificar se é um arquivo válido
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return ['sucesso' => false, 'mensagem' => 'Erro no upload do arquivo'];
    }
    
    // Verificar tamanho (max 10MB)
    if ($arquivo['size'] > 10485760) {
        return ['sucesso' => false, 'mensagem' => 'Arquivo muito grande (máximo 10MB)'];
    }
    
    // Verificar extensão
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $extensoesPermitidas = ['csv', 'xls', 'xlsx'];
    
    if (!in_array($extensao, $extensoesPermitidas)) {
        return ['sucesso' => false, 'mensagem' => 'Tipo de arquivo não permitido. Use CSV, XLS ou XLSX'];
    }
    
    // Gerar nome único
    $nomeArquivo = uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $extensao;
    $caminhoCompleto = $pasta . $nomeArquivo;
    
    // Criar pasta se não existir
    if (!file_exists($pasta)) {
        mkdir($pasta, 0777, true);
    }
    
    // Mover arquivo
    if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
        return ['sucesso' => true, 'arquivo' => $nomeArquivo, 'caminho' => $caminhoCompleto];
    } else {
        return ['sucesso' => false, 'mensagem' => 'Erro ao salvar arquivo'];
    }
}
?>