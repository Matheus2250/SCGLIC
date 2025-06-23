<?php
require_once __DIR__ . '/../helpers/auth.php';

class HomeController extends Controller
{
    public function index()
    {
        requireLogin();
        $usuario = $_SESSION['usuario'];
        $this->view('home/index', ['usuario' => $usuario], 'Início');
    }
}
