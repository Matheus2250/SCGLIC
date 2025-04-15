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
const PORT = process.env.PORT || 3000;
const JWT_SECRET = "sua-chave-secreta-deve-ser-alterada-em-producao";

// Middleware
app.use(cors());
app.use(bodyParser.json()); // Verifique se isto está presente
app.use(bodyParser.urlencoded({ extended: true })); // Adicione isso se não existir
app.use(express.static(path.join(__dirname, "public")));

// Configurar middleware para upload de arquivos
const upload = multer({
  dest: "uploads/",
  limits: { fileSize: 10 * 1024 * 1024 }, // limite de 10MB
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
app.post("/api/export/csv", authenticateToken, async (req, res) => {
  try {
    // Obter o usuário logado para registrar a atividade
    const usuarioId = req.user.id;
    const usuario = await db.get("SELECT nome FROM usuarios WHERE id = ?", [
      usuarioId,
    ]);
    if (!usuario) {
      return res.status(404).json({ mensagem: "Usuário não encontrado" });
    }

    // Obter todos os registros do banco de dados
    const registros = await db.all("SELECT * FROM registros");

    // Aplicar filtros, se fornecidos
    const filtrosReq = req.body.filtros || [];
    const registrosFiltrados = aplicarFiltros(registros, filtrosReq);

    // Gerar conteúdo CSV a partir dos registros filtrados
    const csvContent = gerarCSV(registrosFiltrados);

    // Configurar headers para download do arquivo
    res.setHeader("Content-Type", "text/csv");
    res.setHeader(
      "Content-Disposition",
      `attachment; filename=registros_${
        new Date().toISOString().split("T")[0]
      }.csv`
    );

    // Enviar o conteúdo CSV como resposta
    res.send(csvContent);

    // Registrar atividade de exportação
    const atividade = new Atividade({
      usuario: usuario.nome,
      acao: "Exportação CSV",
      detalhes: `Exportou ${registrosFiltrados.length} registros em formato CSV`,
      data: new Date(),
    });
    await atividade.save();
  } catch (error) {
    console.error("Erro ao exportar registros para CSV:", error);
    res
      .status(500)
      .json({ mensagem: "Erro ao exportar registros", erro: error.message });
  }
});

// Rota para exportar registros no formato Excel
app.post("/api/export/excel", authenticateToken, async (req, res) => {
  try {
    // Obter o usuário logado para registrar a atividade
    const usuarioId = req.user.id;
    const usuario = await db.get("SELECT nome FROM usuarios WHERE id = ?", [
      usuarioId,
    ]);
    if (!usuario) {
      return res.status(404).json({ mensagem: "Usuário não encontrado" });
    }

    // Obter todos os registros do banco de dados
    const registros = await db.all("SELECT * FROM registros");

    // Aplicar filtros, se fornecidos
    const filtrosReq = req.body.filtros || [];
    const registrosFiltrados = aplicarFiltros(registros, filtrosReq);

    // Preparar dados para Excel (converter ObjectId para string, etc.)
    const dadosParaExcel = registrosFiltrados.map((reg) => {
      // Converter registro para objeto plano
      const registro = reg.toObject();

      // Converter ObjectId para string
      registro._id = registro._id.toString();

      // Formatar datas para string legível
      if (registro.dataCriacao)
        registro.dataCriacao = new Date(
          registro.dataCriacao
        ).toLocaleDateString("pt-BR");
      if (registro.dataAtualizacao)
        registro.dataAtualizacao = new Date(
          registro.dataAtualizacao
        ).toLocaleDateString("pt-BR");

      return registro;
    });

    // Criar workbook e worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(dadosParaExcel);

    // Adicionar worksheet ao workbook
    XLSX.utils.book_append_sheet(wb, ws, "Registros");

    // Gerar arquivo Excel
    const excelBuffer = XLSX.write(wb, { type: "buffer", bookType: "xlsx" });

    // Configurar headers para download do arquivo
    res.setHeader(
      "Content-Type",
      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    );
    res.setHeader(
      "Content-Disposition",
      `attachment; filename=registros_${
        new Date().toISOString().split("T")[0]
      }.xlsx`
    );

    // Enviar o arquivo Excel como resposta
    res.send(excelBuffer);

    // Registrar atividade de exportação
    const atividade = new Atividade({
      usuario: usuario.nome,
      acao: "Exportação Excel",
      detalhes: `Exportou ${registrosFiltrados.length} registros em formato Excel`,
      data: new Date(),
    });
    await atividade.save();
  } catch (error) {
    console.error("Erro ao exportar registros para Excel:", error);
    res
      .status(500)
      .json({ mensagem: "Erro ao exportar registros", erro: error.message });
  }
});

// Rota para importar registros de um arquivo Excel
app.post(
  "/api/importar-excel",
  authenticateToken,
  upload.single("excel"),
  async (req, res) => {
    try {
      // Verificar se o arquivo foi enviado
      if (!req.file) {
        return res.status(400).json({ message: "Nenhum arquivo enviado" });
      }

      // Verificar se é um arquivo Excel
      if (!req.file.originalname.match(/\.(xlsx|xls)$/)) {
        // Remover o arquivo enviado
        fs.unlinkSync(req.file.path);
        return res.status(400).json({
          message: "Apenas arquivos Excel (.xlsx ou .xls) são aceitos",
        });
      }

      const limparDadosExistentes = req.body.limparDados === "true";

      // Se solicitado, limpar os dados existentes
      if (limparDadosExistentes) {
        await new Promise((resolve, reject) => {
          db.run("DELETE FROM registros", (err) => {
            if (err) reject(err);
            else resolve();
          });
        });

        // Registrar atividade de limpeza
        registrarAtividade(
          req.user.id,
          req.user.nome || req.user.email,
          "Exclusão",
          null,
          "Todos os registros",
          "Limpeza de registros antes da importação"
        );
      }

      // Ler o arquivo Excel
      const workbook = XLSX.readFile(req.file.path);
      const sheetNames = workbook.SheetNames;
      const firstSheet = sheetNames[0];

      // Converter para JSON
      const worksheet = workbook.Sheets[firstSheet];
      const data = XLSX.utils.sheet_to_json(worksheet);

      // Mapeamento entre os cabeçalhos da planilha e os campos do banco de dados
      const mapeamento = {
        NUP: "nup",
        "DT ENTRADA DIPLI": "dt_entrada_dipli",
        "RESP. INSTRUÇÃO": "resp_instrucao",
        "ÁREA DEMANDANTE": "area_demandante",
        PREGOEIRO: "pregoeiro",
        MODALIDADE: "modalidade",
        TIPO: "tipo",
        NÚMERO: "numero",
        Nº: "numero", // Alternativa comum
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

      // Resultados e progresso
      const result = {
        totalProcessados: data.length,
        sucessos: 0,
        erros: 0,
        detalhesErros: [],
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

      if (validRecords.length === 0) {
        // Remover o arquivo enviado
        fs.unlinkSync(req.file.path);
        return res.status(400).json({
          message:
            "Nenhum registro válido encontrado na planilha. Verifique os nomes das colunas.",
        });
      }

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
      const insertRecord = (record) => {
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
            if (err) {
              reject({ error: err, record });
            } else {
              resolve(this.lastID);
            }
          });
        });
      };

      // Inserir registros em lotes para não sobrecarregar o banco de dados
      const BATCH_SIZE = 50;
      for (let i = 0; i < validRecords.length; i += BATCH_SIZE) {
        const batch = validRecords.slice(i, i + BATCH_SIZE);

        await Promise.allSettled(
          batch.map((record) =>
            insertRecord(record)
              .then(() => {
                result.sucessos++;
              })
              .catch((err) => {
                result.erros++;
                result.detalhesErros.push({
                  message: err.error.message,
                  record: JSON.stringify(err.record),
                });
              })
          )
        );
      }

      // Registrar atividade de importação
      registrarAtividade(
        req.user.id,
        req.user.nome || req.user.email,
        "Importação",
        null,
        `${result.sucessos} registros`,
        `Importação de Excel: ${result.sucessos} sucessos, ${result.erros} falhas`
      );

      // Remover o arquivo enviado
      fs.unlinkSync(req.file.path);

      // Returnar resultado
      return res.status(200).json(result);
    } catch (error) {
      console.error("Erro na importação de Excel:", error);

      // Tentar remover o arquivo em caso de erro
      if (req.file && req.file.path) {
        try {
          fs.unlinkSync(req.file.path);
        } catch (e) {
          console.error("Erro ao remover arquivo temporário:", e);
        }
      }

      return res.status(500).json({
        message: "Erro ao processar a importação",
        error: error.message,
      });
    }
  }
);

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
            return (
              dataRegistroSemHora.getTime() === dataFiltroSemHora.getTime()
            );
          case "antes":
          case "menor":
            return dataRegistroSemHora.getTime() < dataFiltroSemHora.getTime();
          case "depois":
          case "maior":
            return dataRegistroSemHora.getTime() > dataFiltroSemHora.getTime();
          case "entre":
            const dataFiltro2 = new Date(filtro.valor2);
            if (isNaN(dataFiltro2.getTime())) return false;
            const dataFiltro2SemHora = new Date(
              dataFiltro2.toISOString().split("T")[0]
            );
            return (
              dataRegistroSemHora.getTime() >= dataFiltroSemHora.getTime() &&
              dataRegistroSemHora.getTime() <= dataFiltro2SemHora.getTime()
            );
          default:
            return false;
        }
      } else {
        // Campos de texto
        const valorTexto = String(valor).toLowerCase();
        const filtroTexto = String(filtro.valor).toLowerCase();

        // Tratamento especial para o campo situação
        if (filtro.campo === "situacao") {
          switch (filtro.operador) {
            case "igual":
              // Se o filtro for "Homologado"
              if (filtroTexto.includes("homologado")) {
                return valorTexto.includes("homologado");
              }
              // Se o filtro for "Em Andamento"
              else if (filtroTexto.includes("em andamento")) {
                return valorTexto.includes("em andamento");
              }
              // Para outras situações, comportamento normal
              else {
                return valorTexto === filtroTexto;
              }
            case "contem":
              return valorTexto.includes(filtroTexto);
            case "comeca":
              return valorTexto.startsWith(filtroTexto);
            case "termina":
              return valorTexto.endsWith(filtroTexto);
            default:
              return false;
          }
        } else {
          // Para outros campos de texto, comportamento normal
          switch (filtro.operador) {
            case "igual":
              return valorTexto === filtroTexto;
            case "contem":
              return valorTexto.includes(filtroTexto);
            case "comeca":
              return valorTexto.startsWith(filtroTexto);
            case "termina":
              return valorTexto.endsWith(filtroTexto);
            default:
              return false;
          }
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
