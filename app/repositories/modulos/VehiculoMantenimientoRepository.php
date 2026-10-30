<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Control de mantenimientos por kilometraje (aceite de motor, aceite de caja, etc.).
 *
 *  - mantenimiento_servicios: catálogo de la empresa (cada cuántos km, cuántos km antes avisar).
 *  - vehiculos_mantenimientos: por vehículo y servicio, su intervalo propio y la copia del
 *    último / próximo cambio (se recalcula desde el historial, ver recalcularEstado()).
 *  - vehiculos_mantenimientos_historial: cada cambio hecho, desde una orden de Car-Wash o a mano.
 *
 * Todo filtra por id_empresa y eliminado = false.
 */
class VehiculoMantenimientoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('vehiculos_mantenimientos');
    }

    /** ¿Ya se aplicó el SQL 20261030_vehiculos_mantenimientos.sql? */
    public function disponible(): bool
    {
        static $existe = false;
        if (!$existe) {
            $existe = (bool) $this->db->query("SELECT to_regclass('public.vehiculos_mantenimientos_historial') IS NOT NULL")->fetchColumn();
        }
        return $existe;
    }

    /**
     * Texto comparable de marca/motor: mayúsculas, sin tildes y sin espacios de más
     * ("Toyota  Corolla " = "TOYOTA COROLLA"). La marca es texto libre en vehiculos.
     */
    private static function norm(string $col): string
    {
        return "regexp_replace(translate(upper(trim(COALESCE($col, ''))), 'ÁÉÍÓÚÜÑ', 'AEIOUUN'), '\\s+', ' ', 'g')";
    }

    /** ¿Ya se aplicó el SQL de avisos (20261031_mantenimientos_avisos.sql)? */
    public function avisosDisponible(): bool
    {
        return $this->disponible() && $this->columnaExiste('carwash_recordatorios', 'id_vehiculo_mantenimiento');
    }

    // ─── Estimación del km de hoy ─────────────────────────────────────────────

    /**
     * SELECT que estima el km de HOY de un vehículo (para usar como subconsulta o LATERAL;
     * $colVeh / $colEmp son columnas de la consulta de afuera, p. ej. 'v.id').
     *
     * Lecturas: el km de sus órdenes de Car-Wash (no anuladas) y de sus cambios registrados.
     * Recorrido diario = (km de la última lectura − km de la lectura más antigua de los 365
     * días anteriores, con al menos 14 días de diferencia) / días entre ambas, entre 0 y
     * 1.000 km/día. Sin dos lecturas así, se usa $kmDiaDefecto. km de hoy = último km +
     * km/día × días desde la última lectura.
     * Columnas: km_ult, f_ult, km_dia, dia_defecto, km_est.
     */
    public static function sqlEstimacion(string $colVeh, string $colEmp, float $kmDiaDefecto): string
    {
        $def = sprintf('%.4F', max(0, $kmDiaDefecto));
        $lect = "(SELECT o.kilometraje AS km, o.fecha_ingreso::date AS f FROM carwash_ordenes o
                   WHERE o.id_vehiculo = $colVeh AND o.id_empresa = $colEmp AND o.eliminado = false
                     AND o.estado <> 'anulado' AND o.kilometraje IS NOT NULL
                  UNION ALL
                  SELECT h.km, h.fecha FROM vehiculos_mantenimientos_historial h
                   WHERE h.id_vehiculo = $colVeh AND h.id_empresa = $colEmp AND h.eliminado = false)";
        // CASE y no COALESCE: GREATEST/LEAST ignoran los NULL y sin segunda lectura darían 0.
        $tasa = "(CASE WHEN r.km IS NULL THEN $def ELSE LEAST(GREATEST((u.km - r.km)::numeric / NULLIF(u.f - r.f, 0), 0), 1000) END)";
        return "SELECT u.km AS km_ult, u.f AS f_ult,
                       ROUND($tasa, 1) AS km_dia,
                       (r.km IS NULL) AS dia_defecto,
                       (u.km + ROUND($tasa * GREATEST(CURRENT_DATE - u.f, 0)))::int AS km_est
                  FROM (SELECT l.km, l.f FROM $lect l ORDER BY l.f DESC, l.km DESC LIMIT 1) u
             LEFT JOIN LATERAL (SELECT l2.km, l2.f FROM $lect l2
                                 WHERE l2.f <= u.f - 14 AND l2.f >= u.f - 365 AND l2.km <= u.km
                              ORDER BY l2.f ASC, l2.km ASC LIMIT 1) r ON true";
    }

    /**
     * Estado de mantenimiento más urgente del vehículo con el km estimado de hoy:
     * 'vencido', 'proximo', 'al_dia' o NULL (sin cambios registrados). Para el filtro del
     * listado de Vehículos.
     */
    public static function sqlEstadoVehiculo(string $colVeh, string $colEmp, float $kmDiaDefecto, bool $conNoAplica = true): string
    {
        $noAplica = $conNoAplica ? " AND vm.no_aplica = false" : "";
        return "(SELECT CASE WHEN bool_or(vm.proximo_km - k.km_est <= 0) THEN 'vencido'
                             WHEN bool_or(vm.proximo_km - k.km_est <= s.aviso_km) THEN 'proximo'
                             WHEN COUNT(*) > 0 THEN 'al_dia' END
                   FROM vehiculos_mantenimientos vm
                   JOIN mantenimiento_servicios s ON s.id = vm.id_servicio AND s.eliminado = false AND s.activo = true
                   CROSS JOIN LATERAL (" . self::sqlEstimacion($colVeh, $colEmp, $kmDiaDefecto) . ") k
                  WHERE vm.id_vehiculo = $colVeh AND vm.id_empresa = $colEmp AND vm.eliminado = false AND vm.proximo_km IS NOT NULL$noAplica)";
    }

    public function estimacion(int $idVehiculo, int $idEmpresa, float $kmDiaDefecto): ?array
    {
        $st = $this->db->prepare(
            "SELECT k.* FROM vehiculos v CROSS JOIN LATERAL (" . self::sqlEstimacion('v.id', 'v.id_empresa', $kmDiaDefecto) . ") k
              WHERE v.id = :v AND v.id_empresa = :e"
        );
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ─── Avisos al cliente ────────────────────────────────────────────────────

    /** Datos de contacto: los del vehículo y los del cliente de su última orden de Car-Wash. */
    private function selectContacto(): string
    {
        return "v.id AS id_vehiculo, v.placa, v.marca, v.propietario AS vehiculo_propietario,
                v.correo AS vehiculo_correo, v.telefono AS vehiculo_telefono,
                uo.id_cliente, c.nombre AS cliente_nombre, c.email AS cliente_email, c.telefono AS cliente_telefono";
    }

    private function joinContacto(): string
    {
        return "LEFT JOIN LATERAL (SELECT o.id_cliente FROM carwash_ordenes o
                                    WHERE o.id_empresa = v.id_empresa AND o.id_vehiculo = v.id
                                      AND o.eliminado = false AND o.id_cliente IS NOT NULL
                                 ORDER BY o.fecha_ingreso DESC, o.id DESC LIMIT 1) uo ON true
                LEFT JOIN clientes c ON c.id = uo.id_cliente";
    }

    public function contactoVehiculo(int $idVehiculo, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT " . $this->selectContacto() . " FROM vehiculos v " . $this->joinContacto() . "
              WHERE v.id = :v AND v.id_empresa = :e AND v.eliminado = false");
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Mantenimientos con próximo cambio de los vehículos activos de la empresa, con el km
     * estimado de hoy, los datos de contacto y cuántos avisos AUTOMÁTICOS de ese mismo
     * cambio ya salieron por $canal (y cuándo el último). El servicio decide a quién avisar.
     * $idsServicios vacío = todos los servicios activos.
     */
    public function candidatosAviso(int $idEmpresa, string $canal, float $kmDiaDefecto, array $idsServicios = []): array
    {
        $params = [':e' => $idEmpresa, ':canal' => $canal];
        $filtroServ = '';
        $ids = array_values(array_filter(array_map('intval', $idsServicios)));
        if ($ids) {
            $ph = [];
            foreach ($ids as $i => $id) { $ph[] = ":s$i"; $params[":s$i"] = $id; }
            $filtroServ = ' AND s.id IN (' . implode(',', $ph) . ')';
        }
        $st = $this->db->prepare(
            "SELECT vm.id AS id_vehiculo_mantenimiento, vm.id_servicio, s.nombre AS servicio, s.aviso_km, s.orden,
                    vm.proximo_km, vm.ultimo_km, k.km_est, k.km_dia, k.dia_defecto,
                    (vm.proximo_km - k.km_est) AS faltan_km,
                    COALESCE(a.n, 0) AS avisos_enviados, a.ultimo AS ultimo_aviso,
                    " . $this->selectContacto() . "
               FROM vehiculos_mantenimientos vm
               JOIN mantenimiento_servicios s ON s.id = vm.id_servicio AND s.eliminado = false AND s.activo = true
               JOIN vehiculos v ON v.id = vm.id_vehiculo AND v.id_empresa = vm.id_empresa AND v.eliminado = false
                               AND COALESCE(v.estado, 'activo') = 'activo'
         CROSS JOIN LATERAL (" . self::sqlEstimacion('v.id', 'v.id_empresa', $kmDiaDefecto) . ") k
          LEFT JOIN LATERAL (SELECT COUNT(*) AS n, MAX(r.created_at) AS ultimo FROM carwash_recordatorios r
                              WHERE r.id_vehiculo_mantenimiento = vm.id AND r.proximo_km = vm.proximo_km
                                AND r.canal = :canal AND r.origen = 'automatico' AND r.estado = 'enviado' AND r.eliminado = false) a ON true
               " . $this->joinContacto() . "
              WHERE vm.id_empresa = :e AND vm.eliminado = false AND vm.proximo_km IS NOT NULL AND {$this->noAplicaFalso()}
                AND vm.proximo_km - k.km_est <= s.aviso_km $filtroServ
           ORDER BY v.id, s.orden, s.nombre"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Mantenimientos del vehículo con próximo cambio, para un aviso manual. */
    public function mantenimientosConProximo(int $idVehiculo, int $idEmpresa, float $kmDiaDefecto): array
    {
        $st = $this->db->prepare(
            "SELECT vm.id AS id_vehiculo_mantenimiento, vm.id_servicio, s.nombre AS servicio, s.aviso_km,
                    vm.proximo_km, k.km_est, (vm.proximo_km - k.km_est) AS faltan_km
               FROM vehiculos_mantenimientos vm
               JOIN mantenimiento_servicios s ON s.id = vm.id_servicio AND s.eliminado = false AND s.activo = true
               JOIN vehiculos v ON v.id = vm.id_vehiculo
         CROSS JOIN LATERAL (" . self::sqlEstimacion('v.id', 'v.id_empresa', $kmDiaDefecto) . ") k
              WHERE vm.id_vehiculo = :v AND vm.id_empresa = :e AND vm.eliminado = false AND vm.proximo_km IS NOT NULL
                AND {$this->noAplicaFalso()}
           ORDER BY s.orden, s.nombre"
        );
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Registra un aviso (una fila por servicio avisado) en carwash_recordatorios. */
    public function registrarAviso(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO carwash_recordatorios
                (id_empresa, id_vehiculo, id_cliente, id_vehiculo_mantenimiento, id_servicio, proximo_km, aviso_n,
                 fecha_cita, canal, destinatario, asunto, mensaje, estado, detalle, origen, created_by, updated_by)
             VALUES (:e, :v, :c, :vm, :s, :p, :n, CURRENT_DATE, :canal, :dest, :asunto, :msg, :estado, :det, :origen, :u, :u)
             RETURNING id"
        );
        $st->execute([
            ':e' => $d['id_empresa'], ':v' => $d['id_vehiculo'], ':c' => ($d['id_cliente'] ?? 0) ?: null,
            ':vm' => $d['id_vehiculo_mantenimiento'], ':s' => $d['id_servicio'], ':p' => $d['proximo_km'],
            ':n' => $d['aviso_n'] ?? null, ':canal' => $d['canal'],
            ':dest' => mb_substr((string) ($d['destinatario'] ?? ''), 0, 200),
            ':asunto' => mb_substr((string) ($d['asunto'] ?? ''), 0, 250) ?: null, ':msg' => $d['mensaje'] ?? null,
            ':estado' => $d['estado'], ':det' => $d['detalle'] ?? null, ':origen' => $d['origen'], ':u' => $d['id_usuario'] ?: null,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Avisos de mantenimiento enviados al cliente del vehículo (los más recientes primero). */
    public function avisosVehiculo(int $idVehiculo, int $idEmpresa, int $limite = 100): array
    {
        if (!$this->avisosDisponible()) return [];
        $st = $this->db->prepare(
            "SELECT r.id, r.created_at, r.canal, r.destinatario, r.estado, r.detalle, r.origen, r.proximo_km, r.aviso_n,
                    s.nombre AS servicio, u.nombre AS usuario
               FROM carwash_recordatorios r
          LEFT JOIN mantenimiento_servicios s ON s.id = r.id_servicio
          LEFT JOIN usuarios u ON u.id = r.created_by
              WHERE r.id_empresa = :e AND r.id_vehiculo = :v AND r.eliminado = false AND r.id_vehiculo_mantenimiento IS NOT NULL
           ORDER BY r.created_at DESC, r.id DESC
              LIMIT " . max(1, min(300, $limite))
        );
        $st->execute([':e' => $idEmpresa, ':v' => $idVehiculo]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Candado del vehículo para leer → calcular → escribir su estado (CLAUDE.md §8). */
    public function bloquearVehiculo(int $idVehiculo): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('mant_vehiculo:' || :v))");
        $st->execute([':v' => (string) $idVehiculo]);
    }

    // ─── Catálogo de servicios ────────────────────────────────────────────────

    /** Servicios que tuvo la empresa alguna vez (incluidos los eliminados). */
    public function contarServiciosHistoricos(int $idEmpresa): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM mantenimiento_servicios WHERE id_empresa = :e");
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function getServicios(int $idEmpresa, bool $soloActivos = false): array
    {
        $sql = "SELECT id, nombre, intervalo_km, aviso_km, orden, activo
                  FROM mantenimiento_servicios
                 WHERE id_empresa = :e AND eliminado = false" . ($soloActivos ? " AND activo = true" : "") . "
              ORDER BY orden, nombre, id";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findServicio(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, intervalo_km, aviso_km, orden, activo
               FROM mantenimiento_servicios
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existeNombreServicio(int $idEmpresa, string $nombre, int $excluirId = 0): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM mantenimiento_servicios
              WHERE id_empresa = :e AND eliminado = false AND lower(nombre) = lower(:n) AND id <> :x LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':n' => $nombre, ':x' => $excluirId]);
        return (bool) $st->fetchColumn();
    }

    /** Crea un servicio; si ya existe uno con el mismo nombre no hace nada y devuelve 0. */
    public function crearServicio(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO mantenimiento_servicios (id_empresa, nombre, intervalo_km, aviso_km, orden, activo, created_by, updated_by)
             VALUES (:e, :n, :i, :a, :o, :act, :u, :u)
             ON CONFLICT (id_empresa, lower(nombre)) WHERE eliminado = false DO NOTHING
             RETURNING id"
        );
        $st->execute([
            ':e' => $d['id_empresa'], ':n' => $d['nombre'], ':i' => $d['intervalo_km'], ':a' => $d['aviso_km'],
            ':o' => $d['orden'] ?? 0, ':act' => !empty($d['activo']) ? 'true' : 'false', ':u' => $d['id_usuario'],
        ]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    public function actualizarServicio(int $id, int $idEmpresa, array $d): void
    {
        $st = $this->db->prepare(
            "UPDATE mantenimiento_servicios
                SET nombre = :n, intervalo_km = :i, aviso_km = :a, orden = :o, activo = :act,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':n' => $d['nombre'], ':i' => $d['intervalo_km'], ':a' => $d['aviso_km'], ':o' => $d['orden'] ?? 0,
            ':act' => !empty($d['activo']) ? 'true' : 'false', ':u' => $d['id_usuario'], ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function eliminarServicio(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE mantenimiento_servicios
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Vehículos con datos (intervalo o cambios) en un servicio: se avisa antes de eliminarlo. */
    public function contarVehiculosConServicio(int $idServicio, int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM vehiculos_mantenimientos
              WHERE id_servicio = :s AND id_empresa = :e AND eliminado = false
                AND (intervalo_km IS NOT NULL OR proximo_km IS NOT NULL)"
        );
        $st->execute([':s' => $idServicio, ':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    // ─── Vehículo ─────────────────────────────────────────────────────────────

    public function getVehiculo(int $idVehiculo, int $idEmpresa): ?array
    {
        $motor = $this->columnaExiste('vehiculos', 'motor') ? 'motor' : 'NULL::varchar AS motor';
        $st = $this->db->prepare(
            "SELECT id, placa, marca, $motor FROM vehiculos WHERE id = :v AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Kilometraje conocido del vehículo: el mayor entre su ficha, sus órdenes de Car-Wash y
     * sus cambios registrados (el odómetro solo sube). $excluirOrden: la orden que se está
     * editando, para comparar el km escrito contra lo anterior.
     */
    public function kmActual(int $idVehiculo, int $idEmpresa, int $excluirOrden = 0): ?int
    {
        $kmFicha = $this->columnaExiste('vehiculos', 'kilometraje_actual')
            ? "(SELECT kilometraje_actual FROM vehiculos WHERE id = :v AND id_empresa = :e)"
            : "NULL::int";
        $st = $this->db->prepare(
            "SELECT GREATEST(
                    $kmFicha,
                    (SELECT MAX(kilometraje) FROM carwash_ordenes
                      WHERE id_vehiculo = :v AND id_empresa = :e AND eliminado = false AND estado <> 'anulado' AND id <> :x),
                    (SELECT MAX(km) FROM vehiculos_mantenimientos_historial
                      WHERE id_vehiculo = :v AND id_empresa = :e AND eliminado = false AND COALESCE(id_orden, 0) <> :x)
                    )"
        );
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa, ':x' => $excluirOrden]);
        $km = $st->fetchColumn();
        return $km === null || $km === false ? null : (int) $km;
    }

    /** Estado guardado por servicio del vehículo (intervalo propio + último/próximo cambio). */
    public function getEstados(int $idVehiculo, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, id_servicio, intervalo_km, ultimo_km, ultima_fecha, proximo_km, id_orden, " . ($this->noAplicaDisponible() ? "no_aplica" : "false AS no_aplica") . "
               FROM vehiculos_mantenimientos
              WHERE id_vehiculo = :v AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['id_servicio']] = $r;
        return $out;
    }

    /**
     * Intervalos que usan otros vehículos de la empresa con la misma marca, para sugerirlos.
     * Nivel 1: misma marca y modelo (el campo "Marca y modelo") y mismo motor; 2: misma marca
     * y modelo; 3: misma marca (primera palabra). Un vehículo cuenta solo en su nivel más
     * cercano. Devuelve [id_servicio => [ [nivel, intervalo_km, n, ultimo], ... ]].
     */
    public function sugerenciasIntervalo(int $idVehiculo, int $idEmpresa): array
    {
        $motor   = $this->columnaExiste('vehiculos', 'motor');
        $nMarca  = self::norm('v.marca');
        $nMotor  = $motor ? self::norm('v.motor') : "''";
        $nMarcaO = self::norm('o.marca');
        $nMotorO = $motor ? self::norm('o.motor') : "''";
        $st = $this->db->prepare(
            "WITH obj AS (
                 SELECT $nMarcaO AS m, split_part($nMarcaO, ' ', 1) AS b, $nMotorO AS mo
                   FROM vehiculos o WHERE o.id = :v AND o.id_empresa = :e
             ), c AS (
                 SELECT vm.id_servicio, vm.intervalo_km, vm.updated_at,
                        $nMarca AS m, split_part($nMarca, ' ', 1) AS b, $nMotor AS mo
                   FROM vehiculos_mantenimientos vm
                   JOIN vehiculos v ON v.id = vm.id_vehiculo AND v.id_empresa = vm.id_empresa AND v.eliminado = false
                  WHERE vm.id_empresa = :e AND vm.eliminado = false AND vm.intervalo_km IS NOT NULL AND vm.id_vehiculo <> :v
             )
             SELECT c.id_servicio, n.nivel, c.intervalo_km, COUNT(*) AS n, MAX(c.updated_at) AS ultimo
               FROM c CROSS JOIN obj
               CROSS JOIN LATERAL (SELECT CASE
                        WHEN obj.m <> '' AND c.m = obj.m AND obj.mo <> '' AND c.mo = obj.mo THEN 1
                        WHEN obj.m <> '' AND c.m = obj.m THEN 2
                        WHEN obj.b <> '' AND c.b = obj.b THEN 3
                    END AS nivel) n
              WHERE n.nivel IS NOT NULL
           GROUP BY c.id_servicio, n.nivel, c.intervalo_km"
        );
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['id_servicio']][] = $r;
        return $out;
    }

    /**
     * Otros vehículos de la empresa con la misma marca y modelo ('modelo') o solo la misma
     * marca ('marca'), para "Aplicar a los demás". Devuelve sus ids.
     */
    public function vehiculosMismaMarca(int $idVehiculo, int $idEmpresa, string $alcance): array
    {
        $nMarca  = self::norm('v.marca');
        $nMarcaO = self::norm('o.marca');
        $cond = $alcance === 'marca'
            ? "split_part($nMarca, ' ', 1) = split_part($nMarcaO, ' ', 1)"
            : "$nMarca = $nMarcaO";
        $st = $this->db->prepare(
            "SELECT v.id FROM vehiculos v
               JOIN vehiculos o ON o.id = :v AND o.id_empresa = :e
              WHERE v.id_empresa = :e AND v.eliminado = false AND v.id <> :v
                AND $nMarcaO <> '' AND $cond"
        );
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** ¿Ya existe la columna no_aplica (SQL 20261030_vehiculos_mantenimientos.sql actualizado)? */
    public function noAplicaDisponible(): bool
    {
        return $this->disponible() && $this->columnaExiste('vehiculos_mantenimientos', 'no_aplica');
    }

    /** Condición SQL "el servicio aplica a este vehículo" (alias vm). */
    private function noAplicaFalso(): string
    {
        return $this->noAplicaDisponible() ? 'vm.no_aplica = false' : 'true';
    }

    /** Marca (o desmarca) que el servicio no aplica al vehículo: no se muestra ni se avisa. */
    public function setNoAplica(int $idEmpresa, int $idVehiculo, int $idServicio, bool $noAplica, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "INSERT INTO vehiculos_mantenimientos (id_empresa, id_vehiculo, id_servicio, no_aplica, created_by, updated_by)
             VALUES (:e, :v, :s, :n, :u, :u)
             ON CONFLICT (id_vehiculo, id_servicio) WHERE eliminado = false
             DO UPDATE SET no_aplica = EXCLUDED.no_aplica, updated_at = CURRENT_TIMESTAMP, updated_by = EXCLUDED.updated_by"
        );
        $st->execute([':e' => $idEmpresa, ':v' => $idVehiculo, ':s' => $idServicio, ':n' => $noAplica ? 'true' : 'false', ':u' => $idUsuario]);
    }

    /** Fija (o quita, con NULL) el intervalo propio del vehículo para un servicio. */
    public function setIntervalo(int $idEmpresa, int $idVehiculo, int $idServicio, ?int $intervalo, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "INSERT INTO vehiculos_mantenimientos (id_empresa, id_vehiculo, id_servicio, intervalo_km, created_by, updated_by)
             VALUES (:e, :v, :s, :i, :u, :u)
             ON CONFLICT (id_vehiculo, id_servicio) WHERE eliminado = false
             DO UPDATE SET intervalo_km = EXCLUDED.intervalo_km, updated_at = CURRENT_TIMESTAMP, updated_by = EXCLUDED.updated_by"
        );
        $st->execute([':e' => $idEmpresa, ':v' => $idVehiculo, ':s' => $idServicio, ':i' => $intervalo, ':u' => $idUsuario]);
    }

    /** Copia en vehiculos_mantenimientos el último cambio vigente del historial (o lo vacía). */
    public function recalcularEstado(int $idEmpresa, int $idVehiculo, int $idServicio, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "SELECT km, fecha, proximo_km, id_orden FROM vehiculos_mantenimientos_historial
              WHERE id_empresa = :e AND id_vehiculo = :v AND id_servicio = :s AND eliminado = false
           ORDER BY km DESC, fecha DESC, id DESC LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':v' => $idVehiculo, ':s' => $idServicio]);
        $u = $st->fetch(PDO::FETCH_ASSOC) ?: ['km' => null, 'fecha' => null, 'proximo_km' => null, 'id_orden' => null];

        $st = $this->db->prepare(
            "INSERT INTO vehiculos_mantenimientos (id_empresa, id_vehiculo, id_servicio, ultimo_km, ultima_fecha, proximo_km, id_orden, created_by, updated_by)
             VALUES (:e, :v, :s, :k, :f, :p, :o, :u, :u)
             ON CONFLICT (id_vehiculo, id_servicio) WHERE eliminado = false
             DO UPDATE SET ultimo_km = EXCLUDED.ultimo_km, ultima_fecha = EXCLUDED.ultima_fecha,
                           proximo_km = EXCLUDED.proximo_km, id_orden = EXCLUDED.id_orden,
                           updated_at = CURRENT_TIMESTAMP, updated_by = EXCLUDED.updated_by"
        );
        $st->execute([
            ':e' => $idEmpresa, ':v' => $idVehiculo, ':s' => $idServicio,
            ':k' => $u['km'], ':f' => $u['fecha'], ':p' => $u['proximo_km'], ':o' => $u['id_orden'], ':u' => $idUsuario,
        ]);
    }

    // ─── Historial de cambios ─────────────────────────────────────────────────

    /** Cambios vigentes del vehículo, del más reciente al más antiguo. */
    public function getHistorialVehiculo(int $idVehiculo, int $idEmpresa, int $limite = 300): array
    {
        $st = $this->db->prepare(
            "SELECT h.id, h.id_servicio, s.nombre AS servicio, h.km, h.fecha, h.intervalo_km, h.proximo_km,
                    h.origen, h.id_orden, o.numero_orden, h.observacion, h.created_at, u.nombre AS usuario
               FROM vehiculos_mantenimientos_historial h
               JOIN mantenimiento_servicios s ON s.id = h.id_servicio
          LEFT JOIN carwash_ordenes o ON o.id = h.id_orden
          LEFT JOIN usuarios u ON u.id = h.created_by
              WHERE h.id_vehiculo = :v AND h.id_empresa = :e AND h.eliminado = false
           ORDER BY h.km DESC, h.fecha DESC, h.id DESC
              LIMIT $limite"
        );
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cambios vigentes registrados por una orden, por id_servicio. */
    public function getHistorialOrden(int $idOrden, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, id_vehiculo, id_servicio, km, fecha, intervalo_km, proximo_km
               FROM vehiculos_mantenimientos_historial
              WHERE id_orden = :o AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['id_servicio']] = $r;
        return $out;
    }

    public function findHistorial(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, id_vehiculo, id_servicio, km, fecha, intervalo_km, proximo_km, origen, id_orden, observacion, created_by
               FROM vehiculos_mantenimientos_historial
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearHistorial(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO vehiculos_mantenimientos_historial
                    (id_empresa, id_vehiculo, id_servicio, km, fecha, intervalo_km, proximo_km, origen, id_orden, observacion, created_by, updated_by)
             VALUES (:e, :v, :s, :k, :f, :i, :p, :origen, :o, :obs, :u, :u) RETURNING id"
        );
        $st->execute([
            ':e' => $d['id_empresa'], ':v' => $d['id_vehiculo'], ':s' => $d['id_servicio'], ':k' => $d['km'],
            ':f' => $d['fecha'], ':i' => $d['intervalo_km'], ':p' => $d['proximo_km'], ':origen' => $d['origen'],
            ':o' => $d['id_orden'] ?? null, ':obs' => ($d['observacion'] ?? '') !== '' ? $d['observacion'] : null,
            ':u' => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarHistorial(int $id, int $idEmpresa, array $d): void
    {
        $st = $this->db->prepare(
            "UPDATE vehiculos_mantenimientos_historial
                SET id_vehiculo = :v, km = :k, fecha = :f, intervalo_km = :i, proximo_km = :p,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':v' => $d['id_vehiculo'], ':k' => $d['km'], ':f' => $d['fecha'], ':i' => $d['intervalo_km'],
            ':p' => $d['proximo_km'], ':u' => $d['id_usuario'], ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    /** Baja lógica de un cambio. $porAnulacion: lo dio de baja la anulación de su orden. */
    public function bajaHistorial(int $id, int $idEmpresa, int $idUsuario, bool $porAnulacion = false): void
    {
        $st = $this->db->prepare(
            "UPDATE vehiculos_mantenimientos_historial
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u,
                    baja_por_anulacion = :a
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':a' => $porAnulacion ? 'true' : 'false', ':id' => $id, ':e' => $idEmpresa]);
    }

    /**
     * Devuelve los cambios que dio de baja la anulación de la orden (al reactivarla).
     * Solo los que no chocan con un cambio vigente de la misma orden y servicio.
     * Devuelve [ [id_vehiculo, id_servicio], ... ] de los restaurados.
     */
    public function restaurarPorAnulacion(int $idOrden, int $idEmpresa, int $idUsuario): array
    {
        $st = $this->db->prepare(
            "UPDATE vehiculos_mantenimientos_historial h
                SET eliminado = false, deleted_at = NULL, deleted_by = NULL, baja_por_anulacion = false,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE h.id_orden = :o AND h.id_empresa = :e AND h.eliminado = true AND h.baja_por_anulacion = true
                AND NOT EXISTS (SELECT 1 FROM vehiculos_mantenimientos_historial x
                                 WHERE x.id_orden = h.id_orden AND x.id_servicio = h.id_servicio AND x.eliminado = false)
          RETURNING h.id_vehiculo, h.id_servicio"
        );
        $st->execute([':u' => $idUsuario, ':o' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mantenimientos para el PDF / correo / factura de una orden: lo registrado en ella y el
     * próximo cambio vigente de cada servicio del vehículo.
     */
    public function resumenOrden(int $idOrden, int $idVehiculo, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT s.id AS id_servicio, s.nombre, s.orden,
                    h.km AS km_cambio, h.proximo_km AS proximo_orden,
                    vm.ultimo_km, vm.proximo_km
               FROM mantenimiento_servicios s
          LEFT JOIN vehiculos_mantenimientos_historial h
                 ON h.id_servicio = s.id AND h.id_orden = :o AND h.id_empresa = :e AND h.eliminado = false
          LEFT JOIN vehiculos_mantenimientos vm
                 ON vm.id_servicio = s.id AND vm.id_vehiculo = :v AND vm.id_empresa = :e AND vm.eliminado = false
              WHERE s.id_empresa = :e AND s.eliminado = false
                AND (h.id IS NOT NULL OR (vm.proximo_km IS NOT NULL AND " . $this->noAplicaFalso() . "))
           ORDER BY s.orden, s.nombre, s.id"
        );
        $st->execute([':o' => $idOrden, ':v' => $idVehiculo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
