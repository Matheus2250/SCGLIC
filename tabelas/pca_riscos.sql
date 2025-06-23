-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 23/06/2025 às 01:38
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
-- Estrutura para tabela `pca_riscos`
--

CREATE TABLE `pca_riscos` (
  `id` int(11) NOT NULL,
  `numero_dfd` varchar(50) NOT NULL,
  `mes_relatorio` varchar(7) NOT NULL,
  `nivel_risco` enum('baixo','medio','alto','extremo') NOT NULL,
  `categoria_risco` varchar(100) NOT NULL,
  `descricao_risco` text NOT NULL,
  `impacto` text DEFAULT NULL,
  `probabilidade` varchar(50) DEFAULT NULL,
  `acao_mitigacao` text DEFAULT NULL,
  `responsavel` varchar(100) DEFAULT NULL,
  `prazo_mitigacao` date DEFAULT NULL,
  `status_acao` enum('pendente','em_andamento','concluida','cancelada') DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `criado_por` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `pca_riscos`
--
ALTER TABLE `pca_riscos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_numero_dfd` (`numero_dfd`),
  ADD KEY `idx_mes_relatorio` (`mes_relatorio`),
  ADD KEY `idx_nivel_risco` (`nivel_risco`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `pca_riscos`
--
ALTER TABLE `pca_riscos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
