# Instruções para Configuração da Importação de Excel

Para que a funcionalidade de importação de Excel funcione corretamente, siga os passos abaixo:

## 1. Instalação de Dependências

Abra o prompt de comando (não o PowerShell) e navegue até a pasta do projeto:

```bash
cd caminho/para/o/projeto/SCGLIC
```

Em seguida, instale as dependências necessárias:

```bash
npm install multer@1.4.5-lts.1 xlsx@0.18.5 --save
```

## 2. Criação da Pasta de Uploads

É necessário criar uma pasta chamada "uploads" na raiz do projeto. Esta pasta será usada para armazenar temporariamente os arquivos enviados:

```bash
mkdir uploads
```

## 3. Reiniciar o Servidor

Após a instalação das dependências, reinicie o servidor:

```bash
node server.js
```

## 4. Teste da Funcionalidade

1. Faça login no sistema
2. Acesse a página "Importar Excel" pelo menu lateral
3. Selecione um arquivo Excel e siga as instruções para importação

## Solução de Problemas

Se encontrar o erro "Multer: não é uma função" ou similar, verifique se:

1. O pacote foi instalado corretamente (`npm list multer`)
2. A pasta "uploads" foi criada na raiz do projeto
3. O servidor foi reiniciado após a instalação

Para mais detalhes sobre como preparar e importar planilhas Excel, consulte o arquivo `README-IMPORTAR-EXCEL.md`.
