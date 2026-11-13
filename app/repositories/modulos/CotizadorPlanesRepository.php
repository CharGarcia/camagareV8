<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Cotizador de Planes: único punto de acceso a sus tablas (CLAUDE.md §3).
 * Todas son operativas: cada consulta filtra por id_empresa y eliminado = false.
 * Esquema: database/2026-11-13_cotizador_planes.sql
 */
class CotizadorPlanesRepository extends BaseRepository
{
    public const MAPA_ORDEN = [
        'numero'           => 'c.numero',
        'fecha'            => 'c.fecha',
        'identificacion'   => 'c.identificacion',
        'razon_social'     => 'c.razon_social',
        'establecimientos' => 'n_est.total',
        'usuarios'         => 'c.usuarios',
        'modalidad'        => 'c.modalidad',
        'total_anual'      => 'c.total_anual',
        'total_mensual'    => 'c.total_mensual',
        'total_unico'      => 'c.total_unico',
        'vendedor'         => 'u.nombre',
        'estado'           => 'c.estado',
        'created_at'       => 'c.created_at',
    ];

    public function __construct()
    {
        parent::__construct('cotizaciones_planes');
    }

    /** ¿Ya se ejecutó el SQL del módulo? Sin las tablas la pantalla avisa en vez de fallar. */
    public function disponible(): bool
    {
        return $this->tablaExiste('cotizaciones_planes') && $this->tablaExiste('cotizador_planes_items');
    }

    // ── Configuración ────────────────────────────────────────────────────────

    public function getConfig(int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM cotizador_planes_config WHERE id_empresa = :e AND eliminado = false LIMIT 1");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function guardarConfig(int $idEmpresa, array $d, int $idUsuario): int
    {
        $actual = $this->getConfig($idEmpresa);
        $params = [
            ':rec'  => $d['recargo_mensual_pct'],
            ':dest' => $d['descuento_establecimiento_pct'],
            ':upe'  => $d['usuarios_por_establecimiento'],
            ':pua'  => $d['precio_usuario_adicional_anual'],
            ':pdv'  => $d['permitir_descuento_vendedor'] ? 'true' : 'false',
            ':dvm'  => $d['descuento_vendedor_max_pct'],
            ':pra'  => $d['id_producto_resumen_anual'] ?: null,
            ':prm'  => $d['id_producto_resumen_mensual'] ?: null,
            ':dv'   => $d['dias_vigencia'],
            ':u'    => $idUsuario,
            ':e'    => $idEmpresa,
        ];
        if ($actual) {
            $this->db->prepare(
                "UPDATE cotizador_planes_config SET
                    recargo_mensual_pct = :rec, descuento_establecimiento_pct = :dest,
                    usuarios_por_establecimiento = :upe, precio_usuario_adicional_anual = :pua,
                    permitir_descuento_vendedor = :pdv, descuento_vendedor_max_pct = :dvm,
                    id_producto_resumen_anual = :pra, id_producto_resumen_mensual = :prm,
                    dias_vigencia = :dv, updated_at = NOW(), updated_by = :u
                  WHERE id_empresa = :e AND eliminado = false"
            )->execute($params);
            return (int) $actual['id'];
        }
        $st = $this->db->prepare(
            "INSERT INTO cotizador_planes_config
                (id_empresa, recargo_mensual_pct, descuento_establecimiento_pct, usuarios_por_establecimiento,
                 precio_usuario_adicional_anual, permitir_descuento_vendedor, descuento_vendedor_max_pct,
                 id_producto_resumen_anual, id_producto_resumen_mensual, dias_vigencia, created_by, updated_by)
             VALUES (:e, :rec, :dest, :upe, :pua, :pdv, :dvm, :pra, :prm, :dv, :u, :u) RETURNING id"
        );
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    // ── Catálogo de precios ──────────────────────────────────────────────────

    public function getItems(int $idEmpresa, bool $soloActivos = false): array
    {
        $sql = "SELECT i.*, p.nombre AS producto_nombre
                  FROM cotizador_planes_items i
                  LEFT JOIN productos p ON p.id = i.id_producto AND p.id_empresa = i.id_empresa AND p.eliminado = false
                 WHERE i.id_empresa = :e AND i.eliminado = false"
            . ($soloActivos ? " AND i.estado = 'activo'" : '')
            . " ORDER BY i.tipo, COALESCE(i.grupo, ''), i.orden, i.nombre, i.id";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getItem(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM cotizador_planes_items WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeItemSubmodulo(int $idEmpresa, int $idSubmodulo, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM cotizador_planes_items WHERE id_empresa = :e AND id_submodulo = :s AND eliminado = false";
        $params = [':e' => $idEmpresa, ':s' => $idSubmodulo];
        if ($excluirId !== null) {
            $sql .= " AND id <> :x";
            $params[':x'] = $excluirId;
        }
        $st = $this->db->prepare($sql . ' LIMIT 1');
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function insertItem(int $idEmpresa, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO cotizador_planes_items
                (id_empresa, tipo, id_submodulo, ruta, grupo, nombre, descripcion, precio_anual, cobro,
                 aplica_descuento_establecimiento, por_defecto, id_producto, orden, estado, created_by, updated_by)
             VALUES (:e, :tipo, :sub, :ruta, :grupo, :nombre, :desc, :precio, :cobro, :ade, :pd, :prod, :orden, :estado, :u, :u)
             RETURNING id"
        );
        $st->execute($this->paramsItem($d) + [':e' => $idEmpresa, ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function updateItem(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE cotizador_planes_items SET
                tipo = :tipo, id_submodulo = :sub, ruta = :ruta, grupo = :grupo, nombre = :nombre, descripcion = :desc,
                precio_anual = :precio, cobro = :cobro, aplica_descuento_establecimiento = :ade, por_defecto = :pd,
                id_producto = :prod, orden = :orden, estado = :estado, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute($this->paramsItem($d) + [':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    private function paramsItem(array $d): array
    {
        return [
            ':tipo'   => $d['tipo'],
            ':sub'    => $d['id_submodulo'] ?: null,
            ':ruta'   => $d['ruta'] ?: null,
            ':grupo'  => $d['grupo'] ?: null,
            ':nombre' => $d['nombre'],
            ':desc'   => $d['descripcion'] ?: null,
            ':precio' => $d['precio_anual'],
            ':cobro'  => $d['cobro'],
            ':ade'    => $d['aplica_descuento_establecimiento'] ? 'true' : 'false',
            ':pd'     => $d['por_defecto'] ? 'true' : 'false',
            ':prod'   => $d['id_producto'] ?: null,
            ':orden'  => $d['orden'],
            ':estado' => $d['estado'],
        ];
    }

    public function eliminarItem(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE cotizador_planes_items SET eliminado = true, deleted_at = NOW(), deleted_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
        // Sus dependencias (en ambos sentidos) dejan de valer.
        $this->db->prepare(
            "UPDATE cotizador_planes_items_dependencias SET eliminado = true, deleted_at = NOW(), deleted_by = :u
              WHERE id_empresa = :e AND eliminado = false AND (id_item = :id OR id_item_requerido = :id)"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    /** Mapa id_item => [id_item_requerido, …] de la empresa. */
    public function getDependencias(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT d.id_item, d.id_item_requerido
               FROM cotizador_planes_items_dependencias d
               JOIN cotizador_planes_items a ON a.id = d.id_item AND a.eliminado = false
               JOIN cotizador_planes_items b ON b.id = d.id_item_requerido AND b.eliminado = false
              WHERE d.id_empresa = :e AND d.eliminado = false
              ORDER BY d.id_item, d.id_item_requerido"
        );
        $st->execute([':e' => $idEmpresa]);
        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $mapa[(int) $r['id_item']][] = (int) $r['id_item_requerido'];
        }
        return $mapa;
    }

    /** Reemplaza lo que requiere $idItem (eliminación lógica de lo anterior). */
    public function reemplazarDependencias(int $idEmpresa, int $idItem, array $requeridos, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE cotizador_planes_items_dependencias SET eliminado = true, deleted_at = NOW(), deleted_by = :u
              WHERE id_empresa = :e AND id_item = :i AND eliminado = false"
        )->execute([':e' => $idEmpresa, ':i' => $idItem, ':u' => $idUsuario]);
        $st = $this->db->prepare(
            "INSERT INTO cotizador_planes_items_dependencias (id_empresa, id_item, id_item_requerido, created_by, updated_by)
             VALUES (:e, :i, :r, :u, :u)"
        );
        foreach (array_unique(array_map('intval', $requeridos)) as $r) {
            if ($r > 0 && $r !== $idItem) {
                $st->execute([':e' => $idEmpresa, ':i' => $idItem, ':r' => $r, ':u' => $idUsuario]);
            }
        }
    }

    // ── Productos (línea resumen y servicios de la proforma) ─────────────────

    /** Producto de la empresa con su tarifa de IVA (productos.tarifa_iva → tarifa_iva.id). */
    public function getProductoConTarifa(int $idProducto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.codigo, p.nombre, p.id_medida,
                    ti.id AS id_tarifa_iva, ti.porcentaje_iva, ti.codigo AS codigo_tarifa
               FROM productos p
               LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
              WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false"
        );
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Buscador de productos para la configuración (máx. 20). */
    public function buscarProductos(int $idEmpresa, string $q): array
    {
        $params = [':e' => $idEmpresa];
        $where = "p.id_empresa = :e AND p.eliminado = false AND COALESCE(p.status, 1) = 1";
        if ($q !== '') {
            $cond = FiltrosBusqueda::condicionTexto(['p.codigo', 'p.nombre'], $q, $params, 'cp_b');
            if ($cond !== '') $where .= ' AND ' . $cond;
        }
        $st = $this->db->prepare("SELECT p.id, p.codigo, p.nombre FROM productos p WHERE {$where} ORDER BY p.nombre LIMIT 20");
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Clientes y CRM ───────────────────────────────────────────────────────

    public function getClientePorIdentificacion(int $idEmpresa, string $identificacion): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, identificacion, email, telefono, direccion
               FROM clientes WHERE id_empresa = :e AND identificacion = :i AND eliminado = false
              ORDER BY id LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':i' => $identificacion]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Contacto del CRM ya enlazado a ese cliente (el más antiguo). */
    public function getContactoPorCliente(int $idEmpresa, int $idCliente): ?array
    {
        if (!$this->tablaExiste('crm_contactos')) return null;
        $st = $this->db->prepare(
            "SELECT id, nombre FROM crm_contactos
              WHERE id_empresa = :e AND id_cliente = :c AND eliminado = false ORDER BY id LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':c' => $idCliente]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Oportunidad abierta enlazada a la cotización (si sigue viva y sin proforma). */
    public function getOportunidadAbierta(int $idEmpresa, int $idOportunidad): ?array
    {
        if ($idOportunidad <= 0 || !$this->tablaExiste('crm_oportunidades')) return null;
        $st = $this->db->prepare(
            "SELECT id, numero, estado, id_proforma FROM crm_oportunidades
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $idOportunidad, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Cotizaciones ─────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $ordenMulti, ?int $idUsuarioFiltro): array
    {
        $ordenMulti = OrdenListado::normalizar($ordenMulti !== [] ? $ordenMulti : [['col' => 'numero', 'dir' => 'DESC']]);
        $orderBy = OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'c.numero', 'c.id DESC');

        $where  = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $joins = "LEFT JOIN usuarios u ON u.id = c.created_by
                  LEFT JOIN LATERAL (
                      SELECT COUNT(*) AS total FROM cotizaciones_planes_establecimientos ce
                       WHERE ce.id_cotizacion = c.id AND ce.eliminado = false
                  ) n_est ON true";

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = FiltrosBusqueda::condicionTexto(
                ['c.numero::text', 'c.identificacion', 'c.razon_social', 'c.nombre_comercial', 'c.correo', 'u.nombre'],
                $parsed['texto_libre'], $params, 'cp_b'
            );
            if ($cond !== '') $where .= ' AND ' . $cond;
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['ruc' => 'c.identificacion', 'cliente' => 'c.razon_social', 'vendedor' => 'u.nombre'],
            'exacto'   => ['estado' => 'c.estado', 'modalidad' => 'c.modalidad', 'usuario' => 'c.created_by'],
            'fecha'    => ['fecha' => 'c.fecha', 'registro' => 'c.created_at'],
            'numerico' => ['numero' => 'c.numero', 'total_anual' => 'c.total_anual', 'total_mensual' => 'c.total_mensual', 'usuarios' => 'c.usuarios'],
        ]);

        $st = $this->db->prepare("SELECT COUNT(*) FROM cotizaciones_planes c {$joins} {$where}");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $sql = "SELECT c.*, u.nombre AS vendedor, n_est.total AS establecimientos
                  FROM cotizaciones_planes c {$joins} {$where}
                 {$orderBy}";
        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT c.*, u.nombre AS vendedor
               FROM cotizaciones_planes c
               LEFT JOIN usuarios u ON u.id = c.created_by
              WHERE c.id = :id AND c.id_empresa = :e AND c.eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Bloqueo de la cotización durante una escritura (CLAUDE.md §8). */
    public function bloquear(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM cotizaciones_planes WHERE id = :id AND id_empresa = :e AND eliminado = false FOR UPDATE");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getEstablecimientos(int $idCotizacion, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM cotizaciones_planes_establecimientos
              WHERE id_cotizacion = :c AND id_empresa = :e AND eliminado = false ORDER BY orden, id"
        );
        $st->execute([':c' => $idCotizacion, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDetalle(int $idCotizacion, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM cotizaciones_planes_detalle
              WHERE id_cotizacion = :c AND id_empresa = :e AND eliminado = false ORDER BY id_establecimiento, orden, id"
        );
        $st->execute([':c' => $idCotizacion, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Siguiente número por empresa. Llamar DENTRO de la transacción del INSERT. */
    public function siguienteNumero(int $idEmpresa): int
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('cotizacion_plan_num:' || :e::text))")->execute([':e' => $idEmpresa]);
        $st = $this->db->prepare("SELECT COALESCE(MAX(numero), 0) + 1 FROM cotizaciones_planes WHERE id_empresa = :e");
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    private const CAMPOS_CABECERA = [
        'fecha', 'identificacion', 'razon_social', 'nombre_comercial', 'tipo_contribuyente', 'obligado_contabilidad',
        'correo', 'telefono', 'direccion', 'cod_prov', 'cod_ciudad', 'contacto_nombre', 'usuarios', 'modalidad',
        'descuento_vendedor_pct', 'total_anual', 'total_mensual', 'total_unico', 'parametros', 'observaciones', 'id_vendedor',
    ];

    private function paramsCabecera(array $d): array
    {
        $p = [];
        foreach (self::CAMPOS_CABECERA as $c) {
            $v = $d[$c] ?? null;
            if ($c === 'obligado_contabilidad') {
                $v = $v === null ? null : ($v ? 'true' : 'false');
            } elseif ($c === 'parametros') {
                $v = $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE);
            } elseif (is_string($v) && $v === '' && !in_array($c, ['identificacion', 'razon_social'], true)) {
                $v = null;
            }
            $p[':' . $c] = $v;
        }
        return $p;
    }

    public function insertCabecera(int $idEmpresa, int $numero, array $d, int $idUsuario): int
    {
        $cols = implode(', ', self::CAMPOS_CABECERA);
        $vals = implode(', ', array_map(fn($c) => ':' . $c, self::CAMPOS_CABECERA));
        $st = $this->db->prepare(
            "INSERT INTO cotizaciones_planes (id_empresa, numero, {$cols}, estado, created_by, updated_by)
             VALUES (:e, :numero, {$vals}, 'borrador', :u, :u) RETURNING id"
        );
        $st->execute($this->paramsCabecera($d) + [':e' => $idEmpresa, ':numero' => $numero, ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function updateCabecera(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $set = implode(', ', array_map(fn($c) => "{$c} = :{$c}", self::CAMPOS_CABECERA));
        $this->db->prepare(
            "UPDATE cotizaciones_planes SET {$set}, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute($this->paramsCabecera($d) + [':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    /** Al volver a guardar: los establecimientos y su detalle anteriores se eliminan (lógico) y se reescriben. */
    public function eliminarHijos(int $idCotizacion, int $idEmpresa, int $idUsuario): void
    {
        foreach (['cotizaciones_planes_detalle', 'cotizaciones_planes_establecimientos'] as $t) {
            $this->db->prepare(
                "UPDATE {$t} SET eliminado = true, deleted_at = NOW(), deleted_by = :u
                  WHERE id_cotizacion = :c AND id_empresa = :e AND eliminado = false"
            )->execute([':c' => $idCotizacion, ':e' => $idEmpresa, ':u' => $idUsuario]);
        }
    }

    public function insertEstablecimiento(int $idCotizacion, int $idEmpresa, array $e, int $orden, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO cotizaciones_planes_establecimientos
                (id_empresa, id_cotizacion, codigo, nombre, direccion, es_principal, orden, created_by, updated_by)
             VALUES (:e, :c, :cod, :nom, :dir, :pri, :o, :u, :u) RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':c' => $idCotizacion, ':cod' => $e['codigo'],
            ':nom' => ($e['nombre'] ?? '') !== '' ? $e['nombre'] : null,
            ':dir' => ($e['direccion'] ?? '') !== '' ? $e['direccion'] : null,
            ':pri' => !empty($e['es_principal']) ? 'true' : 'false', ':o' => $orden, ':u' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function insertDetalle(int $idCotizacion, int $idEstablecimiento, int $idEmpresa, array $it, int $orden, int $idUsuario): void
    {
        $this->db->prepare(
            "INSERT INTO cotizaciones_planes_detalle
                (id_empresa, id_cotizacion, id_establecimiento, id_item, nombre, grupo, tipo, cobro, precio_anual,
                 aplica_descuento_establecimiento, por_dependencia, id_producto, orden, created_by, updated_by)
             VALUES (:e, :c, :est, :item, :nom, :grupo, :tipo, :cobro, :precio, :ade, :dep, :prod, :o, :u, :u)"
        )->execute([
            ':e' => $idEmpresa, ':c' => $idCotizacion, ':est' => $idEstablecimiento, ':item' => (int) $it['id_item'],
            ':nom' => $it['nombre'], ':grupo' => ($it['grupo'] ?? '') !== '' ? $it['grupo'] : null,
            ':tipo' => $it['tipo'], ':cobro' => $it['cobro'], ':precio' => $it['precio_anual'],
            ':ade' => !empty($it['aplica_descuento_establecimiento']) ? 'true' : 'false',
            ':dep' => !empty($it['por_dependencia']) ? 'true' : 'false',
            ':prod' => !empty($it['id_producto']) ? (int) $it['id_producto'] : null,
            ':o' => $orden, ':u' => $idUsuario,
        ]);
    }

    public function setEnlaces(int $id, int $idEmpresa, array $enlaces, int $idUsuario): void
    {
        $permitidos = ['id_cliente', 'id_contacto', 'id_oportunidad', 'id_proforma', 'estado'];
        $set = []; $params = [':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario];
        foreach ($enlaces as $k => $v) {
            if (!in_array($k, $permitidos, true)) continue;
            $set[] = "{$k} = :{$k}";
            $params[':' . $k] = $v;
        }
        if ($set === []) return;
        $this->db->prepare(
            "UPDATE cotizaciones_planes SET " . implode(', ', $set) . ", updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute($params);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE cotizaciones_planes SET eliminado = true, deleted_at = NOW(), deleted_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }
}
