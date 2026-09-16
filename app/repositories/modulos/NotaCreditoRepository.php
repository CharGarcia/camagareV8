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

        // Texto libre: las columnas del listado y lo que identifica la nota aunque no
        // sea columna. El buscador de la vista no sugiere campos; lo escrito se busca
        // en todo. Decisión del usuario: las columnas Correo y Estado NO entran en el
        // texto libre (se filtran solo desde el modal de filtros).
        if ($textoLibre !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    "CONCAT(nc.establecimiento,'-',nc.punto_emision,'-',nc.secuencial)", // Nº Nota
                    'nc.secuencial',
                    'nc.fecha_emision::text',                                             // Fecha
                    'c.nombre',                                                           // Cliente
                    'c.identificacion',                                                   // Identificación
                    'nc.num_doc_modificado',                                              // Doc. Modificado
                    'nc.total_sin_impuestos::text',                                       // Subtotal
                    'nc.total_descuento::text',                                           // Descuento
                    'nc.importe_total::text',                                             // Total
                    'nc.motivo',                                                          // Motivo
                    'u.nombre',                                                           // Usuario
                    // Fuera del listado, pero identifican la nota:
                    'nc.numero_autorizacion',
                    'nc.clave_acceso',
                    'nc.observaciones',
                    "(SELECT STRING_AGG(CONCAT_WS(' ', ncd.codigo_principal, ncd.codigo_auxiliar, ncd.descripcion), ' ') FROM notas_credito_detalle ncd WHERE ncd.id_nota_credito = nc.id)",
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

        // Conteo total
        $sqlCount = "SELECT COUNT(*) FROM notas_credito_cabecera nc
                     LEFT JOIN clientes c ON nc.id_cliente = c.id
                     LEFT JOIN usuarios u ON nc.id_usuario = u.id
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

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

        // Listado paginado
        $sql = "SELECT nc.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.email as cliente_email,
                       u.nombre as usuario_nombre,
                       e.tipo_ambiente, e.tipo_emision
                FROM notas_credito_cabecera nc
                LEFT JOIN clientes c ON nc.id_cliente = c.id
                LEFT JOIN usuarios u ON nc.id_usuario = u.id
                LEFT JOIN empresas e ON e.id = nc.id_empresa
                $where
                ORDER BY $ordenExpr $ordenDir, nc.id DESC" . ($perPage > 0 ? " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset : "");

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        return [
            'total' => $total,
            'rows'  => $rows
        ];
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

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT nc.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.direccion as cliente_direccion, c.telefono as cliente_telefono,
                       c.email as cliente_email, c.tipo_id as cliente_tipo_id
                FROM notas_credito_cabecera nc
                LEFT JOIN clientes c ON nc.id_cliente = c.id
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

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO notas_credito_adicional (id_nota_credito, nombre, valor) VALUES (?, ?, ?)";
        $st = $this->db->prepare($sql);
        $st->execute([$data['id_nota_credito'], $data['nombre'], $data['valor']]);
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
        $sql = "INSERT INTO notas_credito_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso,
                    cod_doc_modificado, num_doc_modificado, fecha_emision_docs_sustento, motivo,
                    total_sin_impuestos, total_descuento, importe_total, estado, observaciones,
                    created_by, updated_by, tipo_ambiente
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                ) RETURNING id";
        
        $st = $this->db->prepare($sql);
        $st->execute([
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
        ]);

        return (int) $st->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE notas_credito_cabecera SET
                    id_establecimiento = ?, id_punto_emision = ?, id_cliente = ?,
                    fecha_emision = ?, establecimiento = ?, punto_emision = ?, secuencial = ?,
                    cod_doc_modificado = ?, num_doc_modificado = ?, fecha_emision_docs_sustento = ?, motivo = ?,
                    total_sin_impuestos = ?, total_descuento = ?, importe_total = ?,
                    observaciones = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ?
                WHERE id = ?";
        
        $st = $this->db->prepare($sql);
        $st->execute([
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
            $id
        ]);
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO notas_credito_detalle (
                    id_nota_credito, id_producto, codigo_principal, codigo_auxiliar,
                    descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_nota_credito'],
            $data['id_producto'] ?? null,
            $data['codigo_principal'] ?? null,
            $data['codigo_auxiliar'] ?? null,
            $data['descripcion'],
            $data['cantidad'],
            $data['precio_unitario'],
            $data['descuento'] ?? 0,
            $data['precio_total_sin_impuesto']
        ]);

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
