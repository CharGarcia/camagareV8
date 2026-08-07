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
        ]);
    }

    public function contarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            [$tipo, $formato, $desde, $hasta] = $this->leerFiltros();
            DescargaMasivaRules::validarFiltros($tipo, $formato, $desde, $hasta);

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuarioFiltro = $this->idUsuarioFiltro();

            $service = new DescargaMasivaService($this->configModulo());
            $resultado = $service->contar($idEmpresa, $tipo, $desde, $hasta, $idUsuarioFiltro, $formato);

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

        $rutaZip = '';
        try {
            [$tipo, $formato, $desde, $hasta] = $this->leerFiltros();
            DescargaMasivaRules::validarFiltros($tipo, $formato, $desde, $hasta);

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $idUsuarioFiltro = $this->idUsuarioFiltro();

            set_time_limit(0);

            $service = new DescargaMasivaService($this->configModulo());
            $resultado = $service->generarZip($idEmpresa, $idUsuario, $tipo, $desde, $hasta, $formato, $idUsuarioFiltro);
            $rutaZip = $resultado['ruta'];

            if (!is_file($rutaZip)) {
                throw new \RuntimeException('No se pudo generar el archivo.');
            }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $resultado['nombre'] . '"');
            header('Content-Length: ' . filesize($rutaZip));
            header('Cache-Control: no-store');
            readfile($rutaZip);
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
            DescargaMasivaService::eliminarTemporal($rutaZip);
        }
        exit;
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    /** @return array{0:string,1:string,2:string,3:string} [tipo, formato, desde, hasta] */
    private function leerFiltros(): array
    {
        $tipo    = trim((string) ($_GET['tipo'] ?? ''));
        $formato = trim((string) ($_GET['formato'] ?? 'pdf'));
        $desde   = trim((string) ($_GET['desde'] ?? ''));
        $hasta   = trim((string) ($_GET['hasta'] ?? ''));
        return [$tipo, $formato, $desde, $hasta];
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
