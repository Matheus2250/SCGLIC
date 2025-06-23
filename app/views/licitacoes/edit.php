<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Editar Licitação</h3>
        <a href="<?= BASE_URL ?>licitacoes/index" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <form action="<?= BASE_URL ?>licitacoes/update/<?= $licitacao['id'] ?>" method="POST" id="formLicitacao">
        <div class="row">
            <!-- Coluna Esquerda -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-info-circle"></i> Informações Básicas
                    </div>
                    <div class="card-body">
                        <!-- Número da Contratação (Busca PCA) -->
                        <div class="mb-3">
                            <label for="numero_contratacao" class="form-label">
                                Número da Contratação (PCA) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <select class="form-select" name="numero_contratacao" id="numero_contratacao" required>
                                    <option value="">Selecione o número da contratação...</option>
                                    <?php foreach ($numerosContratacao as $pca): ?>
                                        <option value="<?= htmlspecialchars($pca['numero_contratacao']) ?>" 
                                                data-valor="<?= $pca['valor_total_contratacao'] ?>"
                                                data-area="<?= htmlspecialchars($pca['area_requisitante']) ?>"
                                                data-objeto="<?= htmlspecialchars($pca['titulo_contratacao']) ?>"
                                                <?= $pca['numero_contratacao'] === $licitacao['numero_contratacao'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pca['numero_contratacao']) ?> - 
                                            <?= htmlspecialchars(substr($pca['titulo_contratacao'], 0, 50)) ?>...
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="btnBuscarPca">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- NUP -->
                        <div class="mb-3">
                            <label for="nup" class="form-label">NUP (Número Único de Protocolo)</label>
                            <input type="text" class="form-control" name="nup" id="nup" 
                                   value="<?= htmlspecialchars($licitacao['nup']) ?>"
                                   placeholder="Ex: 25000.123456/2024-12">
                        </div>

                        <!-- Data de Entrada DIPLI -->
                        <div class="mb-3">
                            <label for="data_entrada_dipli" class="form-label">Data de Entrada na DIPLI</label>
                            <input type="date" class="form-control" name="data_entrada_dipli" id="data_entrada_dipli"
                                   value="<?= $licitacao['data_entrada_dipli'] ?>">
                        </div>

                        <!-- Responsável pela Instrução -->
                        <div class="mb-3">
                            <label for="resp_instrucao" class="form-label">Responsável pela Instrução</label>
                            <input type="text" class="form-control" name="resp_instrucao" id="resp_instrucao"
                                   value="<?= htmlspecialchars($licitacao['resp_instrucao']) ?>">
                        </div>

                        <!-- Área Demandante -->
                        <div class="mb-3">
                            <label for="area_demandante" class="form-label">Área Demandante</label>
                            <input type="text" class="form-control" name="area_demandante" id="area_demandante"
                                   value="<?= htmlspecialchars($licitacao['area_demandante']) ?>">
                        </div>

                        <!-- Pregoeiro -->
                        <div class="mb-3">
                            <label for="pregoeiro" class="form-label">Pregoeiro</label>
                            <input type="text" class="form-control" name="pregoeiro" id="pregoeiro"
                                   value="<?= htmlspecialchars($licitacao['pregoeiro']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-file-earmark-text"></i> Detalhes da Licitação
                    </div>
                    <div class="card-body">
                        <!-- Modalidade -->
                        <div class="mb-3">
                            <label for="modalidade" class="form-label">Modalidade <span class="text-danger">*</span></label>
                            <select class="form-select" name="modalidade" id="modalidade" required>
                                <option value="">Selecione...</option>
                                <option value="PREGAO" <?= $licitacao['modalidade'] === 'PREGAO' ? 'selected' : '' ?>>Pregão</option>
                                <option value="DISPENSA" <?= $licitacao['modalidade'] === 'DISPENSA' ? 'selected' : '' ?>>Dispensa</option>
                                <option value="INEXIGIBILIDADE" <?= $licitacao['modalidade'] === 'INEXIGIBILIDADE' ? 'selected' : '' ?>>Inexigibilidade</option>
                                <option value="CONCORRENCIA" <?= $licitacao['modalidade'] === 'CONCORRENCIA' ? 'selected' : '' ?>>Concorrência</option>
                                <option value="TOMADA_PRECOS" <?= $licitacao['modalidade'] === 'TOMADA_PRECOS' ? 'selected' : '' ?>>Tomada de Preços</option>
                                <option value="CONVITE" <?= $licitacao['modalidade'] === 'CONVITE' ? 'selected' : '' ?>>Convite</option>
                            </select>
                        </div>

                        <!-- Tipo -->
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo</label>
                            <select class="form-select" name="tipo" id="tipo">
                                <option value="">Selecione...</option>
                                <option value="TRADICIONAL" <?= $licitacao['tipo'] === 'TRADICIONAL' ? 'selected' : '' ?>>Tradicional</option>
                                <option value="SRP" <?= $licitacao['tipo'] === 'SRP' ? 'selected' : '' ?>>Sistema de Registro de Preços</option>
                                <option value="COTACAO" <?= $licitacao['tipo'] === 'COTACAO' ? 'selected' : '' ?>>Cotação</option>
                            </select>
                        </div>

                        <!-- Número e Ano -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="numero" class="form-label">Número</label>
                                    <input type="number" class="form-control" name="numero" id="numero"
                                           value="<?= $licitacao['numero'] ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ano" class="form-label">Ano</label>
                                    <input type="number" class="form-control" name="ano" id="ano" 
                                           value="<?= $licitacao['ano'] ?: date('Y') ?>" min="2020" max="2030">
                                </div>
                            </div>
                        </div>

                        <!-- Objeto -->
                        <div class="mb-3">
                            <label for="objeto" class="form-label">Objeto</label>
                            <textarea class="form-control" name="objeto" id="objeto" rows="3"><?= htmlspecialchars($licitacao['objeto']) ?></textarea>
                        </div>

                        <!-- Valor Estimado -->
                        <div class="mb-3">
                            <label for="valor_estimado" class="form-label">Valor Estimado</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control" name="valor_estimado" id="valor_estimado" 
                                       value="<?= $licitacao['valor_estimado'] ? number_format($licitacao['valor_estimado'], 2, ',', '.') : '' ?>"
                                       placeholder="0,00">
                            </div>
                        </div>

                        <!-- Quantidade de Itens -->
                        <div class="mb-3">
                            <label for="qtd_itens" class="form-label">Quantidade de Itens</label>
                            <input type="number" class="form-control" name="qtd_itens" id="qtd_itens" 
                                   value="<?= $licitacao['qtd_itens'] ?>" min="1">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Datas -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-calendar-event"></i> Cronograma
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="data_abertura" class="form-label">Data de Abertura</label>
                            <input type="date" class="form-control" name="data_abertura" id="data_abertura"
                                   value="<?= $licitacao['data_abertura'] ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="data_publicacao" class="form-label">Data de Publicação</label>
                            <input type="date" class="form-control" name="data_publicacao" id="data_publicacao"
                                   value="<?= $licitacao['data_publicacao'] ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="data_homologacao" class="form-label">Data de Homologação</label>
                            <input type="date" class="form-control" name="data_homologacao" id="data_homologacao"
                                   value="<?= $licitacao['data_homologacao'] ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Resultado -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-trophy"></i> Resultado
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="situacao" class="form-label">Situação</label>
                            <select class="form-select" name="situacao" id="situacao">
                                <option value="PREPARACAO" <?= $licitacao['situacao'] === 'PREPARACAO' ? 'selected' : '' ?>>Preparação</option>
                                <option value="EM_ANDAMENTO" <?= $licitacao['situacao'] === 'EM_ANDAMENTO' ? 'selected' : '' ?>>Em Andamento</option>
                                <option value="HOMOLOGADO" <?= $licitacao['situacao'] === 'HOMOLOGADO' ? 'selected' : '' ?>>Homologado</option>
                                <option value="FRACASSADO" <?= $licitacao['situacao'] === 'FRACASSADO' ? 'selected' : '' ?>>Fracassado</option>
                                <option value="REVOGADO" <?= $licitacao['situacao'] === 'REVOGADO' ? 'selected' : '' ?>>Revogado</option>
                                <option value="CANCELADO" <?= $licitacao['situacao'] === 'CANCELADO' ? 'selected' : '' ?>>Cancelado</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="valor_homologado" class="form-label">Valor Homologado</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control" name="valor_homologado" id="valor_homologado" 
                                       value="<?= $licitacao['valor_homologado'] ? number_format($licitacao['valor_homologado'], 2, ',', '.') : '' ?>"
                                       placeholder="0,00">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="qtd_homol" class="form-label">Quantidade Homologada</label>
                            <input type="number" class="form-control" name="qtd_homol" id="qtd_homol" 
                                   value="<?= $licitacao['qtd_homol'] ?>" min="0">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="link" class="form-label">Link para Documentos</label>
                            <input type="url" class="form-control" name="link" id="link" 
                                   value="<?= htmlspecialchars($licitacao['link']) ?>"
                                   placeholder="https://...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="numero_processo" class="form-label">Número do Processo</label>
                            <input type="text" class="form-control" name="numero_processo" id="numero_processo"
                                   value="<?= htmlspecialchars($licitacao['numero_processo']) ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" id="observacoes" rows="3"><?= htmlspecialchars($licitacao['observacoes']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="<?= BASE_URL ?>licitacoes/index" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Salvar Alterações
            </button>
        </div>

        <!-- Campos ocultos -->
        <input type="hidden" name="pca_dados_id" id="pca_dados_id" value="<?= $licitacao['pca_dados_id'] ?>">
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const numeroContratacaoSelect = document.getElementById('numero_contratacao');
    const btnBuscarPca = document.getElementById('btnBuscarPca');
    
    // Função para preencher campos automaticamente quando selecionar da lista
    numeroContratacaoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            document.getElementById('area_demandante').value = selectedOption.dataset.area || '';
            document.getElementById('objeto').value = selectedOption.dataset.objeto || '';
            document.getElementById('valor_estimado').value = formatMoney(selectedOption.dataset.valor || '');
        }
    });

    // Função para buscar dados via AJAX
    btnBuscarPca.addEventListener('click', function() {
        const numeroContratacao = numeroContratacaoSelect.value;
        if (!numeroContratacao) {
            alert('Selecione um número de contratação primeiro.');
            return;
        }

        fetch('<?= BASE_URL ?>licitacoes/buscarDadosPca', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'numero_contratacao=' + encodeURIComponent(numeroContratacao)
        })
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                document.getElementById('area_demandante').value = data.dados.area_requisitante || '';
                document.getElementById('objeto').value = data.dados.objeto || '';
                document.getElementById('valor_estimado').value = formatMoney(data.dados.valor_estimado || '');
                document.getElementById('pca_dados_id').value = data.dados.pca_dados_id || '';
            } else {
                alert('Erro: ' + data.erro);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao buscar dados do PCA.');
        });
    });

    // Função para formatar valores monetários
    function formatMoney(value) {
        if (!value) return '';
        return parseFloat(value).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Formatação automática de valores monetários
    const camposValor = ['valor_estimado', 'valor_homologado'];
    camposValor.forEach(campo => {
        document.getElementById(campo).addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value) {
                value = (value / 100).toFixed(2);
                this.value = value.replace('.', ',');
            }
        });
    });
});
</script>