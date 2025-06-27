-- Tabela para armazenar andamentos de processos em formato JSON
-- Esta tabela será usada para importar dados de andamentos externos

CREATE TABLE IF NOT EXISTS `processo_andamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nup` varchar(50) NOT NULL COMMENT 'Número Único de Protocolo',
  `processo_id` varchar(100) NOT NULL COMMENT 'ID do processo no sistema externo',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora da última atualização',
  `total_andamentos` int(11) NOT NULL DEFAULT 0 COMMENT 'Total de andamentos registrados',
  `andamentos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Dados JSON dos andamentos' CHECK (json_valid(`andamentos_json`)),
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_processo` (`nup`, `processo_id`),
  KEY `idx_nup` (`nup`),
  KEY `idx_processo_id` (`processo_id`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Armazena andamentos de processos em formato JSON';

-- Índice para busca por conteúdo JSON (se suportado pela versão do MySQL/MariaDB)
-- ALTER TABLE processo_andamentos ADD INDEX idx_andamentos_json ((CAST(andamentos_json AS JSON)));