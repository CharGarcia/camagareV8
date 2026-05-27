<?php
/**
 * Módulos operativos por empresa (rutas /modulos/{acción}).
 */
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Traits\PermisoModuloTrait;

class ModulosController extends Controller
{
    use PermisoModuloTrait;

    // Las acciones de los módulos ahora están estructuradas 
    // en app/controllers/modulos/
}
