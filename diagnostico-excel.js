const XLSX = require('xlsx');
const path = require('path');

// Caminho do arquivo Excel 
const excelFilePath = process.argv[2] || path.resolve(__dirname, 'C:\\Users\\DENIS\\Downloads\\Contratações1 (1).xlsx');
console.log(`Lendo arquivo Excel: ${excelFilePath}`);

try {
  // Ler a planilha
  const workbook = XLSX.readFile(excelFilePath);
  const sheetNames = workbook.SheetNames;
  
  console.log(`\nPlanilhas disponíveis: ${JSON.stringify(sheetNames)}`);
  
  // Analisar cada planilha no arquivo
  sheetNames.forEach(sheetName => {
    console.log(`\n=== Analisando planilha: ${sheetName} ===`);
    
    // Converter para JSON para ver os dados
    const worksheet = workbook.Sheets[sheetName];
    const data = XLSX.utils.sheet_to_json(worksheet);
    
    if (data.length === 0) {
      console.log(`A planilha ${sheetName} está vazia ou não contém cabeçalhos válidos.`);
      return;
    }
    
    // Obter cabeçalhos (nomes das colunas)
    const firstRow = data[0];
    const headers = Object.keys(firstRow);
    
    console.log(`\nTotal de registros na planilha: ${data.length}`);
    console.log(`\nCabeçalhos encontrados (${headers.length}):`);
    
    // Mostrar cada cabeçalho com detalhes
    headers.forEach((header, index) => {
      // Verificar quantos registros têm esse campo preenchido
      const preenchidos = data.filter(row => row[header] !== null && row[header] !== undefined && row[header] !== '').length;
      const percentPreenchido = ((preenchidos / data.length) * 100).toFixed(1);
      
      console.log(`${index + 1}. "${header}" - Preenchido em ${preenchidos}/${data.length} registros (${percentPreenchido}%)`);
      
      // Mostrar alguns exemplos de valores para esse cabeçalho
      const exemplos = data.slice(0, 3).map(row => row[header]).filter(val => val !== null && val !== undefined && val !== '');
      if (exemplos.length > 0) {
        console.log(`   Exemplos: ${JSON.stringify(exemplos)}`);
      }
    });
    
    // Verificar se há algum registro com campos preenchidos
    const registrosValidos = data.filter(row => 
      Object.values(row).some(val => val !== null && val !== undefined && val !== '')
    );
    
    console.log(`\nRegistros com pelo menos um campo preenchido: ${registrosValidos.length}/${data.length}`);
    
    // Sugestão de mapeamento
    console.log('\n=== Mapeamento Sugerido para Script de Importação ===');
    console.log('Copie este mapeamento para seu script de importação:\n');
    
    console.log('const mapeamento = {');
    headers.forEach(header => {
      // Converter o cabeçalho para um nome de campo sugerido (snake_case)
      const campoSugerido = header
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Remove acentos
        .toLowerCase()
        .replace(/[^\w\s]/gi, '') // Remove caracteres especiais
        .trim()
        .replace(/\s+/g, '_'); // Substitui espaços por underscores
      
      console.log(`  '${header}': '${campoSugerido}',`);
    });
    console.log('};');
  });
  
} catch (error) {
  console.error('Erro durante a análise da planilha:', error);
}