<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos de Conciliación de Cobros Bancarios: cargas (archivos de
 * extracto subidos) y líneas extraídas de cada carga con su sugerencia de
 * cliente/factura y resultado de aplicación. Los perfiles de mapeo son un
 * catálogo global: ver App\repositories\ConciliacionPerfilRepository.
 */
class ConciliacionCobrosRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('conciliacion_cargas');
    }

    // ── Cuentas bancarias (mismo criterio que ControlBancarioRepository) ─────

    public function getCuentasBancarias(int $idEmpresa): array
    {
        $sql = "SELECT fp.id, fp.nombre, fp.tipo_cuenta, fp.numero_cuenta, fp.id_banco, b.nombre_banco
                FROM empresa_formas_pago fp
                LEFT JOIN bancos_ecuador b ON b.id = fp.id_banco
                WHERE fp.id_empresa = :id_empresa
                  AND fp.eliminado = FALSE
                  AND fp.activo = TRUE
                  AND fp.id_banco IS NOT NULL
                  AND (fp.aplica_en = 'AMBAS' OR fp.aplica_en = 'INGRESO')
                ORDER BY fp.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Cargas (archivos subidos) ──────────────────────────────────────────

    public function crearCarga(array $data): int
    {
        $sql = "INSERT INTO conciliacion_cargas (
                    id_empresa, id_forma_pago, id_punto_emision, id_perfil, nombre_archivo, ruta_archivo,
                    tipo_archivo, total_lineas, estado, created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_forma_pago, :id_punto_emision, :id_perfil, :nombre_archivo, :ruta_archivo,
                    :tipo_archivo, 0, 'procesando', :usuario, :usuario
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_forma_pago' => $data['id_forma_pago'],
            ':id_punto_emision' => $data['id_punto_emision'],
            ':id_perfil' => $data['id_perfil'],
            ':nombre_archivo' => $data['nombre_archivo'],
            ':ruta_archivo' => $data['ruta_archivo'],
            ':tipo_archivo' => $data['tipo_archivo'],
            ':usuario' => $data['usuario_id'],
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Puntos de emisión activos (select de la carga). Las series inactivas no se ofrecen:
     * los ingresos que genera la carga se numeran en esa serie.
     */
    public function getPuntosEmision(int $idEmpresa): array
    {
        $sql = "SELECT pe.id, pe.codigo_punto, pe.id_establecimiento, es.codigo AS cod_establecimiento
                FROM empresa_punto_emision pe
                INNER JOIN empresa_establecimiento es ON es.id = pe.id_establecimiento
                WHERE pe.id_empresa = :id_empresa AND pe.eliminado = FALSE AND es.eliminado = FALSE
                  AND LOWER(COALESCE(pe.estado, '')) = 'activo'
                ORDER BY es.codigo ASC, pe.codigo_punto ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Punto de emisión con el que se numeran los ingresos de una carga: solo si está activo.
     * Se valida al subir el extracto y otra vez al generar los ingresos, por si la serie se
     * inactivó entre medio.
     */
    public function getPuntoEmision(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT pe.id, pe.codigo_punto, pe.id_establecimiento, es.codigo AS cod_establecimiento
                FROM empresa_punto_emision pe
                INNER JOIN empresa_establecimiento es ON es.id = pe.id_establecimiento
                WHERE pe.id = :id AND pe.id_empresa = :id_empresa AND pe.eliminado = FALSE AND es.eliminado = FALSE
                  AND LOWER(COALESCE(pe.estado, '')) = 'activo'";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getCuentaBancariaPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT fp.id, fp.nombre
                FROM empresa_formas_pago fp
                WHERE fp.id = :id AND fp.id_empresa = :id_empresa AND fp.eliminado = FALSE AND fp.id_banco IS NOT NULL";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getCargaPorId(int $id, int $idEmpresa): ?array
    {
        return $this->findById($id, $idEmpresa);
    }

    public function listarCargas(int $idEmpresa, int $limite = 30): array
    {
        // LEFT JOIN a propósito: si la cuenta bancaria o el formato de una carga se eliminó
        // después, la carga (y sus líneas ya conciliadas) debe seguir apareciendo en el historial.
        $sql = "SELECT c.*, COALESCE(fp.nombre, '— cuenta eliminada —') AS forma_pago_nombre,
                       COALESCE(p.nombre_perfil, '—') AS nombre_perfil,
                       (SELECT COUNT(*) FROM conciliacion_lineas l WHERE l.id_carga = c.id AND l.eliminado = FALSE AND l.estado = 'APLICADO') AS total_aplicadas
                FROM conciliacion_cargas c
                LEFT JOIN empresa_formas_pago fp ON fp.id = c.id_forma_pago AND fp.id_empresa = c.id_empresa
                LEFT JOIN conciliacion_perfiles p ON p.id = c.id_perfil
                WHERE c.id_empresa = :id_empresa AND c.eliminado = FALSE
                ORDER BY c.created_at DESC
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function actualizarEstadoCarga(int $id, string $estado, ?string $mensajeError = null, ?int $totalLineas = null): void
    {
        $sql = "UPDATE conciliacion_cargas SET
                    estado = :estado,
                    mensaje_error = :mensaje_error,
                    total_lineas = COALESCE(:total_lineas, total_lineas),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id' => $id,
            ':estado' => $estado,
            ':mensaje_error' => $mensajeError,
            ':total_lineas' => $totalLineas,
        ]);
    }

    // ── Líneas ──────────────────────────────────────────────────────────────

    public function insertLinea(array $data): int
    {
        $sql = "INSERT INTO conciliacion_lineas (
                    id_carga, id_empresa, fecha_movimiento, descripcion_original, monto, referencia_banco,
                    estado, id_cliente_sugerido, score_match, tipo_documento_sugerido, id_documento_sugerido,
                    monto_aplicar, id_linea_origen, mensaje_error, created_by, updated_by
                ) VALUES (
                    :id_carga, :id_empresa, :fecha_movimiento, :descripcion_original, :monto, :referencia_banco,
                    :estado, :id_cliente_sugerido, :score_match, :tipo_documento_sugerido, :id_documento_sugerido,
                    :monto_aplicar, :id_linea_origen, :mensaje_error, :usuario, :usuario
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_carga' => $data['id_carga'],
            ':id_empresa' => $data['id_empresa'],
            ':fecha_movimiento' => $data['fecha_movimiento'],
            ':descripcion_original' => $data['descripcion_original'],
            ':monto' => $data['monto'],
            ':referencia_banco' => $data['referencia_banco'] ?? null,
            ':estado' => $data['estado'] ?? 'SIN_MATCH',
            ':id_cliente_sugerido' => $data['id_cliente_sugerido'] ?? null,
            ':score_match' => $data['score_match'] ?? null,
            ':tipo_documento_sugerido' => $data['tipo_documento_sugerido'] ?? null,
            ':id_documento_sugerido' => $data['id_documento_sugerido'] ?? null,
            ':monto_aplicar' => $data['monto_aplicar'] ?? $data['monto'],
            ':id_linea_origen' => $data['id_linea_origen'] ?? null,
            ':mensaje_error' => $data['mensaje_error'] ?? null,
            ':usuario' => $data['usuario_id'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function getLineasPorCarga(int $idCarga, int $idEmpresa): array
    {
        $sql = "SELECT l.*, cli.nombre AS cliente_sugerido_nombre
                FROM conciliacion_lineas l
                LEFT JOIN clientes cli ON cli.id = l.id_cliente_sugerido
                WHERE l.id_carga = :id_carga AND l.id_empresa = :id_empresa AND l.eliminado = FALSE
                ORDER BY l.fecha_movimiento ASC, l.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_carga' => $idCarga, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getLineaPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM conciliacion_lineas WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Actualiza el estado y el resumen de la línea. El cliente/documento de la línea son el
     * espejo del PRIMER detalle (ver reemplazarDetalles) y monto_aplicar la SUMA de los detalles.
     */
    public function actualizarMatchLinea(int $id, array $data): void
    {
        $sql = "UPDATE conciliacion_lineas SET
                    estado = :estado,
                    id_cliente_sugerido = :id_cliente_sugerido,
                    tipo_documento_sugerido = :tipo_documento_sugerido,
                    id_documento_sugerido = :id_documento_sugerido,
                    monto_aplicar = :monto_aplicar,
                    mensaje_error = NULL,
                    updated_by = COALESCE(:usuario, updated_by),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id' => $id,
            ':estado' => $data['estado'],
            ':id_cliente_sugerido' => $data['id_cliente_sugerido'] ?? null,
            ':tipo_documento_sugerido' => $data['tipo_documento_sugerido'] ?? null,
            ':id_documento_sugerido' => $data['id_documento_sugerido'] ?? null,
            ':monto_aplicar' => $data['monto_aplicar'] ?? null,
            ':usuario' => $data['usuario_id'] ?? null,
        ]);
    }

    // ── Detalle de cada línea: documentos con los que se completa el depósito ────────────

    /**
     * Documentos asignados a varias líneas, con el nombre del cliente, agrupados por línea.
     *
     * @param int[] $idsLineas
     * @return array<int, array<int, array<string, mixed>>> id_linea => detalles en orden
     */
    public function getDetallesPorLineas(array $idsLineas, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsLineas))));
        if (!$ids) {
            return [];
        }
        $sql = "SELECT d.id, d.id_linea, d.id_cliente, d.tipo_documento, d.id_documento, d.numero_documento,
                       d.monto_aplicar, d.orden, cli.nombre AS cliente_nombre
                FROM conciliacion_lineas_detalle d
                LEFT JOIN clientes cli ON cli.id = d.id_cliente
                WHERE d.id_linea = ANY(CAST(:ids AS int[])) AND d.id_empresa = :id_empresa AND d.eliminado = FALSE
                ORDER BY d.id_linea ASC, d.orden ASC, d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':ids' => '{' . implode(',', $ids) . '}', ':id_empresa' => $idEmpresa]);

        $porLinea = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $r['id'] = (int) $r['id'];
            $r['id_linea'] = (int) $r['id_linea'];
            $r['id_cliente'] = (int) $r['id_cliente'];
            $r['id_documento'] = (int) $r['id_documento'];
            $r['monto_aplicar'] = round((float) $r['monto_aplicar'], 2);
            $porLinea[$r['id_linea']][] = $r;
        }
        return $porLinea;
    }

    /** @return array<int, array<string, mixed>> */
    public function getDetallesPorLinea(int $idLinea, int $idEmpresa): array
    {
        return $this->getDetallesPorLineas([$idLinea], $idEmpresa)[$idLinea] ?? [];
    }

    /**
     * Sustituye los documentos asignados a la línea (baja lógica de los anteriores + alta de
     * los nuevos, en el orden recibido) y deja en la línea el espejo: cliente/documento del
     * primer detalle y monto_aplicar = suma. Llamar dentro de la transacción que ya tomó
     * lockLinea().
     *
     * @param array $detalles [['id_cliente', 'tipo_documento', 'id_documento', 'numero_documento', 'monto_aplicar'], ...]
     */
    public function reemplazarDetalles(int $idLinea, int $idEmpresa, array $detalles, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE conciliacion_lineas_detalle
                                  SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usuario, updated_at = CURRENT_TIMESTAMP
                                  WHERE id_linea = :id_linea AND eliminado = FALSE");
        $st->execute([':id_linea' => $idLinea, ':usuario' => $idUsuario]);

        $ins = $this->db->prepare("INSERT INTO conciliacion_lineas_detalle (
                    id_linea, id_empresa, id_cliente, tipo_documento, id_documento, numero_documento, monto_aplicar, orden, created_by, updated_by
                ) VALUES (
                    :id_linea, :id_empresa, :id_cliente, :tipo_documento, :id_documento, :numero_documento, :monto_aplicar, :orden, :usuario, :usuario
                )");
        foreach (array_values($detalles) as $i => $d) {
            $ins->execute([
                ':id_linea' => $idLinea,
                ':id_empresa' => $idEmpresa,
                ':id_cliente' => (int) $d['id_cliente'],
                ':tipo_documento' => strtoupper((string) $d['tipo_documento']),
                ':id_documento' => (int) $d['id_documento'],
                ':numero_documento' => isset($d['numero_documento']) ? mb_substr((string) $d['numero_documento'], 0, 50) : null,
                ':monto_aplicar' => round((float) $d['monto_aplicar'], 2),
                ':orden' => $i,
                ':usuario' => $idUsuario,
            ]);
        }
    }

    /** Espejo de resumen para una línea a partir de sus detalles (primer documento + suma). */
    public static function resumenDeDetalles(array $detalles, ?int $idClienteRespaldo = null): array
    {
        $primero = $detalles[0] ?? null;
        return [
            'id_cliente_sugerido' => $primero ? (int) $primero['id_cliente'] : $idClienteRespaldo,
            'tipo_documento_sugerido' => $primero ? strtoupper((string) $primero['tipo_documento']) : null,
            'id_documento_sugerido' => $primero ? (int) $primero['id_documento'] : null,
            'monto_aplicar' => $primero ? round(array_sum(array_map(fn ($d) => (float) $d['monto_aplicar'], $detalles)), 2) : null,
        ];
    }

    /**
     * Candado transaccional sobre una línea del extracto (CLAUDE.md §8): se toma antes de
     * releerla al confirmarla o cobrarla, para que dos usuarios no le cambien los documentos
     * a la vez. Se libera solo al COMMIT/ROLLBACK de la transacción del llamador.
     */
    public function lockLinea(int $id): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('conciliacion_linea:' || :id))");
        $st->execute([':id' => $id]);
    }

    /**
     * Candado transaccional sobre un documento por cobrar (CLAUDE.md §8): se toma antes de
     * leer cuánto de su saldo ya está apartado por otras líneas confirmadas, para que dos
     * confirmaciones simultáneas no aparten el mismo saldo dos veces.
     */
    public function lockDocumento(int $idEmpresa, string $tipoDocumento, int $idDocumento): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('conciliacion_doc:' || :e || ':' || :t || ':' || :d))");
        $st->execute([':e' => $idEmpresa, ':t' => $tipoDocumento, ':d' => $idDocumento]);
    }

    /**
     * Monto ya apartado para cada documento por líneas CONFIRMADO que todavía no generaron su
     * ingreso (de cualquier carga de la empresa), sumando los detalles de esas líneas. Ese
     * monto aún no descuenta el saldo de la cuenta por cobrar, así que hay que restarlo para no
     * cobrar el mismo saldo dos veces.
     *
     * @param int[] $excluirLineas Líneas que no cuentan (la que se está editando).
     * @return array<string,float> 'TIPO:id' => monto apartado
     */
    public function getMontosApartados(int $idEmpresa, array $excluirLineas = [], ?string $tipoDocumento = null, ?int $idDocumento = null): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $filtro = '';
        if ($tipoDocumento !== null && $idDocumento !== null) {
            $filtro .= ' AND d.tipo_documento = :tipo AND d.id_documento = :id_doc';
            $params[':tipo'] = $tipoDocumento;
            $params[':id_doc'] = $idDocumento;
        }
        $excluir = array_values(array_filter(array_map('intval', $excluirLineas)));
        if ($excluir) {
            $filtro .= ' AND l.id <> ALL(CAST(:excluir AS int[]))';
            $params[':excluir'] = '{' . implode(',', $excluir) . '}';
        }

        $sql = "SELECT d.tipo_documento AS tipo, d.id_documento AS id_doc, SUM(d.monto_aplicar) AS apartado
                FROM conciliacion_lineas_detalle d
                INNER JOIN conciliacion_lineas l ON l.id = d.id_linea AND l.eliminado = FALSE AND l.estado = 'CONFIRMADO'
                INNER JOIN conciliacion_cargas c ON c.id = l.id_carga AND c.eliminado = FALSE
                WHERE d.id_empresa = :id_empresa AND d.eliminado = FALSE
                  {$filtro}
                GROUP BY d.tipo_documento, d.id_documento";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $mapa[$r['tipo'] . ':' . (int) $r['id_doc']] = round((float) $r['apartado'], 2);
        }
        return $mapa;
    }

    /**
     * Candado de SESIÓN para generar los ingresos de una carga: evita que dos pulsaciones de
     * "Generar ingresos" (doble clic, dos usuarios) procesen las mismas líneas a la vez. Es de
     * sesión y no transaccional porque cada grupo de líneas va en su propia transacción.
     * Devuelve false si otra sesión ya lo tiene.
     */
    public function tomarCandadoGeneracion(int $idCarga): bool
    {
        $st = $this->db->prepare("SELECT pg_try_advisory_lock(hashtext('conciliacion_generar:' || :id))");
        $st->execute([':id' => $idCarga]);
        return (bool) $st->fetchColumn();
    }

    public function soltarCandadoGeneracion(int $idCarga): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_unlock(hashtext('conciliacion_generar:' || :id))");
        $st->execute([':id' => $idCarga]);
    }

    /**
     * ¿Este movimiento del banco ya está en OTRA carga de la misma cuenta? Pasa al subir
     * extractos que se solapan en fechas. Se compara por fecha, referencia, descripción del
     * banco y monto. Las líneas históricas que se dividieron en partes (antes del detalle por
     * línea) comparten id_linea_origen y se suman para contar como un solo depósito. No cuentan
     * las cargas eliminadas ni los movimientos cuyas líneas quedaron todas ignoradas.
     *
     * @return array|null ['id_carga', 'nombre_archivo', 'created_at', 'id_ingreso'] de la primera coincidencia
     */
    public function buscarMovimientoRepetido(int $idEmpresa, int $idFormaPago, int $idCargaActual, string $fecha, float $monto, ?string $referencia, string $descripcion): ?array
    {
        $sql = "WITH candidatas AS (
                    SELECT l.id, l.id_carga, l.estado, l.monto, l.id_ingreso_generado,
                           COALESCE(l.id_linea_origen, l.id) AS grupo,
                           c.nombre_archivo, c.created_at
                    FROM conciliacion_lineas l
                    INNER JOIN conciliacion_cargas c ON c.id = l.id_carga AND c.eliminado = FALSE
                    WHERE l.id_empresa = :id_empresa AND l.eliminado = FALSE
                      AND c.id_forma_pago = :id_forma_pago
                      AND l.id_carga <> :id_carga
                      AND l.fecha_movimiento = :fecha
                      AND COALESCE(l.referencia_banco, '') = COALESCE(:referencia, '')
                      AND l.descripcion_original NOT LIKE '%(diferencia de pago parcial)'
                      AND split_part(l.descripcion_original, ' (parte ', 1) = :descripcion
                )
                SELECT MIN(id_carga) AS id_carga, MIN(nombre_archivo) AS nombre_archivo, MIN(created_at) AS created_at,
                       MAX(id_ingreso_generado) AS id_ingreso
                FROM candidatas
                GROUP BY grupo
                HAVING ROUND(SUM(monto), 2) = ROUND(CAST(:monto AS numeric), 2)
                   AND bool_or(estado <> 'IGNORADO')
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':id_forma_pago' => $idFormaPago,
            ':id_carga' => $idCargaActual,
            ':fecha' => $fecha,
            ':referencia' => $referencia,
            ':descripcion' => $descripcion,
            ':monto' => $monto,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function marcarLineaIgnorada(int $id): void
    {
        $sql = "UPDATE conciliacion_lineas SET estado = 'IGNORADO', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
    }

    /**
     * Vuelve la línea a SUGERIDO sin perder el cliente/documento/monto ya elegidos. Se usa tanto
     * para quitar una confirmación puesta por error como para reactivar una línea ignorada.
     */
    public function desconfirmarLinea(int $id): void
    {
        $sql = "UPDATE conciliacion_lineas SET estado = 'SUGERIDO', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
    }

    public function marcarLineaAplicada(int $id, int $idIngreso): void
    {
        $sql = "UPDATE conciliacion_lineas SET
                    estado = 'APLICADO', id_ingreso_generado = :id_ingreso, mensaje_error = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_ingreso' => $idIngreso]);
    }

    /**
     * Vuelve una línea APLICADO a SUGERIDO cuando el Ingreso que generó fue anulado o
     * eliminado después (fuera de este módulo) — conserva el cliente/documento/monto ya
     * elegidos para que el usuario solo tenga que confirmar de nuevo y regenerar el cobro,
     * sin tener que volver a subir el extracto.
     */
    public function revertirLineaAplicada(int $id): void
    {
        $sql = "UPDATE conciliacion_lineas SET
                    estado = 'SUGERIDO', id_ingreso_generado = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
    }

    public function marcarLineaError(int $id, string $mensaje): void
    {
        $sql = "UPDATE conciliacion_lineas SET estado = 'ERROR', mensaje_error = :mensaje, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':mensaje' => $mensaje]);
    }

    // ── Clientes (para matching en PHP) ────────────────────────────────────

    public function getClientesActivos(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, identificacion
                FROM clientes
                WHERE id_empresa = :id_empresa AND eliminado = false AND status = 1
                ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
