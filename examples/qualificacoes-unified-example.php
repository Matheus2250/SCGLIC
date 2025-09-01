<?php
/**
 * Exemplo de implementação do sistema unificado para Qualificações
 * Este arquivo mostra como usar o template unificado
 */

require_once '../config.php';
require_once '../functions.php';

verificarLogin();

// Configuração específica para Qualificações
$listType = 'qualificacoes';
$listTitle = 'Gestão de Qualificações';
$listIcon = 'clipboard-list';
$createPermission = temPermissao('qualificacao_criar');
$exportPermission = temPermissao('qualificacao_exportar');

// Configuração dos filtros
$filters = [
    [
        'name' => 'busca',
        'type' => 'text',
        'label' => 'Pesquisar',
        'placeholder' => 'NUP, objeto, responsável...'
    ],
    [
        'name' => 'status',
        'type' => 'select',
        'label' => 'Status',
        'options' => [
            'PENDENTE' => 'Pendente',
            'EM ANÁLISE' => 'Em Análise',
            'CONCLUÍDO' => 'Concluído',
            'CANCELADO' => 'Cancelado'
        ]
    ],
    [
        'name' => 'modalidade',
        'type' => 'select',
        'label' => 'Modalidade',
        'options' => [
            'PREGÃO' => 'Pregão',
            'CONCURSO' => 'Concurso',
            'CONCORRÊNCIA' => 'Concorrência',
            'INEXIGIBILIDADE' => 'Inexigibilidade',
            'DISPENSA' => 'Dispensa',
            'ADESÃO' => 'Adesão'
        ]
    ],
    [
        'name' => 'area_demandante',
        'type' => 'select',
        'label' => 'Área Demandante',
        'options' => [
            'DIPLAN' => 'DIPLAN',
            'DIPLI' => 'DIPLI',
            'CGLIC' => 'CGLIC',
            'OUTRAS' => 'Outras'
        ]
    ],
    [
        'name' => 'data_criacao',
        'type' => 'date-range',
        'label' => 'Data de Criação'
    ]
];

// Dados mockados para exemplo (normalmente viriam do banco)
$items = [
    [
        'id' => 1,
        'nup' => '25052.123456/2024-11',
        'area_demandante' => 'DIPLAN',
        'responsavel' => 'João Silva',
        'modalidade' => 'PREGÃO',
        'objeto' => 'Aquisição de equipamentos de informática para modernização do parque tecnológico',
        'status' => 'EM ANÁLISE',
        'numero_contratacao' => '2024/001',
        'valor_estimado' => 150000.00,
        'criado_em' => '2024-01-15'
    ],
    [
        'id' => 2,
        'nup' => '25052.789012/2024-11',
        'area_demandante' => 'DIPLI',
        'responsavel' => 'Maria Santos',
        'modalidade' => 'CONCORRÊNCIA',
        'objeto' => 'Contratação de serviços de consultoria especializada em gestão pública',
        'status' => 'CONCLUÍDO',
        'numero_contratacao' => null,
        'valor_estimado' => 300000.00,
        'criado_em' => '2024-01-10'
    ]
];

$totalItems = 2;
$currentPage = 1;
$itemsPerPage = 10;

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $listTitle; ?> - Sistema CGLIC</title>
    
    <!-- CSS do Sistema -->
    <link rel="stylesheet" href="../assets/style.css">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f5f6fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .breadcrumb {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header da Página -->
        <div class="header">
            <div class="breadcrumb">
                <a href="../selecao_modulos.php">Início</a> / 
                <a href="../qualificacao_dashboard.php">Qualificações</a> / 
                Sistema Unificado (Exemplo)
            </div>
            <h1 style="margin: 0; color: #2c3e50;">
                <i data-lucide="clipboard-list" style="margin-right: 10px; color: #3498db;"></i>
                Exemplo - Sistema Unificado de Listas
            </h1>
            <p style="margin: 10px 0 0 0; color: #6c757d;">
                Demonstração do template padronizado para todas as listas do sistema
            </p>
        </div>
        
        <!-- Template Unificado -->
        <?php include '../templates/unified-list-template.php'; ?>
    </div>
    
    <script>
        // Inicializar ícones Lucide
        lucide.createIcons();
        
        // Funções específicas para qualificações (exemplo)
        function abrirModalCriarQualificacoes() {
            alert('Abrir modal de criação de qualificação');
        }
        
        function visualizarQualificacao(id) {
            alert('Visualizar qualificação ID: ' + id);
        }
        
        function editarQualificacao(id) {
            alert('Editar qualificação ID: ' + id);
        }
        
        function excluirQualificacao(id) {
            if (confirm('Tem certeza que deseja excluir esta qualificação?')) {
                alert('Excluir qualificação ID: ' + id);
            }
        }
        
        console.log('📋 Exemplo de qualificações carregado!');
    </script>
</body>
</html>