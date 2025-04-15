// Script de diagnóstico simplificado
const fs = require("fs");
const path = require("path");

try {
  console.log("Verificando problemas...");

  // Ler o arquivo package.json
  const packageJsonPath = path.join(__dirname, "package.json");
  console.log("Verificando arquivo:", packageJsonPath);

  if (fs.existsSync(packageJsonPath)) {
    const packageData = fs.readFileSync(packageJsonPath, "utf8");
    const packageJson = JSON.parse(packageData);
    console.log("Express versão:", packageJson.dependencies.express);
  } else {
    console.log("package.json não encontrado");
  }

  // Ler o arquivo server.js
  const serverJsPath = path.join(__dirname, "server.js");
  console.log("\nVerificando arquivo:", serverJsPath);

  if (fs.existsSync(serverJsPath)) {
    const serverJs = fs.readFileSync(serverJsPath, "utf8");

    // Procurar pela rota problemática
    if (serverJs.includes('app.get("*"')) {
      console.log('⚠️ ENCONTRADO PROBLEMA: Rota catch-all usando "*"');
      console.log("Essa é provavelmente a causa do erro!");
    }

    // Procurar pelo require do express
    console.log("\nExpressJS importação:");
    const expressRequire = serverJs.match(/require\(['"](express)['"]\)/);
    if (expressRequire) {
      console.log("Express importado corretamente");
    }
  } else {
    console.log("server.js não encontrado");
  }

  console.log("\nSugestão de correção:");
  console.log(
    '1. Altere a rota catch-all de app.get("*", ...) para app.get("/*", ...)'
  );
  console.log(
    "2. Tente usar uma versão específica do express: npm install express@4.17.1"
  );
} catch (error) {
  console.error("Erro durante diagnóstico:", error);
}
