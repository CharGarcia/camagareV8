<?php
/**
 * BaseModuloController - Clase base para todos los controladores bajo modulos/.
 *
 * Convención de uso:
 *   1. Extender esta clase en lugar de Controller directamente.
 *   2. Implementar getRutaModulo() retornando la ruta MVC sin slash inicial (ej: 'modulos/clientes').
 *   3. La ruta MVC debe estar registrada en config/modulos_mvc.php con sus legacy_rutas.
 *   4. El submodulo correspondiente en submodulos_menu debe tener esa misma ruta en el campo 'ruta'.
 *
 * Permisos automáticos (tabla modulos_asignados):
 *   - index()  → llamar requireLeer()
 *   - store()  → llamar requireCrear()
 *   - update() → llamar requireActualizar()
 *   - delete() → llamar requireEliminar()
 *
 * Nivel 3 (super admin): siempre tiene acceso total, sin registro en modulos_asignados.
 */

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\Traits\PermisoModuloTrait;

abstract class BaseModuloController extends Controller
{
    use PermisoModuloTrait;

    /**
     * Ruta MVC del módulo, ej: 'modulos/clientes'.
     * Debe coincidir con la clave en config/modulos_mvc.php
     * y con submodulos_menu.ruta del submodulo correspondiente.
     */
    abstract protected function getRutaModulo(): string;

    /**
     * Verifica sesión + permiso de lectura (r=1).
     * Usar en index() y en las acciones que muestran datos.
     */
    protected function requireLeer(): void
    {
        $this->requireEmpresaSesion();
        $this->requirePermisoVerModulo($this->getRutaModulo());
    }

    /**
     * Verifica sesión + permiso de creación (w=1).
     * Usar en store().
     * Si es petición AJAX, responde JSON 403 en lugar de redirigir.
     */
    protected function requireCrear(): void
    {
        $this->requireEmpresaSesion();
        $this->requirePermisoModulo($this->getRutaModulo(), 'w');
    }

    /**
     * Verifica sesión + permiso de actualización (u=1).
     * Usar en update().
     */
    protected function requireActualizar(): void
    {
        $this->requireEmpresaSesion();
        $this->requirePermisoModulo($this->getRutaModulo(), 'u');
    }

    /**
     * Verifica sesión + permiso de eliminación (d=1).
     * Usar en delete().
     */
    protected function requireEliminar(): void
    {
        $this->requireEmpresaSesion();
        $this->requirePermisoModulo($this->getRutaModulo(), 'd');
    }

    /**
     * Retorna el array de permisos del módulo para pasar a la vista.
     * Equivalente a permisosModuloPorRuta() pero usando la ruta del módulo actual.
     * @return array{ver:bool,crear:bool,actualizar:bool,eliminar:bool,id_submodulo:?int}
     */
    protected function getPermisos(): array
    {
        return $this->permisosModuloPorRuta($this->getRutaModulo());
    }

    // ─── Replicación de registros entre empresas del mismo usuario ────────────
    // Compartido por los módulos que ofrecen "aplicar también en otras empresas"
    // (Clientes, Productos, …). La lógica de qué copiar/omitir es específica de
    // cada Service; esto solo resuelve QUÉ empresas son destinos válidos.

    /**
     * Empresas del usuario actual donde podría replicar un registro de este
     * módulo (todas las asignadas, o todas las activas si es nivel 3),
     * EXCLUYENDO la empresa activa.
     */
    protected function empresasCandidatasReplicacion(): array
    {
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $idEmpresaActual = (int) $_SESSION['id_empresa'];

        $model = new \App\models\Empresa();
        $todas = $nivel >= 3 ? $model->getTodasActivas() : $model->getEmpresasAsignadas($idUsuario);

        return array_values(array_filter($todas, fn($e) => (int) $e['id_empresa'] !== $idEmpresaActual));
    }

    /**
     * De la lista de empresas destino que pidió el usuario, filtra a las que
     * realmente son candidatas (asignadas al usuario / nivel 3) Y donde tiene
     * permiso de crear en la ruta MVC de este módulo. Las que no cumplen se
     * reportan como 'sin_permiso' en vez de simplemente ignorarse, para que la
     * UI pueda avisar.
     *
     * @return array{permitidas:int[], resultado:array<int,array{estado:string}>}
     */
    protected function filtrarEmpresasDestinoPermitidas(array $idsSolicitadas): array
    {
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $candidatasIds = array_map(fn($e) => (int) $e['id_empresa'], $this->empresasCandidatasReplicacion());

        $permitidas = [];
        $resultado = [];
        foreach (array_unique(array_map('intval', $idsSolicitadas)) as $idEmp) {
            if ($idEmp <= 0 || !in_array($idEmp, $candidatasIds, true)) {
                $resultado[$idEmp] = ['estado' => 'sin_permiso'];
                continue;
            }
            $permiso = \App\Helpers\Permisos::porRutaEnEmpresa($this->getRutaModulo(), $idEmp, $idUsuario, $nivel);
            if (empty($permiso['crear'])) {
                $resultado[$idEmp] = ['estado' => 'sin_permiso'];
                continue;
            }
            $permitidas[] = $idEmp;
        }

        return ['permitidas' => $permitidas, 'resultado' => $resultado];
    }

    /**
     * Responde JSON con las empresas candidatas para el selector de replicación
     * (checkboxes del modal / selector del botón masivo). Uso típico:
     *   public function empresasDestinoAjax(): void {
     *       $this->requireCrear();
     *       $this->empresasDestinoAjaxResponse();
     *   }
     */
    protected function empresasDestinoAjaxResponse(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $data = array_map(fn($e) => [
                'id_empresa' => (int) $e['id_empresa'],
                'texto'      => ($e['establecimiento'] ?? '001') . ' - ' . (!empty($e['nombre_comercial']) ? $e['nombre_comercial'] : $e['nombre']),
            ], $this->empresasCandidatasReplicacion());
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'empresasDestinoAjax']);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * AJAX: Obtiene el historial de cambios de un registro.
     */
    public function getHistorialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $id = (int) ($_GET['id'] ?? 0);
        $tabla = $_GET['tabla'] ?? ''; 
        
        if (!$id || !$tabla) {
            echo json_encode(['ok' => false, 'error' => 'Parámetros insuficientes']);
            exit;
        }
        
        // El id_empresa es obligatorio por reglas de seguridad de modulos operativos
        $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
        
        $logService = new \App\Services\LogSistemaService();
        $historial = $logService->getHistorial($tabla, $id, $idEmpresa);
        
        echo json_encode(['ok' => true, 'data' => $historial]);
        exit;
    }
}
