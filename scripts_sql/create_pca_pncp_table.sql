-- ========================================
-- SCRIPT PARA CRIAÇÃO DA TABELA PCA_PNCP
-- Integração com API do PNCP (Portal Nacional de Contratações Públicas)
-- ========================================

-- Remover tabela existente se houver para recriar com nova estrutura
DROP TABLE IF EXISTS `pca_pncp`;

-- Tabela para armazenar dados do PCA obtidos via API do PNCP
CREATE TABLE IF NOT EXISTS `pca_pncp` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `orgao_cnpj` varchar(18) NOT NULL DEFAULT '00394544000185' COMMENT 'CNPJ do órgão',
    `ano_pca` int(4) NOT NULL DEFAULT 2026 COMMENT 'Ano do PCA',
    `unidade_responsavel` varchar(300) DEFAULT NULL COMMENT 'Unidade Responsável',
    `uasg` varchar(20) DEFAULT NULL COMMENT 'UASG',
    `id_item_pca` varchar(50) DEFAULT NULL COMMENT 'Id do item no PCA',
    `categoria_item` varchar(100) DEFAULT NULL COMMENT 'Categoria do Item',
    `identificador_futura_contratacao` varchar(100) DEFAULT NULL COMMENT 'Identificador da Futura Contratação',
    `nome_futura_contratacao` text DEFAULT NULL COMMENT 'Nome da Futura Contratação',
    `catalogo_utilizado` varchar(100) DEFAULT NULL COMMENT 'Catálogo Utilizado',
    `classificacao_catalogo` varchar(200) DEFAULT NULL COMMENT 'Classificação do Catálogo',
    `codigo_classificacao_superior` varchar(50) DEFAULT NULL COMMENT 'Código da Classificação Superior (Classe/Grupo)',
    `nome_classificacao_superior` varchar(300) DEFAULT NULL COMMENT 'Nome da Classificação Superior (Classe/Grupo)',
    `codigo_pdm_item` varchar(50) DEFAULT NULL COMMENT 'Código do PDM do Item',
    `nome_pdm_item` varchar(300) DEFAULT NULL COMMENT 'Nome do PDM do Item',
    `codigo_item` varchar(50) DEFAULT NULL COMMENT 'Código do Item',
    `descricao_item_fornecimento` text DEFAULT NULL COMMENT 'Descrição do Item e de Fornecimento',
    `quantidade_estimada` decimal(15,3) DEFAULT NULL COMMENT 'Quantidade Estimada',
    `valor_unitario_estimado` decimal(15,2) DEFAULT NULL COMMENT 'Valor Unitário Estimado (R$)',
    `valor_total_estimado` decimal(15,2) DEFAULT NULL COMMENT 'Valor Total Estimado (R$)',
    `valor_orcamentario_exercicio` decimal(15,2) DEFAULT NULL COMMENT 'Valor Orçamentário estimado para o exercício (R$)',
    `data_desejada` date DEFAULT NULL COMMENT 'Data Desejada',
    `unidade` varchar(50) DEFAULT NULL COMMENT 'Unidade',
    `data_sincronizacao` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Data da sincronização com a API',
    `sincronizado_em` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp da sincronização',
    `hash_dados` varchar(64) DEFAULT NULL COMMENT 'Hash MD5 dos dados para controle de mudanças',
    `status_sincronizacao` enum('sucesso','erro','pendente') DEFAULT 'sucesso',
    `dados_originais_json` longtext DEFAULT NULL COMMENT 'Dados originais em formato JSON',
    `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pca_pncp_item` (`orgao_cnpj`, `ano_pca`, `uasg`, `id_item_pca`),
    KEY `idx_pca_pncp_orgao` (`orgao_cnpj`),
    KEY `idx_pca_pncp_ano` (`ano_pca`),
    KEY `idx_pca_pncp_uasg` (`uasg`),
    KEY `idx_pca_pncp_categoria` (`categoria_item`),
    KEY `idx_pca_pncp_identificador` (`identificador_futura_contratacao`),
    KEY `idx_pca_pncp_catalogo` (`catalogo_utilizado`),
    KEY `idx_pca_pncp_codigo_item` (`codigo_item`),
    KEY `idx_pca_pncp_codigo_pdm` (`codigo_pdm_item`),
    KEY `idx_pca_pncp_valor_total` (`valor_total_estimado`),
    KEY `idx_pca_pncp_data_desejada` (`data_desejada`),
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

-- Inserir configuração padrão para UASG 250110
INSERT IGNORE INTO `pca_pncp_sincronizacoes` 
(`orgao_cnpj`, `ano_pca`, `url_api`, `tipo_sincronizacao`, `status`, `usuario_nome`) 
VALUES 
('00394544000185', 2026, 'https://pncp.gov.br/api/pncp/v1/orgaos/00394544000185/pca/2026/csv?uasg=250110', 'manual', 'concluida', 'Sistema');

-- ========================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ========================================

-- Índice composto para consultas por categoria e ano
CREATE INDEX IF NOT EXISTS `idx_pca_pncp_categoria_ano` ON `pca_pncp` (`categoria_item`, `ano_pca`);

-- Índice para consultas por valor total
CREATE INDEX IF NOT EXISTS `idx_pca_pncp_valor_total` ON `pca_pncp` (`valor_total_estimado`);

-- Índice para consultas por data desejada
CREATE INDEX IF NOT EXISTS `idx_pca_pncp_data_desejada_ano` ON `pca_pncp` (`data_desejada`, `ano_pca`);

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
- URL: https://pncp.gov.br/api/pncp/v1/orgaos/00394544000185/pca/2026/csv?uasg=250110
- Órgão: Ministério da Saúde (CNPJ: 00394544000185)
- UASG: 250110
- Ano: 2026
- Formato: CSV

CAMPOS PRINCIPAIS:
- uasg: UASG específica (250110)
- id_item_pca: ID do item no PCA
- categoria_item: Categoria do Item
- identificador_futura_contratacao: Identificador da Futura Contratação
- nome_futura_contratacao: Nome da futura contratação
- codigo_item: Código do Item
- codigo_pdm_item: Código do PDM do Item
- valor_unitario_estimado/valor_total_estimado: Valores estimados
- quantidade_estimada: Quantidade prevista
- data_desejada: Data desejada da contratação

FUNCIONALIDADES:
1. Sincronização automática/manual
2. Controle de duplicatas via hash
3. Histórico de mudanças
4. Relatórios comparativos
5. Monitoramento de performance
*/