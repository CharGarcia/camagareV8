<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Condominios — cobros desde la pestaña Condóminos de Configuración de condominios:
 *  - emisión en bloque de recibos o facturas (condominios_emisiones + _items), que procesa un
 *    worker en segundo plano;
 *  - cruce de los condóminos con sus suscripciones, para no duplicarlas al agregarles un cobro
 *    recurrente.
 * Acceso a datos puro; toda consulta operativa filtra id_empresa + eliminado = false.
 * SQL: database/migrations/20261009_condominios_emisiones.sql.
 */
class CondominioCobroRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('condominios_emisiones');
    }

    public function instalado(): bool
    {
        return $this->tablaExiste('condominios_emisiones') && $this->tablaExiste('condominios_emisiones_items');
    }

    /** ¿Está el enlace suscripción ↔ inmueble (suscripciones.id_unidad)? */
    public function suscripcionTieneInmueble(): bool
    {
        return $this->columnaExiste('suscripciones', 'id_unidad');
    }

    // ── Catálogos ────────────────────────────────────────────────────────────

    /** Concepto a cobrar: servicio de la empresa con su tarifa de IVA (para la línea del documento). */
    public function getProductoCobro(int $idProducto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.nombre, p.codigo, p.tipo_produccion, p.tarifa_iva AS id_tarifa_iva,
                    COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva, COALESCE(ti.codigo::text, '0') AS codigo_porcentaje
               FROM productos p LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
              WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false"
        );
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Establecimiento + punto de emisión de la serie elegida (lo que espera la generación del documento). */
    public function getSerie(int $idPunto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT ep.*, pe.id AS id_punto_emision, pe.codigo_punto AS punto_emision_codigo
               FROM empresa_establecimiento ep
               JOIN empresa_punto_emision pe ON pe.id_establecimiento = ep.id
              WHERE pe.id = :p AND ep.id_empresa = :e AND ep.estado = 'activo' AND pe.eliminado = false AND ep.eliminado = false
              LIMIT 1"
        );
        $st->execute([':p' => $idPunto, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Destinatarios ────────────────────────────────────────────────────────

    /** Clientes de la empresa por id (los marcados en la pestaña). */
    public function getClientes(int $idEmpresa, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (!$ids) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT c.id, c.nombre, c.identificacion, c.email, c.status
               FROM clientes c
              WHERE c.id_empresa = :e AND c.eliminado = false AND c.id = ANY(:ids::int[])
              ORDER BY c.nombre, c.id"
        );
        $st->execute([':e' => $idEmpresa, ':ids' => '{' . implode(',', $ids) . '}']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Inmuebles activos que PAGA cada cliente (pagador = propietario o arrendatario). */
    public function getInmueblesPagados(int $idEmpresa, array $idsClientes): array
    {
        if (!$idsClientes) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT u.id, u.codigo, u.nombre, u.tipo,
                    CASE WHEN u.pagador = 'arrendatario' THEN u.id_arrendatario ELSE u.id_propietario END AS id_pagador
               FROM condominios_unidades u
              WHERE u.id_empresa = :e AND u.eliminado = false AND u.estado = 'activo'
                AND (CASE WHEN u.pagador = 'arrendatario' THEN u.id_arrendatario ELSE u.id_propietario END) = ANY(:ids::int[])
              ORDER BY u.codigo, u.id"
        );
        $st->execute([':e' => $idEmpresa, ':ids' => '{' . implode(',', array_map('intval', $idsClientes)) . '}']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Suscripciones vigentes (activas o pausadas) de los clientes, con la línea del concepto si
     * la tienen. Base para no duplicar: una fila por suscripción.
     */
    public function getSuscripcionesClientes(int $idEmpresa, array $idsClientes, int $idProducto): array
    {
        if (!$idsClientes) {
            return [];
        }
        $colUnidad = $this->suscripcionTieneInmueble() ? 's.id_unidad' : 'NULL::int';
        $st = $this->db->prepare(
            "SELECT s.id, s.id_cliente, {$colUnidad} AS id_unidad, s.estado, s.tipo_comprobante, s.id_periodicidad,
                    d.id AS id_detalle, d.precio_unitario AS precio_actual
               FROM suscripciones s
               LEFT JOIN LATERAL (
                    SELECT sd.id, sd.precio_unitario FROM suscripciones_detalle sd
                     WHERE sd.id_suscripcion = s.id AND sd.eliminado = false AND sd.id_producto = :p
                     ORDER BY sd.id LIMIT 1
               ) d ON true
              WHERE s.id_empresa = :e AND s.eliminado = false AND s.estado IN ('activo', 'pausado')
                AND s.id_cliente = ANY(:ids::int[])
              ORDER BY s.id"
        );
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto, ':ids' => '{' . implode(',', array_map('intval', $idsClientes)) . '}']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Emisiones ────────────────────────────────────────────────────────────

    public function insertEmision(int $idEmpresa, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO condominios_emisiones
                (id_empresa, descripcion, tipo_comprobante, id_punto_emision, id_producto, forma_valor, valor, agrupar,
                 texto_item, enviar_correo, estado, total_items, total_valor, created_at, created_by)
             VALUES (:e, :descripcion, :tipo, :punto, :producto, :forma, :valor, :agrupar,
                 :texto, :correo, 'pendiente', :total_items, :total_valor, CURRENT_TIMESTAMP, :u)
             RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':descripcion' => $d['descripcion'], ':tipo' => $d['tipo_comprobante'],
            ':punto' => $d['id_punto_emision'], ':producto' => $d['id_producto'], ':forma' => $d['forma_valor'],
            ':valor' => $d['valor'] ?? 0, ':agrupar' => $d['agrupar'], ':texto' => $d['texto_item'] !== '' ? $d['texto_item'] : null,
            ':correo' => $d['enviar_correo'] ? 'true' : 'false', ':total_items' => $d['total_items'], ':total_valor' => $d['total_valor'],
            ':u' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function insertItem(int $idEmision, int $idEmpresa, array $f, bool $enviarCorreo, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "INSERT INTO condominios_emisiones_items
                (id_emision, id_empresa, id_cliente, id_unidad, inmueble_texto, valor, email, estado, estado_correo, created_at, created_by)
             VALUES (:em, :e, :c, :u, :inm, :v, :email, 'pendiente', :correo, CURRENT_TIMESTAMP, :usr)"
        );
        $st->execute([
            ':em' => $idEmision, ':e' => $idEmpresa, ':c' => $f['id_cliente'], ':u' => $f['id_unidad'] ?: null,
            ':inm' => $f['inmueble'] !== '' ? mb_substr($f['inmueble'], 0, 300) : null, ':v' => $f['valor'],
            ':email' => $f['email'] !== '' ? mb_substr($f['email'], 0, 300) : null,
            ':correo' => $enviarCorreo ? 'pendiente' : 'no_aplica', ':usr' => $idUsuario,
        ]);
    }

    /** Emisión por id; sin $idEmpresa la usa el worker CLI (no hay sesión). */
    public function getEmision(int $id, ?int $idEmpresa = null): ?array
    {
        $sql = "SELECT em.*, p.nombre AS producto_nombre, us.nombre AS usuario_nombre,
                       ep.codigo || '-' || pe.codigo_punto AS serie
                  FROM condominios_emisiones em
                  LEFT JOIN productos p ON p.id = em.id_producto
                  LEFT JOIN usuarios us ON us.id = em.created_by
                  LEFT JOIN empresa_punto_emision pe ON pe.id = em.id_punto_emision
                  LEFT JOIN empresa_establecimiento ep ON ep.id = pe.id_establecimiento
                 WHERE em.id = :id AND em.eliminado = false" . ($idEmpresa !== null ? ' AND em.id_empresa = :e' : '');
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id] + ($idEmpresa !== null ? [':e' => $idEmpresa] : []));
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getEmisiones(int $idEmpresa, int $limite = 50): array
    {
        $st = $this->db->prepare(
            "SELECT em.id, em.descripcion, em.tipo_comprobante, em.estado, em.total_items, em.generados, em.fallidos,
                    em.correos_enviados, em.total_valor, em.enviar_correo, em.created_at, em.finalizado_at,
                    p.nombre AS producto_nombre, us.nombre AS usuario_nombre
               FROM condominios_emisiones em
               LEFT JOIN productos p ON p.id = em.id_producto
               LEFT JOIN usuarios us ON us.id = em.created_by
              WHERE em.id_empresa = :e AND em.eliminado = false
              ORDER BY em.id DESC LIMIT " . max(1, $limite)
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getItems(int $idEmision, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT i.*, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion
               FROM condominios_emisiones_items i
               JOIN clientes c ON c.id = i.id_cliente
              WHERE i.id_emision = :em AND i.id_empresa = :e AND i.eliminado = false
              ORDER BY c.nombre, i.id"
        );
        $st->execute([':em' => $idEmision, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ítem con los datos del cliente que necesita la generación (worker). */
    public function getItemParaGenerar(int $idItem): ?array
    {
        $st = $this->db->prepare(
            "SELECT i.*, c.nombre AS cliente_nombre, c.email AS cliente_email_actual
               FROM condominios_emisiones_items i JOIN clientes c ON c.id = i.id_cliente
              WHERE i.id = :id"
        );
        $st->execute([':id' => $idItem]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Toma el siguiente ítem pendiente (un solo worker por emisión: ver candado en el Service). */
    public function reclamarSiguienteItem(int $idEmision): ?int
    {
        $st = $this->db->prepare(
            "UPDATE condominios_emisiones_items SET estado = 'procesando', updated_at = CURRENT_TIMESTAMP
              WHERE id = (SELECT id FROM condominios_emisiones_items
                           WHERE id_emision = :em AND eliminado = false AND estado = 'pendiente'
                           ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED)
              RETURNING id"
        );
        $st->execute([':em' => $idEmision]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /**
     * Ítems que quedaron «procesando» por un worker que se cortó: pudieron haber creado ya su
     * documento, así que NO se reintentan (sería duplicarlo); quedan para revisar.
     */
    public function marcarInterrumpidos(int $idEmision): int
    {
        $st = $this->db->prepare(
            "UPDATE condominios_emisiones_items
                SET estado = 'revisar', updated_at = CURRENT_TIMESTAMP,
                    mensaje = 'El proceso se interrumpió en este documento: verifique en Recibos/Facturas si se creó antes de volver a emitirlo.'
              WHERE id_emision = :em AND eliminado = false AND estado = 'procesando'"
        );
        $st->execute([':em' => $idEmision]);
        return $st->rowCount();
    }

    public function actualizarItem(int $idItem, array $campos): void
    {
        $permitidos = ['estado', 'id_factura', 'id_recibo', 'numero', 'estado_correo', 'mensaje'];
        $set = [];
        $params = [':id' => $idItem];
        foreach ($campos as $k => $v) {
            if (in_array($k, $permitidos, true)) {
                $set[] = "{$k} = :{$k}";
                $params[":{$k}"] = $v;
            }
        }
        if (!$set) {
            return;
        }
        $st = $this->db->prepare('UPDATE condominios_emisiones_items SET ' . implode(', ', $set) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $st->execute($params);
    }

    public function marcarEmision(int $id, string $estado, bool $inicio = false, bool $fin = false): void
    {
        $st = $this->db->prepare(
            "UPDATE condominios_emisiones SET estado = :s, updated_at = CURRENT_TIMESTAMP"
            . ($inicio ? ', iniciado_at = COALESCE(iniciado_at, CURRENT_TIMESTAMP)' : '')
            . ($fin ? ', finalizado_at = CURRENT_TIMESTAMP' : '')
            . " WHERE id = :id"
        );
        $st->execute([':s' => $estado, ':id' => $id]);
    }

    /** Recalcula los contadores de la emisión desde sus ítems (siempre cuadran con el detalle). */
    public function recontar(int $id): array
    {
        $st = $this->db->prepare(
            "UPDATE condominios_emisiones em SET
                    generados = x.generados, fallidos = x.fallidos, correos_enviados = x.correos, updated_at = CURRENT_TIMESTAMP
               FROM (SELECT COUNT(*) FILTER (WHERE estado IN ('generado', 'autorizado', 'en_procesamiento')) AS generados,
                            COUNT(*) FILTER (WHERE estado IN ('error', 'revisar')) AS fallidos,
                            COUNT(*) FILTER (WHERE estado_correo = 'enviado') AS correos,
                            COUNT(*) FILTER (WHERE estado IN ('pendiente', 'procesando')) AS pendientes
                       FROM condominios_emisiones_items WHERE id_emision = :id AND eliminado = false) x
              WHERE em.id = :id
              RETURNING em.generados, em.fallidos, em.correos_enviados, x.pendientes"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['generados' => 0, 'fallidos' => 0, 'correos_enviados' => 0, 'pendientes' => 0];
    }

    /** Emisiones que el cron debe retomar (no se pudo lanzar el worker o se cortó). */
    public function getEmisionesAbiertas(): array
    {
        return $this->db->query(
            "SELECT id FROM condominios_emisiones
              WHERE eliminado = false AND estado IN ('pendiente', 'procesando')
                AND created_at < CURRENT_TIMESTAMP - INTERVAL '5 minutes'
              ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Candado de sesión (no transaccional): un solo worker por emisión. */
    public function tomarCandado(int $idEmision): bool
    {
        $st = $this->db->prepare("SELECT pg_try_advisory_lock(hashtext('cond_emision:' || :id::text))");
        $st->execute([':id' => $idEmision]);
        return (bool) $st->fetchColumn();
    }

    public function soltarCandado(int $idEmision): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_unlock(hashtext('cond_emision:' || :id::text))");
        $st->execute([':id' => $idEmision]);
    }

    /** Estado de correo de una factura (lo deja en 'enviado' el envío automático tras autorizar). */
    public function estadoCorreoFactura(int $idFactura): ?string
    {
        $st = $this->db->prepare("SELECT estado_correo FROM ventas_cabecera WHERE id = :id");
        $st->execute([':id' => $idFactura]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    public function marcarCorreoFacturaEnviado(int $idFactura): void
    {
        $this->db->prepare("UPDATE ventas_cabecera SET estado_correo = 'enviado', updated_at = NOW() WHERE id = :id")->execute([':id' => $idFactura]);
    }
}
