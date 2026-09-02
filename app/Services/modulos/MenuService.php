<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\MenuRepository;
use App\repositories\modulos\ProductoRepository;
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
    private ProductoRepository $productoRepo;

    public function __construct(MenuRepository $repository, MenuRules $rules, LogSistemaService $logService, ?ProductoRepository $productoRepo = null)
    {
        $this->repository   = $repository;
        $this->rules        = $rules;
        $this->logService   = $logService;
        $this->productoRepo = $productoRepo ?? new ProductoRepository();
    }

    /**
     * La foto del ítem y la del producto vinculado son la misma: al guardarla
     * desde la carta se replica en Productos, para que no queden dos fotos
     * distintas del mismo artículo según dónde se lo mire.
     * Un ítem sin producto vinculado conserva su foto propia y no toca nada.
     */
    private function sincronizarImagenConProducto(array $datos, int $idEmpresa, int $idUsuario): void
    {
        $idProducto = (int) ($datos['id_producto'] ?? 0);
        if ($idProducto <= 0) {
            return;
        }
        $this->productoRepo->actualizarImagen($idProducto, $idEmpresa, $datos['imagen'] ?? null, $idUsuario);
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
        $this->sincronizarImagenConProducto($insert, $idEmpresa, (int) $data['id_usuario']);

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
        $this->sincronizarImagenConProducto($update, $idEmpresa, (int) $data['id_usuario']);

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
            'id_estacion_impresion' => (int) ($data['id_estacion_impresion'] ?? 0) ?: null,
            'disponible'    => !empty($data['disponible']),
            'destacado'     => !empty($data['destacado']),
            'orden'         => (int) ($data['orden'] ?? 0),
            'created_by'    => (int) ($data['id_usuario'] ?? 0),
        ];
    }

    // ─── Categorías ──────────────────────────────────────────────────────────────

    /**
     * Las categorías del menú son las de Productos: se administran en ese módulo
     * y aquí solo se leen. Antes el menú tenía su propia tabla (menu_categorias),
     * que quedó sin uso.
     */
    public function getCategorias(int $idEmpresa): array
    {
        return $this->repository->getCategorias($idEmpresa);
    }

    // ─── Estaciones de impresión ─────────────────────────────────────────────
    // Solo LECTURA: alimenta el selector "Preparar en" de la carta. El catálogo
    // se administra en modulos/configuracion-restaurante.

    public function getEstaciones(int $idEmpresa): array
    {
        return $this->repository->getEstaciones($idEmpresa);
    }

}
