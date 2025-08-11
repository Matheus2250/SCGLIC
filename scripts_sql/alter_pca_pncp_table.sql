-- ========================================
-- SCRIPT PARA ALTERAR A TABELA PCA_PNCP
-- Modificação da estrutura existente sem perder dados
-- ========================================

-- Verificar se a tabela existe
-- Se não existir, você deve executar o script create_pca_pncp_table.sql primeiro

-- Adicionar colunas necessárias se não existirem
ALTER TABLE `pca_pncp` 
ADD COLUMN IF NOT EXISTS `ano_pca` int(4) NOT NULL DEFAULT 2026 COMMENT 'Ano do PCA' AFTER `id`,
ADD COLUMN IF NOT EXISTS `orgao_cnpj` varchar(18) NOT NULL DEFAULT '00394544000185' COMMENT 'CNPJ do órgão' AFTER `id`,
ADD COLUMN IF NOT EXISTS `sequencial` int(11) DEFAULT NULL COMMENT 'Sequencial do item no PCA' AFTER `ano_pca`;

-- Adicionar novas colunas do PNCP se não existirem
ALTER TABLE `pca_pncp`
ADD COLUMN IF NOT EXISTS `codigo_pncp` varchar(50) DEFAULT NULL COMMENT 'Código identificador no PNCP',
ADD COLUMN IF NOT EXISTS `subcategoria_item` varchar(100) DEFAULT NULL COMMENT 'Subcategoria do item',
ADD COLUMN IF NOT EXISTS `descricao_item` text DEFAULT NULL COMMENT 'Descrição detalhada do item',
ADD COLUMN IF NOT EXISTS `justificativa` text DEFAULT NULL COMMENT 'Justificativa da contratação',
ADD COLUMN IF NOT EXISTS `valor_estimado` decimal(15,2) DEFAULT NULL COMMENT 'Valor estimado da contratação',
ADD COLUMN IF NOT EXISTS `unidade_medida` varchar(50) DEFAULT NULL COMMENT 'Unidade de medida',
ADD COLUMN IF NOT EXISTS `quantidade` decimal(15,3) DEFAULT NULL COMMENT 'Quantidade estimada',
ADD COLUMN IF NOT EXISTS `modalidade_licitacao` varchar(50) DEFAULT NULL COMMENT 'Modalidade de licitação prevista',
ADD COLUMN IF NOT EXISTS `trimestre_previsto` int(1) DEFAULT NULL COMMENT 'Trimestre previsto (1-4)',
ADD COLUMN IF NOT EXISTS `mes_previsto` int(2) DEFAULT NULL COMMENT 'Mês previsto (1-12)',
ADD COLUMN IF NOT EXISTS `situacao_item` varchar(50) DEFAULT NULL COMMENT 'Situação atual do item',
ADD COLUMN IF NOT EXISTS `unidade_requisitante` varchar(200) DEFAULT NULL COMMENT 'Unidade requisitante',
ADD COLUMN IF NOT EXISTS `endereco_unidade` text DEFAULT NULL COMMENT 'Endereço da unidade',
ADD COLUMN IF NOT EXISTS `responsavel_demanda` varchar(200) DEFAULT NULL COMMENT 'Responsável pela demanda',
ADD COLUMN IF NOT EXISTS `email_responsavel` varchar(200) DEFAULT NULL COMMENT 'Email do responsável',
ADD COLUMN IF NOT EXISTS `telefone_responsavel` varchar(20) DEFAULT NULL COMMENT 'Telefone do responsável',
ADD COLUMN IF NOT EXISTS `observacoes` text DEFAULT NULL COMMENT 'Observações gerais',
ADD COLUMN IF NOT EXISTS `data_ultima_atualizacao` datetime DEFAULT NULL COMMENT 'Data da última atualização no PNCP',
ADD COLUMN IF NOT EXISTS `unidade_responsavel` varchar(300) DEFAULT NULL COMMENT 'Unidade Responsável',
ADD COLUMN IF NOT EXISTS `uasg` varchar(20) DEFAULT NULL COMMENT 'UASG',
ADD COLUMN IF NOT EXISTS `id_item_pca` varchar(50) DEFAULT NULL COMMENT 'Id do item no PCA',
ADD COLUMN IF NOT EXISTS `codigo_pdm_item` varchar(50) DEFAULT NULL COMMENT 'Código do PDM do Item',
ADD COLUMN IF NOT EXISTS `nome_pdm_item` varchar(300) DEFAULT NULL COMMENT 'Nome do PDM do Item',
ADD COLUMN IF NOT EXISTS `codigo_item` varchar(50) DEFAULT NULL COMMENT 'Código do Item',
ADD COLUMN IF NOT EXISTS `descricao_item_fornecimento` text DEFAULT NULL COMMENT 'Descrição do Item e de Fornecimento',
ADD COLUMN IF NOT EXISTS `quantidade_estimada` decimal(15,3) DEFAULT NULL COMMENT 'Quantidade Estimada',
ADD COLUMN IF NOT EXISTS `valor_unitario_estimado` decimal(15,2) DEFAULT NULL COMMENT 'Valor Unitário Estimado (R$)',
ADD COLUMN IF NOT EXISTS `valor_total_estimado` decimal(15,2) DEFAULT NULL COMMENT 'Valor Total Estimado (R$)',
ADD COLUMN IF NOT EXISTS `valor_orcamentario_exercicio` decimal(15,2) DEFAULT NULL COMMENT 'Valor Orçamentário estimado para o exercício (R$)',
ADD COLUMN IF NOT EXISTS `data_desejada` date DEFAULT NULL COMMENT 'Data Desejada',
ADD COLUMN IF NOT EXISTS `unidade` varchar(50) DEFAULT NULL COMMENT 'Unidade',
ADD COLUMN IF NOT EXISTS `identificador_futura_contratacao` varchar(100) DEFAULT NULL COMMENT 'Identificador da Futura Contratação',
ADD COLUMN IF NOT EXISTS `nome_futura_contratacao` text DEFAULT NULL COMMENT 'Nome da Futura Contratação',
ADD COLUMN IF NOT EXISTS `catalogo_utilizado` varchar(100) DEFAULT NULL COMMENT 'Catálogo Utilizado',
ADD COLUMN IF NOT EXISTS `classificacao_catalogo` varchar(200) DEFAULT NULL COMMENT 'Classificação do Catálogo',
ADD COLUMN IF NOT EXISTS `codigo_classificacao_superior` varchar(50) DEFAULT NULL COMMENT 'Código da Classificação Superior (Classe/Grupo)',
ADD COLUMN IF NOT EXISTS `nome_classificacao_superior` varchar(300) DEFAULT NULL COMMENT 'Nome da Classificação Superior (Classe/Grupo)';

-- Remover chave única antiga se existir (ignora erro se não existir)
ALTER TABLE `pca_pncp` DROP INDEX IF EXISTS `uk_pca_pncp_item`;

-- Adicionar nova chave única
ALTER TABLE `pca_pncp` ADD UNIQUE KEY `uk_pca_pncp_item` (`orgao_cnpj`, `ano_pca`, `sequencial`);

-- Adicionar índices de performance
ALTER TABLE `pca_pncp` 
ADD INDEX IF NOT EXISTS `idx_pca_pncp_orgao` (`orgao_cnpj`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_ano` (`ano_pca`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_sequencial` (`sequencial`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_codigo_pncp` (`codigo_pncp`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_uasg` (`uasg`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_categoria` (`categoria_item`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_subcategoria` (`subcategoria_item`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_modalidade` (`modalidade_licitacao`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_trimestre` (`trimestre_previsto`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_situacao` (`situacao_item`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_identificador` (`identificador_futura_contratacao`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_catalogo` (`catalogo_utilizado`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_codigo_item` (`codigo_item`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_codigo_pdm` (`codigo_pdm_item`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_valor_estimado` (`valor_estimado`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_valor_total` (`valor_total_estimado`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_data_desejada` (`data_desejada`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_data_atualizacao` (`data_ultima_atualizacao`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_sincronizacao` (`data_sincronizacao`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_hash` (`hash_dados`);

-- Índices compostos adicionais
ALTER TABLE `pca_pncp` 
ADD INDEX IF NOT EXISTS `idx_pca_pncp_categoria_ano` (`categoria_item`, `ano_pca`),
ADD INDEX IF NOT EXISTS `idx_pca_pncp_data_desejada_ano` (`data_desejada`, `ano_pca`);

-- Atualizar comentário da tabela
ALTER TABLE `pca_pncp` COMMENT = 'Dados do PCA obtidos da API do PNCP - UASG 250110';

-- ========================================
-- COMENTÁRIOS E INSTRUÇÕES
-- ========================================

/*
INSTRUÇÕES PARA USO:

1. Execute este script para alterar a estrutura da tabela existente
2. O script preserva todos os dados existentes
3. Adiciona apenas as colunas que não existem
4. Atualiza índices para melhor performance

COLUNAS ADICIONADAS:
- ano_pca: Ano do PCA (padrão: 2026)
- orgao_cnpj: CNPJ do órgão (padrão: 00394544000185)
- sequencial: Sequencial do item no PCA
- unidade_responsavel: Unidade Responsável
- uasg: UASG (filtrada para 250110)
- id_item_pca: ID do item no PCA
- codigo_pdm_item: Código do PDM do Item
- nome_pdm_item: Nome do PDM do Item
- codigo_item: Código do Item
- descricao_item_fornecimento: Descrição do Item e de Fornecimento
- quantidade_estimada: Quantidade Estimada
- valor_unitario_estimado: Valor Unitário Estimado (R$)
- valor_total_estimado: Valor Total Estimado (R$)
- valor_orcamentario_exercicio: Valor Orçamentário estimado para o exercício (R$)
- data_desejada: Data Desejada
- unidade: Unidade
- identificador_futura_contratacao: Identificador da Futura Contratação
- nome_futura_contratacao: Nome da Futura Contratação
- catalogo_utilizado: Catálogo Utilizado
- classificacao_catalogo: Classificação do Catálogo
- codigo_classificacao_superior: Código da Classificação Superior (Classe/Grupo)
- nome_classificacao_superior: Nome da Classificação Superior (Classe/Grupo)

SEGURANÇA:
- Usa ADD COLUMN IF NOT EXISTS para evitar erros
- Usa DROP INDEX IF EXISTS para evitar erros
- Preserva dados existentes
- Adiciona valores padrão onde necessário
*/