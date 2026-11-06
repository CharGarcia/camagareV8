<?php
/**
 * Controlador MetricasSistemaConsulta - Métricas globales de la plataforma (solo lectura).
 * Tarjeta en la sección Config, EXCLUSIVA de nivel 3 (superadministrador).
 * Cruza todas las empresas: no filtra por empresa (consulta global).
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\MetricasSistemaService;

class MetricasSistemaConsultaController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $this->viewWithLayout('layouts.main', 'config.metricas_sistema', [
            'titulo'    => 'Métricas del sistema',
            'fullWidth' => true,
            'desde'     => date('Y-m-01'),
            'hasta'  => date('Y-m-d'),
        ]);
    }

    /** GET ?action=datos&seccion=usuarios&desde=Y-m-d&hasta=Y-m-d[&refrescar=1] */
    public function datosAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $seccion   = trim((string) ($_GET['seccion'] ?? 'resumen'));
        $desde     = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
        $hasta     = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
        $refrescar = ($_GET['refrescar'] ?? '') === '1';

        try {
            $r = (new MetricasSistemaService())->seccion($seccion, $desde, $hasta, $refrescar);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['accion' => 'metricas_sistema:' . $seccion]);
            $this->json(['ok' => false, 'error' => 'No se pudieron calcular las métricas. Intente con un rango menor.']);
        }

        $this->json(['ok' => true] + $r);
    }

    private function requireNivel(int $min): void
    {
        if ((int) ($_SESSION['nivel'] ?? 0) >= $min) {
            return;
        }
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || isset($_GET['seccion'])) {
            $this->json(['ok' => false, 'error' => 'No tiene permisos.'], 403);
        }
        $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a las métricas del sistema.'];
        header('Location: ' . BASE_URL . '/config');
        exit;
    }
}
