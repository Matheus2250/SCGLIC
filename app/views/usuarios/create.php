<?php $title = 'Novo Usuário'; ?>

<h3>Novo Usuário</h3>

<form method="POST" action="<?= BASE_URL ?>usuarios/store">
    <div class="mb-3">
        <label for="nome" class="form-label">Nome</label>
        <input type="text" class="form-control" id="nome" name="nome" required>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
        <input type="email" class="form-control" id="email" name="email" required>
    </div>

    <div class="mb-3">
        <label for="senha" class="form-label">Senha</label>
        <input type="password" class="form-control" id="senha" name="senha" required>
    </div>

    <div class="mb-3">
        <label for="tipo_usuario" class="form-label">Tipo</label>
        <select class="form-select" id="tipo_usuario" name="tipo_usuario" required>
            <option value="">Selecione...</option>
            <option value="ADMIN">ADMIN</option>
            <option value="OPERADOR">OPERACIONAL</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="departamento" class="form-label">Departamento</label>
        <select class="form-select" id="departamento" name="departamento" required>
            <option value="">Selecione...</option>
            <option value="DIPLAN">DIPLAN</option>
            <option value="DIPLI">DIPLI</option>
            <option value="DIQUALI">DIQUALI</option>
            <option value="CCONT">CCONT</option>
            <option value="COORDENACAO">COORDENACAO</option>
        </select>
    </div>

    <div class="d-grid">
        <button type="submit" class="btn btn-success">Salvar</button>
    </div>
</form>
