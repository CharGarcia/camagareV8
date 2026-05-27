<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CitaConfiguracionRepository;
use App\Rules\modulos\CitaConfiguracionRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CitaConfiguracionService;
use App\Helpers\PreferenciasHelper;

class CitasConfiguracionController extends BaseModuloController
{
    private CitaConfiguracionService $service;
    private const RUTA_MODULO = 'modulos/citas-configuracion';

    public function __construct()
    {
        parent::__construct();
        $this->service = new CitaConfiguracionService(
            new CitaConfiguracionRepository(),
            new CitaConfiguracionRules(),
            new LogSistemaService()
        );
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $perm        = $this->getPermisos();
        $tabActiva   = trim($_GET['tab'] ?? 'tipos');

        // vistaConfig general (columnas del módulo principal)
        $prefsVista         = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        // vistaConfigs separadas por pestaña (para ordenamiento independiente)
        $prefsVistaTipos    = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO . '-tipos');
        $prefsVistaRecursos = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO . '-recursos');

        // Leer ordenamiento guardado para tipos
        $sortTipos = trim($prefsVistaTipos['__ordenCol__'] ?? 'nombre');
        $dirTipos  = strtoupper(trim($prefsVistaTipos['__ordenDir__'] ?? 'ASC'));
        $resultTipos = $this->service->getTipos($idEmpresa, '', 1, 500, $sortTipos, $dirTipos);

        // Leer ordenamiento guardado para recursos
        $sortRec = trim($prefsVistaRecursos['__ordenCol__'] ?? 'nombre');
        $dirRec  = strtoupper(trim($prefsVistaRecursos['__ordenDir__'] ?? 'ASC'));
        $resultRecursos = $this->service->getRecursos($idEmpresa, '', 1, 500, $sortRec, $dirRec);

        $horarios        = $this->service->getHorarios($idEmpresa);
        $recursosActivos = $this->service->getRecursosActivos($idEmpresa);
        $portalConfig    = $this->service->getPortalConfig($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/citas_configuracion/index', [
            'titulo'             => 'Configuración de Citas',
            'perm'               => $perm,
            'rutaModulo'         => self::RUTA_MODULO,
            'vistaConfig'        => $prefsVista,
            'vistaConfigTipos'   => $prefsVistaTipos,
            'vistaConfigRecursos'=> $prefsVistaRecursos,
            'tabActiva'          => $tabActiva,
            'tiposRows'          => $resultTipos['rows'],
            'tiposTotal'         => $resultTipos['total'],
            'tiposSortCol'       => $sortTipos,
            'tiposSortDir'       => $dirTipos,
            'recursosRows'       => $resultRecursos['rows'],
            'recursosTotal'      => $resultRecursos['total'],
            'recSortCol'         => $sortRec,
            'recSortDir'         => $dirRec,
            'horarios'           => $horarios,
            'recursosActivos'    => $recursosActivos,
            'portalConfig'       => $portalConfig,
            'fullWidth'          => true,
        ]);
    }

    // ─── TIPOS DE CITA ────────────────────────────────────────────────────────

    public function guardarTipo(): void
    {
        header('Content-Type: application/json');
        $id = (int) ($_POST['id'] ?? 0);
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();

        $data               = $this->recogerDatosTipo();
        $data['id']         = $id;
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $newId = $this->service->guardarTipo($data);
            $msg   = $id > 0 ? 'Tipo de cita actualizado correctamente.' : 'Tipo de cita creado correctamente.';
            echo json_encode(['ok' => true, 'mensaje' => $msg, 'id' => $newId]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarTipo(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminarTipo($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Tipo de cita eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── RECURSOS ─────────────────────────────────────────────────────────────

    public function guardarRecurso(): void
    {
        header('Content-Type: application/json');
        $id = (int) ($_POST['id'] ?? 0);
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();

        $data               = $this->recogerDatosRecurso();
        $data['id']         = $id;
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $newId = $this->service->guardarRecurso($data);
            $msg   = $id > 0 ? 'Recurso actualizado correctamente.' : 'Recurso creado correctamente.';
            echo json_encode(['ok' => true, 'mensaje' => $msg, 'id' => $newId]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarRecurso(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminarRecurso($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Recurso eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── HORARIOS ─────────────────────────────────────────────────────────────

    public function guardarHorario(): void
    {
        header('Content-Type: application/json');
        $id = (int) ($_POST['id'] ?? 0);
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();

        $data               = $this->recogerDatosHorario();
        $data['id']         = $id;
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $newId = $this->service->guardarHorario($data);
            $msg   = $id > 0 ? 'Horario actualizado correctamente.' : 'Horario creado correctamente.';
            echo json_encode(['ok' => true, 'mensaje' => $msg, 'id' => $newId]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarHorario(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminarHorario($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Horario eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── PORTAL ───────────────────────────────────────────────────────────────

    public function guardarPortal(): void
    {
        // La config del portal es un upsert: acepta crear O actualizar
        $perm = $this->getPermisos();
        if ($perm['actualizar']) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }
        header('Content-Type: application/json');

        $data = [
            'slug'                   => strtolower(trim($_POST['slug'] ?? '')),
            'titulo'                 => trim($_POST['titulo'] ?? ''),
            'mensaje_bienvenida'     => trim($_POST['mensaje_bienvenida'] ?? ''),
            'color_primario'         => trim($_POST['color_primario'] ?? '#0d6efd'),
            'activo'                 => (($_POST['activo'] ?? '0') === '1'),
            'requiere_confirmacion'  => (($_POST['requiere_confirmacion'] ?? '0') === '1'),
            'max_dias_anticipacion'  => max(1, (int) ($_POST['max_dias_anticipacion'] ?? 30)),
            'min_horas_anticipacion' => max(0, (int) ($_POST['min_horas_anticipacion'] ?? 2)),
            'permite_pagos_online'   => (($_POST['permite_pagos_online'] ?? '0') === '1'),
            'id_empresa'             => (int) $_SESSION['id_empresa'],
            'id_usuario'             => (int) $_SESSION['id_usuario'],
        ];

        try {
            $this->service->guardarPortal($data);
            echo json_encode(['ok' => true, 'mensaje' => 'Configuración del portal guardada correctamente.', 'slug' => $data['slug']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX CATÁLOGOS ───────────────────────────────────────────────────────

    public function recursosActivosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $recursos  = $this->service->getRecursosActivos($idEmpresa);
        echo json_encode(['ok' => true, 'data' => $recursos]);
        exit;
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    private function recogerDatosTipo(): array
    {
        $tipoPago   = trim($_POST['tipo_pago'] ?? 'sin_pago');
        // id_recursos viene como id_recursos[] (array de enteros) o vacío
        $idRecursos = array_filter(array_map('intval', (array) ($_POST['id_recursos'] ?? [])));

        return [
            'nombre'              => trim($_POST['nombre'] ?? ''),
            'descripcion'         => trim($_POST['descripcion'] ?? '') ?: null,
            'duracion_minutos'    => max(5, (int) ($_POST['duracion_minutos'] ?? 30)),
            'precio'              => max(0.0, (float) ($_POST['precio'] ?? 0)),
            'requiere_pago'       => $tipoPago !== 'sin_pago',
            'tipo_pago'           => $tipoPago,
            'anticipo_porcentaje' => ($tipoPago === 'anticipo' && isset($_POST['anticipo_porcentaje']) && $_POST['anticipo_porcentaje'] !== '')
                                        ? (float) $_POST['anticipo_porcentaje'] : null,
            'color'               => trim($_POST['color'] ?? '#0d6efd'),
            'status'              => (int) ($_POST['status'] ?? 1),
            'id_recursos'         => array_values($idRecursos),
        ];
    }

    private function recogerDatosRecurso(): array
    {
        return [
            'nombre'      => trim($_POST['nombre'] ?? ''),
            'tipo'        => trim($_POST['tipo'] ?? 'persona'),
            'descripcion' => trim($_POST['descripcion'] ?? '') ?: null,
            'status'      => (int) ($_POST['status'] ?? 1),
        ];
    }

    private function recogerDatosHorario(): array
    {
        $idRec = (int) ($_POST['id_recurso'] ?? 0);
        return [
            'id_recurso'  => $idRec > 0 ? $idRec : null,
            'dia_semana'  => (int) ($_POST['dia_semana'] ?? 1),
            'hora_inicio' => trim($_POST['hora_inicio'] ?? ''),
            'hora_fin'    => trim($_POST['hora_fin'] ?? ''),
        ];
    }
}
