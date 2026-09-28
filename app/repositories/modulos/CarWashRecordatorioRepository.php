<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Recordatorios de la próxima cita de Car-Wash (tabla carwash_recordatorios) y las
 * citas que salen de las órdenes (carwash_ordenes.proxima_cita).
 *
 * Todas las consultas filtran por empresa, eliminado = false y el ambiente vigente de
 * la empresa (como el listado de Car-Wash). Las órdenes anuladas no generan citas.
 */
class CarWashRecordatorioRepository extends BaseRepository
{
    private const AMBIENTE = "COALESCE((SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = o.id_empresa), '1')";

    public function __construct()
    {
        parent::__construct('carwash_recordatorios');
    }

    /** ¿Ya existe la tabla? (el código no debe romperse si aún no se aplicó el SQL). */
    public function existeTabla(): bool
    {
        static $existe = false;
        if (!$existe) {
            $existe = (bool) $this->db->query("SELECT to_regclass('public.carwash_recordatorios') IS NOT NULL")->fetchColumn();
        }
        return $existe;
    }

    /** Columnas comunes de una cita (orden + cliente + vehículo) para armar el mensaje. */
    private function selectCita(): string
    {
        $ultimo = $this->existeTabla()
            ? "(SELECT MAX(r.created_at) FROM carwash_recordatorios r
                 WHERE r.id_orden = o.id AND r.fecha_cita = o.proxima_cita AND r.estado = 'enviado' AND r.eliminado = false)"
            : "NULL";
        return "o.id AS id_orden, o.id_empresa, o.numero_orden, o.proxima_cita, o.fecha_ingreso, o.placa, o.marca,
                o.id_vehiculo, o.id_cliente,
                c.nombre AS cliente_nombre, c.email AS cliente_email, c.telefono AS cliente_telefono,
                v.propietario AS vehiculo_propietario, v.correo AS vehiculo_correo, v.telefono AS vehiculo_telefono,
                v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo,
                $ultimo AS ultimo_envio";
    }

    /**
     * Citas del vehículo (una por orden con próxima cita), las más recientes primero.
     * Incluye las ya pasadas para ver el historial; la pantalla marca cuáles vencieron.
     */
    public function citasPorVehiculo(int $idVehiculo, int $idEmpresa, ?int $idUsuarioFiltro = null, int $limite = 30): array
    {
        $params = [':e' => $idEmpresa, ':v' => $idVehiculo];
        $extra  = '';
        if ($idUsuarioFiltro !== null) {
            $extra = ' AND o.created_by = :u';
            $params[':u'] = $idUsuarioFiltro;
        }
        $limite = max(1, min(100, $limite));
        $sql = "SELECT " . $this->selectCita() . "
                FROM carwash_ordenes o
                LEFT JOIN clientes c  ON c.id = o.id_cliente
                LEFT JOIN vehiculos v ON v.id = o.id_vehiculo
                WHERE o.id_empresa = :e AND o.id_vehiculo = :v AND o.eliminado = false AND o.estado <> 'anulado'
                  AND o.proxima_cita IS NOT NULL AND o.tipo_ambiente = " . self::AMBIENTE . $extra . "
                ORDER BY o.proxima_cita DESC, o.id DESC
                LIMIT $limite";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Una cita (orden) concreta de la empresa. */
    public function citaPorOrden(int $idOrden, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT " . $this->selectCita() . "
                FROM carwash_ordenes o
                LEFT JOIN clientes c  ON c.id = o.id_cliente
                LEFT JOIN vehiculos v ON v.id = o.id_vehiculo
                WHERE o.id = :id AND o.id_empresa = :e AND o.eliminado = false");
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Citas que vencen entre hoy y hoy + $dias y que aún no tienen recordatorio
     * AUTOMÁTICO enviado por ese canal (envío automático). Una por vehículo y fecha: si
     * dos órdenes del mismo vehículo apuntan a la misma cita, se avisa una sola vez.
     */
    public function citasPendientes(int $idEmpresa, int $dias, string $canal, ?int $idEstablecimiento = null): array
    {
        $params = [':e' => $idEmpresa, ':d' => max(0, $dias), ':c' => $canal];
        $extra  = '';
        if ($idEstablecimiento) {
            $extra = ' AND o.id_establecimiento = :est';
            $params[':est'] = $idEstablecimiento;
        }
        $sql = "SELECT DISTINCT ON (o.id_vehiculo, o.proxima_cita) " . $this->selectCita() . "
                FROM carwash_ordenes o
                LEFT JOIN clientes c  ON c.id = o.id_cliente
                LEFT JOIN vehiculos v ON v.id = o.id_vehiculo
                WHERE o.id_empresa = :e AND o.eliminado = false AND o.estado <> 'anulado'
                  AND o.proxima_cita BETWEEN CURRENT_DATE AND CURRENT_DATE + CAST(:d AS INTEGER)
                  AND o.tipo_ambiente = " . self::AMBIENTE . $extra . "
                  AND NOT EXISTS (SELECT 1 FROM carwash_recordatorios r
                                   WHERE r.id_empresa = o.id_empresa AND r.id_orden = o.id AND r.fecha_cita = o.proxima_cita
                                     AND r.canal = :c AND r.estado = 'enviado' AND r.origen = 'automatico' AND r.eliminado = false)
                ORDER BY o.id_vehiculo, o.proxima_cita, o.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Registra un recordatorio (enviado o con error). */
    public function registrar(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO carwash_recordatorios
                (id_empresa, id_orden, id_vehiculo, id_cliente, fecha_cita, canal, destinatario, asunto, mensaje, estado, detalle, origen, created_by, updated_by)
             VALUES (:e, :o, :v, :c, :f, :canal, :dest, :asunto, :msg, :estado, :det, :origen, :u, :u) RETURNING id"
        );
        $st->execute([
            ':e'      => $d['id_empresa'],
            ':o'      => $d['id_orden'],
            ':v'      => $d['id_vehiculo'] ?: null,
            ':c'      => $d['id_cliente'] ?: null,
            ':f'      => $d['fecha_cita'],
            ':canal'  => $d['canal'],
            ':dest'   => mb_substr((string) ($d['destinatario'] ?? ''), 0, 200),
            ':asunto' => mb_substr((string) ($d['asunto'] ?? ''), 0, 250) ?: null,
            ':msg'    => $d['mensaje'] ?? null,
            ':estado' => $d['estado'],
            ':det'    => $d['detalle'] ?? null,
            ':origen' => $d['origen'] ?? 'manual',
            ':u'      => $d['id_usuario'] ?: null,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Historial de recordatorios del vehículo (los más recientes primero). */
    public function historialPorVehiculo(int $idVehiculo, int $idEmpresa, int $limite = 50): array
    {
        if (!$this->existeTabla()) return [];
        $limite = max(1, min(200, $limite));
        $st = $this->db->prepare(
            "SELECT r.id, r.fecha_cita, r.canal, r.destinatario, r.estado, r.detalle, r.origen, r.created_at,
                    o.numero_orden, u.nombre AS usuario
               FROM carwash_recordatorios r
               JOIN carwash_ordenes o ON o.id = r.id_orden
          LEFT JOIN usuarios u ON u.id = r.created_by
              WHERE r.id_empresa = :e AND r.id_vehiculo = :v AND r.eliminado = false
              ORDER BY r.created_at DESC, r.id DESC
              LIMIT $limite"
        );
        $st->execute([':e' => $idEmpresa, ':v' => $idVehiculo]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
