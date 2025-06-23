<?php
require_once __DIR__ . '/../../helpers/auth.php';

// app/controllers/planejamento/ImportacaoController.php
class ImportacaoController extends Controller
{
    private $modelo;

    public function __construct()
    {
        $this->modelo = $this->model('planejamento/PlanejamentoModel');
    }

    public function form()
    {
        requirePlanejamentoOuCoordenador();
        $importacoes = $this->modelo->listarImportacoes();
        $this->view('planejamento/importar', [
            'importacoes' => $importacoes
        ], 'Importar CSV');
    }

    public function upload()
    {
        requirePlanejamentoOuCoordenador();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv']['tmp_name'])) {
            echo "Envio inválido."; return;
        }

        $arquivo = $_FILES['csv']['name'];
        $anoPca = 2025;
        $usuarioId = $_SESSION['usuario']['id'] ?? null;

        // Registra início
        $stmt = $this->modelo->getDb()->prepare("
            INSERT INTO pca_importacoes (nome_arquivo, ano_pca, status, usuario_id) 
            VALUES (:nome_arquivo, :ano_pca, 'processando', :usuario_id)
        ");
        $stmt->execute([
            'nome_arquivo' => $arquivo,
            'ano_pca'      => $anoPca,
            'usuario_id'   => $usuarioId
        ]);
        $importacaoId = $this->modelo->getDb()->lastInsertId();

        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$handle) {
            $this->modelo->atualizarStatusImportacao($importacaoId, 'erro');
            echo "Falha ao abrir o arquivo."; return;
        }

        $header = fgetcsv($handle, 0, ';');
        $linha = 1;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $linha++;
            try {
                $dados = $this->modelo->mapearCsvParaArray($header, $row);
                $dados['importacao_id'] = $importacaoId;
                $this->modelo->upsert($dados);
            } catch (Exception $e) {
                // Poderíamos logar o erro no futuro
                continue;
            }
        }
        fclose($handle);

        // Marca como concluído
        $this->modelo->atualizarStatusImportacao($importacaoId, 'ok');
        $mensagem = "Importação concluída com sucesso. " . ($linha - 1) . " registros processados.";

        $this->view('planejamento/importar', [
            'mensagem'     => $mensagem,
            'importacoes'  => $this->modelo->listarImportacoes()
        ], 'Importar CSV');
    }
}
