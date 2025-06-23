<div class="container">
    <h3 class="mb-4">Importar Arquivo CSV</h3>

    <!-- Formulário de importação -->
    <form action="<?= BASE_URL ?>importacao/upload" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="csv" class="form-label">Escolha o arquivo CSV</label>
            <input type="file" name="csv" id="csv" class="form-control" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary">Importar</button>
    </form>

    <!-- Mensagem geral da importação -->
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-success mt-4"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <!-- Histórico de importações -->
    <?php if (!empty($importacoes)): ?>
        <div class="mt-5 card">
            <div class="card-header bg-secondary text-white">
                Histórico de Importações
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Arquivo</th>
                            <th>Ano PCA</th>
                            <th>Status</th>
                            <th>Usuário</th>
                            <th>Data/Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($importacoes as $imp): ?>
                            <tr>
                                <td><?= $imp['id'] ?></td>
                                <td><?= htmlspecialchars($imp['nome_arquivo']) ?></td>
                                <td><?= $imp['ano_pca'] ?></td>
                                <td><?= ucfirst($imp['status']) ?></td>
                                <td><?= htmlspecialchars($imp['nome_usuario']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($imp['data_hora'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
