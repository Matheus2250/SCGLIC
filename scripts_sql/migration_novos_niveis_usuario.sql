-- =====================================================
-- MIGRAÇÃO: NOVA ESTRUTURA DE NÍVEIS DE USUÁRIO
-- Sistema CGLIC - Ministério da Saúde
-- =====================================================

-- Criar backup da tabela de usuários antes da migração
CREATE TABLE IF NOT EXISTS usuarios_backup_niveis AS SELECT * FROM usuarios;

-- Atualizar comentários da coluna nivel_acesso
ALTER TABLE usuarios MODIFY COLUMN nivel_acesso INT NOT NULL DEFAULT 6 COMMENT '1=Coordenador, 2=DIPLAN, 3=DIQUALI, 4=DIPLI, 5=CCONT, 6=Visitante';

-- Migrar dados existentes para a nova estrutura
-- Mapear níveis antigos para novos níveis
UPDATE usuarios SET 
    nivel_acesso = CASE 
        WHEN nivel_acesso = 1 THEN 1  -- Coordenador continua como 1
        WHEN nivel_acesso = 2 THEN 2  -- DIPLAN continua como 2  
        WHEN nivel_acesso = 3 THEN 4  -- DIPLI (antigo 3) vira 4
        WHEN nivel_acesso = 4 THEN 6  -- Visitante (antigo 4) vira 6
        ELSE 6  -- Qualquer outro nível vira Visitante
    END;

-- Inserir usuários de exemplo para os novos níveis (opcional)
INSERT IGNORE INTO usuarios (nome, email, senha, departamento, nivel_acesso) VALUES
('DIQUALI Exemplo', 'diquali@cglic.gov.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DIQUALI', 3),
('CCONT Exemplo', 'ccont@cglic.gov.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CCONT', 5);

-- Criar índice para otimizar consultas por nível
CREATE INDEX IF NOT EXISTS idx_usuarios_nivel ON usuarios(nivel_acesso);

-- Atualizar descrições dos níveis na tabela (se existir coluna de descrição)
-- Caso não exista, este comando será ignorado
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS descricao_nivel VARCHAR(255) NULL;

UPDATE usuarios SET 
    descricao_nivel = CASE 
        WHEN nivel_acesso = 1 THEN 'Coordenador - Acesso total ao sistema'
        WHEN nivel_acesso = 2 THEN 'DIPLAN - Acesso total ao Planejamento (PCA)'
        WHEN nivel_acesso = 3 THEN 'DIQUALI - Acesso total à Qualificação'
        WHEN nivel_acesso = 4 THEN 'DIPLI - Acesso total à Licitação'
        WHEN nivel_acesso = 5 THEN 'CCONT - Acesso total aos Contratos'
        WHEN nivel_acesso = 6 THEN 'Visitante - Apenas visualização e relatórios'
        ELSE 'Nível desconhecido'
    END;

-- Verificar a migração
SELECT 
    nivel_acesso,
    COUNT(*) as total_usuarios,
    descricao_nivel
FROM usuarios 
GROUP BY nivel_acesso, descricao_nivel 
ORDER BY nivel_acesso;

-- Verificar se há usuários com níveis inválidos
SELECT * FROM usuarios WHERE nivel_acesso NOT IN (1,2,3,4,5,6);

-- Log da migração
INSERT INTO logs_sistema (acao, modulo, detalhes, usuario_id, created_at) VALUES
('MIGRATION_NIVEIS', 'SISTEMA', 'Migração concluída: Nova estrutura de 6 níveis de usuário implementada', 1, NOW());

-- =====================================================
-- DOCUMENTAÇÃO DOS NOVOS NÍVEIS:
-- 
-- 1 = Coordenador - Acesso total
-- 2 = DIPLAN - Acesso total ao Planejamento (PCA) + visualização dos demais
-- 3 = DIQUALI - Acesso total à Qualificação + visualização dos demais  
-- 4 = DIPLI - Acesso total à Licitação + visualização dos demais
-- 5 = CCONT - Acesso total aos Contratos + visualização dos demais
-- 6 = Visitante - Apenas visualização, relatórios e detalhes
-- =====================================================