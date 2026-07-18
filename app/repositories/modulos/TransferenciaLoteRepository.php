<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Repositorio de Lotes de Transferencia (Documentos → Cargar Transferencias).
 * Cabecera transferencias_lotes + detalle transferencias_lotes_detalle. Cada
 * línea del detalle referencia una línea de pago ya existente en egresos_pagos
 * (proveedores o nómina); el módulo no genera egresos nuevos.
 */
class TransferenciaLoteRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['numero', 'fecha_pago', 'tipo_lote', 'estado', 'monto_total', 'created_at'];

    /**
     * proveedores.tipo_cta es un código entero (1=Ahorros, 2=Corriente, 3=Virtual, 4=Otro,
     * ver app/views/modulos/proveedores/modal_proveedor.php), mientras que
     * empleados.tipo_cuenta es texto libre (ej. 'ahorros'). No se puede hacer
     * COALESCE(integer, varchar) directamente (Postgres lo rechaza); se normaliza
     * el código de proveedor al mismo texto antes de combinar.
     */
    private const TIPO_CUENTA_CASE = "COALESCE(
        CASE prov.tipo_cta WHEN 1 THEN 'ahorros' WHEN 2 THEN 'corriente' WHEN 3 THEN 'virtual' WHEN 4 THEN 'otro' END,
        emp.tipo_cuenta
    )";

    public function __construct()
    {
        parent::__construct('transferencias_lotes');
    }

    // ─── LISTADO / DETALLE ──────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'numero';
        }
        $dir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = "WHERE l.id_empresa = :e AND l.eliminado = false";
        $params = [':e' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= " AND l.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (CAST(l.numero AS TEXT) ILIKE :b OR l.tipo_lote ILIKE :b OR l.estado ILIKE :b OR fp.nombre ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'exacto'   => ['estado' => 'l.estado', 'tipo' => 'l.tipo_lote'],
            'numerico' => ['numero' => 'l.numero', 'monto' => 'l.monto_total'],
            'fecha'    => ['fecha' => 'l.fecha_pago'],
        ]);

        $joins = "LEFT JOIN empresa_formas_pago fp ON fp.id = l.id_forma_pago_origen";

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM transferencias_lotes l $joins $where");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limit = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limit  = "LIMIT $perPage OFFSET $offset";
        }

        $sql = "SELECT l.*,
                       fp.nombre AS forma_pago_nombre,
                       b.nombre_banco AS banco_formato_nombre,
                       u.nombre AS creado_por_nombre,
                       ua.nombre AS aprobado_por_nombre
                FROM transferencias_lotes l
                $joins
                LEFT JOIN bancos_ecuador b ON b.id = l.id_banco_formato
                LEFT JOIN usuarios u  ON u.id = l.created_by
                LEFT JOIN usuarios ua ON ua.id = l.aprobado_por
                $where
                ORDER BY l.$ordenCol $dir
                $limit";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT l.*,
                    fp.nombre AS forma_pago_nombre,
                    fp.numero_cuenta AS forma_pago_numero_cuenta,
                    b.nombre_banco AS banco_formato_nombre,
                    u.nombre AS creado_por_nombre,
                    ua.nombre AS aprobado_por_nombre,
                    ur.nombre AS rechazado_por_nombre,
                    uc.nombre AS confirmado_por_nombre
             FROM transferencias_lotes l
             LEFT JOIN empresa_formas_pago fp ON fp.id = l.id_forma_pago_origen
             LEFT JOIN bancos_ecuador b ON b.id = l.id_banco_formato
             LEFT JOIN usuarios u  ON u.id = l.created_by
             LEFT JOIN usuarios ua ON ua.id = l.aprobado_por
             LEFT JOIN usuarios ur ON ur.id = l.rechazado_por
             LEFT JOIN usuarios uc ON uc.id = l.confirmado_por
             WHERE l.id = :id AND l.id_empresa = :e AND l.eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getDetalle(int $idLote, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT d.*, ec.numero_egreso, b.codigo_banco
             FROM transferencias_lotes_detalle d
             LEFT JOIN egresos_cabecera ec ON ec.id = d.id_egreso
             LEFT JOIN bancos_ecuador b ON b.id = d.id_banco_ecuador
             WHERE d.id_lote = :id AND d.id_empresa = :e AND d.eliminado = false
             ORDER BY d.id ASC"
        );
        $st->execute([':id' => $idLote, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pagos de egresos (proveedores y/o nómina) marcados como TRANSFERENCIA que
     * aún no están reservados en ningún lote activo (no RECHAZADO/ANULADO).
     * $tipo: 'PROVEEDORES' | 'NOMINA' | 'AMBOS'.
     */
    public function getPagosDisponibles(int $idEmpresa, string $tipo, string $buscar = ''): array
    {
        $where  = "WHERE ec.id_empresa = :e
                    AND ec.eliminado = false AND ep.eliminado = false AND ec.estado <> 'anulado'
                    AND ep.tipo_operacion_bancaria = 'TRANSFERENCIA'
                    AND NOT EXISTS (
                        SELECT 1 FROM transferencias_lotes_detalle tld
                        INNER JOIN transferencias_lotes tl ON tl.id = tld.id_lote
                        WHERE tld.id_egreso_pago = ep.id AND tld.eliminado = false
                          AND tl.estado NOT IN ('RECHAZADO', 'ANULADO')
                    )";
        $params = [':e' => $idEmpresa];

        if ($tipo === 'PROVEEDORES') {
            $where .= " AND ec.tipo_sujeto = 'PROVEEDOR'";
        } elseif ($tipo === 'NOMINA') {
            $where .= " AND ec.tipo_sujeto = 'EMPLEADO'";
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (COALESCE(prov.razon_social, emp.nombres_apellidos) ILIKE :b
                          OR COALESCE(prov.identificacion, emp.identificacion) ILIKE :b
                          OR ec.numero_egreso ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }

        $sql = "SELECT ep.id AS id_egreso_pago, ep.monto, ep.referencia,
                       ec.id AS id_egreso, ec.numero_egreso, ec.tipo_sujeto, ec.fecha_emision,
                       ec.id_proveedor, ec.id_empleado,
                       COALESCE(prov.razon_social, emp.nombres_apellidos) AS beneficiario,
                       COALESCE(prov.identificacion, emp.identificacion)  AS identificacion,
                       COALESCE(prov.id_banco, emp.id_banco_ecuador)      AS id_banco,
                       " . self::TIPO_CUENTA_CASE . " AS tipo_cuenta,
                       COALESCE(prov.numero_cta, emp.numero_cuenta)       AS numero_cuenta,
                       b.nombre_banco AS banco_nombre
                FROM egresos_pagos ep
                INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                LEFT JOIN proveedores prov ON prov.id = ec.id_proveedor
                LEFT JOIN empleados emp ON emp.id = ec.id_empleado
                LEFT JOIN bancos_ecuador b ON b.id = COALESCE(prov.id_banco, emp.id_banco_ecuador)
                $where
                ORDER BY ec.fecha_emision ASC, ec.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Un pago puntual (mismo shape que getPagosDisponibles) sin filtrar por disponibilidad, para releer datos al agregarlo a un lote. */
    public function getPagoPorId(int $idEgresoPago, int $idEmpresa): ?array
    {
        $sql = "SELECT ep.id AS id_egreso_pago, ep.monto, ep.referencia,
                       ec.id AS id_egreso, ec.numero_egreso, ec.tipo_sujeto, ec.fecha_emision,
                       ec.id_proveedor, ec.id_empleado,
                       COALESCE(prov.razon_social, emp.nombres_apellidos) AS beneficiario,
                       COALESCE(prov.identificacion, emp.identificacion)  AS identificacion,
                       COALESCE(prov.id_banco, emp.id_banco_ecuador)      AS id_banco,
                       " . self::TIPO_CUENTA_CASE . " AS tipo_cuenta,
                       COALESCE(prov.numero_cta, emp.numero_cuenta)       AS numero_cuenta
                FROM egresos_pagos ep
                INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                LEFT JOIN proveedores prov ON prov.id = ec.id_proveedor
                LEFT JOIN empleados emp ON emp.id = ec.id_empleado
                WHERE ep.id = :id AND ec.id_empresa = :e AND ec.eliminado = false AND ep.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idEgresoPago, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Confirma que las líneas indicadas siguen disponibles (no reservadas por otro lote). */
    public function idsEgresoPagoNoDisponibles(array $idsEgresoPago, ?int $idLoteExcluir = null): array
    {
        $ids = array_values(array_filter(array_map('intval', $idsEgresoPago)));
        if (empty($ids)) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT DISTINCT tld.id_egreso_pago
                FROM transferencias_lotes_detalle tld
                INNER JOIN transferencias_lotes tl ON tl.id = tld.id_lote
                WHERE tld.id_egreso_pago IN ($ph) AND tld.eliminado = false
                  AND tl.estado NOT IN ('RECHAZADO', 'ANULADO')";
        $params = $ids;
        if ($idLoteExcluir !== null) {
            $sql .= " AND tl.id <> ?";
            $params[] = $idLoteExcluir;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    // ─── ESCRITURA ────────────────────────────────────────────────────────────

    public function siguienteNumero(int $idEmpresa): int
    {
        $st = $this->db->prepare("SELECT COALESCE(MAX(numero), 0) + 1 FROM transferencias_lotes WHERE id_empresa = :e");
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function crearCabecera(array $d): int
    {
        $sql = "INSERT INTO transferencias_lotes
                    (id_empresa, numero, tipo_lote, id_forma_pago_origen, id_banco_formato,
                     fecha_pago, observaciones, estado, created_by, created_at, eliminado)
                VALUES
                    (:id_empresa, :numero, :tipo, :forma, :banco,
                     :fecha_pago, :obs, 'BORRADOR', :cb, CURRENT_TIMESTAMP, false)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $d['id_empresa'],
            ':numero'     => $d['numero'],
            ':tipo'       => $d['tipo_lote'],
            ':forma'      => $d['id_forma_pago_origen'] ?: null,
            ':banco'      => $d['id_banco_formato'] ?: null,
            ':fecha_pago' => $d['fecha_pago'] ?: null,
            ':obs'        => $d['observaciones'] ?? null,
            ':cb'         => $d['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function crearDetalle(array $d): int
    {
        $sql = "INSERT INTO transferencias_lotes_detalle
                    (id_lote, id_empresa, id_egreso, id_egreso_pago, tipo_beneficiario,
                     id_proveedor, id_empleado, nombre_beneficiario, identificacion,
                     id_banco_ecuador, tipo_cuenta, numero_cuenta, monto, concepto,
                     created_by, created_at, eliminado)
                VALUES
                    (:id_lote, :id_empresa, :id_egreso, :id_egreso_pago, :tipo_ben,
                     :id_prov, :id_emp, :nombre, :identificacion,
                     :id_banco, :tipo_cuenta, :numero_cuenta, :monto, :concepto,
                     :cb, CURRENT_TIMESTAMP, false)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_lote'        => $d['id_lote'],
            ':id_empresa'     => $d['id_empresa'],
            ':id_egreso'      => $d['id_egreso'],
            ':id_egreso_pago' => $d['id_egreso_pago'],
            ':tipo_ben'       => $d['tipo_beneficiario'],
            ':id_prov'        => $d['id_proveedor'] ?: null,
            ':id_emp'         => $d['id_empleado'] ?: null,
            ':nombre'         => $d['nombre_beneficiario'] ?? null,
            ':identificacion' => $d['identificacion'] ?? null,
            ':id_banco'       => $d['id_banco_ecuador'] ?: null,
            ':tipo_cuenta'    => $d['tipo_cuenta'] ?? null,
            ':numero_cuenta'  => $d['numero_cuenta'] ?? null,
            ':monto'          => $d['monto'] ?? 0,
            ':concepto'       => $d['concepto'] ?? null,
            ':cb'             => $d['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function eliminarDetalleLinea(int $idDetalle, int $idLote, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "UPDATE transferencias_lotes_detalle SET eliminado = true
             WHERE id = :id AND id_lote = :lote AND id_empresa = :e AND eliminado = false"
        );
        return $st->execute([':id' => $idDetalle, ':lote' => $idLote, ':e' => $idEmpresa]);
    }

    /** Recalcula monto_total y cantidad_pagos de la cabecera a partir del detalle activo. */
    public function recalcularTotales(int $idLote, int $idEmpresa): void
    {
        $st = $this->db->prepare(
            "UPDATE transferencias_lotes l
             SET monto_total = COALESCE((SELECT SUM(monto) FROM transferencias_lotes_detalle WHERE id_lote = l.id AND eliminado = false), 0),
                 cantidad_pagos = COALESCE((SELECT COUNT(*) FROM transferencias_lotes_detalle WHERE id_lote = l.id AND eliminado = false), 0),
                 updated_at = CURRENT_TIMESTAMP
             WHERE l.id = :id AND l.id_empresa = :e"
        );
        $st->execute([':id' => $idLote, ':e' => $idEmpresa]);
    }

    public function actualizarCabecera(int $id, int $idEmpresa, array $d, int $idUsuario): bool
    {
        $sql = "UPDATE transferencias_lotes SET
                    tipo_lote = :tipo,
                    id_forma_pago_origen = :forma,
                    id_banco_formato = :banco,
                    fecha_pago = :fecha_pago,
                    observaciones = :obs,
                    updated_by = :ub,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false AND estado = 'BORRADOR'";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':tipo'       => $d['tipo_lote'],
            ':forma'      => $d['id_forma_pago_origen'] ?: null,
            ':banco'      => $d['id_banco_formato'] ?: null,
            ':fecha_pago' => $d['fecha_pago'] ?: null,
            ':obs'        => $d['observaciones'] ?? null,
            ':ub'         => $idUsuario,
            ':id'         => $id,
            ':e'          => $idEmpresa,
        ]);
    }

    public function actualizarEstado(int $id, int $idEmpresa, string $estado, array $extra = []): bool
    {
        $sets = ["estado = :estado", "updated_at = CURRENT_TIMESTAMP"];
        $params = [':estado' => $estado, ':id' => $id, ':e' => $idEmpresa];

        $campos = [
            'aprobado_por', 'aprobado_at', 'rechazado_por', 'rechazado_at', 'motivo_rechazo',
            'token_aprobacion', 'archivo_generado_path', 'archivo_generado_at', 'archivo_generado_by',
            'confirmado_por', 'confirmado_at', 'motivo_anulacion', 'anulado_por', 'anulado_at',
        ];
        foreach ($campos as $c) {
            if (array_key_exists($c, $extra)) {
                $sets[] = "$c = :$c";
                $params[":$c"] = $extra[$c];
            }
        }

        $sql = "UPDATE transferencias_lotes SET " . implode(', ', $sets) . " WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    public function setToken(int $id, string $token): void
    {
        $this->db->prepare("UPDATE transferencias_lotes SET token_aprobacion = :t WHERE id = :id")->execute([':t' => $token, ':id' => $id]);
    }

    public function getByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') return null;
        $st = $this->db->prepare(
            "SELECT l.*, u.nombre AS creado_por_nombre
             FROM transferencias_lotes l
             LEFT JOIN usuarios u ON u.id = l.created_by
             WHERE l.token_aprobacion = :t AND l.eliminado = false LIMIT 1"
        );
        $st->execute([':t' => $token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function clearToken(int $id): void
    {
        $this->db->prepare("UPDATE transferencias_lotes SET token_aprobacion = NULL WHERE id = :id")->execute([':id' => $id]);
    }

    public function getNombresUsuarios(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id, nombre, mail FROM usuarios WHERE id IN ($ph)");
        $st->execute($ids);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE transferencias_lotes SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        return $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }
}
