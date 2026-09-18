<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class GuiaRemisionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('guias_remision_cabecera');
    }

    private function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Series REALMENTE usadas en guías de remisión guardadas (para el filtro "Serie" del buscador). */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM guias_remision_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuario = null
    ): array {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE g.id_empresa = :id_empresa AND g.eliminado = false AND g.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        // Texto libre: las columnas del listado —número, fecha, destinatario, RUC/cédula,
        // transportista, placa, motivo, fecha de inicio y usuario— más los datos de
        // cabecera que identifican la guía: identificación del transportista, documento
        // sustento, direcciones de partida y destino, ruta y observaciones.
        //
        // Qué NO entra, por decisión del usuario, y dónde se busca en su lugar (mismo
        // criterio que Facturas de Venta, Compras y las notas de crédito/débito):
        //   - Estado y Correo → modal de filtros (decisión anterior).
        //   - Clave de acceso y número de autorización → filtros `clave:` y
        //     `autorizacion:` (17-09-2026). En un comprobante electrónico son el MISMO
        //     número de 49 dígitos —fecha, RUC, serie, secuencial y un código numérico
        //     aleatorio de 8—, así que al escribir un número de documento caía dentro de
        //     la clave de OTRAS guías por puro azar y el listado devolvía filas sin
        //     ninguna coincidencia visible.
        //   - Productos de la guía (código y descripción de cada línea) → pestaña
        //     "Detalles" del modal de filtros (buscarEnDetalles()), que SÍ dice qué línea
        //     coincidió. De paso se va la subconsulta STRING_AGG, que corría por cada
        //     guía de la empresa.
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    \App\Helpers\SecuencialFormato::sqlNumeroCompleto('g.establecimiento', 'g.punto_emision', 'g.secuencial'), // Número (canónico)
                    'g.secuencial',
                    'g.fecha_emision::text',                                          // Emisión
                    'c.nombre',                                                       // Destinatario
                    'c.identificacion',                                               // RUC/Cédula
                    't.nombre',                                                       // Transportista
                    'g.placa',                                                        // Placa
                    'g.motivo_traslado',                                              // Motivo
                    'g.fecha_inicio_transporte::text',                                // F. Inicio
                    'u.nombre',                                                       // Usuario
                    // Fuera del listado, pero identifican la guía:
                    't.identificacion',
                    'g.num_doc_sustento',
                    'g.direccion_partida',
                    'g.direccion_destino',
                    'g.ruta',
                    'g.observaciones',
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'cliente'       => 'c.nombre',
                'transportista' => 't.nombre',
                'placa'         => 'g.placa',
                'motivo'        => 'g.motivo_traslado',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                // Nº completo (001-001-000000123)
                'numero'         => "CONCAT(g.establecimiento,'-',g.punto_emision,'-',g.secuencial)",
                'nro'            => "CONCAT(g.establecimiento,'-',g.punto_emision,'-',g.secuencial)",
                'usuario'        => 'u.nombre',
                'clave'          => 'g.clave_acceso',
                'clave_acceso'   => 'g.clave_acceso',
                'autorizacion'   => 'g.numero_autorizacion',
                'doc_sustento'   => 'g.num_doc_sustento',
                'partida'        => 'g.direccion_partida',
                'destino'        => 'g.direccion_destino',
                'ruta'           => 'g.ruta',
                'obs'            => 'g.observaciones',
            ],
            'exacto' => [
                'estado' => 'g.estado',
                'correo' => "COALESCE(NULLIF(g.estado_correo,''),'pendiente')",
                'estado_correo' => "COALESCE(NULLIF(g.estado_correo,''),'pendiente')",
                // Serie = establecimiento-puntoEmision (ej. "001-001"), tal como se
                // muestra en el selector "Serie" del buscador.
                'serie'  => "CONCAT(g.establecimiento,'-',g.punto_emision)",
                'id_usuario'       => 'g.id_usuario',
                'id_transportista' => 'g.id_transportista',
                // ambiente:1 (pruebas) / ambiente:2 (producción)
                'ambiente'         => 'g.tipo_ambiente',
            ],
            'fecha' => [
                'fecha'                   => 'g.fecha_emision',
                'fecha_emision'           => 'g.fecha_emision',
                'fecha_inicio'            => 'g.fecha_inicio_transporte',
                'fecha_inicio_transporte' => 'g.fecha_inicio_transporte',
                'fecha_fin'               => 'g.fecha_fin_transporte',
                'fecha_fin_transporte'    => 'g.fecha_fin_transporte',
                'fecha_autorizacion'      => 'g.fecha_autorizacion',
                'fecha_sustento'          => 'g.fecha_emision_doc_sustento',
            ],
            'numerico' => [
                'secuencial' => 'g.secuencial::numeric',
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND g.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $allowedCols = ['id', 'fecha_emision', 'secuencial', 'estado', 'estado_correo',
                        'cliente_nombre', 'transportista_nombre', 'placa', 'motivo_traslado',
                        'fecha_inicio_transporte', 'usuario_nombre'];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_emision';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match($ordenCol) {
            'cliente_nombre'       => 'c.nombre',
            'transportista_nombre' => 't.nombre',
            'usuario_nombre'       => 'u.nombre',
            default                => "g.{$ordenCol}",
        };

        $sqlCount = "SELECT COUNT(*)
                     FROM guias_remision_cabecera g
                     INNER JOIN clientes      c ON g.id_cliente      = c.id
                     INNER JOIN transportistas t ON g.id_transportista = t.id
                     LEFT  JOIN usuarios       u ON g.id_usuario       = u.id
                     {$where}";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        $sql = "SELECT g.*,
                       c.nombre        AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       t.nombre        AS transportista_nombre,
                       t.identificacion AS transportista_ruc,
                       u.nombre        AS usuario_nombre
                FROM guias_remision_cabecera g
                INNER JOIN clientes       c ON g.id_cliente       = c.id
                INNER JOIN transportistas t ON g.id_transportista = t.id
                LEFT  JOIN usuarios       u ON g.id_usuario       = u.id
                {$where}
                ORDER BY {$ordenExpr} {$ordenDir}, g.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        return ['rows' => $this->query($sql, $params)->fetchAll(), 'total' => (int) $total];
    }

    /** Usuarios que han registrado alguna guía en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConGuias(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM guias_remision_cabecera g
                JOIN usuarios u ON u.id = g.id_usuario
                WHERE g.id_empresa = :id_empresa AND g.eliminado = false
                ORDER BY u.nombre";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Transportistas usados en alguna guía de la empresa (select "Transportista" del modal de filtros). */
    public function getTransportistasConGuias(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT t.id, t.nombre, t.identificacion
                FROM guias_remision_cabecera g
                JOIN transportistas t ON t.id = g.id_transportista
                WHERE g.id_empresa = :id_empresa AND g.eliminado = false
                ORDER BY t.nombre";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las guías (pestaña "Detalles" del modal de filtros):
     * devuelve cada producto transportado o campo de información adicional que
     * coincide con el texto, junto con la guía a la que pertenece. Mismo alcance que
     * el listado (empresa, no eliminadas, ambiente, registros propios por id_usuario).
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "g.id_empresa = :id_empresa AND g.eliminado = false
                      AND g.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $whereBase .= " AND g.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.codigo_principal', 'd.codigo_auxiliar', 'd.descripcion', 'd.cantidad::text'],
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
                    SELECT g.id, CONCAT(g.establecimiento,'-',g.punto_emision,'-',g.secuencial) AS numero,
                           g.fecha_emision, g.estado, c.nombre AS cliente, t.nombre AS transportista
                    FROM guias_remision_cabecera g
                    LEFT JOIN clientes c ON c.id = g.id_cliente
                    LEFT JOIN transportistas t ON t.id = g.id_transportista
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'PRODUCTO' AS origen,
                           COALESCE(NULLIF(d.codigo_principal,''), d.codigo_auxiliar) AS tipo,
                           d.descripcion,
                           d.cantidad,
                           b.id AS id_guia, b.numero, b.fecha_emision, b.estado, b.cliente, b.transportista
                    FROM guias_remision_detalle d
                    JOIN base b ON b.id = d.id_guia_remision
                    WHERE $condDet
                    UNION ALL
                    SELECT 'ADICIONAL' AS origen,
                           a.nombre AS tipo,
                           a.valor AS descripcion,
                           NULL AS cantidad,
                           b.id AS id_guia, b.numero, b.fecha_emision, b.estado, b.cliente, b.transportista
                    FROM guias_remision_adicional a
                    JOIN base b ON b.id = a.id_guia_remision
                    WHERE $condAdic
                ) x
                ORDER BY x.fecha_emision DESC, x.id_guia DESC, x.origen
                LIMIT $limit";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Guías de remisión del rango de fechas para exportación masiva (Descargas Masivas).
     * Sin paginar; el llamador (DescargaMasivaService) valida el límite de cantidad.
     */
    public function getParaDescargaMasiva(int $idEmpresa, ?string $fechaDesde, ?string $fechaHasta, ?int $numeroDesde, ?int $numeroHasta, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE g.id_empresa = :id_empresa AND g.eliminado = false
                   AND g.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                   " . $this->condicionRangoDescargaMasiva('g.', $fechaDesde, $fechaHasta, $numeroDesde, $numeroHasta, $params);
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND g.id_usuario = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT g.id, g.establecimiento, g.punto_emision, g.secuencial, g.fecha_emision, g.estado
                FROM guias_remision_cabecera g
                $where
                ORDER BY g.fecha_emision ASC, g.id ASC";
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT g.*,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.direccion      AS cliente_direccion,
                       c.email          AS cliente_email,
                       c.tipo_id        AS cliente_tipo_id,
                       t.nombre         AS transportista_nombre,
                       t.identificacion AS transportista_ruc,
                       t.tipo_id        AS transportista_tipo_id,
                       t.email          AS transportista_email,
                       u.nombre         AS usuario_nombre,
                       uc.nombre        AS creado_por_nombre,
                       uu.nombre        AS actualizado_por_nombre
                FROM guias_remision_cabecera g
                INNER JOIN clientes       c  ON g.id_cliente       = c.id
                INNER JOIN transportistas t  ON g.id_transportista = t.id
                LEFT  JOIN usuarios       u  ON g.id_usuario       = u.id
                LEFT  JOIN usuarios       uc ON g.created_by       = uc.id
                LEFT  JOIN usuarios       uu ON g.updated_by       = uu.id
                WHERE g.id = ? AND g.eliminado = FALSE";
        $row = $this->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    public function getDetalles(int $idGuia): array
    {
        $sql = "SELECT d.*, COALESCE(p.nombre, d.descripcion) AS producto_nombre,
                       p.codigo AS producto_codigo
                FROM guias_remision_detalle d
                LEFT JOIN productos p ON d.id_producto = p.id
                WHERE d.id_guia_remision = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idGuia])->fetchAll();
    }

    public function getInfoAdicional(int $idGuia): array
    {
        return $this->query("SELECT * FROM guias_remision_adicional WHERE id_guia_remision = ?", [$idGuia])->fetchAll();
    }

    public function insertarCabecera(array $data): int
    {
        $sql = "INSERT INTO guias_remision_cabecera
                    (id_empresa, id_establecimiento, id_punto_emision, id_cliente,
                     id_transportista, id_usuario,
                     fecha_emision, establecimiento, punto_emision, secuencial,
                     clave_acceso, placa,
                     fecha_inicio_transporte, fecha_fin_transporte,
                     direccion_partida, direccion_destino, motivo_traslado, ruta,
                     cod_doc_sustento, num_doc_sustento, num_autorizacion_doc_sustento, fecha_emision_doc_sustento,
                     doc_aduanero_unico, cod_establecimiento_destino,
                     tipo_ambiente, tipo_emision, estado, estado_correo, observaciones,
                     created_by, updated_by)
                VALUES
                    (:id_empresa, :id_establecimiento, :id_punto_emision, :id_cliente,
                     :id_transportista, :id_usuario,
                     :fecha_emision, :establecimiento, :punto_emision, :secuencial,
                     :clave_acceso, :placa,
                     :fecha_inicio_transporte, :fecha_fin_transporte,
                     :direccion_partida, :direccion_destino, :motivo_traslado, :ruta,
                     :cod_doc_sustento, :num_doc_sustento, :num_autorizacion_doc_sustento, :fecha_emision_doc_sustento,
                     :doc_aduanero_unico, :cod_establecimiento_destino,
                     :tipo_ambiente, :tipo_emision, :estado, :estado_correo, :observaciones,
                     :created_by, :updated_by)
                RETURNING id";

        return (int) $this->query($sql, [
            ':id_empresa'                    => $data['id_empresa'],
            ':id_establecimiento'            => $data['id_establecimiento'],
            ':id_punto_emision'              => $data['id_punto_emision'],
            ':id_cliente'                    => $data['id_cliente'],
            ':id_transportista'              => $data['id_transportista'],
            ':id_usuario'                    => $data['id_usuario'],
            ':fecha_emision'                 => $data['fecha_emision'],
            ':establecimiento'               => $data['establecimiento'],
            ':punto_emision'                 => $data['punto_emision'],
            ':secuencial'                    => $data['secuencial'],
            ':clave_acceso'                  => $data['clave_acceso'] ?? null,
            ':placa'                         => $data['placa'],
            ':fecha_inicio_transporte'       => $data['fecha_inicio_transporte'],
            ':fecha_fin_transporte'          => $data['fecha_fin_transporte'],
            ':direccion_partida'             => $data['direccion_partida'],
            ':direccion_destino'             => $data['direccion_destino'],
            ':motivo_traslado'               => $data['motivo_traslado'],
            ':ruta'                          => $data['ruta'] ?? null,
            ':cod_doc_sustento'              => $data['cod_doc_sustento'] ?? null,
            ':num_doc_sustento'              => $data['num_doc_sustento'] ?? null,
            ':num_autorizacion_doc_sustento' => $data['num_autorizacion_doc_sustento'] ?? null,
            ':fecha_emision_doc_sustento'    => $data['fecha_emision_doc_sustento'] ?? null,
            ':doc_aduanero_unico'            => $data['doc_aduanero_unico'] ?? null,
            ':cod_establecimiento_destino'   => $data['cod_establecimiento_destino'] ?? null,
            ':tipo_ambiente'                 => $data['tipo_ambiente'] ?? '1',
            ':tipo_emision'                  => $data['tipo_emision'] ?? '1',
            ':estado'                        => $data['estado'] ?? 'borrador',
            ':estado_correo'                 => $data['estado_correo'] ?? 'pendiente',
            ':observaciones'                 => $data['observaciones'] ?? null,
            ':created_by'                    => $data['id_usuario'],
            ':updated_by'                    => $data['id_usuario'],
        ])->fetchColumn();
    }

    /** Estado del envío por correo del comprobante ('pendiente' | 'enviado'). */
    public function actualizarEstadoCorreo(int $id, string $estadoCorreo): void
    {
        $this->query(
            "UPDATE guias_remision_cabecera SET estado_correo = :estado_correo, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
            [':estado_correo' => $estadoCorreo, ':id' => $id]
        );
    }

    public function actualizarCabecera(int $id, array $data): void
    {
        $sql = "UPDATE guias_remision_cabecera SET
                    id_cliente = :id_cliente, id_transportista = :id_transportista,
                    fecha_emision = :fecha_emision, establecimiento = :establecimiento,
                    punto_emision = :punto_emision, secuencial = :secuencial,
                    clave_acceso = :clave_acceso, placa = :placa,
                    fecha_inicio_transporte = :fecha_inicio_transporte,
                    fecha_fin_transporte = :fecha_fin_transporte,
                    direccion_partida = :direccion_partida, direccion_destino = :direccion_destino,
                    motivo_traslado = :motivo_traslado, ruta = :ruta,
                    cod_doc_sustento = :cod_doc_sustento, num_doc_sustento = :num_doc_sustento,
                    num_autorizacion_doc_sustento = :num_autorizacion_doc_sustento,
                    fecha_emision_doc_sustento = :fecha_emision_doc_sustento,
                    doc_aduanero_unico = :doc_aduanero_unico,
                    cod_establecimiento_destino = :cod_establecimiento_destino,
                    tipo_ambiente = :tipo_ambiente, tipo_emision = :tipo_emision,
                    observaciones = :observaciones,
                    updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";

        $this->query($sql, [
            ':id_cliente'                    => $data['id_cliente'],
            ':id_transportista'              => $data['id_transportista'],
            ':fecha_emision'                 => $data['fecha_emision'],
            ':establecimiento'               => $data['establecimiento'],
            ':punto_emision'                 => $data['punto_emision'],
            ':secuencial'                    => $data['secuencial'],
            ':clave_acceso'                  => $data['clave_acceso'] ?? null,
            ':placa'                         => $data['placa'],
            ':fecha_inicio_transporte'       => $data['fecha_inicio_transporte'],
            ':fecha_fin_transporte'          => $data['fecha_fin_transporte'],
            ':direccion_partida'             => $data['direccion_partida'],
            ':direccion_destino'             => $data['direccion_destino'],
            ':motivo_traslado'               => $data['motivo_traslado'],
            ':ruta'                          => $data['ruta'] ?? null,
            ':cod_doc_sustento'              => $data['cod_doc_sustento'] ?? null,
            ':num_doc_sustento'              => $data['num_doc_sustento'] ?? null,
            ':num_autorizacion_doc_sustento' => $data['num_autorizacion_doc_sustento'] ?? null,
            ':fecha_emision_doc_sustento'    => $data['fecha_emision_doc_sustento'] ?? null,
            ':doc_aduanero_unico'            => $data['doc_aduanero_unico'] ?? null,
            ':cod_establecimiento_destino'   => $data['cod_establecimiento_destino'] ?? null,
            ':tipo_ambiente'                 => $data['tipo_ambiente'] ?? '1',
            ':tipo_emision'                  => $data['tipo_emision'] ?? '1',
            ':observaciones'                 => $data['observaciones'] ?? null,
            ':updated_by'                    => $data['id_usuario'],
            ':id'                            => $id,
            ':id_empresa'                    => $data['id_empresa'],
        ]);
    }

    public function insertarDetalle(int $idGuia, array $detalle): int
    {
        $sql = "INSERT INTO guias_remision_detalle
                    (id_guia_remision, id_producto, codigo_principal, codigo_auxiliar, descripcion, cantidad)
                VALUES (?, ?, ?, ?, ?, ?) RETURNING id";
        return (int) $this->query($sql, [
            $idGuia,
            $detalle['id_producto'] ?? null,
            $detalle['codigo_principal'] ?? null,
            $detalle['codigo_auxiliar']  ?? null,
            $detalle['descripcion'],
            (float) ($detalle['cantidad'] ?? 1),
        ])->fetchColumn();
    }

    public function eliminarDetalles(int $idGuia): void
    {
        $this->query("DELETE FROM guias_remision_detalle WHERE id_guia_remision = ?", [$idGuia]);
    }

    public function insertarAdicional(int $idGuia, string $nombre, string $valor): void
    {
        $this->query(
            "INSERT INTO guias_remision_adicional (id_guia_remision, nombre, valor) VALUES (?, ?, ?)",
            [$idGuia, $nombre, $valor]
        );
    }

    public function eliminarAdicionales(int $idGuia): void
    {
        $this->query("DELETE FROM guias_remision_adicional WHERE id_guia_remision = ?", [$idGuia]);
    }

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $this->query(
            "UPDATE guias_remision_cabecera SET estado = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$estado, $idUsuario, $id]
        );
    }

    public function actualizarSri(int $id, string $estado, ?string $fechaAut, ?string $xmlAut, ?string $errores, int $idUsuario): void
    {
        $sql = "UPDATE guias_remision_cabecera
                SET estado = ?, fecha_autorizacion = ?, numero_autorizacion = ?,
                    updated_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        $numAut = $xmlAut !== null ? (string)$id : null;
        $this->query($sql, [$estado, $fechaAut, $numAut, $idUsuario, $id]);
    }

    public function actualizarEstadoSriCompleto(
        int $id, string $estado, ?string $fechaAut, ?string $numAut, int $idUsuario
    ): void {
        $sql = "UPDATE guias_remision_cabecera
                SET estado = ?, fecha_autorizacion = ?, numero_autorizacion = ?,
                    updated_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        $this->query($sql, [$estado, $fechaAut, $numAut, $idUsuario, $id]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE guias_remision_cabecera
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = ?,
                    updated_at = CURRENT_TIMESTAMP, updated_by = ?
                WHERE id = ? AND id_empresa = ? AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([$idUsuario, $idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM guias_remision_cabecera
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND secuencial = ? AND eliminado = FALSE"
             . ($excluirId !== null ? " AND id <> ?" : "");
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial];
        if ($excluirId !== null) $params[] = $excluirId;
        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }
    public function getPorDocumentoSustento(string $numero, int $idEmpresa): array
    {
        $sql = "SELECT g.*, t.nombre AS transportista_nombre
                FROM guias_remision_cabecera g
                INNER JOIN transportistas t ON g.id_transportista = t.id
                WHERE g.num_doc_sustento = ? AND g.id_empresa = ? AND g.eliminado = FALSE
                ORDER BY g.fecha_emision DESC";
        return $this->query($sql, [$numero, $idEmpresa])->fetchAll();
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    public function updateDetalleXml(int $id, string $xml): void
    {
        try {
            $this->db->exec("ALTER TABLE guias_remision_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT;");
        } catch (\Throwable) {}

        $st = $this->db->prepare(
            "UPDATE guias_remision_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?"
        );
        $st->execute([$xml, $id]);
    }
}
