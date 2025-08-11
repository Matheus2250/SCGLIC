<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();
$usuario_id = $_SESSION['usuario_id'];
$usuario_nivel = $_SESSION['usuario_nivel'];
$usuario_nome = $_SESSION['usuario_nome'];

// Determinar módulos do usuário baseado no nível
$modulos_usuario = [];
switch ($usuario_nivel) {
    case 1: // Coordenador - acesso total
        $modulos_usuario = ['PLANEJAMENTO', 'LICITACAO', 'QUALIFICACAO', 'CONTRATOS'];
        break;
    case 2: // DIPLAN - foco em planejamento
        $modulos_usuario = ['PLANEJAMENTO', 'LICITACAO'];
        break;
    case 3: // DIPLI - foco em licitação
        $modulos_usuario = ['LICITACAO', 'QUALIFICACAO', 'CONTRATOS'];
        break;
    case 4: // Visitante - apenas visualização
        $modulos_usuario = ['PLANEJAMENTO', 'LICITACAO', 'QUALIFICACAO', 'CONTRATOS'];
        break;
}

// Buscar estatísticas para o dashboard
$stats_query = "
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'PENDENTE' THEN 1 END) as pendentes,
        COUNT(CASE WHEN status = 'EM_ANDAMENTO' THEN 1 END) as em_andamento,
        COUNT(CASE WHEN status = 'CONCLUIDA' THEN 1 END) as concluidas,
        COUNT(CASE WHEN prazo_limite < NOW() AND status NOT IN ('CONCLUIDA', 'CANCELADA') THEN 1 END) as atrasadas,
        COUNT(CASE WHEN DATE(prazo_limite) = CURDATE() AND status NOT IN ('CONCLUIDA', 'CANCELADA') THEN 1 END) as vencendo_hoje
    FROM tramitacoes 
    WHERE (modulo_origem IN ('" . implode("','", $modulos_usuario) . "') 
           OR modulo_destino IN ('" . implode("','", $modulos_usuario) . "'))";

if ($usuario_nivel == 4) { // Visitante - sem restrição, mas só visualização
    $stats_query .= "";
} else {
    $stats_query .= " AND (usuario_origem_id = ? OR usuario_destino_id = ? OR usuario_destino_id IS NULL)";
}

$stmt_stats = $pdo->prepare($stats_query);
if ($usuario_nivel != 4) {
    $stmt_stats->execute([$usuario_id, $usuario_id]);
} else {
    $stmt_stats->execute();
}
$stats = $stmt_stats->fetch();

// Buscar templates disponíveis
$templates_query = "
    SELECT * FROM tramitacoes_templates 
    WHERE ativo = 1 AND modulo_origem IN ('" . implode("','", $modulos_usuario) . "')
    ORDER BY nome
";
$templates = $pdo->query($templates_query)->fetchAll();

// Parâmetros de filtro
$filtro_modulo = $_GET['modulo'] ?? 'TODOS';
$filtro_status = $_GET['status'] ?? 'TODOS';
$filtro_prioridade = $_GET['prioridade'] ?? 'TODOS';
$busca = $_GET['busca'] ?? '';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

// Query principal das tramitações
$where_conditions = [];
$params = [];

// Filtro por módulos do usuário
$where_conditions[] = "(modulo_origem IN ('" . implode("','", $modulos_usuario) . "') 
                       OR modulo_destino IN ('" . implode("','", $modulos_usuario) . "'))";

// Filtro por usuário (não aplicar para visitante)
if ($usuario_nivel != 4) {
    $where_conditions[] = "(usuario_origem_id = ? OR usuario_destino_id = ? OR usuario_destino_id IS NULL)";
    $params[] = $usuario_id;
    $params[] = $usuario_id;
}

// Aplicar filtros
if ($filtro_modulo != 'TODOS') {
    $where_conditions[] = "(modulo_origem = ? OR modulo_destino = ?)";
    $params[] = $filtro_modulo;
    $params[] = $filtro_modulo;
}

if ($filtro_status != 'TODOS') {
    $where_conditions[] = "status = ?";
    $params[] = $filtro_status;
}

if ($filtro_prioridade != 'TODOS') {
    $where_conditions[] = "prioridade = ?";
    $params[] = $filtro_prioridade;
}

if (!empty($busca)) {
    $where_conditions[] = "(titulo LIKE ? OR descricao LIKE ? OR numero_tramite LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$where_clause = implode(' AND ', $where_conditions);

// Contar total para paginação
$count_query = "SELECT COUNT(*) as total FROM v_tramitacoes_resumo WHERE $where_clause";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_tramitacoes = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_tramitacoes / $por_pagina);

// Buscar tramitações
$query = "
    SELECT * FROM v_tramitacoes_resumo 
    WHERE $where_clause
    ORDER BY 
        CASE status 
            WHEN 'PENDENTE' THEN 1
            WHEN 'EM_ANDAMENTO' THEN 2
            WHEN 'AGUARDANDO' THEN 3
            ELSE 4
        END,
        CASE prioridade 
            WHEN 'URGENTE' THEN 1
            WHEN 'ALTA' THEN 2
            WHEN 'MEDIA' THEN 3
            WHEN 'BAIXA' THEN 4
        END,
        criado_em DESC
    LIMIT $por_pagina OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tramitacoes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tramitações - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .tramitacoes-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header-tramitacoes {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .stat-card.total { border-left: 4px solid #3b82f6; }
        .stat-card.pendentes { border-left: 4px solid #f59e0b; }
        .stat-card.em-andamento { border-left: 4px solid #10b981; }
        .stat-card.concluidas { border-left: 4px solid #8b5cf6; }
        .stat-card.atrasadas { border-left: 4px solid #ef4444; }
        .stat-card.vencendo { border-left: 4px solid #f97316; }

        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 2fr auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-label {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .filter-input, .filter-select {
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }

        .tramitacoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .tramitacao-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .tramitacao-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .tramitacao-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .tramitacao-card.urgente::before { background: #dc2626; }
        .tramitacao-card.alta::before { background: #ea580c; }
        .tramitacao-card.media::before { background: #d97706; }
        .tramitacao-card.baixa::before { background: #16a34a; }

        .tramitacao-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .tramitacao-numero {
            font-weight: 700;
            color: #1f2937;
            font-size: 16px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pendente { background: #fef3c7; color: #92400e; }
        .status-em_andamento { background: #dcfce7; color: #166534; }
        .status-aguardando { background: #e0e7ff; color: #3730a3; }
        .status-concluida { background: #f3e8ff; color: #6b21a8; }
        .status-cancelada { background: #fee2e2; color: #991b1b; }
        .status-devolvida { background: #fef2f2; color: #7f1d1d; }

        .tramitacao-titulo {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .tramitacao-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #6b7280;
        }

        .tramitacao-descricao {
            color: #6b7280;
            line-height: 1.5;
            margin-bottom: 15px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .tramitacao-footer {
            display: flex;
            justify-content: between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f3f4f6;
        }

        .prazo-info {
            font-size: 14px;
            font-weight: 600;
        }

        .prazo-info.atrasado { color: #dc2626; }
        .prazo-info.vencendo { color: #ea580c; }
        .prazo-info.no_prazo { color: #16a34a; }

        .btn-nova-tramitacao {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-nova-tramitacao:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #d1d5db;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 16px;
            padding: 0;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 4px;
        }

        .modal-body {
            padding: 24px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .form-input, .form-select, .form-textarea {
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .tramitacoes-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal {
                width: 95%;
                margin: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="tramitacoes-container">
        <!-- Header -->
        <div class="header-tramitacoes">
            <div>
                <h1><i data-lucide="workflow"></i> Tramitações</h1>
                <p>Gerencie tramitações entre módulos do sistema</p>
            </div>
            <?php if ($usuario_nivel <= 3): ?>
            <button class="btn-nova-tramitacao" onclick="abrirModalNovaTramitacao()">
                <i data-lucide="plus"></i>
                Nova Tramitação
            </button>
            <?php endif; ?>
        </div>

        <!-- Estatísticas -->
        <div class="stats-cards">
            <div class="stat-card total">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card pendentes">
                <div class="stat-number"><?php echo $stats['pendentes']; ?></div>
                <div class="stat-label">Pendentes</div>
            </div>
            <div class="stat-card em-andamento">
                <div class="stat-number"><?php echo $stats['em_andamento']; ?></div>
                <div class="stat-label">Em Andamento</div>
            </div>
            <div class="stat-card concluidas">
                <div class="stat-number"><?php echo $stats['concluidas']; ?></div>
                <div class="stat-label">Concluídas</div>
            </div>
            <div class="stat-card atrasadas">
                <div class="stat-number"><?php echo $stats['atrasadas']; ?></div>
                <div class="stat-label">Atrasadas</div>
            </div>
            <div class="stat-card vencendo">
                <div class="stat-number"><?php echo $stats['vencendo_hoje']; ?></div>
                <div class="stat-label">Vencendo Hoje</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Módulo</label>
                    <select name="modulo" class="filter-select">
                        <option value="TODOS">Todos os Módulos</option>
                        <option value="PLANEJAMENTO" <?php echo $filtro_modulo == 'PLANEJAMENTO' ? 'selected' : ''; ?>>Planejamento</option>
                        <option value="LICITACAO" <?php echo $filtro_modulo == 'LICITACAO' ? 'selected' : ''; ?>>Licitação</option>
                        <option value="QUALIFICACAO" <?php echo $filtro_modulo == 'QUALIFICACAO' ? 'selected' : ''; ?>>Qualificação</option>
                        <option value="CONTRATOS" <?php echo $filtro_modulo == 'CONTRATOS' ? 'selected' : ''; ?>>Contratos</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-select">
                        <option value="TODOS">Todos os Status</option>
                        <option value="PENDENTE" <?php echo $filtro_status == 'PENDENTE' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="EM_ANDAMENTO" <?php echo $filtro_status == 'EM_ANDAMENTO' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="AGUARDANDO" <?php echo $filtro_status == 'AGUARDANDO' ? 'selected' : ''; ?>>Aguardando</option>
                        <option value="CONCLUIDA" <?php echo $filtro_status == 'CONCLUIDA' ? 'selected' : ''; ?>>Concluída</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Prioridade</label>
                    <select name="prioridade" class="filter-select">
                        <option value="TODOS">Todas as Prioridades</option>
                        <option value="URGENTE" <?php echo $filtro_prioridade == 'URGENTE' ? 'selected' : ''; ?>>Urgente</option>
                        <option value="ALTA" <?php echo $filtro_prioridade == 'ALTA' ? 'selected' : ''; ?>>Alta</option>
                        <option value="MEDIA" <?php echo $filtro_prioridade == 'MEDIA' ? 'selected' : ''; ?>>Média</option>
                        <option value="BAIXA" <?php echo $filtro_prioridade == 'BAIXA' ? 'selected' : ''; ?>>Baixa</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Buscar</label>
                    <input type="text" name="busca" class="filter-input" placeholder="Número, título ou descrição..." value="<?php echo htmlspecialchars($busca); ?>">
                </div>

                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="search"></i>
                        Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de Tramitações -->
        <?php if (count($tramitacoes) > 0): ?>
        <div class="tramitacoes-grid">
            <?php foreach ($tramitacoes as $tramitacao): ?>
            <div class="tramitacao-card <?php echo strtolower($tramitacao['prioridade']); ?>" onclick="verTramitacao(<?php echo $tramitacao['id']; ?>)">
                <div class="tramitacao-header">
                    <div class="tramitacao-numero"><?php echo $tramitacao['numero_tramite']; ?></div>
                    <span class="status-badge status-<?php echo strtolower($tramitacao['status']); ?>">
                        <?php echo str_replace('_', ' ', $tramitacao['status']); ?>
                    </span>
                </div>

                <div class="tramitacao-titulo"><?php echo htmlspecialchars($tramitacao['titulo']); ?></div>

                <div class="tramitacao-info">
                    <div class="info-item">
                        <i data-lucide="arrow-right" size="16"></i>
                        <?php echo $tramitacao['modulo_origem']; ?> → <?php echo $tramitacao['modulo_destino']; ?>
                    </div>
                    <div class="info-item">
                        <i data-lucide="tag" size="16"></i>
                        <?php echo $tramitacao['tipo_demanda']; ?>
                    </div>
                </div>

                <div class="tramitacao-descricao">
                    <?php echo htmlspecialchars(substr($tramitacao['descricao'], 0, 120)) . (strlen($tramitacao['descricao']) > 120 ? '...' : ''); ?>
                </div>

                <div class="tramitacao-footer">
                    <div class="info-item">
                        <i data-lucide="user" size="16"></i>
                        <?php echo htmlspecialchars($tramitacao['usuario_origem_nome']); ?>
                    </div>
                    <?php if ($tramitacao['prazo_limite']): ?>
                    <div class="prazo-info <?php echo $tramitacao['situacao_prazo'] == 'ATRASADO' ? 'atrasado' : ($tramitacao['situacao_prazo'] == 'VENCENDO' ? 'vencendo' : 'no_prazo'); ?>">
                        <i data-lucide="clock" size="16"></i>
                        <?php
                        if ($tramitacao['situacao_prazo'] == 'ATRASADO') {
                            echo abs($tramitacao['dias_restantes']) . ' dia(s) atrasado';
                        } else {
                            echo $tramitacao['dias_restantes'] . ' dia(s) restantes';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $total_paginas; $p++): ?>
                <a href="?pagina=<?php echo $p; ?>&modulo=<?php echo $filtro_modulo; ?>&status=<?php echo $filtro_status; ?>&prioridade=<?php echo $filtro_prioridade; ?>&busca=<?php echo urlencode($busca); ?>" 
                   class="page-link <?php echo $p == $pagina ? 'active' : ''; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <i data-lucide="inbox"></i>
            <h3>Nenhuma tramitação encontrada</h3>
            <p>Não há tramitações que correspondam aos filtros selecionados.</p>
            <?php if ($usuario_nivel <= 3): ?>
            <br>
            <button class="btn-nova-tramitacao" onclick="abrirModalNovaTramitacao()">
                <i data-lucide="plus"></i>
                Criar primeira tramitação
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Nova Tramitação -->
    <div class="modal-overlay" id="modalNovaTramitacao">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Nova Tramitação</h2>
                <button class="modal-close" onclick="fecharModal('modalNovaTramitacao')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form method="POST" action="process.php">
                <input type="hidden" name="action" value="criar_tramitacao">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Módulo Origem</label>
                            <select name="modulo_origem" class="form-select" required>
                                <?php foreach ($modulos_usuario as $modulo): ?>
                                    <option value="<?php echo $modulo; ?>"><?php echo $modulo; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Módulo Destino</label>
                            <select name="modulo_destino" class="form-select" required>
                                <option value="PLANEJAMENTO">Planejamento</option>
                                <option value="LICITACAO">Licitação</option>
                                <option value="QUALIFICACAO">Qualificação</option>
                                <option value="CONTRATOS">Contratos</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select" required>
                                <option value="BAIXA">Baixa</option>
                                <option value="MEDIA" selected>Média</option>
                                <option value="ALTA">Alta</option>
                                <option value="URGENTE">Urgente</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Prazo Limite</label>
                            <input type="datetime-local" name="prazo_limite" class="form-input">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Tipo de Demanda</label>
                            <input type="text" name="tipo_demanda" class="form-input" placeholder="Ex: Análise Técnica, Elaboração de Edital..." required>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-input" placeholder="Título resumido da tramitação" required>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-textarea" placeholder="Descreva detalhadamente a demanda..." required></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-textarea" placeholder="Observações adicionais (opcional)"></textarea>
                        </div>
                    </div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalNovaTramitacao')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Criar Tramitação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalNovaTramitacao() {
            document.getElementById('modalNovaTramitacao').style.display = 'block';
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function verTramitacao(id) {
            window.location.href = 'tramitacao_detalhes.php?id=' + id;
        }

        // Fechar modal ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.style.display = 'none';
            }
        });

        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>