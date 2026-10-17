<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Grupos de productos equivalentes (pestaña Referencias de la ficha del producto).
 *
 * Un producto pertenece a un solo grupo (productos.id_grupo_equivalencia); todos los
 * miembros de un grupo son equivalentes entre sí. El "grupo" es interno: el usuario
 * solo arma la lista de equivalentes y ProductoEquivalenciaService crea, une o
 * disuelve los grupos.
 *
 * Si el SQL 2026-10-16_productos_equivalencias.sql aún no se aplicó, disponible()
 * devuelve false y las lecturas devuelven vacío (la sección se esconde, nada falla).
 */
class ProductoEquivalenciaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('productos_grupos_equivalencia');
    }

    /** ¿Está aplicado el SQL (tabla + columna)? */
    public function disponible(): bool
    {
        return $this->tablaExiste('productos_grupos_equivalencia')
            && $this->columnaExiste('productos', 'id_grupo_equivalencia');
    }

    /**
     * Candado de las equivalencias de la empresa: armar un grupo es "leer los grupos de
     * los productos → decidir → reescribir", y dos fichas guardadas a la vez podrían
     * dejar grupos cruzados. Se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
     */
    public function lock(int $idEmpresa): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('equivalencias:' || :e::text))");
        $st->execute([':e' => $idEmpresa]);
    }

    /** Grupo actual del producto (null si no tiene). */
    public function getGrupoDeProducto(int $idProducto, int $idEmpresa): ?int
    {
        if (!$this->disponible()) return null;
        $st = $this->db->prepare(
            "SELECT p.id_grupo_equivalencia
               FROM productos p
               JOIN productos_grupos_equivalencia g ON g.id = p.id_grupo_equivalencia AND g.eliminado = false
              WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false"
        );
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v ? (int) $v : null;
    }

    /**
     * Productos de la empresa por id (para validar los que llegan del formulario),
     * con su grupo vigente. @return array<int,array{id:int,tipo_produccion:string,id_grupo:?int}>
     */
    public function getProductosPorIds(array $ids, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare(
            "SELECT p.id, COALESCE(p.tipo_produccion, '01') AS tipo_produccion,
                    CASE WHEN g.id IS NOT NULL THEN p.id_grupo_equivalencia END AS id_grupo
               FROM productos p
               LEFT JOIN productos_grupos_equivalencia g ON g.id = p.id_grupo_equivalencia AND g.eliminado = false
              WHERE p.id_empresa = ? AND p.eliminado = false AND p.id IN ($ph)"
        );
        $st->execute(array_merge([$idEmpresa], $ids));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = [
                'id'              => (int) $r['id'],
                'tipo_produccion' => (string) $r['tipo_produccion'],
                'id_grupo'        => $r['id_grupo'] !== null ? (int) $r['id_grupo'] : null,
            ];
        }
        return $out;
    }

    /** Ids de los productos vigentes de un grupo. @return int[] */
    public function getIdsMiembros(int $idGrupo, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id FROM productos
              WHERE id_empresa = :e AND id_grupo_equivalencia = :g AND eliminado = false
              ORDER BY id"
        );
        $st->execute([':e' => $idEmpresa, ':g' => $idGrupo]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Equivalentes de un producto (los otros miembros de su grupo) para la pestaña
     * Referencias: código, nombre, marca, estado y saldo total (kardex del ambiente
     * de la empresa, todas las bodegas). Todo en UNA consulta.
     *
     * @param bool $incluirPropio true = devuelve también el propio producto (lo usa el
     *        buscador de la pestaña: al agregar un producto que ya tiene equivalentes,
     *        se agregan él y los suyos).
     */
    public function getEquivalentes(int $idProducto, int $idEmpresa, bool $incluirPropio = false): array
    {
        if (!$this->disponible()) return [];
        $st = $this->db->prepare(
            "SELECT p.id, p.codigo, p.nombre, COALESCE(p.status, 1) AS status,
                    mar.nombre AS nombre_marca,
                    COALESCE(k.stock, 0) AS stock
               FROM productos base
               JOIN productos_grupos_equivalencia g
                 ON g.id = base.id_grupo_equivalencia AND g.eliminado = false
               JOIN productos p
                 ON p.id_grupo_equivalencia = base.id_grupo_equivalencia
                AND p.id_empresa = base.id_empresa AND p.eliminado = false
               LEFT JOIN marcas mar ON mar.id = p.id_marca
               LEFT JOIN LATERAL (
                    SELECT SUM(ik.cantidad) AS stock
                      FROM inventario_kardex ik
                     WHERE ik.id_producto = p.id AND ik.id_empresa = p.id_empresa
                       AND ik.eliminado = false
                       AND ik.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = p.id_empresa)
               ) k ON true
              WHERE base.id = :id AND base.id_empresa = :e AND base.eliminado = false
                AND (p.id <> base.id OR CAST(:propio AS boolean))
              ORDER BY p.nombre, p.id"
        );
        $st->bindValue(':id', $idProducto, PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':propio', $incluirPropio ? 'true' : 'false');
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ficha mínima de un producto sin grupo (para el buscador de la pestaña). */
    public function getProductoSuelto(int $idProducto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.codigo, p.nombre, COALESCE(p.status, 1) AS status,
                    COALESCE(p.tipo_produccion, '01') AS tipo_produccion,
                    mar.nombre AS nombre_marca,
                    (SELECT COALESCE(SUM(ik.cantidad), 0) FROM inventario_kardex ik
                      WHERE ik.id_producto = p.id AND ik.id_empresa = p.id_empresa AND ik.eliminado = false
                        AND ik.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = p.id_empresa)) AS stock
               FROM productos p
               LEFT JOIN marcas mar ON mar.id = p.id_marca
              WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false"
        );
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Ids de los equivalentes de un producto (sin el propio). @return int[] */
    public function getIdsEquivalentes(int $idProducto, int $idEmpresa): array
    {
        $idGrupo = $this->getGrupoDeProducto($idProducto, $idEmpresa);
        if ($idGrupo === null) return [];
        return array_values(array_filter($this->getIdsMiembros($idGrupo, $idEmpresa), fn($i) => $i !== $idProducto));
    }

    public function crearGrupo(int $idEmpresa, string $nombre, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO productos_grupos_equivalencia (id_empresa, nombre, created_at, created_by)
             VALUES (:e, :n, NOW(), :u) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':n' => mb_substr($nombre, 0, 300), ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    /** Asigna (o quita, con null) el grupo a varios productos de la empresa. */
    public function asignarGrupo(array $idsProducto, ?int $idGrupo, int $idEmpresa, int $idUsuario): void
    {
        $ids = array_values(array_unique(array_map('intval', $idsProducto)));
        if (!$ids) return;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare(
            "UPDATE productos
                SET id_grupo_equivalencia = ?, updated_at = NOW(), updated_by = ?
              WHERE id_empresa = ? AND eliminado = false AND id IN ($ph)"
        );
        $st->execute(array_merge([$idGrupo, $idUsuario, $idEmpresa], $ids));
    }

    /** Baja lógica de un grupo que quedó sin uso. */
    public function eliminarGrupo(int $idGrupo, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE productos_grupos_equivalencia
                SET eliminado = true, deleted_at = NOW(), deleted_by = :u_del,
                    updated_at = NOW(), updated_by = :u_upd
              WHERE id = :g AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u_del' => $idUsuario, ':u_upd' => $idUsuario, ':g' => $idGrupo, ':e' => $idEmpresa]);
    }
}
