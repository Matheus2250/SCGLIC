-- ========================================
-- SCRIPT PARA CRIAÇÃO DA TABELA PCA_PNCP
-- Integração com API do PNCP (Portal Nacional de Contratações Públicas)
-- ========================================

-- Tabela para armazenar dados do PCA obtidos via API do PNCP
CREATE TABLE IF NOT EXISTS `pca_pncp` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `orgao_cnpj` varchar(18) NOT NULL COMMENT 'CNPJ do órgão (00394544000185)',
    `ano_pca` int(4) NOT NULL COMMENT 'Ano do PCA (2026)',
    `sequencial` int(11) DEFAULT NULL COMMENT 'Sequencial do item no PCA',
    `categoria_item` varchar(100) DEFAULT NULL COMMENT 'Categoria do item',
    `subcategoria_item` varchar(100) DEFAULT NULL COMMENT 'Subcategoria do item',
    `descricao_item` text DEFAULT NULL COMMENT 'Descrição detalhada do item',
    `justificativa` text DEFAULT NULL COMMENT 'Justificativa da contratação',
    `valor_estimado` decimal(15,2) DEFAULT NULL COMMENT 'Valor estimado da contratação',
    `unidade_medida` varchar(50) DEFAULT NULL COMMENT 'Unidade de medida',
    `quantidade` decimal(15,3) DEFAULT NULL COMMENT 'Quantidade estimada',
    `modalidade_licitacao` varchar(50) DEFAULT NULL COMMENT 'Modalidade de licitação prevista',
    `trimestre_previsto` int(1) DEFAULT NULL COMMENT 'Trimestre previsto (1-4)',
    `mes_previsto` int(2) DEFAULT NULL COMMENT 'Mês previsto (1-12)',
    `situacao_item` varchar(50) DEFAULT NULL COMMENT 'Situação atual do item',
    `codigo_pncp` varchar(50) DEFAULT NULL COMMENT 'Código identificador no PNCP',
    `unidade_requisitante` varchar(200) DEFAULT NULL COMMENT 'Unidade requisitante',
    `endereco_unidade` text DEFAULT NULL COMMENT 'Endereço da unidade',
    `responsavel_demanda` varchar(200) DEFAULT NULL COMMENT 'Responsável pela demanda',
    `email_responsavel` varchar(200) DEFAULT NULL COMMENT 'Email do responsável',
    `telefone_responsavel` varchar(20) DEFAULT NULL COMMENT 'Telefone do responsável',
    `observacoes` text DEFAULT NULL COMMENT 'Observações gerais',
    `data_ultima_atualizacao` datetime DEFAULT NULL COMMENT 'Data da última atualização no PNCP',
    `data_sincronizacao` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Data da sincronização com a API',
    `sincronizado_em` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp da sincronização',
    `hash_dados` varchar(64) DEFAULT NULL COMMENT 'Hash MD5 dos dados para controle de mudanças',
    `status_sincronizacao` enum('sucesso','erro','pendente') DEFAULT 'sucesso',
    `dados_originais_json` longtext DEFAULT NULL COMMENT 'Dados originais em formato JSON',
    `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pca_pncp_item` (`orgao_cnpj`, `ano_pca`, `sequencial`),
    KEY `idx_pca_pncp_ano` (`ano_pca`),
    KEY `idx_pca_pncp_categoria` (`categoria_item`),
    KEY `idx_pca_pncp_modalidade` (`modalidade_licitacao`),
    KEY `idx_pca_pncp_trimestre` (`trimestre_previsto`),
    KEY `idx_pca_pncp_situacao` (`situacao_item`),
    KEY `idx_pca_pncp_sincronizacao` (`data_sincronizacao`),
    KEY `idx_pca_pncp_hash` (`hash_dados`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dados do PCA obtidos da API do PNCP';

-- Tabela para controlar as sincronizações com a API do PNCP
CREATE TABLE IF NOT EXISTS `pca_pncp_sincronizacoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `orgao_cnpj` varchar(18) NOT NULL,
    `ano_pca` int(4) NOT NULL,
    `url_api` varchar(500) NOT NULL COMMENT 'URL da API utilizada',
    `tipo_sincronizacao` enum('manual','automatica') DEFAULT 'manual',
    `status` enum('iniciada','em_andamento','concluida','erro') DEFAULT 'iniciada',
    `total_registros_api` int(11) DEFAULT NULL COMMENT 'Total de registros retornados pela API',
    `registros_processados` int(11) DEFAULT 0 COMMENT 'Registros processados',
    `registros_novos` int(11) DEFAULT 0 COMMENT 'Novos registros inseridos',
    `registros_atualizados` int(11) DEFAULT 0 COMMENT 'Registros atualizados',
    `registros_ignorados` int(11) DEFAULT 0 COMMENT 'Registros ignorados (duplicados)',
    `tempo_processamento` int(11) DEFAULT NULL COMMENT 'Tempo de processamento em segundos',
    `tamanho_arquivo_csv` int(11) DEFAULT NULL COMMENT 'Tamanho do CSV baixado em bytes',
    `mensagem_erro` text DEFAULT NULL COMMENT 'Mensagem de erro se houver',
    `detalhes_execucao` longtext DEFAULT NULL COMMENT 'Log detalhado da execução',
    `usuario_id` int(11) DEFAULT NULL COMMENT 'ID do usuário que executou',
    `usuario_nome` varchar(200) DEFAULT NULL COMMENT 'Nome do usuário',
    `ip_origem` varchar(45) DEFAULT NULL COMMENT 'IP de origem da solicitação',
    `iniciada_em` datetime DEFAULT CURRENT_TIMESTAMP,
    `finalizada_em` datetime DEFAULT NULL,
    `criada_em` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pncp_sync_orgao_ano` (`orgao_cnpj`, `ano_pca`),
    KEY `idx_pncp_sync_status` (`status`),
    KEY `idx_pncp_sync_data` (`iniciada_em`),
    KEY `idx_pncp_sync_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Controle de sincronizações com API do PNCP';

-- Inserir configuração padrão para o Ministério da Saúde
INSERT IGNORE INTO `pca_pncp_sincronizacoes` 
(`orgao_cnpj`, `ano_pca`, `url_api`, `tipo_sincronizacao`, `status`, `usuario_nome`) 
VALUES 
('00394544000185', 2026, 'https://pncp.gov.br/api/pncp/v1/orgaos/00394544000185/pca/2026/csv', 'manual', 'concluida', 'Sistema');

-- ========================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ========================================

-- Índice composto para consultas por categoria e ano
CREATE INDEX IF NOT EXISTS `idx_pca_pncp_categoria_ano` ON `pca_pncp` (`categoria_item`, `ano_pca`);

-- Índice para consultas por valor
CREATE INDEX IF NOT EXISTS `idx_pca_pncp_valor` ON `pca_pncp` (`valor_estimado`);

-- Índice para consultas por período (trimestre/mês)
CREATE INDEX IF NOT EXISTS `idx_pca_pncp_periodo` ON `pca_pncp` (`trimestre_previsto`, `mes_previsto`);

-- ========================================
-- COMENTÁRIOS E DOCUMENTAÇÃO
-- ========================================

/*
TABELA: pca_pncp
- Armazena dados do PCA obtidos diretamente da API do PNCP
- Permite comparação com dados internos (tabela pca_dados)
- Estrutura otimizada para consultas e relatórios
- Suporte a versionamento via hash_dados

TABELA: pca_pncp_sincronizacoes  
- Controla histórico de sincronizações
- Monitora performance e erros
- Auditoria de operações

API INTEGRADA:
- URL: https://pncp.gov.br/api/pncp/v1/orgaos/00394544000185/pca/2026/csv
- Órgão: Ministério da Saúde (CNPJ: 00394544000185)
- Ano: 2026
- Formato: CSV

CAMPOS PRINCIPAIS:
- codigo_pncp: Identificador único no PNCP
- sequencial: Número sequencial do item
- categoria_item/subcategoria_item: Classificação
- descricao_item: Descrição detalhada
- valor_estimado: Valor previsto
- modalidade_licitacao: Tipo de licitação
- trimestre_previsto/mes_previsto: Cronograma
- situacao_item: Status atual

FUNCIONALIDADES:
1. Sincronização automática/manual
2. Controle de duplicatas via hash
3. Histórico de mudanças
4. Relatórios comparativos
5. Monitoramento de performance
*/