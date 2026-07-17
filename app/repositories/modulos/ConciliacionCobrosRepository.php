<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos de Conciliación de Cobros Bancarios: perfiles de mapeo de
 * columnas, cargas (archivos de extracto subidos) y líneas extraídas de
 * cada carga con su sugerencia de cliente/factura y resultado de aplicación.
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
        $sql = "SELECT fp.id, fp.nombre, fp.tipo_cuenta, fp.numero_cuenta, b.nombre_banco
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

    // ── Perfiles de mapeo ──────────────────────────────────────────────────

    public function getPerfiles(int $idEmpresa): array
    {
        $sql = "SELECT p.*, b.nombre_banco
                FROM conciliacion_perfiles p
                LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
                WHERE p.id_empresa = :id_empresa AND p.eliminado = FALSE AND p.activo = TRUE
                ORDER BY p.nombre_perfil ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPerfilPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM conciliacion_perfiles WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['mapeo_columnas'])) {
            $row['mapeo_columnas'] = json_decode((string) $row['mapeo_columnas'], true) ?: [];
        }
        return $row ?: null;
    }

    public function crearPerfil(array $data): int
    {
        $sql = "INSERT INTO conciliacion_perfiles (
                    id_empresa, id_banco, nombre_perfil, tipo_archivo, fila_inicio,
                    formato_fecha, separador_decimal, mapeo_columnas, created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_banco, :nombre_perfil, :tipo_archivo, :fila_inicio,
                    :formato_fecha, :separador_decimal, :mapeo_columnas, :usuario, :usuario
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_banco' => $data['id_banco'] ?? null,
            ':nombre_perfil' => $data['nombre_perfil'],
            ':tipo_archivo' => $data['tipo_archivo'],
            ':fila_inicio' => $data['fila_inicio'] ?? 0,
            ':formato_fecha' => $data['formato_fecha'] ?? 'd/m/Y',
            ':separador_decimal' => $data['separador_decimal'] ?? '.',
            ':mapeo_columnas' => json_encode($data['mapeo_columnas'], JSON_UNESCAPED_UNICODE),
            ':usuario' => $data['usuario_id'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarPerfil(int $id, array $data): bool
    {
        $sql = "UPDATE conciliacion_perfiles SET
                    id_banco = :id_banco, nombre_perfil = :nombre_perfil, tipo_archivo = :tipo_archivo,
                    fila_inicio = :fila_inicio, formato_fecha = :formato_fecha,
                    separador_decimal = :separador_decimal, mapeo_columnas = :mapeo_columnas,
                    updated_by = :usuario, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id' => $id,
            ':id_empresa' => $data['id_empresa'],
            ':id_banco' => $data['id_banco'] ?? null,
            ':nombre_perfil' => $data['nombre_perfil'],
            ':tipo_archivo' => $data['tipo_archivo'],
            ':fila_inicio' => $data['fila_inicio'] ?? 0,
            ':formato_fecha' => $data['formato_fecha'] ?? 'd/m/Y',
            ':separador_decimal' => $data['separador_decimal'] ?? '.',
            ':mapeo_columnas' => json_encode($data['mapeo_columnas'], JSON_UNESCAPED_UNICODE),
            ':usuario' => $data['usuario_id'],
        ]);
        return $st->rowCount() > 0;
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

    public function getPuntosEmision(int $idEmpresa): array
    {
        $sql = "SELECT pe.id, pe.codigo_punto, pe.id_establecimiento, es.codigo AS cod_establecimiento
                FROM empresa_punto_emision pe
                INNER JOIN empresa_establecimiento es ON es.id = pe.id_establecimiento
                WHERE pe.id_empresa = :id_empresa AND pe.eliminado = FALSE AND es.eliminado = FALSE
                ORDER BY es.codigo ASC, pe.codigo_punto ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPuntoEmision(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT pe.id, pe.codigo_punto, pe.id_establecimiento, es.codigo AS cod_establecimiento
                FROM empresa_punto_emision pe
                INNER JOIN empresa_establecimiento es ON es.id = pe.id_establecimiento
                WHERE pe.id = :id AND pe.id_empresa = :id_empresa AND pe.eliminado = FALSE AND es.eliminado = FALSE";
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
        $sql = "SELECT c.*, fp.nombre AS forma_pago_nombre, p.nombre_perfil,
                       (SELECT COUNT(*) FROM conciliacion_lineas l WHERE l.id_carga = c.id AND l.eliminado = FALSE AND l.estado = 'APLICADO') AS total_aplicadas
                FROM conciliacion_cargas c
                INNER JOIN empresa_formas_pago fp ON fp.id = c.id_forma_pago
                INNER JOIN conciliacion_perfiles p ON p.id = c.id_perfil
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
                    monto_aplicar, created_by, updated_by
                ) VALUES (
                    :id_carga, :id_empresa, :fecha_movimiento, :descripcion_original, :monto, :referencia_banco,
                    :estado, :id_cliente_sugerido, :score_match, :tipo_documento_sugerido, :id_documento_sugerido,
                    :monto_aplicar, :usuario, :usuario
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

    public function actualizarMatchLinea(int $id, array $data): void
    {
        $sql = "UPDATE conciliacion_lineas SET
                    estado = :estado,
                    id_cliente_sugerido = :id_cliente_sugerido,
                    tipo_documento_sugerido = :tipo_documento_sugerido,
                    id_documento_sugerido = :id_documento_sugerido,
                    monto_aplicar = :monto_aplicar,
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
        ]);
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
