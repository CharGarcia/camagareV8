<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Cargas masivas de Novedades (una fila por importación de plantilla Excel).
 *
 * Sirve para poder REVERTIR una carga completa: cada novedad importada guarda
 * el `id_carga` que la originó. Resiliente: si el SQL
 * (`database/novedades_cargas.sql`) aún no está aplicado, `disponible()`
 * devuelve false y la importación sigue funcionando sin registro de carga.
 */
class NovedadCargaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('novedades_cargas');
    }

    /** ¿Está desplegada la tabla de cargas? (cacheado por proceso). */
    public function disponible(): bool
    {
        return $this->tablaExiste('novedades_cargas');
    }

    public function crear(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, archivo, total_filas, creadas, errores, estado, observacion,
                    tipo_ambiente, created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :archivo, :total, :creadas, :errores, 'activo', :observacion,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'  => (int) $d['id_empresa'],
            ':archivo'     => mb_substr((string) ($d['archivo'] ?? ''), 0, 255),
            ':total'       => (int) ($d['total_filas'] ?? 0),
            ':creadas'     => (int) ($d['creadas'] ?? 0),
            ':errores'     => (int) ($d['errores'] ?? 0),
            ':observacion' => $d['observacion'] ?? null,
            ':id_u'        => (int) $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    /** Cierra la carga con el resultado real del procesamiento. */
    public function actualizarResumen(int $id, int $idEmpresa, int $total, int $creadas, int $errores): bool
    {
        $sql = "UPDATE {$this->table}
                   SET total_filas = :total, creadas = :creadas, errores = :errores,
                       updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':total' => $total, ':creadas' => $creadas, ':errores' => $errores,
            ':id' => $id, ':id_empresa' => $idEmpresa,
        ]);
    }

    /**
     * Cargas de la empresa (ambiente actual) con el número de novedades que
     * siguen vigentes. Solo se listan las cargas que dejaron algo creado.
     */
    public function getListado(int $idEmpresa, int $limite = 50, ?int $idUsuarioFiltro = null): array
    {
        if (!$this->disponible()) {
            return [];
        }

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $where .= " AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        // Se ocultan las cargas que no dejaron nada (todas sus filas fallaron). El
        // EXISTS cubre además una importación interrumpida antes de guardar el
        // resumen: si dejó novedades, debe poder revertirse.
        $where .= " AND (c.creadas > 0 OR EXISTS (SELECT 1 FROM novedades n2
                            WHERE n2.id_carga = c.id AND n2.eliminado = false))";

        $sql = "SELECT c.*, u.nombre AS usuario_nombre,
                       (SELECT COUNT(*) FROM novedades n
                         WHERE n.id_carga = c.id AND n.eliminado = false) AS vigentes
                  FROM {$this->table} c
                  LEFT JOIN usuarios u ON u.id = c.created_by
                {$where}
                 ORDER BY c.id DESC
                 LIMIT " . max(1, $limite);
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        if (!$this->disponible()) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT c.*, u.nombre AS usuario_nombre
               FROM {$this->table} c
               LEFT JOIN usuarios u ON u.id = c.created_by
              WHERE c.id = :id AND c.id_empresa = :id_empresa AND c.eliminado = false"
        );
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Marca la carga como revertida (eliminación lógica): sus novedades ya
     * fueron eliminadas lógicamente por NovedadRepository::deleteLogicPorCarga.
     */
    public function marcarRevertida(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table}
                   SET estado = 'revertido', eliminado = true,
                       deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP,
                       updated_by = :id_u, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }
}
