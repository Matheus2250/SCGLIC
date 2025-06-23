<?php
// Garante que BASE_URL esteja definido
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://10.1.41.251:8080/sistema_licitacoes/public/');
}

// Carrega as funções de autenticação
require_once __DIR__ . '/../../helpers/auth.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?? 'Sistema de Licitações' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS + Icons -->
    <link href="<?= BASE_URL ?>css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="<?= BASE_URL ?>js/bootstrap.bundle.min.js"></script>

    <style>
        body { min-height: 100vh; margin: 0; }
        /* -------- Sidebar -------- */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: 220px; height: 100vh;
            background-color: #343a40; color: #fff;
            padding-top: 60px;
        }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .sidebar a {
            color: #fff; text-decoration: none;
            padding: 12px 20px; display: flex; align-items: center;
        }
        .sidebar a:hover { background: #495057; }
        .sidebar i { margin-right: .5rem; }

        /* -------- Topbar -------- */
        .topbar {
            position: fixed; top: 0; left: 220px; right: 0;
            height: 60px; background: #f8f9fa; border-bottom: 1px solid #ddd;
            display: flex; align-items: center; padding: 0 20px; z-index: 1000;
        }

        /* -------- Conteúdo -------- */
        .content { margin-left: 220px; margin-top: 60px; padding: 20px; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <nav class="sidebar">
        <h5 class="text-center mb-3">Menu</h5>
        <ul>
            <li><a href="<?= BASE_URL ?>home/index"><i class="bi bi-house"></i> Início</a></li>
            
            <?php if (podeGerenciarUsuarios()): ?>
                <li><a href="<?= BASE_URL ?>usuarios/index"><i class="bi bi-people"></i> Usuários</a></li>
            <?php endif; ?>
            
            <li><a href="<?= BASE_URL ?>planejamento/index"><i class="bi bi-journal-check"></i> Planejamento</a></li>
            <li><a href="<?= BASE_URL ?>licitacoes/index"><i class="bi bi-file-earmark-text"></i> Licitações</a></li>
            <li><a href="<?= BASE_URL ?>contratos/index"><i class="bi bi-file-earmark"></i> Contratos</a></li>
            
            <!-- Separador para relatórios -->
            <li style="border-top: 1px solid #495057; margin: 10px 0;"></li>
            <li><a href="<?= BASE_URL ?>licitacoes/relatorios"><i class="bi bi-graph-up"></i> Relatórios</a></li>
            
            <li><a href="<?= BASE_URL ?>usuarios/logout"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
        </ul>
    </nav>

    <!-- Topbar -->
    <header class="topbar">
        <div class="me-auto fw-bold">Sistema de Licitações</div>
        <?php if (!empty($_SESSION['usuario'])): ?>
            <?php
            $nivel = getNivelUsuario();
            $nomeNivel = getNomeNivel($nivel);
            $badgeClass = match($nivel) {
                1 => 'bg-danger',
                2 => 'bg-success', 
                3 => 'bg-primary',
                4 => 'bg-secondary',
                default => 'bg-secondary'
            };
            ?>
            <div class="d-flex align-items-center gap-3">
                <span class="badge <?= $badgeClass ?>"><?= $nomeNivel ?></span>
                <span>Olá, <?= htmlspecialchars($_SESSION['usuario']['nome']) ?></span>
            </div>
        <?php endif; ?>
    </header>

    <!-- Conteúdo da página -->
    <main class="content">
        <?php require_once $viewPath; ?>
    </main>

</body>
</html>
