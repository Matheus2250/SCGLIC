<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// Buscar estatísticas rápidas para os cards
$stats_planejamento = $pdo->query("
    SELECT 
        COUNT(DISTINCT numero_dfd) as total_dfds,
        SUM(valor_total_contratacao) as valor_total,
        COUNT(DISTINCT CASE WHEN situacao_execucao = 'Não iniciado' THEN numero_dfd END) as pendentes
    FROM pca_dados 
    WHERE numero_dfd IS NOT NULL
")->fetch();

$stats_licitacao = $pdo->query("
    SELECT 
        COUNT(*) as total_licitacoes,
        COUNT(CASE WHEN situacao = 'EM_ANDAMENTO' THEN 1 END) as em_andamento,
        COUNT(CASE WHEN situacao = 'HOMOLOGADO' THEN 1 END) as homologadas
    FROM licitacoes
")->fetch();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema CGLIC - Seleção de Módulos</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .selecao-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .selecao-content {
            max-width: 1000px;
            width: 100%;
            text-align: center;
        }

        .header-selecao {
            color: white;
            margin-bottom: 50px;
        }

        .header-selecao h1 {
            font-size: 48px;
            margin: 0 0 10px 0;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .header-selecao p {
            font-size: 20px;
            opacity: 0.9;
            margin: 0;
        }

        .modulos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .modulo-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .modulo-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
        }

        .modulo-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            border-radius: 20px 20px 0 0;
        }

        .modulo-planejamento::before {
            background: linear-gradient(90deg, #3498db, #2980b9);
        }

        .modulo-licitacao::before {
            background: linear-gradient(90deg, #e74c3c, #c0392b);
        }

        .modulo-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            margin-bottom: 25px;
        }

        .modulo-planejamento .modulo-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .modulo-licitacao .modulo-icon {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .modulo-title {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin: 0 0 15px 0;
        }

        .modulo-description {
            font-size: 16px;
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .modulo-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #ecf0f1;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #95a5a6;
            text-transform: uppercase;
            font-weight: 600;
        }

        .usuario-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .usuario-dados {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .usuario-avatar {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
        }

        .btn-logout {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .header-selecao h1 {
                font-size: 36px;
            }

            .header-selecao p {
                font-size: 16px;
            }

            .modulos-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .modulo-card {
                padding: 30px 20px;
            }

            .modulo-stats {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .usuario-info {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .usuario-dados {
                flex-direction: column;
                gap: 10px;
            }
        }

        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 20px 40px;
            border-radius: 10px;
            font-size: 16px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="selecao-container">
        <div class="selecao-content">
            <!-- Header -->
            <div class="header-selecao">
                <h1><i data-lucide="library-big"></i> Sistema CGLIC</h1>
                <p>Coordenação Geral de Licitações - Selecione o módulo desejado</p>
            </div>

            <!-- Módulos -->
            <div class="modulos-grid">
                <!-- Módulo Planejamento -->
                <div class="modulo-card modulo-planejamento" onclick="acessarModulo('planejamento')">
                    <div class="modulo-icon">
                        <i data-lucide="calendar-check"></i>
                    </div>
                    <h2 class="modulo-title">Planejamento</h2>
                    <p class="modulo-description">
                        Gerencie o Plano de Contratações Anual (PCA), controle DFDs, 
                        acompanhe cronogramas e monitore o andamento das contratações planejadas.
                    </p>
                    <div class="modulo-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($stats_planejamento['total_dfds'] ?? 0); ?></span>
                            <span class="stat-label">DFDs Cadastrados</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats_planejamento['pendentes'] ?? 0; ?></span>
                            <span class="stat-label">Pendentes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo abreviarValor($stats_planejamento['valor_total'] ?? 0); ?></span>
                            <span class="stat-label">Valor Total</span>
                        </div>
                    </div>
                </div>

                <!-- Módulo Licitação -->
                <div class="modulo-card modulo-licitacao" onclick="acessarModulo('licitacao')">
                    <div class="modulo-icon">
                        <i data-lucide="gavel"></i>
                    </div>
                    <h2 class="modulo-title">Licitação</h2>
                    <p class="modulo-description">
                        Controle o processo licitatório, acompanhe pregões, gerencie contratos 
                        e monitore o andamento das licitações em todas as suas fases.
                    </p>
                    <div class="modulo-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($stats_licitacao['total_licitacoes'] ?? 0); ?></span>
                            <span class="stat-label">Total Licitações</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats_licitacao['em_andamento'] ?? 0; ?></span>
                            <span class="stat-label">Em Andamento</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats_licitacao['homologadas'] ?? 0; ?></span>
                            <span class="stat-label">Homologadas</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informações do Usuário -->
            <div class="usuario-info">
                <div class="usuario-dados">
                    <div class="usuario-avatar">
                        <?php echo strtoupper(substr($_SESSION['usuario_nome'], 0, 1)); ?>
                    </div>
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong><br>
                        <small style="opacity: 0.8;"><?php echo htmlspecialchars($_SESSION['usuario_email']); ?></small>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">
                    <i data-lucide="log-out"></i> Sair
                </a>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div class="loading" id="loading">
        <i data-lucide="loader-2"></i> Carregando módulo...
    </div>

    <script>
        function acessarModulo(modulo) {
            // Mostrar loading
            document.getElementById('loading').style.display = 'block';
            
            // Simular um pequeno delay para melhor UX
            setTimeout(() => {
                if (modulo === 'planejamento') {
                    window.location.href = 'dashboard.php';
                } else if (modulo === 'licitacao') {
                    window.location.href = 'licitacao_dashboard.php';
                }
            }, 500);
        }

        // Carregar ícones Lucide
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
                
                // Animar loading icon
                const loadingIcon = document.querySelector('#loading .lucide-loader-2');
                if (loadingIcon) {
                    loadingIcon.style.animation = 'spin 1s linear infinite';
                }
            }
        });

        // Adicionar animação de rotação para o ícone de loading
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>