// main.js - Script principal do sistema
document.addEventListener("DOMContentLoaded", function () {
  // Configurações da API
  const API_URL = "http://localhost:3001/api";
  let token = localStorage.getItem("token");
  let currentUser = JSON.parse(localStorage.getItem("currentUser"));
  let currentRegistroId = null;

  // Variáveis globais para o sistema de paginação
  let allRegistros = []; // Armazena todos os registros recuperados
  let paginaAtual = 1; // Página atual
  const registrosPorPagina = 15; // Quantidade de registros por página

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

  // Variável para controlar requisições pendentes
  let currentRequest = null;

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
    localStorage.removeItem("sidebarState");
    showLoginPage();
  }

  // Carregador de páginas otimizado
  async function loadPage(pageName) {
    try {
      // Cancelar requisição anterior se existir
      if (currentRequest) {
        currentRequest.abort();
      }

      // Limpar event listeners anteriores
      limparEventListeners();

      // Impedir acesso a páginas restritas
      if (pageName === "usuarios" && (!currentUser || currentUser.nivel_acesso !== "admin")) {
        alert("Acesso negado. Apenas administradores podem acessar esta página.");
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

      // Mostrar loading
      pageContainer.innerHTML = `
        <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
          </div>
        </div>
      `;

      // Criar um novo AbortController
      const controller = new AbortController();
      currentRequest = controller;

      // Carregar conteúdo HTML da página
      const response = await fetch(`${pageName}.html`, {
        signal: controller.signal
      });
      
      if (!response.ok) {
        throw new Error(`Erro ao carregar a página ${pageName}`);
      }

      const html = await response.text();
      pageContainer.innerHTML = html;

      // Limpar requisição atual
      currentRequest = null;

      // Inicializar funcionalidades específicas da página
      switch (pageName) {
        case "dashboard":
          await initDashboard();
          break;
        case "registros":
          await initRegistros();
          break;
        case "novo-registro":
          await initNovoRegistro();
          break;
        case "importar":
          await initImportar();
          break;
        case "usuarios":
          await initUsuarios();
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

      return Promise.resolve();
    } catch (error) {
      if (error.name === 'AbortError') {
        console.log('Requisição cancelada');
        return;
      }
      console.error("Erro ao carregar página:", error);
      pageContainer.innerHTML = `
        <div class="alert alert-danger" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          Erro ao carregar a página. Por favor, tente novamente.
        </div>
      `;
    }
  }

  // Função para limpar event listeners
  function limparEventListeners() {
    // Limpar listeners de botões de ação
    document.querySelectorAll(".visualizar-btn, .editar-btn, .excluir-btn").forEach(btn => {
      btn.replaceWith(btn.cloneNode(true));
    });

    // Limpar listeners de filtros
    const elementosFiltro = [
      "toggle-filtros",
      "filtro-campo",
      "filtro-operador",
      "adicionar-filtro",
      "limpar-filtros",
      "aplicar-filtros",
      "exportCSV",
      "exportExcel"
    ];

    elementosFiltro.forEach(id => {
      const elemento = document.getElementById(id);
      if (elemento) {
        elemento.replaceWith(elemento.cloneNode(true));
      }
    });

    // Limpar listeners de paginação
    const elementosPaginacao = [
      "paginacao-anterior",
      "paginacao-proxima"
    ];

    elementosPaginacao.forEach(id => {
      const elemento = document.getElementById(id);
      if (elemento) {
        elemento.replaceWith(elemento.cloneNode(true));
      }
    });
  }

  // Verificar o estado da barra lateral ao carregar a página
  const sidebarState = localStorage.getItem("sidebarState");

  if (sidebarState === "collapsed") {
    sidebar.classList.remove("expanded");
    sidebar.classList.add("collapsed");
    content.classList.remove("expanded");
    content.classList.add("expanded");

    // Esconder textos dos links
    document.querySelectorAll(".nav-text:not(.logo-text)").forEach((el) => {
      el.classList.add("hidden");
    });

    // Atualizar o texto do botão
    const iconSpan = toggleSidebarBtn.querySelector("i");
    if (iconSpan) {
      iconSpan.classList.remove("bi-chevron-left");
      iconSpan.classList.add("bi-chevron-right");
    }

    // Ocultar o texto "Recolher Menu"
    const navText = toggleSidebarBtn.querySelector(".nav-text");
    if (navText) {
      navText.classList.add("hidden");
    }
  }

  // Toggle sidebar
  toggleSidebarBtn.addEventListener("click", function (e) {
    e.preventDefault();

    if (sidebar.classList.contains("expanded")) {
      sidebar.classList.remove("expanded");
      sidebar.classList.add("collapsed");
      content.classList.remove("expanded");
      content.classList.add("expanded");

      // Salvar estado no localStorage
      localStorage.setItem("sidebarState", "collapsed");

      // Esconder textos dos links
      document.querySelectorAll(".nav-text:not(.logo-text)").forEach((el) => {
        el.classList.add("hidden");
      });

      // Atualizar o texto do botão
      const iconSpan = this.querySelector("i");
      iconSpan.classList.remove("bi-chevron-left");
      iconSpan.classList.add("bi-chevron-right");

      // Ocultar o texto "Recolher Menu"
      this.querySelector(".nav-text").classList.add("hidden");
    } else {
      sidebar.classList.add("expanded");
      sidebar.classList.remove("collapsed");
      content.classList.remove("expanded");

      // Salvar estado no localStorage
      localStorage.setItem("sidebarState", "expanded");

      // Mostrar textos dos links
      document.querySelectorAll(".nav-text").forEach((el) => {
        el.classList.remove("hidden");
      });

      // Atualizar o texto do botão
      const iconSpan = this.querySelector("i");
      iconSpan.classList.remove("bi-chevron-right");
      iconSpan.classList.add("bi-chevron-left");

      // Atualizar o texto para "Recolher Menu"
      const textSpan = this.querySelector(".nav-text");
      textSpan.textContent = "Recolher Menu";
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
            return response.json().then(data => {
              throw new Error(data.message || "Erro ao fazer login");
            });
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
          if (error.message === "Failed to fetch") {
            showError("Não foi possível conectar ao servidor. Verifique se o servidor está rodando.");
          } else {
            showError(error.message);
          }
          console.error("Erro no login:", error);
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
    localStorage.removeItem("sidebarState");
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
    
    situacao = situacao.toLowerCase();
    
    if (situacao.includes("homologado") || situacao.includes("concluído") || situacao.includes("finalizado")) {
      return "bg-success";
    } else if (situacao.includes("andamento") || situacao.includes("processamento")) {
      return "bg-warning";
    } else if (situacao.includes("cancelado") || situacao.includes("fracassado")) {
      return "bg-danger";
    } else if (situacao.includes("aguardando")) {
      return "bg-info";
    } else {
      return "bg-secondary";
    }
  }

  // Função para obter o ícone para cada tipo de ação
  function getActionIcon(acao) {
    switch (acao?.toLowerCase()) {
      case 'login':
      case 'acesso':
        return 'bi-box-arrow-in-right';
      case 'criação':
      case 'criacao':
      case 'novo':
      case 'novo registro':
        return 'bi-plus-circle';
      case 'atualização':
      case 'atualizacao':
      case 'edição':
      case 'edicao':
        return 'bi-pencil';
      case 'exclusão':
      case 'exclusao':
      case 'remoção':
      case 'remocao':
        return 'bi-trash';
      default:
        return 'bi-arrow-right';
    }
  }

  // Função para obter a classe do badge para cada tipo de ação
  function getActionBadgeClass(acao) {
    switch (acao?.toLowerCase()) {
      case 'login':
      case 'acesso':
        return 'bg-primary';
      case 'criação':
      case 'criacao':
      case 'novo':
      case 'novo registro':
        return 'bg-success';
      case 'atualização':
      case 'atualizacao':
      case 'edição':
      case 'edicao':
        return 'bg-warning';
      case 'exclusão':
      case 'exclusao':
      case 'remoção':
      case 'remocao':
        return 'bg-danger';
      default:
        return 'bg-secondary';
    }
  }

  // Função para carregar atividades recentes
  async function loadActivities() {
    const activitiesTable = document.getElementById("activities-table");
    const activitiesTableContainer = document.getElementById("activities-table-container");
    const activitiesLoading = document.getElementById("activities-loading");
    const noActivities = document.getElementById("no-activities");
    
    if (!activitiesTable || !activitiesTableContainer) {
        console.error("Elementos da tabela de atividades não encontrados");
        return;
    }
    
    // Mostrar loading e ocultar outros elementos
    if (activitiesLoading) activitiesLoading.classList.remove("d-none");
    if (activitiesTableContainer) activitiesTableContainer.classList.add("d-none");
    if (noActivities) noActivities.classList.add("d-none");
    
    try {
        // Buscar atividades recentes da API
        const response = await fetch(`${API_URL}/atividades`, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        });
        
        if (!response.ok) {
            throw new Error('Falha ao carregar atividades');
        }
        
        const atividades = await response.json();
        
        // Se não houver atividades, mostrar mensagem
        if (!atividades || atividades.length === 0) {
            if (activitiesLoading) activitiesLoading.classList.add("d-none");
            if (noActivities) {
                noActivities.classList.remove("d-none");
                noActivities.innerHTML = `
                    <i class="bi bi-info-circle me-2"></i>
                    Nenhuma atividade recente encontrada.
                `;
            }
            return;
        }
        
        // Limpar a tabela
        activitiesTable.innerHTML = "";
        
        // Adicionar cada atividade à tabela
        atividades.forEach(atividade => {
            // Formatar detalhes para não mostrar o objeto
            let detalhes = "Registro atualizado";
            
            // Se for uma criação, mostrar "Registro criado"
            if (atividade.acao?.toLowerCase().includes('criação') || atividade.acao?.toLowerCase().includes('criacao')) {
                detalhes = "Registro criado";
            }
            // Se for uma exclusão, mostrar "Registro excluído"
            else if (atividade.acao?.toLowerCase().includes('exclusão') || atividade.acao?.toLowerCase().includes('exclusao')) {
                detalhes = "Registro excluído";
            }
            // Se for um login, mostrar "Acesso ao sistema"
            else if (atividade.acao?.toLowerCase().includes('login') || atividade.acao?.toLowerCase().includes('acesso')) {
                detalhes = "Acesso ao sistema";
            }

            const row = document.createElement("tr");
            row.innerHTML = `
                <td>${formatarDataHora(atividade.data_hora)}</td>
                <td>${atividade.usuario_nome || atividade.usuario || "-"}</td>
                <td><span class="badge ${getActionBadgeClass(atividade.acao)}">
                    <i class="bi ${getActionIcon(atividade.acao)} me-1"></i>${atividade.acao || "-"}
                </span></td>
                <td>${atividade.registro_nup || atividade.registro_id || "-"}</td>
                <td>${detalhes}</td>
            `;
            activitiesTable.appendChild(row);
        });
        
        // Mostrar a tabela
        if (activitiesTableContainer) activitiesTableContainer.classList.remove("d-none");
        
    } catch (error) {
        console.error("Erro ao carregar atividades:", error);
        if (noActivities) {
            noActivities.classList.remove("d-none");
            noActivities.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>
                Erro ao carregar atividades recentes: ${error.message}
            `;
        }
    } finally {
        if (activitiesLoading) activitiesLoading.classList.add("d-none");
    }
}

  // Função auxiliar para formatar data e hora
  function formatarDataHora(dataHora) {
    if (!dataHora) return "-";
    try {
      const data = new Date(dataHora);
      return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (e) {
      return dataHora;
    }
  }

  // Inicializar Dashboard
  function initDashboard() {
    // Referências a elementos do DOM
    const dashboardCards = document.getElementById("dashboard-cards");
    const editDashboardBtn = document.getElementById("edit-dashboard-btn");
    const saveDashboardBtn = document.getElementById("save-dashboard-btn");
    const cancelDashboardBtn = document.getElementById("cancel-dashboard-btn");
    const addWidgetBtn = document.getElementById("add-widget-btn");
    const dashboardLoading = document.getElementById("dashboard-loading");
    
    // Referências ao modal
    const widgetConfigModal = new bootstrap.Modal(document.getElementById("widget-config-modal"));
    const widgetForm = document.getElementById("widget-form");
    const widgetTitle = document.getElementById("widget-title");
    const widgetType = document.getElementById("widget-type");
    const widgetSize = document.getElementById("widget-size");
    const widgetColor = document.getElementById("widget-color");
    const widgetId = document.getElementById("widget-id");
    const saveWidgetBtn = document.getElementById("save-widget-btn");
    const deleteWidgetBtn = document.getElementById("delete-widget-btn");
    
    // Array para armazenar os widgets do usuário
    let userWidgets = [];
    
    // Variáveis para armazenar dados
    let isEditMode = false;
    
    // Função para carregar configurações de widgets salvas
    function loadUserWidgets() {
      const savedWidgets = localStorage.getItem("dashboardWidgets");
      
      if (savedWidgets) {
        userWidgets = JSON.parse(savedWidgets);
      } else {
        // Widgets padrão se o usuário não tiver configurações salvas
        userWidgets = [
          {
            id: "widget-" + Date.now() + "-1",
            title: "Total de Registros",
            type: "total",
            size: "col-md-3",
            color: "bg-primary",
            icon: "bi-file-earmark-text"
          },
          {
            id: "widget-" + Date.now() + "-2",
            title: "Homologados",
            type: "homologados",
            size: "col-md-3",
            color: "bg-success",
            icon: "bi-check-circle"
          },
          {
            id: "widget-" + Date.now() + "-3",
            title: "Em Andamento",
            type: "andamento",
            size: "col-md-3",
            color: "bg-warning",
            icon: "bi-hourglass-split"
          },
          {
            id: "widget-" + Date.now() + "-4",
            title: "Economia Total",
            type: "economia",
            size: "col-md-3",
            color: "bg-info",
            icon: "bi-cash-coin"
          }
        ];
        
        // Salvar widgets padrão
        saveUserWidgets();
      }
    }
    
    // Função para salvar configurações de widgets
    function saveUserWidgets() {
      localStorage.setItem("dashboardWidgets", JSON.stringify(userWidgets));
    }
    
    // Função para obter o ícone apropriado para cada tipo de widget
    function getWidgetIcon(type) {
      switch (type) {
        case "total": return "bi-file-earmark-text";
        case "homologados": return "bi-check-circle";
        case "andamento": return "bi-hourglass-split";
        case "fracassados": return "bi-x-circle";
        case "economia": return "bi-cash-coin";
        case "valor_estimado": return "bi-currency-dollar";
        case "valor_homologado": return "bi-cash-stack";
        default: return "bi-card-text";
      }
    }
    
    // Função para obter dados para o widget com base no tipo
    function getWidgetData(type) {
      if (!allRegistros.length) return "0";
      
      switch (type) {
        case "total":
          return allRegistros.length.toString();
          
        case "homologados": {
          const homologados = allRegistros.filter(r => {
            if (!r.situacao) return false;
            return r.situacao.toUpperCase().includes("HOMOLOGADO");
          });
          return homologados.length.toString();
        }
        
        case "andamento": {
          const andamento = allRegistros.filter(r => {
            if (!r.situacao) return false;
            return r.situacao.toUpperCase().includes("EM ANDAMENTO");
          });
          return andamento.length.toString();
        }
        
        case "fracassados": {
          const fracassados = allRegistros.filter(r => {
            if (!r.situacao) return false;
            return r.situacao.toUpperCase().includes("FRACASSADO") || 
                   r.situacao.toUpperCase().includes("DESERTO") ||
                   r.situacao.toUpperCase().includes("CANCELADO");
          });
          return fracassados.length.toString();
        }
        
        case "economia": {
          const homologados = allRegistros.filter(r => {
            if (!r.situacao) return false;
            return r.situacao.toUpperCase().includes("HOMOLOGADO");
          });
          
          let economia = 0;
          homologados.forEach(r => {
            if (r.economia) {
              economia += parseFloat(r.economia);
            }
          });
          return formatarMoeda(economia);
        }
        
        case "valor_estimado": {
          let valorTotal = 0;
          allRegistros.forEach(r => {
            if (r.valor_estimado) {
              valorTotal += parseFloat(r.valor_estimado);
            }
          });
          return formatarMoeda(valorTotal);
        }
        
        case "valor_homologado": {
          const homologados = allRegistros.filter(r => {
            if (!r.situacao) return false;
            return r.situacao.toUpperCase().includes("HOMOLOGADO");
          });
          
          let valorTotal = 0;
          homologados.forEach(r => {
            if (r.valor_homologado) {
              valorTotal += parseFloat(r.valor_homologado);
            }
          });
          return formatarMoeda(valorTotal);
        }
        
        default:
          return "0";
      }
    }
    
    // Função para obter o filtro para cada tipo de widget
    function getWidgetFilter(type) {
      switch (type) {
        case "total":
          return {};
          
        case "homologados":
          return { campo: "situacao", operador: "igual", valor: "Homologado" };
          
        case "andamento":
          return { campo: "situacao", operador: "igual", valor: "Em Andamento" };
          
        case "fracassados":
          return { campo: "situacao", operador: "contem", valor: "Fracassado" };
          
        case "economia":
          return { campo: "economia", operador: "maior", valor: "0" };
          
        case "valor_estimado":
          return { campo: "valor_estimado", operador: "maior", valor: "0" };
          
        case "valor_homologado":
          return { campo: "valor_homologado", operador: "maior", valor: "0" };
          
        default:
          return {};
      }
    }
    
    // Função para gerar o HTML de um widget
    function generateWidgetHTML(widget) {
      const widgetData = getWidgetData(widget.type);
      const icon = widget.icon || getWidgetIcon(widget.type);
      
      return `
        <div class="dashboard-widget ${widget.size}" id="${widget.id}" data-widget-id="${widget.id}">
          <div class="card text-white stat-card ${widget.color} cursor-pointer clickable-card" data-type="${widget.type}">
            <div class="card-body py-2">
              <div class="widget-controls">
                <button class="btn btn-sm btn-light edit-widget-btn me-1" title="Editar">
                  <i class="bi bi-pencil"></i>
                </button>
              </div>
              <h5 class="card-title"><i class="bi ${icon} me-2"></i>${widget.title}</h5>
              <h2 class="widget-value">${widgetData}</h2>
            </div>
          </div>
        </div>
      `;
    }
    
    // Função para renderizar todos os widgets
    function renderWidgets() {
      if (!dashboardCards) return;
      
      dashboardCards.innerHTML = "";
      
      userWidgets.forEach(widget => {
        dashboardCards.innerHTML += generateWidgetHTML(widget);
      });
      
      // Adicionar event listeners aos cartões quando não estiver em modo de edição
      if (!isEditMode) {
        document.querySelectorAll(".dashboard-widget .card").forEach(card => {
          card.addEventListener("click", function() {
            const widgetType = this.getAttribute("data-type");
            const filter = getWidgetFilter(widgetType);
            
            loadPage("registros").then(() => {
              if (Object.keys(filter).length === 0) return;
              
              // Limpar filtros existentes
              SistemaFiltros.limparTodos();
              
              // Adicionar novo filtro baseado no tipo do widget
              SistemaFiltros.adicionar(filter.campo, filter.operador, filter.valor);
              
              // Aplicar filtros
              aplicarFiltrosAosDados();
            });
          });
        });
      } else {
        // Adicionar event listener aos botões de edição quando estiver em modo de edição
        document.querySelectorAll(".edit-widget-btn").forEach(btn => {
          btn.addEventListener("click", function(e) {
            e.stopPropagation(); // Impedir que o clique propague para o card
            const widgetEl = this.closest(".dashboard-widget");
            const widgetId = widgetEl.getAttribute("data-widget-id");
            const widget = userWidgets.find(w => w.id === widgetId);
            
            if (widget) {
              // Preencher o formulário com os dados do widget
              document.getElementById("widget-id").value = widget.id;
              document.getElementById("widget-title").value = widget.title;
              document.getElementById("widget-type").value = widget.type;
              document.getElementById("widget-size").value = widget.size;
              document.getElementById("widget-color").value = widget.color;
              
              // Mostrar o modal
              widgetConfigModal.show();
            }
          });
        });
      }
    }
    
    // Função para atualizar os valores dos widgets 
    function updateWidgetValues() {
      if (!dashboardCards) return;
      
      userWidgets.forEach(widget => {
        const widgetEl = document.getElementById(widget.id);
        if (widgetEl) {
          const valueEl = widgetEl.querySelector(".widget-value");
          if (valueEl) {
            valueEl.textContent = getWidgetData(widget.type);
          }
        }
      });
    }
    
    // Função para ativar o modo de edição
    function enableEditMode() {
      isEditMode = true;
      
      // Mostrar botões de salvar e cancelar, ocultar botão de editar
      editDashboardBtn.classList.add("d-none");
      saveDashboardBtn.classList.remove("d-none");
      cancelDashboardBtn.classList.remove("d-none");
      addWidgetBtn.classList.remove("d-none");
      
      // Adicionar classe para indicar modo de edição
      dashboardCards.classList.add("edit-mode");
      
      // Recarregar widgets com controles de edição
      renderWidgets();
    }
    
    // Função para desativar o modo de edição
    function disableEditMode() {
      isEditMode = false;
      
      // Ocultar botões de salvar e cancelar, mostrar botão de editar
      editDashboardBtn.classList.remove("d-none");
      saveDashboardBtn.classList.add("d-none");
      cancelDashboardBtn.classList.add("d-none");
      addWidgetBtn.classList.add("d-none");
      
      // Remover classe de modo de edição
      dashboardCards.classList.remove("edit-mode");
      
      // Recarregar widgets sem controles de edição
      renderWidgets();
    }
    
    // Event listeners para os botões
    if (editDashboardBtn) {
      editDashboardBtn.addEventListener("click", enableEditMode);
    }
    
    if (saveDashboardBtn) {
      saveDashboardBtn.addEventListener("click", function() {
        saveUserWidgets();
        disableEditMode();
      });
    }
    
    if (cancelDashboardBtn) {
      cancelDashboardBtn.addEventListener("click", function() {
        // Recarregar widgets do localStorage
        loadUserWidgets();
        disableEditMode();
      });
    }
    
    if (addWidgetBtn) {
      addWidgetBtn.addEventListener("click", function() {
        // Limpar o formulário
        widgetForm.reset();
        widgetId.value = "widget-" + Date.now() + "-" + (userWidgets.length + 1);
        
        // Mostrar o modal
        widgetConfigModal.show();
      });
    }
    
    if (saveWidgetBtn) {
      saveWidgetBtn.addEventListener("click", function() {
        // Verificar se o formulário é válido
        if (!widgetForm.checkValidity()) {
          widgetForm.reportValidity();
          return;
        }
        
        const id = widgetId.value;
        const title = widgetTitle.value;
        const type = widgetType.value;
        const size = widgetSize.value;
        const color = widgetColor.value;
        const icon = getWidgetIcon(type);
        
        // Verificar se é um widget existente ou novo
        const existingWidgetIndex = userWidgets.findIndex(w => w.id === id);
        
        if (existingWidgetIndex >= 0) {
          // Atualizar widget existente
          userWidgets[existingWidgetIndex] = { id, title, type, size, color, icon };
        } else {
          // Adicionar novo widget
          userWidgets.push({ id, title, type, size, color, icon });
        }
        
        // Fechar o modal
        widgetConfigModal.hide();
        
        // Renderizar widgets
        renderWidgets();
      });
    }
    
    if (deleteWidgetBtn) {
      deleteWidgetBtn.addEventListener("click", function() {
        const id = widgetId.value;
        
        // Remover widget
        userWidgets = userWidgets.filter(w => w.id !== id);
        
        // Fechar o modal
        widgetConfigModal.hide();
        
        // Renderizar widgets
        renderWidgets();
      });
    }
    
    // Carregar configurações de widgets
    loadUserWidgets();
    
    // Carregar dados
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
        
        // Armazenar todos os registros
        allRegistros = data;
        
        // Renderizar widgets
        renderWidgets();
        
        // Carregar atividades recentes
        loadActivities();
      })
      .catch((error) => {
        if (dashboardLoading) dashboardLoading.classList.add("d-none");
        console.error("Erro:", error);
        
        // Mesmo em caso de erro, tentar carregar atividades (pode haver dados em cache)
        loadActivities();
      });
  }

  // Cache de registros
  let registrosCache = null;
  let ultimaAtualizacaoCache = null;
  const TEMPO_CACHE = 5 * 60 * 1000; // 5 minutos

  // Sistema de filtros - Nova implementação
  async function initRegistros() {
    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");
    const registrosTable = document.getElementById("registros-table");

    // Inicialmente ocultar conteúdo e mostrar loading
    if (registrosLoading) registrosLoading.classList.remove("d-none");
    if (registrosContainer) registrosContainer.classList.add("d-none");
    if (nenhumRegistro) nenhumRegistro.classList.add("d-none");

    try {
      // Verificar se podemos usar o cache
      const agora = new Date().getTime();
      if (registrosCache && ultimaAtualizacaoCache && (agora - ultimaAtualizacaoCache < TEMPO_CACHE)) {
        // Usar dados do cache
        await processarDadosRegistros(registrosCache);
      } else {
        // Buscar novos dados
        const response = await fetch(`${API_URL}/registros`, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });

        if (!response.ok) {
          throw new Error(`Falha ao carregar registros: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();
        
        // Atualizar cache
        registrosCache = data;
        ultimaAtualizacaoCache = agora;

        // Processar dados
        await processarDadosRegistros(data);
      }

      // Inicializar o sistema de filtros
      inicializarSistemaFiltros();
    } catch (error) {
      console.error("Erro ao carregar registros:", error);
      if (registrosLoading) registrosLoading.classList.add("d-none");
      if (nenhumRegistro) {
        nenhumRegistro.classList.remove("d-none");
        nenhumRegistro.innerHTML = `
          <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Erro ao carregar registros: ${error.message}
          </div>
        `;
      }
    }
  }

  // Função para processar dados dos registros
  async function processarDadosRegistros(data) {
    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");
    const registrosTable = document.getElementById("registros-table");

    if (!data || data.length === 0) {
      if (registrosLoading) registrosLoading.classList.add("d-none");
      if (nenhumRegistro) nenhumRegistro.classList.remove("d-none");
      return;
    }

    // Armazenar todos os registros e exibir apenas a primeira página
    allRegistros = data;
    paginaAtual = 1;

    // Exibir registros paginados
    await exibirRegistrosPaginados();

    // Ocultar loading e mostrar container
    if (registrosLoading) registrosLoading.classList.add("d-none");
    if (registrosContainer) registrosContainer.classList.remove("d-none");

    // Atualizar paginação
    if (data.length > registrosPorPagina) {
      atualizarControlesPaginacao();
      const registrosPaginacao = document.getElementById("registros-paginacao");
      if (registrosPaginacao) registrosPaginacao.classList.remove("d-none");
    } else {
      ocultarPaginacao();
    }
  }

  // Função otimizada para exibir registros paginados
  async function exibirRegistrosPaginados() {
    // Calcular índices de início e fim para a página atual
    const inicio = (paginaAtual - 1) * registrosPorPagina;
    const fim = Math.min(inicio + registrosPorPagina, allRegistros.length);

    // Obter apenas os registros da página atual
    const registrosPagina = allRegistros.slice(inicio, fim);

    // Exibir os registros desta página de forma otimizada
    await exibirRegistros(registrosPagina);

    // Exibir informações da paginação
    const paginacaoInfo = document.getElementById("paginacao-info");
    if (paginacaoInfo) {
      paginacaoInfo.textContent = `Mostrando ${inicio + 1} a ${fim} de ${allRegistros.length} registros`;
    }
  }

  // Função otimizada para exibir registros
  async function exibirRegistros(data) {
    const registrosContainer = document.getElementById("registros-container");
    const registrosTable = document.getElementById("registros-table");
    const nenhumRegistro = document.getElementById("nenhum-registro");

    if (!registrosContainer || !registrosTable) {
      console.error("Elementos essenciais para exibição de registros não encontrados");
      return;
    }

    if (!data || data.length === 0) {
      registrosContainer.classList.add("d-none");
      if (nenhumRegistro) nenhumRegistro.classList.remove("d-none");
      ocultarPaginacao();
      return;
    }

    // Exibir container e limpar tabela
    registrosContainer.classList.remove("d-none");
    if (nenhumRegistro) nenhumRegistro.classList.add("d-none");

    // Criar fragmento para melhor performance
    const fragment = document.createDocumentFragment();

    // Adicionar cada registro ao fragmento
    data.forEach((registro) => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${registro.nup || "-"}</td>
        <td>${registro.objeto ? (registro.objeto.length > 30 ? registro.objeto.substring(0, 30) + "..." : registro.objeto) : "-"}</td>
        <td>${registro.modalidade || "-"}</td>
        <td><span class="badge ${getBadgeClass(registro.situacao)}">${registro.situacao || "-"}</span></td>
        <td>${formatarMoeda(registro.valor_estimado)}</td>
        <td>${formatarMoeda(registro.valor_homologado)}</td>
        <td>${formatarMoeda(registro.economia)}</td>
        <td>
          <div class="btn-group">
            <button class="btn btn-sm btn-info visualizar-btn" data-id="${registro.id}" title="Visualizar">
              <i class="bi bi-eye"></i>
            </button>
            <button class="btn btn-sm btn-primary editar-btn" data-id="${registro.id}" title="Editar">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-danger excluir-btn" data-id="${registro.id}" title="Excluir">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      `;
      fragment.appendChild(row);
    });

    // Limpar tabela e adicionar fragmento
    registrosTable.innerHTML = "";
    registrosTable.appendChild(fragment);

    // Adicionar event listeners de forma otimizada
    const addEventListeners = (selector, handler) => {
      document.querySelectorAll(selector).forEach(btn => {
        btn.addEventListener("click", () => handler(btn.dataset.id));
      });
    };

    addEventListeners(".visualizar-btn", carregarDetalhesRegistro);
    addEventListeners(".editar-btn", carregarRegistroParaEdicao);
    addEventListeners(".excluir-btn", confirmarExclusao);
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
          // Para campos que não são situação, comportamento normal
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

    // Obter todos os filtros ativos
    obterTodos: function () {
      return this.filtrosAtivos.map((filtro) => ({
        campo: filtro.campo,
        operador: filtro.operador,
        valor: filtro.valor,
        valor2: filtro.valor2,
      }));
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
    const exportCSVBtn = document.getElementById("exportCSV");
    const exportExcelBtn = document.getElementById("exportExcel");

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

    // Adicionar Event Listeners para botões de exportação
    if (exportCSVBtn) {
      exportCSVBtn.addEventListener("click", () => exportarRegistros("csv"));
    }

    if (exportExcelBtn) {
      exportExcelBtn.addEventListener("click", () =>
        exportarRegistros("excel")
      );
    }

    // Inicializar a UI de filtros
    SistemaFiltros.atualizarUI();

    // Adicionar event listeners para os botões de paginação
    const paginacaoAnterior = document.getElementById("paginacao-anterior");
    const paginacaoProxima = document.getElementById("paginacao-proxima");

    if (paginacaoAnterior) {
      paginacaoAnterior.addEventListener("click", function (e) {
        e.preventDefault();
        paginaAnterior();
      });
    }

    if (paginacaoProxima) {
      paginacaoProxima.addEventListener("click", function (e) {
        e.preventDefault();
        paginaProxima();
      });
    }
  }

  // Função para exportar registros com os filtros aplicados
  function exportarRegistros(formato) {
    // Mostrar indicador de carregamento
    const loadingElement = document.getElementById("loading");
    if (loadingElement) loadingElement.style.display = "block";

    // Preparar URL com base no formato
    const url = `${API_URL}/export/${formato}`;

    // Obter filtros ativos
    const filtrosAtivos = obterFiltrosAtivos();

    // Fazer requisição POST com os filtros ativos
    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ filtros: filtrosAtivos }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Erro ao exportar: ${response.statusText}`);
        }
        return response.blob();
      })
      .then((blob) => {
        // Ocultar indicador de carregamento
        if (loadingElement) loadingElement.style.display = "none";

        // Criar URL para o blob
        const url = window.URL.createObjectURL(blob);

        // Definir nome do arquivo
        const dataAtual = new Date().toISOString().split("T")[0];
        const extensao = formato === "csv" ? "csv" : "xlsx";
        const nomeArquivo = `registros_${dataAtual}.${extensao}`;

        // Criar link para download
        const a = document.createElement("a");
        a.style.display = "none";
        a.href = url;
        a.download = nomeArquivo;

        // Adicionar link ao documento, clicar nele e removê-lo
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        // Notificar usuário
        alert(`Exportação em ${formato.toUpperCase()} concluída com sucesso!`);
      })
      .catch((error) => {
        console.error(`Erro ao exportar registros em ${formato}:`, error);
        if (loadingElement) loadingElement.style.display = "none";
        alert(`Erro ao exportar: ${error.message}`);
      });
  }

  /**
   * Obtém os filtros ativos da interface
   * @returns {Array} - Lista de filtros ativos
   */
  function obterFiltrosAtivos() {
    const filtros = [];
    const filtrosContainer = document.getElementById("filtros-container");

    // Se não houver container de filtros, retornar array vazio
    if (!filtrosContainer) return filtros;

    // Obter todos os elementos de filtro
    const elementosFiltro = filtrosContainer.querySelectorAll(".filtro");

    elementosFiltro.forEach((filtroElement) => {
      // Obter seletores de campo, operador e valor
      const campoSelect = filtroElement.querySelector(".campo-filtro");
      const operadorSelect = filtroElement.querySelector(".operador-filtro");
      const valorInput = filtroElement.querySelector(".valor-filtro");
      const valor2Input = filtroElement.querySelector(".valor2-filtro");

      // Se algum dos elementos necessários não estiver presente ou o campo e operador não tiverem valores, pular
      if (
        !campoSelect ||
        !operadorSelect ||
        !valorInput ||
        !campoSelect.value ||
        !operadorSelect.value
      ) {
        return;
      }

      // Obter valores dos elementos
      const campo = campoSelect.value;
      const operador = operadorSelect.value;
      let valor = valorInput.value;

      // Se o valor estiver vazio, pular este filtro
      if (!valor.trim()) {
        return;
      }

      // Criar objeto de filtro
      const filtro = {
        campo,
        operador,
        valor,
      };

      // Se for operador "entre", adicionar segundo valor
      if (operador === "entre" && valor2Input && valor2Input.value.trim()) {
        filtro.valor2 = valor2Input.value;
      }

      // Adicionar filtro à lista
      filtros.push(filtro);
    });

    return filtros;
  }

  // Aplicar filtros aos dados de registros
  function aplicarFiltrosAosDados() {
    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");
    const registrosPaginacao = document.getElementById("registros-paginacao");

    // Mostrar loading e ocultar outros elementos
    if (registrosLoading) registrosLoading.classList.remove("d-none");
    if (registrosContainer) registrosContainer.classList.add("d-none");
    if (nenhumRegistro) nenhumRegistro.classList.add("d-none");
    if (registrosPaginacao) registrosPaginacao.classList.add("d-none");

    // Buscar todos os registros e aplicar filtros
    fetch(`${API_URL}/registros`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(
            `Falha ao carregar registros: ${response.status} ${response.statusText}`
          );
        }
        return response.json();
      })
      .then((data) => {
        // Ocultar indicador de carregamento
        if (registrosLoading) registrosLoading.classList.add("d-none");

        // Aplicar filtros aos dados
        allRegistros = SistemaFiltros.aplicar(data);
        paginaAtual = 1; // Resetar para a primeira página ao aplicar filtros

        // Verificar se há resultados
        if (!allRegistros || allRegistros.length === 0) {
          if (nenhumRegistro) {
            nenhumRegistro.classList.remove("d-none");
            nenhumRegistro.innerHTML = `
                        <i class="bi bi-info-circle me-2"></i> 
                        Nenhum registro encontrado com os filtros aplicados.
                    `;
          }
          ocultarPaginacao();
          return;
        }

        // Exibir resultados filtrados (apenas a primeira página)
        exibirRegistrosPaginados();

        // Atualizar paginação apenas se houver registros suficientes
        if (allRegistros.length > registrosPorPagina) {
          atualizarControlesPaginacao();
          if (registrosPaginacao) registrosPaginacao.classList.remove("d-none");
        } else {
          ocultarPaginacao();
        }
      })
      .catch((error) => {
        // Ocultar indicador de carregamento e mostrar mensagem de erro
        if (registrosLoading) registrosLoading.classList.add("d-none");
        console.error("Erro ao aplicar filtros:", error);

        // Exibir mensagem de erro ao usuário
        alert(`Erro ao carregar registros: ${error.message}`);

        // Garantir que o container de "nenhum registro" esteja visível
        if (nenhumRegistro) {
          nenhumRegistro.classList.remove("d-none");
          nenhumRegistro.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>
                    Ocorreu um erro ao aplicar os filtros. Tente novamente mais tarde.
                `;
        }

        // Garantir que a paginação esteja oculta em caso de erro
        ocultarPaginacao();
      });
  }

  // Carregar registros (sem filtros)
  function carregarRegistros() {
    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");
    const registrosPaginacao = document.getElementById("registros-paginacao");

    // Mostrar indicador de carregamento e ocultar outros elementos
    if (registrosLoading) registrosLoading.classList.remove("d-none");
    if (registrosContainer) registrosContainer.classList.add("d-none");
    if (nenhumRegistro) nenhumRegistro.classList.add("d-none");
    if (registrosPaginacao) registrosPaginacao.classList.add("d-none");

    fetch(`${API_URL}/registros`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(
            `Falha ao carregar registros: ${response.status} ${response.statusText}`
          );
        }
        return response.json();
      })
      .then((data) => {
        // Ocultar indicador de carregamento
        if (registrosLoading) registrosLoading.classList.add("d-none");

        // Verificar se há dados
        if (!data || data.length === 0) {
          if (nenhumRegistro) nenhumRegistro.classList.remove("d-none");
          ocultarPaginacao();
          return;
        }

        // Armazenar todos os registros e exibir apenas a primeira página
        allRegistros = data;
        paginaAtual = 1;
        exibirRegistrosPaginados();

        // Atualizar paginação apenas se houver registros suficientes
        if (data.length > registrosPorPagina) {
          atualizarControlesPaginacao();
          if (registrosPaginacao) registrosPaginacao.classList.remove("d-none");
        } else {
          ocultarPaginacao();
        }
      })
      .catch((error) => {
        // Ocultar indicador de carregamento e mostrar mensagem de erro
        if (registrosLoading) registrosLoading.classList.add("d-none");
        console.error("Erro ao carregar registros:", error);

        // Exibir mensagem de erro ao usuário
        alert(`Erro ao carregar registros: ${error.message}`);

        // Garantir que o container de "nenhum registro" esteja visível
        if (nenhumRegistro) {
          nenhumRegistro.classList.remove("d-none");
          nenhumRegistro.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>
                    Ocorreu um erro ao carregar os registros. Tente novamente mais tarde.
                `;
        }

        // Garantir que a paginação esteja oculta em caso de erro
        ocultarPaginacao();
      });
  }

  // Função para exibir apenas os registros da página atual
  function exibirRegistrosPaginados() {
    // Calcular índices de início e fim para a página atual
    const inicio = (paginaAtual - 1) * registrosPorPagina;
    const fim = Math.min(inicio + registrosPorPagina, allRegistros.length);

    // Obter apenas os registros da página atual
    const registrosPagina = allRegistros.slice(inicio, fim);

    // Exibir os registros desta página
    exibirRegistros(registrosPagina);

    // Exibir informações da paginação
    const paginacaoInfo = document.getElementById("paginacao-info");
    if (paginacaoInfo) {
      paginacaoInfo.textContent = `Mostrando ${inicio + 1} a ${fim} de ${
        allRegistros.length
      } registros`;
    }
  }

  // Função para atualizar os controles de paginação
  function atualizarControlesPaginacao() {
    const totalRegistros = allRegistros.length;
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);

    // Verificar se o elemento de informação da paginação existe
    const paginacaoInfo = document.getElementById("paginacao-info");
    if (paginacaoInfo) {
      paginacaoInfo.textContent = `Mostrando ${
        (paginaAtual - 1) * registrosPorPagina + 1
      } a ${Math.min(
        paginaAtual * registrosPorPagina,
        totalRegistros
      )} de ${totalRegistros} registros`;
    }

    // Obter a lista de paginação
    const paginacaoLista = document.querySelector("ul.pagination");
    if (!paginacaoLista) {
      console.error("Elemento de paginação não encontrado");
      return;
    }

    // Mostrar o elemento de paginação
    const registrosPaginacao = document.getElementById("registros-paginacao");
    if (registrosPaginacao) {
      registrosPaginacao.classList.remove("d-none");
    }

    // Obter os botões anterior e próximo
    const btnAnterior = paginacaoLista.querySelector(".page-item:first-child");
    const btnProximo = paginacaoLista.querySelector(".page-item:last-child");

    // Verificar se os botões existem
    if (!btnAnterior || !btnProximo) {
      // Recriar a estrutura básica da paginação se os botões não existirem
      paginacaoLista.innerHTML = `
            <li class="page-item" id="btn-prev">
                <a class="page-link" href="#" id="paginacao-anterior" aria-label="Anterior">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <li class="page-item" id="btn-next">
                <a class="page-link" href="#" id="paginacao-proxima" aria-label="Próximo">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        `;

      // Adicionar os event listeners para os novos botões
      const paginacaoAnterior = document.getElementById("paginacao-anterior");
      const paginacaoProxima = document.getElementById("paginacao-proxima");

      if (paginacaoAnterior) {
        paginacaoAnterior.addEventListener("click", function (e) {
          e.preventDefault();
          paginaAnterior();
        });
      }

      if (paginacaoProxima) {
        paginacaoProxima.addEventListener("click", function (e) {
          e.preventDefault();
          paginaProxima();
        });
      }

      // Atualizar as referências aos botões
      const btnAnteriorNovo = paginacaoLista.querySelector(
        ".page-item:first-child"
      );
      const btnProximoNovo = paginacaoLista.querySelector(
        ".page-item:last-child"
      );

      if (btnAnteriorNovo) {
        btnAnteriorNovo.classList.toggle("disabled", paginaAtual <= 1);
      }

      if (btnProximoNovo) {
        btnProximoNovo.classList.toggle(
          "disabled",
          paginaAtual >= totalPaginas
        );
      }
    } else {
      // Atualizar estado dos botões anterior/próximo se eles existirem
      btnAnterior.classList.toggle("disabled", paginaAtual <= 1);
      btnProximo.classList.toggle("disabled", paginaAtual >= totalPaginas);

      // Remover páginas existentes (mantendo apenas o primeiro e último item que são os botões Anterior e Próximo)
      const itensParaRemover = [];
      paginacaoLista.querySelectorAll(".page-item").forEach((item) => {
        if (
          !item.classList.contains("btn-prev") &&
          !item.classList.contains("btn-next") &&
          item !== btnAnterior &&
          item !== btnProximo
        ) {
          itensParaRemover.push(item);
        }
      });

      itensParaRemover.forEach((item) => {
        if (item.parentNode === paginacaoLista) {
          item.remove();
        }
      });
    }

    // Decidir quais páginas mostrar
    const mostrarPaginas = [];

    // Sempre mostrar a primeira página
    mostrarPaginas.push(1);

    // Lógica para mostrar páginas ao redor da página atual e reticências quando necessário
    if (totalPaginas <= 7) {
      // Se tivermos 7 ou menos páginas, mostrar todas
      for (let i = 2; i < totalPaginas; i++) {
        mostrarPaginas.push(i);
      }
    } else {
      // Se tivermos mais de 7 páginas, mostrar lógica com reticências

      // Se a página atual está próxima do início
      if (paginaAtual <= 3) {
        mostrarPaginas.push(2, 3, 4, "...", totalPaginas - 1);
      }
      // Se a página atual está próxima do final
      else if (paginaAtual >= totalPaginas - 2) {
        mostrarPaginas.push(
          "...",
          totalPaginas - 3,
          totalPaginas - 2,
          totalPaginas - 1
        );
      }
      // Se a página atual está no meio
      else {
        mostrarPaginas.push(
          "...",
          paginaAtual - 1,
          paginaAtual,
          paginaAtual + 1,
          "..."
        );
      }
    }

    // Sempre mostrar a última página, a menos que seja a única página
    if (totalPaginas > 1) {
      mostrarPaginas.push(totalPaginas);
    }

    // Obter referência atualizada ao botão próximo
    const btnProximoRef = paginacaoLista.querySelector(".page-item:last-child");
    if (!btnProximoRef) {
      console.error("Botão 'Próximo' não encontrado após recriação");
      return;
    }

    // Adicionar elementos de página à lista
    mostrarPaginas.forEach((numeroPagina) => {
      const novaPagina = document.createElement("li");
      novaPagina.classList.add("page-item");

      if (numeroPagina === "...") {
        // Reticências
        const span = document.createElement("span");
        span.classList.add("page-link");
        span.textContent = "...";
        span.style.pointerEvents = "none";
        novaPagina.classList.add("disabled");
        novaPagina.appendChild(span);
      } else {
        // Botão de página numérica
        const link = document.createElement("a");
        link.classList.add("page-link");
        link.href = "#";
        link.textContent = numeroPagina;

        if (numeroPagina === paginaAtual) {
          novaPagina.classList.add("active");
        }

        link.addEventListener("click", (e) => {
          e.preventDefault();
          irParaPagina(numeroPagina);
        });

        novaPagina.appendChild(link);
      }

      // Inserir antes do botão "Próximo"
      try {
        paginacaoLista.insertBefore(novaPagina, btnProximoRef);
      } catch (error) {
        console.error("Erro ao inserir item de paginação:", error);
      }
    });
  }

  // Função para ir para uma página específica
  function irParaPagina(numeroPagina) {
    // Converter para número caso seja uma string
    numeroPagina = parseInt(numeroPagina);

    if (isNaN(numeroPagina)) return;

    const totalPaginas = Math.ceil(allRegistros.length / registrosPorPagina);

    // Garantir que a página esteja dentro dos limites
    if (numeroPagina < 1) {
      numeroPagina = 1;
    } else if (numeroPagina > totalPaginas) {
      numeroPagina = totalPaginas;
    }

    // Atualizar a página atual e recarregar os dados
    paginaAtual = numeroPagina;
    exibirRegistrosPaginados();
    atualizarControlesPaginacao();

    // Scroll para o topo da tabela
    const tabela = document.getElementById("tabela-registros");
    if (tabela) {
      tabela.scrollIntoView({ behavior: "smooth" });
    }
  }

  // Função para ir para a página anterior
  function paginaAnterior() {
    irParaPagina(paginaAtual - 1);
  }

  // Função para ir para a próxima página
  function paginaProxima() {
    irParaPagina(paginaAtual + 1);
  }

  // Função para ocultar a paginação
  function ocultarPaginacao() {
    const paginacao = document.getElementById("registros-paginacao");
    if (paginacao) {
      paginacao.classList.add("d-none");
    }

    // Também ocultar o elemento de informação da paginação
    const paginacaoInfo = document.getElementById("paginacao-info");
    if (paginacaoInfo) {
      paginacaoInfo.textContent = "";
    }
  }

  // Função para mostrar a paginação
  function mostrarPaginacao() {
    const paginacao = document.getElementById("registros-paginacao");
    if (paginacao) {
      // Verificar se há mais de uma página para mostrar
      if (allRegistros.length > registrosPorPagina) {
        paginacao.classList.remove("d-none");
        atualizarControlesPaginacao();
      } else {
        ocultarPaginacao();
      }
    }
  }

  // Exibir registros na tabela
  function exibirRegistros(data) {
    const registrosContainer = document.getElementById("registros-container");
    const registrosTable = document.getElementById("registros-table");
    const nenhumRegistro = document.getElementById("nenhum-registro");

    // Verificar se os elementos necessários foram encontrados
    if (!registrosContainer || !registrosTable) {
      console.error(
        "Elementos essenciais para exibição de registros não encontrados"
      );
      return;
    }

    // Verificar se há dados para exibir
    if (!data || data.length === 0) {
      registrosContainer.classList.add("d-none");
      if (nenhumRegistro) {
        nenhumRegistro.classList.remove("d-none");
      }
      ocultarPaginacao();
      return;
    }

    // Exibir container e limpar tabela
    registrosContainer.classList.remove("d-none");
    registrosTable.innerHTML = "";

    if (nenhumRegistro) {
      nenhumRegistro.classList.add("d-none");
    }

    // Mostrar controles de paginação se necessário
    mostrarPaginacao();

    // Adicionar cada registro à tabela
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
  async function carregarDetalhesRegistro(id) {
    const detalhesContent = document.getElementById("detalhes-content");
    
    try {
      const response = await fetch(`${API_URL}/registros/${id}`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error("Erro ao carregar detalhes do registro");
      }

      const registro = await response.json();
      
      detalhesContent.innerHTML = `
        <div class="registro-detalhes">
          <div class="registro-header">
            <div>
              <h4>NUP</h4>
              <div class="valor destaque">${registro.nup || "-"}</div>
            </div>
            <div>
              <h4>Situação</h4>
              <div class="situacao ${registro.situacao?.toLowerCase() || 'pendente'}">
                <i class="bi ${getSituacaoIcon(registro.situacao)}"></i>
                ${registro.situacao || "Pendente"}
              </div>
            </div>
          </div>
          
          <div class="registro-info-grid">
            <div class="registro-info-item">
              <label>Objeto</label>
              <div class="valor">${registro.objeto || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Área Demandante</label>
              <div class="valor">${registro.area_demandante || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Pregoeiro</label>
              <div class="valor">${registro.pregoeiro || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Modalidade</label>
              <div class="valor">${registro.modalidade || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Número</label>
              <div class="valor">${registro.numero || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Ano</label>
              <div class="valor">${registro.ano || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Valor Estimado</label>
              <div class="valor monetario">${formatarMoeda(registro.valor_estimado)}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Valor Homologado</label>
              <div class="valor monetario">${formatarMoeda(registro.valor_homologado)}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Data de Abertura</label>
              <div class="valor data">${registro.data_abertura ? formatarData(registro.data_abertura) : "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Data Entrada DIPLI</label>
              <div class="valor data">${registro.data_entrada_dipli ? formatarData(registro.data_entrada_dipli) : "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Estimado PGC</label>
              <div class="valor">${registro.estimado_pgc || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Ano PGC</label>
              <div class="valor">${registro.ano_pgc || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Item PGC</label>
              <div class="valor">${registro.item_pgc || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Quantidade de Itens</label>
              <div class="valor">${registro.quantidade_itens || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Prioridade</label>
              <div class="valor">${registro.prioridade || "-"}</div>
            </div>
            
            <div class="registro-info-item">
              <label>Andamentos</label>
              <div class="valor">${registro.andamentos || "-"}</div>
            </div>
          </div>
          
          <div class="registro-acoes">
            <button class="btn btn-editar" onclick="carregarRegistroParaEdicao('${registro.id}')">
              <i class="bi bi-pencil"></i>
              Editar
            </button>
            <button class="btn btn-excluir" onclick="confirmarExclusao('${registro.id}')">
              <i class="bi bi-trash"></i>
              Excluir
            </button>
          </div>
        </div>
      `;
      
      // Mostrar modal
      const modal = new bootstrap.Modal(document.getElementById("detalhes-modal"));
      modal.show();
      
    } catch (error) {
      console.error("Erro:", error);
      alert("Erro ao carregar detalhes do registro");
    }
  }

  function getSituacaoIcon(situacao) {
    switch (situacao?.toLowerCase()) {
      case 'concluido':
        return 'bi-check-circle-fill';
      case 'cancelado':
        return 'bi-x-circle-fill';
      case 'pendente':
        return 'bi-clock-fill';
      default:
        return 'bi-dash-circle-fill';
    }
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
      .then((response) => {
        if (!response.ok) {
          throw new Error("Falha ao carregar lista de usuários");
        }
        return response.json();
      })
      .then((data) => {
        if (usuariosLoading) usuariosLoading.classList.add("d-none");
        if (usuariosContainer) usuariosContainer.classList.remove("d-none");
        if (usuariosTable) {
          usuariosTable.innerHTML = "";
          data.forEach((usuario) => {
            const row = document.createElement("tr");
            row.innerHTML = `
              <td>${usuario.nome}</td>
              <td>${usuario.email}</td>
              <td>${usuario.nivel_acesso}</td>
              <td>
                <div class="btn-group">
                  <button class="btn btn-sm btn-primary editar-btn" data-id="${usuario.id}" title="Editar">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-danger excluir-btn" data-id="${usuario.id}" title="Excluir">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            `;
            usuariosTable.appendChild(row);
          });
        }
      })
      .catch((error) => {
        if (usuariosLoading) usuariosLoading.classList.add("d-none");
        console.error("Erro ao carregar lista de usuários:", error);
        alert("Erro ao carregar lista de usuários: " + error.message);
      });
  }

  // Função para inicializar a página de importação
  function initImportar() {
    const pageContainer = document.getElementById("page-container");
    
    pageContainer.innerHTML = `
      <div class="container">
        <div class="row mb-4">
          <div class="col">
            <h2><i class="bi bi-cloud-upload me-2"></i>Importar Dados</h2>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
              <li class="nav-item">
                <a class="nav-link active" href="#" data-import-type="full">Importação Completa</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#" data-import-type="values">Importar Valores</a>
              </li>
            </ul>
          </div>
          
          <div class="card-body">
            <div id="import-full" class="import-section">
              <h5 class="card-title mb-4">Importar Planilha Completa</h5>
              <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Esta opção permite importar todos os dados dos registros.
                Certifique-se de que a planilha contenha as colunas necessárias.
              </div>
              <form id="import-form">
                <div class="mb-4">
                  <input type="file" class="form-control" id="excel-file" accept=".xlsx,.xls">
                </div>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="limpar-dados">
                  <label class="form-check-label" for="limpar-dados">
                    Limpar dados existentes antes de importar
                  </label>
                  <div class="form-text text-danger">Cuidado: Isso excluirá TODOS os registros existentes.</div>
                </div>
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-cloud-upload me-2"></i>Importar Dados
                </button>
              </form>
            </div>

            <div id="import-values" class="import-section d-none">
              <h5 class="card-title mb-4">Importar Valores Monetários</h5>
              <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Esta opção permite importar apenas os valores estimados e homologados. 
                A planilha deve conter as colunas: NUP, Valor Estimado e Valor Homologado.
              </div>
              <div class="mb-4">
                <input type="file" class="form-control" id="values-file" accept=".xlsx,.xls">
              </div>
              <button class="btn btn-primary" id="import-values-btn">
                <i class="bi bi-cloud-upload me-2"></i>Importar Valores
              </button>
            </div>

            <div id="preview-container" class="mt-4 d-none">
              <h6>Prévia dos Dados:</h6>
              <div class="table-responsive">
                <table class="table table-sm table-bordered">
                  <thead id="preview-header"></thead>
                  <tbody id="preview-body"></tbody>
                </table>
              </div>
              <div class="mt-3">
                <button class="btn btn-success" id="confirm-import">
                  <i class="bi bi-check-circle me-2"></i>Confirmar Importação
                </button>
                <button class="btn btn-secondary" id="cancel-import">
                  <i class="bi bi-x-circle me-2"></i>Cancelar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    // Variáveis para armazenar dados
    let excelData = null;
    let importType = 'full';

    // Event listeners para as tabs
    document.querySelectorAll('.nav-link').forEach(tab => {
      tab.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('.nav-link').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        importType = tab.dataset.importType;
        document.querySelectorAll('.import-section').forEach(section => {
          section.classList.add('d-none');
        });
        document.getElementById(`import-${importType}`).classList.remove('d-none');
        document.getElementById('preview-container').classList.add('d-none');
      });
    });

    // Event listener para importação completa
    document.getElementById('import-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const fileInput = document.getElementById('excel-file');
      const limparDados = document.getElementById('limpar-dados').checked;
      
      if (!fileInput.files[0]) {
        alert('Por favor, selecione um arquivo Excel.');
        return;
      }

      try {
        const formData = new FormData();
        formData.append('excel', fileInput.files[0]);
        formData.append('limparDados', limparDados);

        const response = await fetch(`${API_URL}/importar-excel`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`
          },
          body: formData
        });

        if (!response.ok) {
          const error = await response.json();
          throw new Error(error.message || 'Erro ao importar arquivo');
        }

        const result = await response.json();
        alert(`Importação concluída com sucesso!\nTotal processado: ${result.totalProcessados}\nSucessos: ${result.sucessos}\nFalhas: ${result.falhas}`);
        
        // Limpar formulário
        fileInput.value = '';
        document.getElementById('limpar-dados').checked = false;

      } catch (error) {
        console.error('Erro na importação:', error);
        alert(error.message || 'Erro ao importar os dados. Por favor, tente novamente.');
      }
    });

    // Função para processar arquivo de valores
    async function processValuesFile(file) {
      try {
        // Validate file type
        if (!file.name.match(/\.(xlsx|xls)$/i)) {
          showErrorModal('Por favor, selecione um arquivo Excel válido (.xlsx ou .xls)');
          return;
        }

        // Show loading toast
        const loadingToast = showToast('Processando arquivo...', 'info', true);

        // Create FormData and append file
        const formData = new FormData();
        formData.append('file', file);

        // Send to server
        const response = await fetch(`${API_URL}/registros/importar-valores`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`
          },
          body: formData
        });

        if (!response.ok) {
          const result = await response.json();
          throw new Error(result.error || 'Erro ao importar os valores');
        }

        const result = await response.json();

        // Show success message
        showToast('Importação concluída com sucesso!', 'success');

        // Reload records and activities
        await Promise.all([
          loadRecords(),
          loadActivities()
        ]);

      } catch (error) {
        console.error('Erro ao processar arquivo:', error);
        showErrorModal(error.message || 'Erro ao processar o arquivo. Por favor, tente novamente.');
      }
    }

    // Event listener for values file input
    document.getElementById('values-file').addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        processValuesFile(file);
      }
    });

    // Event listener para confirmar importação
    document.getElementById('confirm-import').addEventListener('click', async () => {
      if (!excelData || excelData.length === 0) {
          showToast('error', 'Nenhum dado para importar');
          return;
      }

      const loadingToast = showToast('info', 'Importando valores...', true);

      try {
          const response = await fetch('/api/registros/importar-valores', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json',
                  'Authorization': `Bearer ${token}`
              },
              body: JSON.stringify({ dados: excelData })
          });

          const result = await response.json();

          if (!response.ok) {
              throw new Error(result.error || 'Erro ao importar valores');
          }

          if (result.erros && result.erros.length > 0) {
              // Show errors in modal
              const errorList = result.erros.map(error => `<li>${error.nup}: ${error.erro}</li>`).join('');
              const modalContent = `
                  <div class="alert alert-danger">
                      <h5>Erros encontrados durante a importação:</h5>
                      <ul>${errorList}</ul>
                  </div>
              `;
              showModal('Erros na Importação', modalContent);
              showToast('error', `Importação concluída com ${result.erros.length} erros`);
          } else {
              showToast('success', `Valores importados com sucesso! ${result.atualizados} registros atualizados.`);
          }

          // Reload records and activities
          await loadRecords();
          await loadActivities();

          // Reset form
          document.getElementById('preview-container').classList.add('d-none');
          document.getElementById('values-file').value = '';
          excelData = null;

      } catch (error) {
          console.error('Erro na importação:', error);
          showToast('error', error.message || 'Erro ao importar os valores. Por favor, tente novamente.');
      } finally {
          if (loadingToast) {
              loadingToast.close();
          }
      }
    });

    // Event listener para cancelar importação
    document.getElementById('cancel-import').addEventListener('click', () => {
      document.getElementById('preview-container').classList.add('d-none');
      document.getElementById('values-file').value = '';
      excelData = null;
    });

    // Função para mostrar prévia dos dados
    function showPreview(data, headers) {
        const previewContainer = document.getElementById('preview-container');
        const previewHeader = document.getElementById('preview-header');
        const previewBody = document.getElementById('preview-body');

        // Limpar conteúdo anterior
        previewHeader.innerHTML = '';
        previewBody.innerHTML = '';

        // Adicionar cabeçalhos
        const headerRow = document.createElement('tr');
        headers.forEach(header => {
            const th = document.createElement('th');
            th.textContent = header;
            headerRow.appendChild(th);
        });
        previewHeader.appendChild(headerRow);

        // Adicionar dados
        data.forEach(row => {
            const tr = document.createElement('tr');
            headers.forEach(header => {
                const td = document.createElement('td');
                const value = row[header.toLowerCase().replace(' ', '_')];
                td.textContent = value || '-';
                tr.appendChild(td);
            });
            previewBody.appendChild(tr);
        });

        // Mostrar container
        previewContainer.classList.remove('d-none');
    }
  }

  // Iniciar verificação de autenticação ao carregar a página
  checkAuth();
});
