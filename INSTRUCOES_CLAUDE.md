# 📋 INSTRUÇÕES OPERACIONAIS - Sistema CGLIC

## ⚠️ REGRAS FUNDAMENTAIS

### 🚫 1. BANCO DE DADOS - ACESSO RESTRITO
- **NUNCA** executar comandos diretos no banco de dados
- **NUNCA** tentar acessar MySQL via linha de comando
- **NUNCA** usar comandos como `mysql`, `mysqldump`, `mysqlimport`
- **Ambiente:** phpMyAdmin (interface web) - SEM ACESSO DIRETO
- **Alternativas permitidas:**
  - Gerar scripts SQL para o usuário executar manualmente
  - Modificar arquivos PHP que fazem queries
  - Criar/editar funções em `functions.php`
  - Documentar queries necessárias para execução manual

### ✅ 2. OPERAÇÕES PERMITIDAS
- **Leitura/Escrita** de arquivos PHP, JS, CSS, HTML
- **Criação** de scripts SQL (para execução manual pelo usuário)
- **Modificação** de lógica de negócio nos arquivos PHP
- **Análise** de código e estrutura existente
- **Geração** de relatórios e documentação
- **Testes** via navegador (http://localhost/sistema_licitacao)

### 🔴 3. OPERAÇÕES PROIBIDAS
```bash
# NUNCA executar:
mysql -u root -p
mysqldump sistema_licitacao
php -r "mysqli_connect()"
C:\xampp\mysql\bin\mysql.exe
```

---

## 🤖 MODELO DE TRABALHO - DOIS AGENTES

### 📊 AGENTE 1: PLANEJADOR (Opus)
**Função:** Arquitetar e planejar soluções complexas
**Responsabilidades:**
- Analisar requisitos profundamente
- Criar arquitetura da solução
- Definir fluxo de implementação
- Identificar riscos e dependências
- Gerar plano detalhado de execução
- Revisar código existente para impactos

**Quando usar Opus:**
- Mudanças estruturais grandes
- Novas funcionalidades complexas
- Refatoração de módulos
- Análise de problemas difíceis
- Decisões arquiteturais

### ⚡ AGENTE 2: EXECUTOR (Sonnet)
**Função:** Implementar código rapidamente
**Responsabilidades:**
- Executar plano criado pelo Opus
- Fazer modificações pontuais
- Corrigir bugs simples
- Ajustes de interface
- Implementação direta de código
- Testes básicos de funcionalidade

**Quando usar Sonnet:**
- Implementação de planos prontos
- Correções rápidas
- Ajustes de CSS/JavaScript
- Mudanças simples em PHP
- Tarefas repetitivas

---

## 📝 WORKFLOW RECOMENDADO

### Para Tarefas Complexas:
1. **OPUS** analisa e cria plano detalhado
2. **OPUS** gera TodoList com todas as etapas
3. **SONNET** executa item por item da lista
4. **SONNET** marca tarefas como concluídas
5. **OPUS** revisa resultado final (se necessário)

### Para Tarefas Simples:
1. **SONNET** executa diretamente
2. Sem necessidade de planejamento extenso

---

## 🎯 EXEMPLOS PRÁTICOS

### Exemplo 1: Nova Funcionalidade
```markdown
USUÁRIO: "Preciso adicionar um sistema de notificações por email"

OPUS:
1. Analisa estrutura atual
2. Cria plano:
   - Criar tabela notificacoes (gerar SQL)
   - Adicionar função enviarEmail() em functions.php
   - Criar interface de configuração
   - Integrar com eventos existentes
3. Identifica riscos e dependências

SONNET:
1. Implementa cada item do plano
2. Cria os arquivos necessários
3. Testa funcionalidade
```

### Exemplo 2: Correção de Bug
```markdown
USUÁRIO: "O botão de exportar não funciona"

SONNET (direto):
1. Localiza o problema
2. Corrige o código
3. Testa a correção
```

---

## 💡 BOAS PRÁTICAS

### Sempre:
- ✅ Ler arquivos antes de modificar
- ✅ Fazer backup mental do que vai mudar
- ✅ Testar impactos em outros módulos
- ✅ Documentar mudanças importantes
- ✅ Usar TodoList para tarefas múltiplas

### Nunca:
- ❌ Assumir acesso ao banco de dados
- ❌ Executar comandos MySQL diretamente
- ❌ Modificar config.php sem necessidade
- ❌ Deletar arquivos sem confirmação
- ❌ Fazer mudanças sem entender o contexto

---

## 🔧 COMANDOS ÚTEIS ADAPTADOS

### Em vez de acessar o banco:
```php
// ERRADO:
mysql -u root sistema_licitacao

// CERTO - Gerar script:
echo "-- Script para executar no phpMyAdmin
SELECT * FROM usuarios;
UPDATE usuarios SET nivel_acesso = 1 WHERE id = 1;"
```

### Para verificar dados:
```php
// Criar arquivo temporário PHP:
// verificar_dados.php
<?php
require_once 'config.php';
$resultado = consultarDados("SELECT * FROM tabela");
echo "<pre>";
print_r($resultado);
echo "</pre>";
?>
```

---

## 📊 DIVISÃO DE RESPONSABILIDADES

| Tarefa | Opus | Sonnet |
|--------|------|--------|
| Análise de requisitos | ✅ Principal | ⚠️ Básica |
| Planejamento | ✅ Detalhado | ❌ Não faz |
| Implementação | ⚠️ Complexa | ✅ Principal |
| Correção de bugs | ⚠️ Complexos | ✅ Simples |
| Refatoração | ✅ Planeja | ✅ Executa |
| Documentação | ✅ Estrutura | ✅ Preenche |
| Testes | ✅ Estratégia | ✅ Execução |

---

## 🚨 ALERTAS IMPORTANTES

### Banco de Dados:
```
⚠️ LEMBRE-SE: Você NÃO tem acesso direto ao MySQL!
- phpMyAdmin é usado pelo USUÁRIO
- Você só gera scripts SQL
- Usuário executa manualmente
```

### Arquivos Críticos:
```
🔴 config.php - Modificar com EXTREMO cuidado
🔴 functions.php - Sempre fazer backup antes
🔴 process.php - Central do sistema, testar tudo
```

### Permissões:
```
✅ Você PODE: Ler/escrever arquivos
❌ Você NÃO PODE: Executar comandos MySQL
❌ Você NÃO PODE: Acessar phpMyAdmin
```

---

## 📌 RESUMO EXECUTIVO

1. **SEM ACESSO AO BANCO** - Apenas scripts SQL para execução manual
2. **DOIS MODOS** - Opus planeja, Sonnet executa
3. **WORKFLOW CLARO** - Complexo = Opus→Sonnet, Simples = Sonnet direto
4. **SEMPRE TESTAR** - Impactos e dependências
5. **DOCUMENTAR** - Mudanças importantes

---

**🔄 Última atualização:** Janeiro 2025
**📝 Versão:** 1.0
**✍️ Autor:** Sistema de Instruções Operacionais