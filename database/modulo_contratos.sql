-- ============================================================================
-- MÓDULO CONTRATOS - SISTEMA CGLIC
-- Integração com API Comprasnet (UASG 250110)
-- ============================================================================

-- Tabela principal de contratos
CREATE TABLE IF NOT EXISTS contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Dados principais do contrato
    numero_contrato VARCHAR(50) NOT NULL,
    comprasnet_id VARCHAR(50) UNIQUE,
    objeto TEXT NOT NULL,
    orgao_contratante VARCHAR(200),
    uasg VARCHAR(10) DEFAULT '250110',
    
    -- Dados do contratado
    contratado_nome VARCHAR(200),
    contratado_cnpj VARCHAR(20),
    contratado_tipo ENUM('PF', 'PJ') DEFAULT 'PJ',
    
    -- Valores financeiros
    valor_total DECIMAL(15,2),
    valor_empenhado DECIMAL(15,2) DEFAULT 0,
    valor_pago DECIMAL(15,2) DEFAULT 0,
    valor_disponivel DECIMAL(15,2) GENERATED ALWAYS AS (valor_total - COALESCE(valor_empenhado, 0)) STORED,
    
    -- Datas importantes
    data_assinatura DATE,
    data_inicio_vigencia DATE,
    data_fim_vigencia DATE,
    data_publicacao DATE,
    
    -- Classificação e situação
    modalidade VARCHAR(100),
    tipo_contrato VARCHAR(100),
    numero_processo VARCHAR(50),
    numero_sei VARCHAR(50), -- Vinculação manual com SEI
    situacao VARCHAR(50),
    status_contrato ENUM('vigente', 'encerrado', 'suspenso', 'rescindido') DEFAULT 'vigente',
    
    -- Links e referências
    link_comprasnet TEXT,
    url_documento TEXT,
    
    -- Dados de controle
    ultima_sincronizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sincronizado_por INT,
    
    -- Índices
    INDEX idx_numero_contrato (numero_contrato),
    INDEX idx_comprasnet_id (comprasnet_id),
    INDEX idx_contratado_cnpj (contratado_cnpj),
    INDEX idx_data_fim_vigencia (data_fim_vigencia),
    INDEX idx_situacao (situacao),
    INDEX idx_status (status_contrato),
    INDEX idx_uasg (uasg),
    
    FOREIGN KEY (sincronizado_por) REFERENCES usuarios(id)
);

-- Tabela de aditivos contratuais
CREATE TABLE IF NOT EXISTS contratos_aditivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    
    -- Dados do aditivo
    numero_aditivo VARCHAR(50),
    tipo_aditivo ENUM('prazo', 'valor', 'objeto', 'misto') NOT NULL,
    objeto_aditivo TEXT,
    
    -- Valores do aditivo
    valor_aditivo DECIMAL(15,2) DEFAULT 0,
    percentual_aditivo DECIMAL(5,2),
    
    -- Datas do aditivo
    data_assinatura DATE,
    data_inicio_vigencia DATE,
    data_fim_vigencia DATE,
    data_publicacao DATE,
    
    -- Situação
    situacao VARCHAR(50),
    
    -- Controle
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE,
    INDEX idx_contrato_id (contrato_id),
    INDEX idx_tipo_aditivo (tipo_aditivo)
);

-- Tabela de empenhos
CREATE TABLE IF NOT EXISTS contratos_empenhos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    
    -- Dados do empenho
    numero_empenho VARCHAR(50),
    tipo_empenho VARCHAR(50),
    valor_empenho DECIMAL(15,2),
    
    -- Datas
    data_empenho DATE,
    data_vencimento DATE,
    
    -- Situação
    situacao VARCHAR(50),
    
    -- Controle
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE,
    INDEX idx_contrato_id (contrato_id),
    INDEX idx_numero_empenho (numero_empenho)
);

-- Tabela de pagamentos
CREATE TABLE IF NOT EXISTS contratos_pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    empenho_id INT,
    
    -- Dados do pagamento
    numero_documento VARCHAR(50),
    tipo_pagamento VARCHAR(50),
    valor_pagamento DECIMAL(15,2),
    
    -- Datas
    data_pagamento DATE,
    data_vencimento DATE,
    
    -- Situação
    situacao VARCHAR(50),
    
    -- Controle
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE,
    FOREIGN KEY (empenho_id) REFERENCES contratos_empenhos(id) ON DELETE SET NULL,
    INDEX idx_contrato_id (contrato_id),
    INDEX idx_empenho_id (empenho_id),
    INDEX idx_data_pagamento (data_pagamento)
);

-- Tabela de documentos/anexos do contrato
CREATE TABLE IF NOT EXISTS contratos_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    
    -- Dados do documento
    nome_documento VARCHAR(200),
    tipo_documento VARCHAR(100),
    descricao TEXT,
    caminho_arquivo VARCHAR(500),
    tamanho_arquivo INT,
    
    -- Controle
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    criado_por INT,
    
    FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_contrato_id (contrato_id),
    INDEX idx_tipo_documento (tipo_documento)
);

-- Tabela de alertas e notificações
CREATE TABLE IF NOT EXISTS contratos_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    
    -- Tipo e descrição do alerta
    tipo_alerta ENUM('vencimento_proximo', 'sem_pagamento', 'execucao_longa', 'valor_excedido', 'custom') NOT NULL,
    titulo VARCHAR(200),
    descricao TEXT,
    
    -- Configurações do alerta
    dias_antecedencia INT,
    valor_referencia DECIMAL(15,2),
    
    -- Status
    ativo BOOLEAN DEFAULT TRUE,
    disparado BOOLEAN DEFAULT FALSE,
    data_disparo TIMESTAMP NULL,
    
    -- Controle
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    criado_por INT,
    
    FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_contrato_id (contrato_id),
    INDEX idx_tipo_alerta (tipo_alerta),
    INDEX idx_ativo (ativo)
);

-- Tabela de log de sincronização
CREATE TABLE IF NOT EXISTS contratos_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Dados da sincronização
    tipo_sync ENUM('completa', 'incremental', 'individual') NOT NULL,
    status ENUM('iniciado', 'sucesso', 'erro', 'parcial') NOT NULL,
    
    -- Estatísticas
    total_contratos_api INT DEFAULT 0,
    contratos_novos INT DEFAULT 0,
    contratos_atualizados INT DEFAULT 0,
    contratos_erro INT DEFAULT 0,
    
    -- Tempo de execução
    inicio_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fim_sync TIMESTAMP NULL,
    duracao_segundos INT GENERATED ALWAYS AS (TIMESTAMPDIFF(SECOND, inicio_sync, fim_sync)) STORED,
    
    -- Detalhes
    mensagem TEXT,
    detalhes_erro TEXT,
    
    -- Controle
    executado_por INT,
    
    FOREIGN KEY (executado_por) REFERENCES usuarios(id),
    INDEX idx_tipo_sync (tipo_sync),
    INDEX idx_status (status),
    INDEX idx_inicio_sync (inicio_sync)
);

-- Tabela de configurações da API Comprasnet
CREATE TABLE IF NOT EXISTS contratos_api_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Configurações de acesso
    base_url VARCHAR(200) DEFAULT 'https://contratos.comprasnet.gov.br/api',
    uasg VARCHAR(10) DEFAULT '250110',
    
    -- Autenticação OAuth2
    client_id VARCHAR(100),
    client_secret VARCHAR(200),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP NULL,
    
    -- Configurações de sincronização
    sync_ativo BOOLEAN DEFAULT TRUE,
    sync_intervalo_horas INT DEFAULT 24,
    ultimo_sync TIMESTAMP NULL,
    proximo_sync TIMESTAMP NULL,
    
    -- Rate limiting
    requests_por_minuto INT DEFAULT 60,
    requests_por_hora INT DEFAULT 1000,
    
    -- Controle
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    atualizado_por INT,
    
    FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
);

-- Inserir configuração padrão da API
INSERT INTO contratos_api_config (base_url, uasg, sync_ativo, sync_intervalo_horas) 
VALUES ('https://contratos.comprasnet.gov.br/api', '250110', TRUE, 24)
ON DUPLICATE KEY UPDATE base_url = VALUES(base_url);

-- View para contratos com alertas
CREATE OR REPLACE VIEW vw_contratos_alertas AS
SELECT 
    c.*,
    CASE 
        WHEN c.data_fim_vigencia <= CURDATE() THEN 'vencido'
        WHEN c.data_fim_vigencia <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'vence_30_dias'
        WHEN c.data_fim_vigencia <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'vence_90_dias'
        ELSE 'vigente'
    END as alerta_vencimento,
    
    CASE 
        WHEN MAX(p.data_pagamento) IS NULL THEN 'sem_pagamento'
        WHEN MAX(p.data_pagamento) <= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 'sem_pagamento_90_dias'
        ELSE 'pagamento_ok'
    END as alerta_pagamento,
    
    DATEDIFF(CURDATE(), c.data_inicio_vigencia) as dias_execucao,
    
    CASE 
        WHEN DATEDIFF(CURDATE(), c.data_inicio_vigencia) > 365 
             AND NOT EXISTS (SELECT 1 FROM contratos_aditivos ca WHERE ca.contrato_id = c.id) 
        THEN 'execucao_longa_sem_aditivo'
        ELSE 'execucao_ok'
    END as alerta_execucao
    
FROM contratos c
LEFT JOIN contratos_pagamentos p ON c.id = p.contrato_id
GROUP BY c.id;

-- View para dashboard de contratos
CREATE OR REPLACE VIEW vw_contratos_dashboard AS
SELECT 
    COUNT(*) as total_contratos,
    COUNT(CASE WHEN status_contrato = 'vigente' THEN 1 END) as contratos_vigentes,
    COUNT(CASE WHEN status_contrato = 'encerrado' THEN 1 END) as contratos_encerrados,
    SUM(valor_total) as valor_total_contratos,
    SUM(valor_empenhado) as valor_total_empenhado,
    SUM(valor_pago) as valor_total_pago,
    AVG(valor_total) as valor_medio_contrato,
    
    -- Alertas
    COUNT(CASE WHEN data_fim_vigencia <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as vencem_30_dias,
    COUNT(CASE WHEN data_fim_vigencia <= CURDATE() THEN 1 END) as vencidos,
    
    -- Por modalidade
    COUNT(CASE WHEN modalidade LIKE '%Pregão%' THEN 1 END) as pregoes,
    COUNT(CASE WHEN modalidade LIKE '%Concorrência%' THEN 1 END) as concorrencias,
    COUNT(CASE WHEN modalidade LIKE '%Dispensa%' THEN 1 END) as dispensas
    
FROM contratos 
WHERE uasg = '250110';

-- Criação de índices compostos para otimização
CREATE INDEX idx_contratos_vigencia_status ON contratos(data_fim_vigencia, status_contrato);
CREATE INDEX idx_contratos_valor_modalidade ON contratos(valor_total, modalidade);
CREATE INDEX idx_sync_log_data_status ON contratos_sync_log(inicio_sync, status);