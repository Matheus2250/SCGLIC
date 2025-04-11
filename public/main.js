// main.js - Script principal do sistema
document.addEventListener("DOMContentLoaded", function () {
  // Configurações da API
  const API_URL = "http://localhost:3000/api";
  let token = localStorage.getItem("token");
  let currentUser = JSON.parse(localStorage.getItem("currentUser"));
  let currentRegistroId = null;

  // Elementos do DOM
  const loginPage = document.getElementById("login-page");
  const registerPage = document.getElementById("register-page");
  const mainApp = document.getElementById("main-app");
  const sidebar = document.getElementById("sidebar");
  const content = document.getElementById("content");
  const pageContainer = document.getElementById("page-container");
  const toggleSidebarBtn = document.getElementById("toggle-sidebar");
  const navLinks = document.querySelectorAll(".nav-link[data-page]");
  const adminMenu = document.getElementById("admin-menu");

  // Modais do Bootstrap
  const detalhesModal = new bootstrap.Modal(
    document.getElementById("detalhes-modal")
  );
  const confirmacaoModal = new bootstrap.Modal(
    document.getElementById("confirmacao-modal")
  );
  let usuarioModal;

  // Funções para navegação entre login e registro
  function showLoginPage() {
    if (loginPage) loginPage.classList.remove("d-none");
    if (registerPage) registerPage.classList.add("d-none");
    if (mainApp) mainApp.classList.add("d-none");
  }

  function showRegisterPage() {
    if (loginPage) loginPage.classList.add("d-none");
    if (registerPage) registerPage.classList.remove("d-none");
    if (mainApp) mainApp.classList.add("d-none");
  }

  function showMainApp() {
    if (loginPage) loginPage.classList.add("d-none");
    if (registerPage) registerPage.classList.add("d-none");
    if (mainApp) mainApp.classList.remove("d-none");
  }

  // Validação de email (apenas domínio @saude.gov.br)
  function validateEmail(email) {
    const regex = /@saude\.gov\.br$/i;
    return regex.test(email);
  }

  // Mensagens de erro
  function showError(message) {
    const errorElement = document.getElementById("login-error");
    if (errorElement) {
      errorElement.textContent = message;
      errorElement.style.display = "block";
    }
  }

  function showRegisterError(message) {
    const errorElement = document.getElementById("register-error");
    if (errorElement) {
      errorElement.textContent = message;
      errorElement.style.display = "block";
    }
  }

  // Adicionar toggle de visibilidade de senha
  const togglePassword = document.getElementById("toggle-password");
  const passwordInput = document.getElementById("senha");

  if (togglePassword && passwordInput) {
    togglePassword.addEventListener("click", function () {
      const type =
        passwordInput.getAttribute("type") === "password" ? "text" : "password";
      passwordInput.setAttribute("type", type);

      const eyeIcon = togglePassword.querySelector("i");
      eyeIcon.classList.toggle("bi-eye");
      eyeIcon.classList.toggle("bi-eye-slash");
    });
  }

  // Toggle para visualizar/ocultar senha na página de registro
  const toggleRegPassword = document.getElementById("toggle-reg-password");
  const regPasswordInput = document.getElementById("reg-senha");

  if (toggleRegPassword && regPasswordInput) {
    toggleRegPassword.addEventListener("click", function () {
      const type =
        regPasswordInput.getAttribute("type") === "password"
          ? "text"
          : "password";
      regPasswordInput.setAttribute("type", type);

      const eyeIcon = toggleRegPassword.querySelector("i");
      eyeIcon.classList.toggle("bi-eye");
      eyeIcon.classList.toggle("bi-eye-slash");
    });
  }

  // Link para alternar para a página de registro
  const signupLink = document.getElementById("signup-link");
  if (signupLink) {
    signupLink.addEventListener("click", function (e) {
      e.preventDefault();
      showRegisterError("");
      showRegisterPage();
    });
  }

  // Link para voltar à página de login
  const loginLink = document.getElementById("login-link");
  if (loginLink) {
    loginLink.addEventListener("click", function (e) {
      e.preventDefault();
      showError("");
      showLoginPage();
    });
  }

  // Verificar autenticação
  function checkAuth() {
    if (token && currentUser) {
      // Verificar se o token é válido fazendo uma requisição simples
      fetch(`${API_URL}/registros`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      })
        .then((response) => {
          if (!response.ok) {
            // Se o token expirou ou é inválido, fazer logout
            if (response.status === 401 || response.status === 403) {
              logout();
              alert("Sua sessão expirou. Por favor, faça login novamente.");
              return;
            }
          }

          showMainApp();

          // Verificar se é admin para exibir menu de usuários
          if (currentUser.nivel_acesso === "admin") {
            adminMenu.classList.remove("d-none");
          } else {
            adminMenu.classList.add("d-none"); // Certifica-se de esconder para não-admins
          }

          // Carregar a página inicial (dashboard)
          loadPage("dashboard");
        })
        .catch((error) => {
          console.error("Erro ao verificar autenticação:", error);
          showLoginPage();
        });
    } else {
      showLoginPage();
    }
  }

  // Função para logout
  function logout() {
    token = null;
    currentUser = null;
    localStorage.removeItem("token");
    localStorage.removeItem("currentUser");
    showLoginPage();
  }

  // Carregador de páginas
  async function loadPage(pageName) {
    try {
      // Impedir acesso a páginas restritas
      if (
        pageName === "usuarios" &&
        (!currentUser || currentUser.nivel_acesso !== "admin")
      ) {
        alert(
          "Acesso negado. Apenas administradores podem acessar esta página."
        );
        pageName = "dashboard";
      }

      // Ativar item da navegação
      navLinks.forEach((link) => {
        if (link.dataset.page === pageName) {
          link.classList.add("active");
        } else {
          link.classList.remove("active");
        }
      });

      // Carregar conteúdo HTML da página
      const response = await fetch(`${pageName}.html`);
      if (!response.ok) {
        throw new Error(`Erro ao carregar a página ${pageName}`);
      }

      const html = await response.text();
      pageContainer.innerHTML = html;

      // Inicializar funcionalidades específicas da página
      switch (pageName) {
        case "dashboard":
          initDashboard();
          break;
        case "registros":
          initRegistros();
          break;
        case "novo-registro":
          initNovoRegistro();
          break;
        case "usuarios":
          initUsuarios();
          break;
      }

      // Adicionar event listeners para botões de navegação dentro das páginas
      document.querySelectorAll("[data-page]").forEach((button) => {
        if (button.tagName === "A" || button.tagName === "BUTTON") {
          button.addEventListener("click", function (e) {
            e.preventDefault();
            loadPage(this.dataset.page);
          });
        }
      });

      return Promise.resolve(); // Retornar uma promise resolvida para permitir encadeamento
    } catch (error) {
      console.error("Erro ao carregar página:", error);
      pageContainer.innerHTML = `
      <div class="alert alert-danger">
        <h4>Erro ao carregar a página</h4>
        <p>${error.message}</p>
      </div>
    `;
      return Promise.reject(error);
    }
  }

  // Toggle sidebar
  toggleSidebarBtn.addEventListener("click", function () {
    if (sidebar.classList.contains("expanded")) {
      sidebar.classList.remove("expanded");
      sidebar.classList.add("collapsed");
      content.classList.remove("expanded");
      content.classList.add("expanded");
      this.innerHTML = '<i class="bi bi-chevron-right"></i>';

      // Esconder textos dos links
      document.querySelectorAll(".nav-text").forEach((el) => {
        el.classList.add("hidden");
      });
    } else {
      sidebar.classList.add("expanded");
      sidebar.classList.remove("collapsed");
      content.classList.remove("expanded");
      this.innerHTML = '<i class="bi bi-chevron-left"></i>';

      // Mostrar textos dos links
      document.querySelectorAll(".nav-text").forEach((el) => {
        el.classList.remove("hidden");
      });
    }
  });

  // Navegação principal
  navLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      const pageId = this.dataset.page;
      loadPage(pageId);
    });
  });

  // Login
  document
    .getElementById("login-form")
    .addEventListener("submit", function (e) {
      e.preventDefault();
      const email = document.getElementById("email").value;
      const senha = document.getElementById("senha").value;

      if (!email || !senha) {
        showError("Email e senha são obrigatórios");
        return;
      }

      fetch(`${API_URL}/login`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, senha }),
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error("Credenciais inválidas");
          }
          return response.json();
        })
        .then((data) => {
          token = data.token;
          currentUser = data.user;

          // Salvar no localStorage
          localStorage.setItem("token", token);
          localStorage.setItem("currentUser", JSON.stringify(currentUser));

          showError("");
          document.getElementById("login-form").reset();

          checkAuth();
        })
        .catch((error) => {
          showError(error.message);
        });
    });

  // Registro de novo usuário
  const registerForm = document.getElementById("register-form");
  if (registerForm) {
    registerForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const nome = document.getElementById("reg-nome").value;
      const email = document.getElementById("reg-email").value;
      const senha = document.getElementById("reg-senha").value;
      const confirmaSenha = document.getElementById("reg-confirma-senha").value;

      // Validar nome
      if (!nome || nome.trim().length < 3) {
        showRegisterError("Nome muito curto. Digite seu nome completo.");
        return;
      }

      // Validar email
      if (!validateEmail(email)) {
        showRegisterError(
          "Apenas emails com domínio @saude.gov.br são aceitos."
        );
        return;
      }

      // Validar senha
      if (senha.length < 8) {
        showRegisterError("A senha deve ter pelo menos 8 caracteres.");
        return;
      }

      // Confirmar senha
      if (senha !== confirmaSenha) {
        showRegisterError("As senhas não coincidem.");
        return;
      }

      // Enviar dados para o servidor
      fetch(`${API_URL}/register`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ nome, email, senha }),
      })
        .then(async (response) => {
          // Verificar o tipo de conteúdo antes de tentar fazer o parse
          const contentType = response.headers.get("content-type");

          if (contentType && contentType.includes("application/json")) {
            // Se for JSON, processa normalmente
            const data = await response.json();
            if (!response.ok) {
              throw new Error(data.message || "Erro ao cadastrar usuário");
            }
            return data;
          } else {
            // Se não for JSON, captura o texto e mostra erro
            const text = await response.text();
            console.error(
              "Resposta não-JSON recebida:",
              text.substring(0, 500)
            );
            throw new Error("O servidor retornou uma resposta inesperada");
          }
        })
        .then((data) => {
          alert("Usuário cadastrado com sucesso! Você já pode fazer login.");
          registerForm.reset();
          showLoginPage();
        })
        .catch((error) => {
          showRegisterError(error.message);
        });
    });
  }

  // Links não funcionais
  document
    .querySelector(".forgot-password")
    ?.addEventListener("click", function (e) {
      e.preventDefault();
      showError("Funcionalidade não implementada nesta versão.");
    });

  // Logout
  document.getElementById("logout-btn").addEventListener("click", function (e) {
    e.preventDefault();
    token = null;
    currentUser = null;
    localStorage.removeItem("token");
    localStorage.removeItem("currentUser");
    showLoginPage();
  });

  // Funções auxiliares
  function formatarMoeda(valor) {
    if (valor === null || valor === undefined || isNaN(valor)) {
      return "R$ 0,00";
    }
    return `R$ ${parseFloat(valor).toLocaleString("pt-BR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }

  function formatarData(data) {
    if (!data) return null;
    const partes = data.split("-");
    if (partes.length !== 3) return data;
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
  }

  function getBadgeClass(situacao) {
    if (!situacao) return "bg-secondary";

    switch (situacao) {
      case "Homologado":
        return "bg-success";
      case "Em Andamento":
        return "bg-warning text-dark";
      case "Em Análise":
        return "bg-info text-dark";
      case "Fracassado":
      case "Deserto":
      case "Cancelado":
        return "bg-danger";
      default:
        return "bg-secondary";
    }
  }

  // Função para carregar atividades recentes
  function loadActivities() {
    const activitiesLoading = document.getElementById("activities-loading");
    const activitiesTable = document.getElementById("activities-table");
    const activitiesContainer = document.getElementById(
      "activities-table-container"
    );
    const noActivities = document.getElementById("no-activities");

    if (
      !activitiesLoading ||
      !activitiesTable ||
      !activitiesContainer ||
      !noActivities
    ) {
      return;
    }

    activitiesLoading.classList.remove("d-none");
    activitiesContainer.classList.add("d-none");
    noActivities.classList.add("d-none");

    fetch(`${API_URL}/atividades`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Falha ao carregar atividades");
        }
        return response.json();
      })
      .then((data) => {
        activitiesLoading.classList.add("d-none");

        // Filtrar atividades com base no tipo de usuário
        let filteredActivities = data;

        // Se não for administrador, filtrar para mostrar apenas criação, edição e exclusão de registros
        if (currentUser && currentUser.nivel_acesso !== "admin") {
          filteredActivities = data.filter(
            (atividade) =>
              (atividade.acao === "Criação" ||
                atividade.acao === "Atualização" ||
                atividade.acao === "Exclusão") &&
              atividade.registro_id !== null
          );
        }

        if (filteredActivities.length === 0) {
          noActivities.classList.remove("d-none");
          return;
        }

        activitiesContainer.classList.remove("d-none");
        activitiesTable.innerHTML = "";

        filteredActivities.forEach((atividade) => {
          const row = document.createElement("tr");

          // Formatar data/hora
          const dataHora = new Date(atividade.data_hora);
          const dataFormatada = `${dataHora.toLocaleDateString()} ${dataHora.toLocaleTimeString()}`;

          // Definir ícone da ação
          let iconeAcao = "";
          let classeBadge = "";

          switch (atividade.acao) {
            case "Login":
              iconeAcao = "bi-box-arrow-in-right";
              classeBadge = "bg-info";
              break;
            case "Criação":
              iconeAcao = "bi-plus-circle";
              classeBadge = "bg-success";
              break;
            case "Atualização":
              iconeAcao = "bi-pencil";
              classeBadge = "bg-warning";
              break;
            case "Exclusão":
              iconeAcao = "bi-trash";
              classeBadge = "bg-danger";
              break;
            case "Cadastro":
              iconeAcao = "bi-person-plus";
              classeBadge = "bg-primary";
              break;
            default:
              iconeAcao = "bi-gear";
              classeBadge = "bg-secondary";
          }

          row.innerHTML = `
          <td>${dataFormatada}</td>
          <td>${atividade.usuario_nome}</td>
          <td><span class="badge ${classeBadge}"><i class="bi ${iconeAcao} me-1"></i> ${
            atividade.acao
          }</span></td>
          <td>${atividade.registro_descricao || "-"}</td>
          <td>${atividade.detalhes || "-"}</td>
        `;

          activitiesTable.appendChild(row);
        });
      })
      .catch((error) => {
        activitiesLoading.classList.add("d-none");
        console.error("Erro ao carregar atividades:", error);
      });
  }

  // Inicializar Dashboard
  function initDashboard() {
    const totalRegistros = document.getElementById("total-registros");
    const totalHomologados = document.getElementById("total-homologados");
    const totalAndamento = document.getElementById("total-andamento");
    const totalEconomia = document.getElementById("total-economia");
    const recentRegistros = document.getElementById("recent-registros");
    const dashboardLoading = document.getElementById("dashboard-loading");
    const dashboardTable = document.getElementById("dashboard-table");

    if (dashboardLoading) dashboardLoading.classList.remove("d-none");
    if (dashboardTable) dashboardTable.classList.add("d-none");

    fetch(`${API_URL}/registros`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Falha ao carregar registros");
        }
        return response.json();
      })
      .then((data) => {
        if (dashboardLoading) dashboardLoading.classList.add("d-none");
        if (dashboardTable) dashboardTable.classList.remove("d-none");

        // Estatísticas
        if (totalRegistros) totalRegistros.textContent = data.length;

        const homologados = data.filter((r) => r.situacao === "Homologado");
        if (totalHomologados) totalHomologados.textContent = homologados.length;

        const andamento = data.filter((r) => r.situacao === "Em Andamento");
        if (totalAndamento) totalAndamento.textContent = andamento.length;

        let economia = 0;
        homologados.forEach((r) => {
          if (r.economia) {
            economia += parseFloat(r.economia);
          }
        });
        if (totalEconomia) totalEconomia.textContent = formatarMoeda(economia);

        // Registros recentes
        if (recentRegistros) {
          recentRegistros.innerHTML = "";
          const recentes = data.slice(0, 5);

          recentes.forEach((registro) => {
            const row = document.createElement("tr");
            row.innerHTML = `
            <td>${registro.nup || "-"}</td>
            <td>${registro.objeto || "-"}</td>
            <td>${registro.modalidade || "-"}</td>
            <td><span class="badge ${getBadgeClass(registro.situacao)}">${
              registro.situacao || "-"
            }</span></td>
            <td>${formatarMoeda(registro.valor_estimado)}</td>
            <td>
              <button class="btn btn-sm btn-info visualizar-btn" data-id="${
                registro.id
              }">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          `;
            recentRegistros.appendChild(row);
          });

          // Event listeners para botões de visualização
          document.querySelectorAll(".visualizar-btn").forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              carregarDetalhesRegistro(id);
            });
          });
        }

        // Carregar atividades recentes
        loadActivities();
      })
      .catch((error) => {
        if (dashboardLoading) dashboardLoading.classList.add("d-none");
        console.error("Erro:", error);
      });
  }

  // Sistema de filtros - Nova implementação
  function initRegistros() {
    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");
    const registrosTable = document.getElementById("registros-table");

    // Inicialmente ocultar conteúdo e mostrar loading
    if (registrosLoading) registrosLoading.classList.remove("d-none");
    if (registrosContainer) registrosContainer.classList.add("d-none");
    if (nenhumRegistro) nenhumRegistro.classList.add("d-none");

    // Inicializar o sistema de filtros
    inicializarSistemaFiltros();

    // Carregar registros sem filtros
    carregarRegistros();
  }

  // Sistema de filtros encapsulado
  const SistemaFiltros = {
    filtrosAtivos: [],

    // Adicionar um novo filtro
    adicionar: function (campo, operador, valor, valor2) {
      // Validar entradas
      if (
        !campo ||
        !operador ||
        valor === undefined ||
        valor === null ||
        valor === ""
      ) {
        alert("Por favor, preencha todos os campos do filtro");
        return false;
      }

      // Validar segundo valor para operador "entre"
      if (operador === "entre" && (!valor2 || valor2 === "")) {
        alert(
          "Para filtros do tipo 'Entre', é necessário fornecer o segundo valor"
        );
        return false;
      }

      // Obter textos para display
      const campoSelect = document.getElementById("filtro-campo");
      const operadorSelect = document.getElementById("filtro-operador");
      const campoTexto = campoSelect.options[campoSelect.selectedIndex].text;
      const operadorTexto =
        operadorSelect.options[operadorSelect.selectedIndex].text;

      // Criar objeto de filtro
      const filtro = {
        id: Date.now(), // ID único baseado em timestamp
        campo: campo,
        operador: operador,
        valor: valor,
        valor2: valor2,
        textoExibicao: {
          campo: campoTexto,
          operador: operadorTexto,
        },
      };

      // Adicionar à lista de filtros
      this.filtrosAtivos.push(filtro);
      this.atualizarUI();

      // Limpar campos do formulário
      document.getElementById("filtro-campo").selectedIndex = 0;
      document.getElementById("filtro-operador").innerHTML = "";
      document.getElementById("filtro-valor").value = "";
      document.getElementById("filtro-valor2").value = "";
      document
        .getElementById("filtro-valor2-container")
        .classList.add("d-none");

      return true;
    },

    // Remover um filtro específico
    remover: function (id) {
      this.filtrosAtivos = this.filtrosAtivos.filter((f) => f.id !== id);
      this.atualizarUI();
    },

    /// Botão para limpar todos os filtros
    limparTodos: function () {
      this.filtrosAtivos = [];
      this.atualizarUI();
    },

    /// Aplica os filtros
    aplicar: function (dados) {
      if (!this.filtrosAtivos.length) {
        return dados;
      }

      return dados.filter((registro) => {
        // O registro precisa satisfazer TODOS os filtros
        return this.filtrosAtivos.every((filtro) => {
          return this.avaliarRegistro(registro, filtro);
        });
      });
    },

    // Avaliar se um registro satisfaz um filtro
    avaliarRegistro: function (registro, filtro) {
      const valor = registro[filtro.campo];

      // Se o valor não existir no registro
      if (valor === null || valor === undefined || valor === "") {
        return false;
      }

      // Determinar o tipo de campo
      if (this.isCampoNumerico(filtro.campo)) {
        return this.avaliarNumerico(valor, filtro);
      } else if (this.isCampoData(filtro.campo)) {
        return this.avaliarData(valor, filtro);
      } else {
        return this.avaliarTexto(valor, filtro);
      }
    },

    // Verificar se o campo é numérico
    isCampoNumerico: function (campo) {
      return [
        "valor_estimado",
        "valor_homologado",
        "economia",
        "qtd_itens",
      ].includes(campo);
    },

    // Verificar se o campo é de data
    isCampoData: function (campo) {
      return ["dt_abertura", "dt_homologacao", "dt_entrada_dipli"].includes(
        campo
      );
    },

    // Avaliar campos numéricos
    avaliarNumerico: function (valor, filtro) {
      try {
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
      } catch (e) {
        console.error("Erro ao avaliar filtro numérico:", e);
        return false;
      }
    },

    // Avaliar campos de data
    avaliarData: function (valor, filtro) {
      try {
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
      } catch (e) {
        console.error("Erro ao avaliar filtro de data:", e);
        return false;
      }
    },

    // Avaliar campos de texto
    avaliarTexto: function (valor, filtro) {
      try {
        const valorTexto = String(valor).toLowerCase();
        const filtroTexto = String(filtro.valor).toLowerCase();

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
      } catch (e) {
        console.error("Erro ao avaliar filtro de texto:", e);
        return false;
      }
    },

    // Atualizar a interface de usuário com os filtros ativos
    atualizarUI: function () {
      const container = document.getElementById("filtros-ativos");
      if (!container) return;

      // Limpar o container
      container.innerHTML = "";

      // Se não há filtros ativos
      if (this.filtrosAtivos.length === 0) {
        container.innerHTML = `
          <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> Nenhum filtro aplicado. Adicione filtros acima.
          </div>
        `;
        return;
      }

      // Criar wrapper para os badges de filtros
      const filtrosElement = document.createElement("div");
      filtrosElement.className = "d-flex flex-wrap gap-2 mb-3";

      // Adicionar cada filtro como badge
      this.filtrosAtivos.forEach((filtro) => {
        const badge = document.createElement("div");
        badge.className =
          "badge bg-light text-dark p-2 d-flex align-items-center";
        badge.style.fontSize = "0.9rem";

        // Criar texto descritivo
        let descricao = `${filtro.textoExibicao.campo} ${filtro.textoExibicao.operador} ${filtro.valor}`;
        if (filtro.operador === "entre" && filtro.valor2) {
          descricao = `${filtro.textoExibicao.campo} ${filtro.textoExibicao.operador} ${filtro.valor} e ${filtro.valor2}`;
        }

        badge.innerHTML = `
          <span>${descricao}</span>
          <button class="btn btn-<span>${descricao}</span>
          <button class="btn btn-sm btn-link text-danger p-0 ms-2 remover-filtro" data-id="${filtro.id}">
            <i class="bi bi-x-circle"></i>
          </button>
        `;

        filtrosElement.appendChild(badge);
      });

      container.appendChild(filtrosElement);

      // Adicionar listeners para os botões de remoção
      document.querySelectorAll(".remover-filtro").forEach((btn) => {
        btn.addEventListener("click", () => {
          const id = parseInt(btn.dataset.id);
          this.remover(id);
        });
      });
    },

    // Configurar os operadores com base no campo selecionado
    configurarOperadores: function (campo) {
      const operadorSelect = document.getElementById("filtro-operador");
      const valorInput = document.getElementById("filtro-valor");
      const valor2Input = document.getElementById("filtro-valor2");

      if (!operadorSelect || !valorInput || !valor2Input) return;

      // Limpar operadores atuais
      operadorSelect.innerHTML = "";
      valorInput.value = "";
      valor2Input.value = "";
      document
        .getElementById("filtro-valor2-container")
        .classList.add("d-none");

      if (!campo) return;

      // Ajustar tipo de input e operadores disponíveis com base no tipo de campo
      if (this.isCampoNumerico(campo)) {
        valorInput.type = "number";
        valorInput.step = "0.01";
        valor2Input.type = "number";
        valor2Input.step = "0.01";

        this.adicionarOperador(operadorSelect, "igual", "Igual a");
        this.adicionarOperador(operadorSelect, "maior", "Maior que");
        this.adicionarOperador(operadorSelect, "menor", "Menor que");
        this.adicionarOperador(operadorSelect, "entre", "Entre");
      } else if (this.isCampoData(campo)) {
        valorInput.type = "date";
        valor2Input.type = "date";

        this.adicionarOperador(operadorSelect, "igual", "Igual a");
        this.adicionarOperador(operadorSelect, "antes", "Antes de");
        this.adicionarOperador(operadorSelect, "depois", "Depois de");
        this.adicionarOperador(operadorSelect, "entre", "Entre");
      } else {
        valorInput.type = "text";
        valor2Input.type = "text";

        this.adicionarOperador(operadorSelect, "igual", "Igual a");
        this.adicionarOperador(operadorSelect, "contem", "Contém");
        this.adicionarOperador(operadorSelect, "comeca", "Começa com");
        this.adicionarOperador(operadorSelect, "termina", "Termina com");
      }
    },

    // Atualizar a visibilidade do segundo campo de valor
    atualizarCampoValor2: function (operador) {
      const valor2Container = document.getElementById(
        "filtro-valor2-container"
      );
      if (!valor2Container) return;

      if (operador === "entre") {
        valor2Container.classList.remove("d-none");
      } else {
        valor2Container.classList.add("d-none");
      }
    },

    // Adicionar uma opção ao select de operadores
    adicionarOperador: function (select, valor, texto) {
      const option = document.createElement("option");
      option.value = valor;
      option.textContent = texto;
      select.appendChild(option);
    },
  };

  // Inicializar o sistema de filtros
  function inicializarSistemaFiltros() {
    const toggleFiltros = document.getElementById("toggle-filtros");
    const filtrosContainer = document.getElementById("filtros-container");
    const filtroCampo = document.getElementById("filtro-campo");
    const filtroOperador = document.getElementById("filtro-operador");
    const adicionarFiltroBtn = document.getElementById("adicionar-filtro");
    const limparFiltrosBtn = document.getElementById("limpar-filtros");
    const aplicarFiltrosBtn = document.getElementById("aplicar-filtros");

    // Toggle para mostrar/esconder área de filtros
    if (toggleFiltros && filtrosContainer) {
      toggleFiltros.addEventListener("click", function () {
        const isVisible = filtrosContainer.style.display !== "none";
        filtrosContainer.style.display = isVisible ? "none" : "block";

        const icon = document.getElementById("filtro-icon");
        if (icon) {
          icon.classList.toggle("bi-chevron-down", isVisible);
          icon.classList.toggle("bi-chevron-up", !isVisible);
        }
      });
    }

    // Configurar operadores quando o campo muda
    if (filtroCampo) {
      filtroCampo.addEventListener("change", function () {
        SistemaFiltros.configurarOperadores(this.value);
      });
    }

    // Mostrar/esconder segundo campo de valor quando operador muda
    if (filtroOperador) {
      filtroOperador.addEventListener("change", function () {
        SistemaFiltros.atualizarCampoValor2(this.value);
      });
    }

    // Botão para adicionar filtro
    if (adicionarFiltroBtn) {
      adicionarFiltroBtn.addEventListener("click", function () {
        const campo = filtroCampo.value;
        const operador = filtroOperador.value;
        const valor = document.getElementById("filtro-valor").value;
        const valor2 = document.getElementById("filtro-valor2").value;

        SistemaFiltros.adicionar(campo, operador, valor, valor2);
      });
    }

    // Botão para limpar todos os filtros
    if (limparFiltrosBtn) {
      limparFiltrosBtn.addEventListener("click", function () {
        SistemaFiltros.limparTodos();
        carregarRegistros(); // ou aplicarFiltrosAosDados()
      });
    }

    // Botão para aplicar filtros
    if (aplicarFiltrosBtn) {
      aplicarFiltrosBtn.addEventListener("click", function () {
        aplicarFiltrosAosDados();
      });
    }

    // Inicializar a UI de filtros
    SistemaFiltros.atualizarUI();
  }

  // Aplicar filtros aos dados de registros
  function aplicarFiltrosAosDados() {
    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");

    // Mostrar loading
    if (registrosLoading) registrosLoading.classList.remove("d-none");
    if (registrosContainer) registrosContainer.classList.add("d-none");
    if (nenhumRegistro) nenhumRegistro.classList.add("d-none");

    // Buscar todos os registros e aplicar filtros
    fetch(`${API_URL}/registros`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Falha ao carregar registros");
        }
        return response.json();
      })
      .then((data) => {
        if (registrosLoading) registrosLoading.classList.add("d-none");

        // Aplicar filtros aos dados
        const dadosFiltrados = SistemaFiltros.aplicar(data);

        // Verificar se há resultados
        if (dadosFiltrados.length === 0) {
          if (nenhumRegistro) nenhumRegistro.classList.remove("d-none");
          return;
        }

        // Exibir resultados filtrados
        exibirRegistros(dadosFiltrados);
      })
      .catch((error) => {
        if (registrosLoading) registrosLoading.classList.add("d-none");
        console.error("Erro ao aplicar filtros:", error);
        alert("Erro ao carregar registros: " + error.message);
      });
  }

  // Carregar registros (sem filtros)
  function carregarRegistros() {
    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");

    if (registrosLoading) registrosLoading.classList.remove("d-none");
    if (registrosContainer) registrosContainer.classList.add("d-none");
    if (nenhumRegistro) nenhumRegistro.classList.add("d-none");

    fetch(`${API_URL}/registros`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Falha ao carregar registros");
        }
        return response.json();
      })
      .then((data) => {
        if (registrosLoading) registrosLoading.classList.add("d-none");

        if (data.length === 0) {
          if (nenhumRegistro) nenhumRegistro.classList.remove("d-none");
          return;
        }

        exibirRegistros(data);
      })
      .catch((error) => {
        if (registrosLoading) registrosLoading.classList.add("d-none");
        console.error("Erro ao carregar registros:", error);
        alert("Erro ao carregar registros: " + error.message);
      });
  }

  // Exibir registros na tabela
  function exibirRegistros(data) {
    const registrosContainer = document.getElementById("registros-container");
    const registrosTable = document.getElementById("registros-table");

    if (!registrosContainer || !registrosTable) return;

    registrosContainer.classList.remove("d-none");
    registrosTable.innerHTML = "";

    data.forEach((registro) => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${registro.nup || "-"}</td>
        <td>${
          registro.objeto
            ? registro.objeto.length > 30
              ? registro.objeto.substring(0, 30) + "..."
              : registro.objeto
            : "-"
        }</td>
        <td>${registro.modalidade || "-"}</td>
        <td><span class="badge ${getBadgeClass(registro.situacao)}">${
        registro.situacao || "-"
      }</span></td>
        <td>${formatarMoeda(registro.valor_estimado)}</td>
        <td>${formatarMoeda(registro.valor_homologado)}</td>
        <td>${formatarMoeda(registro.economia)}</td>
        <td>
          <div class="btn-group">
            <button class="btn btn-sm btn-info visualizar-btn" data-id="${
              registro.id
            }" title="Visualizar">
              <i class="bi bi-eye"></i>
            </button>
            <button class="btn btn-sm btn-primary editar-btn" data-id="${
              registro.id
            }" title="Editar">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-danger excluir-btn" data-id="${
              registro.id
            }" title="Excluir">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      `;
      registrosTable.appendChild(row);
    });

    // Adicionar event listeners para os botões
    document.querySelectorAll(".visualizar-btn").forEach((btn) => {
      btn.addEventListener("click", function () {
        carregarDetalhesRegistro(this.dataset.id);
      });
    });

    document.querySelectorAll(".editar-btn").forEach((btn) => {
      btn.addEventListener("click", function () {
        carregarRegistroParaEdicao(this.dataset.id);
      });
    });

    document.querySelectorAll(".excluir-btn").forEach((btn) => {
      btn.addEventListener("click", function () {
        confirmarExclusao(this.dataset.id);
      });
    });
  }

  // Visualizar detalhes do registro
  function carregarDetalhesRegistro(id) {
    fetch(`${API_URL}/registros/${id}`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => response.json())
      .then((registro) => {
        const detalhesContent = document.getElementById("detalhes-content");

        // Definir ID para o botão de edição
        currentRegistroId = registro.id;

        // Criar HTML com os detalhes
        let detalhesHTML = `
        <div class="row">
          <div class="col-md-6">
            <p><strong>NUP:</strong> ${registro.nup || "-"}</p>
            <p><strong>Data Entrada DIPLI:</strong> ${
              formatarData(registro.dt_entrada_dipli) || "-"
            }</p>
            <p><strong>Responsável Instrução:</strong> ${
              registro.resp_instrucao || "-"
            }</p>
            <p><strong>Área Demandante:</strong> ${
              registro.area_demandante || "-"
            }</p>
            <p><strong>Pregoeiro:</strong> ${registro.pregoeiro || "-"}</p>
            <p><strong>Modalidade:</strong> ${registro.modalidade || "-"}</p>
            <p><strong>Tipo:</strong> ${registro.tipo || "-"}</p>
            <p><strong>Número:</strong> ${registro.numero || "-"}</p>
            <p><strong>Ano:</strong> ${registro.ano || "-"}</p>
            <p><strong>Prioridade:</strong> ${registro.prioridade || "-"}</p>
            <p><strong>Item PGC:</strong> ${registro.item_pgc || "-"}</p>
          </div>
          <div class="col-md-6">
            <p><strong>Estimado PGC:</strong> ${
              registro.estimado_pgc || "-"
            }</p>
            <p><strong>Ano PGC:</strong> ${registro.ano_pgc || "-"}</p>
            <p><strong>Objeto:</strong> ${registro.objeto || "-"}</p>
            <p><strong>Quantidade de Itens:</strong> ${
              registro.qtd_itens || "-"
            }</p>
            <p><strong>Valor Estimado:</strong> ${formatarMoeda(
              registro.valor_estimado
            )}</p>
            <p><strong>Data de Abertura:</strong> ${
              formatarData(registro.dt_abertura) || "-"
            }</p>
            <p><strong>Situação:</strong> <span class="badge ${getBadgeClass(
              registro.situacao
            )}">${registro.situacao || "-"}</span></p>
            <p><strong>Andamentos:</strong> ${registro.andamentos || "-"}</p>
            <p><strong>Valor Homologado:</strong> ${formatarMoeda(
              registro.valor_homologado
            )}</p>
            <p><strong>Economia:</strong> ${formatarMoeda(
              registro.economia
            )}</p>
            <p><strong>Data de Homologação:</strong> ${
              formatarData(registro.dt_homologacao) || "-"
            }</p>
          </div>
        </div>
      `;

        detalhesContent.innerHTML = detalhesHTML;
        detalhesModal.show();

        // Event listener para o botão de edição
        document
          .getElementById("editar-registro-btn")
          .addEventListener("click", function () {
            detalhesModal.hide();
            carregarRegistroParaEdicao(currentRegistroId);
          });
      })
      .catch((error) => {
        console.error("Erro ao carregar detalhes:", error);
      });
  }

  // Inicializar página de novo registro
  function initNovoRegistro() {
    const formTitle = document.getElementById("form-title");
    const cancelarBtn = document.getElementById("cancelar-registro");
    const registroForm = document.getElementById("registro-form");

    // Resetar formulário
    if (registroForm) registroForm.reset();

    // Atualizar título para "Novo Registro"
    if (formTitle) formTitle.textContent = "Novo Registro";

    // Limpar ID oculto
    const registroIdInput = document.getElementById("registro-id");
    if (registroIdInput) registroIdInput.value = "";

    // Event listener para o botão cancelar
    if (cancelarBtn) {
      cancelarBtn.addEventListener("click", function () {
        loadPage("registros");
      });
    }

    // Event listener para o formulário
    if (registroForm) {
      registroForm.addEventListener("submit", function (e) {
        e.preventDefault();
        salvarRegistro();
      });
    }
  }

  // Carregar registro para edição
  function carregarRegistroParaEdicao(id) {
    loadPage("novo-registro")
      .then(() => {
        return fetch(`${API_URL}/registros/${id}`, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
      })
      .then((response) => response.json())
      .then((registro) => {
        // Atualizar título
        const formTitle = document.getElementById("form-title");
        if (formTitle) formTitle.textContent = "Editar Registro";

        // Preencher formulário
        document.getElementById("registro-id").value = registro.id;
        document.getElementById("nup").value = registro.nup || "";
        document.getElementById("dt_entrada_dipli").value =
          registro.dt_entrada_dipli || "";
        document.getElementById("resp_instrucao").value =
          registro.resp_instrucao || "";
        document.getElementById("area_demandante").value =
          registro.area_demandante || "";
        document.getElementById("pregoeiro").value = registro.pregoeiro || "";
        document.getElementById("modalidade").value = registro.modalidade || "";
        document.getElementById("tipo").value = registro.tipo || "";
        document.getElementById("numero").value = registro.numero || "";
        document.getElementById("ano").value = registro.ano || "";
        document.getElementById("prioridade").value = registro.prioridade || "";
        document.getElementById("item_pgc").value = registro.item_pgc || "";
        document.getElementById("estimado_pgc").value =
          registro.estimado_pgc || "";
        document.getElementById("ano_pgc").value = registro.ano_pgc || "";
        document.getElementById("objeto").value = registro.objeto || "";
        document.getElementById("qtd_itens").value = registro.qtd_itens || "";
        document.getElementById("valor_estimado").value =
          registro.valor_estimado || "";
        document.getElementById("dt_abertura").value =
          registro.dt_abertura || "";
        document.getElementById("situacao").value = registro.situacao || "";
        document.getElementById("andamentos").value = registro.andamentos || "";
        document.getElementById("valor_homologado").value =
          registro.valor_homologado || "";
        document.getElementById("economia").value = registro.economia || "";
        document.getElementById("dt_homologacao").value =
          registro.dt_homologacao || "";
      })
      .catch((error) => {
        console.error("Erro ao carregar registro para edição:", error);
      });
  }

  // Salvar registro (novo ou edição)
  function salvarRegistro() {
    const formData = {
      nup: document.getElementById("nup").value,
      dt_entrada_dipli: document.getElementById("dt_entrada_dipli").value,
      resp_instrucao: document.getElementById("resp_instrucao").value,
      area_demandante: document.getElementById("area_demandante").value,
      pregoeiro: document.getElementById("pregoeiro").value,
      modalidade: document.getElementById("modalidade").value,
      tipo: document.getElementById("tipo").value,
      numero: document.getElementById("numero").value,
      ano: document.getElementById("ano").value,
      prioridade: document.getElementById("prioridade").value,
      item_pgc: document.getElementById("item_pgc").value,
      estimado_pgc: document.getElementById("estimado_pgc").value,
      ano_pgc: document.getElementById("ano_pgc").value,
      objeto: document.getElementById("objeto").value,
      qtd_itens: document.getElementById("qtd_itens").value,
      valor_estimado: document.getElementById("valor_estimado").value,
      dt_abertura: document.getElementById("dt_abertura").value,
      situacao: document.getElementById("situacao").value,
      andamentos: document.getElementById("andamentos").value,
      valor_homologado: document.getElementById("valor_homologado").value,
      economia: document.getElementById("economia").value,
      dt_homologacao: document.getElementById("dt_homologacao").value,
    };

    const registroId = document.getElementById("registro-id").value;
    const isEdicao = registroId !== "";

    const url = isEdicao
      ? `${API_URL}/registros/${registroId}`
      : `${API_URL}/registros`;
    const method = isEdicao ? "PUT" : "POST";

    fetch(url, {
      method: method,
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(formData),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Falha ao salvar registro");
        }
        return response.json();
      })
      .then(() => {
        // Mostrar mensagem de sucesso
        alert(
          isEdicao
            ? "Registro atualizado com sucesso!"
            : "Registro criado com sucesso!"
        );

        // Redirecionar para a página de registros
        loadPage("registros");
      })
      .catch((error) => {
        console.error("Erro ao salvar registro:", error);
        alert("Erro ao salvar registro: " + error.message);
      });
  }

  // Confirmar exclusão de registro
  function confirmarExclusao(id) {
    const confirmacaoTexto = document.getElementById("confirmacao-texto");
    const confirmacaoBtn = document.getElementById("confirmacao-btn");

    if (confirmacaoTexto)
      confirmacaoTexto.textContent =
        "Tem certeza que deseja excluir este registro? Esta ação não pode ser desfeita.";

    if (confirmacaoBtn) {
      confirmacaoBtn.onclick = function () {
        excluirRegistro(id);
        confirmacaoModal.hide();
      };
    }

    confirmacaoModal.show();
  }

  // Excluir registro
  function excluirRegistro(id) {
    fetch(`${API_URL}/registros/${id}`, {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Falha ao excluir registro");
        }
        return response.json();
      })
      .then(() => {
        // Recarregar registros
        carregarRegistros();
        alert("Registro excluído com sucesso!");
      })
      .catch((error) => {
        console.error("Erro ao excluir registro:", error);
        alert("Erro ao excluir registro: " + error.message);
      });
  }

  // Inicializar página de usuários
  function initUsuarios() {
    // Verificar permissão de admin
    if (!currentUser || currentUser.nivel_acesso !== "admin") {
      alert("Acesso negado. Apenas administradores podem acessar esta página.");
      loadPage("dashboard");
      return;
    }

    const usuariosLoading = document.getElementById("usuarios-loading");
    const usuariosContainer = document.getElementById("usuarios-container");
    const usuariosTable = document.getElementById("usuarios-table");
    const novoUsuarioBtn = document.getElementById("novo-usuario-btn");

    // Inicializar modal de usuários se não estiver inicializado
    if (!usuarioModal) {
      const usuarioModalEl = document.getElementById("usuario-modal");
      if (usuarioModalEl) {
        usuarioModal = new bootstrap.Modal(usuarioModalEl);
      }
    }

    // Mostrar loading
    if (usuariosLoading) usuariosLoading.classList.remove("d-none");
    if (usuariosContainer) usuariosContainer.classList.add("d-none");

    // Carregar lista de usuários
    fetch(`${API_URL}/usuarios`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => response.json())
      .then((data) => {
        if (usuariosLoading) usuariosLoading.classList.add("d-none");
        if (usuariosContainer) usuariosContainer.classList.remove("d-none");

        if (usuariosTable) {
          usuariosTable.innerHTML = "";

          data.forEach((usuario) => {
            const row = document.createElement("tr");
            row.innerHTML = `
            <td>${usuario.id}</td>
            <td>${usuario.nome}</td>
            <td>${usuario.email}</td>
            <td>${
              usuario.nivel_acesso === "admin" ? "Administrador" : "Usuário"
            }</td>
            <td>
              <button class="btn btn-sm btn-danger excluir-usuario-btn" data-id="${
                usuario.id
              }" title="Excluir">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          `;
            usuariosTable.appendChild(row);
          });

          // Event listeners para botões de exclusão
          document.querySelectorAll(".excluir-usuario-btn").forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              // Não permitir excluir o próprio usuário
              if (id == currentUser.id) {
                alert("Você não pode excluir seu próprio usuário.");
                return;
              }
              confirmarExclusaoUsuario(id);
            });
          });
        }
      })
      .catch((error) => {
        if (usuariosLoading) usuariosLoading.classList.add("d-none");
        console.error("Erro ao carregar usuários:", error);
      });

    // Event listener para botão novo usuário
    if (novoUsuarioBtn) {
      novoUsuarioBtn.addEventListener("click", function () {
        document.getElementById("usuario-form").reset();
        document.getElementById("usuario-id").value = "";
        document.getElementById("usuario-modal-title").textContent =
          "Novo Usuário";

        // Event listener para salvar usuário
        document.getElementById("salvar-usuario-btn").onclick = function () {
          salvarUsuario();
        };

        usuarioModal.show();
      });
    }
  }

  // Confirmar exclusão de usuário
  function confirmarExclusaoUsuario(id) {
    const confirmacaoTexto = document.getElementById("confirmacao-texto");
    const confirmacaoBtn = document.getElementById("confirmacao-btn");

    if (confirmacaoTexto)
      confirmacaoTexto.textContent =
        "Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.";

    if (confirmacaoBtn) {
      confirmacaoBtn.onclick = function () {
        excluirUsuario(id);
        confirmacaoModal.hide();
      };
    }

    confirmacaoModal.show();
  }

  // Excluir usuário
  function excluirUsuario(id) {
    fetch(`${API_URL}/usuarios/${id}`, {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Falha ao excluir usuário");
        }
        return response.json();
      })
      .then(() => {
        // Recarregar usuários
        initUsuarios();
        alert("Usuário excluído com sucesso!");
      })
      .catch((error) => {
        console.error("Erro ao excluir usuário:", error);
        alert("Erro ao excluir usuário: " + error.message);
      });
  }

  // Salvar usuário
  function salvarUsuario() {
    const nome = document.getElementById("usuario-nome").value;
    const email = document.getElementById("usuario-email").value;
    const senha = document.getElementById("usuario-senha").value;
    const nivel_acesso = document.getElementById("usuario-nivel").value;

    if (!nome || !email || !senha || !nivel_acesso) {
      alert("Todos os campos são obrigatórios");
      return;
    }

    fetch(`${API_URL}/usuarios`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ nome, email, senha, nivel_acesso }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Falha ao criar usuário");
        }
        return response.json();
      })
      .then(() => {
        usuarioModal.hide();
        alert("Usuário criado com sucesso!");
        initUsuarios();
      })
      .catch((error) => {
        console.error("Erro ao criar usuário:", error);
        alert("Erro ao criar usuário: " + error.message);
      });
  }

  // Inicializar a aplicação
  checkAuth();
});
