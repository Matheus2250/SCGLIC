-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 23/06/2025 às 18:36
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sistema_licitacao`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `licitacoes`
--

CREATE TABLE `licitacoes` (
  `id` int(11) NOT NULL,
  `nup` varchar(20) DEFAULT NULL COMMENT 'Número Único de Protocolo',
  `data_entrada_dipli` date DEFAULT NULL COMMENT 'Data de entrada na DIPLI',
  `resp_instrucao` varchar(255) DEFAULT NULL COMMENT 'Responsável pela instrução',
  `area_demandante` varchar(255) DEFAULT NULL COMMENT 'Área que demandou a licitação',
  `pregoeiro` varchar(255) DEFAULT NULL COMMENT 'Nome do pregoeiro',
  `pca_dados_id` int(11) DEFAULT NULL COMMENT 'Vinculação com PCA atual',
  `numero_processo` varchar(100) DEFAULT NULL COMMENT 'Número do processo (mantido para compatibilidade)',
  `tipo_licitacao` varchar(50) DEFAULT NULL COMMENT 'Tipo de licitação (mantido para compatibilidade)',
  `modalidade` varchar(50) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL COMMENT 'Tipo da licitação (TRADICIONAL, COTACAO, SRP)',
  `numero_contratacao` varchar(100) DEFAULT NULL COMMENT 'Número da contratação do PCA',
  `numero` int(11) DEFAULT NULL COMMENT 'Número sequencial da licitação',
  `ano` int(11) DEFAULT NULL COMMENT 'Ano da licitação',
  `objeto` text DEFAULT NULL,
  `valor_estimado` decimal(15,2) DEFAULT NULL,
  `qtd_itens` int(11) DEFAULT NULL COMMENT 'Quantidade de itens da licitação',
  `data_abertura` date DEFAULT NULL,
  `data_publicacao` date DEFAULT NULL COMMENT 'Data de publicação do edital',
  `data_homologacao` date DEFAULT NULL,
  `valor_homologado` decimal(15,2) DEFAULT NULL COMMENT 'Valor homologado',
  `qtd_homol` int(11) DEFAULT NULL COMMENT 'Quantidade homologada',
  `economia` decimal(15,2) DEFAULT NULL COMMENT 'Economia obtida (estimado - homologado)',
  `link` text DEFAULT NULL COMMENT 'Link para documentos/edital',
  `usuario_id` int(11) DEFAULT NULL COMMENT 'ID do usuário que criou',
  `situacao` enum('EM_ANDAMENTO','HOMOLOGADO','FRACASSADO','REVOGADO','CANCELADO','PREPARACAO') DEFAULT 'EM_ANDAMENTO',
  `observacoes` text DEFAULT NULL COMMENT 'Observações gerais',
  `usuario_criador` int(11) DEFAULT NULL COMMENT 'Usuário criador (mantido para compatibilidade)',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Processos licitatórios vinculados aos PCAs atuais';

--
-- Despejando dados para a tabela `licitacoes`
--

INSERT INTO `licitacoes` (`id`, `nup`, `data_entrada_dipli`, `resp_instrucao`, `area_demandante`, `pregoeiro`, `pca_dados_id`, `numero_processo`, `tipo_licitacao`, `modalidade`, `tipo`, `numero_contratacao`, `numero`, `ano`, `objeto`, `valor_estimado`, `qtd_itens`, `data_abertura`, `data_publicacao`, `data_homologacao`, `valor_homologado`, `qtd_homol`, `economia`, `link`, `usuario_id`, `situacao`, `observacoes`, `usuario_criador`, `criado_em`, `atualizado_em`) VALUES
(10, '25000.069779/2024-94', '2025-02-06', 'Laryssa', 'SEIDIGI.DATASUS.CGIE', 'Suzana', 286, NULL, NULL, 'PREGAO', 'TRADICIONAL', NULL, NULL, 2025, 'Infraestrutura Hiperconvergente', 19925641.00, NULL, '2025-03-10', NULL, '2025-03-31', 19453000.00, NULL, 472641.00, NULL, 4, 'HOMOLOGADO', NULL, NULL, '2025-06-12 22:50:26', '2025-06-13 01:41:45'),
(11, '25000.096822/2024-94', '2025-10-03', 'Laryssa', 'SESAI.CASAI.BSB', 'Hermínio', 575, NULL, NULL, 'PREGAO', 'TRADICIONAL', NULL, NULL, 2025, 'SERVIÇOS CONTINUOS CONDUÇÃO VEÍCULOS CASAI', 1371333.68, NULL, '2025-03-27', NULL, NULL, NULL, NULL, NULL, 'https://saudegov.sharepoint.com/sites/CGMAP-DIPLI/Documentos/Forms/AllItems.aspx?id=%2Fsites%2FCGMAP%2DDIPLI%2FDocumentos%2FLicita%C3%A7%C3%B5es%2F2025%2FPREG%C3%83O%2FTRADICIONAL%2FContrata%C3%A7%C3%A3o%2090300%202025&viewid=8d7bebc8%2D5f0b%2D45aa%2Da831%2D67690ca3045c&csf=1&web=1&e=meA8i2&CID=d95b7f7e%2Dece5%2D41bb%2Daf88%2Dedb0fe6c72ff&FolderCTID=0x012000D908575A73001946A6E39DEC371FD73B', 4, 'EM_ANDAMENTO', NULL, NULL, '2025-06-12 22:54:57', '2025-06-16 18:55:42'),
(12, '25000.022241/2025-05', '2025-03-14', 'Fabiana', 'SAA.COGEP.CODEP', 'Graciene', 74, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', NULL, NULL, 2025, 'Curso Entendo Contabilidade Aplicada ao Setor Público', 8400.00, NULL, '2025-03-24', NULL, NULL, 8400.00, NULL, 0.00, NULL, 4, 'HOMOLOGADO', NULL, NULL, '2025-06-12 23:03:52', '2025-06-13 01:39:41'),
(13, '25000.027674/2025-49', '2025-03-25', 'Fabiana', 'SAA.COGEP.CODEP', 'Graciene', 75, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', NULL, NULL, 2025, 'Logística e Compras Públicas', 145200.00, NULL, '2025-04-04', NULL, NULL, NULL, NULL, NULL, NULL, 4, 'EM_ANDAMENTO', NULL, NULL, '2025-06-12 23:10:12', '2025-06-13 01:38:32'),
(14, '25000.032046/2025-85', '2025-03-27', 'Débora', 'SAA.COGEP.CODEP', 'Graciene', 18, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', NULL, NULL, 2025, 'Contratação de empresa para treinamento e aperfeiçoamento de pessoal', 237000.00, NULL, '2025-04-16', NULL, NULL, 237000.00, NULL, 0.00, NULL, 4, 'HOMOLOGADO', NULL, NULL, '2025-06-12 23:14:40', '2025-06-13 20:42:32'),
(21, '25000.144910/2024-18', '2025-02-20', 'Débora', 'CODEP', 'Suzana', 114, NULL, NULL, 'DISPENSA', 'TRADICIONAL', '186/2025', NULL, 2025, 'Banco de Preços', 30498.05, NULL, '2025-04-23', NULL, NULL, 30498.05, NULL, 0.00, NULL, 4, 'HOMOLOGADO', NULL, NULL, '2025-06-13 01:25:25', '2025-06-16 20:14:29'),
(22, '25000.006025/2025-12', '2025-03-19', 'Valéria', 'SAA.CGCON.DIFSEP', 'Raphael', 556, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', '391/2025', NULL, 2025, 'EBC', 1041475.00, NULL, '2025-03-04', NULL, NULL, 1041475.00, NULL, 0.00, NULL, 4, 'HOMOLOGADO', NULL, NULL, '2025-06-13 01:28:58', '2025-06-13 01:36:33'),
(23, '25000.176849/2024-60', '2025-05-05', 'Laryssa', 'COGEP', 'Suzana', 554, NULL, NULL, 'DISPENSA', 'TRADICIONAL', '378/2025', NULL, 2025, 'CONTRATO TEMPORARIO DA UNIAO (CTU)', 846209.16, NULL, '2025-09-05', NULL, NULL, NULL, NULL, NULL, NULL, 4, 'EM_ANDAMENTO', NULL, NULL, '2025-06-13 01:33:12', '2025-06-16 20:20:42'),
(24, '25000.096615/2024-30', '2025-05-05', 'Débora', 'SAA.CGCON.DIFSEP', 'Katielle', 473, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', '198/2025', NULL, 2025, 'SERPRO', 143080.50, NULL, '2025-05-05', NULL, '2025-05-05', 143080.50, NULL, 0.00, NULL, 4, 'HOMOLOGADO', NULL, NULL, '2025-06-13 01:46:22', '2025-06-13 01:47:35'),
(25, '25000.132017/2024-31', '2025-02-05', 'Valéria', 'SAA.COGEP.CODEP', 'Suzana', 39, NULL, NULL, 'PREGAO', 'TRADICIONAL', '312/2025', NULL, 2025, 'Apoio Administrativo Superintendência RJ', 1878784.20, NULL, '2025-05-23', NULL, NULL, NULL, NULL, NULL, NULL, 4, 'EM_ANDAMENTO', NULL, NULL, '2025-06-13 01:55:22', '2025-06-13 01:55:34'),
(26, '25000.163653/2024-13', '2025-02-05', 'Laryssa', 'SAA.CGINFRA', 'Raphael', 559, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', '390/2025', NULL, 2025, 'CAESB', 2620305.96, NULL, '2025-05-14', NULL, NULL, 2620305.96, NULL, 0.00, 'https://saudegov.sharepoint.com/:f:/r/sites/CGMAP-DIPLI/Documentos/Licita%C3%A7%C3%B5es/2025/INEXIGIBILIDADE/10-2025%20CAESB?csf=1&web=1&e=WvUTgP', 4, 'HOMOLOGADO', NULL, NULL, '2025-06-13 18:14:30', '2025-06-13 18:14:41'),
(27, '25000.028457/2025-76', '2025-05-05', 'Laryssa', 'SAA.COGEP.CODEP', 'Raphael', 294, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', '239/2025', NULL, 2025, 'SERVIÇO DE EDUCAÇÃO E TREINAMENTO PARA SERVIDORES DO DENASUS', 90000.00, NULL, '2025-05-19', NULL, NULL, 90000.00, NULL, 0.00, NULL, 4, 'HOMOLOGADO', NULL, NULL, '2025-06-13 18:17:58', '2025-06-14 01:26:42'),
(29, '25000.161893/2023-94', '2024-10-03', 'Valéria', 'DataSUS', 'Katielle', 291, NULL, NULL, 'DISPENSA', 'TRADICIONAL', '197/2025', NULL, 2025, 'SERVIÇOS DE PROCESSAMENTOS DE DADOS', 120097640.52, NULL, '2025-01-31', NULL, '2025-01-30', 120097640.52, NULL, 0.00, NULL, 4, 'HOMOLOGADO', NULL, NULL, '2025-06-16 19:25:51', '2025-06-16 19:26:03'),
(30, '25000.038284/2024-13', '2025-10-03', 'Vinicius', 'DATASUS', 'Hermínio', 285, NULL, NULL, 'PREGAO', 'SRP', '163/2025', NULL, 2025, 'SUITE DE ESCRITÓRIO', 11087435.88, NULL, '2025-02-19', NULL, '2025-03-28', 10996651.11, NULL, 90784.77, NULL, 4, 'HOMOLOGADO', NULL, NULL, '2025-06-16 19:32:43', '2025-06-16 19:34:43'),
(31, '25000.099326/2023-10', '2025-02-06', 'Fabiana', 'CGFISC', 'Graciane', 77, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', '344/2025', NULL, 2025, 'POSTO DE ATENDIMENTO CAIXA', NULL, NULL, '2025-02-21', NULL, '2025-02-18', NULL, NULL, NULL, 'https://saudegov.sharepoint.com/:f:/s/CGMAP-DIPLI/Ei1OX8kHeR9BsjGMbElhe5UBn3NQTzzApU1B5F2n2caqEg?e=OmAHFI', 4, 'HOMOLOGADO', NULL, NULL, '2025-06-16 19:40:26', '2025-06-16 19:40:55'),
(32, '25000.152316/2023-10', '2025-10-09', 'Fabiana', 'SEIDIGI.DATASUS.CGIE', 'Katielle', 483, NULL, NULL, 'PREGAO', 'TRADICIONAL', '164/2025', NULL, 2025, 'SOLUÇÃO FIREWALL', 9118309.29, NULL, '2025-03-13', NULL, '2025-05-07', 9069800.00, NULL, 48509.29, 'https://saudegov.sharepoint.com/:f:/r/sites/CGMAP-DIPLI/Documentos/Licita%C3%A7%C3%B5es/2025/PREG%C3%83O/TRADICIONAL/Preg%C3%A3o%2090146-2025?csf=1&web=1&e=meA8i2', 4, 'HOMOLOGADO', NULL, NULL, '2025-06-16 19:45:09', '2025-06-16 19:45:20'),
(33, '25000.103599/2023-68', '2024-11-27', 'Laryssa', 'SVS.DAENT', 'Katielle', 502, NULL, NULL, 'PREGAO', 'TRADICIONAL', '146/2025', NULL, 2025, 'DECLARAÇÃO NASCIDOS VIVOS', 13927725.00, NULL, '2025-03-21', NULL, NULL, NULL, NULL, NULL, NULL, 4, 'REVOGADO', NULL, NULL, '2025-06-16 19:54:12', '2025-06-16 19:54:20'),
(34, '25000.024428/2024-54', '2025-07-18', 'Valéria', 'CGLAB', 'Suzana', NULL, NULL, NULL, 'PREGAO', 'SRP', '373/2025', NULL, 2025, 'CÂMARAS REFRIGERADAS, FREEZERS,  ULTRAFREEZERS e BLAST FREEZERS', NULL, NULL, '2025-03-26', NULL, NULL, NULL, NULL, NULL, 'https://saudegov.sharepoint.com/:b:/r/sites/CGMAP-DIPLI/Documentos/Licita%C3%A7%C3%B5es/2025/PREG%C3%83O/SRP/Preg%C3%A3o%2090373-2025%20(frezeres)/DOU.pdf?csf=1&web=1&e=TS0wUY', 4, 'EM_ANDAMENTO', NULL, NULL, '2025-06-16 19:57:59', '2025-06-16 20:33:03'),
(35, '25000.060785/2024-86', '2024-03-12', 'Laryssa', 'CGENG', 'Graciene', 598, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', '403/2025', NULL, 2025, 'SERVIÇO DE FORNECIMENTO', 7772973.00, NULL, '2025-04-02', NULL, NULL, 7772973.00, NULL, 0.00, 'https://saudegov.sharepoint.com/sites/CGMAP-DIPLI/Documentos/Forms/AllItems.aspx?id=%2Fsites%2FCGMAP%2DDIPLI%2FDocumentos%2FLicita%C3%A7%C3%B5es%2F2025%2FINEXIGIBILIDADE%2F03%2D2025%20SERVI%C3%87O%20DE%20FORNECIMENTO&p=true&ga=1', 4, 'HOMOLOGADO', NULL, NULL, '2025-06-16 20:10:36', '2025-06-16 20:10:54'),
(36, '25000.025311/2025-79', '2025-03-20', 'Rafael', 'CODEP', 'Graciene', 438, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', '359/2025', NULL, 2025, 'CURSO DOMINE TRIBUTOS', 32400.00, NULL, '2025-06-03', NULL, NULL, NULL, NULL, NULL, 'https://saudegov.sharepoint.com/:f:/r/sites/CGMAP-DIPLI/Documentos/Licita%C3%A7%C3%B5es/2025/INEXIGIBILIDADE/10-2025%20CURSO%20DOMINE%20TRIBUTOS?csf=1&web=1&e=HyPKdH', 4, 'HOMOLOGADO', NULL, NULL, '2025-06-16 20:29:03', '2025-06-16 20:29:24'),
(37, '25000.193613/2023-15', '2025-11-22', 'Débora', 'CODEP', 'Graciene', NULL, NULL, NULL, 'INEXIBILIDADE', 'TRADICIONAL', '396/2025', NULL, 2025, 'INGRESSO DO MINISTÉRIO GIGANCANGANGA', 97381.80, NULL, '2025-06-03', NULL, NULL, NULL, NULL, NULL, 'https://saudegov.sharepoint.com/:f:/r/sites/CGMAP-DIPLI/Documentos/Licita%C3%A7%C3%B5es/2025/INEXIGIBILIDADE/11-2025%20GIGACANDANGA?csf=1&web=1&e=09ZhbP', 4, 'HOMOLOGADO', NULL, NULL, '2025-06-16 21:32:46', '2025-06-16 19:19:12');

--
-- Acionadores `licitacoes`
--
DELIMITER $$
CREATE TRIGGER `tr_licitacoes_calcular_economia` BEFORE UPDATE ON `licitacoes` FOR EACH ROW BEGIN
    -- Calcular economia se ambos os valores estiverem preenchidos
    IF NEW.valor_estimado IS NOT NULL AND NEW.valor_homologado IS NOT NULL THEN
        SET NEW.economia = NEW.valor_estimado - NEW.valor_homologado;
    END IF;
    
    -- Atualizar data de modificação
    SET NEW.atualizado_em = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_licitacoes_log_mudancas` AFTER UPDATE ON `licitacoes` FOR EACH ROW BEGIN
    -- Log mudança de situação
    IF OLD.situacao != NEW.situacao THEN
        INSERT INTO logs_sistema (usuario_id, acao, modulo, detalhes, modulo_origem, registro_afetado_id) 
        VALUES (NEW.usuario_id, 'MUDANCA_SITUACAO', 'licitacoes', 
                CONCAT('Situação alterada de "', OLD.situacao, '" para "', NEW.situacao, '"'), 
                'TRIGGER', NEW.id);
    END IF;
END
$$
DELIMITER ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `licitacoes`
--
ALTER TABLE `licitacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_processo` (`numero_processo`),
  ADD KEY `fk_licitacoes_pca_dados` (`pca_dados_id`),
  ADD KEY `fk_licitacoes_usuario` (`usuario_criador`),
  ADD KEY `idx_situacao` (`situacao`),
  ADD KEY `idx_data_abertura` (`data_abertura`),
  ADD KEY `idx_modalidade` (`modalidade`),
  ADD KEY `idx_nup` (`nup`),
  ADD KEY `idx_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_ano` (`ano`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_pregoeiro` (`pregoeiro`),
  ADD KEY `fk_licitacoes_usuario_id` (`usuario_id`),
  ADD KEY `idx_licitacoes_situacao` (`situacao`),
  ADD KEY `idx_licitacoes_modalidade` (`modalidade`),
  ADD KEY `idx_licitacoes_data_abertura` (`data_abertura`),
  ADD KEY `idx_licitacoes_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_licitacoes_valor_estimado` (`valor_estimado`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
