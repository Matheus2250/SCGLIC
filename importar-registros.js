const XLSX = require("xlsx");
const sqlite3 = require("sqlite3").verbose();
const path = require("path");
const { promisify } = require("util");
const fs = require("fs");

// Caminho do arquivo Excel - será passado como argumento ou poderá ser definido aqui
const excelFilePath = process.argv[2];

// Verifica se o caminho do arquivo foi fornecido
if (!excelFilePath) {
  console.log("Uso: node importar-registros.js <caminho-do-arquivo-excel>");
  console.log("Exemplo: node importar-registros.js planilha-registros.xlsx");
  process.exit(1);
}

// Verifica se o arquivo existe
if (!fs.existsSync(excelFilePath)) {
  console.error(`Erro: Arquivo não encontrado: ${excelFilePath}`);
  process.exit(1);
}

console.log(`Lendo arquivo Excel: ${excelFilePath}`);

// Caminho do banco de dados SQLite
const dbPath = path.resolve(__dirname, "./database.db");
const db = new sqlite3.Database(dbPath);

// Promisify algumas funções do SQLite para facilitar o uso com async/await
db.runAsync = promisify(db.run.bind(db));
db.getAsync = promisify(db.get.bind(db));
db.allAsync = promisify(db.all.bind(db));

// Mapeamento entre os cabeçalhos da planilha e os campos do banco de dados
// Ajuste os nomes das colunas da sua planilha conforme necessário
const mapeamento = {
  NUP: "nup",
  "DT ENTRADA DIPLI": "dt_entrada_dipli",
  "RESP. INSTRUÇÃO": "resp_instrucao",
  "ÁREA DEMANDANTE": "area_demandante",
  PREGOEIRO: "pregoeiro",
  MODALIDADE: "modalidade",
  TIPO: "tipo",
  NÚMERO: "numero", // Ajuste conforme sua planilha (pode ser 'Nº' ou outro)
  ANO: "ano",
  PRIORIDADE: "prioridade",
  "ITEM PGC": "item_pgc",
  "ESTIMADO PGC": "estimado_pgc",
  "ANO PGC": "ano_pgc",
  OBJETO: "objeto",
  "QTD ITENS": "qtd_itens",
  "VALOR ESTIMADO": "valor_estimado",
  "DT ABERTURA": "dt_abertura",
  SITUAÇÃO: "situacao",
  ANDAMENTOS: "andamentos",
  "VALOR HOMOLOGADO": "valor_homologado",
  ECONOMIA: "economia",
  "DT HOMOLOGAÇÃO": "dt_homologacao",
};

// Função para converter valores monetários
function converterValorMonetario(valor) {
  if (!valor) return null;

  // Se for uma string, tenta converter para número
  if (typeof valor === "string") {
    // Remove caracteres não numéricos exceto o ponto ou vírgula
    valor = valor.replace(/[^\d,.]/g, "");
    // Substitui vírgula por ponto
    valor = valor.replace(",", ".");
  }

  // Converte para número
  const numero = parseFloat(valor);

  // Retorna null se não for um número válido
  return isNaN(numero) ? null : numero;
}

// Função para converter datas
function converterData(data) {
  if (!data) return null;

  // Se já for uma data no formato YYYY-MM-DD, retorna como está
  if (/^\d{4}-\d{2}-\d{2}$/.test(data)) {
    return data;
  }

  // Se for uma data no formato DD/MM/YYYY
  if (/^\d{2}\/\d{2}\/\d{4}$/.test(data)) {
    const partes = data.split("/");
    return `${partes[2]}-${partes[1]}-${partes[0]}`;
  }

  // Se for um número (formato Excel), tenta converter
  if (typeof data === "number") {
    try {
      // Data no Excel: número de dias desde 1/1/1900
      // 25569 é o número de dias entre 1/1/1900 e 1/1/1970 (epoch do JavaScript)
      const dataJS = new Date((data - 25569) * 86400 * 1000);
      return dataJS.toISOString().split("T")[0]; // Formato YYYY-MM-DD
    } catch (e) {
      console.log(`Erro ao converter data: ${data}`, e);
      return null;
    }
  }

  // Se não conseguir converter, retorna como está
  return data;
}

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

    data.forEach((row) => {
      // Verificar se o registro tem pelo menos NUP ou OBJETO
      if (row["NUP"] || row["OBJETO"]) {
        const mappedRecord = {};

        // Aplicar mapeamento de colunas para campos do banco
        Object.entries(row).forEach(([coluna, valor]) => {
          const campoBD = mapeamento[coluna];
          if (campoBD) {
            // Tratamentos específicos para tipos de campos
            if (
              valor === " -   " ||
              valor === "-" ||
              valor === "" ||
              valor === undefined ||
              valor === null
            ) {
              mappedRecord[campoBD] = null;
            } else if (
              campoBD === "valor_estimado" ||
              campoBD === "valor_homologado" ||
              campoBD === "economia"
            ) {
              mappedRecord[campoBD] = converterValorMonetario(valor);
            } else if (
              campoBD === "dt_entrada_dipli" ||
              campoBD === "dt_abertura" ||
              campoBD === "dt_homologacao"
            ) {
              mappedRecord[campoBD] = converterData(valor);
            } else if (campoBD === "qtd_itens") {
              mappedRecord[campoBD] = parseInt(valor) || null;
            } else {
              mappedRecord[campoBD] = valor;
            }
          }
        });

        // Verificar se o registro mapeado tem pelo menos um campo preenchido além do NUP/OBJETO
        const temOutrosCampos = Object.values(mappedRecord).some(
          (v) =>
            v !== null &&
            v !== undefined &&
            v !== "" &&
            !(typeof v === "string" && v.trim() === "")
        );

        if (Object.keys(mappedRecord).length > 0 && temOutrosCampos) {
          validRecords.push(mappedRecord);
        }
      }
    });

    console.log(
      `${validRecords.length} registros válidos encontrados para importação`
    );

    if (validRecords.length === 0) {
      console.log(
        "Nenhum registro válido encontrado na planilha. Verifique os nomes das colunas."
      );
      return db.close();
    }

    // Verificar se a tabela existe
    const tableExists = await db.getAsync(
      "SELECT name FROM sqlite_master WHERE type='table' AND name='registros'"
    );

    if (!tableExists) {
      console.log(
        'Tabela "registros" não encontrada. Verifique se o banco de dados está corretamente inicializado.'
      );
      return db.close();
    }

    // Iniciar transação
    await db.runAsync("BEGIN TRANSACTION");

    // Preparar a query de inserção com os campos específicos da tabela registros
    const insertSQL = `
      INSERT INTO registros (
        nup, dt_entrada_dipli, resp_instrucao, area_demandante, pregoeiro,
        modalidade, tipo, numero, ano, prioridade, item_pgc, estimado_pgc,
        ano_pgc, objeto, qtd_itens, valor_estimado, dt_abertura, situacao,
        andamentos, valor_homologado, economia, dt_homologacao
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `;

    // Função para inserir um registro
    function insertRecord(record) {
      return new Promise((resolve, reject) => {
        const values = [
          record.nup || null,
          record.dt_entrada_dipli || null,
          record.resp_instrucao || null,
          record.area_demandante || null,
          record.pregoeiro || null,
          record.modalidade || null,
          record.tipo || null,
          record.numero || null,
          record.ano || null,
          record.prioridade || null,
          record.item_pgc || null,
          record.estimado_pgc || null,
          record.ano_pgc || null,
          record.objeto || null,
          record.qtd_itens || null,
          record.valor_estimado || null,
          record.dt_abertura || null,
          record.situacao || null,
          record.andamentos || null,
          record.valor_homologado || null,
          record.economia || null,
          record.dt_homologacao || null,
        ];

        db.run(insertSQL, values, function (err) {
          if (err) reject(err);
          else resolve(this.lastID);
        });
      });
    }

    // Inserir registros com contagem de sucesso e falha
    let sucessCount = 0;
    let errorCount = 0;
    let errors = [];

    console.log(`Iniciando importação de ${validRecords.length} registros...`);

    // Inserir registros em lotes para evitar sobrecarga de memória
    const BATCH_SIZE = 50;
    for (let i = 0; i < validRecords.length; i += BATCH_SIZE) {
      const batch = validRecords.slice(i, i + BATCH_SIZE);
      const promises = [];

      for (const record of batch) {
        try {
          const promise = insertRecord(record)
            .then(() => {
              sucessCount++;
            })
            .catch((err) => {
              console.error(`Erro ao inserir registro: ${err.message}`);
              errors.push({ record, error: err.message });
              errorCount++;
            });

          promises.push(promise);
        } catch (e) {
          console.error(`Exceção ao inserir registro: ${e.message}`);
          errors.push({ record, error: e.message });
          errorCount++;
        }
      }

      // Aguardar este lote terminar antes de prosseguir para o próximo
      await Promise.allSettled(promises);
      console.log(
        `Progresso: ${i + batch.length}/${
          validRecords.length
        } registros processados...`
      );
    }

    // Finalizar transação
    await db.runAsync("COMMIT");

    console.log(`Importação concluída!`);
    console.log(`${sucessCount} registros inseridos com sucesso.`);
    console.log(`${errorCount} falhas.`);

    if (errorCount > 0) {
      console.log("\nErros encontrados:");
      errors.slice(0, 5).forEach((err, idx) => {
        console.log(`\nErro ${idx + 1}:`);
        console.log(`Mensagem: ${err.error}`);
        console.log(`Registro: ${JSON.stringify(err.record, null, 2)}`);
      });

      if (errors.length > 5) {
        console.log(`\n... e mais ${errors.length - 5} erros.`);
      }
    }
  } catch (error) {
    console.error("Erro durante a importação:", error);
    try {
      // Tentar fazer rollback em caso de erro
      await db.runAsync("ROLLBACK");
    } catch (rollbackError) {
      console.error("Erro ao fazer rollback:", rollbackError);
    }
  } finally {
    // Sempre fechar a conexão no final
    db.close();
  }
}

// Executar a função principal
main().catch((err) => {
  console.error("Erro fatal:", err);
  db.close();
});
