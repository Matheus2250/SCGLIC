<h3 class="mb-4">Planejamento de Contratações</h3>

<p>Bem-vindo ao módulo de Planejamento. Aqui você pode importar dados, visualizar contratações planejadas e acompanhar seu status.</p>

<?php if (podeImportarPCA()): ?>
    <a href="<?= BASE_URL ?>planejamento/importar" class="btn btn-primary mt-3">
        <i class="bi bi-upload"></i> Importar Dados CSV
    </a>
<?php else: ?>
    <div class="alert alert-info mt-3">
        <i class="bi bi-info-circle"></i> Você não tem permissão para importar dados do PCA. 
        Entre em contato com o coordenador se precisar dessa funcionalidade.
    </div>
<?php endif; ?>
