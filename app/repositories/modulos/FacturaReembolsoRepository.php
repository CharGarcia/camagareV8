<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class FacturaReembolsoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('factura_reembolso_cabecera');
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE fr.id_empresa = :id_empresa AND fr.eliminado = false AND fr.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        // Texto libre: las columnas del listado —número, fecha, cliente, identificación,
        // Terceros (la cantidad), Reembolsado, total y usuario— más las observaciones.
        //
        // Qué NO entra, por decisión del usuario, y dónde se busca en su lugar (mismo
        // criterio que Facturas de Venta, Compras y las notas de crédito/débito):
        //   - Estado y estado del correo → modal de filtros (decisión anterior).
        //   - Número de autorización y clave de acceso → filtros `autorizacion:` y
        //     `clave:` (17-09-2026). En un comprobante electrónico son el MISMO número de
        //     49 dígitos —fecha, RUC, serie, secuencial y un código numérico aleatorio de
        //     8—, así que al escribir un número de documento caía dentro de la clave de
        //     OTRAS facturas por puro azar y el listado devolvía filas sin ninguna
        //     coincidencia visible.
        //   - Líneas del detalle y los proveedores/comprobantes de reembolso (los
        //     "terceros") → pestaña "Detalles" del modal de filtros (buscarEnDetalles()),
        //     que SÍ dice qué línea o qué tercero coincidió. De paso se van dos
        //     subconsultas STRING_AGG que corrían por cada factura de la empresa.
        if ($textoLibre !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    \App\Helpers\SecuencialFormato::sqlNumeroCompleto('fr.establecimiento', 'fr.punto_emision', 'fr.secuencial'), // Número (canónico)
                    'fr.secuencial',
                    'fr.fecha_emision::text',                                             // Fecha
                    'c.nombre',                                                           // Cliente
                    'c.identificacion',                                                   // Identificación
                    "(SELECT COUNT(*) FROM factura_reembolso_terceros frt0 WHERE frt0.id_factura_reembolso = fr.id)::text", // Terceros
                    '(COALESCE(fr.total_base_imponible_reembolso,0) + COALESCE(fr.total_impuesto_reembolso,0))::text',      // Reembolsado
                    'fr.importe_total::text',                                             // Total
                    'u.nombre',                                                           // Usuario
                    // Fuera del listado, pero identifican la factura:
                    'fr.observaciones',
                ],
                $textoLibre,
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'ci'             => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'numero'         => "CONCAT(fr.establecimiento,'-',fr.punto_emision,'-',fr.secuencial)",
                'nro'            => "CONCAT(fr.establecimiento,'-',fr.punto_emision,'-',fr.secuencial)",
                'usuario'        => 'u.nombre',
                'obs'            => 'fr.observaciones',
                'autorizacion'   => 'fr.numero_autorizacion',
                'clave'          => 'fr.clave_acceso',
                'observacion'    => 'fr.observaciones',
                'clave_acceso'   => 'fr.clave_acceso',
            ],
            'exacto' => [
                'estado'        => 'fr.estado',
                'estado_correo' => "COALESCE(NULLIF(fr.estado_correo,''),'pendiente')",
                'correo'        => "COALESCE(NULLIF(fr.estado_correo,''),'pendiente')",
                // Serie = establecimiento-puntoEmision (ej. "001-001"), tal como se
                // muestra en el selector "Serie" del buscador.
                'serie'         => "CONCAT(fr.establecimiento,'-',fr.punto_emision)",
                'id_usuario'    => 'fr.id_usuario',
                // asiento:si / asiento:no
                'asiento'       => "CASE WHEN fr.id_asiento_contable IS NULL THEN 'no' ELSE 'si' END",
                // ambiente:1 (pruebas) / ambiente:2 (producción)
                'ambiente'      => 'fr.tipo_ambiente',
            ],
            'fecha' => [
                'fecha'         => 'fr.fecha_emision',
                'fecha_emision' => 'fr.fecha_emision',
                'fecha_autorizacion' => 'fr.fecha_autorizacion',
                'autorizada'         => 'fr.fecha_autorizacion',
            ],
            'numerico' => [
                'monto'    => 'fr.importe_total',
                'total'    => 'fr.importe_total',
                'subtotal' => 'fr.total_sin_impuestos',
                'reembolso' => 'fr.total_base_imponible_reembolso',
                // Columna "Reembolsado": base + impuesto de los comprobantes de terceros
                'reembolsado' => '(COALESCE(fr.total_base_imponible_reembolso,0) + COALESCE(fr.total_impuesto_reembolso,0))',
                'descuento' => 'COALESCE(fr.total_descuento,0)',
                // Columna "Terceros": cantidad de comprobantes de reembolso
                'terceros'  => '(SELECT COUNT(*) FROM factura_reembolso_terceros frt1 WHERE frt1.id_factura_reembolso = fr.id)',
                // Comparación numérica: "298" encuentra "000000298" sin que el
                // usuario tenga que escribir los ceros a la izquierda, y sigue
                // siendo coincidencia EXACTA (el bucket numérico convierte ILIKE
                // en '=', nunca hace substring).
                'secuencial' => 'fr.secuencial::numeric',
            ],
            'existe' => [
                // proveedor:texto → algún comprobante de reembolso es de ese proveedor
                'proveedor' => [
                    'sql'  => 'EXISTS (SELECT 1 FROM factura_reembolso_terceros frt2 WHERE frt2.id_factura_reembolso = fr.id AND {cond})',
                    'col'  => 'frt2.razon_social_proveedor_reembolso',
                    'tipo' => 'texto',
                ],
                'ruc_proveedor' => [
                    'sql'  => 'EXISTS (SELECT 1 FROM factura_reembolso_terceros frt3 WHERE frt3.id_factura_reembolso = fr.id AND {cond})',
                    'col'  => 'frt3.identificacion_proveedor_reembolso',
                    'tipo' => 'texto',
                ],
                // doc_reembolso:001-001-000000123 → número de algún comprobante de reembolso
                'doc_reembolso' => [
                    'sql'  => 'EXISTS (SELECT 1 FROM factura_reembolso_terceros frt4 WHERE frt4.id_factura_reembolso = fr.id AND {cond})',
                    'col'  => "CONCAT(frt4.estab_doc_reembolso,'-',frt4.pto_emi_doc_reembolso,'-',frt4.secuencial_doc_reembolso)",
                    'tipo' => 'texto',
                ],
                'fecha_reembolso' => [
                    'sql'  => 'EXISTS (SELECT 1 FROM factura_reembolso_terceros frt5 WHERE frt5.id_factura_reembolso = fr.id AND {cond})',
                    'col'  => 'frt5.fecha_emision_doc_reembolso',
                    'tipo' => 'fecha',
                ],
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND fr.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $sqlCount = "SELECT COUNT(*) FROM factura_reembolso_cabecera fr
                     LEFT JOIN clientes c ON fr.id_cliente = c.id
                     LEFT JOIN usuarios u ON fr.id_usuario = u.id
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $ordenExpr = match ($ordenCol) {
            'cliente_nombre' => 'c.nombre',
            'cliente_ruc'    => 'c.identificacion',
            'usuario_nombre' => 'u.nombre',
            'numero'         => 'fr.secuencial',
            'secuencial', 'fecha_emision', 'total_sin_impuestos', 'importe_total',
            'estado', 'estado_correo', 'total_base_imponible_reembolso', 'total_impuesto_reembolso'
                             => "fr.$ordenCol",
            default          => 'fr.fecha_emision',
        };
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT fr.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.email as cliente_email,
                       u.nombre as usuario_nombre,
                       (SELECT COUNT(*) FROM factura_reembolso_terceros t WHERE t.id_factura_reembolso = fr.id) AS cantidad_terceros
                FROM factura_reembolso_cabecera fr
                LEFT JOIN clientes c ON fr.id_cliente = c.id
                LEFT JOIN usuarios u ON fr.id_usuario = u.id
                $where
                ORDER BY $ordenExpr $ordenDir, fr.id DESC" . ($perPage > 0 ? " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset : "");

        $st = $this->db->prepare($sql);
        $st->execute($params);

        return [
            'total' => $total,
            'rows'  => $st->fetchAll(),
        ];
    }

    /**
     * Series (establecimiento-punto_emision) con al menos un documento real
     * registrado, para el filtro "Serie" del buscador. A diferencia de
     * $puntos (armado en el controller para "qué serie puedo usar en un
     * documento NUEVO", solo con el punto configurado con secuencial), esto
     * incluye series de cualquier establecimiento y aunque el punto ya no
     * tenga secuencial configurado (mismo patrón que
     * FacturaVentaRepository::getSeriesDistintas()).
     */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM factura_reembolso_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Usuarios que han registrado alguna factura de reembolso en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConFacturas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM factura_reembolso_cabecera fr
                JOIN usuarios u ON u.id = fr.id_usuario
                WHERE fr.id_empresa = :id_empresa AND fr.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las facturas de reembolso (pestaña "Detalles" del modal
     * de filtros): devuelve cada línea, comprobante de reembolso de terceros, forma de
     * pago o campo de información adicional que coincide con el texto, junto con la
     * factura a la que pertenece. Mismo alcance que el listado (empresa, no eliminadas,
     * ambiente, registros propios por id_usuario).
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "fr.id_empresa = :id_empresa AND fr.eliminado = false
                      AND fr.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $whereBase .= " AND fr.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.descripcion', 'd.cantidad::text', 'd.precio_unitario::text', 'd.precio_total_sin_impuesto::text'],
            $q, $params, 'dt'
        );
        $condTer = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['t.razon_social_proveedor_reembolso', 't.identificacion_proveedor_reembolso',
             "CONCAT(t.estab_doc_reembolso,'-',t.pto_emi_doc_reembolso,'-',t.secuencial_doc_reembolso)",
             't.numero_autorizacion_doc_reemb', 't.base_imponible_total::text', 't.impuesto_total::text'],
            $q, $params, 'tr'
        );
        $condPago = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['fp.nombre', 'p.forma_pago', 'p.total::text', 'p.plazo::text', 'p.unidad_tiempo'],
            $q, $params, 'pg'
        );
        $condAdic = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['a.nombre', 'a.valor'],
            $q, $params, 'ad'
        );
        if ($condDet === '' || $condTer === '' || $condPago === '' || $condAdic === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT fr.id, CONCAT(fr.establecimiento,'-',fr.punto_emision,'-',fr.secuencial) AS numero,
                           fr.fecha_emision, fr.estado, c.nombre AS cliente
                    FROM factura_reembolso_cabecera fr
                    LEFT JOIN clientes c ON c.id = fr.id_cliente
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'LINEA' AS origen,
                           CASE WHEN d.es_reembolso THEN 'Reembolso' ELSE 'Honorarios' END AS tipo,
                           d.descripcion,
                           d.cantidad,
                           d.precio_total_sin_impuesto AS monto,
                           b.id AS id_factura, b.numero, b.fecha_emision, b.estado, b.cliente
                    FROM factura_reembolso_detalle d
                    JOIN base b ON b.id = d.id_factura_reembolso
                    WHERE $condDet
                    UNION ALL
                    SELECT 'TERCERO' AS origen,
                           CONCAT(t.estab_doc_reembolso,'-',t.pto_emi_doc_reembolso,'-',t.secuencial_doc_reembolso) AS tipo,
                           NULLIF(CONCAT_WS(' · ', NULLIF(t.razon_social_proveedor_reembolso, ''), NULLIF(t.identificacion_proveedor_reembolso, '')), '') AS descripcion,
                           NULL AS cantidad,
                           (COALESCE(t.base_imponible_total, 0) + COALESCE(t.impuesto_total, 0)) AS monto,
                           b.id AS id_factura, b.numero, b.fecha_emision, b.estado, b.cliente
                    FROM factura_reembolso_terceros t
                    JOIN base b ON b.id = t.id_factura_reembolso
                    WHERE $condTer
                    UNION ALL
                    SELECT 'PAGO' AS origen,
                           COALESCE(fp.nombre, p.forma_pago) AS tipo,
                           NULLIF(CONCAT_WS(' ', p.plazo::text, p.unidad_tiempo), '') AS descripcion,
                           NULL AS cantidad,
                           p.total AS monto,
                           b.id AS id_factura, b.numero, b.fecha_emision, b.estado, b.cliente
                    FROM factura_reembolso_pagos p
                    JOIN base b ON b.id = p.id_factura_reembolso
                    LEFT JOIN formas_pago_sri fp ON fp.codigo = p.forma_pago
                    WHERE $condPago
                    UNION ALL
                    SELECT 'ADICIONAL' AS origen,
                           a.nombre AS tipo,
                           a.valor AS descripcion,
                           NULL AS cantidad,
                           NULL AS monto,
                           b.id AS id_factura, b.numero, b.fecha_emision, b.estado, b.cliente
                    FROM factura_reembolso_adicional a
                    JOIN base b ON b.id = a.id_factura_reembolso
                    WHERE $condAdic
                ) x
                ORDER BY x.fecha_emision DESC, x.id_factura DESC, x.origen
                LIMIT $limit";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT fr.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.direccion as cliente_direccion, c.telefono as cliente_telefono,
                       c.email as cliente_email, c.tipo_id as cliente_tipo_id
                FROM factura_reembolso_cabecera fr
                LEFT JOIN clientes c ON fr.id_cliente = c.id
                WHERE fr.id = ? AND fr.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getTipoIdCliente(int $idCliente, int $idEmpresa): ?array
    {
        $sql = "SELECT c.tipo_id, c.identificacion, COALESCE(icv.nombre, '') AS nombre_tipo_id
                FROM clientes c
                LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
                WHERE c.id = ? AND c.id_empresa = ? AND c.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([$idCliente, $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Detalle (líneas libres) ──────────────────────────────────────────────

    public function getDetalles(int $idFR): array
    {
        $sql = "SELECT * FROM factura_reembolso_detalle WHERE id_factura_reembolso = ? ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idFR]);
        return $st->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        $sql = "SELECT * FROM factura_reembolso_detalle_impuestos WHERE id_factura_reembolso_detalle = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idDetalle]);
        return $st->fetchAll();
    }

    /**
     * Impuestos de VARIAS líneas en UNA sola consulta, agrupados por línea.
     *
     * Evita el N+1 de llamar a getImpuestosDetalle() dentro del bucle de
     * detalles: con la base en un servidor remoto, un documento de 30 líneas
     * pagaba 30 viajes de red solo para esto.
     *
     * @param int[] $idsDetalle
     * @return array<int,array> id de la línea => sus impuestos
     */
    public function getImpuestosPorDetalles(array $idsDetalle): array
    {
        $ids = array_values(array_unique(array_filter(array_map("intval", $idsDetalle))));
        if (!$ids) {
            return [];
        }

        $ph  = implode(",", array_fill(0, count($ids), "?"));
        $sql = "SELECT * FROM factura_reembolso_detalle_impuestos WHERE id_factura_reembolso_detalle IN ($ph)";

        $porDetalle = [];
        $st = $this->db->prepare($sql);
        $st->execute($ids);
        foreach ($st->fetchAll() as $imp) {
            $porDetalle[(int) $imp["id_factura_reembolso_detalle"]][] = $imp;
        }
        return $porDetalle;
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO factura_reembolso_detalle (
                    id_factura_reembolso, descripcion, cantidad, precio_unitario,
                    descuento, precio_total_sin_impuesto, es_reembolso
                ) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_factura_reembolso'],
            $data['descripcion'],
            $data['cantidad'],
            $data['precio_unitario'],
            $data['descuento'] ?? 0,
            $data['precio_total_sin_impuesto'],
            !empty($data['es_reembolso']) ? 'true' : 'false',
        ]);
        return (int) $st->fetchColumn();
    }

    public function insertImpuestoDetalle(array $data): void
    {
        $sql = "INSERT INTO factura_reembolso_detalle_impuestos (
                    id_factura_reembolso_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?, ?, ?, ?, ?, ?)";
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_factura_reembolso_detalle'], $data['codigo_impuesto'], $data['codigo_porcentaje'],
            $data['tarifa'], $data['base_imponible'], $data['valor'],
        ]);
    }

    public function deleteDetalles(int $idFR): void
    {
        // Los impuestos se eliminan en cascada (FK ON DELETE CASCADE).
        $st = $this->db->prepare("DELETE FROM factura_reembolso_detalle WHERE id_factura_reembolso = ?");
        $st->execute([$idFR]);
    }

    // ── Terceros reembolsados (bloque SRI <reembolsos>) ──────────────────────

    public function getTerceros(int $idFR): array
    {
        $sql = "SELECT t.*, co.establecimiento_prov, co.punto_emision_prov, co.secuencial_prov
                FROM factura_reembolso_terceros t
                LEFT JOIN compras_cabecera co ON co.id = t.id_compra
                WHERE t.id_factura_reembolso = ?
                ORDER BY t.orden ASC, t.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idFR]);
        return $st->fetchAll();
    }

    public function getImpuestosTercero(int $idTercero): array
    {
        $sql = "SELECT * FROM factura_reembolso_terceros_impuestos WHERE id_factura_reembolso_tercero = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idTercero]);
        return $st->fetchAll();
    }

    public function insertTercero(array $data): int
    {
        $sql = "INSERT INTO factura_reembolso_terceros (
                    id_factura_reembolso, id_compra, orden,
                    tipo_identificacion_proveedor_reembolso, identificacion_proveedor_reembolso,
                    razon_social_proveedor_reembolso, cod_pais_pago_proveedor_reembolso, tipo_proveedor_reembolso,
                    cod_doc_reembolso, estab_doc_reembolso, pto_emi_doc_reembolso,
                    secuencial_doc_reembolso, fecha_emision_doc_reembolso, numero_autorizacion_doc_reemb,
                    base_imponible_total, impuesto_total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_factura_reembolso'],
            !empty($data['id_compra']) ? (int) $data['id_compra'] : null,
            $data['orden'] ?? 0,
            $data['tipo_identificacion_proveedor_reembolso'],
            $data['identificacion_proveedor_reembolso'],
            $data['razon_social_proveedor_reembolso'] ?? null,
            $data['cod_pais_pago_proveedor_reembolso'] ?? null,
            $data['tipo_proveedor_reembolso'],
            $data['cod_doc_reembolso'] ?? '01',
            $data['estab_doc_reembolso'],
            $data['pto_emi_doc_reembolso'],
            $data['secuencial_doc_reembolso'],
            $data['fecha_emision_doc_reembolso'],
            $data['numero_autorizacion_doc_reemb'],
            $data['base_imponible_total'] ?? 0,
            $data['impuesto_total'] ?? 0,
        ]);
        return (int) $st->fetchColumn();
    }

    public function insertImpuestoTercero(array $data): void
    {
        $sql = "INSERT INTO factura_reembolso_terceros_impuestos (
                    id_factura_reembolso_tercero, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?, ?, ?, ?, ?, ?)";
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_factura_reembolso_tercero'], $data['codigo_impuesto'], $data['codigo_porcentaje'],
            $data['tarifa'], $data['base_imponible'], $data['valor'],
        ]);
    }

    public function deleteTerceros(int $idFR): void
    {
        // Los impuestos se eliminan en cascada (FK ON DELETE CASCADE).
        $st = $this->db->prepare("DELETE FROM factura_reembolso_terceros WHERE id_factura_reembolso = ?");
        $st->execute([$idFR]);
    }

    /**
     * Typeahead de compras ya registradas para autocompletar un tercero reembolsado:
     * cabecera (proveedor, tipo/serie/secuencial/autorización del documento).
     */
    public function buscarComprasParaTercero(int $idEmpresa, string $buscar, int $limit = 15): array
    {
        $buscar = trim($buscar);
        $params = [':id_empresa' => $idEmpresa];
        $whereBuscar = '';
        if ($buscar !== '') {
            $whereBuscar = " AND (p.razon_social ILIKE :buscar OR p.identificacion ILIKE :buscar
                              OR (c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov) ILIKE :buscar) ";
            $params[':buscar'] = '%' . $buscar . '%';
        }
        $sql = "SELECT c.id, c.id_proveedor, c.tipo_id_proveedor, c.tipo_comprobante,
                       c.establecimiento_prov, c.punto_emision_prov, c.secuencial_prov,
                       c.fecha_emision, c.numero_autorizacion, c.total_sin_impuestos, c.importe_total,
                       p.razon_social AS proveedor_nombre, p.identificacion AS proveedor_identificacion
                FROM compras_cabecera c
                INNER JOIN proveedores p ON p.id = c.id_proveedor
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false
                $whereBuscar
                ORDER BY c.fecha_emision DESC, c.id DESC
                LIMIT $limit";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Impuestos de una compra agregados por (codigo_impuesto, codigo_porcentaje, tarifa) — para autollenar un tercero. */
    public function getImpuestosAgregadosCompra(int $idCompra): array
    {
        $sql = "SELECT di.codigo_impuesto, di.codigo_porcentaje, di.tarifa,
                       SUM(di.base_imponible) AS base_imponible, SUM(di.valor) AS valor
                FROM compras_detalle_impuestos di
                INNER JOIN compras_detalle d ON d.id = di.id_compra_detalle
                WHERE d.id_compra = ?
                GROUP BY di.codigo_impuesto, di.codigo_porcentaje, di.tarifa";
        $st = $this->db->prepare($sql);
        $st->execute([$idCompra]);
        return $st->fetchAll();
    }

    // ── Pagos ─────────────────────────────────────────────────────────────────

    public function getPagos(int $idFR): array
    {
        $sql = "SELECT fp.*, COALESCE(fps.nombre, fp.forma_pago) AS nombre_forma_pago
                FROM factura_reembolso_pagos fp
                LEFT JOIN formas_pago_sri fps ON fps.codigo = fp.forma_pago
                WHERE fp.id_factura_reembolso = ?
                ORDER BY fp.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idFR]);
        return $st->fetchAll();
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO factura_reembolso_pagos (id_factura_reembolso, forma_pago, total, plazo, unidad_tiempo) VALUES (?, ?, ?, ?, ?)";
        $st = $this->db->prepare($sql);
        $st->execute([$data['id_factura_reembolso'], $data['forma_pago'], $data['total'], $data['plazo'] ?? 0, $data['unidad_tiempo'] ?? 'dias']);
    }

    public function deletePagos(int $idFR): void
    {
        $st = $this->db->prepare("DELETE FROM factura_reembolso_pagos WHERE id_factura_reembolso = ?");
        $st->execute([$idFR]);
    }

    // ── Información adicional ────────────────────────────────────────────────

    public function getInfoAdicional(int $idFR): array
    {
        $sql = "SELECT * FROM factura_reembolso_adicional WHERE id_factura_reembolso = ? ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idFR]);
        return $st->fetchAll();
    }

    /**
     * `nombre` VARCHAR(300) y `valor` VARCHAR(500): texto libre del modal.
     * PostgreSQL no trunca: un valor más largo aborta el INSERT con SQLSTATE[22001]
     * y se cae la factura entera, así que se capa al largo real de cada columna
     * (mismo criterio que FacturaVentaRepository::insertInfoAdicional).
     */
    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO factura_reembolso_adicional (id_factura_reembolso, nombre, valor) VALUES (?, ?, ?)";
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_factura_reembolso'],
            $this->caparTexto('nombre', $data['nombre'] ?? '', 'factura_reembolso_adicional'),
            $this->caparTexto('valor',  $data['valor']  ?? null, 'factura_reembolso_adicional'),
        ]);
    }

    public function deleteInfoAdicional(int $idFR): void
    {
        $st = $this->db->prepare("DELETE FROM factura_reembolso_adicional WHERE id_factura_reembolso = ?");
        $st->execute([$idFR]);
    }

    // ── Cabecera ──────────────────────────────────────────────────────────────

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO factura_reembolso_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso,
                    tipo_emision, tipo_ambiente,
                    total_sin_impuestos, total_descuento, importe_total, propina, moneda,
                    cod_doc_reembolso, total_comprobantes_reembolso, total_base_imponible_reembolso, total_impuesto_reembolso,
                    estado, observaciones, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            (int) $data['id_empresa'],
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (int) $data['id_cliente'],
            (int) $data['id_usuario'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['clave_acceso'] ?? null,
            $data['tipo_emision'] ?? '1',
            $data['tipo_ambiente'] ?? '1',
            (float) $data['total_sin_impuestos'],
            (float) ($data['total_descuento'] ?? 0),
            (float) $data['importe_total'],
            (float) ($data['propina'] ?? 0),
            $data['moneda'] ?? 'DOLAR',
            '41',
            (float) ($data['total_comprobantes_reembolso'] ?? 0),
            (float) ($data['total_base_imponible_reembolso'] ?? 0),
            (float) ($data['total_impuesto_reembolso'] ?? 0),
            $data['estado'] ?? 'borrador',
            !empty($data['observaciones']) ? $data['observaciones'] : null,
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE factura_reembolso_cabecera SET
                    id_establecimiento = ?, id_punto_emision = ?, id_cliente = ?,
                    fecha_emision = ?, establecimiento = ?, punto_emision = ?, secuencial = ?, clave_acceso = ?,
                    total_sin_impuestos = ?, total_descuento = ?, importe_total = ?, propina = ?, moneda = ?,
                    total_comprobantes_reembolso = ?, total_base_imponible_reembolso = ?, total_impuesto_reembolso = ?,
                    observaciones = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ?
                WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (int) $data['id_cliente'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['clave_acceso'] ?? null,
            (float) $data['total_sin_impuestos'],
            (float) ($data['total_descuento'] ?? 0),
            (float) $data['importe_total'],
            (float) ($data['propina'] ?? 0),
            $data['moneda'] ?? 'DOLAR',
            (float) ($data['total_comprobantes_reembolso'] ?? 0),
            (float) ($data['total_base_imponible_reembolso'] ?? 0),
            (float) ($data['total_impuesto_reembolso'] ?? 0),
            !empty($data['observaciones']) ? $data['observaciones'] : null,
            (int) $data['id_usuario'],
            $id,
        ]);
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPuntoEmision, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM factura_reembolso_cabecera
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ? AND secuencial = ? AND eliminado = false";
        $params = [$idEmpresa, $idEstablecimiento, $idPuntoEmision, $secuencial];
        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    public function getFormasPago(): array
    {
        return $this->db->query("SELECT * FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        return $this->db->query("SELECT * FROM tarifa_iva ORDER BY status DESC, porcentaje_iva ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTiposIdentificacionProveedor(): array
    {
        return $this->db->query(
            "SELECT codigo, nombre FROM identificador_comprador_vendedor WHERE codigo IN ('04','05','06','07','08') ORDER BY codigo ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateEstado(int $id, string $estado): void
    {
        $st = $this->db->prepare("UPDATE factura_reembolso_cabecera SET estado = ? WHERE id = ?");
        $st->execute([$estado, $id]);
    }

    public function updateAutorizacion(int $id, string $numero, string $fecha): void
    {
        $st = $this->db->prepare(
            "UPDATE factura_reembolso_cabecera SET numero_autorizacion = ?, fecha_autorizacion = ?, estado = 'autorizado' WHERE id = ?"
        );
        $st->execute([$numero, $fecha, $id]);
    }

    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE factura_reembolso_cabecera SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = ? WHERE id = ?"
        );
        $st->execute([$idUsuario, $id]);
    }

    public function updateAsientoContable(int $id, int $idAsiento): void
    {
        $st = $this->db->prepare("UPDATE factura_reembolso_cabecera SET id_asiento_contable = ? WHERE id = ?");
        $st->execute([$idAsiento, $id]);
    }

    public function updateDetalleXml(int $id, string $xml): void
    {
        $st = $this->db->prepare("UPDATE factura_reembolso_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?");
        $st->execute([$xml, $id]);
    }
}
