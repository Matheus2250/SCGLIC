-- Script para ajustar a estrutura da tabela qualificacoes
-- Remove campo numero_dfd se existir e garante que numero_contratacao existe
-- Autor: Sistema CGLIC
-- Data: Janeiro 2025

-- Verificar e adicionar campo numero_contratacao se não existir
ALTER TABLE qualificacoes 
ADD COLUMN IF NOT EXISTS numero_contratacao VARCHAR(255) DEFAULT NULL 
COMMENT 'Número da contratação vinculada do PCA';

-- Remover campo numero_dfd se existir (não é mais necessário)
ALTER TABLE qualificacoes 
DROP COLUMN IF EXISTS numero_dfd;

-- Adicionar índice para melhorar performance de busca
ALTER TABLE qualificacoes 
ADD INDEX IF NOT EXISTS idx_numero_contratacao (numero_contratacao);

-- Adicionar índice para NUP se não existir
ALTER TABLE qualificacoes 
ADD INDEX IF NOT EXISTS idx_nup (nup);

-- Garantir que a tabela pca_dados existe e tem o campo numero_contratacao
CREATE TABLE IF NOT EXISTS pca_dados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_contratacao VARCHAR(255),
    titulo_contratacao TEXT,
    area_requisitante VARCHAR(255),
    valor_estimado DECIMAL(15,2),
    ano INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pca_numero_contratacao (numero_contratacao),
    INDEX idx_pca_ano (ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mensagem de conclusão
SELECT 'Script executado com sucesso! Tabela qualificacoes ajustada para usar apenas numero_contratacao.' AS mensagem;