<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Detalhes da Licitação</h3>
        <div>
            <?php if (podeEditarLicitacoes()): ?>
                <a href="<?= BASE_URL ?>licitacoes/edit/<?= $licitacao['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>licitacoes/index" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Coluna Esquerda - Informações Básicas -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle"></i> Informações Básicas
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>NUP:</strong></div>
                        <div class="col-sm-8"><?= htmlspecialchars($licitacao['nup']) ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Número da Contratação:</strong></div>
                        <div class="col-sm-8">
                            <?php if ($licitacao['numero_contratacao']): ?>
                                <span class="badge bg-info"><?= htmlspecialchars($licitacao['numero_contratacao']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Data Entrada DIPLI:</strong></div>
                        <div class="col-sm-8">
                            <?= $licitacao['data_entrada_dipli'] ? date('d/m/Y', strtotime($licitacao['data_entrada_dipli'])) : '-' ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Responsável Instrução:</strong></div>
                        <div class="col-sm-8"><?= htmlspecialchars($licitacao['resp_instrucao']) ?: '-' ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Área Demandante:</strong></div>
                        <div class="col-sm-8"><?= htmlspecialchars($licitacao['area_demandante']) ?: '-' ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Pregoeiro:</strong></div>
                        <div class="col-sm-8"><?= htmlspecialchars($licitacao['pregoeiro']) ?: '-' ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Usuário Criador:</strong></div>
                        <div class="col-sm-8"><?= htmlspecialchars($licitacao['nome_usuario']) ?: '-' ?></div>
                    </div>
                </div>
            </div>

            <!-- Cronograma -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-calendar-event"></i> Cronograma
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Data Abertura:</strong></div>
                        <div class="col-sm-6">
                            <?= $licitacao['data_abertura'] ? date('d/m/Y', strtotime($licitacao['data_abertura'])) : '-' ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Data Publicação:</strong></div>
                        <div class="col-sm-6">
                            <?= $licitacao['data_publicacao'] ? date('d/m/Y', strtotime($licitacao['data_publicacao'])) : '-' ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Data Homologação:</strong></div>
                        <div class="col-sm-6">
                            <?= $licitacao['data_homologacao'] ? date('d/m/Y', strtotime($licitacao['data_homologacao'])) : '-' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna Direita - Detalhes da Licitação -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-file-earmark-text"></i> Detalhes da Licitação
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Modalidade:</strong></div>
                        <div class="col-sm-8">
                            <span class="badge bg-secondary"><?= htmlspecialchars($licitacao['modalidade']) ?></span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Tipo:</strong></div>
                        <div class="col-sm-8"><?= htmlspecialchars($licitacao['tipo']) ?: '-' ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Número/Ano:</strong></div>
                        <div class="col-sm-8">
                            <?php if ($licitacao['numero'] && $licitacao['ano']): ?>
                                <?= $licitacao['numero'] ?>/<?= $licitacao['ano'] ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Situação:</strong></div>
                        <div class="col-sm-8">
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
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Qtd Itens:</strong></div>
                        <div class="col-sm-8"><?= $licitacao['qtd_itens'] ?: '-' ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Número Processo:</strong></div>
                        <div class="col-sm-8"><?= htmlspecialchars($licitacao['numero_processo']) ?: '-' ?></div>
                    </div>
                </div>
            </div>

            <!-- Valores -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-currency-dollar"></i> Valores
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Valor Estimado:</strong></div>
                        <div class="col-sm-6">
                            <?php if ($licitacao['valor_estimado']): ?>
                                <span class="text-success fw-bold">
                                    R$ <?= number_format($licitacao['valor_estimado'], 2, ',', '.') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Valor Homologado:</strong></div>
                        <div class="col-sm-6">
                            <?php if ($licitacao['valor_homologado']): ?>
                                <span class="text-primary fw-bold">
                                    R$ <?= number_format($licitacao['valor_homologado'], 2, ',', '.') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($licitacao['valor_estimado'] && $licitacao['valor_homologado']): ?>
                        <?php $economia = $licitacao['valor_estimado'] - $licitacao['valor_homologado']; ?>
                        <div class="row mb-3">
                            <div class="col-sm-6"><strong>Economia:</strong></div>
                            <div class="col-sm-6">
                                <span class="<?= $economia > 0 ? 'text-success' : ($economia < 0 ? 'text-danger' : 'text-muted') ?> fw-bold">
                                    R$ <?= number_format($economia, 2, ',', '.') ?>
                                    <?php if ($economia > 0): ?>
                                        <i class="bi bi-arrow-down-circle text-success"></i>
                                    <?php elseif ($economia < 0): ?>
                                        <i class="bi bi-arrow-up-circle text-danger"></i>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-6"><strong>% Economia:</strong></div>
                            <div class="col-sm-6">
                                <?php $percentual = ($economia / $licitacao['valor_estimado']) * 100; ?>
                                <span class="<?= $economia > 0 ? 'text-success' : ($economia < 0 ? 'text-danger' : 'text-muted') ?> fw-bold">
                                    <?= number_format($percentual, 2, ',', '.') ?>%
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Qtd Homologada:</strong></div>
                        <div class="col-sm-6"><?= $licitacao['qtd_homol'] ?: '-' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Objeto -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-file-text"></i> Objeto da Licitação
        </div>
        <div class="card-body">
            <p class="mb-0">
                <?= $licitacao['objeto'] ? nl2br(htmlspecialchars($licitacao['objeto'])) : '<span class="text-muted">Não informado</span>' ?>
            </p>
        </div>
    </div>

    <!-- Observações -->
    <?php if ($licitacao['observacoes']): ?>
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <i class="bi bi-chat-square-text"></i> Observações
            </div>
            <div class="card-body">
                <p class="mb-0"><?= nl2br(htmlspecialchars($licitacao['observacoes'])) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Link para Documentos -->
    <?php if ($licitacao['link']): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-link-45deg"></i> Documentos
            </div>
            <div class="card-body">
                <a href="<?= htmlspecialchars($licitacao['link']) ?>" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right"></i> Acessar Documentos
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Informações de Sistema -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <i class="bi bi-info-circle"></i> Informações do Sistema
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        <strong>Criado em:</strong> 
                        <?= date('d/m/Y H:i', strtotime($licitacao['criado_em'])) ?>
                    </small>
                </div>
                <div class="col-md-6">
                    <small class="text-muted">
                        <strong>Atualizado em:</strong> 
                        <?= date('d/m/Y H:i', strtotime($licitacao['atualizado_em'])) ?>
                    </small>
                </div>
            </div>
            <?php if ($licitacao['pca_dados_id']): ?>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <small class="text-muted">
                            <strong>Vinculado ao PCA ID:</strong> <?= $licitacao['pca_dados_id'] ?>
                        </small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>