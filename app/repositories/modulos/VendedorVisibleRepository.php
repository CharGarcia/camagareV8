<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Vendedores adicionales que un usuario puede ver en el Reporte de Ventas por
 * Vendedor (tabla `usuarios_vendedores_visibles`, ver
 * database/usuarios_vendedores_visibles.sql). Se administran en
 * /config/permisos-modulos.
 *
 * Mientras no se ejecute el SQL, la tabla no existe: las lecturas devuelven
 * vacío (el reporte sigue funcionando como antes) en vez de romper.
 */
class VendedorVisibleRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('usuarios_vendedores_visibles');
    }

    /** ¿Ya se ejecutó el SQL que crea la tabla? (cacheado por BaseRepository). */
    public function disponible(): bool
    {
        return $this->tablaExiste($this->table);
    }

    /**
     * Ids de los vendedores habilitados al usuario en la empresa. Solo vendedores
     * vigentes (no eliminados) de esa misma empresa.
     *
     * @return int[]
     */
    public function getIdsVendedores(int $idEmpresa, int $idUsuario): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT uvv.id_vendedor
               FROM {$this->table} uvv
               JOIN vendedores v ON v.id = uvv.id_vendedor
                                AND v.id_empresa = uvv.id_empresa
                                AND v.eliminado = false
              WHERE uvv.id_empresa = :id_empresa
                AND uvv.id_usuario = :id_usuario
                AND uvv.eliminado = false"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Vendedores de la empresa para la tarjeta de configuración, cada uno con la
     * marca `visible` si el usuario ya lo tiene habilitado. Incluye inactivos
     * (status <> 1) para que una asignación vieja no quede escondida.
     */
    public function getVendedoresConMarca(int $idEmpresa, int $idUsuario): array
    {
        $visible = $this->disponible()
            ? "EXISTS (SELECT 1 FROM {$this->table} uvv
                        WHERE uvv.id_empresa = v.id_empresa AND uvv.id_usuario = :id_usuario
                          AND uvv.id_vendedor = v.id AND uvv.eliminado = false)"
            : 'false';
        $params = [':id_empresa' => $idEmpresa];
        if ($this->disponible()) {
            $params[':id_usuario'] = $idUsuario;
        }
        $st = $this->db->prepare(
            "SELECT v.id, v.nombre, v.identificacion,
                    (COALESCE(v.status, 0) = 1) AS activo,
                    {$visible} AS visible
               FROM vendedores v
              WHERE v.id_empresa = :id_empresa
                AND v.eliminado = false
              ORDER BY v.nombre ASC, v.id ASC"
        );
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['activo'] = (bool) $r['activo'];
            $r['visible'] = (bool) $r['visible'];
        }
        return $rows;
    }

    /** Vendedor vigente de la empresa, o null. */
    public function getVendedor(int $idEmpresa, int $idVendedor): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre FROM vendedores
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':id' => $idVendedor, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Nombres de un conjunto de vendedores de la empresa (para el filtro Vendedor
     * del reporte de un usuario restringido).
     *
     * @param int[] $ids
     */
    public function getVendedoresPorIds(int $idEmpresa, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if (!$ids) {
            return [];
        }
        $ph = [];
        $params = [':id_empresa' => $idEmpresa];
        foreach ($ids as $i => $id) {
            $ph[] = ":v{$i}";
            $params[":v{$i}"] = $id;
        }
        $st = $this->db->prepare(
            "SELECT id, nombre FROM vendedores
              WHERE id_empresa = :id_empresa AND eliminado = false
                AND id IN (" . implode(',', $ph) . ")
              ORDER BY nombre ASC, id ASC"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Fila vigente usuario + vendedor, o null. */
    public function getAsignacion(int $idEmpresa, int $idUsuario, int $idVendedor): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM {$this->table}
              WHERE id_empresa = :id_empresa AND id_usuario = :id_usuario
                AND id_vendedor = :id_vendedor AND eliminado = false
              LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario, ':id_vendedor' => $idVendedor]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Serializa los cambios sobre la lista de un mismo usuario en una empresa
     * (§8): dos clics simultáneos sobre el mismo vendedor no crean dos filas.
     * Debe llamarse dentro de la transacción del llamador.
     */
    public function lock(int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('vendedores_visibles:' || :id_empresa || ':' || :id_usuario))");
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario]);
    }

    public function crear(int $idEmpresa, int $idUsuario, int $idVendedor, int $idActor): int
    {
        $st = $this->db->prepare(
            "INSERT INTO {$this->table} (id_empresa, id_usuario, id_vendedor, created_at, created_by, eliminado)
             VALUES (:id_empresa, :id_usuario, :id_vendedor, NOW(), :created_by, false)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa'  => $idEmpresa,
            ':id_usuario'  => $idUsuario,
            ':id_vendedor' => $idVendedor,
            ':created_by'  => $idActor,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Eliminación lógica de la asignación. */
    public function eliminar(int $id, int $idEmpresa, int $idActor): bool
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table}
                SET eliminado = true, deleted_at = NOW(), deleted_by = :deleted_by,
                    updated_at = NOW(), updated_by = :updated_by
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':deleted_by' => $idActor, ':updated_by' => $idActor, ':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->rowCount() > 0;
    }
}
