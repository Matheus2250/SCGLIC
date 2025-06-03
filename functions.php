<?php
require_once 'config.php';

// Função para agrupar áreas
function agruparArea($area) {
    if (empty($area)) return 'SEM ÁREA';
    
    $area = trim($area);
    
    // Casos especiais - unificar variações
    if (strpos($area, 'GM') === 0) {
        return 'GM.';
    }
    
    // Se tem ponto, pega a parte antes do ponto + ponto
    if (strpos($area, '.') !== false) {
        $partes = explode('.', $area);
        return trim($partes[0]) . '.';
    }
    
    return $area;
}

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

// Formatar data para banco - CORRIGIDA
function formatarDataDB($data) {
    if (empty($data)) return null;
    
    // Se já está no formato do banco (Y-m-d)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return $data;
    }
    
    // Tentar diferentes formatos
    $formatos = [
        'd/m/Y',     // 31/12/2024
        'd-m-Y',     // 31-12-2024
        'Y-m-d',     // 2024-12-31
        'd/m/y',     // 31/12/24
        'd-m-y',     // 31-12-24
        'm/d/Y',     // 12/31/2024 (formato americano)
        'Y/m/d',     // 2024/12/31
    ];
    
    foreach ($formatos as $formato) {
        $dateTime = DateTime::createFromFormat($formato, $data);
        if ($dateTime && $dateTime->format($formato) === $data) {
            return $dateTime->format('Y-m-d');
        }
    }
    
    // Tentar usar strtotime como último recurso
    $timestamp = strtotime($data);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

// Formatar valor monetário para exibição
function formatarMoeda($valor) {
    if (is_null($valor) || $valor === '') return 'R$ 0,00';
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Formatar valor para banco - CORRIGIDA
function formatarValorDB($valor) {
    if (empty($valor)) return null;
    
    // Remover caracteres não numéricos exceto vírgula e ponto
    $valor = preg_replace('/[^\d,.-]/', '', $valor);
    
    // Se vazio após limpeza, retorna null
    if (empty($valor)) {
        return null;
    }
    
    // Se tem vírgula e ponto, assumir formato brasileiro (1.234,56)
    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        // Formato brasileiro: 1.234.567,89
        $valor = str_replace('.', '', $valor); // Remove pontos de milhares
        $valor = str_replace(',', '.', $valor); // Converte vírgula para ponto decimal
    } elseif (strpos($valor, ',') !== false) {
        // Só tem vírgula - pode ser decimal brasileiro ou separador de milhares
        $partes = explode(',', $valor);
        if (count($partes) == 2 && strlen(end($partes)) <= 2) {
            // Última parte tem 2 dígitos ou menos - é decimal brasileiro
            $valor = str_replace(',', '.', $valor);
        } else {
            // É separador de milhares - remove
            $valor = str_replace(',', '', $valor);
        }
    }
    
    // Converter para float
    $valor_float = floatval($valor);
    
    // Validar se é um valor válido
    if ($valor_float < 0) {
        return null;
    }
    
    return $valor_float;
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

// Função para processar upload de arquivo - CORRIGIDA
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

// Função para abreviar valores grandes
function abreviarValor($valor) {
    if (is_null($valor) || $valor === '') return '0';
    
    if ($valor >= 1000000000) {
        return number_format($valor / 1000000000, 1, ',', '.') . 'B';
    } elseif ($valor >= 1000000) {
        return number_format($valor / 1000000, 1, ',', '.') . 'M';
    } elseif ($valor >= 1000) {
        return number_format($valor / 1000, 1, ',', '.') . 'K';
    } else {
        return number_format($valor, 0, ',', '.');
    }
}

/**
 * Função específica para processar valores monetários da importação
 */
function processarValorMonetario($valor) {
    if (empty($valor)) {
        return null;
    }
    
    // Remover caracteres não numéricos exceto vírgula e ponto
    $valor = preg_replace('/[^\d,.]/', '', $valor);
    
    // Se vazio após limpeza, retorna null
    if (empty($valor)) {
        return null;
    }
    
    // Se tem vírgula e ponto, assumir formato brasileiro (1.234,56)
    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        // Formato brasileiro: 1.234.567,89
        $valor = str_replace('.', '', $valor); // Remove pontos de milhares
        $valor = str_replace(',', '.', $valor); // Converte vírgula para ponto decimal
    } elseif (strpos($valor, ',') !== false) {
        // Só tem vírgula - pode ser decimal brasileiro ou separador de milhares
        $partes = explode(',', $valor);
        if (count($partes) == 2 && strlen(end($partes)) <= 2) {
            // Última parte tem 2 dígitos ou menos - é decimal brasileiro
            $valor = str_replace(',', '.', $valor);
        } else {
            // É separador de milhares - remove
            $valor = str_replace(',', '', $valor);
        }
    }
    
    // Converter para float
    $valor_float = floatval($valor);
    
    // Validar se é um valor válido
    if ($valor_float < 0) {
        return null;
    }
    
    return $valor_float;
}

/**
 * Função específica para processar datas da importação
 */
function processarData($data) {
    if (empty($data)) {
        return null;
    }
    
    // Limpar a string
    $data = trim($data);
    
    // Se já está no formato do banco (Y-m-d)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return $data;
    }
    
    // Tentar diferentes formatos de data
    $formatos = [
        'd/m/Y',     // 31/12/2024
        'd-m-Y',     // 31-12-2024
        'Y-m-d',     // 2024-12-31
        'd/m/y',     // 31/12/24
        'd-m-y',     // 31-12-24
        'm/d/Y',     // 12/31/2024 (formato americano)
        'Y/m/d',     // 2024/12/31
        'd.m.Y',     // 31.12.2024
        'Y.m.d',     // 2024.12.31
    ];
    
    foreach ($formatos as $formato) {
        $dateTime = DateTime::createFromFormat($formato, $data);
        if ($dateTime && $dateTime->format($formato) === $data) {
            return $dateTime->format('Y-m-d');
        }
    }
    
    // Tentar usar strtotime como último recurso
    $timestamp = strtotime($data);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    // Se nenhum formato funcionou, retornar null
    return null;
}

/**
 * Função para detectar separador de CSV
 */
function detectarSeparadorCSV($arquivo) {
    $handle = fopen($arquivo, 'r');
    if (!$handle) return ';';
    
    $primeiraLinha = fgets($handle);
    fclose($handle);
    
    $separadores = [';', ',', '\t', '|'];
    $contadores = [];
    
    foreach ($separadores as $sep) {
        $contadores[$sep] = substr_count($primeiraLinha, $sep);
    }
    
    return array_search(max($contadores), $contadores) ?: ';';
}

/**
 * Função para validar linha do CSV
 */
function validarLinhaPCA($linha) {
    // Verificar se tem pelo menos os campos obrigatórios
    if (empty($linha[0])) { // numero_contratacao
        return false;
    }
    
    if (empty($linha[11])) { // numero_dfd
        return false;
    }
    
    return true;
}

/**
 * Função para limpar e validar encoding do arquivo
 */
function processarEncodingArquivo($caminhoArquivo) {
    $conteudo = file_get_contents($caminhoArquivo);
    
    // Detectar encoding
    $encoding = mb_detect_encoding($conteudo, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    
    if ($encoding !== 'UTF-8') {
        // Converter para UTF-8
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $encoding);
        file_put_contents($caminhoArquivo, $conteudo);
    }
    
    return true;
}

/**
 * Função para debug de importação (opcional)
 */
function debugImportacao($linha, $numeroLinha, $dados) {
    // Definir a constante se não existir
    if (!defined('DEBUG_IMPORTACAO')) {
        define('DEBUG_IMPORTACAO', false); // Mude para true para ativar debug
    }
    
    if (DEBUG_IMPORTACAO) {
        $debug = [
            'linha' => $numeroLinha,
            'dados_originais' => $linha,
            'dados_processados' => $dados
        ];
        error_log('DEBUG IMPORTACAO: ' . json_encode($debug));
    }
}
?>