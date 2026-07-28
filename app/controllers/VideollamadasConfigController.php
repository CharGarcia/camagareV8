<?php

/**
 * Configuración GLOBAL de videollamadas — vive en /config, no en el módulo.
 *
 * Aquí se cargan los servidores STUN/TURN que hereda todo el sistema y los
 * límites de la empresa activa. Es configuración de plataforma (guarda
 * credenciales de servicios contratados), no una función operativa, por eso
 * está entre las tarjetas de /config y no dentro del módulo de reuniones.
 *
 * Rutas (a través de ConfigController::videollamadas):
 *   GET  /config/videollamadas                  Pantalla
 *   POST /config/videollamadas?action=guardar         Configuración de la empresa
 *   POST /config/videollamadas?action=guardarGlobal   Configuración global
 *   GET  /config/videollamadas?action=probar          Prueba de servidores
 *
 * Solo nivel 3.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\VideollamadaRepository;
use App\Rules\modulos\VideollamadaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\VideollamadaService;
use App\Services\modulos\videollamadas\ProveedorInterno;

class VideollamadasConfigController extends Controller
{
    private VideollamadaService $service;
    private VideollamadaRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new VideollamadaRepository();
        $this->service    = new VideollamadaService(
            $this->repository,
            new VideollamadaRules(),
            new LogSistemaService()
        );
    }

    public function index(): void
    {
        $this->requireSuperadmin();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) $_SESSION['id_usuario'];

        $this->viewWithLayout('layouts.main', 'config.videollamadas', [
            'titulo'   => 'Videollamadas',
            'empresa'  => $this->service->getConfigParaVista($idEmpresa, $idUsuario),
            'global'   => $this->service->getConfigGlobalParaVista($idUsuario),
            'efectiva' => $this->service->getConfigEfectiva($idEmpresa, $idUsuario),
            'maxMesh'  => VideollamadaRules::MAX_PARTICIPANTES_MESH,
        ]);
    }

    /** Límites y, si hace falta, servidores propios de la empresa activa. */
    public function guardarAjax(): void
    {
        $this->requireSuperadmin();

        try {
            $this->service->guardarConfig(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                $_POST
            );
            $this->json(['ok' => true, 'mensaje' => 'Configuración de la empresa guardada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Servidores que hereda todo el sistema. */
    public function guardarGlobalAjax(): void
    {
        $this->requireSuperadmin();

        try {
            $this->service->guardarConfigGlobal((int) $_SESSION['id_usuario'], $_POST);
            $this->json(['ok' => true, 'mensaje' => 'Configuración global guardada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Comprueba qué servidores quedan realmente disponibles con la
     * configuración actual. Es lo que confirma si el TURN está bien puesto
     * antes de una reunión de verdad.
     */
    public function probarAjax(): void
    {
        $this->requireSuperadmin();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $config = $this->service->getConfigEfectiva($idEmpresa, $idUsuario);
            $cred   = (new ProveedorInterno())->obtenerCredenciales(['codigo' => ''], $config, []);

            $stun = 0;
            $turn = 0;
            foreach ($cred['ice_servers'] as $srv) {
                $urls = is_array($srv['urls']) ? $srv['urls'] : [$srv['urls']];
                foreach ($urls as $u) {
                    str_starts_with((string) $u, 'turn') ? $turn++ : $stun++;
                }
            }

            $this->json([
                'ok'    => true,
                'stun'  => $stun,
                'turn'  => $turn,
                'aviso' => $turn === 0
                    ? 'No hay TURN disponible. Entre el 10% y el 20% de las llamadas no va a conectar.'
                    : '',
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Guarda credenciales de servicios contratados y afecta a todas las
     * empresas: queda reservada al superadministrador.
     */
    private function requireSuperadmin(): void
    {
        $this->requireAuth();

        if ((int) ($_SESSION['nivel'] ?? 0) < 3) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['ok' => false, 'mensaje' => 'Solo el superadministrador puede acceder a esta configuración.'], 403);
            }
            $this->redirect(rtrim(BASE_URL ?? '', '/') . '/config');
        }
    }
}
