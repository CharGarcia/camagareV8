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
