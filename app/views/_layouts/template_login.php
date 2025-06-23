<!DOCTYPE html>
<html lang="pt-br">
<?php
// Define BASE_URL se ainda não estiver disponível
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://10.1.41.251:8080/sistema_licitacoes/public/');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?? 'Login' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?= BASE_URL ?>css/bootstrap.min.css" rel="stylesheet">
    <script src="<?= BASE_URL ?>js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f1f1f1;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <?php require_once $viewPath; ?>
            </div>
        </div>
    </div>
</body>
</html>
