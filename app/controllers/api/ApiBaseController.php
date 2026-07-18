<?php
/**
 * Controlador base para la API móvil (/api/v1/*).
 * Formato de respuesta estándar {ok, data|error} con códigos HTTP reales
 * (401/403/404/409/422/500), a diferencia del patrón AJAX web (siempre 200),
 * porque el cliente móvil decide automáticamente reintentar/refrescar token/
 * mostrar conflicto según el código HTTP.
 *
 * Incorpora PermisoModuloTrait (igual que BaseModuloController) para reutilizar
 * TAL CUAL la resolución de permisos por submódulo (modulos_asignados): los
 * permisos son por usuario+empresa+submódulo, no por canal web/app, así que la
 * misma ruta de módulo (ej. 'modulos/pedidos') sirve para ambos.
 */

declare(strict_types=1);

namespace App\controllers\api;

use App\core\Controller;
use App\middleware\ApiAuthMiddleware;
use App\Traits\PermisoModuloTrait;

abstract class ApiBaseController extends Controller
{
    use PermisoModuloTrait;

    protected array $claims = [];

    /**
     * Ruta MVC del módulo para resolver permisos (ej. 'modulos/pedidos'), la MISMA
     * que usa el controlador web equivalente en submodulos_menu/modulos_asignados.
     */
    abstract protected function getRutaModulo(): string;

    /**
     * Exige un Bearer JWT válido. Rellena $this->claims y $_SESSION.
     * Debe llamarse (directa o indirectamente vía requireLeer/Crear/...) al inicio
     * de toda acción que no sea pública (login/refresh).
     */
    protected function requireAuthApi(): array
    {
        if (empty($this->claims)) {
            $this->claims = ApiAuthMiddleware::handle();
        }
        return $this->claims;
    }

    /** Siempre responde JSON (nunca redirect): esta clase solo sirve la API. */
    protected function esAjaxRequest(): bool
    {
        return true;
    }

    protected function requireLeer(): void
    {
        $this->requireAuthApi();
        $this->requireEmpresaSesion();
        $this->requirePermisoVerModulo($this->getRutaModulo());
    }

    protected function requireCrear(): void
    {
        $this->requireAuthApi();
        $this->requireEmpresaSesion();
        $this->requirePermisoModulo($this->getRutaModulo(), 'w');
    }

    protected function requireActualizar(): void
    {
        $this->requireAuthApi();
        $this->requireEmpresaSesion();
        $this->requirePermisoModulo($this->getRutaModulo(), 'u');
    }

    protected function requireEliminar(): void
    {
        $this->requireAuthApi();
        $this->requireEmpresaSesion();
        $this->requirePermisoModulo($this->getRutaModulo(), 'd');
    }

    /** @return array{ver:bool,crear:bool,actualizar:bool,eliminar:bool,todo:bool,id_submodulo:?int} */
    protected function getPermisos(): array
    {
        return $this->permisosModuloPorRuta($this->getRutaModulo());
    }

    protected function jsonOk(mixed $data = null, array $meta = [], int $status = 200): never
    {
        $body = ['ok' => true, 'data' => $data];
        if (!empty($meta)) {
            $body['meta'] = $meta;
        }
        $this->salidaJson($body, $status);
    }

    protected function jsonError(string $code, string $message, int $status = 400, array $extra = []): never
    {
        $error = array_merge(['code' => $code, 'message' => $message], $extra);
        $this->salidaJson(['ok' => false, 'error' => $error], $status);
    }

    /**
     * Cuerpo del request como array. Acepta tanto JSON (application/json, lo usual
     * en el cliente móvil con axios) como form-urlencoded ($_POST), para no atar
     * el cliente a un solo Content-Type.
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }

    private function salidaJson(array $body, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
