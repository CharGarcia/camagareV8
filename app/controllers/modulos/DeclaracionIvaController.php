<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\DeclaracionIvaRepository;
use App\services\modulos\DeclaracionIvaService;

class DeclaracionIvaController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/declaracion-iva';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new DeclaracionIvaRepository();
        $this->service    = new DeclaracionIvaService($this->repository);
        $this->verificarMigracionCasilleros();
    }

    private function verificarMigracionCasilleros(): void
    {
        $db = \App\core\Database::getConnection();
        try {
            $st = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'id'");
            if ($st->rowCount() === 0) {
                $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN id SERIAL PRIMARY KEY");
                $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN editado_manualmente BOOLEAN DEFAULT FALSE");
                $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN concepto TEXT DEFAULT NULL");
            } else {
                $st2 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'editado_manualmente'");
                if ($st2->rowCount() === 0) {
                    $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN editado_manualmente BOOLEAN DEFAULT FALSE");
                }
                $st3 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'concepto'");
                if ($st3->rowCount() === 0) {
                    $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN concepto TEXT DEFAULT NULL");
                }
            }

            // Validar que la tabla sri_casilleros_etiquetas tenga la columna eliminado
            $st4 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = 'eliminado'");
            if ($st4->rowCount() === 0) {
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN eliminado BOOLEAN DEFAULT FALSE");
            }
        } catch (\Throwable $e) {
            // Ignorar errores de migración si ocurren (por locks u otra causa)
            error_log("Error migracion casilleros: " . $e->getMessage());
        }
    }

    public function index(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $anio = $_GET['anio'] ?? date('Y');
        $mes  = $_GET['mes']  ?? date('m');

        $estructura = $this->repository->getEstructuraFormulario();
        $anios      = $this->repository->getAniosConVentas($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/declaracion_iva/index', [
            'titulo' => 'Declaración de IVA (form 104 SRI)',
            'perm' => $this->getPermisos(),
            'anio' => (int) $anio,
            'mes' => $mes,
            'anios' => $anios,
            'estructura' => $estructura,
            'base' => BASE_URL,
            'rutaModulo' => $this->getRutaModulo()
        ]);
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $anio = $_GET['anio'] ?? date('Y');
        $periodo = $_GET['periodo'] ?? date('m');
        $tipo = $_GET['tipo_periodo'] ?? 'mensual';

        try {
            if ($tipo === 'semestral') {
                if ($periodo == '1') {
                    $fechaDesde = "{$anio}-01-01";
                    $fechaHasta = "{$anio}-06-30";
                } else {
                    $fechaDesde = "{$anio}-07-01";
                    $fechaHasta = "{$anio}-12-31";
                }
            } else {
                $fechaDesde = "{$anio}-{$periodo}-01";
                $fechaHasta = date("Y-m-t", strtotime($fechaDesde));
            }

            $sincronizar = (int)($_GET['sincronizar'] ?? 0) === 1;
            if ($sincronizar) {
                if ($tipo === 'semestral') {
                    for ($m = ($periodo == '1' ? 1 : 7); $m <= ($periodo == '1' ? 6 : 12); $m++) {
                        $mesStr = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                        $this->service->sincronizarPeriodo($idEmpresa, (string)$anio, $mesStr, $idUsuario);
                    }
                } else {
                    $this->service->sincronizarPeriodo($idEmpresa, (string)$anio, (string)$periodo, $idUsuario);
                }
            }
            
            $resumenCompleto = $this->service->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta);
            $detalleDocumentos = $this->repository->getDetalleDocumentos($idEmpresa, $fechaDesde, $fechaHasta);
            
            echo json_encode([
                'ok' => true, 
                'resumen_completo' => $resumenCompleto,
                'detalle_documentos' => $detalleDocumentos
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function actualizarCasilleroAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $nuevoCasillero = trim((string)($_POST['casillero'] ?? ''));

        if ($id <= 0 || empty($nuevoCasillero)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos']);
            exit;
        }

        try {
            $this->repository->actualizarCasilleroManual($id, $nuevoCasillero);
            echo json_encode(['ok' => true, 'mensaje' => 'Casillero actualizado exitosamente']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
