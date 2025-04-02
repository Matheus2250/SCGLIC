// main.js - Script principal do sistema
document.addEventListener("DOMContentLoaded", function () {
  // Configurações da API
  const API_URL = "http://localhost:3000/api";
  let token = localStorage.getItem("token");
  let currentUser = JSON.parse(localStorage.getItem("currentUser"));
  let currentRegistroId = null;

  // Elementos do DOM
  const loginPage = document.getElementById("login-page");
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

  // Mensagens de erro
  function showError(message) {
    const errorElement = document.getElementById("login-error");
    if (errorElement) {
      errorElement.textContent = message;
      errorElement.style.display = "block";
    }
  }

  // Verificar autenticação
  function checkAuth() {
    if (token && currentUser) {
      showMainApp();

      // Verificar se é admin para exibir menu de usuários
      if (currentUser.nivel_acesso === "admin") {
        adminMenu.classList.remove("d-none");
      }

      // Carregar a página inicial (dashboard)
      loadPage("dashboard");
    } else {
      showLoginPage();
    }
  }

  // Mostrar página de login
  function showLoginPage() {
    loginPage.classList.remove("d-none");
    mainApp.classList.add("d-none");
  }

  // Mostrar aplicação principal
  function showMainApp() {
    loginPage.classList.add("d-none");
    mainApp.classList.remove("d-none");
  }

  // Carregador de páginas
  async function loadPage(pageName) {
    try {
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
    } catch (error) {
      console.error("Erro ao carregar página:", error);
      pageContainer.innerHTML = `
        <div class="alert alert-danger">
          <h4>Erro ao carregar a página</h4>
          <p>${error.message}</p>
        </div>
      `;
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

  // Links não funcionais
  document
    .querySelector(".forgot-password")
    ?.addEventListener("click", function (e) {
      e.preventDefault();
      showError("Funcionalidade não implementada nesta versão.");
    });

  document
    .getElementById("signup-link")
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
      })
      .catch((error) => {
        if (dashboardLoading) dashboardLoading.classList.add("d-none");
        console.error("Erro:", error);
      });
  }

  // Inicializar página de registros
  function initRegistros() {
    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");
    const registrosTable = document.getElementById("registros-table");
    const aplicarFiltros = document.getElementById("aplicar-filtros");

    // Inicialmente ocultar conteúdo e mostrar loading
    if (registrosLoading) registrosLoading.classList.remove("d-none");
    if (registrosContainer) registrosContainer.classList.add("d-none");
    if (nenhumRegistro) nenhumRegistro.classList.add("d-none");

    // Carregar opções para filtros
    carregarOpcoesFiltragem();

    // Carregar registros
    carregarRegistros();

    // Event listener para aplicar filtros
    if (aplicarFiltros) {
      aplicarFiltros.addEventListener("click", function () {
        carregarRegistrosFiltrados();
      });
    }
  }

  // Carregar registros
  function carregarRegistros() {
    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");
    const registrosTable = document.getElementById("registros-table");

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

        if (registrosContainer) registrosContainer.classList.remove("d-none");
        if (registrosTable) {
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

          // Event listeners para botões de ação
          document.querySelectorAll(".visualizar-btn").forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              carregarDetalhesRegistro(id);
            });
          });

          document.querySelectorAll(".editar-btn").forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              carregarRegistroParaEdicao(id);
            });
          });

          document.querySelectorAll(".excluir-btn").forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              confirmarExclusao(id);
            });
          });
        }
      })
      .catch((error) => {
        if (registrosLoading) registrosLoading.classList.add("d-none");
        console.error("Erro:", error);
      });
  }

  // Carregar opções de filtragem
  function carregarOpcoesFiltragem() {
    const filtroModalidade = document.getElementById("filtro-modalidade");
    const filtroSituacao = document.getElementById("filtro-situacao");
    const filtroAno = document.getElementById("filtro-ano");

    if (!filtroModalidade && !filtroSituacao && !filtroAno) return;

    fetch(`${API_URL}/registros`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => response.json())
      .then((data) => {
        // Modalidades únicas
        if (filtroModalidade) {
          const modalidades = [
            ...new Set(data.map((item) => item.modalidade).filter(Boolean)),
          ];
          filtroModalidade.innerHTML = '<option value="">Todas</option>';
          modalidades.forEach((modalidade) => {
            filtroModalidade.innerHTML += `<option value="${modalidade}">${modalidade}</option>`;
          });
        }

        // Situações únicas
        if (filtroSituacao) {
          const situacoes = [
            ...new Set(data.map((item) => item.situacao).filter(Boolean)),
          ];
          filtroSituacao.innerHTML = '<option value="">Todas</option>';
          situacoes.forEach((situacao) => {
            filtroSituacao.innerHTML += `<option value="${situacao}">${situacao}</option>`;
          });
        }

        // Anos únicos
        if (filtroAno) {
          const anos = [
            ...new Set(data.map((item) => item.ano).filter(Boolean)),
          ];
          filtroAno.innerHTML = '<option value="">Todos</option>';
          anos
            .sort((a, b) => b - a)
            .forEach((ano) => {
              filtroAno.innerHTML += `<option value="${ano}">${ano}</option>`;
            });
        }
      })
      .catch((error) => {
        console.error("Erro ao carregar opções de filtros:", error);
      });
  }

  // Carregar registros com filtros aplicados
  function carregarRegistrosFiltrados() {
    const filtroModalidade =
      document.getElementById("filtro-modalidade")?.value;
    const filtroSituacao = document.getElementById("filtro-situacao")?.value;
    const filtroAno = document.getElementById("filtro-ano")?.value;
    const filtroTexto = document
      .getElementById("filtro-texto")
      ?.value.toLowerCase();

    const registrosLoading = document.getElementById("registros-loading");
    const registrosContainer = document.getElementById("registros-container");
    const nenhumRegistro = document.getElementById("nenhum-registro");
    const registrosTable = document.getElementById("registros-table");

    if (registrosLoading) registrosLoading.classList.remove("d-none");
    if (registrosContainer) registrosContainer.classList.add("d-none");
    if (nenhumRegistro) nenhumRegistro.classList.add("d-none");

    fetch(`${API_URL}/registros`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => response.json())
      .then((data) => {
        if (registrosLoading) registrosLoading.classList.add("d-none");

        // Aplicar filtros
        let filteredData = data;

        if (filtroModalidade) {
          filteredData = filteredData.filter(
            (r) => r.modalidade === filtroModalidade
          );
        }

        if (filtroSituacao) {
          filteredData = filteredData.filter(
            (r) => r.situacao === filtroSituacao
          );
        }

        if (filtroAno) {
          filteredData = filteredData.filter((r) => r.ano === filtroAno);
        }

        if (filtroTexto) {
          filteredData = filteredData.filter(
            (r) =>
              (r.nup && r.nup.toLowerCase().includes(filtroTexto)) ||
              (r.objeto && r.objeto.toLowerCase().includes(filtroTexto))
          );
        }

        if (filteredData.length === 0) {
          if (nenhumRegistro) nenhumRegistro.classList.remove("d-none");
          return;
        }

        if (registrosContainer) registrosContainer.classList.remove("d-none");
        if (registrosTable) {
          registrosTable.innerHTML = "";

          filteredData.forEach((registro) => {
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

          // Event listeners para botões de ação
          document.querySelectorAll(".visualizar-btn").forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              carregarDetalhesRegistro(id);
            });
          });

          document.querySelectorAll(".editar-btn").forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              carregarRegistroParaEdicao(id);
            });
          });

          document.querySelectorAll(".excluir-btn").forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              confirmarExclusao(id);
            });
          });
        }
      })
      .catch((error) => {
        if (registrosLoading) registrosLoading.classList.add("d-none");
        console.error("Erro ao aplicar filtros:", error);
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
    // Primeiro carregar a página de novo registro
    loadPage("novo-registro").then(() => {
      // Depois carregar os dados do registro para o formulário
      fetch(`${API_URL}/registros/${id}`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
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
          document.getElementById("modalidade").value =
            registro.modalidade || "";
          document.getElementById("tipo").value = registro.tipo || "";
          document.getElementById("numero").value = registro.numero || "";
          document.getElementById("ano").value = registro.ano || "";
          document.getElementById("prioridade").value =
            registro.prioridade || "";
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
          document.getElementById("andamentos").value =
            registro.andamentos || "";
          document.getElementById("valor_homologado").value =
            registro.valor_homologado || "";
          document.getElementById("economia").value = registro.economia || "";
          document.getElementById("dt_homologacao").value =
            registro.dt_homologacao || "";
        })
        .catch((error) => {
          console.error("Erro ao carregar registro para edição:", error);
        });
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
    if (currentUser.nivel_acesso !== "admin") {
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
