<?php
/**
 * Controlador Cliente
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;

class ClienteController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        if (empty($_SESSION['id_empresa'])) {
            $this->redirect(BASE_URL . '/empresa/menu');
        }

        $this->viewWithLayout('layouts.main', 'cliente.index', [
            'titulo' => 'Clientes',
        ]);
    }
}
