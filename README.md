# 🏥 Sistema de Informações CGLIC - Ministério da Saúde

Sistema web para gestão e controle de licitações, processos de contratação e Plano de Contratações Anual (PCA) da Coordenação Geral de Licitações do Ministério da Saúde.

## 📋 Visão Geral

**Nome:** Sistema de Informações CGLIC  
**Órgão:** Ministério da Saúde  
**Objetivo:** Organizar e gerenciar processos, informações e dados da Coordenação Geral de Licitações  
**URL Local:** http://localhost/sistema_licitacao  

## 🛠 Ambiente de Desenvolvimento

### Stack Tecnológica
- **Servidor:** XAMPP (Apache + MySQL + PHP)
- **Linguagem:** PHP (versão atual do XAMPP)
- **Banco de Dados:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript vanilla
- **Ícones:** Lucide Icons
- **Gráficos:** Chart.js

### Estrutura Real do Projeto
```
sistema_licitacao/
├── assets/                 # CSS, JS, recursos visuais
│   ├── dashboard.css       # Estilos do dashboard de planejamento
│   ├── dashboard.js        # Scripts do dashboard de planejamento
│   ├── licitacao-dashboard.css  # Estilos do dashboard de licitações
│   ├── licitacao-dashboard.js   # Scripts do dashboard de licitações
│   ├── style.css           # Estilos globais
│   ├── script.js           # Scripts globais
│   └── mobile-improvements.* # Melhorias para mobile
├── database/               # Scripts e migrações do banco
│   ├── database_complete.sql     # Script completo de instalação
│   ├── database_complete_atualizado.sql  # Versão atualizada
│   ├── migration_niveis_usuario.sql      # Migração para níveis
│   └── migrations/         # Scripts de migração
├── storage/                # Armazenamento de arquivos
│   └── uploads/           # Arquivos CSV importados
├── cache/                  # Cache de dados e relatórios
│   ├── chart_data_*.cache # Cache dos gráficos
│   └── dashboard_stats.cache  # Cache do dashboard
├── relatorios/            # Sistema de relatórios
│   ├── exportar_*.php     # Scripts de exportação
│   └── gerar_relatorio_*.php  # Geradores de relatório
├── utils/                 # Utilitários do sistema
│   ├── cron_backup.php    # Backup automático
│   └── detalhes.php       # Detalhes de contratações
├── api/                   # APIs simples
│   ├── get_licitacao.php  # API para dados de licitação
│   └── get_pca_data.php   # API para dados do PCA
├── config.php             # Configurações do sistema
├── functions.php          # Funções principais
├── process.php            # Processamento de formulários
├── index.php              # Tela de login
├── selecao_modulos.php    # Menu principal
├── dashboard.php          # Módulo Planejamento
├── licitacao_dashboard.php # Módulo Licitações
└── gerenciar_usuarios.php  # Gestão de usuários
```

## 🚀 Instalação Rápida

### 1. Requisitos
- **XAMPP** com PHP 7.4+ e MySQL 5.7+
- **Navegador moderno** (Chrome, Firefox, Safari, Edge)

### 2. Instalação no XAMPP

1. **Baixar o projeto** e colocar em: `C:\xampp\htdocs\sistema_licitacao\`

2. **Criar o banco de dados:**
```sql
CREATE DATABASE sistema_licitacao CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

3. **Executar script de instalação:**
```bash
mysql -u root -p sistema_licitacao < database/estrutura_completa_2025.sql
```

4. **Acessar o sistema:** `http://localhost/sistema_licitacao`

### 3. Configuração (Opcional)
O sistema funciona sem configuração adicional. Para personalizar, edite `config.php`:

```php
// Configurações básicas já definidas:
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_licitacao');
define('DB_USER', 'root');
define('DB_PASS', '');  // Sem senha por padrão no XAMPP
```

## 👤 Primeiro Acesso

**Usuário padrão:**
- Email: `admin@cglic.gov.br`
- Senha: `password`

⚠️ **IMPORTANTE:** Altere a senha após o primeiro login!

## 👥 Sistema de Usuários

### Níveis de Acesso
| Nível | Nome | Descrição |
|-------|------|-----------|
| **1** | **Coordenador** | Acesso total - gerencia usuários, backups, todos os módulos |
| **2** | **DIPLAN** | Planejamento - importa PCA, gera relatórios, visualiza licitações |
| **3** | **DIPLI** | Licitações - cria/gerencia licitações, visualiza PCA, relatórios básicos |
| **4** | **Visitante** | Somente leitura - visualiza dados, gera relatórios, exporta informações |

### Gestão de Usuários
- **Interface:** `gerenciar_usuarios.php`
- **Funcionalidades:** Busca, filtros, paginação
- **Controle:** Permissões por nível hierárquico

## 🎯 Módulos Principais

### 1. 📊 Planejamento (dashboard.php)
**Gestão do Plano de Contratações Anual (PCA)**

**PCAs Disponíveis:**
- **2022-2024:** Históricos (apenas visualização)
- **2025-2026:** Atuais (editáveis, permite criar licitações)

**Funcionalidades:**
- ✅ Importação de arquivos CSV
- ✅ Visualização de dados com filtros
- ✅ Gráficos interativos (Chart.js)
- ✅ Dashboard com estatísticas
- ✅ Relatórios e exportações
- ✅ Sistema de cache para performance

**Volume de Dados:** Gerencia centenas de contratações com vários GB de dados

### 2. ⚖️ Licitações (licitacao_dashboard.php)
**Controle de Processos Licitatórios**

**Funcionalidades:**
- ✅ Criação de licitações com autocomplete de contratações
- ✅ Vinculação automática com dados do PCA
- ✅ Controle de situações (Em Andamento, Homologado, Fracassado, Revogado)
- ✅ Cálculo automático de economia
- ✅ Gráficos por modalidade, pregoeiro e evolução mensal
- ✅ Sistema de edição inline
- ✅ Filtros avançados
- ✅ Exportação para Excel/CSV

**Campos de Licitação:**
- NUP, Modalidade, Tipo, Número/Ano
- Responsável Instrução, Área Demandante, Pregoeiro
- Datas (Entrada DIPLI, Abertura, Homologação)
- Valores (Estimado, Homologado, Economia)
- Objeto, Situação, Link, Observações

### 3. 🛡️ Gestão de Riscos (gestao_riscos.php)
**Sistema de Análise de Riscos com Matriz 5x5**

**Funcionalidades:**
- ✅ Matriz de risco 5x5 (Probabilidade × Impacto)
- ✅ Categorização por tipo de risco
- ✅ Vinculação com DFDs do PCA
- ✅ Ações de mitigação e responsáveis
- ✅ Relatórios mensais em PDF/HTML
- ✅ Dashboard com estatísticas visuais

### 4. 👥 Usuários (gerenciar_usuarios.php)
**Gestão de Permissões e Níveis**

**Funcionalidades:**
- ✅ Controle por 4 níveis de acesso
- ✅ Busca e filtros por nome/email/departamento
- ✅ Paginação (10 usuários por página)
- ✅ Histórico de login
- ✅ Bloqueio por tentativas excessivas

### 5. 💾 Sistema de Backup
**Backup Automático e Manual**

**Funcionalidades:**
- ✅ Backup de banco de dados e arquivos
- ✅ Interface web para execução
- ✅ Histórico de backups com estatísticas
- ✅ Verificação de integridade
- ✅ Download de arquivos de backup

## 📊 Banco de Dados

### Configuração
- **Nome:** `sistema_licitacao`
- **Charset:** `utf8mb4_unicode_ci`
- **Engine:** InnoDB com foreign keys

### Tabelas Principais
| Tabela | Descrição | Registros Típicos |
|--------|-----------|-------------------|
| `usuarios` | Usuários e permissões (4 níveis) | 50-200 |
| `pca_dados` | Dados do PCA atual (2025-2026) | 200-500+ |
| `pca_historico_anos` | PCAs históricos (2022-2024) | 1000+ |
| `licitacoes` | Processos licitatórios | 100-300+ |
| `pca_riscos` | Gestão de riscos (matriz 5x5) | 50-200+ |
| `pca_importacoes` | Histórico de importações | 50+ |
| `backups_sistema` | Controle de backups | 100+ |
| `logs_sistema` | Logs de operações | 1000+ |

### Relacionamentos
- `licitacoes.pca_dados_id` → `pca_dados.id`
- `licitacoes.usuario_id` → `usuarios.id`
- `pca_importacoes.usuario_id` → `usuarios.id`

## 🔧 Funcionalidades Técnicas

### Sistema de Cache
- **Localização:** `/cache/`
- **Tipos:** Dados de gráficos, estatísticas do dashboard
- **Benefício:** Performance para consultas pesadas

### Importação de Dados
- **Formato:** CSV com encoding UTF-8
- **Validação:** Verificação de estrutura e dados
- **Backup:** Arquivos originais mantidos em `/storage/uploads/`
- **Limpeza:** Remoção de caracteres especiais e encoding

### Sistema de Logs
- **Tabela:** `logs_sistema`
- **Eventos:** Login, criação/edição de licitações, importações
- **Auditoria:** Rastreamento completo de ações

### Autocomplete Inteligente
- **Funcionalidade:** Busca de contratações em tempo real
- **Performance:** Cache de dados JavaScript
- **Integração:** Preenchimento automático de campos relacionados

## 🔒 Segurança

### Autenticação
- ✅ Senhas criptografadas (bcrypt)
- ✅ Sessões seguras com timeout
- ✅ Controle de tentativas de login
- ✅ Tokens CSRF em formulários

### Proteções
- ✅ SQL Injection (Prepared Statements)
- ✅ XSS (Sanitização de dados)
- ✅ Validação de entrada
- ✅ Controle de permissões por módulo

### Logs de Segurança
- ✅ Tentativas de login registradas
- ✅ Operações críticas auditadas
- ✅ Bloqueio automático por atividade suspeita

## 📈 Performance e Otimização

### Banco de Dados
- ✅ Índices em campos frequentemente consultados
- ✅ Foreign keys para integridade
- ✅ Queries otimizadas com LIMIT

### Frontend
- ✅ Cache de dados JavaScript
- ✅ Carregamento lazy de gráficos
- ✅ Debounce em autocomplete
- ✅ Paginação para listas grandes

### Volume Suportado
- **Usuários:** 50-200 ativos
- **Contratações:** 500+ por ano
- **Licitações:** 300+ ativas
- **Performance:** Subsegundo para consultas principais

## 🔄 Operações Comuns

### Workflow de Uso
1. **Login** → Seleção de módulos
2. **Planejamento:** Importar PCA → Visualizar dados → Gerar relatórios
3. **Licitações:** Criar licitação → Vincular com PCA → Acompanhar processo
4. **Gestão:** Gerenciar usuários → Verificar logs → Backup

### Importação de PCA
1. Preparar arquivo CSV (UTF-8)
2. Acessar Dashboard de Planejamento
3. Usar botão "Importar CSV"
4. Validar dados importados
5. Verificar no dashboard

### Criação de Licitação
1. Acessar Dashboard de Licitações
2. Clicar "Nova Licitação"
3. Buscar número da contratação (autocomplete)
4. Preencher dados específicos
5. Salvar e acompanhar

## 🐛 Troubleshooting

### Problemas Comuns

**1. Erro de Conexão com Banco**
```
Verificar: config.php
Solução: Confirmar credenciais MySQL no XAMPP
```

**2. Importação CSV Falha**
```
Causa: Encoding incorreto
Solução: Salvar CSV como UTF-8 no Excel
```

**3. Autocomplete Não Funciona**
```
Causa: JavaScript desabilitado ou dados não carregados
Solução: Verificar console do navegador
```

**4. Valores Multiplicados**
```
Causa: Formatação monetária
Status: ✅ Corrigido na versão atual
```

### Logs de Debug
- **Localização:** Console do navegador (F12)
- **Arquivo:** Logs do Apache em `C:\xampp\apache\logs\`

## 📝 Changelog Recente

### ✅ Correções Implementadas
- **Sistema de criação de licitações:** Corrigido erro de colunas inexistentes
- **Valores monetários:** Corrigido problema de multiplicação/formatação
- **Campo valor homologado:** Corrigido limitação de entrada de números grandes
- **Autocomplete:** Corrigido conflitos entre sistemas inline e externo
- **Estrutura do banco:** Alinhada com realidade do sistema
- **Resposta JSON:** Corrigida para formulários AJAX

### 🔧 Melhorias Técnicas
- **Formatação monetária:** Sistema inteligente que detecta formato brasileiro
- **Validação de dados:** Uso de `floatval()` em vez de `str_replace` problemático
- **Interface de usuário:** Mantida posição do cursor durante formatação
- **Sistema de logs:** Melhorado para debug de erros

## 🎯 Roadmap

### Próximas Implementações
- [ ] API REST completa
- [ ] Sistema de notificações
- [ ] Relatórios personalizáveis
- [ ] Dashboard mobile otimizado
- [ ] Integração com outros sistemas

### Otimizações Futuras
- [ ] Cache Redis para alta performance
- [ ] Backup automático agendado
- [ ] Monitoramento de sistema
- [ ] Logs estruturados (JSON)

## 📞 Suporte

### Desenvolvimento
- **Assistente:** Claude AI
- **Documentação:** CLAUDE.md (instruções técnicas detalhadas)

### Ambiente
- **Local:** XAMPP
- **Produção:** A definir conforme necessidades do Ministério da Saúde

### Contato
Para questões técnicas, consulte o arquivo `CLAUDE.md` que contém instruções detalhadas para desenvolvedores.

---

**📌 IMPORTANTE:** Este sistema foi desenvolvido especificamente para as necessidades da CGLIC - Ministério da Saúde, com foco em usabilidade, performance e conformidade com processos governamentais.

**🏥 Ministério da Saúde - CGLIC**  
**Sistema de Informações para Gestão de Licitações**  
**Versão Atual:** Estável com todas as funcionalidades principais implementadas