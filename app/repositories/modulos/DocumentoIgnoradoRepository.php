<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use PDO;

/**
 * «Claves a no registrar» (Descargas SRI): claves de acceso que la descarga
 * del SRI (manual, automática o carga de XML) no debe registrar.
 *
 * Alcance del bloqueo (columna solo_establecimiento, database/2026-11-15_documentos_ignorados_sri_alcance.sql):
 *  - false → bloquea en TODOS los establecimientos del mismo RUC (anulado en el SRI,
 *            emitido por error).
 *  - true  → bloquea solo en el establecimiento (id_empresa) que lo registró: el
 *            documento se eliminó aquí para registrarlo en otro establecimiento.
 * Sin la columna (SQL aún no aplicado) todo se comporta como antes: alcance RUC.
 */
class DocumentoIgnoradoRepository
{
    public const MOTIVO_ANULADO_SRI          = 'ANULADO_SRI';
    public const MOTIVO_OTRO_ESTABLECIMIENTO = 'OTRO_ESTABLECIMIENTO';

    private PDO $db;
    private static ?bool $tieneAlcance = null;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** ¿Ya existen las columnas solo_establecimiento / motivo? */
    public function tieneAlcance(): bool
    {
        if (self::$tieneAlcance === null) {
            $st = $this->db->query(
                "SELECT 1 FROM information_schema.columns
                  WHERE table_schema = 'public' AND table_name = 'documentos_ignorados_sri'
                    AND column_name = 'solo_establecimiento' LIMIT 1"
            );
            self::$tieneAlcance = (bool) $st->fetchColumn();
        }
        return self::$tieneAlcance;
    }

    private function columnasAlcance(): string
    {
        return $this->tieneAlcance()
            ? 'd.solo_establecimiento, d.motivo'
            : 'false AS solo_establecimiento, NULL::varchar AS motivo';
    }

    public function getListado(int $idEmpresa): array
    {
        return $this->getListadoGrupo([$idEmpresa]);
    }

    /** Listado del grupo de empresas que comparten RUC, con el establecimiento que lo registró. */
    public function getListadoGrupo(array $idsEmpresa): array
    {
        if (empty($idsEmpresa)) return [];
        $placeholders = implode(',', array_fill(0, count($idsEmpresa), '?'));
        $sql = "SELECT d.id, d.id_empresa, d.clave_acceso, d.nombre_proveedor, d.fecha_documento,
                       d.observaciones, d.created_at, {$this->columnasAlcance()},
                       TRIM(COALESCE(e.establecimiento, '') || ' - ' ||
                            COALESCE(NULLIF(TRIM(e.nombre_comercial), ''), e.nombre, '')) AS empresa_nombre
                FROM documentos_ignorados_sri d
                LEFT JOIN empresas e ON e.id = d.id_empresa
                WHERE d.id_empresa IN ($placeholders) AND d.eliminado = false
                ORDER BY d.created_at DESC";
        $st = $this->db->prepare($sql);
        $st->execute(array_values($idsEmpresa));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Fila vigente de esta clave en esta empresa (o null). */
    public function getVigente(string $clave, int $idEmpresa): ?array
    {
        $sql = "SELECT d.id, d.id_empresa, d.clave_acceso, d.observaciones, {$this->columnasAlcance()}
                FROM documentos_ignorados_sri d
                WHERE d.clave_acceso = :ca AND d.id_empresa = :ie AND d.eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':ca' => $clave, ':ie' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function insertar(array $data): int
    {
        $soloEst = !empty($data['solo_establecimiento']);
        if ($this->tieneAlcance()) {
            $sql = "INSERT INTO documentos_ignorados_sri
                        (id_empresa, clave_acceso, nombre_proveedor, fecha_documento, observaciones,
                         solo_establecimiento, motivo, created_by, updated_by)
                    VALUES (:ie, :ca, :np, :fd, :obs, :se, :mo, :cb, :cb)
                    RETURNING id";
            $params = [
                ':se' => $soloEst ? 'true' : 'false',
                ':mo' => $data['motivo'] ?? null,
            ];
        } else {
            $sql = "INSERT INTO documentos_ignorados_sri
                        (id_empresa, clave_acceso, nombre_proveedor, fecha_documento, observaciones, created_by, updated_by)
                    VALUES (:ie, :ca, :np, :fd, :obs, :cb, :cb)
                    RETURNING id";
            $params = [];
        }
        $st = $this->db->prepare($sql);
        $st->execute($params + [
            ':ie'  => $data['id_empresa'],
            ':ca'  => $data['clave_acceso'],
            ':np'  => ($data['nombre_proveedor'] ?? '') !== '' ? mb_substr((string) $data['nombre_proveedor'], 0, 300) : null,
            ':fd'  => ($data['fecha_documento'] ?? '') !== '' ? $data['fecha_documento'] : null,
            ':obs' => ($data['observaciones'] ?? '') !== '' ? $data['observaciones'] : null,
            ':cb'  => $data['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    /** Amplía una fila de «solo este establecimiento» a todo el RUC. */
    public function ampliarATodoElRuc(int $id, string $motivo, ?string $observaciones, int $idUsuario): void
    {
        if (!$this->tieneAlcance()) return;
        $sql = "UPDATE documentos_ignorados_sri SET
                    solo_establecimiento = false,
                    motivo = :mo,
                    observaciones = COALESCE(:obs, observaciones),
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by = :ub
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':mo' => $motivo, ':obs' => $observaciones, ':ub' => $idUsuario, ':id' => $id]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        return $this->eliminarEnGrupo($id, [$idEmpresa], $idUsuario);
    }

    /**
     * Quita de la lista una fila de cualquier empresa del grupo RUC (el listado se
     * muestra a nivel de grupo).
     */
    public function eliminarEnGrupo(int $id, array $idsEmpresa, int $idUsuario): bool
    {
        if (empty($idsEmpresa)) return false;
        $placeholders = implode(',', array_fill(0, count($idsEmpresa), '?'));
        $sql = "UPDATE documentos_ignorados_sri SET
                    eliminado = true,
                    deleted_at = CURRENT_TIMESTAMP,
                    deleted_by = ?
                WHERE id = ? AND id_empresa IN ($placeholders) AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute(array_merge([$idUsuario, $id], array_values($idsEmpresa)));
        return $st->rowCount() > 0;
    }

    /** ¿Está bloqueada para esta empresa? (filas propias de cualquier alcance). */
    public function existeClave(string $clave, int $idEmpresa): bool
    {
        return $this->existeClaveEnGrupo($clave, [$idEmpresa], $idEmpresa);
    }

    /**
     * ¿La descarga debe saltar esta clave en $idEmpresaActual?
     * Sí, si alguna empresa del grupo RUC la bloqueó para todo el RUC, o si la propia
     * empresa la bloqueó solo para ella. El bloqueo «solo establecimiento» de un
     * hermano NO aplica aquí (es justamente para poder registrarla en otro).
     * Sin $idEmpresaActual se considera cualquier fila del grupo (comportamiento anterior).
     */
    public function existeClaveEnGrupo(string $clave, array $idsEmpresa, ?int $idEmpresaActual = null): bool
    {
        if (empty($idsEmpresa)) return false;
        $placeholders = implode(',', array_fill(0, count($idsEmpresa), '?'));
        $params = array_merge([$clave], array_values($idsEmpresa));
        $alcance = '';
        if ($idEmpresaActual !== null && $this->tieneAlcance()) {
            $alcance = ' AND (solo_establecimiento = false OR id_empresa = ?)';
            $params[] = $idEmpresaActual;
        }
        $sql = "SELECT 1 FROM documentos_ignorados_sri
                WHERE clave_acceso = ? AND id_empresa IN ($placeholders) AND eliminado = false{$alcance}
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }
}
