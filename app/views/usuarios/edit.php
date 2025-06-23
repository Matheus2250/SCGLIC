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
                <option value="admin" <?= $usuario['tipo_usuario'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="operador" <?= $usuario['tipo_usuario'] == 'operador' ? 'selected' : '' ?>>Operador</option>
                <option value="visitante" <?= $usuario['tipo_usuario'] == 'visitante' ? 'selected' : '' ?>>Visitante</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="nivel_acesso" class="form-label">Nível de Acesso</label>
            <select class="form-select" id="nivel_acesso" name="nivel_acesso" required>
                <option value="">Selecione...</option>
                <option value="1" <?= ($usuario['nivel_acesso'] ?? 4) == 1 ? 'selected' : '' ?>>1 - Coordenador (Acesso Total)</option>
                <option value="2" <?= ($usuario['nivel_acesso'] ?? 4) == 2 ? 'selected' : '' ?>>2 - Planejamento (PCA + Relatórios)</option>
                <option value="3" <?= ($usuario['nivel_acesso'] ?? 4) == 3 ? 'selected' : '' ?>>3 - Licitação (Licitações + Relatórios)</option>
                <option value="4" <?= ($usuario['nivel_acesso'] ?? 4) == 4 ? 'selected' : '' ?>>4 - Visitante (Apenas Relatórios)</option>
            </select>
            <div class="form-text">
                <strong>Níveis:</strong><br>
                • <strong>Coordenador:</strong> Pode fazer tudo, incluindo gerenciar usuários<br>
                • <strong>Planejamento:</strong> Pode importar PCA, visualizar licitações e gerar relatórios<br>
                • <strong>Licitação:</strong> Pode gerenciar licitações, visualizar planejamento e gerar relatórios<br>
                • <strong>Visitante:</strong> Apenas visualizar e gerar relatórios
            </div>
        </div>

        <div class="mb-3">
            <label for="departamento" class="form-label">Departamento</label>
            <select class="form-select" id="departamento" name="departamento" required>
                <option value="">Selecione...</option>
                <option value="CGLIC" <?= $usuario['departamento'] == 'CGLIC' ? 'selected' : '' ?>>CGLIC</option>
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
