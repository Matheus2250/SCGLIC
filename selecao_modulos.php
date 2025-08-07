<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Informações CGLIC - Seleção de Módulos</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .selecao-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #667eea 100%);
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .selecao-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 200, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .selecao-content {
            max-width: 1000px;
            width: 100%;
            text-align: center;
            position: relative;
            z-index: 1;
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
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .modulo-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px 30px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .modulo-card:hover {
            transform: translateY(-15px);
            box-shadow: 
                0 35px 70px rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
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
            background: linear-gradient(90deg, #1e3c72, #2a5298);
        }

        .modulo-licitacao::before {
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .modulo-qualificacao::before {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .modulo-contratos::before {
            background: linear-gradient(90deg, #dc2626, #b91c1c);
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
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            box-shadow: 
                0 10px 25px rgba(30, 60, 114, 0.3),
                0 0 0 4px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .modulo-licitacao .modulo-icon {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 
                0 10px 25px rgba(16, 185, 129, 0.3),
                0 0 0 4px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .modulo-qualificacao .modulo-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 
                0 10px 25px rgba(245, 158, 11, 0.3),
                0 0 0 4px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .modulo-contratos .modulo-icon {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            box-shadow: 
                0 10px 25px rgba(220, 38, 38, 0.3),
                0 0 0 4px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
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
                <h1><i data-lucide="library-big"></i> Sistema de Informações CGLIC</h1>
                <p>Coordenação Geral de Licitações e Contratos Administrativos - Selecione o módulo desejado</p>
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
                </div>

                <!-- Módulo Qualificação -->
                <div class="modulo-card modulo-qualificacao" onclick="acessarModulo('qualificacao')">
                    <div class="modulo-icon">
                        <i data-lucide="award"></i>
                    </div>
                    <h2 class="modulo-title">Qualificação</h2>
                    <p class="modulo-description">
                        Gerencie qualificações de fornecedores, avalie capacitação técnica 
                        e controle documentação para processos licitatórios.
                    </p>
                </div>

                <!-- Módulo Contratos -->
                <div class="modulo-card modulo-contratos" onclick="acessarModulo('contratos')">
                    <div class="modulo-icon">
                        <i data-lucide="file-text"></i>
                    </div>
                    <h2 class="modulo-title">Contratos</h2>
                    <p class="modulo-description">
                        Controle e gerencie contratos administrativos, acompanhe vigências, 
                        valores e documentação contratual.
                    </p>
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
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php if ($_SESSION['usuario_nivel'] == 1): ?>
                    <a href="gerenciar_usuarios.php" class="btn-logout" style="background: rgba(34, 197, 94, 0.2); border-color: rgba(34, 197, 94, 0.3);">
                        <i data-lucide="users"></i> Gerenciar Usuários
                    </a>
                    <a href="dashboard.php?secao=backup-sistema" class="btn-logout" style="background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.3);">
                        <i data-lucide="shield"></i> Backup & Segurança
                    </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-logout">
                        <i data-lucide="log-out"></i> Sair
                    </a>
                </div>
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
                } else if (modulo === 'qualificacao') {
                    window.location.href = 'qualificacao_dashboard.php';
                } else if (modulo === 'contratos') {
                    window.location.href = 'contratos_dashboard.php';
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