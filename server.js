// server.js
const express = require("express");
const sqlite3 = require("sqlite3").verbose();
const bcrypt = require("bcrypt");
const jwt = require("jsonwebtoken");
const cors = require("cors");
const path = require("path");
const bodyParser = require("body-parser");

// Inicializar aplicação
const app = express();
const PORT = process.env.PORT || 3000;
const JWT_SECRET = "sua-chave-secreta-deve-ser-alterada-em-producao";

// Middleware
app.use(cors());
app.use(bodyParser.json());
app.use(express.static(path.join(__dirname, "public")));

// Conectar ao banco de dados
const db = new sqlite3.Database("./database.db", (err) => {
  if (err) {
    console.error("Erro ao conectar ao banco de dados:", err.message);
  } else {
    console.log("Conectado ao banco de dados SQLite");
    initializeDatabase();
  }
});

// Inicializar o banco de dados
function initializeDatabase() {
  // Criar tabela de usuários
  db.run(`CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    senha TEXT NOT NULL,
    nivel_acesso TEXT NOT NULL
  )`);

  // Criar tabela de registros
  db.run(`CREATE TABLE IF NOT EXISTS registros (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nup TEXT,
    dt_entrada_dipli TEXT,
    resp_instrucao TEXT,
    area_demandante TEXT,
    pregoeiro TEXT,
    modalidade TEXT,
    tipo TEXT,
    numero TEXT,
    ano TEXT,
    prioridade TEXT,
    item_pgc TEXT,
    estimado_pgc TEXT,
    ano_pgc TEXT,
    objeto TEXT,
    qtd_itens INTEGER,
    valor_estimado REAL,
    dt_abertura TEXT,
    situacao TEXT,
    andamentos TEXT,
    valor_homologado REAL,
    economia REAL,
    dt_homologacao TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )`);

  // Inserir usuário administrador padrão se não existir
  db.get(
    "SELECT * FROM usuarios WHERE email = 'admin@sistema.com'",
    (err, row) => {
      if (err) {
        console.error(err.message);
      }
      if (!row) {
        bcrypt.hash("admin123", 10, (err, hash) => {
          if (err) {
            console.error(err);
          } else {
            db.run(
              "INSERT INTO usuarios (nome, email, senha, nivel_acesso) VALUES (?, ?, ?, ?)",
              ["Administrador", "admin@sistema.com", hash, "admin"],
              (err) => {
                if (err) {
                  console.error(err.message);
                } else {
                  console.log("Usuário administrador criado com sucesso");
                }
              }
            );
          }
        });
      }
    }
  );
}

// Middleware para verificar autenticação
function authenticateToken(req, res, next) {
  const authHeader = req.headers["authorization"];
  const token = authHeader && authHeader.split(" ")[1];

  if (!token)
    return res
      .status(401)
      .json({ message: "Token de autenticação não fornecido" });

  jwt.verify(token, JWT_SECRET, (err, user) => {
    if (err)
      return res.status(403).json({ message: "Token inválido ou expirado" });
    req.user = user;
    next();
  });
}

// Rota de login
app.post("/api/login", (req, res) => {
  const { email, senha } = req.body;

  if (!email || !senha) {
    return res.status(400).json({ message: "Email e senha são obrigatórios" });
  }

  db.get("SELECT * FROM usuarios WHERE email = ?", [email], (err, user) => {
    if (err) {
      return res.status(500).json({ message: "Erro no servidor" });
    }

    if (!user) {
      return res.status(401).json({ message: "Credenciais inválidas" });
    }

    bcrypt.compare(senha, user.senha, (err, result) => {
      if (err || !result) {
        return res.status(401).json({ message: "Credenciais inválidas" });
      }

      const token = jwt.sign(
        { id: user.id, email: user.email, nivel_acesso: user.nivel_acesso },
        JWT_SECRET,
        { expiresIn: "8h" }
      );

      res.json({
        token,
        user: {
          id: user.id,
          nome: user.nome,
          email: user.email,
          nivel_acesso: user.nivel_acesso,
        },
      });
    });
  });
});

// Rota para obter todos os registros
app.get("/api/registros", authenticateToken, (req, res) => {
  db.all(
    "SELECT * FROM registros ORDER BY data_criacao DESC",
    [],
    (err, rows) => {
      if (err) {
        return res
          .status(500)
          .json({ message: "Erro ao obter registros", error: err.message });
      }
      res.json(rows);
    }
  );
});

// Rota para obter um registro específico
app.get("/api/registros/:id", authenticateToken, (req, res) => {
  db.get(
    "SELECT * FROM registros WHERE id = ?",
    [req.params.id],
    (err, row) => {
      if (err) {
        return res
          .status(500)
          .json({ message: "Erro ao obter registro", error: err.message });
      }
      if (!row) {
        return res.status(404).json({ message: "Registro não encontrado" });
      }
      res.json(row);
    }
  );
});

// Rota para criar um novo registro
app.post("/api/registros", authenticateToken, (req, res) => {
  const {
    nup,
    dt_entrada_dipli,
    resp_instrucao,
    area_demandante,
    pregoeiro,
    modalidade,
    tipo,
    numero,
    ano,
    prioridade,
    item_pgc,
    estimado_pgc,
    ano_pgc,
    objeto,
    qtd_itens,
    valor_estimado,
    dt_abertura,
    situacao,
    andamentos,
    valor_homologado,
    economia,
    dt_homologacao,
  } = req.body;

  const sql = `
    INSERT INTO registros (
      nup, dt_entrada_dipli, resp_instrucao, area_demandante, pregoeiro,
      modalidade, tipo, numero, ano, prioridade, item_pgc, estimado_pgc,
      ano_pgc, objeto, qtd_itens, valor_estimado, dt_abertura, situacao,
      andamentos, valor_homologado, economia, dt_homologacao
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `;

  db.run(
    sql,
    [
      nup,
      dt_entrada_dipli,
      resp_instrucao,
      area_demandante,
      pregoeiro,
      modalidade,
      tipo,
      numero,
      ano,
      prioridade,
      item_pgc,
      estimado_pgc,
      ano_pgc,
      objeto,
      qtd_itens,
      valor_estimado,
      dt_abertura,
      situacao,
      andamentos,
      valor_homologado,
      economia,
      dt_homologacao,
    ],
    function (err) {
      if (err) {
        return res
          .status(500)
          .json({ message: "Erro ao criar registro", error: err.message });
      }

      res.status(201).json({
        id: this.lastID,
        message: "Registro criado com sucesso",
      });
    }
  );
});

// Rota para atualizar um registro
app.put("/api/registros/:id", authenticateToken, (req, res) => {
  const {
    nup,
    dt_entrada_dipli,
    resp_instrucao,
    area_demandante,
    pregoeiro,
    modalidade,
    tipo,
    numero,
    ano,
    prioridade,
    item_pgc,
    estimado_pgc,
    ano_pgc,
    objeto,
    qtd_itens,
    valor_estimado,
    dt_abertura,
    situacao,
    andamentos,
    valor_homologado,
    economia,
    dt_homologacao,
  } = req.body;

  const sql = `
    UPDATE registros SET
      nup = ?,
      dt_entrada_dipli = ?,
      resp_instrucao = ?,
      area_demandante = ?,
      pregoeiro = ?,
      modalidade = ?,
      tipo = ?,
      numero = ?,
      ano = ?,
      prioridade = ?,
      item_pgc = ?,
      estimado_pgc = ?,
      ano_pgc = ?,
      objeto = ?,
      qtd_itens = ?,
      valor_estimado = ?,
      dt_abertura = ?,
      situacao = ?,
      andamentos = ?,
      valor_homologado = ?,
      economia = ?,
      dt_homologacao = ?,
      ultima_atualizacao = CURRENT_TIMESTAMP
    WHERE id = ?
  `;

  db.run(
    sql,
    [
      nup,
      dt_entrada_dipli,
      resp_instrucao,
      area_demandante,
      pregoeiro,
      modalidade,
      tipo,
      numero,
      ano,
      prioridade,
      item_pgc,
      estimado_pgc,
      ano_pgc,
      objeto,
      qtd_itens,
      valor_estimado,
      dt_abertura,
      situacao,
      andamentos,
      valor_homologado,
      economia,
      dt_homologacao,
      req.params.id,
    ],
    function (err) {
      if (err) {
        return res
          .status(500)
          .json({ message: "Erro ao atualizar registro", error: err.message });
      }

      if (this.changes === 0) {
        return res.status(404).json({ message: "Registro não encontrado" });
      }

      res.json({ message: "Registro atualizado com sucesso" });
    }
  );
});

// Rota para excluir um registro
app.delete("/api/registros/:id", authenticateToken, (req, res) => {
  db.run("DELETE FROM registros WHERE id = ?", [req.params.id], function (err) {
    if (err) {
      return res
        .status(500)
        .json({ message: "Erro ao excluir registro", error: err.message });
    }

    if (this.changes === 0) {
      return res.status(404).json({ message: "Registro não encontrado" });
    }

    res.json({ message: "Registro excluído com sucesso" });
  });
});

// Rota para criar um novo usuário (apenas admin pode criar)
app.post("/api/usuarios", authenticateToken, (req, res) => {
  // Verificar se o usuário atual é admin
  if (req.user.nivel_acesso !== "admin") {
    return res.status(403).json({
      message: "Acesso negado. Apenas administradores podem criar usuários.",
    });
  }

  const { nome, email, senha, nivel_acesso } = req.body;

  if (!nome || !email || !senha || !nivel_acesso) {
    return res
      .status(400)
      .json({ message: "Todos os campos são obrigatórios" });
  }

  // Verificar se o email já está em uso
  db.get("SELECT id FROM usuarios WHERE email = ?", [email], (err, row) => {
    if (err) {
      return res.status(500).json({ message: "Erro no servidor" });
    }

    if (row) {
      return res.status(400).json({ message: "Este email já está em uso" });
    }

    // Criptografar a senha
    bcrypt.hash(senha, 10, (err, hash) => {
      if (err) {
        return res.status(500).json({ message: "Erro ao criptografar senha" });
      }

      // Inserir o novo usuário
      db.run(
        "INSERT INTO usuarios (nome, email, senha, nivel_acesso) VALUES (?, ?, ?, ?)",
        [nome, email, hash, nivel_acesso],
        function (err) {
          if (err) {
            return res
              .status(500)
              .json({ message: "Erro ao criar usuário", error: err.message });
          }

          res.status(201).json({
            message: "Usuário criado com sucesso",
            userId: this.lastID,
          });
        }
      );
    });
  });
});

// Rota para listar todos os usuários (apenas admin)
app.get("/api/usuarios", authenticateToken, (req, res) => {
  if (req.user.nivel_acesso !== "admin") {
    return res.status(403).json({
      message: "Acesso negado. Apenas administradores podem listar usuários.",
    });
  }

  db.all(
    "SELECT id, nome, email, nivel_acesso FROM usuarios",
    [],
    (err, rows) => {
      if (err) {
        return res
          .status(500)
          .json({ message: "Erro ao listar usuários", error: err.message });
      }

      res.json(rows);
    }
  );
});

// Rota de página inicial para servir o frontend
app.get("*", (req, res) => {
  res.sendFile(path.join(__dirname, "public", "index.html"));
});

// Iniciar o servidor
app.listen(PORT, () => {
  console.log(`Servidor rodando na porta ${PORT}`);
});
