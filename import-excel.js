const XLSX = require('xlsx');
const sqlite3 = require('sqlite3').verbose();
const path = require('path');
const { promisify } = require('util');

// Caminho do arquivo Excel 
const excelFilePath = process.argv[2] || path.resolve(__dirname, 'C:\\Users\\DENIS\\Downloads\\Contratações1 (1).xlsx');
console.log(`Lendo arquivo Excel: ${excelFilePath}`);

// Caminho do banco de dados SQLite
const dbPath = path.resolve(__dirname, './database.sqlite');
const db = new sqlite3.Database(dbPath);

// Promisify algumas funções do SQLite para facilitar o uso com async/await
db.runAsync = promisify(db.run.bind(db));
db.getAsync = promisify(db.get.bind(db));
db.allAsync = promisify(db.all.bind(db));

// Mapeamento entre os cabeçalhos da planilha e os campos do banco de dados
const mapeamento = {
  'NUP': 'nup',
  'DT ENTRADA DIPLI': 'dt_entrada_dipli',
  'ÁREA DEMANDANTE': 'area_demandante',
  'PREGOEIRO': 'pregoeiro',
  'MODALIDADE': 'modalidade',
  'TIPO': 'tipo',
  'Nº': 'n',
  'ANO': 'ano',
  'OBJETO': 'objeto',
  'QTD ITENS': 'qtd_itens',
  ' VALOR ESTIMADO (R$) ': 'valor_estimado_r',
  'DT ABERTURA': 'dt_abertura',
  'SITUAÇÃO': 'situacao',
  'ANDAMENTOS': 'andamentos',
  'QTD FRAC': 'qtd_frac',
  'MOTIVO DO FRACASSO/REVOGAÇÃO/SUSPENSÃO': 'motivo_do_fracassorevogacaosuspensao',
  'QTD HOMOL': 'qtd_homol',
  ' VALOR HOMOLOGADO (R$) ': 'valor_homologado_r',
  ' ECONOMIA (R$) ': 'economia_r',
  'TEMP DIPLI': 'temp_dipli',
  'MÊS DIPLI': 'mes_dipli',
  'DIAS DIPLI': 'dias_dipli',
  'TEMP PREGO': 'temp_prego',
  'MÊS PREGO': 'mes_prego',
  'DIAS PREGO': 'dias_prego',
  'LINK': 'link',
  // Campos opcionais mantidos para compatibilidade
  'RESP. INSTRUÇÃO': 'resp_instrucao',
  'PRIORIDADE': 'prioridade',
  'ITEM PGC': 'item_pgc',
  ' ESTIMADO PGC (R$) ': 'estimado_pgc_r',
  'ANO PGC': 'ano_pgc',
  'IMPUGNADO?': 'impugnado',
  'PERTINENTE?': 'pertinente',
  'MOTIVO': 'motivo',
  ' VALOR ': 'valor',
  'RECURSO?': 'recurso',
  'MOTIVO_1': 'motivo_1',
  'DT HOMOLOGAÇÃO': 'dt_homologacao',
  'INGRESSO JUDICIAL?': 'ingresso_judicial',
  'MOTIVO_2': 'motivo_2',
  'MOTIVO EXTERNO': 'motivo_externo',
  'DATA ENTRADA': 'data_entrada',
  'DATA SAÍDA': 'data_saida'
};

async function main() {
  try {
    // Ler a planilha
    const workbook = XLSX.readFile(excelFilePath);
    const sheetNames = workbook.SheetNames;
    const firstSheet = sheetNames[0];
    console.log(`Planilha encontrada: ${firstSheet}`);
    
    // Converter para JSON
    const worksheet = workbook.Sheets[firstSheet];
    const data = XLSX.utils.sheet_to_json(worksheet);
    console.log(`Lidos ${data.length} registros da planilha`);
    
    // Filtrar registros válidos e mapear para os campos do banco
    const validRecords = [];
    
    data.forEach(row => {
      // Verificar se o registro tem pelo menos NUP ou OBJETO
      if (row['NUP'] || row['OBJETO']) {
        const mappedRecord = {};
        
        // Aplicar mapeamento de colunas para campos do banco
        Object.entries(row).forEach(([coluna, valor]) => {
          const campoBD = mapeamento[coluna];
          if (campoBD) {
            // Tratar valores especiais ou formatações
            if (valor === ' -   ' || valor === '-' || valor === '' || valor === undefined || valor === null) {
              mappedRecord[campoBD] = null;
            } else {
              mappedRecord[campoBD] = valor;
            }
          }
        });
        
        // Verificar se o registro mapeado tem pelo menos um campo preenchido além do NUP/OBJETO
        const temOutrosCampos = Object.values(mappedRecord).some(v => 
          v !== null && v !== undefined && v !== '' && 
          !(typeof v === 'string' && v.trim() === '')
        );
        
        if (Object.keys(mappedRecord).length > 0 && temOutrosCampos) {
          validRecords.push(mappedRecord);
        }
      }
    });
    
    console.log(`${validRecords.length} registros válidos encontrados para importação`);
    
    if (validRecords.length === 0) {
      console.log('Nenhum registro válido encontrado na planilha. Verifique os nomes das colunas.');
      return db.close();
    }
    
    // Verificar se a tabela existe
    const tableExists = await db.getAsync("SELECT name FROM sqlite_master WHERE type='table' AND name='contratos'");
    
    // Se a tabela não existir, criá-la
    if (!tableExists) {
      console.log('Tabela "contratos" não encontrada. Criando tabela...');
      
      // Criar script de criação de tabela baseado nos campos mapeados
      const primeiroRegistro = validRecords[0];
      const colunas = Object.keys(primeiroRegistro);
      
      const createTableColunas = colunas.map(col => `${col} TEXT`).join(', ');
      const createTableSQL = `CREATE TABLE contratos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ${createTableColunas}
      )`;
      
      // Criar a tabela
      await db.runAsync(createTableSQL);
      console.log('Tabela "contratos" criada com sucesso!');
    }
    
    // Iniciar transação
    await db.runAsync('BEGIN TRANSACTION');
    
    // Preparar a query de inserção
    const primeiroRegistro = validRecords[0];
    const colunas = Object.keys(primeiroRegistro);
    const placeholders = colunas.map(() => '?').join(', ');
    
    const insertQuery = `INSERT INTO contratos (${colunas.join(', ')}) VALUES (${placeholders})`;
    
    // Função para inserir um registro com Promise
    function insertRecord(record) {
      return new Promise((resolve, reject) => {
        const values = colunas.map(col => record[col]);
        db.run(insertQuery, values, function(err) {
          if (err) reject(err);
          else resolve(this.lastID);
        });
      });
    }
    
    // Inserir registros com contagem de sucesso e falha
    let sucessCount = 0;
    let errorCount = 0;
    
    console.log(`Iniciando importação de ${validRecords.length} registros...`);
    
    // Inserir registros em lotes para evitar sobrecarga de memória
    const BATCH_SIZE = 50;
    for (let i = 0; i < validRecords.length; i += BATCH_SIZE) {
      const batch = validRecords.slice(i, i + BATCH_SIZE);
      const promises = [];
      
      for (const record of batch) {
        try {
          const promise = insertRecord(record)
            .then(() => { sucessCount++; })
            .catch(err => { 
              console.error(`Erro ao inserir registro: ${err.message}`);
              errorCount++; 
            });
          
          promises.push(promise);
        } catch (e) {
          console.error(`Exceção ao inserir registro: ${e.message}`);
          errorCount++;
        }
      }
      
      // Aguardar este lote terminar antes de prosseguir para o próximo
      await Promise.allSettled(promises);
      console.log(`Progresso: ${i + batch.length}/${validRecords.length} registros processados...`);
    }
    
    // Finalizar transação
    await db.runAsync('COMMIT');
    console.log(`Importação concluída! ${sucessCount} registros inseridos com sucesso, ${errorCount} falhas.`);
    
  } catch (error) {
    console.error('Erro durante a importação:', error);
    try {
      // Tentar fazer rollback em caso de erro
      await db.runAsync('ROLLBACK');
    } catch (rollbackError) {
      console.error('Erro ao fazer rollback:', rollbackError);
    }
  } finally {
    // Sempre fechar a conexão no final
    db.close();
  }
}

// Executar a função principal
main().catch(err => {
  console.error('Erro fatal:', err);
  db.close();
});