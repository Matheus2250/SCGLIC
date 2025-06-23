-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 23/06/2025 às 02:41
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

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
-- Estrutura para tabela `pca_dados`
--

CREATE TABLE `pca_dados` (
  `id` int(11) NOT NULL,
  `importacao_id` int(11) DEFAULT NULL,
  `ano_pca` int(4) NOT NULL DEFAULT 2025,
  `numero_contratacao` varchar(100) DEFAULT NULL,
  `status_contratacao` varchar(100) DEFAULT NULL,
  `situacao_execucao` varchar(100) DEFAULT NULL,
  `titulo_contratacao` text DEFAULT NULL,
  `categoria_contratacao` varchar(255) DEFAULT NULL,
  `uasg_atual` varchar(50) DEFAULT NULL,
  `valor_total_contratacao` decimal(15,2) DEFAULT NULL,
  `data_inicio_processo` date DEFAULT NULL,
  `data_conclusao_processo` date DEFAULT NULL,
  `prazo_duracao_dias` int(11) DEFAULT NULL,
  `area_requisitante` varchar(255) DEFAULT NULL,
  `numero_dfd` varchar(100) DEFAULT NULL,
  `prioridade` varchar(50) DEFAULT NULL,
  `numero_item_dfd` varchar(100) DEFAULT NULL,
  `data_conclusao_dfd` date DEFAULT NULL,
  `classificacao_contratacao` varchar(255) DEFAULT NULL,
  `codigo_classe_grupo` varchar(50) DEFAULT NULL,
  `nome_classe_grupo` varchar(255) DEFAULT NULL,
  `codigo_pdm_material` varchar(50) DEFAULT NULL,
  `nome_pdm_material` varchar(255) DEFAULT NULL,
  `codigo_material_servico` varchar(50) DEFAULT NULL,
  `descricao_material_servico` text DEFAULT NULL,
  `unidade_fornecimento` varchar(50) DEFAULT NULL,
  `valor_unitario` decimal(15,2) DEFAULT NULL,
  `quantidade` decimal(15,3) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `pca_dados`
--

INSERT INTO `pca_dados` (`id`, `importacao_id`, `ano_pca`, `numero_contratacao`, `status_contratacao`, `situacao_execucao`, `titulo_contratacao`, `categoria_contratacao`, `uasg_atual`, `valor_total_contratacao`, `data_inicio_processo`, `data_conclusao_processo`, `prazo_duracao_dias`, `area_requisitante`, `numero_dfd`, `prioridade`, `numero_item_dfd`, `data_conclusao_dfd`, `classificacao_contratacao`, `codigo_classe_grupo`, `nome_classe_grupo`, `codigo_pdm_material`, `nome_pdm_material`, `codigo_material_servico`, `descricao_material_servico`, `unidade_fornecimento`, `valor_unitario`, `quantidade`, `valor_total`, `criado_em`) VALUES
(5621, NULL, 2025, '', '', '', '', '', '250110', NULL, NULL, NULL, NULL, '', '', 'Alto', '', NULL, '', '', '', '', '', '', '', '', NULL, 35000.000, 4216800.00, '2025-06-23 00:24:47');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `pca_dados`
--
ALTER TABLE `pca_dados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_contratacao_item` (`numero_contratacao`,`numero_item_dfd`,`codigo_material_servico`),
  ADD KEY `idx_pca_dados_ano_pca` (`ano_pca`),
  ADD KEY `idx_pca_dados_ano_importacao` (`ano_pca`,`importacao_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `pca_dados`
--
ALTER TABLE `pca_dados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6965;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
