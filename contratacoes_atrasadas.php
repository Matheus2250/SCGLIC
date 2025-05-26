<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

$pdo = conectarDB();

// Tipo de atraso para filtrar
$tipo_atraso = $_GET['tipo'] ?? 'todos';

// Construir WHERE baseado no tipo
$where = [];
$params = [];
$titulo_pagina = 'Contratações Atrasadas';

switch ($tipo_atraso) {
    case 'inicio':
        $where[] = "p.data_inicio_processo < CURDATE() AND p.situacao_execucao = 'Não iniciado'";
        $titulo_pagina = 'Contratações Não Iniciadas';
        break;
    case 'conclusao':
        $where[] = "p.data_conclusao_processo < CURDATE() AND p.situacao_execucao != 'Concluído'";
        $titulo_pagina = 'Contratações Vencidas';
        break;
    case 'vencendo':
        $where[] = "p.data_conclusao_processo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        $titulo_pagina = 'Contratações Vencendo em 30 dias';
        break;
    default:
        $where[] = "(p.data_inicio_processo < CURDATE() AND p.situacao_execucao = 'Não iniciado') 
                    OR (p.data_conclusao_processo < CURDATE() AND p.situacao_execucao != 'Concluído')";
        $titulo_pagina = 'Todas as Contratações Atrasadas';
}

// Filtro adicional por área se fornecido
if (!empty($_GET['area'])) {
    $where[] = "p.area_requisitante = ?";
    $params[] = $_GET['area'];
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Query principal
$sql = "SELECT 
        p.numero_contratacao,
        MAX(p.titulo_contratacao) as titulo_contratacao,
        MAX(p.categoria_contratacao) as categoria_contratacao,
        SUM(p.valor_total_contratacao) as valor_total_contratacao,
        MAX(p.area_requisitante) as area_requisitante,
        MAX(p.situacao_execucao) as situacao_execucao,
        MAX(p.data_inicio_processo) as data_inicio_processo,
        MAX(p.data_conclusao_processo) as data_conclusao_processo,
        DATEDIFF(MAX(p.data_inicio_processo), CURDATE()) as dias_atraso_inicio,
        DATEDIFF(MAX(p.data_conclusao_processo), CURDATE()) as dias_atraso_conclusao,
        COUNT(*) as qtd_itens,
        GROUP_CONCAT(p.id) as ids,
        MAX((SELECT COUNT(*) FROM licitacoes WHERE pca_dados_id IN (
            SELECT id FROM pca_dados WHERE numero_contratacao = p.numero_contratacao
        ))) as tem_licitacao,
        MAX(p.numero_dfd) as numero_dfd
        FROM pca_dados p 
        $whereClause 
        GROUP BY p.numero_contratacao
        ORDER BY 
            CASE 
                WHEN p.data_conclusao_processo < CURDATE() THEN 1
                WHEN p.data_inicio_processo < CURDATE() THEN 2
                ELSE 3
            END,
            ABS(DATEDIFF(p.data_conclusao_processo, CURDATE())) ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contratacoes = $stmt->fetchAll();

// Buscar lista de áreas únicas
$areas_sql = "SELECT DISTINCT area_requisitante FROM pca_dados ORDER BY area_requisitante";
$areas = $pdo->query($areas_sql)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filtro-tipos {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tipo-badge {
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .tipo-badge:hover {
            transform: translateY(-1px);
        }
        .tipo-badge.ativo {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .tipo-inicio {
            background: #fff5f5;
            color: #ff6b6b;
            border-color: #ffdddd;
        }
        .tipo-conclusao {
            background: #ffe0e0;
            color: #dc3545;
            border-color: #ffcccc;
        }
        .tipo-vencendo {
            background: #fff8e6;
            color: #f39c12;
            border-color: #ffe8aa;
        }
        .tipo-todos {
            background: #f0f4ff;
            color: #3498db;
            border-color: #d4e2ff;
        }
        .info-atraso {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .tabela-atrasadas th {
            background: #e9ecef;
            position: sticky;
            top: 0;
        }
        .dias-atraso {
            font-weight: bold;
            font-size: 16px;
        }
        .atraso-alto { color: #dc3545; }
        .atraso-medio { color: #ff6b6b; }
        .atraso-baixo { color: #f39c12; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><?php echo $titulo_pagina; ?></h1>
            <div class="nav-menu">
                <a href="dashboard.php">← Voltar ao Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filtros de tipo -->
        <div class="filtro-tipos">
            <a href="?tipo=todos" class="tipo-badge tipo-todos <?php echo $tipo_atraso == 'todos' ? 'ativo' : ''; ?>">
                Todas (<?php echo count($contratacoes); ?>)
            </a>
            <a href="?tipo=inicio" class="tipo-badge tipo-inicio <?php echo $tipo_atraso == 'inicio' ? 'ativo' : ''; ?>">
                ⚠️ Não Iniciadas
            </a>
            <a href="?tipo=conclusao" class="tipo-badge tipo-conclusao <?php echo $tipo_atraso == 'conclusao' ? 'ativo' : ''; ?>">
                🚨 Vencidas
            </a>
            <a href="?tipo=vencendo" class="tipo-badge tipo-vencendo <?php echo $tipo_atraso == 'vencendo' ? 'ativo' : ''; ?>">
                📅 Vencendo em 30 dias
            </a>
        </div>

        <!-- Filtro por área -->
        <div class="filtros" style="margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="tipo" value="<?php echo $tipo_atraso; ?>">
                <select name="area" onchange="this.form.submit()">
                    <option value="">Todas as áreas</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?php echo htmlspecialchars($area); ?>" 
                                <?php echo ($_GET['area'] ?? '') == $area ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($area); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($_GET['area'])): ?>
                    <a href="?tipo=<?php echo $tipo_atraso; ?>" class="btn btn-pequeno btn-secundario">Limpar filtro</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Informações resumidas -->
        <?php if (!empty($contratacoes)): ?>
        <div class="info-atraso">
            <p><strong><?php echo count($contratacoes); ?></strong> contratações encontradas</p>
            <p><strong>Valor total:</strong> <?php echo formatarMoeda(array_sum(array_column($contratacoes, 'valor_total_contratacao'))); ?></p>
        </div>
        <?php endif; ?>

        <!-- Tabela -->
        <div class="tabela-container">
            <table style="font-size: 13px; width: 100%;">
                <thead>
                    <tr>
                        <th>Nº Contratação</th>
                        <th>Título</th>
                        <th>Área</th>
                        <th>Valor</th>
                        <th>Conclusão</th>
                        <th>Atraso</th>
                        <th>Situação</th>
                        <th style="width: 100px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contratacoes)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhuma contratação atrasada encontrada</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contratacoes as $item): ?>
                        <?php
                            $classe_atraso = '';
                            $dias_atraso = 0;
                            $tipo_atraso_item = '';
                            
                            if ($item['dias_atraso_conclusao'] < 0) {
                                $dias_atraso = abs($item['dias_atraso_conclusao']);
                                $tipo_atraso_item = 'conclusão';
                                $classe_atraso = $dias_atraso > 30 ? 'atraso-alto' : ($dias_atraso > 15 ? 'atraso-medio' : 'atraso-baixo');
                            } elseif ($item['dias_atraso_inicio'] < 0 && $item['situacao_execucao'] == 'Não iniciado') {
                                $dias_atraso = abs($item['dias_atraso_inicio']);
                                $tipo_atraso_item = 'início';
                                $classe_atraso = $dias_atraso > 30 ? 'atraso-alto' : ($dias_atraso > 15 ? 'atraso-medio' : 'atraso-baixo');
                            } elseif ($item['dias_atraso_conclusao'] >= 0 && $item['dias_atraso_conclusao'] <= 30) {
                                $dias_atraso = $item['dias_atraso_conclusao'];
                                $tipo_atraso_item = 'vencendo';
                                $classe_atraso = 'atraso-baixo';
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['numero_contratacao']); ?></strong></td>
                            <td class="titulo-cell" title="<?php echo htmlspecialchars($item['titulo_contratacao']); ?>">
                                <?php echo htmlspecialchars(substr($item['titulo_contratacao'], 0, 40)) . '...'; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['area_requisitante']); ?></td>
                            <td style="white-space: nowrap;"><?php echo formatarMoeda($item['valor_total_contratacao']); ?></td>
                            <td style="white-space: nowrap;"><?php echo formatarData($item['data_conclusao_processo']); ?></td>
                            <td>
                                <?php if ($tipo_atraso_item == 'vencendo'): ?>
                                    <span class="dias-atraso <?php echo $classe_atraso; ?>">
                                        <?php echo $dias_atraso; ?>d
                                    </span>
                                <?php elseif ($dias_atraso > 0): ?>
                                    <span class="dias-atraso <?php echo $classe_atraso; ?>">
                                        <?php echo $dias_atraso; ?>d
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="situacao-badge" style="font-size: 12px;">
                                    <?php echo htmlspecialchars($item['situacao_execucao']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <button onclick="verDetalhes('<?php echo $item['ids']; ?>')" 
                                            class="btn-acao btn-ver" title="Ver detalhes">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    <?php if ($item['tem_licitacao'] == 0): ?>
                                        <button onclick="abrirModalLicitacao('<?php echo $item['ids']; ?>')" 
                                                class="btn-acao btn-licitar" title="Licitar">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Exportar -->
        <?php if (!empty($contratacoes)): ?>
        <div style="margin-top: 20px; text-align: right;">
            <a href="exportar_atrasadas.php?tipo=<?php echo $tipo_atraso; ?>&area=<?php echo $_GET['area'] ?? ''; ?>" 
               class="btn-exportar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Exportar para Excel
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Licitação (reutilizar do dashboard) -->
    <div id="modalLicitacao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Criar Licitação</h3>
                <span class="close" onclick="fecharModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form action="process.php" method="POST" id="formLicitacao">
                    <input type="hidden" name="acao" value="criar_licitacao">
                    <input type="hidden" name="pca_dados_ids" id="pca_dados_ids">
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label>NUP *</label>
                                <input type="text" name="nup" required placeholder="xxxxx.xxxxxx/xxxx-xx" 
                                       pattern="\d{5}\.\d{6}\/\d{4}-\d{2}">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Data Entrada DIPLI</label>
                                <input type="date" name="data_entrada_dipli">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Responsável Instrução</label>
                                <input type="text" name="resp_instrucao">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Área Demandante</label>
                                <input type="text" name="area_demandante">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Pregoeiro</label>
                                <input type="text" name="pregoeiro">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Modalidade *</label>
                                <select name="modalidade" required>
                                    <option value="">Selecione</option>
                                    <option value="DISPENSA">DISPENSA</option>
                                    <option value="PREGAO">PREGÃO</option>
                                    <option value="RDC">RDC</option>
                                    <option value="INEXIBILIDADE">INEXIBILIDADE</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Tipo *</label>
                                <select name="tipo" required>
                                    <option value="">Selecione</option>
                                    <option value="TRADICIONAL">TRADICIONAL</option>
                                    <option value="COTACAO">COTAÇÃO</option>
                                    <option value="SRP">SRP</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Número</label>
                                <input type="number" name="numero">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Ano</label>
                                <input type="number" name="ano" value="<?php echo date('Y'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-grid-full">
                            <div class="form-group">
                                <label>Objeto *</label>
                                <textarea name="objeto" required rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Qtd Itens</label>
                                <input type="number" name="qtd_itens" value="1">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Valor Estimado (R$)</label>
                                <input type="text" name="valor_estimado" placeholder="0,00">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Data Abertura</label>
                                <input type="date" name="data_abertura">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Situação *</label>
                                <select name="situacao" required>
                                    <option value="EM_ANDAMENTO">EM ANDAMENTO</option>
                                    <option value="REVOGADO">REVOGADO</option>
                                    <option value="FRACASSADO">FRACASSADO</option>
                                    <option value="HOMOLOGADO">HOMOLOGADO</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-20">
                        <button type="submit" class="btn btn-sucesso">Criar Licitação</button>
                        <button type="button" class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes -->
    <div id="modalDetalhes" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalhes da Contratação</h3>
                <span class="close" onclick="fecharModalDetalhes()">&times;</span>
            </div>
            <div class="modal-body" id="conteudoDetalhes">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>