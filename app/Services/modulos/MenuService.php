<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\MenuRepository;
use App\Rules\modulos\MenuRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Lógica de negocio del módulo Menú (carta del restaurante). Un ítem del
 * menú puede vincularse a un producto (incluye productos compuestos/combos,
 * ya soportados por InventarioService) o ser independiente.
 */
class MenuService
{
    private MenuRepository $repository;
    private MenuRules $rules;
    private LogSistemaService $logService;

    public function __construct(MenuRepository $repository, MenuRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function findById(int $id, int $idEmpresa): ?array
    {
        return $this->repository->find($id, $idEmpresa);
    }

    public function getDisponibles(int $idEmpresa, string $buscar = ''): array
    {
        return $this->repository->getDisponibles($idEmpresa, $buscar);
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa   = (int) $data['id_empresa'];
        $idProducto  = (int) ($data['id_producto'] ?? 0);
        if ($idProducto > 0 && $this->repository->existeParaProducto($idProducto, $idEmpresa)) {
            throw new Exception('Ese producto ya tiene un ítem en el menú.');
        }

        $insert = $this->armarDatos($data);
        $id = $this->repository->create($insert);

        $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'crear', 'menu_items', $id, null, $insert);
        return $id;
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        // El controller no manda id_empresa dentro de $data (viene aparte como
        // argumento); MenuRules::validar() sí lo exige, así que se completa aquí
        // (mismo bug que ya se corrigió en MesaService::actualizar()).
        $data['id_empresa'] = $idEmpresa;
        $this->rules->validar($data);

        $antes = $this->repository->find($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El ítem del menú no existe o ha sido eliminado.');
        }

        $idProducto = (int) ($data['id_producto'] ?? 0);
        if ($idProducto > 0 && $this->repository->existeParaProducto($idProducto, $idEmpresa, $id)) {
            throw new Exception('Ese producto ya tiene un ítem en el menú.');
        }

        $update = $this->armarDatos($data);
        $update['updated_by'] = (int) $data['id_usuario'];
        $this->repository->update($id, $idEmpresa, $update);

        $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'actualizar', 'menu_items', $id, $antes, $update);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->find($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El ítem del menú no existe o ya fue eliminado.');
        }
        $this->repository->delete($id, $idEmpresa, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'menu_items', $id, $antes, ['eliminado' => true]);
    }

    private function armarDatos(array $data): array
    {
        return [
            'id_empresa'    => (int) $data['id_empresa'],
            'id_producto'   => (int) ($data['id_producto'] ?? 0) ?: null,
            'nombre'        => mb_strtoupper(trim((string) $data['nombre']), 'UTF-8'),
            'descripcion'   => trim((string) ($data['descripcion'] ?? '')),
            'precio'        => round((float) ($data['precio'] ?? 0), 2),
            'imagen'        => trim((string) ($data['imagen'] ?? '')),
            'id_categoria'  => (int) ($data['id_categoria'] ?? 0) ?: null,
            'id_tarifa_iva' => (int) ($data['id_tarifa_iva'] ?? 0) ?: null,
            'disponible'    => !empty($data['disponible']),
            'destacado'     => !empty($data['destacado']),
            'orden'         => (int) ($data['orden'] ?? 0),
            'created_by'    => (int) ($data['id_usuario'] ?? 0),
        ];
    }

    // ─── Categorías del menú (propias, separadas de `categorias` de Productos) ────

    public function getMenuCategorias(int $idEmpresa): array
    {
        return $this->repository->getMenuCategorias($idEmpresa);
    }

    public function crearMenuCategoria(array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la categoría es obligatorio.');
        }
        if ($this->repository->existeMenuCategoriaNombre($idEmpresa, $nombre)) {
            throw new Exception("Ya existe una categoría del menú con el nombre '{$nombre}'.");
        }

        $insert = [
            'id_empresa'            => $idEmpresa,
            'nombre'                => mb_strtoupper($nombre, 'UTF-8'),
            'id_estacion_impresion' => (int) ($data['id_estacion_impresion'] ?? 0) ?: null,
            'orden'                 => (int) ($data['orden'] ?? 0),
            'created_by'            => (int) $data['id_usuario'],
        ];
        $id = $this->repository->crearMenuCategoria($insert);
        $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'crear', 'menu_categorias', $id, null, $insert);
        return $id;
    }

    public function actualizarMenuCategoria(int $id, int $idEmpresa, array $data): void
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la categoría es obligatorio.');
        }
        if ($this->repository->existeMenuCategoriaNombre($idEmpresa, $nombre, $id)) {
            throw new Exception("Ya existe otra categoría del menú con el nombre '{$nombre}'.");
        }

        $update = [
            'nombre'                => mb_strtoupper($nombre, 'UTF-8'),
            'id_estacion_impresion' => (int) ($data['id_estacion_impresion'] ?? 0) ?: null,
            'orden'                 => (int) ($data['orden'] ?? 0),
            'updated_by'            => (int) $data['id_usuario'],
        ];
        $this->repository->actualizarMenuCategoria($id, $idEmpresa, $update);
        $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'actualizar', 'menu_categorias', $id, null, $update);
    }

    public function eliminarMenuCategoria(int $id, int $idEmpresa, int $idUsuario): void
    {
        $usos = $this->repository->contarMenuItemsEnCategoria($id, $idEmpresa);
        if ($usos > 0) {
            throw new Exception("No se puede eliminar: {$usos} ítem(s) del menú usan esta categoría.");
        }
        $this->repository->eliminarMenuCategoria($id, $idEmpresa, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'menu_categorias', $id, null, ['eliminado' => true]);
    }

    // ─── Estaciones de impresión (catálogo compartido: Productos + Menú + KDS) ────

    public function getEstaciones(int $idEmpresa): array
    {
        return $this->repository->getEstaciones($idEmpresa);
    }

    public function crearEstacion(array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la estación es obligatorio.');
        }
        if ($this->repository->existeEstacionNombre($idEmpresa, $nombre)) {
            throw new Exception("Ya existe una estación con el nombre '{$nombre}'.");
        }

        $insert = [
            'id_empresa' => $idEmpresa,
            'nombre'     => mb_strtoupper($nombre, 'UTF-8'),
            'tipo'       => $this->normalizarTipoEstacion($data['tipo'] ?? 'cocina'),
            'orden'      => (int) ($data['orden'] ?? 0),
            'created_by' => (int) $data['id_usuario'],
        ];
        $id = $this->repository->crearEstacion($insert);
        $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'crear', 'estaciones_impresion', $id, null, $insert);
        return $id;
    }

    public function actualizarEstacion(int $id, int $idEmpresa, array $data): void
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la estación es obligatorio.');
        }
        if ($this->repository->existeEstacionNombre($idEmpresa, $nombre, $id)) {
            throw new Exception("Ya existe otra estación con el nombre '{$nombre}'.");
        }

        $update = [
            'nombre'     => mb_strtoupper($nombre, 'UTF-8'),
            'tipo'       => $this->normalizarTipoEstacion($data['tipo'] ?? 'cocina'),
            'orden'      => (int) ($data['orden'] ?? 0),
            'updated_by' => (int) $data['id_usuario'],
        ];
        $this->repository->actualizarEstacion($id, $idEmpresa, $update);
        $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'actualizar', 'estaciones_impresion', $id, null, $update);
    }

    public function eliminarEstacion(int $id, int $idEmpresa, int $idUsuario): void
    {
        $usos = $this->repository->contarUsosEstacion($id, $idEmpresa);
        if ($usos > 0) {
            throw new Exception("No se puede eliminar: {$usos} categoría(s) usan esta estación.");
        }
        $this->repository->eliminarEstacion($id, $idEmpresa, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'estaciones_impresion', $id, null, ['eliminado' => true]);
    }

    /** Solo informativo (ícono/agrupación); no restringe a cuántas estaciones se pueden crear. */
    private function normalizarTipoEstacion(string $tipo): string
    {
        return in_array($tipo, ['cocina', 'barra', 'otro'], true) ? $tipo : 'cocina';
    }
}
