# Instruções para Importação Melhorada de Registros

Este documento contém instruções para utilizar o script melhorado de importação de registros a partir de planilhas Excel. O script foi especialmente desenvolvido para corrigir problemas com a identificação de situação ("homologado", etc.) e com valores monetários.

## Requisitos

Antes de usar o script, certifique-se de que as seguintes dependências estão instaladas:

```bash
npm install xlsx sqlite3 --save
```

## Como Usar

1. Salve o arquivo `importar-registros-melhorado.js` na pasta raiz do seu projeto SCGLIC.

2. Prepare sua planilha Excel seguindo as recomendações abaixo:

   - Certifique-se de que os cabeçalhos das colunas correspondem aos esperados pelo sistema
   - Inclua pelo menos as colunas obrigatórias: "NUP" e "OBJETO"
   - Para valores monetários, use o formato R$ 0.000,00 ou 0.000,00
   - Para a situação, use termos como "Homologado", "Em Andamento", "Deserto", etc.

3. Execute o script pela linha de comando, passando o caminho para o arquivo Excel:

```bash
node importar-registros-melhorado.js caminho/para/seu/arquivo.xlsx
```

## Funcionamento

O script realiza as seguintes operações:

1. **Análise da planilha**: Lê o arquivo Excel e identifica todos os cabeçalhos presentes.

2. **Mapeamento**: Mapeia os cabeçalhos da planilha para os campos correspondentes no banco de dados.

3. **Normalização de dados**:

   - Reconhece valores de situação como "Homologado", "Em Andamento", etc., mesmo com variações de escrita
   - Converte valores monetários considerando formatos brasileiros (R$ 1.234,56) e internacionais (1,234.56)
   - Trata diferentes formatos de data (DD/MM/YYYY, YYYY-MM-DD, etc.)

4. **Validação**: Verifica se os registros contêm os campos mínimos necessários.

5. **Estatísticas**: Exibe estatísticas sobre os dados, incluindo a distribuição por situação.

6. **Confirmação**: Solicita confirmação antes de prosseguir com a importação.

7. **Importação**: Insere os registros no banco de dados em lotes, para melhor desempenho.

8. **Relatório**: Apresenta um relatório final com contagem de sucessos e falhas.

## Melhorias Implementadas

Este script corrige especificamente os seguintes problemas:

1. **Reconhecimento de Situação**:

   - Agora identifica corretamente registros "Homologados" e outras situações
   - Usa uma função especial (`normalizarSituacao`) que reconhece variações de escrita

2. **Conversão de Valores Monetários**:

   - Trata tanto o formato brasileiro (1.234,56) quanto o internacional (1,234.56)
   - Remove símbolos e espaços em branco antes da conversão
   - Reconhece uma ampla variedade de formatos de cabeçalhos para valores monetários

3. **Tratamento de Datas**:

   - Converte datas em vários formatos (DD/MM/YYYY, MM/DD/YYYY, etc.)
   - Lida com datas armazenadas como números no Excel

4. **Diagnóstico Aprimorado**:
   - Exibe cabeçalhos encontrados e seu mapeamento
   - Mostra uma distribuição dos registros por situação
   - Fornece detalhes sobre erros durante a importação

## Resolvendo Problemas Comuns

Se encontrar problemas durante a importação, verifique:

1. **Cabeçalhos da planilha**: O script exibe os cabeçalhos encontrados. Certifique-se de que estão corretos.

2. **Formato dos dados**: Verifique se os valores monetários e datas estão em formatos reconhecíveis.

3. **Valores de situação**: Se a distribuição por situação não corresponder ao esperado, verifique como a situação está sendo escrita na planilha.

4. **Erros específicos**: O script exibe os primeiros 5 erros encontrados. Analise-os para identificar problemas comuns.

## Campos Suportados

O script suporta os seguintes campos (com várias opções de cabeçalho para cada um):

- `nup` - Número Único de Processo
- `objeto` - Descrição do objeto da licitação
- `situacao` - Situação atual do processo
- `valor_estimado` - Valor estimado da licitação
- `valor_homologado` - Valor homologado da licitação
- `economia` - Economia obtida
- `dt_entrada_dipli` - Data de entrada na DIPLI
- `resp_instrucao` - Responsável pela instrução
- `area_demandante` - Área demandante
- `pregoeiro` - Pregoeiro responsável
- `modalidade` - Modalidade da licitação
- `tipo` - Tipo da licitação
- `numero` - Número da licitação
- `ano` - Ano da licitação
- `prioridade` - Prioridade do processo
- `item_pgc` - Item PGC
- `estimado_pgc` - Valor estimado PGC
- `ano_pgc` - Ano PGC
- `qtd_itens` - Quantidade de itens
- `dt_abertura` - Data de abertura
- `andamentos` - Andamentos do processo
- `dt_homologacao` - Data de homologação
