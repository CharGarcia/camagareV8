<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;
use PDO;

class NotaCreditoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('notas_credito_cabecera');
    }

    /**
     * Series (establecimiento-puntoEmision) que REALMENTE tienen al menos una
     * nota de crédito guardada, para poblar el filtro "Serie" del buscador. A
     * propósito NO usa los puntos de emisión configurados actualmente (esos
     * solo sirven para elegir la serie de un documento NUEVO).
     */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM notas_credito_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE nc.id_empresa = :id_empresa AND nc.eliminado = false AND nc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        // Parser de filtros
        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        // Texto libre: las columnas del listado (número, fecha, cliente, identificación,
        // documento modificado, importes, motivo y usuario) más las observaciones.
        //
        // Qué NO entra, por decisión del usuario, y dónde se busca en su lugar:
        //   - Correo y Estado → modal de filtros (decisión anterior).
        //   - Número de autorización y clave de acceso → filtros `autorizacion:` y
        //     `clave:` (17-09-2026). En un comprobante electrónico son el MISMO número de
        //     49 dígitos —fecha, RUC, serie, secuencial y un código numérico aleatorio de
        //     8—, así que al escribir un número de documento caía dentro de la clave de
        //     OTRAS notas por puro azar y el listado devolvía filas sin ninguna
        //     coincidencia visible. Mismo caso que en FacturaVentaRepository y
        //     ComprasRepository.
        //   - Productos/servicios del detalle → pestaña "Detalles" del modal de filtros
        //     (buscarEnDetalles()), que SÍ dice qué línea coincidió; desde el listado la
        //     nota aparecía sin que se viera el motivo. De paso se va la subconsulta
        //     STRING_AGG, que se evaluaba por cada nota de la empresa.
        if ($textoLibre !== '') {
            // Rendimiento: montos y fecha solo se comparan si la palabra tiene dígitos
            // (ver FiltrosBusqueda::condicionTexto). La subconsulta del detalle va al final.
            $digitos = \App\Helpers\FiltrosBusqueda::SI_DIGITOS;
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    \App\Helpers\SecuencialFormato::sqlNumeroCompleto('nc.establecimiento', 'nc.punto_emision', 'nc.secuencial'), // Nº Nota (canónico)
                    'nc.secuencial',
                    'c.nombre',                                                           // Cliente
                    'c.identificacion',                                                   // Identificación
                    'nc.num_doc_modificado',                                              // Doc. Modificado
                    'nc.motivo',                                                          // Motivo
                    'u.nombre',                                                           // Usuario
                    // Fuera del listado, pero identifican la nota:
                    'nc.observaciones',
                    ['sql' => 'nc.fecha_emision', 'si' => $digitos],                      // Fecha
                    ['sql' => 'nc.total_sin_impuestos', 'si' => $digitos],                // Subtotal
                    ['sql' => 'nc.total_descuento', 'si' => $digitos],                    // Descuento
                    ['sql' => 'nc.importe_total', 'si' => $digitos],                      // Total
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
                // Nº completo (001-001-000000123): también encuentra solo el secuencial,
                // como antes, porque es una coincidencia parcial (ILIKE).
                'numero'         => "CONCAT(nc.establecimiento,'-',nc.punto_emision,'-',nc.secuencial)",
                'nro'            => "CONCAT(nc.establecimiento,'-',nc.punto_emision,'-',nc.secuencial)",
                'doc_modificado' => 'nc.num_doc_modificado',
                'motivo'         => 'nc.motivo',
                'usuario'        => 'u.nombre',
                'obs'            => 'nc.observaciones',
                'observacion'    => 'nc.observaciones',
                'autorizacion'   => 'nc.numero_autorizacion',
                'clave'          => 'nc.clave_acceso',
                'clave_acceso'   => 'nc.clave_acceso',
            ],
            'exacto' => [
                'estado' => 'nc.estado',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), tal como se
                // muestra en el selector "Serie" del modal de nota de crédito.
                'serie'  => "CONCAT(nc.establecimiento,'-',nc.punto_emision)",
                'estado_correo' => "COALESCE(NULLIF(nc.estado_correo,''),'pendiente')",
                'correo'        => "COALESCE(NULLIF(nc.estado_correo,''),'pendiente')",
                'id_usuario'    => 'nc.id_usuario',
                // asiento:si / asiento:no
                'asiento'       => "CASE WHEN nc.id_asiento_contable IS NULL THEN 'no' ELSE 'si' END",
                // ambiente:1 (pruebas) / ambiente:2 (producción)
                'ambiente'      => 'nc.tipo_ambiente',
            ],
            'fecha' => [
                'fecha'         => 'nc.fecha_emision',
                'fecha_emision' => 'nc.fecha_emision',
                'fecha_autorizacion' => 'nc.fecha_autorizacion',
                'autorizada'         => 'nc.fecha_autorizacion',
                'fecha_sustento'     => 'nc.fecha_emision_docs_sustento',
            ],
            'numerico' => [
                'monto'    => 'nc.importe_total',
                'total'    => 'nc.importe_total',
                'subtotal' => 'nc.total_sin_impuestos',
                'descuento' => 'nc.total_descuento',
                // Comparación numérica: "298" encuentra "000000298" sin que el
                // usuario tenga que escribir los ceros a la izquierda, y sigue
                // siendo coincidencia EXACTA (el bucket numérico convierte ILIKE
                // en '=', nunca hace substring).
                'secuencial' => 'nc.secuencial::numeric',
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND nc.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        // Mapear la columna de orden a su expresión real (algunas son alias de JOINs).
        $ordenExpr = match ($ordenCol) {
            'cliente_nombre'  => 'c.nombre',
            'cliente_ruc'     => 'c.identificacion',
            'usuario_nombre'  => 'u.nombre',
            'numero'          => 'nc.secuencial',
            'secuencial', 'fecha_emision', 'total_sin_impuestos', 'total_descuento',
            'importe_total', 'estado', 'estado_correo', 'num_doc_modificado', 'motivo'
                              => "nc.$ordenCol",
            default           => 'nc.fecha_emision',
        };
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        // Rendimiento (2026-09-16): conteo + página en UNA consulta (el WHERE con texto
        // libre se evalúa una sola vez). Ver App\Helpers\ListadoPaginado.
        $joinsFiltro = "LEFT JOIN clientes c ON nc.id_cliente = c.id
                LEFT JOIN usuarios u ON nc.id_usuario = u.id";

        return \App\Helpers\ListadoPaginado::consultar(
            function (string $sql, array $p): array {
                $st = $this->db->prepare($sql);
                $st->execute($p);
                return $st->fetchAll();
            },
            [
                'tabla'       => 'notas_credito_cabecera',
                'alias'       => 'nc',
                'joinsFiltro' => $joinsFiltro,
                'joinsFinal'  => $joinsFiltro . "
                LEFT JOIN empresas e ON e.id = nc.id_empresa",
                'where'       => $where,
                'orderBy'     => "ORDER BY $ordenExpr $ordenDir, nc.id DESC",
                'perPage'     => (int) $perPage,
                'conBusqueda' => trim($buscar) !== '',   // sin buscar: forma liviana (ids por índice + COUNT aparte)
                'offset'      => (int) $offset,
                'select'      => "nc.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.email as cliente_email,
                       u.nombre as usuario_nombre,
                       e.tipo_ambiente, e.tipo_emision",
            ],
            $params
        );
    }

    /** Usuarios que han registrado alguna nota de crédito en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConNotas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM notas_credito_cabecera nc
                JOIN usuarios u ON u.id = nc.id_usuario
                WHERE nc.id_empresa = :id_empresa AND nc.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las notas de crédito (pestaña "Detalles" del modal de
     * filtros): devuelve cada línea (producto/servicio) o campo de información adicional
     * que coincide con el texto, junto con la nota a la que pertenece. Mismo alcance que
     * el listado (empresa, no eliminadas, ambiente, registros propios por id_usuario).
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "nc.id_empresa = :id_empresa AND nc.eliminado = false
                      AND nc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $whereBase .= " AND nc.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.codigo_principal', 'd.codigo_auxiliar', 'd.descripcion', 'd.cantidad::text',
             'd.precio_unitario::text', 'd.precio_total_sin_impuesto::text'],
            $q, $params, 'dt'
        );
        $condAdic = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['a.nombre', 'a.valor'],
            $q, $params, 'ad'
        );
        if ($condDet === '' || $condAdic === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT nc.id, CONCAT(nc.establecimiento,'-',nc.punto_emision,'-',nc.secuencial) AS numero,
                           nc.fecha_emision, nc.estado, nc.num_doc_modificado, c.nombre AS cliente
                    FROM notas_credito_cabecera nc
                    LEFT JOIN clientes c ON c.id = nc.id_cliente
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'PRODUCTO' AS origen,
                           COALESCE(NULLIF(d.codigo_principal,''), d.codigo_auxiliar) AS tipo,
                           d.descripcion,
                           d.cantidad,
                           d.precio_total_sin_impuesto AS monto,
                           b.id AS id_nota, b.numero, b.fecha_emision, b.estado, b.num_doc_modificado, b.cliente
                    FROM notas_credito_detalle d
                    JOIN base b ON b.id = d.id_nota_credito
                    WHERE $condDet
                    UNION ALL
                    SELECT 'ADICIONAL' AS origen,
                           a.nombre AS tipo,
                           a.valor AS descripcion,
                           NULL AS cantidad,
                           NULL AS monto,
                           b.id AS id_nota, b.numero, b.fecha_emision, b.estado, b.num_doc_modificado, b.cliente
                    FROM notas_credito_adicional a
                    JOIN base b ON b.id = a.id_nota_credito
                    WHERE $condAdic
                ) x
                ORDER BY x.fecha_emision DESC, x.id_nota DESC, x.origen
                LIMIT $limit";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Notas de crédito del rango de fechas para exportación masiva (Descargas Masivas).
     * Sin paginar; el llamador (DescargaMasivaService) valida el límite de cantidad.
     */
    public function getParaDescargaMasiva(int $idEmpresa, ?string $fechaDesde, ?string $fechaHasta, ?int $numeroDesde, ?int $numeroHasta, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE nc.id_empresa = :id_empresa AND nc.eliminado = false
                   AND nc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                   " . $this->condicionRangoDescargaMasiva('nc.', $fechaDesde, $fechaHasta, $numeroDesde, $numeroHasta, $params);
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND nc.id_usuario = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT nc.id, nc.establecimiento, nc.punto_emision, nc.secuencial, nc.fecha_emision, nc.estado
                FROM notas_credito_cabecera nc
                $where
                ORDER BY nc.fecha_emision ASC, nc.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ¿Alguno de estos productos es inventariable? Solo esos devuelven stock al emitir la NC;
     * una nota con líneas libres o de servicios (p. ej. descuento por pronto pago) no mueve
     * inventario y no necesita bodega de reintegro.
     */
    public function hayProductosInventariables(int $idEmpresa, array $idsProducto): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsProducto))));
        if (!$ids) {
            return false;
        }

        $marcas = implode(', ', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare(
            "SELECT 1 FROM productos
              WHERE id_empresa = ? AND eliminado = false AND inventariable = true
                AND id IN ({$marcas})
              LIMIT 1"
        );
        $st->execute(array_merge([$idEmpresa], $ids));

        return (bool) $st->fetchColumn();
    }

    /** El vendedor existe, es de la empresa y no está eliminado. */
    public function vendedorEsDeEmpresa(int $idVendedor, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM vendedores WHERE id = ? AND id_empresa = ? AND eliminado = false LIMIT 1"
        );
        $st->execute([$idVendedor, $idEmpresa]);

        return (bool) $st->fetchColumn();
    }

    public function getPorId(int $id): ?array
    {
        // Nombre de la bodega de reintegro: la vista lo necesita cuando esa bodega ya no
        // está en el combo del usuario (acceso revocado o bodega inactiva).
        $conBodega = $this->columnaExiste('notas_credito_cabecera', 'id_bodega');
        $selBodega = $conBodega ? ", b.nombre as bodega_nombre" : "";
        $joinBodega = $conBodega ? "LEFT JOIN bodegas b ON b.id = nc.id_bodega" : "";
        // Ídem con el vendedor: si quedó inactivo no sale en el combo, pero la NC lo conserva.
        $conVendedor = $this->columnaExiste('notas_credito_cabecera', 'id_vendedor');
        $selVendedor = $conVendedor ? ", vend.nombre as vendedor_nombre" : "";
        $joinVendedor = $conVendedor ? "LEFT JOIN vendedores vend ON vend.id = nc.id_vendedor" : "";

        $sql = "SELECT nc.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.direccion as cliente_direccion, c.telefono as cliente_telefono,
                       c.email as cliente_email, c.tipo_id as cliente_tipo_id{$selBodega}{$selVendedor}
                FROM notas_credito_cabecera nc
                LEFT JOIN clientes c ON nc.id_cliente = c.id
                {$joinBodega}
                {$joinVendedor}
                WHERE nc.id = ? AND nc.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getDetalles(int $idNC): array
    {
        $sql = "SELECT * FROM notas_credito_detalle WHERE id_nota_credito = ? ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idNC]);
        return $st->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        $sql = "SELECT * FROM notas_credito_detalle_impuestos WHERE id_nota_credito_detalle = ?";
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
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsDetalle))));
        if (!$ids) {
            return [];
        }

        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM notas_credito_detalle_impuestos WHERE id_nota_credito_detalle IN ($ph)";
        $st  = $this->db->prepare($sql);
        $st->execute($ids);

        $porDetalle = [];
        foreach ($st->fetchAll() as $imp) {
            $porDetalle[(int) $imp['id_nota_credito_detalle']][] = $imp;
        }
        return $porDetalle;
    }

    // ── Información adicional ──────────────────────────────────────────────────

    public function getInfoAdicional(int $idNC): array
    {
        // Tolerante: si la tabla aún no existe en la BD (producción sin migrar),
        // devuelve vacío en lugar de romper la apertura/exportación de la NC.
        try {
            $sql = "SELECT * FROM notas_credito_adicional WHERE id_nota_credito = ? ORDER BY id ASC";
            $st = $this->db->prepare($sql);
            $st->execute([$idNC]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * `nombre` VARCHAR(300) y `valor` VARCHAR(500): texto libre del modal o de otro
     * módulo. PostgreSQL no trunca: un valor más largo aborta el INSERT con
     * SQLSTATE[22001] y se cae la nota entera, así que se capa al largo real de
     * cada columna (mismo criterio que FacturaVentaRepository::insertInfoAdicional).
     */
    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO notas_credito_adicional (id_nota_credito, nombre, valor) VALUES (?, ?, ?)";
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_nota_credito'],
            $this->caparTexto('nombre', $data['nombre'] ?? '', 'notas_credito_adicional'),
            $this->caparTexto('valor',  $data['valor']  ?? null, 'notas_credito_adicional'),
        ]);
    }

    public function deleteInfoAdicional(int $idNC): void
    {
        $st = $this->db->prepare("DELETE FROM notas_credito_adicional WHERE id_nota_credito = ?");
        $st->execute([$idNC]);
    }

    /**
     * Vincula el asiento contable generado a la nota de crédito.
     */
    public function updateAsientoContable(int $idNotaCredito, int $idAsiento): void
    {
        $st = $this->db->prepare("UPDATE notas_credito_cabecera SET id_asiento_contable = ? WHERE id = ?");
        $st->execute([$idAsiento, $idNotaCredito]);
    }

    public function insertCabecera(array $data): int
    {
        $cols = "id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso,
                    cod_doc_modificado, num_doc_modificado, fecha_emision_docs_sustento, motivo,
                    total_sin_impuestos, total_descuento, importe_total, estado, observaciones,
                    created_by, updated_by, tipo_ambiente";
        $params = [
            $data['id_empresa'],
            $data['id_establecimiento'],
            $data['id_punto_emision'],
            $data['id_cliente'],
            $data['id_usuario'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['clave_acceso'] ?? null,
            $data['cod_doc_modificado'] ?? '01',
            $data['num_doc_modificado'],
            $data['fecha_emision_docs_sustento'],
            $data['motivo'],
            $data['total_sin_impuestos'],
            $data['total_descuento'],
            $data['importe_total'],
            $data['estado'] ?? 'borrador',
            $data['observaciones'] ?? null,
            $data['id_usuario'],
            $data['id_usuario'],
            $data['tipo_ambiente'] ?? '1'
        ];

        // Bodega de reintegro (database/20260924_nc_cabecera_id_bodega.sql). Mientras ese SQL
        // no esté aplicado, la NC se guarda sin ella (el stock igual se reintegra).
        if ($this->columnaExiste('notas_credito_cabecera', 'id_bodega')) {
            $cols    .= ", id_bodega";
            $params[] = !empty($data['id_bodega']) ? (int) $data['id_bodega'] : null;
        }

        // Vendedor (database/20260924_nc_cabecera_id_vendedor.sql): mismo criterio que la bodega.
        if ($this->columnaExiste('notas_credito_cabecera', 'id_vendedor')) {
            $cols    .= ", id_vendedor";
            $params[] = !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null;
        }

        $marcas = implode(', ', array_fill(0, count($params), '?'));
        $st = $this->db->prepare("INSERT INTO notas_credito_cabecera ({$cols}) VALUES ({$marcas}) RETURNING id");
        $st->execute($params);

        return (int) $st->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE notas_credito_cabecera SET
                    id_establecimiento = ?, id_punto_emision = ?, id_cliente = ?,
                    fecha_emision = ?, establecimiento = ?, punto_emision = ?, secuencial = ?,
                    cod_doc_modificado = ?, num_doc_modificado = ?, fecha_emision_docs_sustento = ?, motivo = ?,
                    total_sin_impuestos = ?, total_descuento = ?, importe_total = ?,
                    observaciones = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ?";
        $params = [
            $data['id_establecimiento'],
            $data['id_punto_emision'],
            $data['id_cliente'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['cod_doc_modificado'] ?? '01',
            $data['num_doc_modificado'],
            $data['fecha_emision_docs_sustento'],
            $data['motivo'],
            $data['total_sin_impuestos'],
            $data['total_descuento'],
            $data['importe_total'],
            $data['observaciones'] ?? null,
            $data['id_usuario'],
        ];

        if ($this->columnaExiste('notas_credito_cabecera', 'id_bodega')) {
            $sql     .= ", id_bodega = ?";
            $params[] = !empty($data['id_bodega']) ? (int) $data['id_bodega'] : null;
        }

        if ($this->columnaExiste('notas_credito_cabecera', 'id_vendedor')) {
            $sql     .= ", id_vendedor = ?";
            $params[] = !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null;
        }

        $params[] = $id;
        $st = $this->db->prepare($sql . " WHERE id = ?");
        $st->execute($params);
    }

    public function insertDetalle(array $data): int
    {
        $cols = "id_nota_credito, id_producto, codigo_principal, codigo_auxiliar,
                 descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto";
        $params = [
            $data['id_nota_credito'],
            // Línea sin producto (concepto libre): el formulario manda '' y Postgres no lo acepta como integer.
            !empty($data['id_producto']) ? (int) $data['id_producto'] : null,
            $data['codigo_principal'] ?? null,
            $data['codigo_auxiliar'] ?? null,
            $data['descripcion'],
            $data['cantidad'],
            $data['precio_unitario'],
            $data['descuento'] ?? 0,
            $data['precio_total_sin_impuesto']
        ];

        // Línea de la factura de la que viene el ítem (database/20260919_nc_detalle_linea_factura.sql).
        // Mientras ese SQL no esté aplicado, el ítem se guarda sin el enlace.
        if ($this->columnaExiste('notas_credito_detalle', 'id_venta_detalle')) {
            $cols    .= ", id_venta_detalle";
            $params[] = !empty($data['id_venta_detalle']) ? (int) $data['id_venta_detalle'] : null;
        }

        $marcas = implode(', ', array_fill(0, count($params), '?'));
        $st = $this->db->prepare("INSERT INTO notas_credito_detalle ({$cols}) VALUES ({$marcas}) RETURNING id");
        $st->execute($params);

        return (int) $st->fetchColumn();
    }

    public function deleteDetalles(int $idNC): void
    {
        $sql = "DELETE FROM notas_credito_detalle WHERE id_nota_credito = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idNC]);
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO notas_credito_detalle_impuestos (
                    id_nota_credito_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_nota_credito_detalle'],
            $data['codigo_impuesto'],
            $data['codigo_porcentaje'],
            $data['tarifa'],
            $data['base_imponible'],
            $data['valor']
        ]);
    }

    public function getFormasPago(): array
    {
        $sql = "SELECT * FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        // Se devuelven TODAS (incluidas inactivas) para poder mostrar el IVA histórico de documentos
        // viejos (p.ej. 12% ya inactivo). El JS arma el select solo con activas + la del documento.
        $sql = "SELECT * FROM tarifa_iva ORDER BY status DESC, porcentaje_iva ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnidadesMedida(int $idEmpresa): array
    {
        $sql = "SELECT * FROM unidades_medida WHERE eliminado = false AND status = true AND id_empresa = :id_empresa ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateEstado(int $id, string $estado): void
    {
        $sql = "UPDATE notas_credito_cabecera SET estado = ? WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$estado, $id]);
    }

    public function updateAutorizacion(int $id, string $numero, string $fecha): void
    {
        $sql = "UPDATE notas_credito_cabecera SET numero_autorizacion = ?, fecha_autorizacion = ?, estado = 'autorizado' WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$numero, $fecha, $id]);
    }

    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $sql = "UPDATE notas_credito_cabecera SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = ? WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idUsuario, $id]);
    }

    public function getPorDocumentoModificado(string $numeroFactura, int $idEmpresa): array
    {
        $sql = "SELECT nc.*, u.nombre as usuario_nombre
                FROM notas_credito_cabecera nc
                LEFT JOIN usuarios u ON nc.id_usuario = u.id
                WHERE nc.num_doc_modificado = ? 
                  AND nc.id_empresa = ? 
                  AND nc.eliminado = false
                ORDER BY nc.fecha_emision DESC";
        $st = $this->db->prepare($sql);
        $st->execute([$numeroFactura, $idEmpresa]);
        return $st->fetchAll();
    }

    public function getSumaImporteNotasCredito(string $numeroFactura, int $idEmpresa, ?int $exceptoId = null): float
    {
        $sql = "SELECT SUM(importe_total) FROM notas_credito_cabecera 
                WHERE num_doc_modificado = ? 
                  AND id_empresa = ? 
                  AND eliminado = false
                  AND estado != 'anulado'";
        $params = [$numeroFactura, $idEmpresa];
        if ($exceptoId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptoId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (float) $st->fetchColumn();
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    public function updateDetalleXml(int $id, string $xml): void
    {
        try {
            $this->db->exec("ALTER TABLE notas_credito_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT;");
        } catch (\Throwable) {}

        $st = $this->db->prepare(
            "UPDATE notas_credito_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?"
        );
        $st->execute([$xml, $id]);
    }
}
