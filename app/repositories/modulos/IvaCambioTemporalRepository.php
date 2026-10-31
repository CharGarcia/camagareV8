<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Cambio temporal de IVA en productos (prestadores de servicios turísticos — IVA 8% en feriados).
 *
 * Cada registro de iva_cambios_temporales es "tarifa actual → tarifa nueva" entre
 * dos fechas para los productos de una empresa. Al aplicarlo se guarda en el detalle
 * la tarifa que tenía cada producto, y al restaurarlo se le devuelve exactamente esa.
 * Esquema: database/2026-10-31_iva_cambios_temporales.sql.
 */
class IvaCambioTemporalRepository extends BaseRepository
{
    /** Filtro "Aplicar a" → condición sobre productos.tipo_produccion (01 bien, 02 servicio). */
    private const FILTRO_TIPO = [
        'todos'     => '',
        'servicios' => " AND COALESCE(p.tipo_produccion, '01') = '02'",
        'bienes'    => " AND COALESCE(p.tipo_produccion, '01') <> '02'",
    ];

    private static ?bool $tablaDisponible = null;

    public function __construct()
    {
        parent::__construct('iva_cambios_temporales');
    }

    /** ¿Ya se aplicó la migración? Sin la tabla el módulo no hace nada (degrada). */
    public function tablaDisponible(): bool
    {
        if (self::$tablaDisponible === null) {
            try {
                $st = $this->db->query("SELECT to_regclass('public.iva_cambios_temporales') IS NOT NULL");
                self::$tablaDisponible = (bool) $st->fetchColumn();
            } catch (\Throwable $e) {
                self::$tablaDisponible = false;
            }
        }
        return self::$tablaDisponible;
    }

    /**
     * Candado del cambio temporal de IVA de la empresa: programar, aplicar y
     * restaurar leen el estado y luego escriben productos, así que dos procesos
     * a la vez (cron + usuario) no deben cruzarse. Se libera al COMMIT/ROLLBACK.
     */
    public function lockEmpresa(int $idEmpresa): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('iva_temporal:' || CAST(:id AS text)))");
        $st->execute([':id' => $idEmpresa]);
    }

    /** Tarifas de IVA vigentes (catálogo global, sin id_empresa). */
    public function getTarifas(): array
    {
        $st = $this->db->query(
            "SELECT id, codigo, tarifa, porcentaje_iva FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva DESC, tarifa"
        );
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTarifa(int $id): ?array
    {
        $st = $this->db->prepare("SELECT id, codigo, tarifa, porcentaje_iva, status FROM tarifa_iva WHERE id = :id");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Cuántos productos de la empresa tienen cada tarifa, separados en bienes y
     * servicios (para el selector "Tarifa actual" y la vista previa del modal).
     *
     * @return array<int, array{total:int, servicios:int, bienes:int}> por id de tarifa
     */
    public function conteoPorTarifa(int $idEmpresa): array
    {
        $where = $this->getBaseWhere($idEmpresa, 'p');
        $st = $this->db->prepare(
            "SELECT p.tarifa_iva AS id_tarifa,
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE COALESCE(p.tipo_produccion, '01') = '02') AS servicios
               FROM productos p
               {$where}
                AND p.tarifa_iva IS NOT NULL
              GROUP BY p.tarifa_iva"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $total = (int) $r['total'];
            $serv  = (int) $r['servicios'];
            $out[(int) $r['id_tarifa']] = ['total' => $total, 'servicios' => $serv, 'bienes' => $total - $serv];
        }
        return $out;
    }

    /** Historial de cambios de la empresa (más reciente primero). */
    public function listar(int $idEmpresa, int $limite = 50): array
    {
        $where = $this->getBaseWhere($idEmpresa, 'c');
        $st = $this->db->prepare(
            "SELECT c.id, c.id_tarifa_actual, c.id_tarifa_nueva, c.aplica_a, c.fecha_desde, c.fecha_hasta,
                    c.observaciones, c.estado, c.aplicado_at, c.restaurado_at,
                    c.total_productos, c.total_restaurados, c.total_omitidos, c.created_at,
                    ta.tarifa AS tarifa_actual, tn.tarifa AS tarifa_nueva,
                    u.nombre AS creado_por
               FROM iva_cambios_temporales c
               LEFT JOIN tarifa_iva ta ON ta.id = c.id_tarifa_actual
               LEFT JOIN tarifa_iva tn ON tn.id = c.id_tarifa_nueva
               LEFT JOIN usuarios u    ON u.id = c.created_by
               {$where}
              ORDER BY c.fecha_desde DESC, c.id DESC
              LIMIT " . max(1, $limite)
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        $where = $this->getBaseWhere($idEmpresa, 'c');
        $st = $this->db->prepare("SELECT c.* FROM iva_cambios_temporales c {$where} AND c.id = :id");
        $st->execute([':id_empresa' => $idEmpresa, ':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** ¿Hay otro cambio vigente (programado o aplicado) cuyas fechas se crucen? */
    public function existeCruce(int $idEmpresa, string $desde, string $hasta): ?array
    {
        $where = $this->getBaseWhere($idEmpresa, 'c');
        $st = $this->db->prepare(
            "SELECT c.id, c.fecha_desde, c.fecha_hasta
               FROM iva_cambios_temporales c
               {$where}
                AND c.estado IN ('programado', 'aplicado')
                AND c.fecha_desde <= :hasta
                AND c.fecha_hasta >= :desde
              LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':desde' => $desde, ':hasta' => $hasta]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function insertar(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO iva_cambios_temporales
                (id_empresa, id_tarifa_actual, id_tarifa_nueva, aplica_a, fecha_desde, fecha_hasta,
                 observaciones, estado, created_at, created_by, eliminado)
             VALUES (:id_empresa, :actual, :nueva, :aplica_a, :desde, :hasta,
                     :obs, 'programado', NOW(), :usuario, false)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa' => $d['id_empresa'],
            ':actual'     => $d['id_tarifa_actual'],
            ':nueva'      => $d['id_tarifa_nueva'],
            ':aplica_a'   => $d['aplica_a'],
            ':desde'      => $d['fecha_desde'],
            ':hasta'      => $d['fecha_hasta'],
            ':obs'        => $d['observaciones'] !== '' ? $d['observaciones'] : null,
            ':usuario'    => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Cambia el estado del encabezado y, según el paso, sus marcas de tiempo y
     * contadores. $extra admite: total_productos, total_restaurados, total_omitidos.
     */
    public function actualizarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario, array $extra = []): void
    {
        $sets   = ['estado = :estado', 'updated_at = NOW()', 'updated_by = :usuario'];
        $params = [':estado' => $estado, ':usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa];
        if ($estado === 'aplicado') {
            $sets[] = 'aplicado_at = NOW()';
        } elseif ($estado === 'restaurado') {
            $sets[] = 'restaurado_at = NOW()';
        }
        foreach (['total_productos', 'total_restaurados', 'total_omitidos'] as $c) {
            if (array_key_exists($c, $extra)) {
                $sets[] = "{$c} = :{$c}";
                $params[":{$c}"] = (int) $extra[$c];
            }
        }
        $st = $this->db->prepare(
            'UPDATE iva_cambios_temporales SET ' . implode(', ', $sets) . '
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false'
        );
        $st->execute($params);
    }

    /**
     * Aplica el cambio: guarda en el detalle la tarifa de cada producto que cumple
     * el filtro y luego se la cambia. Devuelve cuántos productos cambió.
     */
    public function aplicarProductos(array $cambio, int $idUsuario): int
    {
        $idEmpresa = (int) $cambio['id_empresa'];
        $filtro    = self::FILTRO_TIPO[$cambio['aplica_a']] ?? '';
        $where     = $this->getBaseWhere($idEmpresa, 'p');

        $st = $this->db->prepare(
            "INSERT INTO iva_cambios_temporales_detalle
                (id_cambio, id_empresa, id_producto, id_tarifa_anterior, id_tarifa_aplicada, created_at, created_by, eliminado)
             SELECT :id_cambio, p.id_empresa, p.id, p.tarifa_iva, :nueva, NOW(), :usuario, false
               FROM productos p
               {$where}
                AND p.tarifa_iva = :actual{$filtro}"
        );
        $st->execute([
            ':id_cambio'  => (int) $cambio['id'],
            ':id_empresa' => $idEmpresa,
            ':actual'     => (int) $cambio['id_tarifa_actual'],
            ':nueva'      => (int) $cambio['id_tarifa_nueva'],
            ':usuario'    => $idUsuario,
        ]);

        $st = $this->db->prepare(
            "UPDATE productos p
                SET tarifa_iva = d.id_tarifa_aplicada, updated_at = NOW(), updated_by = :usuario
               FROM iva_cambios_temporales_detalle d
              WHERE d.id_cambio = :id_cambio
                AND d.eliminado = false
                AND p.id = d.id_producto
                AND p.id_empresa = :id_empresa"
        );
        $st->execute([':id_cambio' => (int) $cambio['id'], ':id_empresa' => $idEmpresa, ':usuario' => $idUsuario]);
        return $st->rowCount();
    }

    /**
     * Restaura el cambio: cada producto vuelve a la tarifa que tenía antes. Si
     * alguien le cambió la tarifa a mano durante el período, no se toca (queda
     * "omitido"): esa decisión posterior manda sobre la restauración automática.
     *
     * @return array{restaurados:int, omitidos:int}
     */
    public function restaurarProductos(int $idCambio, int $idEmpresa, int $idUsuario): array
    {
        $params = [':id_cambio' => $idCambio, ':id_empresa' => $idEmpresa, ':usuario' => $idUsuario];

        $st = $this->db->prepare(
            "UPDATE iva_cambios_temporales_detalle d
                SET omitido = true, updated_at = NOW(), updated_by = :usuario
               FROM productos p
              WHERE d.id_cambio = :id_cambio
                AND d.id_empresa = :id_empresa
                AND d.eliminado = false
                AND d.restaurado = false
                AND p.id = d.id_producto
                AND p.tarifa_iva IS DISTINCT FROM d.id_tarifa_aplicada"
        );
        $st->execute($params);
        $omitidos = $st->rowCount();

        $st = $this->db->prepare(
            "UPDATE productos p
                SET tarifa_iva = d.id_tarifa_anterior, updated_at = NOW(), updated_by = :usuario
               FROM iva_cambios_temporales_detalle d
              WHERE d.id_cambio = :id_cambio
                AND d.id_empresa = :id_empresa
                AND d.eliminado = false
                AND d.restaurado = false
                AND d.omitido = false
                AND p.id = d.id_producto
                AND p.id_empresa = d.id_empresa
                AND p.tarifa_iva = d.id_tarifa_aplicada"
        );
        $st->execute($params);
        $restaurados = $st->rowCount();

        $st = $this->db->prepare(
            "UPDATE iva_cambios_temporales_detalle
                SET restaurado = true, restaurado_at = NOW(), updated_at = NOW(), updated_by = :usuario
              WHERE id_cambio = :id_cambio
                AND id_empresa = :id_empresa
                AND eliminado = false
                AND restaurado = false
                AND omitido = false"
        );
        $st->execute($params);

        return ['restaurados' => $restaurados, 'omitidos' => $omitidos];
    }

    /** Productos de un cambio aplicado (para "Ver productos" del historial). */
    public function getDetalle(int $idCambio, int $idEmpresa): array
    {
        $where = $this->getBaseWhere($idEmpresa, 'd');
        $st = $this->db->prepare(
            "SELECT p.codigo, p.nombre, ta.tarifa AS tarifa_anterior, tn.tarifa AS tarifa_aplicada,
                    tv.tarifa AS tarifa_hoy, d.restaurado, d.omitido
               FROM iva_cambios_temporales_detalle d
               JOIN productos p        ON p.id = d.id_producto
               LEFT JOIN tarifa_iva ta ON ta.id = d.id_tarifa_anterior
               LEFT JOIN tarifa_iva tn ON tn.id = d.id_tarifa_aplicada
               LEFT JOIN tarifa_iva tv ON tv.id = p.tarifa_iva
               {$where}
                AND d.id_cambio = :id_cambio
              ORDER BY p.nombre"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id_cambio' => $idCambio]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cambios que ya deberían estar aplicados o restaurados a la fecha $hoy
     * (fecha de Ecuador, la pasa el Service): programados cuyo "desde" llegó y
     * aplicados cuyo "hasta" ya pasó. Con $idEmpresa, solo los de esa empresa.
     */
    public function getPendientes(string $hoy, ?int $idEmpresa = null): array
    {
        $sql = "SELECT c.id, c.id_empresa
                  FROM iva_cambios_temporales c
                 WHERE c.eliminado = false
                   AND ((c.estado = 'programado' AND c.fecha_desde <= :hoy_desde)
                     OR (c.estado = 'aplicado'   AND c.fecha_hasta <  :hoy_hasta))";
        $params = [':hoy_desde' => $hoy, ':hoy_hasta' => $hoy];
        if ($idEmpresa !== null) {
            $sql .= ' AND c.id_empresa = :id_empresa';
            $params[':id_empresa'] = $idEmpresa;
        }
        $st = $this->db->prepare($sql . ' ORDER BY c.id_empresa, c.fecha_desde, c.id');
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
