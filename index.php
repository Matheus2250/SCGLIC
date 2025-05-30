<?php
require_once 'config.php';
require_once 'functions.php';

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Sistema de Informações CGLIC</h2>
        
        <?php echo getMensagem(); ?>
        
        <!-- Formulário de Login -->
        <div id="form-login">
            <form action="process.php" method="POST">
                <input type="hidden" name="acao" value="login">
                
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                
                <button type="submit" class="btn" style="width: 100%;">Entrar</button>
            </form>
            
            <p class="text-center mt-20">
                Não tem cadastro? 
                <a href="#" onclick="mostrarCadastro()">Cadastre-se aqui</a>
            </p>
        </div>
        
        <!-- Formulário de Cadastro -->
        <div id="form-cadastro" style="display: none;">
            <form action="process.php" method="POST">
                <input type="hidden" name="acao" value="cadastro">
                
                <div class="form-group">
                    <label for="nome_cadastro">Nome Completo</label>
                    <input type="text" id="nome_cadastro" name="nome" required>
                </div>
                
                <div class="form-group">
                    <label for="email_cadastro">E-mail</label>
                    <input type="email" id="email_cadastro" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="senha_cadastro">Senha</label>
                    <input type="password" id="senha_cadastro" name="senha" required>
                </div>
                
                <div class="form-group">
                    <label for="confirmar_senha">Confirmar Senha</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                </div>
                
                <button type="submit" class="btn" style="width: 100%;">Cadastrar</button>
            </form>
            
            <p class="text-center mt-20">
                Já tem cadastro? 
                <a href="#" onclick="mostrarLogin()">Faça login aqui</a>
            </p>
        </div>
    </div>
    
    <script>
    // Garantir que apenas um formulário apareça
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('form-cadastro').style.display = 'none';
    });
    
    function mostrarCadastro() {
        document.getElementById('form-login').style.display = 'none';
        document.getElementById('form-cadastro').style.display = 'block';
    }
    
    function mostrarLogin() {
        document.getElementById('form-cadastro').style.display = 'none';
        document.getElementById('form-login').style.display = 'block';
    }
</script>
</body>
</html>