<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CajaSesionRepository extends BaseRepository
{
    protected string $table = 'caja_sesiones';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getAbiertaPorPuntoEmision(int $idEmpresa, int $idPuntoEmision): ?array
    {
        $sql = "SELECT cs.*, u.nombre AS cajero_nombre
                FROM {$this->table} cs
                LEFT JOIN usuarios u ON u.id = cs.id_usuario
                WHERE cs.id_empresa = :id_empresa
                  AND cs.id_punto_emision = :id_punto_emision
                  AND cs.estado = 'abierta'
                  AND cs.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':id_punto_emision' => $idPuntoEmision,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cualquier turno abierto de la empresa, sin importar el punto de emisión
     * — usado por el portal público QR (modulos/comandas no exige que el
     * cliente sepa el punto de emisión, solo que el restaurante esté
     * operando; el punto de emisión real se resuelve normal al cobrar).
     */
    public function getAbiertaPorEmpresa(int $idEmpresa): ?array
    {
        $sql = "SELECT cs.*, u.nombre AS cajero_nombre
                FROM {$this->table} cs
                LEFT JOIN usuarios u ON u.id = cs.id_usuario
                WHERE cs.id_empresa = :id_empresa
                  AND cs.estado = 'abierta'
                  AND cs.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_punto_emision, id_usuario, fondo_inicial,
                    estado, created_by, eliminado
                ) VALUES (
                    :id_empresa, :id_punto_emision, :id_usuario, :fondo_inicial,
                    'abierta', :created_by, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_punto_emision' => $data['id_punto_emision'],
            ':id_usuario' => $data['id_usuario'],
            ':fondo_inicial' => $data['fondo_inicial'],
            ':created_by' => $data['created_by'],
        ]);
        return (int) $this->lastInsertId();
    }

    /**
     * Efectivo cobrado durante el turno (Facturas + Recibos del POS enlazados
     * a esta sesión, forma_pago = '01' = efectivo). Es la base del arqueo:
     * monto_esperado = fondo_inicial + este valor. Protegido: si la columna
     * id_caja_sesion aún no existe (migración pendiente), devuelve 0 en vez
     * de romper el cierre de caja.
     */
    public function getEfectivoCobradoEnTurno(int $idCajaSesion): float
    {
        try {
            $stF = $this->db->prepare(
                "SELECT COALESCE(SUM(vp.total), 0)
                 FROM ventas_pagos vp
                 JOIN ventas_cabecera v ON v.id = vp.id_venta
                 WHERE v.id_caja_sesion = :id AND v.eliminado = false
                   AND v.estado != 'anulado' AND vp.forma_pago = '01'"
            );
            $stF->execute([':id' => $idCajaSesion]);
            $totalFacturas = (float) $stF->fetchColumn();

            $stR = $this->db->prepare(
                "SELECT COALESCE(SUM(rp.total), 0)
                 FROM recibos_venta_pagos rp
                 JOIN recibos_venta_cabecera r ON r.id = rp.id_recibo
                 WHERE r.id_caja_sesion = :id AND r.eliminado = false
                   AND r.estado != 'anulado' AND rp.forma_pago = '01'"
            );
            $stR->execute([':id' => $idCajaSesion]);
            $totalRecibos = (float) $stR->fetchColumn();

            return round($totalFacturas + $totalRecibos, 2);
        } catch (\Throwable $e) {
            error_log('[CajaSesion] No se pudo calcular el efectivo cobrado del turno (¿migración id_caja_sesion pendiente?): ' . $e->getMessage());
            return 0.0;
        }
    }

    public function cerrar(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    estado = 'cerrada',
                    fecha_cierre = CURRENT_TIMESTAMP,
                    monto_esperado = :monto_esperado,
                    monto_contado = :monto_contado,
                    diferencia = :diferencia,
                    observaciones_cierre = :observaciones_cierre,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false AND estado = 'abierta'";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':monto_esperado' => $data['monto_esperado'],
            ':monto_contado' => $data['monto_contado'],
            ':diferencia' => $data['diferencia'],
            ':observaciones_cierre' => $data['observaciones_cierre'],
            ':updated_by' => $data['updated_by'],
            ':id' => $id,
            ':id_empresa' => $idEmpresa,
        ]);
    }
}
