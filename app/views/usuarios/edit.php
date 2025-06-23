<?php $title = 'Editar Usuário'; ?>

<h3>Editar Usuário</h3>

<?php if (!isset($usuario) || !$usuario): ?>
    <div class="alert alert-danger">Usuário não encontrado.</div>
<?php else: ?>
    <form method="POST" action="<?= BASE_URL ?>usuarios/edit/<?= $usuario['id'] ?>">
        <div class="mb-3">
            <label for="nome" class="form-label">Nome</label>
            <input type="text" class="form-control" id="nome" name="nome"
                value="<?= htmlspecialchars($usuario['nome']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email"
                value="<?= htmlspecialchars($usuario['email']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="tipo_usuario" class="form-label">Tipo</label>
            <select class="form-select" id="tipo_usuario" name="tipo_usuario" required>
                <option value="">Selecione...</option>
                <option value="ADMIN" <?= $usuario['tipo_usuario'] == 'ADMIN' ? 'selected' : '' ?>>ADMIN</option>
                <option value="OPERADOR" <?= $usuario['tipo_usuario'] == 'OPERADOR' ? 'selected' : '' ?>>OPERADOR</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="departamento" class="form-label">Departamento</label>
            <select class="form-select" id="departamento" name="departamento" required>
                <option value="">Selecione...</option>
                <option value="DIPLAN" <?= $usuario['departamento'] == 'DIPLAN' ? 'selected' : '' ?>>DIPLAN</option>
                <option value="DIPLI" <?= $usuario['departamento'] == 'DIPLI' ? 'selected' : '' ?>>DIPLI</option>
                <option value="DIQUALI" <?= $usuario['departamento'] == 'DIQUALI' ? 'selected' : '' ?>>DIQUALI</option>
                <option value="CCONT" <?= $usuario['departamento'] == 'CCONT' ? 'selected' : '' ?>>CCONT</option>
                <option value="COORDENACAO" <?= $usuario['departamento'] == 'COORDENACAO' ? 'selected' : '' ?>>COORDENACAO</option>
            </select>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Atualizar</button>
        </div>
    </form>
<?php endif; ?>
