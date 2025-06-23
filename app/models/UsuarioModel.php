<?php
class UsuarioModel extends Model
{
    /* ========== CRUD BÁSICO ========== */

    /** Insere um novo usuário */
    public function inserir(array $dados): bool
    {
        $sql = "INSERT INTO usuarios 
                   (nome, email, senha, tipo_usuario, nivel_acesso, departamento, ativo)
                VALUES 
                   (:nome, :email, :senha, :tipo_usuario, :nivel_acesso, :departamento, :ativo)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nome'         => $dados['nome'],
            ':email'        => $dados['email'],
            ':senha'        => $dados['senha'],
            ':tipo_usuario' => $dados['tipo_usuario'],
            ':nivel_acesso' => $dados['nivel_acesso'] ?? 4,
            ':departamento' => $dados['departamento'],
            ':ativo'        => $dados['ativo']
        ]);
    }

    /** Retorna todos os usuários */
    public function buscarTodos(): array
    {
        $stmt = $this->db->query("SELECT * FROM usuarios");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Busca usuário pelo ID */
    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Busca usuário pelo e-mail */
    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Atualiza dados de um usuário existente */
    public function atualizar(array $dados): bool
    {
        $sql = "UPDATE usuarios SET
                   nome          = :nome,
                   email         = :email,
                   tipo_usuario  = :tipo_usuario,
                   nivel_acesso  = :nivel_acesso,
                   departamento  = :departamento,
                   ativo         = :ativo
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nome'         => $dados['nome'],
            ':email'        => $dados['email'],
            ':tipo_usuario' => $dados['tipo_usuario'],
            ':nivel_acesso' => $dados['nivel_acesso'] ?? 4,
            ':departamento' => $dados['departamento'],
            ':ativo'        => $dados['ativo'],
            ':id'           => $dados['id']
        ]);
    }

    /** Exclui um usuário */
    public function excluir(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
