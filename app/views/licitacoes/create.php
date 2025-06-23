<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Nova Licitação</h3>
        <a href="<?= BASE_URL ?>licitacoes/index" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <form action="<?= BASE_URL ?>licitacoes/store" method="POST" id="formLicitacao">
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
                                                data-objeto="<?= htmlspecialchars($pca['titulo_contratacao']) ?>">
                                            <?= htmlspecialchars($pca['numero_contratacao']) ?> - 
                                            <?= htmlspecialchars(substr($pca['titulo_contratacao'], 0, 50)) ?>...
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="btnBuscarPca">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                Selecione o número da contratação do PCA para puxar as informações automaticamente
                            </div>
                        </div>

                        <!-- NUP -->
                        <div class="mb-3">
                            <label for="nup" class="form-label">NUP (Número Único de Protocolo)</label>
                            <input type="text" class="form-control" name="nup" id="nup" 
                                   placeholder="Ex: 25000.123456/2024-12">
                        </div>

                        <!-- Data de Entrada DIPLI -->
                        <div class="mb-3">
                            <label for="data_entrada_dipli" class="form-label">Data de Entrada na DIPLI</label>
                            <input type="date" class="form-control" name="data_entrada_dipli" id="data_entrada_dipli">
                        </div>

                        <!-- Responsável pela Instrução -->
                        <div class="mb-3">
                            <label for="resp_instrucao" class="form-label">Responsável pela Instrução</label>
                            <input type="text" class="form-control" name="resp_instrucao" id="resp_instrucao">
                        </div>

                        <!-- Área Demandante (será preenchida automaticamente) -->
                        <div class="mb-3">
                            <label for="area_demandante" class="form-label">Área Demandante</label>
                            <input type="text" class="form-control" name="area_demandante" id="area_demandante" readonly>
                            <div class="form-text">Preenchido automaticamente ao selecionar a contratação do PCA</div>
                        </div>

                        <!-- Pregoeiro -->
                        <div class="mb-3">
                            <label for="pregoeiro" class="form-label">Pregoeiro</label>
                            <input type="text" class="form-control" name="pregoeiro" id="pregoeiro">
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
                                <option value="PREGAO">Pregão</option>
                                <option value="DISPENSA">Dispensa</option>
                                <option value="INEXIGIBILIDADE">Inexigibilidade</option>
                                <option value="CONCORRENCIA">Concorrência</option>
                                <option value="TOMADA_PRECOS">Tomada de Preços</option>
                                <option value="CONVITE">Convite</option>
                            </select>
                        </div>

                        <!-- Tipo -->
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo</label>
                            <select class="form-select" name="tipo" id="tipo">
                                <option value="">Selecione...</option>
                                <option value="TRADICIONAL">Tradicional</option>
                                <option value="SRP">Sistema de Registro de Preços</option>
                                <option value="COTACAO">Cotação</option>
                            </select>
                        </div>

                        <!-- Número e Ano -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="numero" class="form-label">Número</label>
                                    <input type="number" class="form-control" name="numero" id="numero">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ano" class="form-label">Ano</label>
                                    <input type="number" class="form-control" name="ano" id="ano" 
                                           value="<?= date('Y') ?>" min="2020" max="2030">
                                </div>
                            </div>
                        </div>

                        <!-- Objeto (será preenchido automaticamente) -->
                        <div class="mb-3">
                            <label for="objeto" class="form-label">Objeto</label>
                            <textarea class="form-control" name="objeto" id="objeto" rows="3"></textarea>
                            <div class="form-text">Preenchido automaticamente ao selecionar a contratação do PCA</div>
                        </div>

                        <!-- Valor Estimado (será preenchido automaticamente) -->
                        <div class="mb-3">
                            <label for="valor_estimado" class="form-label">Valor Estimado</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control" name="valor_estimado" id="valor_estimado" 
                                       placeholder="0,00" readonly>
                            </div>
                            <div class="form-text">Preenchido automaticamente ao selecionar a contratação do PCA</div>
                        </div>

                        <!-- Quantidade de Itens -->
                        <div class="mb-3">
                            <label for="qtd_itens" class="form-label">Quantidade de Itens</label>
                            <input type="number" class="form-control" name="qtd_itens" id="qtd_itens" min="1">
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
                            <input type="date" class="form-control" name="data_abertura" id="data_abertura">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="data_publicacao" class="form-label">Data de Publicação</label>
                            <input type="date" class="form-control" name="data_publicacao" id="data_publicacao">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="data_homologacao" class="form-label">Data de Homologação</label>
                            <input type="date" class="form-control" name="data_homologacao" id="data_homologacao">
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
                                <option value="PREPARACAO">Preparação</option>
                                <option value="EM_ANDAMENTO">Em Andamento</option>
                                <option value="HOMOLOGADO">Homologado</option>
                                <option value="FRACASSADO">Fracassado</option>
                                <option value="REVOGADO">Revogado</option>
                                <option value="CANCELADO">Cancelado</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="valor_homologado" class="form-label">Valor Homologado</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control" name="valor_homologado" id="valor_homologado" 
                                       placeholder="0,00">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="qtd_homol" class="form-label">Quantidade Homologada</label>
                            <input type="number" class="form-control" name="qtd_homol" id="qtd_homol" min="0">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="link" class="form-label">Link para Documentos</label>
                            <input type="url" class="form-control" name="link" id="link" 
                                   placeholder="https://...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="numero_processo" class="form-label">Número do Processo</label>
                            <input type="text" class="form-control" name="numero_processo" id="numero_processo">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" id="observacoes" rows="3"></textarea>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="<?= BASE_URL ?>licitacoes/index" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Salvar Licitação
            </button>
        </div>

        <!-- Campos ocultos -->
        <input type="hidden" name="pca_dados_id" id="pca_dados_id">
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
        } else {
            // Limpar campos se não houver seleção
            document.getElementById('area_demandante').value = '';
            document.getElementById('objeto').value = '';
            document.getElementById('valor_estimado').value = '';
        }
    });

    // Função para buscar dados via AJAX (caso o usuário digite um número não listado)
    btnBuscarPca.addEventListener('click', function() {
        const numeroContratacao = numeroContratacaoSelect.value;
        if (!numeroContratacao) {
            alert('Selecione um número de contratação primeiro.');
            return;
        }

        // Fazer requisição AJAX
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
                
                if (data.ja_existe_licitacao) {
                    alert('Atenção: Já existe uma licitação cadastrada para esta contratação!');
                }
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
    document.getElementById('valor_homologado').addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        value = (value / 100).toFixed(2);
        this.value = value.replace('.', ',');
    });
});
</script>