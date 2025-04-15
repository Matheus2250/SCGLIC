# Sistema de Paginação para Registros

## Resumo das Alterações

Foi implementado um sistema de paginação na tabela de registros para melhorar o desempenho da página e reduzir o tempo de carregamento quando existem muitos registros. Agora, em vez de carregar todos os registros de uma só vez, o sistema mostra apenas 15 registros por página.

## Funcionalidades Implementadas

1. **Exibição Paginada**: Apenas 15 registros são mostrados por vez, melhorando significativamente o desempenho.
2. **Navegação Intuitiva**: Botões para navegar entre páginas (anterior, próxima, números de página).
3. **Informações de Contexto**: Um contador mostrando quais registros estão sendo exibidos e o total de registros.
4. **Integração com Filtros**: O sistema de paginação funciona perfeitamente com o sistema de filtros existente.
5. **Experiência de Usuário Melhorada**: Ao mudar de página, a tabela é rolada automaticamente para o topo.

## Como Funciona

1. Quando a página de registros é carregada, todos os registros são obtidos do servidor, mas apenas os primeiros 15 são exibidos.
2. A interface mostra informações sobre os registros sendo exibidos (ex: "Mostrando 1 a 15 de 150 registros").
3. Os controles de paginação permitem navegar facilmente entre as páginas.
4. Quando um filtro é aplicado, o sistema reseta para a primeira página e mostra apenas os registros filtrados.

## Detalhes Técnicos

- **Variáveis Globais**:

  - `dadosRegistros`: Armazena todos os registros recuperados do servidor.
  - `paginaAtual`: Controla qual página está sendo exibida.
  - `registrosPorPagina`: Define o número de registros por página (atualmente 15).

- **Funções Principais**:
  - `exibirRegistrosPaginados()`: Calcula quais registros devem ser exibidos na página atual.
  - `atualizarControlesPaginacao()`: Atualiza a interface de paginação.
  - `irParaPagina(pagina)`: Navega para uma página específica.
  - `paginaAnterior()` e `paginaProxima()`: Facilitam a navegação sequencial.

## Benefícios

1. **Desempenho Melhorado**: A página carrega muito mais rápido, especialmente com grandes volumes de dados.
2. **Menor Consumo de Recursos**: O navegador precisa renderizar menos elementos de uma vez.
3. **Experiência de Usuário Superior**: Interface mais responsiva e mais fácil de navegar.
4. **Melhor Acessibilidade**: Os registros são mais fáceis de encontrar e revisar quando estão organizados em páginas.

## Compatibilidade

O sistema de paginação foi integrado de forma a manter todas as funcionalidades existentes:

- Filtros
- Visualização de detalhes
- Edição de registros
- Exclusão de registros
- Exportação

## Notas Importantes

- O sistema carrega todos os registros inicialmente e depois gerencia a paginação no lado do cliente. Em uma implementação futura, poderíamos implementar a paginação do lado do servidor para um desempenho ainda melhor.
- O número de registros por página (15) pode ser ajustado conforme necessário.
