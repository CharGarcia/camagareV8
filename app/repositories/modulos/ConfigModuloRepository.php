<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Personalización de módulos por empresa y por cliente (tabla `config_modulos`,
 * database/2026-11-24_config_modulos.sql). El catálogo de opciones es
 * App\Helpers\ConfigModulo.
 *
 *  - Fila de la empresa: id_cliente IS NULL.
 *  - Fila de un cliente: id_cliente = el cliente (excepción).
 *
 * Mientras no se ejecute el SQL la tabla no existe: las lecturas devuelven vacío
 * (valores por defecto del catálogo) en vez de romper.
 */
class ConfigModuloRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('config_modulos');
    }

    /** ¿Ya se ejecutó el SQL que crea la tabla? (cacheado por BaseRepository). */
    public function disponible(): bool
    {
        return $this->tablaExiste($this->table);
    }

    /** Fila vigente de la empresa para el módulo, o null. */
    public function getFilaEmpresa(int $idEmpresa, string $modulo): ?array
    {
        if (!$this->disponible()) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT * FROM {$this->table}
              WHERE id_empresa = :id_empresa AND modulo = :modulo
                AND id_cliente IS NULL AND eliminado = false
              LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':modulo' => $modulo]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decodificar($row) : null;
    }

    /** Fila vigente de un cliente para el módulo, o null. */
    public function getFilaCliente(int $idEmpresa, string $modulo, int $idCliente): ?array
    {
        if (!$this->disponible() || $idCliente <= 0) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT * FROM {$this->table}
              WHERE id_empresa = :id_empresa AND modulo = :modulo
                AND id_cliente = :id_cliente AND eliminado = false
              LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':modulo' => $modulo, ':id_cliente' => $idCliente]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decodificar($row) : null;
    }

    /**
     * Filas vigentes de clientes del módulo, con el nombre e identificación del
     * cliente (para la lista de la pestaña). Solo clientes no eliminados.
     */
    public function getFilasClientes(int $idEmpresa, string $modulo): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT cm.*, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion
               FROM {$this->table} cm
               JOIN clientes c ON c.id = cm.id_cliente
                              AND c.id_empresa = cm.id_empresa
                              AND c.eliminado = false
              WHERE cm.id_empresa = :id_empresa AND cm.modulo = :modulo
                AND cm.id_cliente IS NOT NULL AND cm.eliminado = false
              ORDER BY c.nombre"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':modulo' => $modulo]);
        return array_map([$this, 'decodificar'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * De los ids recibidos, los que son clientes vigentes de la empresa.
     *
     * @param int[] $ids
     * @return int[]
     */
    public function clientesValidos(int $idEmpresa, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (!$ids) {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $ph = [];
        foreach ($ids as $i => $id) {
            $ph[] = ":c{$i}";
            $params[":c{$i}"] = $id;
        }
        $st = $this->db->prepare(
            "SELECT id FROM clientes
              WHERE id_empresa = :id_empresa AND eliminado = false
                AND id IN (" . implode(',', $ph) . ")"
        );
        $st->execute($params);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Serializa los cambios de configuración de un mismo módulo y empresa (§8):
     * dos guardados simultáneos no crean filas duplicadas ni se pisan. Debe
     * llamarse dentro de la transacción del llamador.
     */
    public function lock(int $idEmpresa, string $modulo): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('config_modulos:' || :id_empresa || ':' || :modulo))");
        $st->execute([':id_empresa' => $idEmpresa, ':modulo' => $modulo]);
    }

    public function crear(int $idEmpresa, string $modulo, ?int $idCliente, array $opciones, int $idActor): int
    {
        $st = $this->db->prepare(
            "INSERT INTO {$this->table} (id_empresa, modulo, id_cliente, opciones, created_at, created_by, eliminado)
             VALUES (:id_empresa, :modulo, :id_cliente, CAST(:opciones AS jsonb), NOW(), :created_by, false)
             RETURNING id"
        );
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':modulo', $modulo);
        $st->bindValue(':id_cliente', $idCliente, $idCliente === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':opciones', $this->json($opciones));
        $st->bindValue(':created_by', $idActor, PDO::PARAM_INT);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, int $idEmpresa, array $opciones, int $idActor): bool
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table}
                SET opciones = CAST(:opciones AS jsonb), updated_at = NOW(), updated_by = :updated_by
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([
            ':opciones'   => $this->json($opciones),
            ':updated_by' => $idActor,
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    /** Eliminación lógica (el cliente vuelve a seguir la configuración general). */
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

    private function decodificar(array $row): array
    {
        $op = json_decode((string) ($row['opciones'] ?? '{}'), true);
        $row['opciones'] = is_array($op) ? $op : [];
        return $row;
    }

    private function json(array $opciones): string
    {
        // Objeto JSON aun sin claves ({} y no []).
        return json_encode((object) $opciones, JSON_UNESCAPED_UNICODE);
    }
}
