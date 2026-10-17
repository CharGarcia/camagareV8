<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ProductoEquivalenciaRepository;
use App\Rules\modulos\ProductoEquivalenciaRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Productos equivalentes (pestaña Referencias de la ficha del producto).
 *
 * El usuario arma la LISTA de equivalentes de un producto; este servicio traduce esa
 * lista a grupos: todos los productos de un grupo son equivalentes entre sí, así que
 * agregar a B en la ficha de A deja a A en la lista de B (y a los demás del grupo).
 *
 *  - A sin grupo + B sin grupo         → se crea un grupo con A y B.
 *  - A sin grupo + B con grupo         → A entra al grupo de B.
 *  - A con grupo + B con otro grupo    → se unen: B (y los suyos que sigan en la lista)
 *                                        pasan al grupo de A.
 *  - Quitar a B de la lista            → B sale del grupo.
 *  - Un grupo que queda con un solo producto se disuelve.
 *
 * Solo saca del grupo a los productos que el usuario VIO en la pestaña y quitó
 * ($vistos): si otro usuario agregó un equivalente mientras la ficha estaba abierta,
 * ese no se pierde al guardar.
 */
class ProductoEquivalenciaService
{
    private ProductoEquivalenciaRepository $repository;
    private ProductoEquivalenciaRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        ?ProductoEquivalenciaRepository $repository = null,
        ?ProductoEquivalenciaRules $rules = null,
        ?LogSistemaService $logService = null
    ) {
        $this->repository = $repository ?? new ProductoEquivalenciaRepository();
        $this->rules      = $rules ?? new ProductoEquivalenciaRules();
        $this->logService = $logService ?? new LogSistemaService();
    }

    /** ¿Está aplicado el SQL? Sin él la pestaña no muestra la sección. */
    public function disponible(): bool
    {
        return $this->repository->disponible();
    }

    /** Equivalentes de un producto para la pestaña Referencias. */
    public function getEquivalentes(int $idProducto, int $idEmpresa): array
    {
        return $this->repository->getEquivalentes($idProducto, $idEmpresa);
    }

    /**
     * Lo que se agrega a la lista al elegir un producto en el buscador de la pestaña: el
     * producto y, si ya tiene equivalentes, también ellos (se van a unir los grupos y el
     * usuario debe verlo antes de guardar).
     */
    public function getParaAgregar(int $idProducto, int $idEmpresa): array
    {
        if (!$this->disponible()) return [];
        $filas = $this->repository->getEquivalentes($idProducto, $idEmpresa, true);
        if ($filas) return $filas;
        $suelto = $this->repository->getProductoSuelto($idProducto, $idEmpresa);
        if (!$suelto) return [];
        if ($suelto['tipo_produccion'] === '02') {
            throw new Exception('Los servicios no pueden ser productos equivalentes.');
        }
        unset($suelto['tipo_produccion']);
        return [$suelto];
    }

    /** Ids de los equivalentes de un producto (para las sugerencias al vender). */
    public function getIdsEquivalentes(int $idProducto, int $idEmpresa): array
    {
        if (!$this->disponible()) return [];
        return $this->repository->getIdsEquivalentes($idProducto, $idEmpresa);
    }

    /**
     * Deja la lista de equivalentes del producto como la armó el usuario.
     *
     * Requiere una transacción ABIERTA (la de ProductoService::crear()/actualizar()): no
     * abre ni confirma la suya, para que el producto y sus equivalentes se guarden o se
     * reviertan juntos.
     *
     * @param int[] $ids    Lista final de equivalentes (sin el propio producto).
     * @param int[] $vistos Productos que el usuario tuvo en la lista durante la edición.
     */
    public function sincronizar(
        int $idProducto,
        int $idEmpresa,
        array $ids,
        array $vistos,
        string $tipoProduccion,
        string $nombreProducto,
        int $idUsuario
    ): void {
        if (!$this->repository->disponible()) return;
        if (!$this->repository->getDb()->inTransaction()) {
            throw new Exception('Las equivalencias se guardan dentro de la transacción del producto.');
        }

        $this->repository->lock($idEmpresa);

        $norm = fn(array $l) => array_values(array_unique(array_filter(array_map('intval', $l), fn($i) => $i > 0 && $i !== $idProducto)));
        $ids    = $tipoProduccion === '02' ? [] : $norm($ids);
        $vistos = $norm(array_merge($vistos, $ids));

        $encontrados = $this->repository->getProductosPorIds(array_merge($ids, [$idProducto]), $idEmpresa);
        $this->rules->validar($idProducto, $ids, $encontrados);

        $grupoActual = $encontrados[$idProducto]['id_grupo'] ?? null;
        $antes = $grupoActual !== null
            ? array_values(array_diff($this->repository->getIdsMiembros($grupoActual, $idEmpresa), [$idProducto]))
            : [];

        $ordenar = function (array $l) { sort($l); return $l; };
        if ($ordenar($antes) === $ordenar($ids)) return; // sin cambios

        $gruposAfectados = [];
        $destino = null;

        if (!$ids) {
            // Sin equivalentes: el producto sale de su grupo.
            if ($grupoActual !== null) {
                $this->repository->asignarGrupo([$idProducto], null, $idEmpresa, $idUsuario);
                $gruposAfectados[$grupoActual] = true;
            }
        } else {
            $destino = $grupoActual;
            if ($destino === null) {
                foreach ($ids as $id) {
                    if (($encontrados[$id]['id_grupo'] ?? null) !== null) { $destino = $encontrados[$id]['id_grupo']; break; }
                }
            }
            if ($destino === null) {
                $destino = $this->repository->crearGrupo($idEmpresa, $nombreProducto, $idUsuario);
            }

            $final = array_merge([$idProducto], $ids);
            foreach ($final as $id) {
                $g = $encontrados[$id]['id_grupo'] ?? null;
                if ($g !== null && $g !== $destino) $gruposAfectados[$g] = true;
            }
            // Salen del grupo solo los que el usuario vio en la lista y quitó.
            $quitados = array_values(array_intersect(
                array_diff($this->repository->getIdsMiembros($destino, $idEmpresa), $final),
                $vistos
            ));

            $this->repository->asignarGrupo($final, $destino, $idEmpresa, $idUsuario);
            if ($quitados) {
                $this->repository->asignarGrupo($quitados, null, $idEmpresa, $idUsuario);
            }
        }

        // Grupos que quedaron con 0 o 1 producto: se disuelven.
        foreach (array_keys($gruposAfectados) as $g) {
            if ($g === $destino) continue;
            $miembros = $this->repository->getIdsMiembros($g, $idEmpresa);
            if (count($miembros) <= 1) {
                if ($miembros) $this->repository->asignarGrupo($miembros, null, $idEmpresa, $idUsuario);
                $this->repository->eliminarGrupo($g, $idEmpresa, $idUsuario);
            }
        }
        if ($destino !== null && count($this->repository->getIdsMiembros($destino, $idEmpresa)) <= 1) {
            $this->repository->asignarGrupo([$idProducto], null, $idEmpresa, $idUsuario);
            $this->repository->eliminarGrupo($destino, $idEmpresa, $idUsuario);
            $destino = null;
        }

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'actualizar_equivalencias',
            'productos',
            $idProducto,
            ['id_grupo_equivalencia' => $grupoActual, 'equivalentes' => $antes],
            ['id_grupo_equivalencia' => $destino, 'equivalentes' => $ids]
        );
    }
}
