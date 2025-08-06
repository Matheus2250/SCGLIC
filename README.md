# Sistema de Informações CGLIC - Ministério da Saúde

Sistema web completo para gestão e controle de licitações, processos de contratação e Plano de Contratações Anual (PCA) da Coordenação Geral de Licitações do Ministério da Saúde.

## Visão Geral

**Nome:** Sistema de Informações CGLIC  
**Órgão:** Ministério da Saúde  
**Objetivo:** Organizar e gerenciar processos, informações e dados da Coordenação Geral de Licitações  
**URL Local:** http://localhost/sistema_licitacao  
**Versão:** v2025.12 - Sistema completo com 4 níveis de usuário  
**Status:** Funcionando completamente  

## Ambiente de Desenvolvimento

### Stack Tecnológica
- **Servidor:** XAMPP (Apache + MySQL + PHP)
- **Linguagem:** PHP (versão atual do XAMPP)
- **Banco de Dados:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript vanilla
- **Ícones:** Lucide Icons
- **Gráficos:** Chart.js
- **Mobile:** Responsivo completo
- **Cache:** Sistema avançado

### Arquitetura do Sistema
```
sistema_licitacao/
├── assets/                     # Frontend (3.577 linhas)
│   ├── dashboard.css           # Estilos do dashboard
│   ├── dashboard.js            # Scripts do dashboard
│   ├── licitacao-dashboard.css # Estilos das licitações
│   ├── licitacao-dashboard.js  # Scripts das licitações
│   ├── style.css               # Sistema completo de UI
│   ├── script.js               # Máscaras e validações
│   ├── mobile-improvements.css # Responsividade completa
│   ├── mobile-improvements.js  # UX mobile completa
│   ├── notifications.js        # Sistema de notificações
│   └── ux-improvements.js      # Loading states e validação
├── api/                        # APIs RESTful
│   ├── backup_api_simple.php   # API de backup para XAMPP
│   ├── exportar_licitacoes.php # Exportação customizada
│   ├── get_licitacao.php       # Busca dados de licitação
│   ├── get_pca_data.php        # Dados do PCA
│   └── process_risco.php       # CRUD de riscos
├── database/                   # Scripts do banco
│   └── estrutura_completa_2025.sql # Script consolidado atual
├── relatorios/                 # Sistema de relatórios
├── utils/                      # Utilitários especializados
├── backups/                    # Backups automáticos
├── cache/                      # Cache de performance
├── uploads/                    # Arquivos CSV importados
├── config.php                  # Configurações seguras
├── functions.php               # Funções principais
├── process.php                 # Processamento de formulários
├── index.php                   # Tela de login
├── selecao_modulos.php         # Menu principal
├── dashboard.php               # Módulo Planejamento
├── licitacao_dashboard.php     # Módulo Licitações
├── gestao_riscos.php           # Gestão de Riscos
├── gerenciar_usuarios.php      # Gestão de usuários
└── setup_codespaces.sh         # Setup GitHub Codespaces
```

## Instalação

### Requisitos
- XAMPP com PHP 7.4+ e MySQL 5.7+
- Navegador moderno (Chrome, Firefox, Safari, Edge)

### Instalação no XAMPP

1. **Baixar o projeto** e colocar em: `C:\xampp\htdocs\sistema_licitacao\`

2. **Criar o banco de dados:**
```sql
CREATE DATABASE sistema_licitacao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. **Executar script de instalação:**
```bash
mysql -u root -p sistema_licitacao < database/estrutura_completa_2025.sql
```

4. **Acessar o sistema:** `http://localhost/sistema_licitacao`

### Instalação via GitHub Codespaces
```bash
chmod +x setup_codespaces.sh
./setup_codespaces.sh
```

### Configuração
O sistema funciona sem configuração adicional. Para personalizar, edite `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_licitacao');
define('DB_USER', 'root');
define('DB_PASS', '');  // Sem senha por padrão no XAMPP
```

## Primeiro Acesso

**Usuário padrão:**
- Email: `admin@cglic.gov.br`
- Senha: `admin123`

**Importante:** Altere a senha após o primeiro login!

## Sistema de Usuários

### 4 Níveis de Acesso
| Nível | Nome | Permissões |
|-------|------|------------|
| **1** | **Coordenador** | Acesso total - gerencia usuários, backups, todos os módulos |
| **2** | **DIPLAN** | Planejamento: Edita PCA / Licitações: Visualiza apenas |
| **3** | **DIPLI** | Licitações: Edita / PCA: Visualiza apenas |
| **4** | **Visitante** | Somente leitura - visualiza dados, gera relatórios, exporta |

### Controle de Permissões por Módulo
| Módulo | Coordenador (1) | DIPLAN (2) | DIPLI (3) | Visitante (4) |
|--------|----------------|------------|-----------|---------------|
| **Planejamento** | Total | Edição | Visualização | Visualização |
| **Licitações** | Total | Visualização | Edição | Visualização |
| **Riscos** | Total | Visualização | Edição | Visualização |
| **Usuários** | Gestão | Bloqueado | Bloqueado | Bloqueado |

## Módulos Principais

### 1. Planejamento (dashboard.php)
**Gestão do Plano de Contratações Anual (PCA)**

**PCAs Disponíveis:**
- **2022-2024:** Históricos (apenas visualização)
- **2025-2026:** Atuais (editáveis, permite criar licitações)

**Funcionalidades:**
- Importação CSV com detecção automática de encoding
- Dashboard com 6 cards de estatísticas
- 4 gráficos interativos (Chart.js)
- Sistema de cache para performance
- 4 tipos de relatórios especializados
- Filtros avançados e paginação
- Sistema de backup integrado

### 2. Licitações (licitacao_dashboard.php)
**Controle Completo de Processos Licitatórios**

**Funcionalidades:**
- Autocomplete inteligente de contratações
- Vinculação automática com dados do PCA
- Sistema de edição inline
- 4 situações (Em Andamento, Homologado, Fracassado, Revogado)
- Cálculo automático de economia
- 4 tipos de relatórios especializados
- 4 gráficos de análise
- Exportação Excel/CSV/JSON

### 3. Gestão de Riscos (gestao_riscos.php)
**Sistema de Análise de Riscos**

**Funcionalidades:**
- Matriz de risco 5x5 (Probabilidade × Impacto)
- 5 categorias de risco
- Vinculação com DFDs do PCA
- Ações preventivas e de contingência
- Relatórios mensais em PDF/HTML
- Dashboard com visualização gráfica
- API CRUD completa

### 4. Usuários (gerenciar_usuarios.php)
**Gestão de Permissões**

**Funcionalidades:**
- Sistema de 4 níveis hierárquicos
- Busca e filtros avançados
- Paginação otimizada
- Controle de tentativas de login
- Logs de auditoria
- Bloqueio automático por atividade suspeita

### 5. Sistema de Backup
**Backup Automático e Manual**

**Funcionalidades:**
- API simplificada para XAMPP
- Backup de banco e arquivos
- Interface web intuitiva
- CLI para automação
- Histórico com estatísticas
- Verificação de integridade
- Limpeza automática

## Interface e Experiência do Usuário

### Sistema Frontend Avançado
- **UX Improvements:** LoadingManager, ValidationManager, ToastManager
- **Mobile-First:** Menu responsivo, gestos de toque, conversão automática tabela para cards
- **Notificações:** Auto-hide inteligente, pause/resume no hover
- **Design System:** 7 tipos de cards, modal system responsivo, grid flexível

### Responsividade
- **Breakpoints:** 768px (tablet), 480px (mobile)
- **Grid adaptativo:** Auto-fit minmax
- **Tabelas:** Scroll horizontal + cards mobile
- **Formulários:** Font-size 16px (prevenção zoom iOS)

## Banco de Dados

### Configuração
- **Nome:** `sistema_licitacao`
- **Charset:** `utf8mb4_unicode_ci`
- **Engine:** InnoDB com foreign keys

### Tabelas Principais
| Tabela | Descrição | Registros Típicos |
|--------|-----------|-------------------|
| `usuarios` | 4 níveis de usuário | 50-200 |
| `pca_dados` | PCA unificado (todos os anos) | 500-2000+ |
| `licitacoes` | Processos licitatórios | 100-300+ |
| `pca_riscos` | Matriz de riscos 5x5 | 50-200+ |
| `pca_importacoes` | Histórico de importações | 50+ |
| `backups_sistema` | Controle de backups | 100+ |
| `logs_sistema` | Auditoria completa | 1000+ |

## APIs e Relatórios

### APIs RESTful
```
api/backup_api_simple.php       # Backup automático
api/exportar_licitacoes.php     # Exportação customizada  
api/get_licitacao.php          # Dados de licitação por ID
api/get_pca_data.php           # Dados do PCA
api/process_risco.php          # CRUD completo de riscos
```

### Sistema de Relatórios (8 tipos)
**Módulo Licitações:**
1. Por Modalidade - Performance e distribuição
2. Por Pregoeiro - Análise individual  
3. Análise de Prazos - Gargalos temporais
4. Relatório Financeiro - Economia gerada

**Módulo PCA:**
1. Por Categoria - Criticidade e execução
2. Por Área Requisitante - Performance departamental
3. Análise de Prazos - DFDs atrasados
4. Relatório Financeiro - Execução orçamentária

### Formatos de Exportação
- **HTML:** Visualização responsiva
- **PDF:** Quando TCPDF disponível
- **CSV:** Excel compatível
- **JSON:** Para integrações

## Segurança

### Autenticação
- Senhas bcrypt
- Sessões seguras com regeneração
- Tokens CSRF em formulários
- Headers de segurança
- Rate limiting implícito

### Proteções Implementadas
- SQL Injection (Prepared Statements)
- XSS (Sanitização rigorosa)
- CSRF (Tokens em formulários)
- Controle de acesso por nível
- Auditoria completa

### Sistema de Logs
- Login/logout registrados
- Operações críticas auditadas
- Tentativas de acesso monitoradas
- Logs estruturados para análise

## Performance e Otimização

### Sistema de Cache
- Cache em arquivos com TTL
- Cache específico para dashboard (5 min)
- Cache para gráficos (10 min)
- Invalidação inteligente
- Estatísticas de uso

### Otimizações Frontend
- Debounce em autocomplete (300ms)
- Lazy loading de gráficos
- Paginação inteligente
- Compressão de assets

### Volume Suportado
- **Usuários:** 50-200 ativos
- **Contratações:** 2000+ (multi-ano)
- **Licitações:** 300+ ativas
- **Performance:** Subsegundo com cache

## Operações Comuns

### Workflow Típico
1. **Login** → Seleção de módulos baseada no nível
2. **Planejamento:** Importar PCA → Dashboard → Relatórios
3. **Licitações:** Criar → Vincular PCA → Acompanhar
4. **Gestão:** Usuários → Riscos → Backup

### Importação de PCA
- Upload CSV (UTF-8, ISO-8859-1, Windows-1252)
- Validação automática de estrutura
- Processamento em lotes
- Histórico completo
- Cache invalidation

### Criação de Licitação
- Autocomplete de contratações
- Preenchimento automático
- Validação em tempo real
- Cálculo de economia
- Vinculação PCA automática

## Troubleshooting

### Problemas Comuns

**1. Erro de Conexão:**
```
Arquivo: config.php
Verificar: Credenciais MySQL
```

**2. Cache Problemático:**
```
Solução: cache.php → clearExpired()
```

**3. Importação Falha:**
```
Causa: Encoding incorreto
Solução: UTF-8 ou usar limpar_encoding.php
```

**4. Mobile Não Responsivo:**
```
Arquivo: mobile-improvements.css
Verificar: viewport meta tag
```

### Debug
- Console navegador (F12)
- Logs Apache: `C:\xampp\apache\logs\`
- Cache stats: Sistema de estatísticas
- Logs sistema: Tabela `logs_sistema`

## DevOps e Deploy

### GitHub Codespaces
```bash
# Setup automatizado incluído
./setup_codespaces.sh
# MySQL setup + DB import + PHP server
```

### Backup Automático
```bash
# CLI disponível
php utils/cron_backup.php --tipo=completo
# API web também disponível
```

### Monitoramento
- Estatísticas de cache
- Logs de performance
- Auditoria de usuários
- Histórico de backups

## Documentação Técnica

### Arquivos de Documentação
- **CLAUDE.md:** Instruções completas para desenvolvedores
- **README.md:** Este arquivo (visão geral)
- **Código:** Comentado e organizado

### Análise Técnica
- **69 arquivos** analisados (100% do sistema)
- **3.577 linhas** de frontend (JS+CSS)
- **Sistema de cache** (278 linhas)
- **Mobile-first** (871 linhas dedicadas)

## Características do Sistema

### Sistema Governamental Completo
- 4 níveis hierárquicos de usuário
- Auditoria completa de operações
- Relatórios especializados para gestão pública
- Conformidade com processos do Ministério da Saúde

### Performance Empresarial
- Sistema de cache multi-camada
- Otimizações para grandes volumes
- Paginação inteligente
- Loading states profissionais

### UX Moderna
- Mobile-first responsivo
- Validação em tempo real
- Notificações toast elegantes
- Gestos de toque nativos

### DevOps Ready
- GitHub Codespaces automatizado
- Sistema de backup robusto
- APIs para integrações
- Logs estruturados

---

**Ministério da Saúde - CGLIC**  
**Sistema de Informações para Gestão de Licitações**  
**Versão:** v2025.12  
**Status:** Produção Ready