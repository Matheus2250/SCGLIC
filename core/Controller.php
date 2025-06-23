<?php
class Controller {
    protected function model($model) {
        require_once __DIR__ . '/../app/models/' . $model . '.php';
        $parts = explode('/', $model);
        $className = end($parts);
        return new $className();
    }
    public function view($view, $data = [], $title = '', $layout = 'template')
{
    extract($data);
    $viewPath = __DIR__ . '/../app/views/' . $view . '.php';
    require_once __DIR__ . '/../app/views/_layouts/' . $layout . '.php';
}

}
