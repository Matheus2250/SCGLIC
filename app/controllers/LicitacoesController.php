<?php
require_once __DIR__ . '/../helpers/auth.php';

class LicitacoesController extends Controller
{
    private $licitacaoModel;

    public function __construct()
    {
        $this->licitacaoModel = $this->model('LicitacaoModel');
    }

    /** Lista todas as licitações */
    public function index()
    {
        requireLogin();
        $licitacoes = $this->licitacaoModel->buscarTodos();
        $resumoPorSituacao = $this->licitacaoModel->resumoPorSituacao();
        $economiaTotal = $this->licitacaoModel->economiaTotal();
        
        $this->view('licitacoes/index', [
            'licitacoes' => $licitacoes,
            'resumoPorSituacao' => $resumoPorSituacao,
            'economiaTotal' => $economiaTotal
        ], 'Licitações');
    }

    /** Exibe formulário para nova licitação */
    public function create()
    {
        requireLogin();
        $numerosContratacao = $this->licitacaoModel->listarNumerosContratacaoDisponiveis();
        
        $this->view('licitacoes/create', [
            'numerosContratacao' => $numerosContratacao
        ], 'Nova Licitação');
    }

    /** Processa criação de nova licitação */
    public function store()
    {
        requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'licitacoes/index');
            return;
        }

        $dados = [
            ':nup' => $_POST['nup'] ?? '',
            ':data_entrada_dipli' => $_POST['data_entrada_dipli'] ?? null,
            ':resp_instrucao' => $_POST['resp_instrucao'] ?? '',
            ':area_demandante' => $_POST['area_demandante'] ?? '',
            ':pregoeiro' => $_POST['pregoeiro'] ?? '',
            ':pca_dados_id' => $_POST['pca_dados_id'] ?? null,
            ':numero_processo' => $_POST['numero_processo'] ?? '',
            ':tipo_licitacao' => $_POST['tipo_licitacao'] ?? '',
            ':modalidade' => $_POST['modalidade'] ?? '',
            ':tipo' => $_POST['tipo'] ?? '',
            ':numero_contratacao' => $_POST['numero_contratacao'] ?? '',
            ':numero' => $_POST['numero'] ?? null,
            ':ano' => $_POST['ano'] ? (int)$_POST['ano'] : date('Y'),
            ':objeto' => $_POST['objeto'] ?? '',
            ':valor_estimado' => $_POST['valor_estimado'] ? (float)str_replace(['.', ','], ['', '.'], $_POST['valor_estimado']) : null,
            ':qtd_itens' => $_POST['qtd_itens'] ? (int)$_POST['qtd_itens'] : null,
            ':data_abertura' => $_POST['data_abertura'] ?? null,
            ':data_publicacao' => $_POST['data_publicacao'] ?? null,
            ':data_homologacao' => $_POST['data_homologacao'] ?? null,
            ':valor_homologado' => $_POST['valor_homologado'] ? (float)str_replace(['.', ','], ['', '.'], $_POST['valor_homologado']) : null,
            ':qtd_homol' => $_POST['qtd_homol'] ? (int)$_POST['qtd_homol'] : null,
            ':link' => $_POST['link'] ?? '',
            ':usuario_id' => $_SESSION['usuario']['id'],
            ':situacao' => $_POST['situacao'] ?? 'PREPARACAO',
            ':observacoes' => $_POST['observacoes'] ?? ''
        ];

        // Converte datas do formato brasileiro para MySQL
        $dados = $this->licitacaoModel->prepararDadosParaInsercao($dados);

        if ($this->licitacaoModel->inserir($dados)) {
            header('Location: ' . BASE_URL . 'licitacoes/index');
        } else {
            echo "Erro ao criar licitação.";
        }
    }

    /** Exibe formulário para editar licitação */
    public function edit($id)
    {
        requireLogin();
        $licitacao = $this->licitacaoModel->buscarPorId($id);
        
        if (!$licitacao) {
            echo "Licitação não encontrada.";
            return;
        }

        $numerosContratacao = $this->licitacaoModel->listarNumerosContratacaoDisponiveis();
        
        $this->view('licitacoes/edit', [
            'licitacao' => $licitacao,
            'numerosContratacao' => $numerosContratacao
        ], 'Editar Licitação');
    }

    /** Processa atualização de licitação */
    public function update($id)
    {
        requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'licitacoes/index');
            return;
        }

        $dados = [
            ':id' => $id,
            ':nup' => $_POST['nup'] ?? '',
            ':data_entrada_dipli' => $_POST['data_entrada_dipli'] ?? null,
            ':resp_instrucao' => $_POST['resp_instrucao'] ?? '',
            ':area_demandante' => $_POST['area_demandante'] ?? '',
            ':pregoeiro' => $_POST['pregoeiro'] ?? '',
            ':pca_dados_id' => $_POST['pca_dados_id'] ?? null,
            ':numero_processo' => $_POST['numero_processo'] ?? '',
            ':tipo_licitacao' => $_POST['tipo_licitacao'] ?? '',
            ':modalidade' => $_POST['modalidade'] ?? '',
            ':tipo' => $_POST['tipo'] ?? '',
            ':numero_contratacao' => $_POST['numero_contratacao'] ?? '',
            ':numero' => $_POST['numero'] ?? null,
            ':ano' => $_POST['ano'] ? (int)$_POST['ano'] : date('Y'),
            ':objeto' => $_POST['objeto'] ?? '',
            ':valor_estimado' => $_POST['valor_estimado'] ? (float)str_replace(['.', ','], ['', '.'], $_POST['valor_estimado']) : null,
            ':qtd_itens' => $_POST['qtd_itens'] ? (int)$_POST['qtd_itens'] : null,
            ':data_abertura' => $_POST['data_abertura'] ?? null,
            ':data_publicacao' => $_POST['data_publicacao'] ?? null,
            ':data_homologacao' => $_POST['data_homologacao'] ?? null,
            ':valor_homologado' => $_POST['valor_homologado'] ? (float)str_replace(['.', ','], ['', '.'], $_POST['valor_homologado']) : null,
            ':qtd_homol' => $_POST['qtd_homol'] ? (int)$_POST['qtd_homol'] : null,
            ':link' => $_POST['link'] ?? '',
            ':situacao' => $_POST['situacao'] ?? 'PREPARACAO',
            ':observacoes' => $_POST['observacoes'] ?? ''
        ];

        // Converte datas do formato brasileiro para MySQL
        $dados = $this->licitacaoModel->prepararDadosParaInsercao($dados);

        if ($this->licitacaoModel->atualizar($dados)) {
            header('Location: ' . BASE_URL . 'licitacoes/index');
        } else {
            echo "Erro ao atualizar licitação.";
        }
    }

    /** Exclui uma licitação */
    public function delete($id)
    {
        requireLogin();
        
        if ($this->licitacaoModel->excluir($id)) {
            header('Location: ' . BASE_URL . 'licitacoes/index');
        } else {
            echo "Erro ao excluir licitação.";
        }
    }

    /** API para buscar dados do PCA pelo número da contratação (AJAX) */
    public function buscarDadosPca()
    {
        requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['erro' => 'Método não permitido']);
            return;
        }

        $numeroContratacao = $_POST['numero_contratacao'] ?? '';
        
        if (empty($numeroContratacao)) {
            http_response_code(400);
            echo json_encode(['erro' => 'Número da contratação é obrigatório']);
            return;
        }

        $dadosPca = $this->licitacaoModel->buscarDadosPcaPorNumeroContratacao($numeroContratacao);
        
        if ($dadosPca) {
            // Verifica se já existe licitação para esta contratação
            $jaExiste = $this->licitacaoModel->verificarExisteLicitacaoParaContratacao($numeroContratacao);
            
            header('Content-Type: application/json');
            echo json_encode([
                'sucesso' => true,
                'dados' => $dadosPca,
                'ja_existe_licitacao' => $jaExiste
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['erro' => 'Número da contratação não encontrado no PCA']);
        }
    }

    /** Exibe relatórios e estatísticas */
    public function relatorios()
    {
        requireLogin();
        
        $resumoPorSituacao = $this->licitacaoModel->resumoPorSituacao();
        $resumoPorModalidade = $this->licitacaoModel->resumoPorModalidade();
        $licitacoesProximas = $this->licitacaoModel->licitacoesProximasVencimento();
        $economiaTotal = $this->licitacaoModel->economiaTotal();
        
        $this->view('licitacoes/relatorios', [
            'resumoPorSituacao' => $resumoPorSituacao,
            'resumoPorModalidade' => $resumoPorModalidade,
            'licitacoesProximas' => $licitacoesProximas,
            'economiaTotal' => $economiaTotal
        ], 'Relatórios - Licitações');
    }
}