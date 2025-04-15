# Instruções para Implementação da Exportação de Registros

Implementamos a funcionalidade para exportar registros em formatos CSV e Excel, mesmo quando há filtros aplicados. Para que essa funcionalidade funcione corretamente, siga as instruções abaixo:

## 1. Instalação de Dependências

Você precisa instalar o módulo `xlsx` para que a exportação para Excel funcione. Execute o seguinte comando:

```bash
npm install xlsx --save
```

## 2. Verificação da Implementação

Já implementamos:

1. Rotas no servidor para exportação:

   - `/api/export/csv` (POST) - para exportar em CSV
   - `/api/export/excel` (POST) - para exportar em Excel

2. Funções JavaScript no frontend:

   - Event listeners para os botões de exportação
   - Função `exportarRegistros()` que envia os filtros ativos ao servidor

3. Funções de processamento no servidor:
   - `aplicarFiltros()` - aplica os filtros à lista de registros
   - `gerarCSV()` - formata os dados para CSV

## 3. Como funciona

1. O usuário aplica filtros na tela de registros (opcional)
2. Ao clicar em "Exportar CSV" ou "Exportar Excel", o sistema:
   - Envia os filtros ativos para o servidor
   - O servidor processa os dados aplicando os mesmos filtros
   - Um arquivo é gerado no formato escolhido e enviado de volta para o navegador
   - O download do arquivo é iniciado automaticamente

## 4. Solução de Problemas

Se encontrar algum erro:

1. Verifique se a dependência `xlsx` está instalada
2. Verifique se o servidor está em execução
3. Verifique no console do navegador e do servidor se há mensagens de erro
4. Garanta que o usuário está autenticado (token válido)

## 5. Considerações Finais

Esta implementação funciona com todos os tipos de filtros existentes no sistema, incluindo:

- Filtros por texto (contém, começa com, termina com, etc.)
- Filtros numéricos (maior que, menor que, entre, etc.)
- Filtros de data (antes de, depois de, entre, etc.)

A exportação usa a mesma lógica de filtros aplicada na interface, garantindo consistência nos resultados.
