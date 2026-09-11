<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;

class SriEnvioLog
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Inserta un registro de log SRI. */
    public function registrar(array $data): void
    {
        $st = $this->db->prepare(
            "INSERT INTO sri_envio_log
                (id_empresa, tipo_comprobante, id_comprobante, clave_acceso, tipo_ambiente,
                 accion, estado_sri, mensaje, detalle_json, numero_autorizacion, fecha_autorizacion, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $data['id_empresa'],
            $data['tipo_comprobante']    ?? 'factura_venta',
            $data['id_comprobante'],
            $data['clave_acceso']        ?? null,
            $data['tipo_ambiente']       ?? '1',
            $data['accion'],
            $data['estado_sri']          ?? null,
            $data['mensaje']             ?? null,
            $data['detalle_json']        ?? null,
            $data['numero_autorizacion'] ?? null,
            $data['fecha_autorizacion']  ?? null,
            $data['created_by']          ?? 0,
        ]);

        // Invalidar la caché de contadores del navbar: cualquier acción SRI cambia
        // las "novedades" (y a veces los borradores). Nunca debe romper el registro.
        try {
            \App\Services\ContadoresNavbarService::invalidarPorTabla('sri_envio_log', (int) ($data['id_empresa'] ?? 0));
        } catch (\Throwable $e) {
            // Silencioso a propósito.
        }
    }

    /** Devuelve todos los logs de un comprobante, ordenados del más reciente al más antiguo. */
    public function getPorComprobante(string $tipo, int $idComprobante, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, id_empresa, tipo_comprobante, id_comprobante, clave_acceso, tipo_ambiente,
                    accion, estado_sri, mensaje, detalle_json, numero_autorizacion,
                    fecha_autorizacion, created_by,
                    TO_CHAR(created_at, 'DD-MM-YYYY HH24:MI:SS') AS created_at
             FROM sri_envio_log
             WHERE tipo_comprobante = ? AND id_comprobante = ? AND id_empresa = ?
             ORDER BY id DESC"
        );
        $st->execute([$tipo, $idComprobante, $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * ¿El SRI tiene este comprobante recibido, en cola o autorizado? Si es así,
     * devuelve la fila del log que lo prueba (clave_acceso, tipo_ambiente,
     * accion); si no, null.
     *
     * Se usa para impedir eliminar un documento que el SRI ya tiene: su
     * secuencial quedó registrado en ese ambiente con esa clave, y borrar la
     * copia local solo esconde el problema (el número no puede volver a
     * usarse; ver SecuencialRepository::getSiguienteDisponible()).
     *
     * Se recorre el historial del más reciente al más antiguo: la primera
     * acción concluyente decide. 'no_autorizado' y 'devuelta' significan que el
     * SRI NO retuvo esa clave (se puede eliminar); 'recibida',
     * 'en_procesamiento' y 'autorizado/a' significan que sí. 'enviando',
     * 'error' y 'aviso_email' no dicen nada por sí solas y se saltan.
     */
    public function getRegistroRetenidoPorSri(string $tipo, int $idComprobante): ?array
    {
        $st = $this->db->prepare(
            "SELECT accion, clave_acceso, tipo_ambiente, created_at
             FROM sri_envio_log
             WHERE tipo_comprobante = ? AND id_comprobante = ?
             ORDER BY id DESC"
        );
        $st->execute([$tipo, $idComprobante]);

        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $accion = strtolower((string) ($row['accion'] ?? ''));
            // 'no_autorizado' (factura, NC, ND…) y 'no_autorizada' (retención): el
            // estado interno de cada flujo va en el género del documento.
            if (str_starts_with($accion, 'no_autorizad') || $accion === 'devuelta') {
                return null;
            }
            if ($accion === 'recibida' || $accion === 'en_procesamiento' || str_starts_with($accion, 'autoriz')) {
                return $row;
            }
        }
        return null;
    }

    /** Devuelve un registro por ID validando la empresa. */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM sri_envio_log WHERE id = ? AND id_empresa = ?"
        );
        $st->execute([$id, $idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Elimina un log físicamente.
     * Solo se permite para tipo_ambiente = '1' (pruebas).
     * El controlador valida el ambiente antes de llamar a este método.
     */
    public function eliminar(int $id, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "DELETE FROM sri_envio_log WHERE id = ? AND id_empresa = ? AND tipo_ambiente = '1'"
        );
        $st->execute([$id, $idEmpresa]);
        return $st->rowCount() > 0;
    }
}
