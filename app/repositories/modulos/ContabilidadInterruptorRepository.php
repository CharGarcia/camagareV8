<?php
/**
 * Acceso a datos del interruptor por empresa "¿este módulo genera asientos?".
 *
 * Tabla: contabilidad_modulos_empresa (database/migrations/20260922_contabilidad_modulos_empresa.sql).
 * Sin fila = el módulo contabiliza. Solo se guardan filas cuando alguien toca el
 * interruptor, así que la ausencia de la tabla (SQL aún no aplicado en un
 * ambiente) equivale a "todo contabiliza" y no rompe nada.
 */

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ContabilidadInterruptorRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('contabilidad_modulos_empresa');
    }

    /**
     * Si la tabla no existe (SQL aún no aplicado) se responde "todo contabiliza" SIN lanzar la
     * consulta: un error de SQL dentro de una transacción abierta la dejaría abortada (25P02) y
     * tumbaría el guardado del documento que estaba contabilizándose. BaseRepository::tablaExiste()
     * usa to_regclass, que no lanza.
     */
    public function existeTabla(): bool
    {
        return $this->tablaExiste($this->table);
    }

    /** @return string[] Claves de módulo apagadas para la empresa. */
    public function getClavesApagadas(int $idEmpresa): array
    {
        if (!$this->existeTabla()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT modulo_clave
               FROM contabilidad_modulos_empresa
              WHERE id_empresa = :id_empresa AND eliminado = false AND contabiliza = false"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Fila viva de empresa+módulo, o null si nunca se tocó el interruptor. */
    public function getFila(int $idEmpresa, string $clave): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, modulo_clave, contabiliza, updated_at, updated_by
               FROM contabilidad_modulos_empresa
              WHERE id_empresa = :id_empresa AND modulo_clave = :clave AND eliminado = false
              LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':clave' => $clave]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Candado transaccional por empresa+módulo (§8): dos usuarios cambiando el mismo interruptor a
     * la vez no deben insertar dos filas ni pisarse el "antes" que queda en log_sistema.
     */
    public function lock(int $idEmpresa, string $clave): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('contab_interruptor:' || CAST(:e AS TEXT) || ':' || CAST(:c AS TEXT)))");
        $st->execute([':e' => $idEmpresa, ':c' => $clave]);
    }

    public function insertar(int $idEmpresa, string $clave, bool $contabiliza, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO contabilidad_modulos_empresa (id_empresa, modulo_clave, contabiliza, created_at, created_by, updated_at, updated_by)
             VALUES (:id_empresa, :clave, :contabiliza, NOW(), :u_crea, NOW(), :u_act)
             RETURNING id"
        );
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':clave', $clave);
        $st->bindValue(':contabiliza', $contabiliza, PDO::PARAM_BOOL);
        $st->bindValue(':u_crea', $idUsuario, PDO::PARAM_INT);
        $st->bindValue(':u_act', $idUsuario, PDO::PARAM_INT);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, int $idEmpresa, bool $contabiliza, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE contabilidad_modulos_empresa
                SET contabiliza = :contabiliza, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->bindValue(':contabiliza', $contabiliza, PDO::PARAM_BOOL);
        $st->bindValue(':u', $idUsuario, PDO::PARAM_INT);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->execute();
    }

    /**
     * ¿El documento ya tiene un asiento vivo? Mismo criterio que
     * AsientoContableRepository::getAsientoPorOrigen() (origen + ambiente de la empresa), pero sin
     * leer el asiento completo: solo interesa si existe.
     */
    public function tieneAsientoVivo(string $moduloOrigen, int $idReferencia, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1
               FROM asientos_contables_cabecera
              WHERE modulo_origen = :modulo AND id_referencia_origen = :id_ref
                AND id_empresa = :id_empresa AND eliminado = false AND estado <> 'anulado'
                AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_amb)
              LIMIT 1"
        );
        $st->execute([':modulo' => $moduloOrigen, ':id_ref' => $idReferencia, ':id_empresa' => $idEmpresa, ':id_empresa_amb' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Saldo (debe − haber) de una cuenta en los asientos vivos de la empresa, en su ambiente actual.
     * Se usa para avisar, al apagar Consignaciones, cuánto quedó en «Mercadería en Consignación».
     */
    public function getSaldoCuenta(int $idEmpresa, int $idCuenta): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(d.debe - d.haber), 0)
               FROM asientos_contables_detalle d
               JOIN asientos_contables_cabecera c ON c.id = d.id_asiento
              WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.estado <> 'anulado'
                AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_amb)
                AND d.eliminado = false AND d.id_cuenta_contable = :id_cuenta"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id_empresa_amb' => $idEmpresa, ':id_cuenta' => $idCuenta]);
        return round((float) $st->fetchColumn(), 2);
    }
}
