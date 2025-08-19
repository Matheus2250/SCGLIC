<?php
/**
 * Script auxiliar para converter o restante do CSV de contratos para SQL
 * Converte registros 33 em diante do arquivo CONTRATOS 2025 14.csv
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$csvFile = '../new-stuff/CONTRATOS 2025 14.csv';
$outputFile = 'importar_contratos_csv_parte2.sql';

if (!file_exists($csvFile)) {
    die("Arquivo CSV não encontrado: $csvFile\n");
}

// Função para limpar e converter dados
function limparDado($valor) {
    if ($valor === null || $valor === '' || $valor === '-') {
        return 'NULL';
    }
    
    // Escapar aspas
    $valor = str_replace("'", "''", $valor);
    
    return "'" . $valor . "'";
}

function converterValor($valor) {
    if ($valor === null || $valor === '' || $valor === '-' || $valor === ' - ') {
        return 'NULL';
    }
    
    // Remover espaços
    $valor = trim($valor);
    
    // Converter formato brasileiro para decimal
    $valor = str_replace('.', '', $valor); // Remove pontos de milhares
    $valor = str_replace(',', '.', $valor); // Vírgula vira ponto decimal
    
    return floatval($valor);
}

function converterData($data) {
    if ($data === null || $data === '' || $data === '-' || $data === '00/00/00' || $data === '00/00/0000') {
        return 'NULL';
    }
    
    // Formatos possíveis: dd/mm/aa, dd/mm/aaaa
    $data = trim($data);
    
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $data, $matches)) {
        $dia = $matches[1];
        $mes = $matches[2];
        $ano = '20' . $matches[3]; // Assume século 21
        return "'$ano-$mes-$dia'";
    }
    
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $matches)) {
        $dia = $matches[1];
        $mes = $matches[2];
        $ano = $matches[3];
        return "'$ano-$mes-$dia'";
    }
    
    return 'NULL';
}

// Abrir CSV
$handle = fopen($csvFile, 'r');
if (!$handle) {
    die("Erro ao abrir arquivo CSV\n");
}

// Pular cabeçalho
fgets($handle);

// Pular primeiros 32 registros (já convertidos)
for ($i = 0; $i < 32; $i++) {
    fgetcsv($handle, 0, ';');
}

// Iniciar arquivo SQL
$sqlContent = "/**\n";
$sqlContent .= " * PARTE 2 - IMPORTAÇÃO DE CONTRATOS RESTANTES\n";
$sqlContent .= " * Registros 33 em diante do CSV\n";
$sqlContent .= " * Gerado automaticamente em " . date('Y-m-d H:i:s') . "\n";
$sqlContent .= " */\n\n";
$sqlContent .= "USE sistema_licitacao;\n\n";
$sqlContent .= "INSERT INTO contratos (\n";
$sqlContent .= "    numero_sequencial, ano_contrato, numero_contrato, nome_empresa, cnpj_cpf,\n";
$sqlContent .= "    numero_sei, objeto_servico, modalidade, numero_modalidade,\n";
$sqlContent .= "    valor_2020, valor_2021, valor_2022, valor_2023, valor_2025,\n";
$sqlContent .= "    valor_inicial, valor_atual, data_inicio, data_fim, data_assinatura,\n";
$sqlContent .= "    area_gestora, finalidade, portaria_fiscal_sei, fiscais, garantia,\n";
$sqlContent .= "    alerta_vigencia_sei, situacao_atual, mao_obra, prorrogacao,\n";
$sqlContent .= "    link_documentos, portaria_mf_mgi_mp, numero_dfd, status_contrato\n";
$sqlContent .= ") VALUES\n\n";

$registros = [];
$contador = 33;

// Processar registros restantes
while (($linha = fgetcsv($handle, 0, ';')) !== FALSE) {
    if (count($linha) < 31) continue; // Pular linhas incompletas
    
    // Limpar encoding de cada campo
    $linha = array_map(function($campo) {
        return mb_convert_encoding($campo, 'UTF-8', 'UTF-8');
    }, $linha);
    
    $registro = sprintf(
        "-- Registro %d\n(%d, %d, %s, %s, %s,\n%s, %s, %s, %s,\n%s, %s, %s, %s, %s,\n%s, %s, %s, %s, %s,\n%s, %s, %s, %s, %s,\n%s, %s, %s, %s,\n%s, %s, %s, 'ativo')",
        $contador,
        intval($linha[0]), // numero_sequencial
        intval($linha[1]), // ano_contrato
        limparDado($linha[2]), // numero_contrato
        limparDado($linha[3]), // nome_empresa
        limparDado($linha[4]), // cnpj_cpf
        limparDado($linha[5]), // numero_sei
        limparDado($linha[6]), // objeto_servico
        limparDado($linha[7]), // modalidade
        limparDado($linha[8]), // numero_modalidade
        converterValor($linha[9]), // valor_2020
        converterValor($linha[10]), // valor_2021
        converterValor($linha[11]), // valor_2022
        converterValor($linha[12]), // valor_2023
        converterValor($linha[13]), // valor_2025
        converterValor($linha[14]), // valor_inicial
        converterValor($linha[15]), // valor_atual
        converterData($linha[16]), // data_inicio
        converterData($linha[17]), // data_fim
        converterData($linha[18]), // data_assinatura
        limparDado($linha[19]), // area_gestora
        limparDado($linha[20]), // finalidade
        limparDado($linha[21]), // portaria_fiscal_sei
        limparDado($linha[22]), // fiscais
        limparDado($linha[23]), // garantia
        limparDado($linha[24]), // alerta_vigencia_sei
        limparDado($linha[25]), // situacao_atual
        limparDado($linha[26]), // mao_obra
        limparDado($linha[27]), // prorrogacao
        limparDado($linha[28]), // link_documentos
        limparDado($linha[29]), // portaria_mf_mgi_mp
        limparDado($linha[30]) // numero_dfd
    );
    
    $registros[] = $registro;
    $contador++;
    
    // Limite para não sobrecarregar
    if ($contador > 100) break; // Processar apenas os próximos 68 registros por vez
}

fclose($handle);

// Finalizar SQL
$sqlContent .= implode(",\n\n", $registros);
$sqlContent .= "\n\n;\n\n";
$sqlContent .= "-- Atualizar AUTO_INCREMENT\n";
$sqlContent .= "ALTER TABLE contratos AUTO_INCREMENT = $contador;\n\n";
$sqlContent .= "-- Verificar registros\n";
$sqlContent .= "SELECT COUNT(*) as total_importados FROM contratos;\n";
$sqlContent .= "SELECT MAX(numero_sequencial) as ultimo_registro FROM contratos;\n\n";
$sqlContent .= "COMMIT;\n";

// Salvar arquivo
file_put_contents($outputFile, $sqlContent);

echo "Conversão concluída!\n";
echo "Registros processados: " . count($registros) . "\n";
echo "Arquivo gerado: $outputFile\n";
echo "Execute: mysql -u root sistema_licitacao < $outputFile\n";
?>