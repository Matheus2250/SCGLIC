-- Script para corrigir AUTO_INCREMENT de todas as tabelas críticas

-- ========================================
-- DIAGNÓSTICO GERAL
-- ========================================
SELECT TABLE_NAME, AUTO_INCREMENT 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'sistema_licitacao' 
AND AUTO_INCREMENT IS NOT NULL
ORDER BY TABLE_NAME;

-- ========================================
-- CORREÇÃO: pca_importacoes
-- ========================================
-- Verificar registros inválidos
SELECT 'pca_importacoes - Registros com ID 0:' as info, COUNT(*) as total FROM pca_importacoes WHERE id = 0;

-- Remover registros com ID 0
DELETE FROM pca_importacoes WHERE id = 0;

-- Corrigir AUTO_INCREMENT
ALTER TABLE pca_importacoes AUTO_INCREMENT = 1;

-- ========================================
-- CORREÇÃO: pca_dados
-- ========================================
-- Verificar registros inválidos
SELECT 'pca_dados - Registros com ID 0:' as info, COUNT(*) as total FROM pca_dados WHERE id = 0;

-- Remover registros com ID 0
DELETE FROM pca_dados WHERE id = 0;

-- Corrigir AUTO_INCREMENT
ALTER TABLE pca_dados AUTO_INCREMENT = 1;

-- ========================================
-- CORREÇÃO: licitacoes
-- ========================================
-- Verificar registros inválidos
SELECT 'licitacoes - Registros com ID 0:' as info, COUNT(*) as total FROM licitacoes WHERE id = 0;

-- Remover registros com ID 0
DELETE FROM licitacoes WHERE id = 0;

-- Corrigir AUTO_INCREMENT
ALTER TABLE licitacoes AUTO_INCREMENT = 1;

-- ========================================
-- CORREÇÃO: usuarios
-- ========================================
-- Verificar registros inválidos
SELECT 'usuarios - Registros com ID 0:' as info, COUNT(*) as total FROM usuarios WHERE id = 0;

-- Remover registros com ID 0
DELETE FROM usuarios WHERE id = 0;

-- Corrigir AUTO_INCREMENT
ALTER TABLE usuarios AUTO_INCREMENT = 1;

-- ========================================
-- CORREÇÃO: logs_sistema
-- ========================================
-- Verificar registros inválidos
SELECT 'logs_sistema - Registros com ID 0:' as info, COUNT(*) as total FROM logs_sistema WHERE id = 0;

-- Remover registros com ID 0
DELETE FROM logs_sistema WHERE id = 0;

-- Corrigir AUTO_INCREMENT
ALTER TABLE logs_sistema AUTO_INCREMENT = 1;

-- ========================================
-- VERIFICAÇÃO FINAL
-- ========================================
SELECT 'RESULTADO FINAL:' as info, TABLE_NAME, AUTO_INCREMENT 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'sistema_licitacao' 
AND AUTO_INCREMENT IS NOT NULL
ORDER BY TABLE_NAME;