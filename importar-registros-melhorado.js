const XLSX = require("xlsx");
const sqlite3 = require("sqlite3").verbose();
const path = require("path");
const { promisify } = require("util");
const fs = require("fs");

// Caminho do arquivo Excel - será passado como argumento ou poderá ser definido aqui
const excelFilePath = process.argv[2];

// Verifica se o caminho do arquivo foi fornecido
if (!excelFilePath) {
  console.log(
    "Uso: node importar-registros-melhorado.js <caminho-do-arquivo-excel>"
  );
  console.log(
    "Exemplo: node importar-registros-melhorado.js planilha-registros.xlsx"
  );
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
// IMPORTANTE: Ajuste esse mapeamento conforme os cabeçalhos da sua planilha
const mapeamento = {
  // Cabeçalhos obrigatórios
  NUP: "nup",
  OBJETO: "objeto",

  // Tipos comuns de cabeçalhos para "situacao"
  SITUAÇÃO: "situacao",
  SITUACAO: "situacao",
  STATUS: "situacao",

  // Tipos comuns de cabeçalhos para valores monetários
  "VALOR ESTIMADO": "valor_estimado",
  "VALOR ESTIMADO (R$)": "valor_estimado",
  " VALOR ESTIMADO (R$) ": "valor_estimado",
  "VL ESTIMADO": "valor_estimado",
  "VALOR HOMOLOGADO": "valor_homologado",
  "VALOR HOMOLOGADO (R$)": "valor_homologado",
  " VALOR HOMOLOGADO (R$) ": "valor_homologado",
  "VL HOMOLOGADO": "valor_homologado",
  ECONOMIA: "economia",
  "ECONOMIA (R$)": "economia",
  " ECONOMIA (R$) ": "economia",

  // Outros cabeçalhos comuns
  "DT ENTRADA DIPLI": "dt_entrada_dipli",
  "RESP. INSTRUÇÃO": "resp_instrucao",
  "ÁREA DEMANDANTE": "area_demandante",
  PREGOEIRO: "pregoeiro",
  MODALIDADE: "modalidade",
  TIPO: "tipo",
  NÚMERO: "numero",
  Nº: "numero",
  ANO: "ano",
  PRIORIDADE: "prioridade",
  "ITEM PGC": "item_pgc",
  "ESTIMADO PGC": "estimado_pgc",
  "ANO PGC": "ano_pgc",
  "QTD ITENS": "qtd_itens",
  "DT ABERTURA": "dt_abertura",
  ANDAMENTOS: "andamentos",
  "DT HOMOLOGAÇÃO": "dt_homologacao",
};

// Função para normalizar a situação para usar valores padronizados no sistema
function normalizarSituacao(valor) {
  if (!valor) return null;

  const valorUpper = String(valor).toUpperCase().trim();

  // Mapeamento de valores comuns
  if (valorUpper.includes("HOMOLOG")) return "Homologado";
  if (valorUpper.includes("ANDAMENTO") || valorUpper.includes("EM AND"))
    return "Em Andamento";
  if (valorUpper.includes("ANÁLISE") || valorUpper.includes("ANALISE"))
    return "Em Análise";
  if (valorUpper.includes("FRACAS")) return "Fracassado";
  if (valorUpper.includes("DESERT")) return "Deserto";
  if (valorUpper.includes("CANCEL")) return "Cancelado";

  // Se não encontrar um mapeamento, retorna o valor original com a primeira letra maiúscula
  return valor.charAt(0).toUpperCase() + valor.slice(1).toLowerCase();
}

// Função melhorada para converter valores monetários
function converterValorMonetario(valor) {
  if (valor === null || valor === undefined) return null;

  // Se já for um número, simplesmente retorna
  if (typeof valor === "number") return valor;

  // Se for uma string, tenta converter para número
  if (typeof valor === "string") {
    // Remove qualquer caractere que não seja dígito, ponto ou vírgula
    let valorLimpo = valor.replace(/[^\d,.]/g, "");

    // Lidar com formatações diferentes (ex: 1.234,56 vs 1,234.56)
    if (valorLimpo.includes(",") && valorLimpo.includes(".")) {
      // Se tem ambos, assume formato brasileiro: 1.234,56
      const partes = valorLimpo.split(",");
      if (partes.length > 1) {
        const decimais = partes.pop();
        valorLimpo = partes.join("").replace(/\./g, "") + "." + decimais;
      }
    } else {
      // Caso tenha apenas vírgula, substitui por ponto
      valorLimpo = valorLimpo.replace(",", ".");
    }

    // Converte para número
    const numero = parseFloat(valorLimpo);

    // Retorna null se não for um número válido
    return isNaN(numero) ? null : numero;
  }

  return null;
}

// Função para converter datas com melhor tratamento de formatos
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

  // Se for uma data no formato MM/DD/YYYY (formato americano comum)
  if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(data)) {
    try {
      const date = new Date(data);
      if (!isNaN(date.getTime())) {
        return date.toISOString().split("T")[0]; // formato YYYY-MM-DD
      }
    } catch (e) {
      // Continua para outras tentativas
    }
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

    // Obter os cabeçalhos da planilha para diagnóstico
    if (data.length > 0) {
      console.log("\nCabeçalhos encontrados na planilha:");
      const headers = Object.keys(data[0]);
      headers.forEach((header) => {
        console.log(
          `- "${header}" ${
            mapeamento[header] ? `-> ${mapeamento[header]}` : "(não mapeado)"
          }`
        );
      });
      console.log("");
    }

    // Filtrar registros válidos e mapear para os campos do banco
    const validRecords = [];

    data.forEach((row, index) => {
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

              // Log para diagnóstico
              if (index < 2) {
                // Só mostra os primeiros registros para não poluir a saída
                console.log(
                  `Conversão de valor: "${coluna}" = "${valor}" -> ${mappedRecord[campoBD]}`
                );
              }
            } else if (campoBD === "situacao") {
              mappedRecord[campoBD] = normalizarSituacao(valor);

              // Log para diagnóstico
              if (index < 2) {
                console.log(
                  `Normalização de situação: "${valor}" -> "${mappedRecord[campoBD]}"`
                );
              }
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
      `\n${validRecords.length} registros válidos encontrados para importação`
    );

    if (validRecords.length === 0) {
      console.log(
        "Nenhum registro válido encontrado na planilha. Verifique os nomes das colunas."
      );
      return db.close();
    }

    // Estatísticas de situação
    const estatisticasSituacao = {};
    validRecords.forEach((record) => {
      if (record.situacao) {
        estatisticasSituacao[record.situacao] =
          (estatisticasSituacao[record.situacao] || 0) + 1;
      }
    });

    console.log("\nDistribuição por situação:");
    Object.entries(estatisticasSituacao).forEach(([situacao, count]) => {
      console.log(`- ${situacao}: ${count} registros`);
    });

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

    // Perguntar se deseja continuar com a importação
    console.log("\nDeseja continuar com a importação? (S/N)");

    // Função para ler uma linha da entrada padrão
    const readLine = () => {
      return new Promise((resolve) => {
        const readline = require("readline").createInterface({
          input: process.stdin,
          output: process.stdout,
        });

        readline.question("", (answer) => {
          readline.close();
          resolve(answer.trim().toUpperCase());
        });
      });
    };

    const resposta = await readLine();
    if (resposta !== "S") {
      console.log("Importação cancelada pelo usuário.");
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

    console.log(
      `\nIniciando importação de ${validRecords.length} registros...`
    );

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

    console.log(`\nImportação concluída!`);
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
