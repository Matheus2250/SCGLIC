<?php $title = 'Login'; ?>

<div class="card shadow-sm p-4">
    <div class="card-body">
        <h4 class="card-title text-center mb-4">Acesso ao Sistema</h4>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger"><?= $erro ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" name="email" id="email" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
                <label for="senha" class="form-label">Senha</label>
                <input type="password" name="senha" id="senha" class="form-control" required>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Entrar</button>
                <div class="text-center mt-3">
                <div class="text-center mt-3">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#modalCadastro" class="text-decoration-none">Não tem uma conta? Cadastre-se</a>
                </div>

            </div>
        </form>
        <!-- Modal de Cadastro -->
<div class="modal fade" id="modalCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="<?= BASE_URL ?>usuarios/registrar">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCadastroLabel">Cadastro de Novo Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome completo</label>
                    <input type="text" name="nome" id="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="email_cad" class="form-label">E-mail</label>
                    <input type="email" name="email" id="email_cad" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="senha_cad" class="form-label">Senha</label>
                    <input type="password" name="senha" id="senha_cad" class="form-control" required>
                </div>
                <!-- Bootstrap Bundle com Popper.js (necessário para modal) -->
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Cadastrar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </form>
  </div>
</div>

    </div>
</div>
