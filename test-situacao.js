/**
 * Teste para verificar se o sistema reconhece corretamente situações como "HOMOLOGADO" e "EM ANDAMENTO"
 * em diferentes formatos (maiúsculas, minúsculas, mistas).
 */

// Simulação da função de avaliação de situação
function testeSituacao(situacao, filtro) {
    if (!situacao) return false;
    
    // Teste da lógica de comparação
    const situacaoTexto = situacao.toLowerCase();
    const filtroTexto = filtro.toLowerCase();
    
    if (filtroTexto.includes("homologado")) {
        return situacaoTexto.includes("homologado");
    } else if (filtroTexto.includes("em andamento")) {
        return situacaoTexto.includes("em andamento");
    } else {
        return situacaoTexto === filtroTexto;
    }
}

// Função que testa a lógica de exibição correta do badge
function testeBadgeClass(situacao) {
    if (!situacao) return "bg-secondary";
    
    const situacaoNormalizada = situacao.toUpperCase();
    
    if (situacaoNormalizada.includes("HOMOLOGADO")) {
        return "bg-success";
    } else if (situacaoNormalizada.includes("EM ANDAMENTO")) {
        return "bg-warning text-dark";
    } else if (situacaoNormalizada.includes("EM ANÁLISE") || situacaoNormalizada.includes("EM ANALISE")) {
        return "bg-info text-dark";
    } else if (
        situacaoNormalizada.includes("FRACASSADO") || 
        situacaoNormalizada.includes("DESERTO") || 
        situacaoNormalizada.includes("CANCELADO")
    ) {
        return "bg-danger";
    } else {
        return "bg-secondary";
    }
}

// Dados de teste para diferentes situações
const testesSituacao = [
    { situacao: "Homologado", filtro: "Homologado", esperado: true },
    { situacao: "HOMOLOGADO", filtro: "Homologado", esperado: true },
    { situacao: "homologado", filtro: "Homologado", esperado: true },
    { situacao: "Processo Homologado", filtro: "Homologado", esperado: true },
    { situacao: "Em Andamento", filtro: "Em Andamento", esperado: true },
    { situacao: "EM ANDAMENTO", filtro: "Em Andamento", esperado: true },
    { situacao: "em andamento", filtro: "Em Andamento", esperado: true },
    { situacao: "Processo em Andamento", filtro: "Em Andamento", esperado: true },
    { situacao: "Cancelado", filtro: "Homologado", esperado: false },
    { situacao: "Fracassado", filtro: "Em Andamento", esperado: false }
];

// Testes de formatação de badges
const testesBadges = [
    { situacao: "Homologado", esperado: "bg-success" },
    { situacao: "HOMOLOGADO", esperado: "bg-success" },
    { situacao: "Processo Homologado em 01/01/2024", esperado: "bg-success" },
    { situacao: "Em Andamento", esperado: "bg-warning text-dark" },
    { situacao: "EM ANDAMENTO", esperado: "bg-warning text-dark" },
    { situacao: "Processo Em andamento", esperado: "bg-warning text-dark" },
    { situacao: "Fracassado", esperado: "bg-danger" },
    { situacao: "DESERTO", esperado: "bg-danger" },
    { situacao: "Outro Status", esperado: "bg-secondary" }
];

// Execução dos testes
console.log("=== TESTES DE FILTRO DE SITUAÇÃO ===");
testesSituacao.forEach((teste, index) => {
    const resultado = testeSituacao(teste.situacao, teste.filtro);
    const status = resultado === teste.esperado ? "PASSOU ✓" : "FALHOU ✗";
    console.log(`Teste ${index + 1}: '${teste.situacao}' com filtro '${teste.filtro}' - ${status}`);
});

console.log("\n=== TESTES DE FORMATAÇÃO DE BADGES ===");
testesBadges.forEach((teste, index) => {
    const resultado = testeBadgeClass(teste.situacao);
    const status = resultado === teste.esperado ? "PASSOU ✓" : "FALHOU ✗";
    console.log(`Teste ${index + 1}: '${teste.situacao}' => '${resultado}' - ${status}`);
});

console.log("\nInstruções: Execute este arquivo para verificar se a lógica de reconhecimento de situações está funcionando corretamente.");
console.log("Você pode executar usando: node test-situacao.js"); 