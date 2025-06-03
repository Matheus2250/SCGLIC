<?php
require_once 'config.php';
require_once 'functions.php';

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: selecao_modulos.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema CGLIC</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Elementos de fundo decorativos */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: drift 20s linear infinite;
            pointer-events: none;
        }

        @keyframes drift {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        /* Container principal */
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 50px 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header */
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-container {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 32px;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
        }

        .login-title {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 16px;
            color: #7f8c8d;
            font-weight: 500;
        }

        /* Tabs */
        .tabs-container {
            display: flex;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 30px;
            position: relative;
        }

        .tab-button {
            flex: 1;
            padding: 12px 20px;
            background: none;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .tab-button.active {
            color: #2c3e50;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Formulários */
        .form-container {
            position: relative;
        }

        .form-section {
            display: none;
            animation: fadeIn 0.4s ease-in-out;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .input-container {
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-1px);
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: color 0.3s ease;
        }

        .form-group input:focus + .input-icon {
            color: #3498db;
        }

        /* Botões */
        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* Links de navegação */
        .form-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .form-footer p {
            color: #6b7280;
            font-size: 14px;
            margin: 0;
        }

        .form-footer a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .form-footer a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        /* Mensagens */
        .mensagem {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem.sucesso {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .mensagem.erro {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Loading */
        .loading {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e5e7eb;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Footer */
        .login-footer {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        /* Responsivo */
        @media (max-width: 640px) {
            .login-container {
                padding: 40px 30px;
                margin: 20px;
                border-radius: 20px;
            }

            .login-title {
                font-size: 28px;
            }

            .login-subtitle {
                font-size: 14px;
            }

            .form-group input {
                padding: 14px 18px 14px 45px;
                font-size: 16px; /* Evita zoom no iOS */
            }

            .input-icon {
                left: 14px;
            }

            .btn-primary {
                padding: 14px;
                font-size: 15px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .login-container {
                padding: 30px 25px;
            }

            .logo-container {
                width: 70px;
                height: 70px;
                font-size: 28px;
            }

            .tabs-container {
                flex-direction: column;
                gap: 4px;
            }
        }

        /* Validação visual */
        .form-group.error input {
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .form-group.success input {
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }

        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Efeitos especiais */
        .form-group input::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        .btn-primary .lucide {
            transition: transform 0.3s ease;
        }

        .btn-primary:hover .lucide {
            transform: translateX(2px);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="logo-container">
                <i data-lucide="shield-check"></i>
            </div>
            <h1 class="login-title">Sistema CGLIC</h1>
            <p class="login-subtitle">Coordenação Geral de Licitações</p>
        </div>

        <!-- Mensagens -->
        <?php echo getMensagem(); ?>

        <!-- Tabs -->
        <div class="tabs-container">
            <button class="tab-button active" onclick="switchTab('login')">
                Entrar
            </button>
            <button class="tab-button" onclick="switchTab('cadastro')">
                Cadastrar
            </button>
        </div>

        <!-- Container dos Formulários -->
        <div class="form-container">
            <!-- Loading -->
            <div class="loading" id="loading">
                <div class="loading-spinner"></div>
                <p style="margin: 0; text-align: center; color: #6b7280;">Processando...</p>
            </div>

            <!-- Formulário de Login -->
            <div id="form-login" class="form-section active">
                <form action="process.php" method="POST" onsubmit="showLoading()">
                    <input type="hidden" name="acao" value="login">
                    
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <div class="input-container">
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="seu@email.com"
                                required
                                autocomplete="email"
                            >
                            <i class="input-icon" data-lucide="mail"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <div class="input-container">
                            <input 
                                type="password" 
                                id="senha" 
                                name="senha" 
                                placeholder="Sua senha"
                                required
                                autocomplete="current-password"
                            >
                            <i class="input-icon" data-lucide="lock"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i data-lucide="log-in"></i>
                        Entrar no Sistema
                    </button>
                </form>
                
                <div class="form-footer">
                    <p>
                        Não tem cadastro? 
                        <a href="#" onclick="switchTab('cadastro')">Cadastre-se aqui</a>
                    </p>
                </div>
            </div>
            
            <!-- Formulário de Cadastro -->
            <div id="form-cadastro" class="form-section">
                <form action="process.php" method="POST" onsubmit="showLoading(); return validateCadastro()">
                    <input type="hidden" name="acao" value="cadastro">
                    
                    <div class="form-group">
                        <label for="nome_cadastro">Nome Completo</label>
                        <div class="input-container">
                            <input 
                                type="text" 
                                id="nome_cadastro" 
                                name="nome" 
                                placeholder="Seu nome completo"
                                required
                                autocomplete="name"
                            >
                            <i class="input-icon" data-lucide="user"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_cadastro">E-mail</label>
                        <div class="input-container">
                            <input 
                                type="email" 
                                id="email_cadastro" 
                                name="email" 
                                placeholder="seu@email.com"
                                required
                                autocomplete="email"
                            >
                            <i class="input-icon" data-lucide="mail"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="senha_cadastro">Senha</label>
                        <div class="input-container">
                            <input 
                                type="password" 
                                id="senha_cadastro" 
                                name="senha" 
                                placeholder="Mínimo 6 caracteres"
                                required
                                autocomplete="new-password"
                            >
                            <i class="input-icon" data-lucide="lock"></i>
                        </div>
                        <div class="error-message" id="senha-error" style="display: none;">
                            <i data-lucide="alert-circle" style="width: 12px; height: 12px;"></i>
                            A senha deve ter pelo menos 6 caracteres
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Senha</label>
                        <div class="input-container">
                            <input 
                                type="password" 
                                id="confirmar_senha" 
                                name="confirmar_senha" 
                                placeholder="Confirme sua senha"
                                required
                                autocomplete="new-password"
                            >
                            <i class="input-icon" data-lucide="shield-check"></i>
                        </div>
                        <div class="error-message" id="confirmar-error" style="display: none;">
                            <i data-lucide="alert-circle" style="width: 12px; height: 12px;"></i>
                            As senhas não coincidem
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i data-lucide="user-plus"></i>
                        Criar Conta
                    </button>
                </form>
                
                <div class="form-footer">
                    <p>
                        Já tem cadastro? 
                        <a href="#" onclick="switchTab('login')">Faça login aqui</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="login-footer">
        <p>&copy; <?php echo date('Y'); ?> Sistema CGLIC - Ministério da Saúde</p>
    </div>

    <script>
        // Inicializar Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        // Alternar entre abas
        function switchTab(tab) {
            // Remover classe ativa dos botões
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Esconder todos os formulários
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Ativar aba selecionada
            if (tab === 'login') {
                document.querySelector('.tab-button:first-child').classList.add('active');
                document.getElementById('form-login').classList.add('active');
            } else {
                document.querySelector('.tab-button:last-child').classList.add('active');
                document.getElementById('form-cadastro').classList.add('active');
            }
            
            // Recriar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Mostrar loading
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }

        // Validação do formulário de cadastro
        function validateCadastro() {
            const senha = document.getElementById('senha_cadastro').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            const senhaError = document.getElementById('senha-error');
            const confirmarError = document.getElementById('confirmar-error');
            
            let isValid = true;
            
            // Validar senha
            if (senha.length < 6) {
                senhaError.style.display = 'flex';
                document.getElementById('senha_cadastro').parentNode.parentNode.classList.add('error');
                isValid = false;
            } else {
                senhaError.style.display = 'none';
                document.getElementById('senha_cadastro').parentNode.parentNode.classList.remove('error');
                document.getElementById('senha_cadastro').parentNode.parentNode.classList.add('success');
            }
            
            // Validar confirmação
            if (senha !== confirmarSenha) {
                confirmarError.style.display = 'flex';
                document.getElementById('confirmar_senha').parentNode.parentNode.classList.add('error');
                isValid = false;
            } else if (confirmarSenha.length > 0) {
                confirmarError.style.display = 'none';
                document.getElementById('confirmar_senha').parentNode.parentNode.classList.remove('error');
                document.getElementById('confirmar_senha').parentNode.parentNode.classList.add('success');
            }
            
            if (!isValid) {
                document.getElementById('loading').style.display = 'none';
            }
            
            return isValid;
        }

        // Validação em tempo real
        document.addEventListener('DOMContentLoaded', function() {
            const senhaInput = document.getElementById('senha_cadastro');
            const confirmarInput = document.getElementById('confirmar_senha');
            
            if (senhaInput) {
                senhaInput.addEventListener('input', function() {
                    if (this.value.length >= 6) {
                        this.parentNode.parentNode.classList.remove('error');
                        this.parentNode.parentNode.classList.add('success');
                        document.getElementById('senha-error').style.display = 'none';
                    } else {
                        this.parentNode.parentNode.classList.remove('success');
                    }
                });
            }
            
            if (confirmarInput) {
                confirmarInput.addEventListener('input', function() {
                    const senha = document.getElementById('senha_cadastro').value;
                    if (this.value === senha && this.value.length > 0) {
                        this.parentNode.parentNode.classList.remove('error');
                        this.parentNode.parentNode.classList.add('success');
                        document.getElementById('confirmar-error').style.display = 'none';
                    } else {
                        this.parentNode.parentNode.classList.remove('success');
                    }
                });
            }
        });

        // Auto-fechar mensagens após 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const mensagens = document.querySelectorAll('.mensagem');
            mensagens.forEach(function(msg) {
                setTimeout(function() {
                    msg.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    msg.style.opacity = '0';
                    msg.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        msg.remove();
                    }, 500);
                }, 5000);
            });
        });

        // Escape key para fechar loading (se necessário)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('loading').style.display = 'none';
            }
        });
    </script>
</body>
</html>