// Arquivo de teste para identificar problemas com rotas
const express = require("express");
const app = express();
const PORT = 3001;

// Rota básica
app.get("/api/test", (req, res) => {
  res.json({ message: "API de teste funcionando!" });
});

// Rota catch-all original que pode estar causando o problema
app.get("*", (req, res) => {
  res.send("Rota catch-all");
});

// Iniciar o servidor de teste
app.listen(PORT, () => {
  console.log(`Servidor de teste rodando na porta ${PORT}`);
});
