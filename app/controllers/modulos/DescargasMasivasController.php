<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Rules\modulos\DescargaMasivaRules;
use App\Services\modulos\DescargaMasivaService;

/**
 * Descargas Masivas: PDF y/o XML de varios documentos de un mismo tipo (rango
 * de fechas) comprimidos en un ZIP. Genera en el momento y no guarda nada: el
 * ZIP se arma en un archivo temporal y se borra apenas se transmite al navegador.
 */
class DescargasMasivasController extends BaseModuloController
{
    protected function getRutaModulo(): string
    {
        return 'modulos/descargas-masivas';
    }

    public function index(): void
    {
        $this->requireLeer();

        $this->viewWithLayout('layouts.main', 'modulos/descargas_masivas/index', [
            'titulo'     => 'Descargas Masivas',
            'perm'       => $this->getPermisos(),
            'base'       => BASE_URL,
            'rutaModulo' => $this->getRutaModulo(),
            'tipos'      => DescargaMasivaService::ETIQUETAS,
            'tiposSinXml' => DescargaMasivaService::TIPOS_SIN_XML,
            'umbralPdfUnico' => (int) ($this->configModulo()['umbral_pdf_unico'] ?? 20),
        ]);
    }

    public function contarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            [$tipo, $formato, $filtro] = $this->leerFiltros();
            DescargaMasivaRules::validarFiltros(
                $tipo, $formato, $filtro['modo'],
                $filtro['desde'] ?? null, $filtro['hasta'] ?? null,
                $filtro['numero_desde'] ?? null, $filtro['numero_hasta'] ?? null
            );

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuarioFiltro = $this->idUsuarioFiltro();

            $service = new DescargaMasivaService($this->configModulo());
            $resultado = $service->contar($idEmpresa, $tipo, $filtro, $idUsuarioFiltro, $formato);

            echo json_encode(['ok' => true] + $resultado, JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo consultar los documentos.']);
        }
        exit;
    }

    public function descargarAjax(): void
    {
        $this->requireLeer();

        $rutaArchivo = '';
        try {
            [$tipo, $formato, $filtro] = $this->leerFiltros();
            DescargaMasivaRules::validarFiltros(
                $tipo, $formato, $filtro['modo'],
                $filtro['desde'] ?? null, $filtro['hasta'] ?? null,
                $filtro['numero_desde'] ?? null, $filtro['numero_hasta'] ?? null
            );

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $idUsuarioFiltro = $this->idUsuarioFiltro();

            set_time_limit(0);

            $service = new DescargaMasivaService($this->configModulo());
            $resultado = $service->generarDescarga($idEmpresa, $idUsuario, $tipo, $filtro, $formato, $idUsuarioFiltro);
            $rutaArchivo = $resultado['ruta'];

            if (!is_file($rutaArchivo)) {
                throw new \RuntimeException('No se pudo generar el archivo.');
            }

            $contentType = $resultado['tipo_salida'] === 'pdf' ? 'application/pdf' : 'application/zip';
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . $resultado['nombre'] . '"');
            header('Content-Length: ' . filesize($rutaArchivo));
            header('Cache-Control: no-store');
            readfile($rutaArchivo);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo $e->getMessage();
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'No se pudo generar la descarga.';
        } finally {
            DescargaMasivaService::eliminarTemporal($rutaArchivo);
        }
        exit;
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    /**
     * @return array{0:string,1:string,2:array} [tipo, formato, filtro]
     *         filtro: modo ('fecha'|'numero') + desde/hasta o numero_desde/numero_hasta.
     */
    private function leerFiltros(): array
    {
        $tipo    = trim((string) ($_GET['tipo'] ?? ''));
        $formato = trim((string) ($_GET['formato'] ?? 'pdf'));
        $modo    = trim((string) ($_GET['modo'] ?? 'fecha')) ?: 'fecha';

        $filtro = ['modo' => $modo];
        if ($modo === 'numero') {
            $numeroDesde = $_GET['numero_desde'] ?? '';
            $numeroHasta = $_GET['numero_hasta'] ?? '';
            $filtro['numero_desde'] = is_numeric($numeroDesde) ? (int) $numeroDesde : null;
            $filtro['numero_hasta'] = is_numeric($numeroHasta) ? (int) $numeroHasta : null;
        } else {
            $filtro['desde'] = trim((string) ($_GET['desde'] ?? ''));
            $filtro['hasta'] = trim((string) ($_GET['hasta'] ?? ''));
        }

        return [$tipo, $formato, $filtro];
    }

    private function idUsuarioFiltro(): ?int
    {
        $perm = $this->getPermisos();
        return empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    private function configModulo(): array
    {
        $cfg = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        return $cfg['descargas_masivas'] ?? [];
    }
}
