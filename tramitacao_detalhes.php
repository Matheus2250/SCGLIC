<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();
$usuario_id = $_SESSION['usuario_id'];
$usuario_nivel = $_SESSION['usuario_nivel'];

$tramitacao_id = intval($_GET['id'] ?? 0);

if (!$tramitacao_id) {
    header('Location: tramitacoes.php');
    exit;
}

// Buscar detalhes da tramitação
$query = "SELECT * FROM v_tramitacoes_resumo WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$tramitacao_id]);
$tramitacao = $stmt->fetch();

if (!$tramitacao) {
    header('Location: tramitacoes.php');
    exit;
}

// Verificar permissão de acesso
$pode_visualizar = ($usuario_nivel == 1) || // Coordenador
                   ($tramitacao['usuario_origem_id'] == $usuario_id) || // Criador
                   ($tramitacao['usuario_destino_id'] == $usuario_id) || // Destinatário
                   ($usuario_nivel == 4); // Visitante pode ver tudo

if (!$pode_visualizar) {
    header('Location: tramitacoes.php');
    exit;
}

// Buscar histórico
$hist_query = "
    SELECT th.*, u.nome as usuario_nome, u.email as usuario_email
    FROM tramitacoes_historico th
    LEFT JOIN usuarios u ON th.usuario_id = u.id
    WHERE th.tramitacao_id = ?
    ORDER BY th.criado_em DESC
";
$stmt_hist = $pdo->prepare($hist_query);
$stmt_hist->execute([$tramitacao_id]);
$historico = $stmt_hist->fetchAll();

// Buscar comentários
$coment_query = "
    SELECT tc.*, u.nome as usuario_nome, u.email as usuario_email
    FROM tramitacoes_comentarios tc
    LEFT JOIN usuarios u ON tc.usuario_id = u.id
    WHERE tc.tramitacao_id = ?
    ORDER BY tc.criado_em ASC
";
$stmt_coment = $pdo->prepare($coment_query);
$stmt_coment->execute([$tramitacao_id]);
$comentarios = $stmt_coment->fetchAll();

// Verificar permissões de ação
$pode_editar = ($usuario_nivel <= 3) && 
               (($tramitacao['usuario_origem_id'] == $usuario_id) || 
                ($tramitacao['usuario_destino_id'] == $usuario_id) || 
                ($usuario_nivel == 1));

$pode_comentar = $usuario_nivel <= 3;
$pode_mudar_status = $pode_editar && ($tramitacao['status'] != 'CONCLUIDA' && $tramitacao['status'] != 'CANCELADA');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tramitação <?php echo $tramitacao['numero_tramite']; ?> - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .detalhes-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header-detalhes {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .titulo-secao {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .subtitulo {
            color: #6b7280;
            font-size: 16px;
            margin: 5px 0 0 0;
        }

        .btn-voltar {
            background: #f3f4f6;
            color: #374151;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-voltar:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }

        .detalhes-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card-principal {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .card-info {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }

        .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .tramitacao-numero {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pendente { background: #fef3c7; color: #92400e; }
        .status-em_andamento { background: #dcfce7; color: #166534; }
        .status-aguardando { background: #e0e7ff; color: #3730a3; }
        .status-concluida { background: #f3e8ff; color: #6b21a8; }
        .status-cancelada { background: #fee2e2; color: #991b1b; }
        .status-devolvida { background: #fef2f2; color: #7f1d1d; }

        .prioridade-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .prioridade-urgente { background: #fef2f2; color: #dc2626; }
        .prioridade-alta { background: #fff7ed; color: #ea580c; }
        .prioridade-media { background: #fefbeb; color: #d97706; }
        .prioridade-baixa { background: #f0fdf4; color: #16a34a; }

        .titulo-tramitacao {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 16px;
            line-height: 1.4;
        }

        .descricao-tramitacao {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 24px;
            white-space: pre-wrap;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
        }

        .info-value {
            font-size: 16px;
            color: #1f2937;
            font-weight: 500;
        }

        .fluxo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .modulo-box {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
            text-align: center;
            font-weight: 600;
            color: #374151;
        }

        .modulo-origem {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .modulo-destino {
            border-color: #10b981;
            background: #ecfdf5;
            color: #059669;
        }

        .seta-fluxo {
            color: #6b7280;
            font-size: 24px;
        }

        .prazo-info {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            text-align: center;
        }

        .prazo-info.atrasado {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .prazo-info.vencendo {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #ea580c;
        }

        .prazo-info.no_prazo {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        .acoes-container {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .btn-acao {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-iniciar {
            background: #10b981;
            color: white;
        }

        .btn-iniciar:hover {
            background: #059669;
        }

        .btn-pausar {
            background: #f59e0b;
            color: white;
        }

        .btn-pausar:hover {
            background: #d97706;
        }

        .btn-concluir {
            background: #8b5cf6;
            color: white;
        }

        .btn-concluir:hover {
            background: #7c3aed;
        }

        .btn-devolver {
            background: #ef4444;
            color: white;
        }

        .btn-devolver:hover {
            background: #dc2626;
        }

        .tabs-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .tabs-header {
            display: flex;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .tab-button {
            flex: 1;
            padding: 16px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
            background: white;
        }

        .tab-content {
            display: none;
            padding: 24px;
        }

        .tab-content.active {
            display: block;
        }

        .historico-timeline {
            position: relative;
        }

        .historico-timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }

        .historico-item {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            position: relative;
        }

        .historico-item::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3b82f6;
            z-index: 1;
        }

        .historico-icon {
            min-width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #eff6ff;
            border: 2px solid #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3b82f6;
        }

        .historico-conteudo {
            flex: 1;
        }

        .historico-titulo {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .historico-meta {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .historico-descricao {
            color: #4b5563;
            line-height: 1.5;
        }

        .comentarios-lista {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 24px;
        }

        .comentario-item {
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 16px;
            background: #fafafa;
        }

        .comentario-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 8px;
        }

        .comentario-autor {
            font-weight: 600;
            color: #1f2937;
        }

        .comentario-data {
            font-size: 14px;
            color: #6b7280;
        }

        .comentario-texto {
            color: #4b5563;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .novo-comentario {
            border-top: 1px solid #e5e7eb;
            padding-top: 24px;
        }

        .form-comentario {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .textarea-comentario {
            min-height: 100px;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            resize: vertical;
        }

        .btn-enviar-comentario {
            align-self: flex-end;
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-enviar-comentario:hover {
            background: #1d4ed8;
        }

        @media (max-width: 768px) {
            .detalhes-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .header-detalhes {
                flex-direction: column;
                align-items: stretch;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .fluxo-container {
                flex-direction: column;
                gap: 12px;
            }

            .acoes-container {
                justify-content: center;
            }

            .tabs-header {
                overflow-x: auto;
            }

            .tab-button {
                white-space: nowrap;
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="detalhes-container">
        <!-- Header -->
        <div class="header-detalhes">
            <div>
                <h1 class="titulo-secao">
                    <i data-lucide="workflow"></i>
                    Tramitação <?php echo $tramitacao['numero_tramite']; ?>
                </h1>
                <p class="subtitulo">Criada em <?php echo date('d/m/Y \à\s H:i', strtotime($tramitacao['criado_em'])); ?></p>
            </div>
            <a href="tramitacoes.php" class="btn-voltar">
                <i data-lucide="arrow-left"></i>
                Voltar
            </a>
        </div>

        <!-- Grid Principal -->
        <div class="detalhes-grid">
            <!-- Card Principal -->
            <div class="card-principal">
                <div class="status-header">
                    <div class="tramitacao-numero">Trâmite <?php echo $tramitacao['numero_tramite']; ?></div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="prioridade-badge prioridade-<?php echo strtolower($tramitacao['prioridade']); ?>">
                            <?php echo $tramitacao['prioridade']; ?>
                        </span>
                        <span class="status-badge status-<?php echo strtolower($tramitacao['status']); ?>">
                            <?php echo str_replace('_', ' ', $tramitacao['status']); ?>
                        </span>
                    </div>
                </div>

                <h2 class="titulo-tramitacao"><?php echo htmlspecialchars($tramitacao['titulo']); ?></h2>

                <div class="fluxo-container">
                    <div class="modulo-box modulo-origem">
                        <i data-lucide="send"></i><br>
                        <?php echo $tramitacao['modulo_origem']; ?>
                    </div>
                    <div class="seta-fluxo">
                        <i data-lucide="arrow-right"></i>
                    </div>
                    <div class="modulo-box modulo-destino">
                        <i data-lucide="inbox"></i><br>
                        <?php echo $tramitacao['modulo_destino']; ?>
                    </div>
                </div>

                <div class="descricao-tramitacao">
                    <?php echo htmlspecialchars($tramitacao['descricao']); ?>
                </div>

                <?php if ($tramitacao['observacoes']): ?>
                <div style="background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                    <strong>Observações:</strong><br>
                    <?php echo htmlspecialchars($tramitacao['observacoes']); ?>
                </div>
                <?php endif; ?>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Tipo de Demanda</span>
                        <span class="info-value"><?php echo htmlspecialchars($tramitacao['tipo_demanda']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Criado por</span>
                        <span class="info-value"><?php echo htmlspecialchars($tramitacao['usuario_origem_nome']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Responsável</span>
                        <span class="info-value">
                            <?php echo $tramitacao['usuario_destino_nome'] ? htmlspecialchars($tramitacao['usuario_destino_nome']) : 'Não atribuído'; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Última Atualização</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($tramitacao['atualizado_em'])); ?></span>
                    </div>
                </div>

                <?php if ($pode_mudar_status): ?>
                <div class="acoes-container">
                    <?php if ($tramitacao['status'] == 'PENDENTE'): ?>
                        <button class="btn-acao btn-iniciar" onclick="mudarStatus('EM_ANDAMENTO')">
                            <i data-lucide="play"></i>
                            Iniciar
                        </button>
                    <?php endif; ?>

                    <?php if ($tramitacao['status'] == 'EM_ANDAMENTO'): ?>
                        <button class="btn-acao btn-pausar" onclick="mudarStatus('AGUARDANDO')">
                            <i data-lucide="pause"></i>
                            Pausar
                        </button>
                        <button class="btn-acao btn-concluir" onclick="mudarStatus('CONCLUIDA')">
                            <i data-lucide="check"></i>
                            Concluir
                        </button>
                    <?php endif; ?>

                    <?php if ($tramitacao['status'] == 'AGUARDANDO'): ?>
                        <button class="btn-acao btn-iniciar" onclick="mudarStatus('EM_ANDAMENTO')">
                            <i data-lucide="play"></i>
                            Retomar
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($tramitacao['status'], ['PENDENTE', 'EM_ANDAMENTO', 'AGUARDANDO'])): ?>
                        <button class="btn-acao btn-devolver" onclick="mudarStatus('DEVOLVIDA')">
                            <i data-lucide="arrow-left"></i>
                            Devolver
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Card Info Lateral -->
            <div class="card-info">
                <?php if ($tramitacao['prazo_limite']): ?>
                <div class="prazo-info <?php echo $tramitacao['situacao_prazo'] == 'ATRASADO' ? 'atrasado' : ($tramitacao['situacao_prazo'] == 'VENCENDO' ? 'vencendo' : 'no_prazo'); ?>">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 8px;">
                        <i data-lucide="clock"></i>
                        <strong>Prazo</strong>
                    </div>
                    <div style="font-size: 18px; font-weight: 700; margin-bottom: 4px;">
                        <?php echo date('d/m/Y H:i', strtotime($tramitacao['prazo_limite'])); ?>
                    </div>
                    <div>
                        <?php
                        if ($tramitacao['situacao_prazo'] == 'ATRASADO') {
                            echo abs($tramitacao['dias_restantes']) . ' dia(s) em atraso';
                        } else if ($tramitacao['situacao_prazo'] == 'VENCENDO') {
                            echo 'Vence em ' . $tramitacao['dias_restantes'] . ' dia(s)';
                        } else {
                            echo $tramitacao['dias_restantes'] . ' dia(s) restantes';
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <div style="margin-top: 24px;">
                    <h3 style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="message-circle"></i>
                        Atividade Recente
                    </h3>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach (array_slice($historico, 0, 5) as $item): ?>
                        <div style="margin-bottom: 16px; padding: 12px; background: #f9fafb; border-radius: 8px;">
                            <div style="font-weight: 600; color: #374151; margin-bottom: 4px;">
                                <?php echo htmlspecialchars($item['acao']); ?>
                            </div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">
                                <?php echo htmlspecialchars($item['usuario_nome']); ?> • 
                                <?php echo date('d/m H:i', strtotime($item['criado_em'])); ?>
                            </div>
                            <?php if ($item['observacao']): ?>
                            <div style="color: #4b5563; font-size: 14px;">
                                <?php echo htmlspecialchars($item['observacao']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Inferior -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" onclick="showTab('historico')">
                    <i data-lucide="history"></i>
                    Histórico Completo
                </button>
                <button class="tab-button" onclick="showTab('comentarios')">
                    <i data-lucide="message-square"></i>
                    Comentários (<?php echo count($comentarios); ?>)
                </button>
            </div>

            <!-- Tab Histórico -->
            <div class="tab-content active" id="tab-historico">
                <div class="historico-timeline">
                    <?php foreach ($historico as $item): ?>
                    <div class="historico-item">
                        <div class="historico-icon">
                            <i data-lucide="activity"></i>
                        </div>
                        <div class="historico-conteudo">
                            <div class="historico-titulo">
                                <?php echo htmlspecialchars($item['acao']); ?>
                                <?php if ($item['status_anterior'] && $item['status_novo']): ?>
                                    (<?php echo $item['status_anterior']; ?> → <?php echo $item['status_novo']; ?>)
                                <?php endif; ?>
                            </div>
                            <div class="historico-meta">
                                Por <?php echo htmlspecialchars($item['usuario_nome']); ?> em 
                                <?php echo date('d/m/Y \à\s H:i', strtotime($item['criado_em'])); ?>
                            </div>
                            <?php if ($item['observacao']): ?>
                            <div class="historico-descricao">
                                <?php echo htmlspecialchars($item['observacao']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tab Comentários -->
            <div class="tab-content" id="tab-comentarios">
                <?php if (count($comentarios) > 0): ?>
                <div class="comentarios-lista">
                    <?php foreach ($comentarios as $comentario): ?>
                    <div class="comentario-item">
                        <div class="comentario-header">
                            <span class="comentario-autor"><?php echo htmlspecialchars($comentario['usuario_nome']); ?></span>
                            <span class="comentario-data"><?php echo date('d/m/Y H:i', strtotime($comentario['criado_em'])); ?></span>
                        </div>
                        <div class="comentario-texto">
                            <?php echo htmlspecialchars($comentario['comentario']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #6b7280;">
                    <i data-lucide="message-circle" size="48"></i>
                    <p>Ainda não há comentários nesta tramitação.</p>
                </div>
                <?php endif; ?>

                <?php if ($pode_comentar): ?>
                <div class="novo-comentario">
                    <h4 style="margin-bottom: 12px;">Adicionar Comentário</h4>
                    <form class="form-comentario" method="POST" action="process.php">
                        <input type="hidden" name="action" value="comentar_tramitacao">
                        <input type="hidden" name="tramitacao_id" value="<?php echo $tramitacao_id; ?>">
                        <textarea name="comentario" class="textarea-comentario" placeholder="Digite seu comentário..." required></textarea>
                        <button type="submit" class="btn-enviar-comentario">
                            <i data-lucide="send"></i>
                            Enviar Comentário
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Esconder todas as tabs
            const tabs = document.querySelectorAll('.tab-content');
            const buttons = document.querySelectorAll('.tab-button');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Mostrar tab selecionada
            document.getElementById(`tab-${tabName}`).classList.add('active');
            event.target.classList.add('active');
        }

        function mudarStatus(novoStatus) {
            if (confirm(`Confirma a alteração do status para ${novoStatus.replace('_', ' ')}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="alterar_status_tramitacao">
                    <input type="hidden" name="tramitacao_id" value="<?php echo $tramitacao_id; ?>">
                    <input type="hidden" name="novo_status" value="${novoStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>