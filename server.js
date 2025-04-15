// server.js
const express = require("express");
const sqlite3 = require("sqlite3").verbose();
const bcrypt = require("bcrypt");
const jwt = require("jsonwebtoken");
const cors = require("cors");
const path = require("path");
const bodyParser = require("body-parser");
const XLSX = require("xlsx");
const multer = require("multer");
const fs = require("fs");

// Inicializar aplicação
const app = express();
const PORT = process.env.PORT || 3001;
const JWT_SECRET = "sua-chave-secreta-deve-ser-alterada-em-producao";

// Middleware
app.use(cors({
  origin: '*',
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization']
}));
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, "public")));

// Configurar middleware para upload de arquivos
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 10 * 1024 * 1024 } // limite de 10MB
});

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
  // Tabelas existentes
  db.run(`CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    senha TEXT NOT NULL,
    nivel_acesso TEXT NOT NULL
  )`);

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

  // Nova tabela de atividades
  db.run(`CREATE TABLE IF NOT EXISTS atividades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    usuario_nome TEXT NOT NULL,
    acao TEXT NOT NULL,
    registro_id INTEGER,
    registro_descricao TEXT,
    detalhes TEXT,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
  )`);

  // Usuário padrão (existente)
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

                  // Registrar atividade de criação do administrador
                  registrarAtividade(
                    1,
                    "Administrador",
                    "Criação de Sistema",
                    null,
                    null,
                    "Sistema CGLIC inicializado com usuário administrador"
                  );
                }
              }
            );
          }
        });
      }
    }
  );
}

// Função para registrar atividades
function registrarAtividade(
  usuarioId,
  usuarioNome,
  acao,
  registroId,
  registroDescricao,
  detalhes
) {
  const sql = `
    INSERT INTO atividades (
      usuario_id, usuario_nome, acao, registro_id, registro_descricao, detalhes
    ) VALUES (?, ?, ?, ?, ?, ?)
  `;

  db.run(
    sql,
    [usuarioId, usuarioNome, acao, registroId, registroDescricao, detalhes],
    function (err) {
      if (err) {
        console.error("Erro ao registrar atividade:", err.message);
      }
    }
  );
}

// Validação de email com domínio @saude.gov.br
function validarEmailSaude(email) {
  const regex = /@saude\.gov\.br$/i;
  return regex.test(email);
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
        {
          id: user.id,
          email: user.email,
          nivel_acesso: user.nivel_acesso,
          nome: user.nome,
        },
        JWT_SECRET,
        { expiresIn: "8h" }
      );

      // Registrar atividade de login
      registrarAtividade(
        user.id,
        user.nome,
        "Login",
        null,
        null,
        `Login bem-sucedido`
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

// Rota para registro de novos usuários
app.post("/api/register", (req, res) => {
  try {
    const { nome, email, senha } = req.body;

    if (!nome || !email || !senha) {
      return res
        .status(400)
        .json({ message: "Todos os campos são obrigatórios" });
    }

    // Validar domínio de email
    if (!validarEmailSaude(email)) {
      return res.status(400).json({
        message: "Apenas emails com domínio @saude.gov.br são aceitos",
      });
    }

    // Verificar se o email já está em uso
    db.get("SELECT id FROM usuarios WHERE email = ?", [email], (err, row) => {
      if (err) {
        console.error("Erro na consulta:", err);
        return res
          .status(500)
          .json({ message: "Erro no servidor", error: err.message });
      }

      if (row) {
        return res.status(400).json({ message: "Este email já está em uso" });
      }

      // Criptografar a senha
      bcrypt.hash(senha, 10, (err, hash) => {
        if (err) {
          console.error("Erro ao criptografar:", err);
          return res.status(500).json({
            message: "Erro ao criptografar senha",
            error: err.message,
          });
        }

        // Todos os novos usuários começam como usuários normais (não admin)
        const nivel_acesso = "usuario";

        // Inserir o novo usuário
        db.run(
          "INSERT INTO usuarios (nome, email, senha, nivel_acesso) VALUES (?, ?, ?, ?)",
          [nome, email, hash, nivel_acesso],
          function (err) {
            if (err) {
              console.error("Erro na inserção:", err);
              return res
                .status(500)
                .json({ message: "Erro ao criar usuário", error: err.message });
            }

            // Registrar atividade
            registrarAtividade(
              this.lastID,
              nome,
              "Cadastro",
              null,
              null,
              `Novo usuário cadastrado: ${email}`
            );

            res.status(201).json({
              message: "Usuário cadastrado com sucesso",
              userId: this.lastID,
            });
          }
        );
      });
    });
  } catch (error) {
    console.error("Erro geral:", error);
    res
      .status(500)
      .json({ message: "Erro interno no servidor", error: error.message });
  }
});

// Rota para obter atividades recentes
app.get("/api/atividades", authenticateToken, (req, res) => {
  db.all(
    "SELECT * FROM atividades ORDER BY data_hora DESC LIMIT 20",
    [],
    (err, rows) => {
      if (err) {
        return res
          .status(500)
          .json({ message: "Erro ao obter atividades", error: err.message });
      }
      res.json(rows);
    }
  );
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

      // Registrar atividade
      registrarAtividade(
        req.user.id,
        req.user.nome || req.user.email,
        "Criação",
        this.lastID,
        `NUP: ${nup}`,
        `Novo registro criado: ${objeto}`
      );

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

      // Registrar atividade
      registrarAtividade(
        req.user.id,
        req.user.nome || req.user.email,
        "Atualização",
        req.params.id,
        `NUP: ${nup}`,
        `Registro atualizado: ${objeto}`
      );

      res.json({ message: "Registro atualizado com sucesso" });
    }
  );
});

// Rota para excluir um registro
app.delete("/api/registros/:id", authenticateToken, (req, res) => {
  // Primeiro obter informações do registro para registrar na atividade
  db.get(
    "SELECT nup, objeto FROM registros WHERE id = ?",
    [req.params.id],
    (err, registro) => {
      if (err) {
        return res
          .status(500)
          .json({ message: "Erro ao buscar registro", error: err.message });
      }

      if (!registro) {
        return res.status(404).json({ message: "Registro não encontrado" });
      }

      // Excluir o registro
      db.run(
        "DELETE FROM registros WHERE id = ?",
        [req.params.id],
        function (err) {
          if (err) {
            return res.status(500).json({
              message: "Erro ao excluir registro",
              error: err.message,
            });
          }

          // Registrar atividade
          registrarAtividade(
            req.user.id,
            req.user.nome || req.user.email,
            "Exclusão",
            req.params.id,
            `NUP: ${registro.nup}`,
            `Registro excluído: ${registro.objeto}`
          );

          res.json({ message: "Registro excluído com sucesso" });
        }
      );
    }
  );
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

          // Registrar atividade
          registrarAtividade(
            req.user.id,
            req.user.nome || req.user.email,
            "Criação de Usuário",
            null,
            null,
            `Usuário criado: ${nome} (${email}) - Nível: ${nivel_acesso}`
          );

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

// Rota para excluir um usuário (apenas admin)
app.delete("/api/usuarios/:id", authenticateToken, (req, res) => {
  if (req.user.nivel_acesso !== "admin") {
    return res.status(403).json({
      message: "Acesso negado. Apenas administradores podem excluir usuários.",
    });
  }

  // Impedir excluir o próprio usuário
  if (req.user.id == req.params.id) {
    return res.status(403).json({
      message: "Você não pode excluir seu próprio usuário.",
    });
  }

  // Primeiro obter informações do usuário para registrar na atividade
  db.get(
    "SELECT nome, email FROM usuarios WHERE id = ?",
    [req.params.id],
    (err, usuario) => {
      if (err) {
        return res
          .status(500)
          .json({ message: "Erro ao buscar usuário", error: err.message });
      }

      if (!usuario) {
        return res.status(404).json({ message: "Usuário não encontrado" });
      }

      // Excluir o usuário
      db.run(
        "DELETE FROM usuarios WHERE id = ?",
        [req.params.id],
        function (err) {
          if (err) {
            return res
              .status(500)
              .json({ message: "Erro ao excluir usuário", error: err.message });
          }

          // Registrar atividade
          registrarAtividade(
            req.user.id,
            req.user.nome || req.user.email,
            "Exclusão de Usuário",
            null,
            null,
            `Usuário excluído: ${usuario.nome} (${usuario.email})`
          );

          res.json({ message: "Usuário excluído com sucesso" });
        }
      );
    }
  );
});

// Rota para exportar registros no formato CSV
app.post("/api/export/csv", authenticateToken, (req, res) => {
  try {
    // Obter registros do banco de dados
    db.all("SELECT * FROM registros", [], (err, registros) => {
      if (err) {
        console.error("Erro ao buscar registros:", err);
        return res.status(500).json({ mensagem: "Erro ao buscar registros", erro: err.message });
      }

      // Garantir que registros seja um array
      if (!Array.isArray(registros)) {
        console.error("Registros não é um array:", registros);
        registros = [];
      }

      // Aplicar filtros
      const filtrosReq = req.body.filtros || [];
      const registrosFiltrados = aplicarFiltros(registros, filtrosReq);

      // Gerar CSV
      let csvContent = "";
      if (registrosFiltrados.length > 0) {
        const colunas = Object.keys(registrosFiltrados[0]);
        csvContent = colunas.join(",") + "\n";
        
        registrosFiltrados.forEach(registro => {
          const linha = colunas.map(coluna => {
            const valor = registro[coluna];
            if (valor === null || valor === undefined) return "";
            return `"${String(valor).replace(/"/g, '""')}"`;
          });
          csvContent += linha.join(",") + "\n";
        });
      } else {
        csvContent = "Nenhum registro encontrado";
      }

      // Configurar headers
      res.setHeader("Content-Type", "text/csv");
      res.setHeader(
        "Content-Disposition",
        `attachment; filename=registros_${new Date().toISOString().split("T")[0]}.csv`
      );

      // Enviar resposta
      res.send(csvContent);

      // Registrar atividade
      registrarAtividade(
        req.user.id,
        req.user.nome,
        "Exportação CSV",
        null,
        null,
        `Exportou ${registrosFiltrados.length} registros em formato CSV`
      );
    });
  } catch (error) {
    console.error("Erro ao exportar registros para CSV:", error);
    res.status(500).json({ mensagem: "Erro ao exportar registros", erro: error.message });
  }
});

// Rota para exportar registros no formato Excel
app.post("/api/export/excel", authenticateToken, (req, res) => {
  try {
    // Obter registros do banco de dados
    db.all("SELECT * FROM registros", [], (err, registros) => {
      if (err) {
        console.error("Erro ao buscar registros:", err);
        return res.status(500).json({ mensagem: "Erro ao buscar registros", erro: err.message });
      }

      // Garantir que registros seja um array
      if (!Array.isArray(registros)) {
        console.error("Registros não é um array:", registros);
        registros = [];
      }

      // Aplicar filtros
      const filtrosReq = req.body.filtros || [];
      const registrosFiltrados = aplicarFiltros(registros, filtrosReq);

      // Criar workbook e worksheet
      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(registrosFiltrados);

      // Adicionar worksheet ao workbook
      XLSX.utils.book_append_sheet(wb, ws, "Registros");

      // Gerar arquivo Excel
      const excelBuffer = XLSX.write(wb, { type: "buffer", bookType: "xlsx" });

      // Configurar headers
      res.setHeader(
        "Content-Type",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
      );
      res.setHeader(
        "Content-Disposition",
        `attachment; filename=registros_${new Date().toISOString().split("T")[0]}.xlsx`
      );

      // Enviar resposta
      res.send(excelBuffer);

      // Registrar atividade
      registrarAtividade(
        req.user.id,
        req.user.nome,
        "Exportação Excel",
        null,
        null,
        `Exportou ${registrosFiltrados.length} registros em formato Excel`
      );
    });
  } catch (error) {
    console.error("Erro ao exportar registros para Excel:", error);
    res.status(500).json({ mensagem: "Erro ao exportar registros", erro: error.message });
  }
});

// Rota para importar registros de um arquivo Excel
app.post('/api/importar', authenticateToken, upload.single('file'), async (req, res) => {
  if (!req.file) {
    return res.status(400).json({ error: 'Nenhum arquivo foi enviado.' });
  }

  const workbook = xlsx.readFile(req.file.path);
  const worksheet = workbook.Sheets[workbook.SheetNames[0]];
  const registros = xlsx.utils.sheet_to_json(worksheet);

  if (!registros || registros.length === 0) {
    return res.status(400).json({ error: 'Planilha vazia ou sem dados válidos.' });
  }

  // Inicia uma transação para garantir consistência dos dados
  db.serialize(() => {
    db.run('BEGIN TRANSACTION');

    try {
      let totalImportados = 0;
      let erros = [];

      for (let i = 0; i < registros.length; i++) {
        const registro = registros[i];
        
        // Valida e converte os valores monetários
        const valor_estimado = validarValorMonetario(registro.valor_estimado);
        const valor_homologado = validarValorMonetario(registro.valor_homologado);
        
        // Calcula economia apenas se ambos os valores forem válidos
        const economia = (valor_estimado && valor_homologado) ? valor_estimado - valor_homologado : 0;

        // Valida campos obrigatórios
        if (!registro.processo || !registro.objeto) {
          erros.push(`Linha ${i + 2}: Processo ou objeto ausente`);
          continue;
        }

        // Insere o registro
        db.run(
          `INSERT INTO registros (
            processo, objeto, valor_estimado, valor_homologado, economia, 
            data_abertura, data_homologacao, situacao, modalidade, 
            created_at, updated_at
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))`,
          [
            registro.processo,
            registro.objeto,
            valor_estimado,
            valor_homologado,
            economia,
            registro.data_abertura || null,
            registro.data_homologacao || null,
            registro.situacao || 'Em andamento',
            registro.modalidade || 'Não informada'
          ],
          function(err) {
            if (err) {
              erros.push(`Linha ${i + 2}: ${err.message}`);
            } else {
              totalImportados++;
            }
          }
        );
      }

      // Se houver erros em todos os registros, faz rollback
      if (erros.length === registros.length) {
        db.run('ROLLBACK');
        return res.status(400).json({
          error: 'Falha total na importação',
          detalhes: erros
        });
      }

      // Caso contrário, commit e registra atividade
      db.run('COMMIT');
      
      // Registra a atividade de importação
      registrarAtividade(
        req.user.id,
        req.user.nome,
        'importacao',
        null,
        `Importação de ${totalImportados} registros`,
        erros.length > 0 ? `Com ${erros.length} erros` : 'Sem erros'
      );

      res.json({
        message: `Importação concluída. ${totalImportados} registros importados.`,
        erros: erros.length > 0 ? erros : undefined
      });

    } catch (error) {
      db.run('ROLLBACK');
      console.error('Erro na importação:', error);
      res.status(500).json({
        error: 'Erro ao importar os valores',
        detalhes: error.message
      });
    } finally {
      // Limpa o arquivo temporário
      fs.unlinkSync(req.file.path);
    }
  });
});

// Rota para importar valores monetários
app.post('/api/registros/importar-valores', authenticateToken, upload.single('file'), async (req, res) => {
  const file = req.file;
  
  if (!file) {
    return res.status(400).json({ error: 'Nenhum arquivo foi enviado' });
  }

  try {
    // Read Excel file
    const workbook = XLSX.read(file.buffer, { type: 'buffer' });
    const worksheet = workbook.Sheets[workbook.SheetNames[0]];
    const data = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

    if (data.length < 2) {
      return res.status(400).json({ error: 'O arquivo está vazio ou não contém dados válidos' });
    }

    // Get header row and find required column indices
    const headers = data[0].map(h => h?.toString().toLowerCase().trim());
    const nupIndex = headers.indexOf('nup');
    const valorEstimadoIndex = headers.indexOf('valor estimado');
    const valorHomologadoIndex = headers.indexOf('valor homologado');

    if (nupIndex === -1 || valorEstimadoIndex === -1 || valorHomologadoIndex === -1) {
      return res.status(400).json({ error: 'O arquivo deve conter as colunas: NUP, Valor Estimado e Valor Homologado' });
    }

    // Process data rows
    const processedData = data.slice(1)
      .filter(row => row[nupIndex]) // Filter out empty rows
      .map(row => ({
        nup: row[nupIndex]?.toString().trim(),
        valorEstimado: parseFloat(row[valorEstimadoIndex]?.toString().replace(/[^\d.,]/g, '').replace(',', '.')) || null,
        valorHomologado: parseFloat(row[valorHomologadoIndex]?.toString().replace(/[^\d.,]/g, '').replace(',', '.')) || null
      }))
      .filter(item => item.nup && (!isNaN(item.valorEstimado) || !isNaN(item.valorHomologado)));

    if (processedData.length === 0) {
      return res.status(400).json({ error: 'Nenhum registro válido encontrado no arquivo' });
    }

    // Start transaction
    await new Promise((resolve, reject) => {
      db.run('BEGIN TRANSACTION', (err) => {
        if (err) reject(err);
        else resolve();
      });
    });

    try {
      // Update records
      let updatedCount = 0;
      for (const item of processedData) {
        const updates = [];
        const params = [];
        
        if (!isNaN(item.valorEstimado)) {
          updates.push('valor_estimado = ?');
          params.push(item.valorEstimado);
        }
        
        if (!isNaN(item.valorHomologado)) {
          updates.push('valor_homologado = ?');
          params.push(item.valorHomologado);
        }
        
        // Skip this iteration if there are no updates to make
        if (updates.length === 0) {
          continue;
        }
        
        params.push(item.nup);
        
        const query = `UPDATE registros SET ${updates.join(', ')} WHERE nup = ?`;
        
        await new Promise((resolve, reject) => {
          db.run(query, params, function(err) {
            if (err) reject(err);
            else {
              if (this.changes > 0) updatedCount++;
              resolve();
            }
          });
        });
      }

      // Log activity
      await registrarAtividade(
        req.user.id,
        req.user.nome,
        'importar_valores',
        null,
        null,
        `Importou valores para ${updatedCount} registros`
      );

      // Commit transaction
      await new Promise((resolve, reject) => {
        db.run('COMMIT', (err) => {
          if (err) reject(err);
          else resolve();
        });
      });

      res.json({ 
        message: 'Importação concluída com sucesso',
        updatedCount 
      });

    } catch (error) {
      // Rollback transaction on error
      await new Promise((resolve) => {
        db.run('ROLLBACK', () => resolve());
      });
      throw error;
    }

  } catch (error) {
    console.error('Erro ao processar importação:', error);
    res.status(500).json({ 
      error: 'Erro ao processar o arquivo. Por favor, verifique o formato e tente novamente.' 
    });
  }
});

// Função melhorada para validar e converter valores monetários
function validarValorMonetario(valor) {
  // Retorna null para valores nulos/undefined/vazios
  if (valor === null || valor === undefined || valor === '') {
    return null;
  }
  
  try {
    // Se já for um número, retorna ele mesmo (desde que seja válido)
    if (typeof valor === 'number') {
      return isNaN(valor) ? null : valor;
    }
    
    // Se for string, tenta converter
    if (typeof valor === 'string') {
      // Remove R$ e espaços
      let valorLimpo = valor.replace(/R\$\s*/g, '').trim();
      
      // Remove pontos de milhar e trata vírgula decimal
      if (valorLimpo.includes('.') && valorLimpo.includes(',')) {
        // Formato brasileiro (1.234,56)
        valorLimpo = valorLimpo.replace(/\./g, '').replace(',', '.');
      } else if (valorLimpo.includes(',')) {
        // Apenas vírgula decimal
        valorLimpo = valorLimpo.replace(',', '.');
      }
      
      // Remove caracteres inválidos
      valorLimpo = valorLimpo.replace(/[^\d.-]/g, '');
      
      // Converte para número
      const numero = parseFloat(valorLimpo);
      return isNaN(numero) ? null : numero;
    }
    
    return null;
  } catch (error) {
    console.error('Erro ao converter valor monetário:', valor, error);
    return null;
  }
}

// Função auxiliar para formatar moeda
function formatarMoeda(valor) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  }).format(valor);
}

// Alterar a rota catch-all problemática
app.get(
  [
    "/",
    "/index.html",
    "/dashboard",
    "/registros",
    "/usuarios",
    "/novo-registro",
  ],
  (req, res) => {
    res.sendFile(path.join(__dirname, "public", "index.html"));
  }
);

// Iniciar o servidor
app.listen(PORT, () => {
  console.log(`Servidor rodando na porta ${PORT}`);
});

// Criar diretório para uploads se não existir
if (!fs.existsSync("./uploads")) {
  fs.mkdirSync("./uploads");
}

// Função auxiliar para aplicar filtros aos registros
function aplicarFiltros(registros, filtros) {
  // Garantir que registros seja um array
  if (!Array.isArray(registros)) {
    console.error("Registros não é um array:", registros);
    return [];
  }

  return registros.filter((registro) => {
    // Cada registro precisa satisfazer TODOS os filtros
    return filtros.every((filtro) => {
      const valor = registro[filtro.campo];

      // Se o valor não existir no registro
      if (valor === null || valor === undefined || valor === "") {
        return false;
      }

      // Determinar o tipo de filtro
      if (
        filtro.campo === "valor_estimado" ||
        filtro.campo === "valor_homologado" ||
        filtro.campo === "economia"
      ) {
        // Campos numéricos
        const valorNum = parseFloat(valor);
        const filtroValorNum = parseFloat(filtro.valor);

        if (isNaN(valorNum) || isNaN(filtroValorNum)) {
          return false;
        }

        switch (filtro.operador) {
          case "igual":
            return valorNum === filtroValorNum;
          case "maior":
            return valorNum > filtroValorNum;
          case "menor":
            return valorNum < filtroValorNum;
          case "entre":
            const filtroValor2Num = parseFloat(filtro.valor2);
            if (isNaN(filtroValor2Num)) return false;
            return valorNum >= filtroValorNum && valorNum <= filtroValor2Num;
          default:
            return false;
        }
      } else if (
        filtro.campo === "dt_abertura" ||
        filtro.campo === "dt_homologacao" ||
        filtro.campo === "dt_entrada_dipli"
      ) {
        // Campos de data
        const dataRegistro = new Date(valor);
        const dataFiltro = new Date(filtro.valor);

        if (isNaN(dataRegistro.getTime()) || isNaN(dataFiltro.getTime())) {
          return false;
        }

        // Remover componente de hora para comparar apenas datas
        const dataRegistroSemHora = new Date(
          dataRegistro.toISOString().split("T")[0]
        );
        const dataFiltroSemHora = new Date(
          dataFiltro.toISOString().split("T")[0]
        );

        switch (filtro.operador) {
          case "igual":
            return dataRegistroSemHora.getTime() === dataFiltroSemHora.getTime();
          case "antes":
            return dataRegistroSemHora < dataFiltroSemHora;
          case "depois":
            return dataRegistroSemHora > dataFiltroSemHora;
          case "entre":
            const dataFiltro2 = new Date(filtro.valor2);
            if (isNaN(dataFiltro2.getTime())) return false;
            const dataFiltro2SemHora = new Date(
              dataFiltro2.toISOString().split("T")[0]
            );
            return (
              dataRegistroSemHora >= dataFiltroSemHora &&
              dataRegistroSemHora <= dataFiltro2SemHora
            );
          default:
            return false;
        }
      } else {
        // Campos de texto
        const valorStr = String(valor).toLowerCase();
        const filtroValorStr = String(filtro.valor).toLowerCase();

        switch (filtro.operador) {
          case "igual":
            return valorStr === filtroValorStr;
          case "contem":
            return valorStr.includes(filtroValorStr);
          case "comeca":
            return valorStr.startsWith(filtroValorStr);
          case "termina":
            return valorStr.endsWith(filtroValorStr);
          default:
            return false;
        }
      }
    });
  });
}

// Função auxiliar para gerar CSV
function gerarCSV(registros) {
  if (registros.length === 0) {
    return "Nenhum registro encontrado";
  }

  // Obter os cabeçalhos (colunas) a partir do primeiro registro
  const colunas = Object.keys(registros[0]);

  // Criar a linha de cabeçalho
  let csvContent = colunas.join(",") + "\n";

  // Adicionar cada registro como uma linha
  registros.forEach((registro) => {
    const linha = colunas.map((coluna) => {
      // Tratar valores para evitar problemas com CSV
      const valor = registro[coluna];
      if (valor === null || valor === undefined) {
        return "";
      }

      // Escapar vírgulas e aspas
      const valorStr = String(valor).replace(/"/g, '""');
      return `"${valorStr}"`;
    });

    csvContent += linha.join(",") + "\n";
  });

  return csvContent;
}

