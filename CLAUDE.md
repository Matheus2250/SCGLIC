# 🏥 Sistema de Informações CGLIC - Ministério da Saúde

## 📋 **Visão Geral**

**Nome:** Sistema de Informações CGLIC  
**Órgão:** Ministério da Saúde  
**Objetivo:** Organizar e gerenciar processos, informações e dados da Coordenação Geral de Licitações  
**URL Local:** http://localhost/sistema_licitacao  

---

## 🛠 **Ambiente de Desenvolvimento**

### **Stack Tecnológica**
- **Servidor:** XAMPP (Apache + MySQL + PHP)
- **Linguagem:** PHP (versão atual do XAMPP)
- **Banco de Dados:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript vanilla
- **Ícones:** Lucide Icons
- **Gráficos:** Chart.js

### **Estrutura de Pastas**
```
C:\xampp\htdocs\sistema_licitacao\
├── assets/           # CSS, JS, recursos visuais
├── backup/           # Scripts e arquivos de backup
├── backups/          # Backups gerados automaticamente
├── cache/            # Cache de dados e relatórios
├── logs/             # Logs do sistema
├── scripts_sql/      # Scripts SQL para migração
├── uploads/          # Arquivos enviados (CSV, etc.)
├── config.php        # Configurações do banco
├── functions.php     # Funções principais
├── process.php       # Processamento de formulários
├── index.php         # Tela de login
├── selecao_modulos.php  # Menu principal
├── dashboard.php     # Módulo Planejamento
├── licitacao_dashboard.php  # Módulo Licitações
└── gerenciar_usuarios.php   # Gestão de usuários
```

---

## 📊 **Banco de Dados**

### **Configuração**
- **Nome do Banco:** `sistema_licitacao`
- **Usuário:** `root`
- **Senha:** *(sem senha)*
- **Host:** `localhost`

### **Tabelas Principais**
| Tabela | Descrição | Criticidade |
|--------|-----------|-------------|
| `usuarios` | Usuários e permissões (4 níveis) | 🔴 CRÍTICA |
| `pca_dados` | Dados do PCA atual (2025-2026) | 🔴 CRÍTICA |
| `pca_historico_anos` | PCAs históricos (2022-2024) | 🔴 CRÍTICA |
| `licitacoes` | Processos licitatórios | 🔴 CRÍTICA |
| `pca_riscos` | Gestão de riscos (matriz 5x5) | 🔴 CRÍTICA |
| `pca_importacoes` | Histórico de importações | 🟡 IMPORTANTE |
| `pca_historico` | Auditoria de mudanças | 🟡 IMPORTANTE |
| `backups_sistema` | Controle de backups | 🟡 IMPORTANTE |
| `logs_sistema` | Logs de operações | 🟢 SECUNDÁRIA |

### **PCAs por Ano**
| Ano | Status | Operações Permitidas |
|-----|--------|---------------------|
| **2022** | 📚 Histórico | Apenas visualização, relatórios |
| **2023** | 📚 Histórico | Apenas visualização, relatórios |
| **2024** | 📚 Histórico | Apenas visualização, relatórios |
| **2025** | 🔄 Atual | Importação, edição, visualização, licitações |
| **2026** | 🔄 Atual | Importação, edição, visualização, licitações |

### **Scripts de Migração**
- `database/estrutura_completa_2025.sql` - Script completo atualizado (USE ESTE!)
- `database/database_complete.sql` - Script antigo (não usar)
- `database/migration_niveis_usuario.sql` - Migração para níveis de usuário
- `create_admin.sql` - Criar usuário administrador padrão

---

## 👤 **Sistema de Usuários**

### **Níveis de Acesso**
| Nível | Nome | Descrição |
|-------|------|-----------|
| **1** | **Coordenador** | Acesso total - pode gerenciar usuários, executar backups, todos os módulos |
| **2** | **DIPLAN** | Planejamento - pode importar/editar PCA, apenas VISUALIZAR licitações |
| **3** | **DIPLI** | Licitações - pode criar/editar licitações, apenas VISUALIZAR PCA |
| **4** | **Visitante** | Somente leitura - pode visualizar dados, gerar relatórios, exportar informações |

### **Gestão de Usuários**
- **Interface:** `gerenciar_usuarios.php`
- **Funcionalidades:** Busca, filtros, paginação (10 por página)
- **Filtros:** Nome/email, nível, departamento

### **⚠️ IMPORTANTE: Sistema de Permissões por Módulo**

#### **🔒 Regras de Acesso por Nível:**

| Módulo | Coordenador (1) | DIPLAN (2) | DIPLI (3) | Visitante (4) |
|--------|----------------|------------|-----------|---------------|
| **📊 Planejamento** | ✅ Total | ✅ Edição | 👁️ Visualização | 👁️ Visualização |
| **⚖️ Licitações** | ✅ Total | 👁️ Visualização | ✅ Edição | 👁️ Visualização |
| **👥 Usuários** | ✅ Gestão | ❌ Bloqueado | ❌ Bloqueado | ❌ Bloqueado |

#### **📝 Detalhamento das Permissões:**

**DIPLAN (Nível 2) - Especialista em Planejamento:**
- ✅ **PCA**: Importar, editar, relatórios, exportar
- 👁️ **Licitações**: Apenas visualizar, relatórios, exportar
- 👁️ **Riscos**: Apenas visualizar, exportar

**DIPLI (Nível 3) - Especialista em Licitações:**
- ✅ **Licitações**: Criar, editar, relatórios, exportar
- 👁️ **PCA**: Apenas visualizar, relatórios, exportar  
- ✅ **Riscos**: Criar, editar, visualizar

**Visitante (Nível 4) - Consulta:**
- 👁️ **Todos os módulos**: Apenas visualização, relatórios, exportação

---

## 🎯 **Módulos Principais**

### **1. 📊 Planejamento (dashboard.php)**
- **Função:** Gestão do Plano de Contratações Anual (PCA)
- **Operações:** Importar CSV, visualizar DFDs, relatórios, exportações
- **PCAs Disponíveis:** 2022, 2023, 2024, 2025, 2026
  - **2022-2024:** Históricos (apenas visualização)
  - **2025-2026:** Atuais (editáveis, licitações)
- **Dados:** Volume alto - vários GB/mês de atualizações

### **2. ⚖️ Licitações (licitacao_dashboard.php)**
- **Função:** Controle de processos licitatórios
- **Operações:** Criar, editar, acompanhar licitações, relatórios
- **Integração:** Vinculação com dados do PCA

### **3. 🛡️ Gestão de Riscos (gestao_riscos.php)**
- **Função:** Análise de riscos com matriz 5x5
- **Operações:** Criar riscos, avaliar probabilidade/impacto, ações de mitigação
- **Relatórios:** Exportação em PDF/HTML, estatísticas mensais

### **4. 👥 Usuários (gerenciar_usuarios.php)**
- **Função:** Gestão de permissões e níveis
- **Operações:** Atribuir níveis, filtrar usuários, busca
- **Segurança:** Controle de acesso por hierarquia

### **5. 💾 Sistema de Backup**
- **Função:** Backup manual e automático
- **Operações:** Backup de banco/arquivos, verificação de integridade
- **Interface:** Histórico, estatísticas, downloads

---

## 🔧 **Operações Comuns**

### **Teste e Desenvolvimento**
```bash
# Acessar o sistema
http://localhost/sistema_licitacao

# Estrutura de teste
1. Login no sistema
2. Testar módulos principais
3. Verificar permissões por nível
4. Validar importações/exportações
```

### **Backup e Manutenção**
```php
# Backup manual via interface
Sistema → Backup & Segurança → Backup Manual

# Localização dos backups
/backups/database/    # Backups do banco
/backups/files/       # Backups de arquivos
```

### **Deploy em Novo Ambiente**
1. **Baixar projeto do GitHub**
2. **Colocar em:** `C:\xampp\htdocs\sistema_licitacao\`
3. **Executar script:** `database/estrutura_completa_2025.sql`
4. **Configurar:** `config.php` (se necessário)
5. **Testar acesso:** `http://localhost/sistema_licitacao`
6. **Login padrão:** `admin@cglic.gov.br` / `password`

---

## 🛡 **Segurança**

### **Autenticação**
- **Sessões PHP** com verificação de login
- **Tokens CSRF** em formulários
- **Validação de entrada** com sanitização

### **Permissões**
- **Controle por níveis** (1, 2, 3)
- **Verificação de módulos** `temAcessoModulo()`
- **Validação de ações** `temPermissao()`

### **Logs**
- **Login/logout** registrados
- **Operações críticas** auditadas
- **Tentativas de acesso** monitoradas
- **Localização:** `/logs/` e tabela `logs_sistema`

---

## 📝 **Convenções de Código**

### **PHP**
- **Nomenclatura:** snake_case para variáveis e funções
- **Arquivos:** Nomes descritivos (dashboard.php, process.php)
- **Funções:** Agrupadas em functions.php
- **Sanitização:** Sempre limpar dados de entrada

### **Banco de Dados**
- **Tabelas:** snake_case (pca_dados, logs_sistema)
- **Prepared Statements** sempre para queries
- **Validação** de tipos de dados

### **Frontend**
- **CSS:** Classes semânticas e organizadas
- **JavaScript:** Vanilla JS com Lucide Icons
- **Responsivo:** Mobile-first approach

---

## ⚠️ **Operações Críticas**

### **NUNCA PODE FALHAR**
1. **Backup do banco de dados** - Dados são vitais
2. **Integridade das tabelas principais** - usuarios, pca_dados, licitacoes
3. **Sistema de login** - Acesso ao sistema
4. **Importação de PCA** - Entrada principal de dados

### **Workflow de Mudanças**
1. **SEMPRE testar** antes de implementar
2. **Fazer backup** se mudança for crítica
3. **Testar com usuários diferentes** (níveis 1, 2, 3)
4. **Se der erro:** Reverter imediatamente

---

## 📈 **Performance e Volume**

### **Dados Esperados**
- **Usuários:** 50-200 usuários ativos
- **Volume:** Vários GB/mês de atualizações
- **Operações:** Uso diário intenso
- **Picos:** Importações mensais de PCA

### **Otimizações**
- **Cache** para relatórios pesados
- **Paginação** em listas grandes
- **Índices** no banco para consultas frequentes

---

## 🔄 **Backup e Recuperação**

### **Estratégia de Backup**
- **Automático:** Sistema de backup integrado
- **Manual:** Interface para backup sob demanda
- **Localização:** `/backups/` com timestamp
- **Tipos:** Banco + arquivos

### **Recuperação**
- **Scripts SQL** para restaurar banco
- **Arquivos** organizados por data
- **Testes** regulares de recuperação recomendados

---

## 📚 **Recursos e Documentação**

### **Usuários**
- **Manual de usuário:** *(a ser criado)*
- **Treinamento por nível:** Coordenador, DIPLAN, DIPLI
- **FAQ:** *(a ser desenvolvida)*

### **Técnica**
- **Código:** Comentado e organizado
- **Funções:** Documentadas em functions.php
- **APIs:** Documentação interna *(em desenvolvimento)*

---

## 🔧 **Comandos Úteis para Claude**

### **Verificações Rápidas**
```bash
# Verificar status do sistema
http://localhost/sistema_licitacao

# Testar login
Email: admin@cglic.gov.br
Senha: admin123

# Verificar logs de erro
C:\xampp\apache\logs\error.log
```

### **Manutenção**
```sql
-- Verificar usuários
SELECT id, nome, email, tipo_usuario, nivel_acesso FROM usuarios;

-- Status das tabelas
SHOW TABLE STATUS FROM sistema_licitacao;

-- Últimas importações
SELECT * FROM pca_importacoes ORDER BY criado_em DESC LIMIT 5;
```

---

## 🚀 **Roadmap e Melhorias**

### **Próximas Implementações**
- [ ] **Sistema de versionamento** (Git)
- [ ] **Manual do usuário** completo
- [ ] **Rotinas de manutenção** automatizadas
- [ ] **Relatórios avançados** personalizáveis
- [ ] **API REST** para integrações futuras

### **Otimizações Futuras**
- [ ] **Cache inteligente** para relatórios
- [ ] **Backup automático** agendado
- [ ] **Monitoramento** de performance
- [ ] **Logs estruturados** para análise

---

## 📞 **Suporte e Manutenção**

### **Contatos**
- **Desenvolvimento:** Claude AI Assistant
- **Sistema:** Ministério da Saúde - CGLIC
- **Ambiente:** XAMPP local

### **Problemas Conhecidos**
- *(Nenhum reportado até o momento)*

### **Atualizações**
- **Última atualização:** Dezembro 2024 - Sistema completo com 4 níveis de usuário
- **Versão atual:** v2025.12 - Implementação completa com backup, riscos e nível Visitante
- **Script atual:** `database/estrutura_completa_2025.sql`
- **Próxima revisão:** A definir

### **Implementações Recentes (v2025.12)**
- ✅ **Nível Visitante (4):** Usuários somente leitura com acesso a relatórios e exportações
- ✅ **Sistema de Backup:** Interface completa para backup manual e histórico
- ✅ **Gestão de Riscos:** Matriz 5x5 com relatórios em PDF/HTML
- ✅ **Estrutura SQL Completa:** Script consolidado com todas as tabelas e dados iniciais
- ✅ **Permissões Corrigidas:** Usuários Visitante podem acessar relatórios, gestão de riscos e contratações atrasadas
- ✅ **Remoção de Dados Demo:** Gráficos e interfaces sem informações fictícias

---

**📌 IMPORTANTE:** Este arquivo deve ser atualizado sempre que houver mudanças significativas no sistema, novos módulos ou alterações na estrutura do banco de dados.