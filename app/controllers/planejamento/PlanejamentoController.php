<?php
class PlanejamentoController extends Controller
{
    public function index()
    {
        $this->view('planejamento/index', [], 'Planejamento');
    }

    public function importar()
    {
        $this->view('planejamento/importar', [], 'Importar Planejamento');
    }
}
