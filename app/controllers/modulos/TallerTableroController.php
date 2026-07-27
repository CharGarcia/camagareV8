<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\TallerDepartamentoRepository;
use App\repositories\modulos\TallerOrdenRepository;
use App\Rules\modulos\TallerOrdenRules;
use App\Services\LogSistemaService;
use App\Services\modulos\TallerOrdenService;

/**
 * Tablero del taller — vista del jefe de taller.
 *
 * Una columna por departamento y una tarjeta por vehículo: de un vistazo se ve
 * dónde está cada carro, cuánto lleva dentro y si el cliente ya aprobó el
 * presupuesto. Es solo lectura: desde aquí no se modifica nada, se abre la
 * orden en el módulo de Órdenes de Trabajo.
 *
 * Va como módulo aparte de modulos/taller para poder darle acceso al jefe de
 * taller o a gerencia sin abrirles la edición de las órdenes.
 */
class TallerTableroController extends BaseModuloController
{
    private TallerOrdenService $service;
    private const RUTA_MODULO = 'modulos/taller-tablero';

    public function __construct()
    {
        parent::__construct();
        $logService = new LogSistemaService();
        $this->service = new TallerOrdenService(
            new TallerOrdenRepository(),
            new TallerDepartamentoRepository(),
            new TallerOrdenRules(),
            $logService
        );
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $this->viewWithLayout('layouts.main', 'modulos.taller_tablero.index', [
            'titulo'      => 'Tablero del taller',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'rutaOrdenes' => 'modulos/taller',
            'tablero'     => $this->service->getTablero($idEmpresa, $idUsuarioFiltro),
        ]);
    }

    /** Refresco automático del tablero. */
    public function tableroAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        echo json_encode(['ok' => true, 'data' => $this->service->getTablero((int) $_SESSION['id_empresa'], $idUsuarioFiltro)]);
        exit;
    }

    /**
     * Abrir una orden desde una tarjeta del tablero.
     *
     * El id se deja en sesión y la navegación va a la URL limpia
     * (`modulos/taller`, sin parámetros). Mismo patrón que comandas/ver: la
     * barra de direcciones queda legible y nadie edita el id a mano.
     */
    public function entrarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Orden no válida.']);
            exit;
        }

        $_SESSION['taller_abrir_orden'] = $id;
        echo json_encode(['ok' => true]);
        exit;
    }

    /** Indicadores: tiempo por departamento y productividad por técnico. */
    public function indicadoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $desde = trim($_GET['desde'] ?? date('Y-m-01'));
        $hasta = trim($_GET['hasta'] ?? date('Y-m-d'));

        echo json_encode(['ok' => true, 'data' => $this->service->getIndicadores((int) $_SESSION['id_empresa'], $desde, $hasta)]);
        exit;
    }
}
