<?php

declare(strict_types=1);

namespace App\Traits;

use App\Helpers\AsientoPestana;
use App\Services\modulos\DimensionReporteService;

/**
 * Endpoints de las pestañas «Documentos» y «Resumen» de Proyectos y Centros de costo:
 * documentosAjax, resumenAjax, pdfResumen, excelResumen y documentoOrigenAjax.
 *
 * Muestran importes de los asientos contables, así que además de VER el módulo exigen ver
 * Contabilidad → Asientos Contables (misma regla que la pestaña «Asiento contable» de los
 * documentos, AsientoPestana::puedeVer()).
 *
 * El controlador que lo usa (extiende BaseModuloController) implementa:
 *   tipoDimension(): 'proyecto' | 'centro'
 *   entidadDimension(int $id, int $idEmpresa): ?array  — la fila, o null si no es de la empresa
 */
trait DimensionReporteTrait
{
    abstract protected function tipoDimension(): string;

    abstract protected function entidadDimension(int $id, int $idEmpresa): ?array;

    /** Fila validada (de la empresa activa) + permisos; corta con error si algo falla. */
    private function dimensionPedida(bool $json): array
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_GET['id'] ?? 0);
        $error = null;
        if (!AsientoPestana::puedeVer()) {
            $error = 'No tiene acceso a Contabilidad → Asientos Contables.';
        } elseif ($id <= 0 || !($entidad = $this->entidadDimension($id, $idEmpresa))) {
            $error = $this->tipoDimension() === 'proyecto' ? 'Proyecto no encontrado.' : 'Centro de costo no encontrado.';
        }
        if ($error !== null) {
            if ($json) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => $error]);
            } else {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => $error]);
            }
            exit;
        }
        return [$id, $idEmpresa, $entidad];
    }

    public function documentosAjax(): void
    {
        [$id, $idEmpresa] = $this->dimensionPedida(true);
        header('Content-Type: application/json');
        try {
            $datos = DimensionReporteService::crear()->documentos($this->tipoDimension(), $id, $idEmpresa, $_GET['desde'] ?? null, $_GET['hasta'] ?? null);
            echo json_encode(['ok' => true] + $datos);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function resumenAjax(): void
    {
        [$id, $idEmpresa, $entidad] = $this->dimensionPedida(true);
        header('Content-Type: application/json');
        try {
            $datos = DimensionReporteService::crear()->resumen($this->tipoDimension(), $id, $idEmpresa, $entidad, $_GET['desde'] ?? null, $_GET['hasta'] ?? null);
            echo json_encode(['ok' => true, 'data' => $datos]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** PDF de UN registro: la vista lo pide con CMG_pdfDocumento() (§9). */
    public function pdfResumen(): void
    {
        [$id, $idEmpresa, $entidad] = $this->dimensionPedida(false);
        try {
            DimensionReporteService::crear()->pdf($this->tipoDimension(), $id, $idEmpresa, $entidad, $_GET['desde'] ?? null, $_GET['hasta'] ?? null);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (ob_get_length()) ob_end_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No se pudo generar el PDF: ' . $e->getMessage()]);
        }
        exit;
    }

    public function excelResumen(): void
    {
        [$id, $idEmpresa, $entidad] = $this->dimensionPedida(false);
        try {
            DimensionReporteService::crear()->excel($this->tipoDimension(), $id, $idEmpresa, $entidad, $_GET['desde'] ?? null, $_GET['hasta'] ?? null);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (ob_get_length()) ob_end_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No se pudo generar el Excel: ' . $e->getMessage()]);
        }
        exit;
    }

    /** Visor de documento origen (documento_origen_modal.js) desde la pestaña Documentos. */
    public function documentoOrigenAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            if (!AsientoPestana::puedeVer()) {
                throw new \RuntimeException('No tiene acceso a Contabilidad → Asientos Contables.');
            }
            $datos = (new \App\Services\modulos\DocumentoOrigenService())->getDetalle(
                trim((string) ($_GET['modulo'] ?? '')),
                (int) ($_GET['id'] ?? 0),
                (int) $_SESSION['id_empresa']
            );
            echo json_encode(['success' => true, 'data' => $datos]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
