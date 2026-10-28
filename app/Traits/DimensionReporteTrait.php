<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\modulos\DimensionReporteService;
use App\Services\modulos\PestanasModuloService;

/**
 * Endpoints de las pestañas «Documentos» y «Avance / Resumen» de Proyectos y Centros de
 * costo: documentosAjax, resumenAjax, pdfResumen, excelResumen y documentoOrigenAjax.
 *
 * Permiso: VER el módulo y, para un usuario de nivel 1, que el administrador no le haya
 * ocultado esa pestaña en /config/permisos-modulos (catálogo App\Helpers\PestanasModulo,
 * §6). No dependen del permiso de otros módulos. La misma regla dibuja la pestaña y
 * protege sus datos y exportaciones, para que una URL armada a mano no sirva lo oculto.
 *
 * El controlador que lo usa (extiende BaseModuloController) implementa:
 *   tipoDimension(): 'proyecto' | 'centro'
 *   entidadDimension(int $id, int $idEmpresa): ?array  — la fila, o null si no es de la empresa
 */
trait DimensionReporteTrait
{
    abstract protected function tipoDimension(): string;

    abstract protected function entidadDimension(int $id, int $idEmpresa): ?array;

    private ?array $dimPestanasCache = null;

    /** Clave de la segunda pestaña en el catálogo: 'avance' (proyectos) o 'resumen' (centros). */
    private function claveResumen(): string
    {
        return $this->tipoDimension() === 'proyecto' ? 'avance' : 'resumen';
    }

    /**
     * Qué pestañas ve el usuario, una vez por petición: ['documentos' => bool, 'resumen' => bool].
     * La vista las recibe del index() para dibujar la barra.
     */
    protected function dimPestanas(): array
    {
        if ($this->dimPestanasCache === null) {
            $v = PestanasModuloService::crear()->visibles(
                (int) ($_SESSION['id_empresa'] ?? 0),
                (int) ($_SESSION['id_usuario'] ?? 0),
                (int) ($_SESSION['nivel'] ?? 1),
                $this->getRutaModulo()
            );
            $this->dimPestanasCache = [
                'documentos' => !empty($v['documentos']),
                'resumen'    => !empty($v[$this->claveResumen()]),
            ];
        }
        return $this->dimPestanasCache;
    }

    /** Fila validada (de la empresa activa) + permisos; corta con error si algo falla. */
    private function dimensionPedida(bool $json, string $pestana): array
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_GET['id'] ?? 0);
        $error = null;
        if (empty($this->dimPestanas()[$pestana])) {
            $error = 'No tiene permiso para esta pestaña.';
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
        [$id, $idEmpresa] = $this->dimensionPedida(true, 'documentos');
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
        [$id, $idEmpresa, $entidad] = $this->dimensionPedida(true, 'resumen');
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
        [$id, $idEmpresa, $entidad] = $this->dimensionPedida(false, 'resumen');
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
        [$id, $idEmpresa, $entidad] = $this->dimensionPedida(false, 'resumen');
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
            if (empty($this->dimPestanas()['documentos'])) {
                throw new \RuntimeException('No tiene permiso para esta pestaña.');
            }
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $modulo = trim((string) ($_GET['modulo'] ?? ''));
            $idDoc = (int) ($_GET['id'] ?? 0);
            // Solo documentos de ESTE proyecto / centro: sin esto, el visor abriría cualquier
            // documento de la empresa con solo cambiar el id en la URL.
            if (!DimensionReporteService::crear()->documentoPertenece($this->tipoDimension(), (int) ($_GET['dim'] ?? 0), $modulo, $idDoc, $idEmpresa)) {
                throw new \RuntimeException('El documento no pertenece a este ' . ($this->tipoDimension() === 'proyecto' ? 'proyecto.' : 'centro de costo.'));
            }
            $datos = (new \App\Services\modulos\DocumentoOrigenService())->getDetalle($modulo, $idDoc, $idEmpresa);
            echo json_encode(['success' => true, 'data' => $datos]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
