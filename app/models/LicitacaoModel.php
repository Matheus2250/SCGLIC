<?php
class LicitacaoModel extends Model
{
    /* ========== CRUD BÁSICO ========== */

    /** Insere uma nova licitação */
    public function inserir(array $dados): bool
    {
        $sql = "INSERT INTO licitacoes 
                   (nup, data_entrada_dipli, resp_instrucao, area_demandante, pregoeiro, 
                    pca_dados_id, numero_processo, tipo_licitacao, modalidade, tipo, 
                    numero_contratacao, numero, ano, objeto, valor_estimado, qtd_itens, 
                    data_abertura, data_publicacao, data_homologacao, valor_homologado, 
                    qtd_homol, link, usuario_id, situacao, observacoes)
                VALUES 
                   (:nup, :data_entrada_dipli, :resp_instrucao, :area_demandante, :pregoeiro, 
                    :pca_dados_id, :numero_processo, :tipo_licitacao, :modalidade, :tipo, 
                    :numero_contratacao, :numero, :ano, :objeto, :valor_estimado, :qtd_itens, 
                    :data_abertura, :data_publicacao, :data_homologacao, :valor_homologado, 
                    :qtd_homol, :link, :usuario_id, :situacao, :observacoes)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($dados);
    }

    /** Retorna todas as licitações */
    public function buscarTodos(): array
    {
        $sql = "SELECT l.*, u.nome as nome_usuario 
                FROM licitacoes l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                ORDER BY l.criado_em DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Busca licitação pelo ID */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT l.*, u.nome as nome_usuario 
                FROM licitacoes l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                WHERE l.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Atualiza dados de uma licitação existente */
    public function atualizar(array $dados): bool
    {
        $sql = "UPDATE licitacoes SET
                   nup = :nup,
                   data_entrada_dipli = :data_entrada_dipli,
                   resp_instrucao = :resp_instrucao,
                   area_demandante = :area_demandante,
                   pregoeiro = :pregoeiro,
                   pca_dados_id = :pca_dados_id,
                   numero_processo = :numero_processo,
                   tipo_licitacao = :tipo_licitacao,
                   modalidade = :modalidade,
                   tipo = :tipo,
                   numero_contratacao = :numero_contratacao,
                   numero = :numero,
                   ano = :ano,
                   objeto = :objeto,
                   valor_estimado = :valor_estimado,
                   qtd_itens = :qtd_itens,
                   data_abertura = :data_abertura,
                   data_publicacao = :data_publicacao,
                   data_homologacao = :data_homologacao,
                   valor_homologado = :valor_homologado,
                   qtd_homol = :qtd_homol,
                   link = :link,
                   situacao = :situacao,
                   observacoes = :observacoes
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($dados);
    }

    /** Exclui uma licitação */
    public function excluir(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM licitacoes WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /* ========== MÉTODOS ESPECÍFICOS PARA INTEGRAÇÃO COM PCA ========== */

    /** Busca dados do PCA pelo número da contratação */
    public function buscarDadosPcaPorNumeroContratacao(string $numeroContratacao): ?array
    {
        $sql = "SELECT DISTINCT 
                    numero_contratacao,
                    area_requisitante,
                    valor_total_contratacao as valor_estimado,
                    titulo_contratacao as objeto,
                    status_contratacao,
                    MIN(id) as pca_dados_id
                FROM pca_dados 
                WHERE numero_contratacao = :numero_contratacao
                GROUP BY numero_contratacao, area_requisitante, valor_total_contratacao, titulo_contratacao, status_contratacao";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':numero_contratacao' => $numeroContratacao]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Lista todos os números de contratação disponíveis no PCA */
    public function listarNumerosContratacaoDisponiveis(): array
    {
        $sql = "SELECT DISTINCT 
                    numero_contratacao,
                    titulo_contratacao,
                    area_requisitante,
                    valor_total_contratacao
                FROM pca_dados 
                WHERE numero_contratacao IS NOT NULL 
                AND numero_contratacao != ''
                ORDER BY numero_contratacao";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Verifica se já existe licitação para o número de contratação */
    public function verificarExisteLicitacaoParaContratacao(string $numeroContratacao): bool
    {
        $sql = "SELECT COUNT(*) FROM licitacoes WHERE numero_contratacao = :numero_contratacao";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':numero_contratacao' => $numeroContratacao]);
        return $stmt->fetchColumn() > 0;
    }

    /* ========== MÉTODOS DE RELATÓRIOS E ESTATÍSTICAS ========== */

    /** Resumo de licitações por situação */
    public function resumoPorSituacao(): array
    {
        $sql = "SELECT situacao, COUNT(*) as total, SUM(valor_estimado) as valor_total
                FROM licitacoes 
                GROUP BY situacao 
                ORDER BY total DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Resumo de licitações por modalidade */
    public function resumoPorModalidade(): array
    {
        $sql = "SELECT modalidade, COUNT(*) as total, SUM(valor_estimado) as valor_total
                FROM licitacoes 
                GROUP BY modalidade 
                ORDER BY total DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Licitações próximas ao vencimento (data_abertura) */
    public function licitacoesProximasVencimento(int $dias = 30): array
    {
        $sql = "SELECT * FROM licitacoes 
                WHERE data_abertura BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
                AND situacao IN ('EM_ANDAMENTO', 'PREPARACAO')
                ORDER BY data_abertura ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':dias' => $dias]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Economia total obtida nas licitações homologadas */
    public function economiaTotal(): float
    {
        $sql = "SELECT SUM(economia) as economia_total 
                FROM licitacoes 
                WHERE situacao = 'HOMOLOGADO' 
                AND economia IS NOT NULL";
        
        $stmt = $this->db->query($sql);
        return (float) $stmt->fetchColumn();
    }

    /* ========== MÉTODOS AUXILIARES ========== */

    /** Converte data do formato brasileiro para MySQL */
    private function converterDataParaMySQL(?string $data): ?string
    {
        if (!$data) return null;
        $partes = explode('/', $data);
        if (count($partes) === 3) {
            return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
        }
        return null;
    }

    /** Converte data do MySQL para formato brasileiro */
    private function converterDataParaBR(?string $data): ?string
    {
        if (!$data) return null;
        $partes = explode('-', $data);
        if (count($partes) === 3) {
            return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
        }
        return null;
    }

    /** Prepara dados para inserção, convertendo datas */
    public function prepararDadosParaInsercao(array $dados): array
    {
        $camposData = ['data_entrada_dipli', 'data_abertura', 'data_publicacao', 'data_homologacao'];
        
        foreach ($camposData as $campo) {
            if (isset($dados[$campo])) {
                $dados[$campo] = $this->converterDataParaMySQL($dados[$campo]);
            }
        }
        
        return $dados;
    }
}