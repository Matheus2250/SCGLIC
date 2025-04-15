const XLSX = require("xlsx");
const fs = require("fs");
const path = require("path");

// Verifica se o caminho do arquivo foi fornecido
const excelFilePath = process.argv[2];
if (!excelFilePath) {
  console.log("Uso: node diagnostico-importacao.js <caminho-do-arquivo-excel>");
  console.log(
    "Exemplo: node diagnostico-importacao.js planilha-registros.xlsx"
  );
  process.exit(1);
}

// Verifica se o arquivo existe
if (!fs.existsSync(excelFilePath)) {
  console.error(`Erro: Arquivo não encontrado: ${excelFilePath}`);
  process.exit(1);
}

console.log(`\n==== DIAGNÓSTICO DE IMPORTAÇÃO EXCEL ====`);
console.log(`Analisando arquivo: ${excelFilePath}\n`);

try {
  // Ler a planilha
  const workbook = XLSX.readFile(excelFilePath);
  const sheetNames = workbook.SheetNames;
  const firstSheet = sheetNames[0];
  console.log(`Planilha encontrada: ${firstSheet}`);

  // Converter para JSON
  const worksheet = workbook.Sheets[firstSheet];
  const data = XLSX.utils.sheet_to_json(worksheet);
  console.log(`Lidos ${data.length} registros da planilha\n`);

  if (data.length === 0) {
    console.log(
      "A planilha não contém registros. Verifique se ela possui cabeçalhos válidos."
    );
    process.exit(1);
  }

  // Obter cabeçalhos da planilha
  const headers = Object.keys(data[0]);
  console.log(`====== CABEÇALHOS ENCONTRADOS ======`);
  headers.forEach((header) => {
    console.log(`- "${header}"`);
  });
  console.log("");

  // Verificar cabeçalhos importantes
  const situacaoHeaders = headers.filter(
    (h) =>
      h.toUpperCase().includes("SITUAÇÃO") ||
      h.toUpperCase().includes("SITUACAO") ||
      h.toUpperCase().includes("STATUS")
  );

  const valorHeaders = headers.filter(
    (h) =>
      h.toUpperCase().includes("VALOR") ||
      h.toUpperCase().includes("VALOR ESTIMADO") ||
      h.toUpperCase().includes("HOMOLOGADO") ||
      h.toUpperCase().includes("ECONOMIA")
  );

  console.log(`====== ANÁLISE DE CABEÇALHOS CRÍTICOS ======`);
  console.log(
    `Cabeçalhos de Situação: ${
      situacaoHeaders.length > 0
        ? situacaoHeaders.join(", ")
        : "Nenhum encontrado"
    }`
  );
  console.log(
    `Cabeçalhos de Valores: ${
      valorHeaders.length > 0 ? valorHeaders.join(", ") : "Nenhum encontrado"
    }\n`
  );

  // Análise dos valores de situação
  if (situacaoHeaders.length > 0) {
    console.log(`====== ANÁLISE DE VALORES DE SITUAÇÃO ======`);
    situacaoHeaders.forEach((header) => {
      const valores = [
        ...new Set(data.map((row) => row[header]).filter(Boolean)),
      ];
      console.log(`Valores únicos em "${header}":`);
      valores.forEach((valor) => {
        const count = data.filter((row) => row[header] === valor).length;
        console.log(`  - "${valor}" (${count} ocorrências)`);
      });
      console.log("");
    });
  }

  // Análise dos valores monetários
  if (valorHeaders.length > 0) {
    console.log(`====== ANÁLISE DE VALORES MONETÁRIOS ======`);
    valorHeaders.forEach((header) => {
      const exemplos = data
        .slice(0, 5)
        .map((row) => row[header])
        .filter(Boolean);
      console.log(`Exemplos de valores em "${header}":`);
      exemplos.forEach((valor, idx) => {
        console.log(
          `  - Amostra ${idx + 1}: "${valor}" (Tipo: ${typeof valor})`
        );

        // Testar conversão
        if (typeof valor === "string") {
          // Remove caracteres não numéricos exceto o ponto ou vírgula
          const valorLimpo = valor.replace(/[^\d,.]/g, "");
          // Substitui vírgula por ponto
          const valorPonto = valorLimpo.replace(",", ".");
          // Converte para número
          const valorNumerico = parseFloat(valorPonto);

          console.log(`    * Valor após limpeza: "${valorLimpo}"`);
          console.log(`    * Valor substituindo vírgula: "${valorPonto}"`);
          console.log(
            `    * Valor numérico: ${
              isNaN(valorNumerico) ? "NaN (conversão falhou)" : valorNumerico
            }`
          );
        }
      });
      console.log("");
    });
  }

  // Contagem de registros por classificações importantes
  console.log(`====== ESTATÍSTICAS GERAIS ======`);

  if (situacaoHeaders.length > 0) {
    const situacaoHeader = situacaoHeaders[0];
    const homologados = data.filter((row) => {
      const valor = row[situacaoHeader];
      return valor && String(valor).toUpperCase().includes("HOMOLOG");
    }).length;

    const emAndamento = data.filter((row) => {
      const valor = row[situacaoHeader];
      return valor && String(valor).toUpperCase().includes("ANDAMENTO");
    }).length;

    console.log(`Registros possivelmente homologados: ${homologados}`);
    console.log(`Registros possivelmente em andamento: ${emAndamento}`);
  }

  // Sugestões de ajustes no mapeamento
  console.log(`\n====== SUGESTÕES DE MAPEAMENTO ======`);
  console.log(
    "Para corrigir problemas de importação, utilize o seguinte mapeamento no seu código:"
  );
  console.log("\nconst mapeamento = {");

  headers.forEach((header) => {
    let fieldName = "";

    // Mapear situação
    if (
      header.toUpperCase().includes("SITUAÇÃO") ||
      header.toUpperCase().includes("SITUACAO") ||
      header.toUpperCase().includes("STATUS")
    ) {
      fieldName = "situacao";
    }
    // Mapear valores
    else if (header.toUpperCase().includes("VALOR ESTIMADO")) {
      fieldName = "valor_estimado";
    } else if (header.toUpperCase().includes("VALOR HOMOLOGADO")) {
      fieldName = "valor_homologado";
    } else if (header.toUpperCase().includes("ECONOMIA")) {
      fieldName = "economia";
    }
    // Outros campos comuns
    else if (header.toUpperCase() === "NUP") {
      fieldName = "nup";
    } else if (header.toUpperCase().includes("OBJETO")) {
      fieldName = "objeto";
    } else if (header.toUpperCase().includes("MODALIDADE")) {
      fieldName = "modalidade";
    }

    if (fieldName) {
      console.log(`  '${header}': '${fieldName}',`);
    }
  });

  console.log("  // Adicione os demais campos conforme necessário");
  console.log("};");

  console.log("\n====== SUGESTÕES DE CORREÇÃO ======");
  console.log(
    "1. Verifique os nomes exatos dos cabeçalhos na sua planilha e ajuste o mapeamento no script importar-registros.js"
  );
  console.log(
    "2. Para campos de valor monetário, certifique-se de que a função converterValorMonetario está lidando corretamente com o formato dos valores"
  );
  console.log(
    "3. Para o campo situação, verifique se os valores estão sendo normalizados para corresponder aos valores esperados pelo sistema"
  );
} catch (error) {
  console.error("\nErro durante a análise da planilha:", error);
}
