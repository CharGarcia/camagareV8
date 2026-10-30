<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Recordatorios de la próxima cita de Car-Wash (tabla carwash_recordatorios) y las
 * citas que salen de las órdenes (carwash_ordenes.proxima_cita) o de los recordatorios
 * agregados a mano en la ficha del vehículo (vehiculos_recordatorios).
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

    /**
     * ¿Ya se aplicó el SQL de recordatorios agregados a mano (vehiculos_recordatorios +
     * carwash_recordatorios.id_recordatorio)? Sin él, solo existen las citas de las órdenes.
     */
    public function existeManual(): bool
    {
        static $existe = false;
        if (!$existe && $this->existeTabla()) {
            $existe = (bool) $this->db->query(
                "SELECT 1 FROM information_schema.columns
                  WHERE table_schema = 'public' AND table_name = 'carwash_recordatorios' AND column_name = 'id_recordatorio'"
            )->fetchColumn();
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
        return "o.id AS id_orden, NULL AS id_recordatorio, NULL AS motivo, o.id_empresa, o.numero_orden, o.proxima_cita, o.fecha_ingreso, o.placa, o.marca,
                o.id_vehiculo, o.id_cliente,
                c.nombre AS cliente_nombre, c.email AS cliente_email, c.telefono AS cliente_telefono,
                v.propietario AS vehiculo_propietario, v.correo AS vehiculo_correo, v.telefono AS vehiculo_telefono,
                v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo,
                $ultimo AS ultimo_envio";
    }

    /**
     * Cita de un recordatorio agregado a mano, con las mismas columnas que la de una orden.
     * El cliente es el de la última orden de Car-Wash del vehículo (si tiene), para tener
     * a quién saludar y un correo/teléfono de respaldo.
     */
    private function sqlCitaManual(string $where): string
    {
        return "SELECT NULL AS id_orden, r.id AS id_recordatorio, r.motivo, r.id_empresa, NULL AS numero_orden,
                       r.fecha AS proxima_cita, NULL AS fecha_ingreso, v.placa, v.marca,
                       r.id_vehiculo, uo.id_cliente,
                       c.nombre AS cliente_nombre, c.email AS cliente_email, c.telefono AS cliente_telefono,
                       v.propietario AS vehiculo_propietario, v.correo AS vehiculo_correo, v.telefono AS vehiculo_telefono,
                       v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo, r.observacion,
                       (SELECT MAX(x.created_at) FROM carwash_recordatorios x
                         WHERE x.id_recordatorio = r.id AND x.fecha_cita = r.fecha AND x.estado = 'enviado' AND x.eliminado = false) AS ultimo_envio
                  FROM vehiculos_recordatorios r
                  JOIN vehiculos v ON v.id = r.id_vehiculo
             LEFT JOIN LATERAL (SELECT o.id_cliente FROM carwash_ordenes o
                                 WHERE o.id_empresa = r.id_empresa AND o.id_vehiculo = r.id_vehiculo
                                   AND o.eliminado = false AND o.id_cliente IS NOT NULL
                                 ORDER BY o.fecha_ingreso DESC, o.id DESC LIMIT 1) uo ON true
             LEFT JOIN clientes c ON c.id = uo.id_cliente
                 WHERE r.id_empresa = :e AND r.eliminado = false AND $where";
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
        $citas = $st->fetchAll(PDO::FETCH_ASSOC);

        // Recordatorios agregados a mano, mezclados con las citas de las órdenes por fecha.
        if ($this->existeManual()) {
            $where = 'r.id_vehiculo = :v' . ($idUsuarioFiltro !== null ? ' AND r.created_by = :u' : '');
            $st = $this->db->prepare($this->sqlCitaManual($where) . " ORDER BY r.fecha DESC, r.id DESC LIMIT $limite");
            $st->execute($params);
            $citas = array_merge($citas, $st->fetchAll(PDO::FETCH_ASSOC));
            usort($citas, fn($a, $b) => strcmp((string) $b['proxima_cita'], (string) $a['proxima_cita']));
        }
        return $citas;
    }

    /** Un recordatorio agregado a mano, como cita. */
    public function citaPorRecordatorio(int $idRecordatorio, int $idEmpresa): ?array
    {
        if (!$this->existeManual()) return null;
        $st = $this->db->prepare($this->sqlCitaManual('r.id = :id'));
        $st->execute([':id' => $idRecordatorio, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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
        $citas = $st->fetchAll(PDO::FETCH_ASSOC);

        // Recordatorios agregados a mano: no dependen de un establecimiento, entran siempre.
        if ($this->existeManual()) {
            $st = $this->db->prepare($this->sqlCitaManual(
                "r.fecha BETWEEN CURRENT_DATE AND CURRENT_DATE + CAST(:d AS INTEGER)
                 AND NOT EXISTS (SELECT 1 FROM carwash_recordatorios x
                                  WHERE x.id_empresa = r.id_empresa AND x.id_recordatorio = r.id AND x.fecha_cita = r.fecha
                                    AND x.canal = :c AND x.estado = 'enviado' AND x.origen = 'automatico' AND x.eliminado = false)"
            ) . ' ORDER BY r.fecha, r.id');
            $st->execute([':e' => $idEmpresa, ':d' => max(0, $dias), ':c' => $canal]);
            $citas = array_merge($citas, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        return $citas;
    }

    /** Registra un recordatorio (enviado o con error). */
    public function registrar(array $d): int
    {
        $manual = $this->existeManual();
        $st = $this->db->prepare(
            "INSERT INTO carwash_recordatorios
                (id_empresa, id_orden, " . ($manual ? 'id_recordatorio, ' : '') . "id_vehiculo, id_cliente, fecha_cita, canal, destinatario, asunto, mensaje, estado, detalle, origen, created_by, updated_by)
             VALUES (:e, :o, " . ($manual ? ':r, ' : '') . ":v, :c, :f, :canal, :dest, :asunto, :msg, :estado, :det, :origen, :u, :u) RETURNING id"
        );
        $params = [
            ':e'      => $d['id_empresa'],
            ':o'      => $d['id_orden'] ?: null,
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
        ];
        if ($manual) $params[':r'] = ($d['id_recordatorio'] ?? 0) ?: null;
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    /** Historial de recordatorios del vehículo (los más recientes primero). */
    public function historialPorVehiculo(int $idVehiculo, int $idEmpresa, int $limite = 50): array
    {
        if (!$this->existeTabla()) return [];
        $limite = max(1, min(200, $limite));
        $manual = $this->existeManual();
        $st = $this->db->prepare(
            "SELECT r.id, r.fecha_cita, r.canal, r.destinatario, r.estado, r.detalle, r.origen, r.created_at,
                    o.numero_orden, " . ($manual ? 'vr.motivo' : 'NULL') . " AS motivo, u.nombre AS usuario
               FROM carwash_recordatorios r
          LEFT JOIN carwash_ordenes o ON o.id = r.id_orden
          " . ($manual ? 'LEFT JOIN vehiculos_recordatorios vr ON vr.id = r.id_recordatorio' : '') . "
          LEFT JOIN usuarios u ON u.id = r.created_by
              WHERE r.id_empresa = :e AND r.id_vehiculo = :v AND r.eliminado = false
              " . ($this->columnaExiste('carwash_recordatorios', 'id_vehiculo_mantenimiento')
                    ? 'AND r.id_vehiculo_mantenimiento IS NULL' : '') /* los avisos de mantenimiento van en su pestaña */ . "
              ORDER BY r.created_at DESC, r.id DESC
              LIMIT $limite"
        );
        $st->execute([':e' => $idEmpresa, ':v' => $idVehiculo]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
