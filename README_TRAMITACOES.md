# 📋 Módulo de Tramitações - Sistema CGLIC

## 📌 **Visão Geral**

O Módulo de Tramitações é um sistema de fluxo de trabalho entre os diferentes módulos do Sistema CGLIC, funcionando de forma similar ao Trello. Permite que usuários enviem demandas de um módulo para outro, acompanhem o progresso e gerenciem tarefas de forma colaborativa.

## 🏗️ **Arquitetura**

### **Tabelas Criadas**

1. **`tramitacoes`** - Tabela principal com as tramitações
2. **`tramitacoes_historico`** - Timeline completa de ações
3. **`tramitacoes_comentarios`** - Sistema de comentários
4. **`tramitacoes_templates`** - Templates pré-definidos
5. **`tramitacoes_anexos`** - Anexos de arquivos

### **Views Criadas**

1. **`v_tramitacoes_resumo`** - View consolidada com dados dos usuários
2. **`v_tramitacoes_dashboard`** - Estatísticas por módulo e status

### **Funções e Triggers**

1. **`gerar_numero_tramite()`** - Gera números únicos (000001/2025)
2. **Triggers automáticos** para histórico de mudanças
3. **Sistema de auditoria** completo

## 🎯 **Funcionalidades**

### **1. Criação de Tramitações**
- Interface intuitiva com formulário modal
- Validações automáticas
- Numeração automática
- Templates pré-definidos

### **2. Acompanhamento de Status**
- Status: PENDENTE → EM_ANDAMENTO → CONCLUIDA
- Estados especiais: AGUARDANDO, DEVOLVIDA, CANCELADA
- Timeline visual com histórico completo

### **3. Sistema de Prioridades**
- 4 níveis: BAIXA, MEDIA, ALTA, URGENTE
- Indicação visual por cores
- Ordenação automática por prioridade

### **4. Controle de Prazos**
- Prazos opcionais com alertas
- Indicação visual: No prazo, Vencendo, Atrasado
- Sistema de notificações

### **5. Comentários e Comunicação**
- Sistema de comentários por tramitação
- Diferentes tipos de comentário
- Controle de visibilidade

## 🔐 **Sistema de Permissões**

### **Por Nível de Usuário:**

| Nível | Nome | Tramitações |
|-------|------|-------------|
| **1** | Coordenador | ✅ Total (criar, editar, comentar, gerenciar) |
| **2** | DIPLAN | ✅ Criar, editar, comentar suas tramitações |
| **3** | DIPLI | ✅ Criar, editar, comentar suas tramitações |
| **4** | Visitante | 👁️ Apenas visualizar |

### **Regras de Acesso:**
- Usuários veem apenas tramitações onde são origem ou destino
- Coordenador vê todas as tramitações
- Visitante pode visualizar mas não interagir

## 🚀 **Arquivos Implementados**

### **1. Scripts SQL**
- `scripts_sql/tramitacoes.sql` - Estrutura completa do banco

### **2. Interfaces Web**
- `tramitacoes.php` - Lista principal (estilo Kanban)
- `tramitacao_detalhes.php` - Detalhes com abas (histórico + comentários)

### **3. Processamento**
- `process.php` - APIs para criar, alterar status, comentar
- `functions.php` - Sistema de notificações e permissões

### **4. Integração**
- Menu "Tramitações" em todos os dashboards
- Badges de notificação com prazos
- Estilos CSS integrados

## 🎨 **Interface de Usuário**

### **Design System**
- Cards responsivos estilo Trello
- Cores por prioridade (Verde → Amarelo → Laranja → Vermelho)
- Status badges com cores semânticas
- Interface mobile-first

### **Estatísticas Dashboard**
- Total de tramitações
- Pendentes, em andamento, concluídas
- Atrasadas e vencendo hoje
- Filtros avançados

### **Sistema de Filtros**
- Por módulo (origem/destino)
- Por status
- Por prioridade
- Busca por texto
- Paginação otimizada

## 🔔 **Sistema de Notificações**

### **Funcionalidades Implementadas**
- Contagem de tramitações atrasadas/vencendo
- Badges visuais nos menus
- Funções para expansão futura

### **Expansões Futuras**
- Notificações por email
- Push notifications
- Webhooks para integrações
- Alertas em tempo real

## 📊 **Fluxos de Trabalho**

### **Fluxos Comuns Implementados**

1. **Planejamento → Licitação**
   - Análise técnica de DFDs
   - Solicitação de processo licitatório

2. **Licitação → Qualificação**
   - Habilitação de fornecedores
   - Verificação de documentos

3. **Licitação → Contratos**
   - Formalização de contratos
   - Pós-adjudicação

4. **Licitação → Planejamento**
   - Revisão de planejamento
   - Atualizações no PCA

### **Templates Pré-definidos**
- 5 templates principais já configurados
- Campos e prazos pré-preenchidos
- Facilita criação rápida

## 🛠️ **Instalação e Configuração**

### **1. Executar Script SQL**
```sql
-- Executar no banco de dados
source scripts_sql/tramitacoes.sql
```

### **2. Verificar Integração**
- Menus aparecem automaticamente
- Permissões já configuradas
- Badges de notificação ativos

### **3. Teste Básico**
1. Acessar via menu "Tramitações"
2. Criar nova tramitação
3. Verificar detalhes e comentários
4. Testar mudança de status

## 📈 **Métricas e Análises**

### **Relatórios Disponíveis**
- Tramitações por módulo
- Performance por usuário
- Tempo médio de conclusão
- Taxa de atraso por prioridade

### **Views SQL para Consultas**
```sql
-- Estatísticas gerais
SELECT * FROM v_tramitacoes_dashboard;

-- Tramitações com detalhes
SELECT * FROM v_tramitacoes_resumo WHERE status = 'PENDENTE';

-- Tramitações atrasadas
SELECT * FROM v_tramitacoes_resumo 
WHERE situacao_prazo = 'ATRASADO';
```

## 🔧 **Manutenção**

### **Limpeza Automática**
- Triggers mantêm histórico atualizado
- Numeração sequencial automática
- Índices otimizados para performance

### **Logs e Auditoria**
- Todas as ações são registradas
- Sistema de logs integrado
- Rastreabilidade completa

## 🚀 **Próximas Melhorias**

### **Curto Prazo**
- [ ] Upload de anexos
- [ ] Notificações por email
- [ ] Relatórios avançados
- [ ] API REST completa

### **Médio Prazo**
- [ ] Integração com calendário
- [ ] Dashboard executivo
- [ ] Automação de fluxos
- [ ] Integração externa

### **Longo Prazo**
- [ ] IA para sugestões
- [ ] Mobile app
- [ ] Integrações gov.br
- [ ] Workflow designer

## 📋 **Checklist de Implementação**

- ✅ Script SQL criado e testado
- ✅ Interface principal implementada
- ✅ Página de detalhes funcional
- ✅ Sistema de comentários ativo
- ✅ Integração com menus existentes
- ✅ Sistema de permissões configurado
- ✅ Badges de notificação implementados
- ✅ Processamento de formulários
- ✅ Validações e segurança
- ✅ Design responsivo
- ✅ Documentação completa

---

## 💡 **Como Usar**

### **Para Usuários Finais:**
1. Acesse via menu lateral "Tramitações"
2. Use "Nova Tramitação" para criar demandas
3. Acompanhe o progresso através dos cards
4. Comente e interaja conforme necessário

### **Para Administradores:**
1. Execute o script SQL uma única vez
2. Sistema já integrado automaticamente
3. Gerencie permissões via níveis de usuário
4. Monitor através das views de relatório

---

**🎯 O Módulo de Tramitações está totalmente funcional e integrado ao Sistema CGLIC, proporcionando um fluxo de trabalho eficiente entre todos os módulos!**