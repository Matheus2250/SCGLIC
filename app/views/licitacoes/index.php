<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Gestão de Licitações</h3>
        <div>
            <?php if (podeEditarLicitacoes()): ?>
                <a href="<?= BASE_URL ?>licitacoes/create" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nova Licitação
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>licitacoes/relatorios" class="btn btn-info">
                <i class="bi bi-graph-up"></i> Relatórios
            </a>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-pie-chart"></i> Resumo por Situação
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($resumoPorSituacao as $situacao): ?>
                            <div class="col-md-4 mb-2">
                                <div class="border rounded p-2 text-center">
                                    <strong><?= ucfirst(str_replace('_', ' ', $situacao['situacao'])) ?></strong><br>
                                    <span class="text-muted"><?= $situacao['total'] ?> processos</span><br>
                                    <small class="text-success">R$ <?= number_format($situacao['valor_total'] ?: 0, 2, ',', '.') ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-piggy-bank"></i> Economia Total
                </div>
                <div class="card-body text-center">
                    <h4 class="text-success">
                        R$ <?= number_format($economiaTotal ?: 0, 2, ',', '.') ?>
                    </h4>
                    <small class="text-muted">Em licitações homologadas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Licitações -->
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <i class="bi bi-list-ul"></i> Lista de Licitações
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>NUP</th>
                            <th>Modalidade</th>
                            <th>Objeto</th>
                            <th>Valor Estimado</th>
                            <th>Data Abertura</th>
                            <th>Situação</th>
                            <th>Responsável</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($licitacoes)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Nenhuma licitação cadastrada
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($licitacoes as $licitacao): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($licitacao['nup']) ?></strong>
                                        <?php if ($licitacao['numero_contratacao']): ?>
                                            <br><small class="text-muted">PCA: <?= htmlspecialchars($licitacao['numero_contratacao']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($licitacao['modalidade']) ?></span>
                                        <?php if ($licitacao['tipo']): ?>
                                            <br><small><?= htmlspecialchars($licitacao['tipo']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($licitacao['objeto']) ?>">
                                            <?= htmlspecialchars($licitacao['objeto']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($licitacao['valor_estimado']): ?>
                                            R$ <?= number_format($licitacao['valor_estimado'], 2, ',', '.') ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($licitacao['data_abertura']): ?>
                                            <?= date('d/m/Y', strtotime($licitacao['data_abertura'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
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
                                        <?php if ($licitacao['resp_instrucao']): ?>
                                            <?= htmlspecialchars($licitacao['resp_instrucao']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= BASE_URL ?>licitacoes/details/<?= $licitacao['id'] ?>" 
                                               class="btn btn-outline-info" title="Ver Detalhes">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if (podeEditarLicitacoes()): ?>
                                                <a href="<?= BASE_URL ?>licitacoes/edit/<?= $licitacao['id'] ?>" 
                                                   class="btn btn-outline-primary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="<?= BASE_URL ?>licitacoes/delete/<?= $licitacao['id'] ?>" 
                                                   class="btn btn-outline-danger" title="Excluir"
                                                   onclick="return confirm('Tem certeza que deseja excluir esta licitação?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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