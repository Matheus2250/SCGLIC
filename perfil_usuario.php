<?php
/**
 * Página de Perfil do Usuário - Sistema CGLIC
 * 
 * CRIADO: 01/01/2025
 * FUNCIONALIDADES: Edição de perfil, configurações de tema, estatísticas de uso
 */

require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// Processar formulários
if ($_POST) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_POST['action']) {
            case 'update_personal':
                // Atualizar informações pessoais
                $nome = trim($_POST['nome']);
                $email = trim($_POST['email']);
                $departamento = trim($_POST['departamento']);
                
                if (empty($nome) || empty($email)) {
                    throw new Exception('Nome e email são obrigatórios');
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email inválido');
                }
                
                // Verificar se email já existe (exceto o próprio usuário)
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['usuario_id']]);
                if ($stmt->fetch()) {
                    throw new Exception('Email já está em uso por outro usuário');
                }
                
                // Atualizar dados
                $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, departamento = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $departamento, $_SESSION['usuario_id']]);
                
                // Atualizar sessão
                $_SESSION['usuario_nome'] = $nome;
                $_SESSION['usuario_email'] = $email;
                $_SESSION['usuario_departamento'] = $departamento;
                
                $response['success'] = true;
                $response['message'] = 'Informações atualizadas com sucesso!';
                break;
                
            case 'change_password':
                // Alterar senha
                $senha_atual = $_POST['senha_atual'];
                $nova_senha = $_POST['nova_senha'];
                $confirmar_senha = $_POST['confirmar_senha'];
                
                if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
                    throw new Exception('Todos os campos de senha são obrigatórios');
                }
                
                if ($nova_senha !== $confirmar_senha) {
                    throw new Exception('Nova senha e confirmação não coincidem');
                }
                
                if (strlen($nova_senha) < 6) {
                    throw new Exception('Nova senha deve ter pelo menos 6 caracteres');
                }
                
                // Verificar senha atual
                $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
                $stmt->execute([$_SESSION['usuario_id']]);
                $user = $stmt->fetch();
                
                if (!password_verify($senha_atual, $user['senha'])) {
                    throw new Exception('Senha atual incorreta');
                }
                
                // Atualizar senha
                $hash_nova_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmt->execute([$hash_nova_senha, $_SESSION['usuario_id']]);
                
                $response['success'] = true;
                $response['message'] = 'Senha alterada com sucesso!';
                break;
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    // Se for AJAX, retornar JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Se não for AJAX, definir mensagem para exibir na página
    if ($response['success']) {
        setMensagem($response['message'], 'success');
    } else {
        setMensagem($response['message'], 'error');
    }
    
    header('Location: perfil_usuario.php');
    exit;
}

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$userData = $stmt->fetch();

// Buscar estatísticas de uso com tratamento de erro
$userStats = ['total_licitacoes' => 0, 'total_importacoes_sistema' => 0, 'anos_sistema' => 0, 'nivel_nome' => 'Usuário'];

try {
    // Buscar licitações do usuário
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM licitacoes WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $result = $stmt->fetch();
    $userStats['total_licitacoes'] = $result['total'] ?? 0;
    
    // Buscar total de importações no sistema
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pca_importacoes");
    $stmt->execute();
    $result = $stmt->fetch();
    $userStats['total_importacoes_sistema'] = $result['total'] ?? 0;
    
} catch (Exception $e) {
    // Se houver erro, manter valores padrão
    error_log("Erro ao buscar estatísticas do usuário: " . $e->getMessage());
}

// Calcular estatísticas adicionais
$userStats['anos_sistema'] = $userData['criado_em'] ? date('Y') - date('Y', strtotime($userData['criado_em'])) : 0;
$userStats['nivel_nome'] = getNomeNivel($userData['nivel_acesso']);

// Buscar atividades recentes
$recentActivities = [];
try {
    $stmt = $pdo->prepare("
        SELECT 'Licitação criada' as acao, objeto as detalhes, criado_em as data_acao 
        FROM licitacoes 
        WHERE usuario_id = ? 
        ORDER BY criado_em DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $recentActivities = $stmt->fetchAll();
} catch (Exception $e) {
    // Se houver erro, continuar sem atividades
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/dark-mode.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            background-color: var(--bg-primary);
            min-height: 100vh;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 50%;
            height: 200%;
            background: rgba(255,255,255,0.05);
            transform: rotate(35deg);
        }
        
        .header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: white;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .profile-details h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
            color: white !important;
        }
        
        .profile-details p {
            margin: 0 0 4px 0;
            opacity: 0.9;
            font-size: 16px;
        }
        
        .profile-badge {
            background: rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn-voltar {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            color: white;
            padding: 12px 24px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-voltar:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .profile-tabs {
            display: flex;
            background: var(--bg-card);
            border-radius: 12px;
            padding: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px var(--shadow-card);
            gap: 8px;
            overflow-x: auto;
        }
        
        .tab-btn {
            flex: 1;
            min-width: 150px;
            padding: 15px 20px;
            background: transparent;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        
        .tab-btn:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .tab-btn.active {
            background: #007bff;
            color: white;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-section {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-card);
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            margin: 0 0 20px 0;
            color: var(--text-primary);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-input);
            border-radius: 8px;
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .form-group input:disabled {
            background: var(--bg-secondary);
            color: var(--text-muted);
            cursor: not-allowed;
        }
        
        /* Estilos para botões de formulário */
        .btn-primary, .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 140px;
            justify-content: center;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }
        
        .btn-primary:disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
            transform: none;
            box-shadow: none;
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 2px solid var(--border-input);
        }
        
        .btn-secondary:hover {
            background: var(--border-input);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-secondary:active {
            transform: translateY(0);
            box-shadow: none;
        }
        
        /* Dark mode adjustments for buttons */
        [data-theme="dark"] .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-input);
        }
        
        [data-theme="dark"] .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--text-secondary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-card);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #0056b3);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(0, 123, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: #007bff;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .theme-section {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-card);
        }
        
        .theme-toggle-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .theme-info {
            flex: 1;
        }
        
        .theme-info h4 {
            margin: 0 0 8px 0;
            color: var(--text-primary);
            font-size: 16px;
        }
        
        .theme-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .theme-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #ddd;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        [data-theme="dark"] .theme-switch {
            background: #007bff;
        }
        
        .theme-switch::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        [data-theme="dark"] .theme-switch::before {
            transform: translateX(30px);
        }
        
        .activities-list {
            background: var(--bg-card);
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-card);
            overflow: hidden;
        }
        
        .activity-item {
            padding: 20px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: rgba(0, 123, 255, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #007bff;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-action {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .activity-details {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .activity-time {
            color: var(--text-muted);
            font-size: 12px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .profile-container {
                padding: 20px;
            }
            
            .profile-header {
                padding: 30px 20px;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .profile-tabs {
                flex-direction: column;
                gap: 8px;
            }
            
            .tab-btn {
                min-width: auto;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Header da Página -->
        <div class="profile-header">
            <div class="header-content">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($userData['nome'], 0, 1)); ?>
                    </div>
                    <div class="profile-details">
                        <h1><?php echo htmlspecialchars($userData['nome']); ?></h1>
                        <p><?php echo htmlspecialchars($userData['email']); ?></p>
                        <p><?php echo htmlspecialchars($userData['departamento'] ?? 'Sem departamento'); ?></p>
                        <span class="profile-badge">
                            <?php echo getNomeNivel($userData['nivel_acesso']); ?>
                        </span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="selecao_modulos.php" class="btn-voltar">
                        <i data-lucide="arrow-left"></i> Voltar ao Menu
                    </a>
                </div>
            </div>
        </div>

        <?php echo getMensagem(); ?>

        <!-- Tabs de Navegação -->
        <div class="profile-tabs">
            <button class="tab-btn active" onclick="showTab('personal')" data-tab="personal">
                <i data-lucide="user"></i>
                <span>Informações Pessoais</span>
            </button>
            <button class="tab-btn" onclick="showTab('security')" data-tab="security">
                <i data-lucide="shield"></i>
                <span>Segurança</span>
            </button>
            <button class="tab-btn" onclick="showTab('preferences')" data-tab="preferences">
                <i data-lucide="settings"></i>
                <span>Preferências</span>
            </button>
            <button class="tab-btn" onclick="showTab('stats')" data-tab="stats">
                <i data-lucide="bar-chart-3"></i>
                <span>Estatísticas</span>
            </button>
        </div>

        <!-- Conteúdo das Abas -->
        
        <!-- Aba: Informações Pessoais -->
        <div id="tab-personal" class="tab-content active">
            <div class="form-section">
                <h3><i data-lucide="user"></i> Informações Pessoais</h3>
                <form id="form-personal" method="POST">
                    <input type="hidden" name="action" value="update_personal">
                    <?php echo getCSRFInput(); ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome">Nome Completo *</label>
                            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($userData['nome']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="departamento">Departamento</label>
                            <input type="text" id="departamento" name="departamento" value="<?php echo htmlspecialchars($userData['departamento'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="nivel_acesso">Nível de Acesso</label>
                            <input type="text" id="nivel_acesso" value="<?php echo getNomeNivel($userData['nivel_acesso']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="criado_em">Membro desde</label>
                            <input type="text" id="criado_em" value="<?php echo formatarData($userData['criado_em']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="ultimo_login">Último login</label>
                            <input type="text" id="ultimo_login" value="<?php echo $userData['ultimo_login'] ? formatarDataHora($userData['ultimo_login']) : 'Primeiro acesso'; ?>" disabled>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                        <button type="reset" class="btn-secondary">
                            <i data-lucide="refresh-cw"></i> Restaurar
                        </button>
                        <button type="submit" class="btn-primary">
                            <i data-lucide="save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Aba: Segurança -->
        <div id="tab-security" class="tab-content">
            <div class="form-section">
                <h3><i data-lucide="shield"></i> Alterar Senha</h3>
                <form id="form-security" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <?php echo getCSRFInput(); ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="senha_atual">Senha Atual *</label>
                            <input type="password" id="senha_atual" name="senha_atual" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nova_senha">Nova Senha *</label>
                            <input type="password" id="nova_senha" name="nova_senha" required minlength="6">
                            <small style="color: var(--text-secondary); font-size: 12px;">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar Nova Senha *</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                        <button type="reset" class="btn-secondary">
                            <i data-lucide="x"></i> Cancelar
                        </button>
                        <button type="submit" class="btn-primary">
                            <i data-lucide="lock"></i> Alterar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Aba: Preferências -->
        <div id="tab-preferences" class="tab-content">
            <div class="theme-section">
                <h3><i data-lucide="palette"></i> Aparência</h3>
                
                <div class="theme-toggle-container">
                    <div class="theme-info">
                        <h4>Modo Escuro</h4>
                        <p>Reduz o cansaço visual em ambientes com pouca luz</p>
                    </div>
                    <div class="theme-switch" id="theme-toggle-switch" onclick="toggleTheme()"></div>
                </div>
                
                <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px; margin-top: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: var(--text-primary);">
                        <i data-lucide="keyboard"></i> Atalho de Teclado
                    </h4>
                    <p style="margin: 0; color: var(--text-secondary);">
                        Use <kbd style="background: var(--bg-card); padding: 4px 8px; border-radius: 4px; font-family: monospace;">Ctrl + Shift + D</kbd> 
                        para alternar rapidamente entre os modos
                    </p>
                </div>
                
                <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px; margin-top: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: var(--text-primary);">
                        <i data-lucide="info"></i> Sobre os Temas
                    </h4>
                    <p style="margin: 0 0 10px 0; color: var(--text-secondary); font-size: 14px;">
                        <strong>Modo Claro:</strong> Interface padrão com fundo branco, ideal para ambientes bem iluminados.
                    </p>
                    <p style="margin: 0; color: var(--text-secondary); font-size: 14px;">
                        <strong>Modo Escuro:</strong> Interface com fundo escuro que reduz o cansaço visual durante uso prolongado.
                    </p>
                </div>
                
                <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px; margin-top: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: var(--text-primary);">
                        <i data-lucide="layout-dashboard"></i> Configurações do Dashboard
                    </h4>
                    <p style="margin: 0 0 15px 0; color: var(--text-secondary); font-size: 14px;">
                        Personalize widgets, densidade da interface e outras configurações do dashboard.
                    </p>
                    <a href="dashboard_config.php" class="btn-primary" style="display: inline-flex; text-decoration: none;">
                        <i data-lucide="settings"></i> Configurar Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Aba: Estatísticas -->
        <div id="tab-stats" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="gavel"></i>
                    </div>
                    <div class="stat-number"><?php echo $userStats['total_licitacoes'] ?? 0; ?></div>
                    <div class="stat-label">Licitações Criadas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="database"></i>
                    </div>
                    <div class="stat-number"><?php echo $userStats['total_importacoes_sistema'] ?? 0; ?></div>
                    <div class="stat-label">Importações no Sistema</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="calendar"></i>
                    </div>
                    <div class="stat-number"><?php echo $userStats['anos_sistema']; ?></div>
                    <div class="stat-label">Anos no Sistema</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="shield-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $userStats['nivel_nome']; ?></div>
                    <div class="stat-label">Nível de Acesso</div>
                </div>
            </div>
            
            <?php if (!empty($recentActivities)): ?>
            <div class="form-section">
                <h3><i data-lucide="activity"></i> Atividades Recentes</h3>
                <div class="activities-list">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i data-lucide="plus-circle"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-action"><?php echo htmlspecialchars($activity['acao']); ?></div>
                            <div class="activity-details"><?php echo htmlspecialchars(substr($activity['detalhes'], 0, 100)) . (strlen($activity['detalhes']) > 100 ? '...' : ''); ?></div>
                        </div>
                        <div class="activity-time">
                            <?php echo timeAgo($activity['data_acao']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/dark-mode.js"></script>
    <script>
        // Função para trocar de aba
        function showTab(tabName) {
            // Remover active de todos os botões e conteúdos
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Adicionar active ao botão e conteúdo selecionados
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.add('active');
            
            // Reinicializar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
        
        // Função para alternar tema (integração com dark-mode.js)
        function toggleTheme() {
            if (window.darkModeManager) {
                window.darkModeManager.toggleTheme();
            }
        }
        
        // Submissão de formulários via AJAX
        document.addEventListener('DOMContentLoaded', function() {
            // Formulário de informações pessoais
            document.getElementById('form-personal').addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this, 'Atualizando informações...');
            });
            
            // Formulário de alteração de senha
            document.getElementById('form-security').addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this, 'Alterando senha...');
            });
            
            // Inicializar ícones Lucide
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
        
        function submitForm(form, loadingMessage) {
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-lucide="loader" style="animation: spin 1s linear infinite;"></i> ' + loadingMessage;
            
            // Preparar dados
            const formData = new FormData(form);
            
            // Enviar via fetch
            fetch('perfil_usuario.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    if (form.id === 'form-security') {
                        form.reset(); // Limpar formulário de senha
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erro ao processar solicitação', 'error');
                console.error('Erro:', error);
            })
            .finally(() => {
                // Restaurar botão
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                // Reinicializar ícones
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        }
        
        function showNotification(message, type) {
            // Criar elemento de notificação
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                font-weight: 500;
                max-width: 400px;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Mostrar
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            // Ocultar e remover
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        // Animação do CSS
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