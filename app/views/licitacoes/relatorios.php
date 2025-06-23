<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Relatórios - Licitações</h3>
        <a href="<?= BASE_URL ?>licitacoes/index" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <!-- Cards de Estatísticas Gerais -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-success">
                <div class="card-header">
                    <i class="bi bi-piggy-bank"></i> Economia Total
                </div>
                <div class="card-body">
                    <h3 class="card-title">R$ <?= number_format($economiaTotal ?: 0, 2, ',', '.') ?></h3>
                    <p class="card-text">Economia obtida em licitações homologadas</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-primary">
                <div class="card-header">
                    <i class="bi bi-graph-up"></i> Licitações Próximas
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?= count($licitacoesProximas) ?></h3>
                    <p class="card-text">Aberturas nos próximos 30 dias</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info">
                <div class="card-header">
                    <i class="bi bi-calendar-check"></i> Total de Processos
                </div>
                <div class="card-body">
                    <h3 class="card-title">
                        <?= array_sum(array_column($resumoPorSituacao, 'total')) ?>
                    </h3>
                    <p class="card-text">Licitações cadastradas no sistema</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos e Tabelas -->
    <div class="row">
        <!-- Resumo por Situação -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-pie-chart"></i> Resumo por Situação
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Situação</th>
                                    <th class="text-center">Qtd</th>
                                    <th class="text-end">Valor Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resumoPorSituacao)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Nenhum dado disponível</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resumoPorSituacao as $situacao): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $badgeClass = match($situacao['situacao']) {
                                                    'HOMOLOGADO' => 'bg-success',
                                                    'EM_ANDAMENTO' => 'bg-primary',
                                                    'PREPARACAO' => 'bg-warning',
                                                    'FRACASSADO', 'REVOGADO', 'CANCELADO' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                };
                                                ?>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $situacao['situacao'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?= $situacao['total'] ?></td>
                                            <td class="text-end">
                                                R$ <?= number_format($situacao['valor_total'] ?: 0, 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumo por Modalidade -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-bar-chart"></i> Resumo por Modalidade
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Modalidade</th>
                                    <th class="text-center">Qtd</th>
                                    <th class="text-end">Valor Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resumoPorModalidade)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Nenhum dado disponível</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resumoPorModalidade as $modalidade): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= ucfirst(str_replace('_', ' ', $modalidade['modalidade'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?= $modalidade['total'] ?></td>
                                            <td class="text-end">
                                                R$ <?= number_format($modalidade['valor_total'] ?: 0, 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Licitações Próximas ao Vencimento -->
    <div class="card">
        <div class="card-header bg-warning text-dark">
            <i class="bi bi-exclamation-triangle"></i> Licitações com Abertura Próxima (30 dias)
        </div>
        <div class="card-body">
            <?php if (empty($licitacoesProximas)): ?>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i> Não há licitações com abertura prevista para os próximos 30 dias.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>NUP</th>
                                <th>Modalidade</th>
                                <th>Objeto</th>
                                <th>Data Abertura</th>
                                <th>Dias Restantes</th>
                                <th>Situação</th>
                                <th>Responsável</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($licitacoesProximas as $licitacao): ?>
                                <?php
                                $dataAbertura = new DateTime($licitacao['data_abertura']);
                                $hoje = new DateTime();
                                $diasRestantes = $hoje->diff($dataAbertura)->days;
                                
                                $urgenciaClass = '';
                                if ($diasRestantes <= 7) {
                                    $urgenciaClass = 'table-danger';
                                } elseif ($diasRestantes <= 15) {
                                    $urgenciaClass = 'table-warning';
                                }
                                ?>
                                <tr class="<?= $urgenciaClass ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($licitacao['nup']) ?></strong>
                                        <?php if ($licitacao['numero_contratacao']): ?>
                                            <br><small class="text-muted">PCA: <?= htmlspecialchars($licitacao['numero_contratacao']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($licitacao['modalidade']) ?></span>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($licitacao['objeto']) ?>">
                                            <?= htmlspecialchars($licitacao['objeto']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= $dataAbertura->format('d/m/Y') ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $diasRestantes <= 7 ? 'bg-danger' : ($diasRestantes <= 15 ? 'bg-warning' : 'bg-primary') ?>">
                                            <?= $diasRestantes ?> dias
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = match($licitacao['situacao']) {
                                            'HOMOLOGADO' => 'bg-success',
                                            'EM_ANDAMENTO' => 'bg-primary',
                                            'PREPARACAO' => 'bg-warning',
                                            'FRACASSADO', 'REVOGADO', 'CANCELADO' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst(str_replace('_', ' ', $licitacao['situacao'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($licitacao['resp_instrucao']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>