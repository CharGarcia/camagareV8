<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Vendedores cuya información un usuario NO ve en un módulo (tabla
 * `usuarios_vendedores_ocultos`, ver database/2026-10-06_usuarios_vendedores_ocultos.sql).
 * Se administran en /config/permisos-modulos. El catálogo de módulos que lo
 * admiten es App\Helpers\VendedoresModulo.
 *
 * Mientras no se ejecute el SQL, la tabla no existe: las lecturas devuelven
 * vacío (el usuario ve a todos, como antes) en vez de romper.
 */
class VendedorOcultoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('usuarios_vendedores_ocultos');
    }

    /** ¿Ya se ejecutó el SQL que crea la tabla? (cacheado por BaseRepository). */
    public function disponible(): bool
    {
        return $this->tablaExiste($this->table);
    }

    /**
     * Ids de los vendedores ocultos al usuario en ese módulo y empresa. Solo
     * vendedores vigentes (no eliminados) de esa misma empresa.
     *
     * @return int[]
     */
    public function getIdsOcultos(int $idEmpresa, int $idUsuario, string $modulo): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT uvo.id_vendedor
               FROM {$this->table} uvo
               JOIN vendedores v ON v.id = uvo.id_vendedor
                                AND v.id_empresa = uvo.id_empresa
                                AND v.eliminado = false
              WHERE uvo.id_empresa = :id_empresa
                AND uvo.id_usuario = :id_usuario
                AND uvo.modulo = :modulo
                AND uvo.eliminado = false"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario, ':modulo' => $modulo]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Ids de todos los vendedores vigentes de la empresa (activos e inactivos: un
     * inactivo puede tener ventas en el período consultado).
     *
     * @return int[]
     */
    public function getIdsVendedoresEmpresa(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id FROM vendedores WHERE id_empresa = :id_empresa AND eliminado = false ORDER BY id"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Vendedores de la empresa para la tarjeta de configuración, cada uno con la
     * marca `visible` (false si el usuario lo tiene oculto en ese módulo). Incluye
     * inactivos para que una ocultación vieja no quede escondida.
     */
    public function getVendedoresConMarca(int $idEmpresa, int $idUsuario, string $modulo): array
    {
        $oculto = $this->disponible()
            ? "EXISTS (SELECT 1 FROM {$this->table} uvo
                        WHERE uvo.id_empresa = v.id_empresa AND uvo.id_usuario = :id_usuario
                          AND uvo.modulo = :modulo AND uvo.id_vendedor = v.id AND uvo.eliminado = false)"
            : 'false';
        $params = [':id_empresa' => $idEmpresa];
        if ($this->disponible()) {
            $params[':id_usuario'] = $idUsuario;
            $params[':modulo']     = $modulo;
        }
        $st = $this->db->prepare(
            "SELECT v.id, v.nombre, v.identificacion,
                    (COALESCE(v.status, 0) = 1) AS activo,
                    NOT ({$oculto}) AS visible
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

    /** Fila vigente de ocultación, o null si el vendedor está visible. */
    public function getOcultacion(int $idEmpresa, int $idUsuario, string $modulo, int $idVendedor): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM {$this->table}
              WHERE id_empresa = :id_empresa AND id_usuario = :id_usuario
                AND modulo = :modulo AND id_vendedor = :id_vendedor AND eliminado = false
              LIMIT 1"
        );
        $st->execute([
            ':id_empresa'  => $idEmpresa,
            ':id_usuario'  => $idUsuario,
            ':modulo'      => $modulo,
            ':id_vendedor' => $idVendedor,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Serializa los cambios sobre la lista de un mismo usuario, empresa y módulo
     * (§8): dos clics simultáneos no crean dos filas ni dejan ocultos a todos.
     * Debe llamarse dentro de la transacción del llamador.
     */
    public function lock(int $idEmpresa, int $idUsuario, string $modulo): void
    {
        $st = $this->db->prepare(
            "SELECT pg_advisory_xact_lock(hashtext('vendedores_ocultos:' || :id_empresa || ':' || :id_usuario || ':' || :modulo))"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario, ':modulo' => $modulo]);
    }

    public function crear(int $idEmpresa, int $idUsuario, string $modulo, int $idVendedor, int $idActor): int
    {
        $st = $this->db->prepare(
            "INSERT INTO {$this->table} (id_empresa, id_usuario, modulo, id_vendedor, created_at, created_by, eliminado)
             VALUES (:id_empresa, :id_usuario, :modulo, :id_vendedor, NOW(), :created_by, false)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa'  => $idEmpresa,
            ':id_usuario'  => $idUsuario,
            ':modulo'      => $modulo,
            ':id_vendedor' => $idVendedor,
            ':created_by'  => $idActor,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Eliminación lógica de la ocultación (el vendedor vuelve a verse). */
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
