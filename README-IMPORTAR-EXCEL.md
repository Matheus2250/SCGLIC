# Importação de Planilhas Excel para o Sistema SCGLIC

Este documento explica como utilizar a funcionalidade de importação de planilhas Excel para popular o banco de dados do sistema SCGLIC.

## Requisitos

Antes de começar, certifique-se de que:

1. O servidor está em execução
2. Você tem um arquivo Excel (.xlsx ou .xls) com os dados a serem importados
3. Você tem permissões de acesso ao sistema

## Dependências Necessárias

Para que a funcionalidade de importação funcione corretamente, o sistema precisa ter as seguintes dependências instaladas:

```bash
npm install multer xlsx fs --save
```

## Preparando a Planilha Excel

Para uma importação bem-sucedida, sua planilha Excel deve conter, pelo menos, uma das seguintes colunas:

### Cabeçalhos Obrigatórios (pelo menos um destes)

- NUP
- OBJETO

### Cabeçalhos Recomendados

- MODALIDADE
- SITUAÇÃO
- ÁREA DEMANDANTE
- PREGOEIRO
- TIPO
- VALOR ESTIMADO
- VALOR HOMOLOGADO
- ECONOMIA
- DT ABERTURA
- DT HOMOLOGAÇÃO

## Como Importar

1. Faça login no sistema SCGLIC
2. No menu lateral, clique em "Importar Excel"
3. Selecione o arquivo Excel usando o botão "Escolher arquivo"
4. Se desejar limpar todos os registros existentes antes de importar, marque a opção "Limpar dados existentes"
5. Clique no botão "Importar"
6. Aguarde o processo terminar - você verá um relatório com o resultado da importação

## Mapeamento de Colunas

O sistema reconhece automaticamente os seguintes cabeçalhos da planilha e os mapeia para os campos do banco de dados:

| Cabeçalho da Planilha | Campo no Banco de Dados |
| --------------------- | ----------------------- |
| NUP                   | nup                     |
| DT ENTRADA DIPLI      | dt_entrada_dipli        |
| RESP. INSTRUÇÃO       | resp_instrucao          |
| ÁREA DEMANDANTE       | area_demandante         |
| PREGOEIRO             | pregoeiro               |
| MODALIDADE            | modalidade              |
| TIPO                  | tipo                    |
| NÚMERO/Nº             | numero                  |
| ANO                   | ano                     |
| PRIORIDADE            | prioridade              |
| ITEM PGC              | item_pgc                |
| ESTIMADO PGC          | estimado_pgc            |
| ANO PGC               | ano_pgc                 |
| OBJETO                | objeto                  |
| QTD ITENS             | qtd_itens               |
| VALOR ESTIMADO        | valor_estimado          |
| DT ABERTURA           | dt_abertura             |
| SITUAÇÃO              | situacao                |
| ANDAMENTOS            | andamentos              |
| VALOR HOMOLOGADO      | valor_homologado        |
| ECONOMIA              | economia                |
| DT HOMOLOGAÇÃO        | dt_homologacao          |

## Formatos Suportados

- **Datas**: O sistema reconhece datas nos formatos DD/MM/YYYY, YYYY-MM-DD ou no formato numérico do Excel
- **Valores Monetários**: O sistema reconhece valores com ou sem símbolo de moeda, com vírgula ou ponto como separador decimal

## Solução de Problemas

### Nenhum registro foi importado

- Verifique se a planilha tem pelo menos uma linha com NUP ou OBJETO preenchidos
- Verifique se os nomes das colunas estão exatamente como listados acima
- Certifique-se de que o arquivo é um Excel válido (.xlsx ou .xls)

### Erros durante a importação

- Verifique o log de erros apresentado após a importação
- Considere limpar registros existentes se houver problemas com duplicidade
- Se os erros persistirem, entre em contato com o administrador do sistema

## Informações Técnicas

A importação ocorre em lotes de 50 registros para otimizar o desempenho. Se você estiver importando uma planilha muito grande, o processo pode levar algum tempo.
