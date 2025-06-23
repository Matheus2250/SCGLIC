<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn() {
    return isset($_SESSION['usuario']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'usuarios/login');
        exit;
    }
}

/* ========== SISTEMA DE NÍVEIS DE USUÁRIO ========== */

/**
 * Níveis de Usuário:
 * 1 - COORDENADOR: Acesso total, pode gerenciar usuários e seus níveis
 * 2 - PLANEJAMENTO: Pode importar PCA, ver licitações (apenas visualizar), relatórios
 * 3 - LICITAÇÃO: Pode criar/editar licitações, ver planejamento (apenas visualizar), relatórios  
 * 4 - VISITANTE: Apenas visualizar e gerar relatórios
 */

function getNivelUsuario(): int {
    if (!isLoggedIn()) return 4; // Visitante por padrão
    return (int) ($_SESSION['usuario']['nivel_acesso'] ?? 4);
}

function getNomeNivel(int $nivel): string {
    return match($nivel) {
        1 => 'Coordenador',
        2 => 'Planejamento', 
        3 => 'Licitação',
        4 => 'Visitante',
        default => 'Desconhecido'
    };
}

function isCoordenador(): bool {
    return getNivelUsuario() === 1;
}

function isPlanejamento(): bool {
    return getNivelUsuario() === 2;
}

function isLicitacao(): bool {
    return getNivelUsuario() === 3;
}

function isVisitante(): bool {
    return getNivelUsuario() === 4;
}

function podeGerenciarUsuarios(): bool {
    return isCoordenador();
}

function podeImportarPCA(): bool {
    return isCoordenador() || isPlanejamento();
}

function podeEditarLicitacoes(): bool {
    return isCoordenador() || isLicitacao();
}

function podeVisualizarRelatorios(): bool {
    return true; // Todos podem ver relatórios
}

function requireNivel(int $nivelMinimo, string $mensagem = 'Acesso negado.') {
    requireLogin();
    if (getNivelUsuario() > $nivelMinimo) {
        echo $mensagem;
        exit;
    }
}

function requireCoordenador(string $mensagem = 'Apenas coordenadores podem acessar esta funcionalidade.') {
    requireNivel(1, $mensagem);
}

function requirePlanejamentoOuCoordenador(string $mensagem = 'Acesso restrito ao pessoal de planejamento.') {
    requireLogin();
    if (!isCoordenador() && !isPlanejamento()) {
        echo $mensagem;
        exit;
    }
}

function requireLicitacaoOuCoordenador(string $mensagem = 'Acesso restrito ao pessoal de licitação.') {
    requireLogin();
    if (!isCoordenador() && !isLicitacao()) {
        echo $mensagem;
        exit;
    }
}

function requireNotVisitante(string $mensagem = 'Visitantes não podem realizar esta ação.') {
    requireLogin();
    if (isVisitante()) {
        echo $mensagem;
        exit;
    }
}
