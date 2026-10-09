<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Datos de caja del Resumen Diario: traslados entre formas de pago (caja_traslados) y
 * saldos de apertura por forma de pago (caja_saldos_apertura). Tablas de
 * database/20261008_caja_saldos_traslados.sql. Toda consulta filtra id_empresa +
 * eliminado = false.
 */
class CajaMovimientoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('caja_traslados');
    }

    /** Formas de pago activas de la empresa (para los selectores). */
    public function getFormasPago(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre FROM empresa_formas_pago
              WHERE id_empresa = :e AND eliminado = false AND COALESCE(activo, true) = true
              ORDER BY COALESCE(orden, 9999), nombre"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿La forma de pago es de la empresa y no está eliminada? */
    public function formaEsDeEmpresa(int $idForma, int $idEmpresa): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM empresa_formas_pago WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $idForma, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    // ── Traslados ─────────────────────────────────────────────────────────────

    public function getTraslado(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM caja_traslados WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertarTraslado(array $d, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO caja_traslados (id_empresa, fecha, id_forma_origen, id_forma_destino, valor, observaciones, created_by, updated_by)
             VALUES (:e, :fecha, :origen, :destino, :valor, :obs, :u, :u)
             RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':fecha' => $d['fecha'], ':origen' => $d['id_forma_origen'],
            ':destino' => $d['id_forma_destino'], ':valor' => $d['valor'],
            ':obs' => $d['observaciones'] !== '' ? $d['observaciones'] : null, ':u' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function eliminarTraslado(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE caja_traslados SET eliminado = true, deleted_at = NOW(), deleted_by = :u, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    // ── Saldos de apertura ────────────────────────────────────────────────────

    /** Aperturas vigentes, una por forma de pago: id_forma => fila. */
    public function getAperturas(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, id_forma_pago, fecha, valor, COALESCE(observaciones, '') AS observaciones
               FROM caja_saldos_apertura WHERE id_empresa = :e AND eliminado = false"
        );
        $st->execute([':e' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_forma_pago']] = $r;
        }
        return $out;
    }

    public function insertarApertura(int $idEmpresa, int $idForma, string $fecha, float $valor, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO caja_saldos_apertura (id_empresa, id_forma_pago, fecha, valor, created_by, updated_by)
             VALUES (:e, :f, :fecha, :valor, :u, :u) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':f' => $idForma, ':fecha' => $fecha, ':valor' => $valor, ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function actualizarApertura(int $id, int $idEmpresa, string $fecha, float $valor, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE caja_saldos_apertura SET fecha = :fecha, valor = :valor, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':fecha' => $fecha, ':valor' => $valor, ':u' => $idUsuario]);
    }

    public function eliminarApertura(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE caja_saldos_apertura SET eliminado = true, deleted_at = NOW(), deleted_by = :u, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }
}
