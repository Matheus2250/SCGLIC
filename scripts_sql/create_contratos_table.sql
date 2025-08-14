/**
 * Script SQL para criação da tabela de CONTRATOS
 * Sistema CGLIC - Ministério da Saúde
 * 
 * Baseado na planilha: CONTRATOS 2025 14.csv
 * Data: Janeiro 2025
 */

-- Tabela principal de contratos
CREATE TABLE IF NOT EXISTS `contratos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `numero_sequencial` int(11) DEFAULT NULL COMMENT 'Número sequencial do contrato',
    `ano_contrato` year DEFAULT NULL COMMENT 'Ano do contrato',
    `numero_contrato` varchar(50) DEFAULT NULL COMMENT 'Número do contrato (ex: 046/2010)',
    `nome_empresa` varchar(255) NOT NULL COMMENT 'Nome da empresa contratada',
    `cnpj_cpf` varchar(20) DEFAULT NULL COMMENT 'CNPJ ou CPF da empresa',
    `numero_sei` varchar(100) DEFAULT NULL COMMENT 'Número do processo SEI',
    `objeto_servico` text DEFAULT NULL COMMENT 'Objeto/Serviço contratado',
    `modalidade` varchar(100) DEFAULT NULL COMMENT 'Modalidade de licitação',
    `numero_modalidade` varchar(50) DEFAULT NULL COMMENT 'Número da modalidade',
    
    -- Valores por ano
    `valor_2020` decimal(15,2) DEFAULT NULL COMMENT 'Valor executado em 2020',
    `valor_2021` decimal(15,2) DEFAULT NULL COMMENT 'Valor executado em 2021',
    `valor_2022` decimal(15,2) DEFAULT NULL COMMENT 'Valor executado em 2022',
    `valor_2023` decimal(15,2) DEFAULT NULL COMMENT 'Valor executado em 2023',
    `valor_2025` decimal(15,2) DEFAULT NULL COMMENT 'Valor previsto para 2025',
    
    -- Valores do contrato
    `valor_inicial` decimal(15,2) DEFAULT NULL COMMENT 'Valor inicial do contrato',
    `valor_atual` decimal(15,2) DEFAULT NULL COMMENT 'Valor atual do contrato',
    
    -- Datas importantes
    `data_inicio` date DEFAULT NULL COMMENT 'Data de início da vigência',
    `data_fim` date DEFAULT NULL COMMENT 'Data de fim da vigência',
    `data_assinatura` date DEFAULT NULL COMMENT 'Data de assinatura do contrato',
    
    -- Gestão e controle
    `area_gestora` varchar(100) DEFAULT NULL COMMENT 'Área gestora do contrato',
    `finalidade` varchar(100) DEFAULT NULL COMMENT 'Finalidade do contrato',
    `portaria_fiscal_sei` varchar(100) DEFAULT NULL COMMENT 'Número da portaria de designação de fiscal',
    `fiscais` text DEFAULT NULL COMMENT 'Nomes dos fiscais responsáveis',
    `garantia` varchar(100) DEFAULT NULL COMMENT 'Tipo de garantia',
    `alerta_vigencia_sei` varchar(100) DEFAULT NULL COMMENT 'Número SEI do alerta de vigência',
    `situacao_atual` varchar(100) DEFAULT NULL COMMENT 'Situação atual do contrato',
    `mao_obra` varchar(10) DEFAULT NULL COMMENT 'Possui mão de obra (SIM/NÃO)',
    `prorrogacao` text DEFAULT NULL COMMENT 'Informações sobre prorrogações',
    `link_documentos` text DEFAULT NULL COMMENT 'Link para documentos no SharePoint',
    `portaria_mf_mgi_mp` text DEFAULT NULL COMMENT 'Portarias relacionadas',
    `numero_dfd` varchar(50) DEFAULT NULL COMMENT 'Número do DFD vinculado',
    
    -- Controle interno
    `status_contrato` enum('ativo','inativo','suspenso','encerrado') DEFAULT 'ativo' COMMENT 'Status do contrato',
    `observacoes` text DEFAULT NULL COMMENT 'Observações gerais',
    `usuario_responsavel` int(11) DEFAULT NULL COMMENT 'ID do usuário responsável',
    `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
    `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `criado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que criou',
    `atualizado_por` int(11) DEFAULT NULL COMMENT 'ID do usuário que atualizou',
    
    PRIMARY KEY (`id`),
    KEY `idx_numero_contrato` (`numero_contrato`),
    KEY `idx_ano_contrato` (`ano_contrato`),
    KEY `idx_nome_empresa` (`nome_empresa`),
    KEY `idx_cnpj_cpf` (`cnpj_cpf`),
    KEY `idx_numero_sei` (`numero_sei`),
    KEY `idx_modalidade` (`modalidade`),
    KEY `idx_area_gestora` (`area_gestora`),
    KEY `idx_situacao_atual` (`situacao_atual`),
    KEY `idx_status_contrato` (`status_contrato`),
    KEY `idx_data_inicio` (`data_inicio`),
    KEY `idx_data_fim` (`data_fim`),
    KEY `idx_valor_atual` (`valor_atual`),
    KEY `fk_usuario_responsavel` (`usuario_responsavel`),
    KEY `fk_criado_por` (`criado_por`),
    KEY `fk_atualizado_por` (`atualizado_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de contratos do Ministério da Saúde';

-- Tabela para histórico de alterações nos contratos
CREATE TABLE IF NOT EXISTS `contratos_historico` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `contrato_id` int(11) NOT NULL,
    `acao` enum('criado','atualizado','status_alterado','valor_alterado') NOT NULL,
    `campo_alterado` varchar(100) DEFAULT NULL,
    `valor_anterior` text DEFAULT NULL,
    `valor_novo` text DEFAULT NULL,
    `motivo` text DEFAULT NULL,
    `usuario_id` int(11) DEFAULT NULL,
    `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp(),
    `ip_origem` varchar(45) DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    KEY `idx_contrato_id` (`contrato_id`),
    KEY `idx_usuario_id` (`usuario_id`),
    KEY `idx_data_alteracao` (`data_alteracao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de alterações nos contratos';

-- Tabela para anexos dos contratos
CREATE TABLE IF NOT EXISTS `contratos_anexos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `contrato_id` int(11) NOT NULL,
    `nome_arquivo` varchar(255) NOT NULL,
    `nome_original` varchar(255) NOT NULL,
    `tipo_anexo` enum('contrato','aditivo','fiscal','garantia','outros') DEFAULT 'outros',
    `tamanho_arquivo` int(11) DEFAULT NULL,
    `tipo_mime` varchar(100) DEFAULT NULL,
    `caminho_arquivo` varchar(500) NOT NULL,
    `descricao` text DEFAULT NULL,
    `usuario_upload` int(11) DEFAULT NULL,
    `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
    
    PRIMARY KEY (`id`),
    KEY `idx_contrato_id` (`contrato_id`),
    KEY `idx_tipo_anexo` (`tipo_anexo`),
    KEY `idx_usuario_upload` (`usuario_upload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anexos dos contratos';

-- Inserir dados de exemplo (opcional)
INSERT IGNORE INTO `contratos` (
    `numero_sequencial`, `ano_contrato`, `numero_contrato`, `nome_empresa`, `cnpj_cpf`, 
    `numero_sei`, `objeto_servico`, `modalidade`, `valor_inicial`, `valor_atual`,
    `data_inicio`, `data_fim`, `area_gestora`, `status_contrato`
) VALUES 
(1, 2025, '001/2025', 'Empresa Exemplo LTDA', '12.345.678/0001-90', 
 '25000.123456/2025-01', 'Prestação de serviços de exemplo', 'Pregão Eletrônico', 
 100000.00, 100000.00, '2025-01-01', '2025-12-31', 'CGLIC', 'ativo');

-- Criar views úteis
CREATE OR REPLACE VIEW `vw_contratos_ativos` AS
SELECT 
    c.*,
    u1.nome as nome_usuario_responsavel,
    u2.nome as nome_criado_por,
    DATEDIFF(c.data_fim, CURDATE()) as dias_para_vencimento,
    CASE 
        WHEN c.data_fim < CURDATE() THEN 'vencido'
        WHEN DATEDIFF(c.data_fim, CURDATE()) <= 30 THEN 'vence_30_dias'
        WHEN DATEDIFF(c.data_fim, CURDATE()) <= 90 THEN 'vence_90_dias'
        ELSE 'vigente'
    END as status_vigencia
FROM contratos c
LEFT JOIN usuarios u1 ON c.usuario_responsavel = u1.id
LEFT JOIN usuarios u2 ON c.criado_por = u2.id
WHERE c.status_contrato = 'ativo';

-- Criar view para resumo financeiro
CREATE OR REPLACE VIEW `vw_contratos_resumo_financeiro` AS
SELECT 
    area_gestora,
    COUNT(*) as total_contratos,
    SUM(valor_inicial) as valor_total_inicial,
    SUM(valor_atual) as valor_total_atual,
    AVG(valor_atual) as valor_medio,
    SUM(CASE WHEN status_contrato = 'ativo' THEN valor_atual ELSE 0 END) as valor_contratos_ativos
FROM contratos
GROUP BY area_gestora;

COMMIT;