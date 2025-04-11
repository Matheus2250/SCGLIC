// init-db.js
const sqlite3 = require("sqlite3").verbose();
const bcrypt = require("bcrypt");
const path = require("path");

// Conectar ao banco de dados
const db = new sqlite3.Database(path.join(__dirname, "database.db"), (err) => {
  if (err) {
    console.error("Erro ao conectar ao banco de dados:", err.message);
    process.exit(1);
  } else {
    console.log("Conectado ao banco de dados SQLite");
    initializeDatabase();
  }
});

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
      } else {
        console.log("Atividade registrada com sucesso:", acao);
      }
    }
  );
}

// Inicializar o banco de dados
function initializeDatabase() {
  console.log("Inicializando banco de dados...");

  // Tabela de usuários
  db.run(
    `CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    senha TEXT NOT NULL,
    nivel_acesso TEXT NOT NULL
  )`,
    (err) => {
      if (err) {
        console.error("Erro ao criar tabela de usuários:", err.message);
        return;
      }
      console.log("Tabela de usuários verificada/criada com sucesso");
    }
  );

  // Tabela de registros
  db.run(
    `CREATE TABLE IF NOT EXISTS registros (
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
  )`,
    (err) => {
      if (err) {
        console.error("Erro ao criar tabela de registros:", err.message);
        return;
      }
      console.log("Tabela de registros verificada/criada com sucesso");
    }
  );

  // Tabela de atividades
  db.run(
    `CREATE TABLE IF NOT EXISTS atividades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    usuario_nome TEXT NOT NULL,
    acao TEXT NOT NULL,
    registro_id INTEGER,
    registro_descricao TEXT,
    detalhes TEXT,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
  )`,
    (err) => {
      if (err) {
        console.error("Erro ao criar tabela de atividades:", err.message);
        return;
      }
      console.log("Tabela de atividades verificada/criada com sucesso");

      // Criar usuários padrão após verificar as tabelas
      createDefaultUsers();
    }
  );
}

// Criar usuários padrão
function createDefaultUsers() {
  // Verificar se já existe um administrador
  db.get(
    "SELECT COUNT(*) as count FROM usuarios WHERE nivel_acesso = 'admin'",
    (err, row) => {
      if (err) {
        console.error("Erro ao verificar usuário admin:", err.message);
        return;
      }

      if (row.count === 0) {
        // Criar usuário admin
        bcrypt.hash("admin123", 10, (err, hash) => {
          if (err) {
            console.error("Erro ao gerar hash da senha admin:", err.message);
            return;
          }

          db.run(
            "INSERT INTO usuarios (nome, email, senha, nivel_acesso) VALUES (?, ?, ?, ?)",
            ["Administrador", "admin@saude.gov.br", hash, "admin"],
            function (err) {
              if (err) {
                console.error("Erro ao criar usuário admin:", err.message);
                return;
              }

              console.log("Usuário admin criado com sucesso! ID:", this.lastID);

              // Registrar atividade de criação do administrador
              registrarAtividade(
                this.lastID,
                "Administrador",
                "Criação de Sistema",
                null,
                null,
                "Sistema CGLIC inicializado com usuário administrador"
              );
            }
          );
        });
      } else {
        console.log("Usuário admin já existe, pulando criação");
      }
    }
  );

  // Verificar se já existe um usuário comum
  db.get(
    "SELECT COUNT(*) as count FROM usuarios WHERE nivel_acesso = 'usuario'",
    (err, row) => {
      if (err) {
        console.error("Erro ao verificar usuário comum:", err.message);
        return;
      }

      if (row.count === 0) {
        // Criar usuário comum
        bcrypt.hash("usuario123", 10, (err, hash) => {
          if (err) {
            console.error(
              "Erro ao gerar hash da senha de usuário:",
              err.message
            );
            return;
          }

          db.run(
            "INSERT INTO usuarios (nome, email, senha, nivel_acesso) VALUES (?, ?, ?, ?)",
            ["Usuário Teste", "usuario@saude.gov.br", hash, "usuario"],
            function (err) {
              if (err) {
                console.error("Erro ao criar usuário comum:", err.message);
                return;
              }

              console.log("Usuário comum criado com sucesso! ID:", this.lastID);
            }
          );
        });
      } else {
        console.log("Usuário comum já existe, pulando criação");
      }
    }
  );

  // Criar dados de teste após um delay para garantir que os usuários foram criados
  setTimeout(() => {
    createSampleData();
  }, 1000);
}

// Criar alguns registros de exemplo
function createSampleData() {
  // Verificar se já existem registros
  db.get("SELECT COUNT(*) as count FROM registros", (err, row) => {
    if (err) {
      console.error("Erro ao verificar registros:", err.message);
      return;
    }

    if (row.count === 0) {
      console.log("Criando registros de exemplo...");

      // Array com registros de exemplo
      const registrosExemplo = [
        {
          nup: "25000.123456/2023-01",
          dt_entrada_dipli: "2023-03-15",
          resp_instrucao: "João Silva",
          area_demandante: "Departamento de Logística",
          pregoeiro: "Maria Oliveira",
          modalidade: "Pregão",
          tipo: "Eletrônico",
          numero: "42",
          ano: "2023",
          prioridade: "Alta",
          item_pgc: "PGC2023-156",
          estimado_pgc: "150000",
          ano_pgc: "2023",
          objeto: "Aquisição de equipamentos de informática",
          qtd_itens: 15,
          valor_estimado: 180000.0,
          dt_abertura: "2023-05-20",
          situacao: "Homologado",
          andamentos: "Edital publicado. Sessão realizada. Homologado.",
          valor_homologado: 165000.0,
          economia: 15000.0,
          dt_homologacao: "2023-06-15",
        },
        {
          nup: "25000.789012/2023-02",
          dt_entrada_dipli: "2023-04-10",
          resp_instrucao: "Pedro Santos",
          area_demandante: "Departamento de Atenção Básica",
          pregoeiro: "Carla Mendes",
          modalidade: "Pregão",
          tipo: "Eletrônico",
          numero: "57",
          ano: "2023",
          prioridade: "Média",
          item_pgc: "PGC2023-212",
          estimado_pgc: "230000",
          ano_pgc: "2023",
          objeto: "Contratação de serviços de limpeza",
          qtd_itens: 3,
          valor_estimado: 240000.0,
          dt_abertura: "2023-06-05",
          situacao: "Em Andamento",
          andamentos: "Edital publicado. Sessão agendada.",
          valor_homologado: null,
          economia: null,
          dt_homologacao: null,
        },
        {
          nup: "25000.456789/2023-03",
          dt_entrada_dipli: "2023-02-20",
          resp_instrucao: "Ana Rodrigues",
          area_demandante: "Secretaria de Vigilância em Saúde",
          pregoeiro: "Roberto Alves",
          modalidade: "Dispensa",
          tipo: null,
          numero: "18",
          ano: "2023",
          prioridade: "Baixa",
          item_pgc: "PGC2023-089",
          estimado_pgc: "45000",
          ano_pgc: "2023",
          objeto: "Aquisição de material de escritório",
          qtd_itens: 25,
          valor_estimado: 48000.0,
          dt_abertura: "2023-03-10",
          situacao: "Homologado",
          andamentos: "Finalizado",
          valor_homologado: 42500.0,
          economia: 5500.0,
          dt_homologacao: "2023-03-25",
        },
      ];

      // Inserir os registros de exemplo
      const insertSql = `
        INSERT INTO registros (
          nup, dt_entrada_dipli, resp_instrucao, area_demandante, pregoeiro,
          modalidade, tipo, numero, ano, prioridade, item_pgc, estimado_pgc,
          ano_pgc, objeto, qtd_itens, valor_estimado, dt_abertura, situacao,
          andamentos, valor_homologado, economia, dt_homologacao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `;

      registrosExemplo.forEach((registro) => {
        db.run(
          insertSql,
          [
            registro.nup,
            registro.dt_entrada_dipli,
            registro.resp_instrucao,
            registro.area_demandante,
            registro.pregoeiro,
            registro.modalidade,
            registro.tipo,
            registro.numero,
            registro.ano,
            registro.prioridade,
            registro.item_pgc,
            registro.estimado_pgc,
            registro.ano_pgc,
            registro.objeto,
            registro.qtd_itens,
            registro.valor_estimado,
            registro.dt_abertura,
            registro.situacao,
            registro.andamentos,
            registro.valor_homologado,
            registro.economia,
            registro.dt_homologacao,
          ],
          function (err) {
            if (err) {
              console.error(
                "Erro ao inserir registro de exemplo:",
                err.message
              );
              return;
            }

            console.log(
              `Registro de exemplo criado com sucesso! ID: ${this.lastID}`
            );

            // Registrar atividade para este registro
            db.get(
              "SELECT id FROM usuarios WHERE nivel_acesso = 'admin' LIMIT 1",
              (err, user) => {
                if (err || !user) {
                  console.error(
                    "Erro ao obter usuário para registrar atividade:",
                    err?.message
                  );
                  return;
                }

                registrarAtividade(
                  user.id,
                  "Administrador",
                  "Criação",
                  this.lastID,
                  `NUP: ${registro.nup}`,
                  `Registro de exemplo criado: ${registro.objeto}`
                );
              }
            );
          }
        );
      });
    } else {
      console.log("Já existem registros, pulando criação de exemplos");
    }
  });
}

// Fechar a conexão após o término
process.on("exit", () => {
  db.close();
  console.log("Conexão com o banco de dados fechada");
});
