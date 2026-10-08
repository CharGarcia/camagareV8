<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\DeclaracionRentaRepository;
use App\Services\modulos\DeclaracionRentaService;

/**
 * Declaración de Impuesto a la Renta (formulario 101 sociedades / 102 personas naturales).
 * Reporte anual: no escribe en la base de datos.
 */
class DeclaracionRentaController extends BaseModuloController
{
    private DeclaracionRentaService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/declaracion-renta';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new DeclaracionRentaService(new DeclaracionRentaRepository());
    }

    /** Año pedido por URL o, si no viene, el más reciente con movimientos. */
    private function anio(array $anios): int
    {
        $anio = (int) ($_GET['anio'] ?? 0);
        if ($anio <= 0) {
            $anio = $anios[0] ?? (int) date('Y');
        }
        return $anio;
    }

    /** Ajustes manuales que viajan en la URL (GET) o en el POST. */
    private function ajustes(): array
    {
        $src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $claves = ['cargas_familiares', 'caso_especial', 'otros_ingresos', 'otras_deducciones', 'gastos_no_deducibles',
                   'rentas_exentas', 'deducciones_adicionales', 'amortizacion_perdidas', 'anticipo_pagado',
                   'credito_anios_anteriores', 'otros_creditos', 'participacion_trabajadores_pct', 'tarifa_pct', 'fuente'];
        $out = [];
        foreach ($claves as $k) {
            if (isset($src[$k]) && $src[$k] !== '') {
                $out[$k] = is_string($src[$k]) ? trim($src[$k]) : $src[$k];
            }
        }
        return $out;
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $ctx = $this->service->getContexto($idEmpresa, (int) $_SESSION['id_usuario']);
        $anios = $ctx['anios'];
        $anio = $this->anio($anios);
        if (!in_array($anio, $anios, true)) {
            $anios[] = $anio;
            rsort($anios);
        }

        $this->viewWithLayout('layouts.main', 'modulos/declaracion_renta/index', [
            'titulo'     => 'Declaración de Impuesto a la Renta',
            'fullWidth'  => true,
            'perm'       => $this->getPermisos(),
            'anio'       => $anio,
            'anios'      => $anios,
            'contexto'   => $ctx,
            'base'       => BASE_URL,
            'rutaModulo' => $this->getRutaModulo(),
        ]);
    }

    /** Calcula la declaración del año con los ajustes de pantalla y devuelve el JSON completo. */
    public function calcularAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
            $calc = $this->service->calcular($idEmpresa, $src['anio'] ?? 0, $this->ajustes(), (int) $_SESSION['id_usuario']);
            $calc['resumen_documentos'] = $this->service->filasResumenDocumentos($calc['documentos']);
            $calc['fuentes_detalle'] = DeclaracionRentaService::FUENTES_DETALLE;
            unset($calc['empresa']);
            $this->json(['success' => true, 'data' => $calc]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Documentos de una fuente (pestaña Detalle de documentos). */
    public function detalleAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $fuente = (string) ($_GET['fuente'] ?? '');
            if (!isset(DeclaracionRentaService::FUENTES_DETALLE[$fuente])) {
                throw new \InvalidArgumentException('Fuente de detalle no válida.');
            }
            $rows = $this->service->getDetalle($idEmpresa, $_GET['anio'] ?? 0, $fuente, (int) $_SESSION['id_usuario']);
            foreach ($rows as &$r) {
                $r['tipo_nombre'] = DeclaracionRentaService::nombreTipoDoc((string) ($r['tipo'] ?? ''));
            }
            unset($r);
            $this->json(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function esAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    private function errorExport(\Throwable $e): never
    {
        if ($this->esAjax()) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
        http_response_code(422);
        echo 'No se pudo generar el archivo: ' . htmlspecialchars($e->getMessage());
        exit;
    }

    /** Envía un archivo generado en memoria como descarga. */
    private function descargar(string $contenido, string $nombre, string $mime): never
    {
        if (ob_get_length()) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($contenido));
        header('Cache-Control: max-age=0');
        echo $contenido;
        exit;
    }

    public function pdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $calc = $this->service->calcular($idEmpresa, $_GET['anio'] ?? 0, $this->ajustes(), (int) $_SESSION['id_usuario']);
            $pdf = $this->service->generarPdf($calc);
        } catch (\Throwable $e) {
            $this->errorExport($e);
        }
        $this->descargar($pdf, $this->service->nombreArchivo($calc, 'pdf'), 'application/pdf');
    }

    public function excel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $calc = $this->service->calcular($idEmpresa, $_GET['anio'] ?? 0, $this->ajustes(), (int) $_SESSION['id_usuario']);
            $xlsx = $this->service->generarExcel($calc, $idEmpresa, (int) $_SESSION['id_usuario']);
        } catch (\Throwable $e) {
            $this->errorExport($e);
        }
        $this->descargar($xlsx, $this->service->nombreArchivo($calc, 'xlsx'), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** XML de casilleros para cargar en el portal del SRI (solo formularios con contabilidad). */
    public function xml(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $calc = $this->service->calcular($idEmpresa, $_GET['anio'] ?? 0, $this->ajustes(), (int) $_SESSION['id_usuario']);
            if ($calc['tipo'] === DeclaracionRentaService::TIPO_PN) {
                throw new \RuntimeException('El XML de casilleros solo aplica a contribuyentes con contabilidad (formulario 101 y 102 de obligados).');
            }
            $xml = $this->service->generarXml($calc);
        } catch (\Throwable $e) {
            $this->errorExport($e);
        }
        $this->descargar($xml, $this->service->nombreArchivo($calc, 'xml'), 'application/xml; charset=utf-8');
    }
}
