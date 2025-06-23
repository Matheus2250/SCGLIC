<?php $title = 'Usuários'; ?>
<h2>Usuários</h2>

<a href="<?= BASE_URL ?>usuarios/create" class="btn btn-success mb-3">Novo Usuário</a>

<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Email</th>
            <th>Tipo</th>
            <th>Nível</th>
            <th>Departamento</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= $u['nome'] ?></td>
                <td><?= $u['email'] ?></td>
                <td><?= ucfirst($u['tipo_usuario']) ?></td>
                <td>
                    <?php
                    $nivel = $u['nivel_acesso'] ?? 4;
                    $badgeClass = match($nivel) {
                        1 => 'bg-danger',
                        2 => 'bg-success', 
                        3 => 'bg-primary',
                        4 => 'bg-secondary',
                        default => 'bg-secondary'
                    };
                    $nomeNivel = match($nivel) {
                        1 => 'Coordenador',
                        2 => 'Planejamento',
                        3 => 'Licitação', 
                        4 => 'Visitante',
                        default => 'Desconhecido'
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= $nivel ?> - <?= $nomeNivel ?></span>
                </td>
                <td><?= $u['departamento'] ?></td>
                <td>
                    <a href="<?= BASE_URL ?>usuarios/edit/<?= $u['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                    <a href="<?= BASE_URL ?>usuarios/delete/<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deseja excluir este usuário?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
