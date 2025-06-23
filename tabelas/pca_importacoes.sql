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
-- Estrutura para tabela `pca_importacoes`
--

CREATE TABLE `pca_importacoes` (
  `id` int(11) NOT NULL,
  `nome_arquivo` varchar(255) NOT NULL,
  `ano_pca` int(11) NOT NULL,
  `total_linhas` int(11) DEFAULT 0,
  `linhas_processadas` int(11) DEFAULT 0,
  `linhas_erro` int(11) DEFAULT 0,
  `total_registros` int(11) DEFAULT 0,
  `registros_novos` int(11) DEFAULT 0,
  `registros_atualizados` int(11) DEFAULT 0,
  `status` enum('processando','concluido','erro') DEFAULT 'processando',
  `mensagem_erro` text DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tempo_processamento` int(11) DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `finalizado_em` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `pca_importacoes`
--
ALTER TABLE `pca_importacoes`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `pca_importacoes`
--
ALTER TABLE `pca_importacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
