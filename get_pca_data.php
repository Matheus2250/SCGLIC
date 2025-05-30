<?php
require_once 'config.php';
require_once 'functions.php';

verificarLogin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['erro' => 'ID não fornecido']);
    exit;
}

$id = intval($_GET['id']);
$pdo = conectarDB();

$sql = "SELECT numero_contratacao, titulo_contratacao, area_requisitante, 
        valor_total_contratacao, prioridade
        FROM pca_dados 
        WHERE id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode(['erro' => 'Dados não encontrados']);
}
?>