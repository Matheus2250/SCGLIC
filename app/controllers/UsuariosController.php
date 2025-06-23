<?php
require_once __DIR__ . '/../helpers/auth.php';

class UsuariosController extends Controller
{
    private $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = $this->model('UsuarioModel');
    }

public function login()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $usuario = $this->usuarioModel->buscarPorEmail($email);
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario'] = $usuario;
            header('Location: ' . BASE_URL . 'home/index'); // Redireciona ao início
            exit;
        } else {
            $erro = 'E-mail ou senha inválidos.';
            $this->view('usuarios/login', ['erro' => $erro], 'Login', 'template_login');
            return;
        }
    }
    $this->view('usuarios/login', [], 'Login', 'template_login');
}





    public function logout()
    {
        session_destroy();
        header('Location: ' . BASE_URL . 'usuarios/login');
    }

    public function index()
    {
        requireCoordenador();
        $usuarios = $this->usuarioModel->buscarTodos();
        $this->view('usuarios/index', ['usuarios' => $usuarios], 'Usuários');
    }

    public function create()
    {
        requireCoordenador();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $dados = [
                'nome' => $_POST['nome'],
                'email' => $_POST['email'],
                'senha' => password_hash($_POST['senha'], PASSWORD_DEFAULT),
                'tipo_usuario' => $_POST['tipo_usuario'],
                'nivel_acesso' => $_POST['nivel_acesso'] ?? 4,
                'departamento' => $_POST['departamento'],
                'ativo' => 1
            ];
            $this->usuarioModel->inserir($dados);
            header('Location: ' . BASE_URL . 'usuarios/index');
        } else {
            $this->view('usuarios/create', [], 'Novo Usuário');
        }
    }

public function edit($id)
{
    requireCoordenador();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = [
            'id' => $id,
            'nome' => $_POST['nome'],
            'email' => $_POST['email'],
            'tipo_usuario' => $_POST['tipo_usuario'],
            'nivel_acesso' => $_POST['nivel_acesso'] ?? 4,
            'departamento' => $_POST['departamento'],
            'ativo' => 1
        ];
        $this->usuarioModel->atualizar($dados);
        header('Location: ' . BASE_URL . 'usuarios/index');
    } else {
        $usuario = $this->usuarioModel->buscarPorId($id);

        if (!$usuario) {
            echo "Usuário não encontrado.";
            return;
        }

        $this->view('usuarios/edit', ['usuario' => $usuario], 'Editar Usuário');
    }
}


    public function delete($id)
    {
        requireCoordenador();
        $this->usuarioModel->excluir($id);
        header('Location: ' . BASE_URL . 'usuarios/index');
    }

    public function store()
{
    // Verifica se veio via POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = [
            'nome' => $_POST['nome'] ?? '',
            'email' => $_POST['email'] ?? '',
            'senha' => password_hash($_POST['senha'] ?? '', PASSWORD_DEFAULT),
            'tipo_usuario' => $_POST['tipo_usuario'] ?? '',
            'departamento' => $_POST['departamento'] ?? '',
            'ativo' => 1
        ];

        $this->usuarioModel->inserir($dados);

        header("Location: " . BASE_URL . "usuarios/index");
        exit;
    }

    echo "Requisição inválida.";
}

public function registrar()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = [
            'nome' => $_POST['nome'],
            'email' => $_POST['email'],
            'senha' => password_hash($_POST['senha'], PASSWORD_DEFAULT),
            'tipo_usuario' => 'OPERADOR', // fixo
            'departamento' => 'NÃO INFORMADO', // ou 'EXTERNO'
            'ativo' => 1
        ];

        $this->usuarioModel->inserir($dados);
        header('Location: ' . BASE_URL . 'home/index');
        exit;
    }

    echo "Requisição inválida.";
}


}
