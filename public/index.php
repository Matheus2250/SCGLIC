<?php
session_start();

/* ========= Requires fundamentais ========= */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/View.php';

/* ========= Autoload recursivo (controllers e models) ========= */
spl_autoload_register(function ($class) {
    $baseDirs = [__DIR__ . '/../app/controllers', __DIR__ . '/../app/models'];

    foreach ($baseDirs as $base) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getFilename() === $class . '.php') {
                require_once $file->getPathname();
                return;
            }
        }
    }
});

/* ========= Captura e sanitiza a URL ========= */
$urlRaw = $_GET['url'] ?? '';                         // ex.: usuarios/edit/3
$urlRaw = rtrim(filter_var($urlRaw, FILTER_SANITIZE_URL), '/');

/* ========= Rota padrão (dashboard) ========= */
if ($urlRaw === '') {                                 // acessou /public/ direto
    $home = new HomeController();
    $home->index();
    exit;
}

/* ========= Processa a rota convencional ========= */
$parts          = explode('/', $urlRaw);
$controllerName = ucfirst($parts[0]) . 'Controller';  // UsuariosController, etc.
$method         = $parts[1] ?? 'index';
$args           = array_slice($parts, 2);             // parâmetros adicionais

if (class_exists($controllerName) && method_exists($controllerName, $method)) {
    $controller = new $controllerName();
    call_user_func_array([$controller, $method], $args);
} else {
    http_response_code(404);
    echo "Página não encontrada.";
}
