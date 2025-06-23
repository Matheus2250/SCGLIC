<div class="container mt-4">
    <h2 class="mb-4">Bem-vindo, <?= $_SESSION['usuario']['nome'] ?></h2>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">

        <!-- Card Planejamento -->
        <div class="col">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <h5 class="card-title">Planejamento</h5>
                    <p class="card-text">Gerencie os planejamentos de contratação da sua área.</p>
                    <a href="<?= BASE_URL ?>planejamento/index" class="btn btn-primary">Acessar</a>
                </div>
            </div>
        </div>

        <!-- Exemplo de outros cards já existentes -->
        <div class="col">
            <div class="card border-success h-100">
                <div class="card-body">
                    <h5 class="card-title">Licitações</h5>
                    <p class="card-text">Visualize e acompanhe os processos licitatórios.</p>
                    <a href="<?= BASE_URL ?>licitacoes/index" class="btn btn-success">Acessar</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card border-warning h-100">
                <div class="card-body">
                    <h5 class="card-title">Contratos</h5>
                    <p class="card-text">Gerencie os contratos administrativos ativos.</p>
                    <a href="<?= BASE_URL ?>contratos/index" class="btn btn-warning">Acessar</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card border-dark h-100">
                <div class="card-body">
                    <h5 class="card-title">Usuários</h5>
                    <p class="card-text">Administre usuários e permissões do sistema.</p>
                    <a href="<?= BASE_URL ?>usuarios/index" class="btn btn-dark">Acessar</a>
                </div>
            </div>
        </div>

    </div>
</div>
