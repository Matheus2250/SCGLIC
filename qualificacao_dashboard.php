<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// Buscar estatísticas das qualificações
try {
    // Verificar se a tabela existe
    $check_table = $pdo->query("SHOW TABLES LIKE 'qualificacoes'");
    if ($check_table->rowCount() == 0) {
        // Tabela não existe - usar dados zerados
        $stats = [
            'total_qualificacoes' => 0,
            'em_andamento' => 0,
            'aprovadas' => 0,
            'reprovadas' => 0,
            'valor_total' => 0.00
        ];
        $qualificacoes_recentes = [];
    } else {
        // Buscar estatísticas
        $stats_sql = "SELECT 
            COUNT(*) as total_qualificacoes,
            SUM(CASE WHEN status = 'Em Análise' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN status = 'Aprovado' THEN 1 ELSE 0 END) as aprovadas,
            SUM(CASE WHEN status = 'Reprovado' THEN 1 ELSE 0 END) as reprovadas,
            SUM(valor_estimado) as valor_total
            FROM qualificacoes";
        $stmt_stats = $pdo->query($stats_sql);
        $stats = $stmt_stats->fetch();
        
        // Garantir que os valores não sejam null
        $stats['total_qualificacoes'] = intval($stats['total_qualificacoes']);
        $stats['em_andamento'] = intval($stats['em_andamento']);
        $stats['aprovadas'] = intval($stats['aprovadas']);
        $stats['reprovadas'] = intval($stats['reprovadas']);
        $stats['valor_total'] = floatval($stats['valor_total'] ?? 0.00);
        
        // Buscar qualificações recentes para a tabela
        $qualificacoes_sql = "SELECT * FROM qualificacoes ORDER BY criado_em DESC LIMIT 20";
        $stmt_qualificacoes = $pdo->query($qualificacoes_sql);
        $qualificacoes_recentes = $stmt_qualificacoes->fetchAll();
    }
} catch (Exception $e) {
    // Em caso de erro, usar dados zerados
    $stats = [
        'total_qualificacoes' => 0,
        'em_andamento' => 0,
        'aprovadas' => 0,
        'reprovadas' => 0,
        'valor_total' => 0.00
    ];
    $qualificacoes_recentes = [];
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qualificação - Sistema CGLIC</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/qualificacao-dashboard.css">
    <link rel="stylesheet" href="assets/dark-mode.css">
    <link rel="stylesheet" href="assets/mobile-improvements.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i data-lucide="menu"></i>
                </button>
                <h2><i data-lucide="award"></i> Qualificação</h2>
            </div>
            
            <nav class="sidebar-nav">
                <!-- Navegação Principal -->
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="javascript:void(0)" class="nav-item active" onclick="showSection('dashboard')">
                        <i data-lucide="chart-line"></i>
                        <span>Painel Principal</span>
                    </a>
                </div>
                
                <!-- Qualificações -->
                <div class="nav-section">
                    <div class="nav-section-title">Qualificações</div>
                    <a href="javascript:void(0)" class="nav-item" onclick="showSection('lista-qualificacoes')">
                        <i data-lucide="list"></i>
                        <span>Qualificações</span>
                    </a>
                </div>
                
                <!-- Relatórios -->
                <div class="nav-section">
                    <div class="nav-section-title">Relatórios</div>
                    <a href="javascript:void(0)" class="nav-item" onclick="showSection('relatorios')">
                        <i data-lucide="file-text"></i>
                        <span>Relatórios</span>
                    </a>
                    <a href="javascript:void(0)" class="nav-item" onclick="showSection('estatisticas')">
                        <i data-lucide="bar-chart-3"></i>
                        <span>Estatísticas</span>
                    </a>
                </div>
                
                <!-- Navegação Geral -->
                <div class="nav-section">
                    <div class="nav-section-title">Sistema</div>
                    <a href="selecao_modulos.php" class="nav-item">
                        <i data-lucide="home"></i>
                        <span>Menu Principal</span>
                    </a>
                    <a href="dashboard.php" class="nav-item">
                        <i data-lucide="calendar-check"></i>
                        <span>Planejamento</span>
                    </a>
                    <a href="licitacao_dashboard.php" class="nav-item">
                        <i data-lucide="gavel"></i>
                        <span>Licitações</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['usuario_nome'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></h4>
                        <p><?php echo htmlspecialchars($_SESSION['usuario_email']); ?></p>
                    </div>
                </div>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i data-lucide="log-out"></i>
                    <span>Sair</span>
                </button>
            </div>
        </div>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            
            <!-- Dashboard Principal -->
            <section id="dashboard" class="content-section active">
                <!-- Header -->
                <div class="dashboard-header">
                    <h1><i data-lucide="award"></i> Painel de Qualificações</h1>
                    <p>Gerencie qualificações de fornecedores, avalie capacitação técnica e controle documentação</p>
                </div>
                
                <!-- Cards de Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-number"><?php echo number_format($stats['total_qualificacoes']); ?></div>
                        <div class="stat-label">Total Qualificações</div>
                    </div>
                    <div class="stat-card andamento">
                        <div class="stat-number"><?php echo number_format($stats['em_andamento']); ?></div>
                        <div class="stat-label">Em Andamento</div>
                    </div>
                    <div class="stat-card aprovados">
                        <div class="stat-number"><?php echo number_format($stats['aprovadas']); ?></div>
                        <div class="stat-label">Aprovadas</div>
                    </div>
                    <div class="stat-card reprovados">
                        <div class="stat-number"><?php echo number_format($stats['reprovadas']); ?></div>
                        <div class="stat-label">Reprovadas</div>
                    </div>
                    <div class="stat-card valor">
                        <div class="stat-number"><?php echo abreviarValor($stats['valor_total']); ?></div>
                        <div class="stat-label">Valor Total</div>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-title">
                            <i data-lucide="pie-chart"></i>
                            Qualificações por Categoria
                        </div>
                        <div class="chart-container">
                            <canvas id="qualificationChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-title">
                            <i data-lucide="bar-chart"></i>
                            Status das Qualificações
                        </div>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-title">
                            <i data-lucide="trending-up"></i>
                            Performance Mensal
                        </div>
                        <div class="chart-container">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Seção removida - será recriada como modal seguindo padrão de licitações -->
            
            <!-- Lista de Qualificações -->
            <section id="lista-qualificacoes" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="list"></i> Lista de Qualificações</h1>
                    <p>Visualize e gerencie todas as qualificações cadastradas</p>
                </div>
                
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">Qualificações Cadastradas</div>
                        <div class="table-actions">
                            <button onclick="abrirModal('modalCriarQualificacao')" class="btn-primary">
                                <i data-lucide="plus-circle"></i> Nova Qualificação
                            </button>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>NUP</th>
                                <th>Área Demandante</th>
                                <th>Responsável</th>
                                <th>Modalidade</th>
                                <th>Objeto</th>
                                <th>Status</th>
                                <th>Valor Estimado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($qualificacoes_recentes)): ?>
                                <?php foreach ($qualificacoes_recentes as $qualificacao): ?>
                                <tr>
                                    <td><span class="dfd-number"><?php echo htmlspecialchars($qualificacao['nup']); ?></span></td>
                                    <td><?php echo htmlspecialchars($qualificacao['area_demandante']); ?></td>
                                    <td><?php echo htmlspecialchars($qualificacao['responsavel']); ?></td>
                                    <td><?php echo htmlspecialchars($qualificacao['modalidade']); ?></td>
                                    <td class="titulo-cell"><?php echo htmlspecialchars(substr($qualificacao['objeto'], 0, 80) . (strlen($qualificacao['objeto']) > 80 ? '...' : '')); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch($qualificacao['status']) {
                                            case 'Aprovado': $status_class = 'status-aprovado'; break;
                                            case 'Reprovado': $status_class = 'status-reprovado'; break;
                                            case 'Em Análise': $status_class = 'status-em-andamento'; break;
                                            default: $status_class = 'status-pendente';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($qualificacao['status']); ?>
                                        </span>
                                    </td>
                                    <td><span class="valor-cell"><?php echo formatarMoeda($qualificacao['valor_estimado']); ?></span></td>
                                    <td class="table-actions">
                                        <button onclick="visualizarQualificacao(<?php echo $qualificacao['id']; ?>)" title="Ver Detalhes" style="background: #6c757d; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; margin-right: 4px;">
                                            <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                        </button>
                                        <button onclick="editarQualificacao(<?php echo $qualificacao['id']; ?>)" title="Editar" style="background: #f39c12; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; margin-right: 4px;">
                                            <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                                        </button>
                                        <button onclick="excluirQualificacao(<?php echo $qualificacao['id']; ?>)" title="Excluir" style="background: #e74c3c; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
                                            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #6c757d; font-style: italic;">
                                        <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 15px; opacity: 0.5;"></i><br>
                                        Nenhuma qualificação cadastrada ainda.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            
            <section id="relatorios" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="file-text"></i> Relatórios</h1>
                    <p>Módulo de relatórios em desenvolvimento</p>
                </div>
                <div class="empty-state">
                    <i data-lucide="construction"></i>
                    <h3>Em Desenvolvimento</h3>
                    <p>Este módulo será implementado em breve.</p>
                </div>
            </section>
            
            <section id="estatisticas" class="content-section">
                <div class="dashboard-header">
                    <h1><i data-lucide="bar-chart-3"></i> Estatísticas</h1>
                    <p>Módulo de estatísticas em desenvolvimento</p>
                </div>
                <div class="empty-state">
                    <i data-lucide="construction"></i>
                    <h3>Em Desenvolvimento</h3>
                    <p>Este módulo será implementado em breve.</p>
                </div>
            </section>
            
        </main>
    </div>

    <!-- Modal de Criação de Qualificação (baseado no modal de licitações) -->
    <div id="modalCriarQualificacao" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="plus-circle"></i> Criar Nova Qualificação
                </h3>
                <span class="close" onclick="fecharModal('modalCriarQualificacao')">&times;</span>
            </div>
            <div class="modal-body">
                <form action="process.php" method="POST" id="formCriarQualificacao">
                    <input type="hidden" name="acao" value="criar_qualificacao">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>NUP (Número Único de Protocolo) *</label>
                            <input type="text" name="nup" id="nup_criar" required placeholder="xxxxx.xxxxxx/xxxx-xx" maxlength="20">
                        </div>

                        <div class="form-group">
                            <label>Área Demandante *</label>
                            <input type="text" name="area_demandante" required placeholder="Nome da área solicitante">
                        </div>

                        <div class="form-group">
                            <label>Responsável *</label>
                            <input type="text" name="responsavel" required placeholder="Nome do responsável">
                        </div>

                        <div class="form-group">
                            <label>Modalidade *</label>
                            <select name="modalidade" required>
                                <option value="">Selecione a modalidade</option>
                                <option value="Pregão Eletrônico">Pregão Eletrônico</option>
                                <option value="Concorrência">Concorrência</option>
                                <option value="Tomada de Preços">Tomada de Preços</option>
                                <option value="Convite">Convite</option>
                                <option value="Dispensa">Dispensa</option>
                                <option value="Inexigibilidade">Inexigibilidade</option>
                            </select>
                        </div>

                        <div class="form-group form-full">
                            <label>Objeto *</label>
                            <textarea name="objeto" required placeholder="Descrição do objeto da qualificação" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Palavras-Chave</label>
                            <input type="text" name="palavras_chave" placeholder="Ex: equipamentos, serviços, tecnologia">
                        </div>

                        <div class="form-group">
                            <label>Valor Estimado (R$) *</label>
                            <input type="text" name="valor_estimado" class="currency" required placeholder="R$ 0,00">
                        </div>

                        <div class="form-group">
                            <label>Status *</label>
                            <select name="status" required>
                                <option value="">Selecione o status</option>
                                <option value="Em Análise">Em Análise</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Reprovado">Reprovado</option>
                                <option value="Pendente">Pendente</option>
                            </select>
                        </div>

                        <div class="form-group form-full">
                            <label>Observações</label>
                            <textarea name="observacoes" placeholder="Observações adicionais" rows="4"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer" style="display: flex; gap: 15px; justify-content: flex-end; padding: 20px 0 0 0; border-top: 1px solid #e5e7eb; margin-top: 25px;">
                        <button type="button" class="btn-secondary" onclick="fecharModal('modalCriarQualificacao')" style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="x"></i> Cancelar
                        </button>
                        <button type="submit" class="btn-primary" style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="save"></i> Criar Qualificação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="assets/qualificacao-dashboard.js"></script>
    <script src="assets/dark-mode.js"></script>
    <script src="assets/mobile-improvements.js"></script>
    <script src="assets/ux-improvements.js"></script>
    <script src="assets/notifications.js"></script>
    
</body>
</html>