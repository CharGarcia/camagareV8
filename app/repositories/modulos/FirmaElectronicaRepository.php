<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class FirmaElectronicaRepository extends BaseRepository
{
    protected string $table = 'firmas_electronicas';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        $whereSql = $this->getBaseWhere($idEmpresa, 'f', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // $buscar es el string serializado del buscador (FiltrosModal en la vista):
        // `clave:valor ... texto libre`. Antes se buscaba el string completo con un
        // solo ILIKE, así que los filtros del buscador (estado:, estado_pago:…) nunca
        // se aplicaban.
        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre: columnas del listado y lo que identifica la firma. Decisión del
            // usuario: Tipo Firma (producto), Pago, Factura (estado) y Estado NO entran;
            // se filtran solo desde el modal. Datos sensibles fuera del texto libre a
            // propósito: código dactilar, fecha de nacimiento, dirección, adjuntos y rutas.
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    "CONCAT_WS(' ', f.nombres, f.apellidos)",              // Nombres
                    'f.numero_identificacion',                              // Identificación
                    'f.telefono',                                           // Teléfono
                    'f.correo',                                             // Correo
                    "TO_CHAR(f.fecha_caducidad, 'DD-MM-YYYY')",             // Caducidad
                    "TO_CHAR(f.created_at, 'DD-MM-YYYY HH24:MI:SS')",       // Fecha
                    'f.ruc_empresa', 'f.nombre_empresa',                    // Empresa (firma con RUC / jurídica)
                    'f.facturacion_nombres', 'f.facturacion_num_id',        // A quién se factura
                    "CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial)", // Nº de la factura
                    'f.observaciones',
                    'u.nombre',                                             // Usuario que registró
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $whereSql .= " AND {$condicion}";
            }
        }
        // Claves del modal de filtros (las viejas se conservan: viajan en los enlaces
        // de PDF/Excel).
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto'  => [
                'nombres'        => 'f.nombres',
                'apellidos'      => 'f.apellidos',
                'identificacion' => 'f.numero_identificacion',
                'telefono'       => 'f.telefono',
                'correo'         => 'f.correo',
                'tipo_firma'     => 'f.nombre_producto',
                'empresa'        => "CONCAT_WS(' ', f.ruc_empresa, f.nombre_empresa)",
            ],
            'exacto' => [
                'estado'              => 'f.estado',
                'estado_pago'         => 'f.estado_pago',
                'tipo_identificacion' => 'f.tipo_identificacion',
                'tipo_persona'        => 'f.tipo_persona',
                'id_producto'         => 'f.id_producto',
                'usuario'             => 'f.created_by',
                'con_ruc'             => "CASE WHEN f.con_ruc THEN 'si' ELSE 'no' END",
                // Misma regla que la columna Factura: sin factura o factura eliminada = por facturar
                'factura'             => "CASE WHEN f.id_factura IS NULL OR v.id IS NULL OR v.eliminado THEN 'por_facturar' ELSE LOWER(v.estado) END",
                // Misma regla que el color de la columna Caducidad (30 días)
                'vigencia'            => "CASE WHEN f.fecha_caducidad IS NULL THEN 'sin_fecha'
                                               WHEN f.fecha_caducidad < CURRENT_DATE THEN 'vencida'
                                               WHEN f.fecha_caducidad <= CURRENT_DATE + 30 THEN 'por_vencer'
                                               ELSE 'vigente' END",
            ],
            'fecha'  => [
                'fecha'     => 'f.created_at',
                'caducidad' => 'f.fecha_caducidad',
            ],
        ]);

        // Mismos JOIN en el conteo y el listado: el texto libre y los filtros usan v y u.
        $joinsFiltro = "LEFT JOIN ventas_cabecera v ON v.id = f.id_factura
                        LEFT JOIN usuarios        u ON u.id = f.created_by";

        $cols = [
            'nombres'              => 'f.nombres',
            'apellidos'            => 'f.apellidos',
            'numero_identificacion'=> 'f.numero_identificacion',
            'estado'               => 'f.estado',
            'estado_pago'          => 'f.estado_pago',
            'created_at'           => 'f.created_at',
            'nombre_producto'      => 'f.nombre_producto',
            'telefono'             => 'f.telefono',
            'correo'               => 'f.correo',
            'fecha_caducidad'      => 'f.fecha_caducidad',
            'tipo_persona'         => 'f.tipo_persona',
        ];
        $col = $cols[$ordenCol] ?? 'f.created_at';
        $dir = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} f {$joinsFiltro} {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset  = ($page - 1) * $perPage;
        $sqlRows = "SELECT f.id, f.tipo_persona, f.con_ruc,
                           f.tipo_identificacion, f.numero_identificacion, f.codigo_dactilar,
                           f.nombres, f.apellidos, f.fecha_nacimiento, f.sexo, f.nacionalidad,
                           f.telefono, f.correo, f.direccion,
                           f.cod_prov, f.cod_ciudad,
                           f.id_producto, f.nombre_producto,
                           f.ruc_empresa, f.nombre_empresa, f.cargo,
                           f.tipo_pago, f.estado, f.estado_pago, f.fecha_caducidad,
                           f.observaciones, f.created_at, f.updated_at,
                           f.facturacion_mismos_datos, f.facturacion_tipo_id, f.facturacion_num_id,
                           f.facturacion_nombres, f.facturacion_direccion, f.facturacion_correo,
                           f.facturacion_telefono, f.id_factura,
                           p.nombre AS provincia_nombre, c.nombre AS ciudad_nombre,
                           v.estado AS factura_estado, v.eliminado AS factura_eliminada,
                           v.establecimiento AS factura_establecimiento,
                           v.punto_emision   AS factura_punto_emision,
                           v.secuencial      AS factura_secuencial,
                           v.importe_total   AS factura_importe_total
                    FROM {$this->table} f
                    LEFT JOIN provincia       p ON p.codigo = f.cod_prov
                    LEFT JOIN ciudad          c ON c.codigo = f.cod_ciudad AND c.cod_prov = f.cod_prov
                    {$joinsFiltro}
                    {$whereSql}
                    ORDER BY {$col} {$dir}, f.id DESC";
        if ($perPage > 0) {
            $sqlRows .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * Opciones de los selects del modal de filtros: tipos de firma (productos) y
     * usuarios que ya aparecen en alguna firma de la empresa, con el mismo alcance
     * de registros propios que el listado.
     *
     * @return array{productos: array<int, array{id:int, nombre:string}>, usuarios: array<int, array{id:int, nombre:string}>}
     */
    public function getOpcionesFiltroListado(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $whereSql = $this->getBaseWhere($idEmpresa, 'f', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // Un producto puede haber cambiado de nombre: se toma el nombre más reciente guardado en la firma.
        $st = $this->db->prepare(
            "SELECT DISTINCT ON (f.id_producto) f.id_producto AS id, f.nombre_producto AS nombre
             FROM {$this->table} f
             {$whereSql} AND f.id_producto IS NOT NULL
             ORDER BY f.id_producto, f.created_at DESC"
        );
        $st->execute($params);
        $productos = $st->fetchAll(PDO::FETCH_ASSOC);
        usort($productos, static fn($a, $b) => strcmp((string) $a['nombre'], (string) $b['nombre']));

        $st = $this->db->prepare(
            "SELECT DISTINCT u.id, u.nombre
             FROM {$this->table} f
             JOIN usuarios u ON u.id = f.created_by
             {$whereSql}
             ORDER BY u.nombre"
        );
        $st->execute($params);

        return ['productos' => $productos, 'usuarios' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT f.*,
                       p.nombre  AS provincia_nombre,
                       c.nombre  AS ciudad_nombre,
                       u_c.nombre AS creado_por_nombre,
                       u_u.nombre AS actualizado_por_nombre,
                       v.estado        AS factura_estado,
                       v.eliminado     AS factura_eliminada,
                       v.establecimiento AS factura_establecimiento,
                       v.punto_emision   AS factura_punto_emision,
                       v.secuencial      AS factura_secuencial,
                       v.importe_total   AS factura_importe_total,
                       v.fecha_emision   AS factura_fecha_emision
                FROM {$this->table} f
                LEFT JOIN provincia       p   ON p.codigo   = f.cod_prov
                LEFT JOIN ciudad          c   ON c.codigo   = f.cod_ciudad AND c.cod_prov = f.cod_prov
                LEFT JOIN usuarios        u_c ON u_c.id     = f.created_by
                LEFT JOIN usuarios        u_u ON u_u.id     = f.updated_by
                LEFT JOIN ventas_cabecera v   ON v.id       = f.id_factura
                WHERE f.id = :id AND f.id_empresa = :id_empresa AND f.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by,
                    id_producto, nombre_producto,
                    tipo_persona, con_ruc, ruc_empresa, nombre_empresa, cargo,
                    tipo_identificacion, numero_identificacion, codigo_dactilar,
                    nombres, apellidos, fecha_nacimiento,
                    apellido_paterno, apellido_materno, telefono2, correo2, departamento, motivo,
                    rep_tipo_identificacion, rep_identificacion, rep_nombres, rep_apellidos,
                    telefono, correo, nacionalidad, sexo, direccion,
                    cod_prov, cod_ciudad,
                    tipo_pago, estado_pago, estado, fecha_caducidad, observaciones,
                    facturacion_mismos_datos, facturacion_tipo_id, facturacion_num_id,
                    facturacion_nombres, facturacion_direccion, facturacion_correo, facturacion_telefono,
                    eliminado, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by,
                    :id_producto, :nombre_producto,
                    :tipo_persona, :con_ruc, :ruc_empresa, :nombre_empresa, :cargo,
                    :tipo_identificacion, :numero_identificacion, :codigo_dactilar,
                    :nombres, :apellidos, :fecha_nacimiento,
                    :apellido_paterno, :apellido_materno, :telefono2, :correo2, :departamento, :motivo,
                    :rep_tipo_identificacion, :rep_identificacion, :rep_nombres, :rep_apellidos,
                    :telefono, :correo, :nacionalidad, :sexo, :direccion,
                    :cod_prov, :cod_ciudad,
                    :tipo_pago, :estado_pago, :estado, :fecha_caducidad, :observaciones,
                    :facturacion_mismos_datos, :facturacion_tipo_id, :facturacion_num_id,
                    :facturacion_nombres, :facturacion_direccion, :facturacion_correo, :facturacion_telefono,
                    false, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute($this->mapParams($data));
        return $this->lastInsertId('firmas_electronicas_id_seq');
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_producto = :id_producto, nombre_producto = :nombre_producto,
                    tipo_persona = :tipo_persona, con_ruc = :con_ruc,
                    ruc_empresa = :ruc_empresa, nombre_empresa = :nombre_empresa, cargo = :cargo,
                    tipo_identificacion = :tipo_identificacion,
                    numero_identificacion = :numero_identificacion,
                    codigo_dactilar = :codigo_dactilar,
                    nombres = :nombres, apellidos = :apellidos,
                    apellido_paterno = :apellido_paterno, apellido_materno = :apellido_materno,
                    telefono2 = :telefono2, correo2 = :correo2, departamento = :departamento, motivo = :motivo,
                    rep_tipo_identificacion = :rep_tipo_identificacion, rep_identificacion = :rep_identificacion,
                    rep_nombres = :rep_nombres, rep_apellidos = :rep_apellidos,
                    fecha_nacimiento = :fecha_nacimiento,
                    telefono = :telefono, correo = :correo,
                    nacionalidad = :nacionalidad, sexo = :sexo, direccion = :direccion,
                    cod_prov = :cod_prov, cod_ciudad = :cod_ciudad,
                    tipo_pago = :tipo_pago, estado_pago = :estado_pago,
                    estado = :estado, fecha_caducidad = :fecha_caducidad,
                    observaciones = :observaciones,
                    facturacion_mismos_datos = :facturacion_mismos_datos,
                    facturacion_tipo_id = :facturacion_tipo_id,
                    facturacion_num_id = :facturacion_num_id,
                    facturacion_nombres = :facturacion_nombres,
                    facturacion_direccion = :facturacion_direccion,
                    facturacion_correo = :facturacion_correo,
                    facturacion_telefono = :facturacion_telefono,
                    updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $params = $this->mapParams($data);
        unset($params[':id_usuario'], $params[':created_by']);
        $params[':id']         = $id;
        $params[':id_empresa'] = $idEmpresa;
        $params[':updated_by'] = $data['updated_by'] ?? $data['id_usuario'];
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':u' => $idUsuario]);
    }

    // ── Adjuntos ──────────────────────────────────────────────

    public function getAdjuntos(int $idFirma, int $idEmpresa): array
    {
        $sql = "SELECT id, tipo, nombre_original, nombre_archivo, mime_type, tamano_bytes, created_at
                FROM firmas_electronicas_adjuntos
                WHERE id_firma = :id_firma AND id_empresa = :id_empresa AND eliminado = false
                ORDER BY created_at ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_firma' => $idFirma, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createAdjunto(array $data): int
    {
        $sql = "INSERT INTO firmas_electronicas_adjuntos
                    (id_firma, id_empresa, tipo, nombre_original, nombre_archivo, ruta_relativa, mime_type, tamano_bytes, created_by)
                VALUES (:id_firma, :id_empresa, :tipo, :nombre_original, :nombre_archivo, :ruta_relativa, :mime_type, :tamano_bytes, :created_by)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_firma'       => $data['id_firma'],
            ':id_empresa'     => $data['id_empresa'],
            ':tipo'           => $data['tipo'],
            ':nombre_original'=> $data['nombre_original'],
            ':nombre_archivo' => $data['nombre_archivo'],
            ':ruta_relativa'  => $data['ruta_relativa'],
            ':mime_type'      => $data['mime_type'],
            ':tamano_bytes'   => $data['tamano_bytes'],
            ':created_by'     => $data['created_by'],
        ]);
        return $this->lastInsertId('firmas_electronicas_adjuntos_id_seq');
    }

    public function getAdjuntoPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM firmas_electronicas_adjuntos WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deleteAdjunto(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE firmas_electronicas_adjuntos SET
                    eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':u' => $idUsuario]);
    }

    // ── Trámite (portal del cliente, pago, Uanataca) ──────────

    /** Candado del trámite de una firma: serializa pago, facturación y cambios de estado (CLAUDE.md §8). */
    public function lockFirma(int $idFirma): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('firma_tramite:' || :id))");
        $st->execute([':id' => $idFirma]);
    }

    public function getPorSolicitud(int $idSolicitud, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT f.*, v.estado AS factura_estado, v.eliminado AS factura_eliminada,
                    v.establecimiento AS factura_establecimiento, v.punto_emision AS factura_punto_emision,
                    v.secuencial AS factura_secuencial, v.importe_total AS factura_importe_total
             FROM {$this->table} f
             LEFT JOIN ventas_cabecera v ON v.id = f.id_factura
             WHERE f.id_solicitud = :s AND f.id_empresa = :ie AND f.eliminado = false
             LIMIT 1"
        );
        $st->execute([':s' => $idSolicitud, ':ie' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Campos del trámite que el Service puede escribir sin pasar por el formulario completo. */
    private const CAMPOS_TRAMITE = [
        'estado', 'estado_pago', 'tipo_pago', 'id_factura', 'id_ingreso', 'fecha_caducidad',
        'pago_motivo_rechazo', 'pago_revisado_by', 'pago_revisado_at',
        'correo_confirmacion_token', 'correo_confirmacion_enviado_at', 'correo_confirmado_at',
        'uanataca_referencia', 'uanataca_registrada_at', 'emitida_at', 'observacion_cliente',
        'uanataca_uuid', 'uanataca_estado', 'uanataca_estado_at', 'uanataca_comentarios', 'uanataca_enviada_at', 'uanataca_error',
        'id_firma_renovacion',
    ];

    public function actualizarTramite(int $id, int $idEmpresa, array $campos, ?int $idUsuario): void
    {
        $sets   = [];
        $params = [':id' => $id, ':ie' => $idEmpresa, ':ub' => $idUsuario ?: null];
        foreach ($campos as $campo => $valor) {
            if (!in_array($campo, self::CAMPOS_TRAMITE, true)) {
                throw new \InvalidArgumentException("Campo de trámite no permitido: {$campo}");
            }
            $sets[] = "{$campo} = :{$campo}";
            $params[":{$campo}"] = $valor;
        }
        if (!$sets) {
            return;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . ",
                       updated_by = COALESCE(:ub, updated_by), updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :ie AND eliminado = false";
        $this->db->prepare($sql)->execute($params);
    }

    /** Datos del titular desde el portal (sin tocar pago, estado ni facturación). */
    public function actualizarDatosPortal(int $id, int $idEmpresa, array $data): void
    {
        $sql = "UPDATE {$this->table} SET
                    id_producto = :id_producto, nombre_producto = :nombre_producto,
                    tipo_persona = :tipo_persona, con_ruc = :con_ruc,
                    ruc_empresa = :ruc_empresa, nombre_empresa = :nombre_empresa, cargo = :cargo,
                    tipo_identificacion = :tipo_identificacion, numero_identificacion = :numero_identificacion,
                    codigo_dactilar = :codigo_dactilar, nombres = :nombres, apellidos = :apellidos,
                    apellido_paterno = :apellido_paterno, apellido_materno = :apellido_materno,
                    telefono2 = :telefono2, correo2 = :correo2, departamento = :departamento, motivo = :motivo,
                    rep_tipo_identificacion = :rep_tipo_identificacion, rep_identificacion = :rep_identificacion,
                    rep_nombres = :rep_nombres, rep_apellidos = :rep_apellidos,
                    fecha_nacimiento = :fecha_nacimiento, telefono = :telefono, correo = :correo,
                    nacionalidad = :nacionalidad, sexo = :sexo, direccion = :direccion,
                    cod_prov = :cod_prov, cod_ciudad = :cod_ciudad, observaciones = :observaciones,
                    facturacion_mismos_datos = :facturacion_mismos_datos,
                    facturacion_tipo_id = :facturacion_tipo_id, facturacion_num_id = :facturacion_num_id,
                    facturacion_nombres = :facturacion_nombres, facturacion_direccion = :facturacion_direccion,
                    facturacion_correo = :facturacion_correo, facturacion_telefono = :facturacion_telefono,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $params = $this->mapParams($data);
        unset($params[':id_usuario'], $params[':created_by'], $params[':tipo_pago'], $params[':estado_pago'],
              $params[':estado'], $params[':fecha_caducidad']);
        $params[':id']         = $id;
        $params[':id_empresa'] = $idEmpresa;
        $this->db->prepare($sql)->execute($params);
    }

    /** Firma creada desde el portal: guarda además el origen y la solicitud que la generó. */
    public function createDesdePortal(array $data, int $idSolicitud): int
    {
        $id = $this->create($data);
        $this->db->prepare("UPDATE {$this->table} SET origen = 'portal', id_solicitud = :s WHERE id = :id")
                 ->execute([':s' => $idSolicitud, ':id' => $id]);
        return $id;
    }

    /** Firma (de empresa activa) dueña de un token de confirmación de correo. */
    public function getPorTokenConfirmacion(string $token): ?array
    {
        $st = $this->db->prepare(
            "SELECT f.*, e.nombre AS empresa_nombre, s.token AS token_portal
             FROM {$this->table} f
             JOIN empresas e ON e.id = f.id_empresa AND e.estado = '1' AND e.eliminado = false
             LEFT JOIN firma_solicitudes s ON s.id = f.id_solicitud
             WHERE f.correo_confirmacion_token = :t AND f.eliminado = false
             LIMIT 1"
        );
        $st->execute([':t' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Firmas pagadas cuya factura ya está autorizada y todavía no recibieron el correo
     * de confirmación (lo envía el cron cuando el SRI autorizó después del pago).
     */
    public function getPendientesCorreoConfirmacion(int $limite = 30): array
    {
        $st = $this->db->prepare(
            "SELECT f.id, f.id_empresa
             FROM {$this->table} f
             JOIN ventas_cabecera v ON v.id = f.id_factura AND v.eliminado = false AND v.estado = 'autorizado'
             JOIN empresas e ON e.id = f.id_empresa AND e.estado = '1' AND e.eliminado = false
             WHERE f.eliminado = false
               AND f.estado_pago = 'confirmado'
               AND f.correo_confirmacion_enviado_at IS NULL
               AND f.correo_confirmado_at IS NULL
               AND f.estado NOT IN ('cancelada')
             ORDER BY f.id
             LIMIT " . max(1, $limite)
        );
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Firmas pagadas cuya factura quedó en borrador (el SRI no respondió a tiempo al
     * pagar). Solo las de los últimos 3 días, para no reintentar sin fin un error de
     * configuración (p. ej. firma de la empresa vencida): esas las ve el operador.
     */
    public function getPendientesEmitir(int $limite = 20): array
    {
        $st = $this->db->prepare(
            "SELECT f.id, f.id_empresa
             FROM {$this->table} f
             JOIN ventas_cabecera v ON v.id = f.id_factura AND v.eliminado = false AND v.estado = 'borrador'
             JOIN empresas e ON e.id = f.id_empresa AND e.estado = '1' AND e.eliminado = false
             WHERE f.eliminado = false AND f.estado_pago = 'confirmado' AND f.id_ingreso IS NOT NULL
               AND f.pago_revisado_at > CURRENT_TIMESTAMP - INTERVAL '3 days'
               AND f.pago_revisado_at < CURRENT_TIMESTAMP - INTERVAL '5 minutes'
             ORDER BY f.id
             LIMIT " . max(1, $limite)
        );
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEstadoFactura(int $idFactura, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, estado, eliminado FROM ventas_cabecera WHERE id = :id AND id_empresa = :ie"
        );
        $st->execute([':id' => $idFactura, ':ie' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Productos de firma (categoría cuyo nombre contiene "FIRMA") con su PVP. */
    public function getTiposFirma(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.nombre,
                    ROUND((p.precio_base + COALESCE(p.valor_ice, 0))
                        * (1 + COALESCE(ti.porcentaje_iva, 0)::numeric / 100), 2) AS pvp
             FROM productos p
             INNER JOIN categorias c ON c.id = p.id_categoria
             LEFT  JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
             WHERE p.id_empresa = :ie AND p.eliminado = false AND p.status = 1
               AND UPPER(c.nombre) LIKE '%FIRMA%'
             ORDER BY p.nombre ASC"
        );
        $st->execute([':ie' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Producto de la firma con lo necesario para facturarlo. */
    public function getProductoFacturable(int $idProducto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.nombre, p.precio_base, p.codigo, p.codigo_auxiliar, p.id_medida,
                    ti.id AS id_tarifa_iva, ti.porcentaje_iva, ti.codigo AS codigo_porcentaje_iva
             FROM productos p
             LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
             WHERE p.id = :id AND p.id_empresa = :ie AND p.eliminado = false"
        );
        $st->execute([':id' => $idProducto, ':ie' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Serie activa (establecimiento + punto) de la empresa; sin id, la primera. */
    public function getSerieFacturacion(int $idEmpresa, ?int $idPunto): ?array
    {
        $filtro = $idPunto ? 'AND p.id = :ip' : '';
        $st = $this->db->prepare(
            "SELECT p.id, p.codigo_punto, e.id AS id_establecimiento, e.codigo AS cod_establecimiento
             FROM empresa_punto_emision p
             JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
             WHERE p.id_empresa = :ie AND p.eliminado = false AND e.eliminado = false
               AND p.estado = 'activo' AND e.estado = 'activo' {$filtro}
             ORDER BY e.codigo ASC, p.codigo_punto ASC
             LIMIT 1"
        );
        $params = [':ie' => $idEmpresa];
        if ($idPunto) {
            $params[':ip'] = $idPunto;
        }
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Series activas de la empresa (selector de la configuración del módulo). */
    public function getSeriesFacturacion(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT p.id, e.codigo || '-' || p.codigo_punto AS serie, COALESCE(e.nombre, '') AS establecimiento
             FROM empresa_punto_emision p
             JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
             WHERE p.id_empresa = :ie AND p.eliminado = false AND e.eliminado = false
               AND p.estado = 'activo' AND e.estado = 'activo'
             ORDER BY e.codigo, p.codigo_punto"
        );
        $st->execute([':ie' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Formas de cobro de la empresa válidas para un ingreso (aprobación del comprobante). */
    public function getFormasCobro(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, UPPER(tipo) AS tipo
             FROM empresa_formas_pago
             WHERE id_empresa = :ie AND eliminado = false AND activo = true
               AND aplica_en IN ('AMBAS', 'INGRESO')
             ORDER BY CASE WHEN UPPER(tipo) = 'BANCO' THEN 0 ELSE 1 END, nombre"
        );
        $st->execute([':ie' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFormaCobro(int $idFormaCobro, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, UPPER(tipo) AS tipo, modalidad_tarjeta
             FROM empresa_formas_pago
             WHERE id = :id AND id_empresa = :ie AND eliminado = false AND activo = true
               AND aplica_en IN ('AMBAS', 'INGRESO')"
        );
        $st->execute([':id' => $idFormaCobro, ':ie' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Historial del trámite ─────────────────────────────────

    public function crearEvento(int $idEmpresa, int $idFirma, string $tipo, string $titulo, ?string $detalle, bool $visibleCliente, ?int $idUsuario): void
    {
        $st = $this->db->prepare(
            "INSERT INTO firmas_electronicas_eventos (id_empresa, id_firma, tipo, titulo, detalle, visible_cliente, created_by)
             VALUES (:ie, :f, :t, :ti, :d, :v, :u)"
        );
        $st->execute([
            ':ie' => $idEmpresa, ':f' => $idFirma, ':t' => $tipo, ':ti' => mb_substr($titulo, 0, 200),
            ':d' => $detalle, ':v' => $visibleCliente ? 'true' : 'false', ':u' => $idUsuario ?: null,
        ]);
    }

    public function getEventos(int $idFirma, int $idEmpresa, bool $soloCliente = false): array
    {
        $filtro = $soloCliente ? 'AND ev.visible_cliente = true' : '';
        $st = $this->db->prepare(
            "SELECT ev.id, ev.tipo, ev.titulo, ev.detalle, ev.visible_cliente, ev.created_at,
                    u.nombre AS usuario
             FROM firmas_electronicas_eventos ev
             LEFT JOIN usuarios u ON u.id = ev.created_by
             WHERE ev.id_firma = :f AND ev.id_empresa = :ie AND ev.eliminado = false {$filtro}
             ORDER BY ev.created_at ASC, ev.id ASC"
        );
        $st->execute([':f' => $idFirma, ':ie' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Configuración del módulo por empresa ──────────────────

    public function getConfig(int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM firmas_electronicas_config WHERE id_empresa = :ie AND eliminado = false LIMIT 1");
        $st->execute([':ie' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Config por el código del enlace fijo, solo si está activo y la empresa también. */
    public function getConfigPorEnlace(string $codigo): ?array
    {
        $st = $this->db->prepare(
            "SELECT c.*, e.nombre AS empresa_nombre, e.ruc AS empresa_ruc
             FROM firmas_electronicas_config c
             JOIN empresas e ON e.id = c.id_empresa AND e.estado = '1' AND e.eliminado = false
             WHERE c.enlace_fijo_codigo = :c AND c.enlace_fijo_activo = true AND c.eliminado = false
             LIMIT 1"
        );
        $st->execute([':c' => $codigo]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function guardarConfig(int $idEmpresa, array $d, int $idUsuario): void
    {
        $params = [
            ':ie'  => $idEmpresa,
            ':pe'  => $d['id_punto_emision'] ?: null,
            ':ur'  => $d['id_usuario_responsable'] ?: null,
            ':pp'  => $d['payphone_activo'] ? 'true' : 'false',
            ':tr'  => $d['transferencia_activa'] ? 'true' : 'false',
            ':dt'  => $d['datos_transferencia'] !== '' ? $d['datos_transferencia'] : null,
            ':ef'  => $d['enlace_fijo_activo'] ? 'true' : 'false',
            ':cod' => $d['enlace_fijo_codigo'],
            ':ua'     => !empty($d['uanataca_activo']) ? 'true' : 'false',
            ':uamb'   => ($d['uanataca_ambiente'] ?? '') === 'produccion' ? 'produccion' : 'pruebas',
            ':uusr'   => ($d['uanataca_usuario'] ?? '') !== '' ? $d['uanataca_usuario'] : null,
            // Clave vacía = conservar la guardada; se guarda cifrada.
            ':uclave' => ($d['uanataca_clave'] ?? '') !== '' ? \App\Helpers\CryptoHelper::encriptar($d['uanataca_clave']) : null,
            ':uauto'  => !empty($d['uanataca_envio_automatico']) ? 'true' : 'false',
            ':ucorreo'=> !empty($d['uanataca_exigir_correo']) ? 'true' : 'false',
            ':avact'  => !empty($d['avisos_vencimiento_activo']) ? 'true' : 'false',
            ':avcor'  => (string) ($d['avisos_dias_correo'] ?? '30,15,5,0'),
            ':avwa'   => (string) ($d['avisos_dias_whatsapp'] ?? '30,5,0'),
            ':u'   => $idUsuario,
        ];
        if ($this->getConfig($idEmpresa)) {
            $sql = "UPDATE firmas_electronicas_config SET
                        id_punto_emision = :pe, id_usuario_responsable = :ur, payphone_activo = :pp,
                        transferencia_activa = :tr, datos_transferencia = :dt, enlace_fijo_activo = :ef,
                        enlace_fijo_codigo = :cod, uanataca_activo = :ua, uanataca_ambiente = :uamb,
                        uanataca_usuario = :uusr, uanataca_clave_cifrada = COALESCE(:uclave, uanataca_clave_cifrada),
                        uanataca_envio_automatico = :uauto, uanataca_exigir_correo = :ucorreo,
                        avisos_vencimiento_activo = :avact, avisos_dias_correo = :avcor, avisos_dias_whatsapp = :avwa,
                        updated_by = :u, updated_at = CURRENT_TIMESTAMP
                    WHERE id_empresa = :ie AND eliminado = false";
        } else {
            $sql = "INSERT INTO firmas_electronicas_config
                        (id_empresa, id_punto_emision, id_usuario_responsable, payphone_activo, transferencia_activa,
                         datos_transferencia, enlace_fijo_activo, enlace_fijo_codigo, uanataca_activo, uanataca_ambiente,
                         uanataca_usuario, uanataca_clave_cifrada, uanataca_envio_automatico, uanataca_exigir_correo,
                         avisos_vencimiento_activo, avisos_dias_correo, avisos_dias_whatsapp, created_by)
                    VALUES (:ie, :pe, :ur, :pp, :tr, :dt, :ef, :cod, :ua, :uamb, :uusr, :uclave, :uauto, :ucorreo, :avact, :avcor, :avwa, :u)";
        }
        $this->db->prepare($sql)->execute($params);
    }

    // ── Avisos de vencimiento ─────────────────────────────────

    /** Empresas activas con los avisos de vencimiento encendidos. */
    public function getEmpresasConAvisos(): array
    {
        return $this->db->query(
            "SELECT c.id_empresa, c.avisos_dias_correo, c.avisos_dias_whatsapp
             FROM firmas_electronicas_config c
             JOIN empresas e ON e.id = c.id_empresa AND e.estado = '1' AND e.eliminado = false
             WHERE c.eliminado = false AND c.avisos_vencimiento_activo = true"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Firmas emitidas que vencen entre hoy y hoy + $maxDias, con los días que faltan y
     * si el titular ya renovó (otra firma suya, posterior y pagada).
     */
    public function getFirmasPorVencer(int $idEmpresa, int $maxDias): array
    {
        $st = $this->db->prepare(
            "SELECT f.*, (f.fecha_caducidad - CURRENT_DATE) AS dias_restantes,
                    EXISTS (
                        SELECT 1 FROM {$this->table} r
                        WHERE r.id_empresa = f.id_empresa AND r.eliminado = false AND r.id <> f.id
                          AND r.numero_identificacion = f.numero_identificacion
                          AND r.estado_pago = 'confirmado' AND r.created_at > f.created_at
                    ) AS renovada
             FROM {$this->table} f
             WHERE f.id_empresa = :ie AND f.eliminado = false AND f.estado = 'emitida'
               AND f.fecha_caducidad BETWEEN CURRENT_DATE AND CURRENT_DATE + CAST(:dias AS INTEGER)
             ORDER BY f.fecha_caducidad, f.id"
        );
        $st->execute([':ie' => $idEmpresa, ':dias' => $maxDias]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reserva un aviso antes de enviarlo. Automático: si ya existe para esa firma,
     * vencimiento, etapa y canal, devuelve null (otro proceso o un día anterior lo envió).
     */
    public function reservarAviso(int $idEmpresa, int $idFirma, string $caducidad, int $dias, string $canal, bool $manual, string $destino, ?int $idUsuario): ?int
    {
        $st = $this->db->prepare(
            "INSERT INTO firmas_avisos_vencimiento (id_empresa, id_firma, fecha_caducidad, dias, canal, manual, destino, created_by)
             VALUES (:ie, :f, :fc, :d, :c, :m, :dest, :u)
             ON CONFLICT (id_firma, fecha_caducidad, dias, canal) WHERE manual = false AND eliminado = false DO NOTHING
             RETURNING id"
        );
        $st->execute([':ie' => $idEmpresa, ':f' => $idFirma, ':fc' => $caducidad, ':d' => $dias, ':c' => $canal,
                      ':m' => $manual ? 'true' : 'false', ':dest' => mb_substr($destino, 0, 150), ':u' => $idUsuario ?: null]);
        $id = $st->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function resultadoAviso(int $id, bool $enviado, ?string $error): void
    {
        $this->db->prepare(
            "UPDATE firmas_avisos_vencimiento SET enviado = :e, error = :err, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        )->execute([':e' => $enviado ? 'true' : 'false', ':err' => $error !== null ? mb_substr($error, 0, 1000) : null, ':id' => $id]);
    }

    public function getAvisos(int $idFirma, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT dias, canal, manual, enviado, destino, error, created_at
             FROM firmas_avisos_vencimiento
             WHERE id_firma = :f AND id_empresa = :ie AND eliminado = false
             ORDER BY created_at DESC, id DESC"
        );
        $st->execute([':f' => $idFirma, ':ie' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Solicitud de renovación creada a partir de otra firma. */
    public function marcarRenovacion(int $idNueva, int $idAnterior, int $idSolicitud): void
    {
        $this->db->prepare(
            "UPDATE {$this->table} SET origen = 'renovacion', id_firma_anterior = :a, id_solicitud = :s WHERE id = :id"
        )->execute([':a' => $idAnterior, ':s' => $idSolicitud, ':id' => $idNueva]);
    }

    // ── Uanataca: mapeo de productos y colas del cron ─────────
    /** Vigencias ajustadas a mano: id_producto => {cantidad, unidad}. */
    public function getVigencias(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id_producto, cantidad, unidad FROM firmas_productos_vigencia WHERE id_empresa = :ie AND eliminado = false"
        );
        $st->execute([':ie' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_producto']] = ['cantidad' => (int) $r['cantidad'], 'unidad' => $r['unidad']];
        }
        return $out;
    }

    /** Reemplaza el ajuste de vigencia de un producto (cantidad null = quitar el ajuste). */
    public function guardarVigencia(int $idEmpresa, int $idProducto, ?int $cantidad, string $unidad, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE firmas_productos_vigencia SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id_empresa = :ie AND id_producto = :p AND eliminado = false"
        )->execute([':u' => $idUsuario, ':ie' => $idEmpresa, ':p' => $idProducto]);
        if ($cantidad === null) {
            return;
        }
        $this->db->prepare(
            "INSERT INTO firmas_productos_vigencia (id_empresa, id_producto, cantidad, unidad, created_by)
             VALUES (:ie, :p, :c, :un, :u)"
        )->execute([':ie' => $idEmpresa, ':p' => $idProducto, ':c' => $cantidad, ':un' => $unidad, ':u' => $idUsuario]);
    }

    /** ¿Alguna firma (de cualquier empresa) ya quedó enlazada a esta solicitud de Uanataca? */
    public function existeUanatacaUuid(string $uuid): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE uanataca_uuid = :u LIMIT 1");
        $st->execute([':u' => $uuid]);
        return (bool) $st->fetchColumn();
    }

    public function getMapeoProductos(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id_producto, uanataca_product_uuid, uanataca_code, uanataca_nombre
             FROM firmas_productos_uanataca WHERE id_empresa = :ie AND eliminado = false"
        );
        $st->execute([':ie' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_producto']] = $r;
        }
        return $out;
    }

    /** Reemplaza el producto de Uanataca de un producto del sistema ('' = quitar el enlace). */
    public function guardarMapeoProducto(int $idEmpresa, int $idProducto, string $uuid, string $code, string $nombre, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE firmas_productos_uanataca SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id_empresa = :ie AND id_producto = :p AND eliminado = false"
        )->execute([':u' => $idUsuario, ':ie' => $idEmpresa, ':p' => $idProducto]);
        if ($uuid === '') {
            return;
        }
        $this->db->prepare(
            "INSERT INTO firmas_productos_uanataca (id_empresa, id_producto, uanataca_product_uuid, uanataca_code, uanataca_nombre, created_by)
             VALUES (:ie, :p, :uuid, :code, :nom, :u)"
        )->execute([':ie' => $idEmpresa, ':p' => $idProducto, ':uuid' => $uuid, ':code' => $code ?: null, ':nom' => $nombre ?: null, ':u' => $idUsuario]);
    }

    /**
     * Firmas listas para el envío automático: pagadas, sin solicitud en Uanataca, de
     * empresas con la API y el envío automático activos (y correo confirmado si la
     * empresa lo exige). Un intento fallido espera 1 hora antes del siguiente.
     */
    public function getPendientesEnvioUanataca(int $limite = 10): array
    {
        $st = $this->db->prepare(
            "SELECT f.id, f.id_empresa
             FROM {$this->table} f
             JOIN firmas_electronicas_config c ON c.id_empresa = f.id_empresa AND c.eliminado = false
                  AND c.uanataca_activo = true AND c.uanataca_envio_automatico = true
             JOIN empresas e ON e.id = f.id_empresa AND e.estado = '1' AND e.eliminado = false
             WHERE f.eliminado = false AND f.estado = 'por_registrar' AND f.estado_pago = 'confirmado'
               AND f.uanataca_uuid IS NULL
               AND (c.uanataca_exigir_correo = false OR f.correo_confirmado_at IS NOT NULL)
               AND (f.uanataca_estado_at IS NULL OR f.uanataca_estado_at < CURRENT_TIMESTAMP - INTERVAL '1 hour')
             ORDER BY f.id
             LIMIT " . max(1, $limite)
        );
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Solicitudes enviadas a Uanataca aún sin estado final, no consultadas en los últimos 10 min. */
    public function getPendientesSincronizarUanataca(int $limite = 30): array
    {
        $st = $this->db->prepare(
            "SELECT f.id, f.id_empresa
             FROM {$this->table} f
             JOIN firmas_electronicas_config c ON c.id_empresa = f.id_empresa AND c.eliminado = false AND c.uanataca_activo = true
             JOIN empresas e ON e.id = f.id_empresa AND e.estado = '1' AND e.eliminado = false
             WHERE f.eliminado = false AND f.uanataca_uuid IS NOT NULL
               AND COALESCE(f.uanataca_estado, '') NOT IN ('ISSUED', 'CANCELED')
               AND f.estado NOT IN ('emitida', 'cancelada')
               AND (f.uanataca_estado_at IS NULL OR f.uanataca_estado_at < CURRENT_TIMESTAMP - INTERVAL '10 minutes')
             ORDER BY f.uanataca_estado_at NULLS FIRST, f.id
             LIMIT " . max(1, $limite)
        );
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Privado ───────────────────────────────────────────────

    private function mapParams(array $data): array
    {
        $fn = fn($val) => ($val === '' || $val === null) ? null : $val;
        return [
            ':id_empresa'           => $data['id_empresa'],
            ':id_usuario'           => $fn($data['id_usuario'] ?? null),
            ':created_by'           => $fn($data['id_usuario'] ?? null),
            ':id_producto'          => $fn($data['id_producto'] ?? null),
            ':nombre_producto'      => $fn($data['nombre_producto'] ?? null),
            ':tipo_persona'         => $data['tipo_persona'] ?? 'natural',
            ':con_ruc'              => ($data['con_ruc'] ?? false) ? 'true' : 'false',
            ':ruc_empresa'          => $fn($data['ruc_empresa'] ?? null),
            ':nombre_empresa'       => $fn($data['nombre_empresa'] ?? null),
            ':cargo'                => $fn($data['cargo'] ?? null),
            ':tipo_identificacion'  => $data['tipo_identificacion'],
            ':numero_identificacion'=> $data['numero_identificacion'],
            ':codigo_dactilar'      => $fn($data['codigo_dactilar'] ?? null),
            ':nombres'              => mb_strtoupper(trim($data['nombres']), 'UTF-8'),
            // Con paterno/materno separados, "apellidos" (listados, facturas) es su unión.
            ':apellidos'            => mb_strtoupper(trim(
                trim((string) ($data['apellido_paterno'] ?? '')) !== ''
                    ? trim(($data['apellido_paterno'] ?? '') . ' ' . ($data['apellido_materno'] ?? ''))
                    : (string) $data['apellidos']
            ), 'UTF-8'),
            ':apellido_paterno'     => $fn(mb_strtoupper(trim((string) ($data['apellido_paterno'] ?? '')), 'UTF-8')),
            ':apellido_materno'     => $fn(mb_strtoupper(trim((string) ($data['apellido_materno'] ?? '')), 'UTF-8')),
            ':telefono2'            => $fn(trim((string) ($data['telefono2'] ?? ''))),
            ':correo2'              => $fn(trim((string) ($data['correo2'] ?? ''))),
            ':departamento'         => $fn(mb_strtoupper(trim((string) ($data['departamento'] ?? '')), 'UTF-8')),
            ':motivo'               => $fn(trim((string) ($data['motivo'] ?? ''))),
            ':rep_tipo_identificacion' => $fn($data['rep_tipo_identificacion'] ?? null),
            ':rep_identificacion'   => $fn(trim((string) ($data['rep_identificacion'] ?? ''))),
            ':rep_nombres'          => $fn(mb_strtoupper(trim((string) ($data['rep_nombres'] ?? '')), 'UTF-8')),
            ':rep_apellidos'        => $fn(mb_strtoupper(trim((string) ($data['rep_apellidos'] ?? '')), 'UTF-8')),
            ':fecha_nacimiento'     => $fn($data['fecha_nacimiento'] ?? null),
            ':telefono'             => $fn($data['telefono'] ?? null),
            ':correo'               => $fn($data['correo'] ?? null),
            ':nacionalidad'         => $fn($data['nacionalidad'] ?? null),
            ':sexo'                 => $fn($data['sexo'] ?? null),
            ':direccion'            => $fn($data['direccion'] ?? null),
            ':cod_prov'             => $fn($data['cod_prov'] ?? null),
            ':cod_ciudad'           => $fn($data['cod_ciudad'] ?? null),
            ':tipo_pago'                 => $fn($data['tipo_pago'] ?? null),
            ':estado_pago'               => $data['estado_pago'] ?? 'pendiente',
            ':estado'                    => $data['estado'] ?? 'pendiente',
            ':fecha_caducidad'           => $fn($data['fecha_caducidad'] ?? null),
            ':observaciones'             => $fn($data['observaciones'] ?? null),
            ':facturacion_mismos_datos'  => ($data['facturacion_mismos_datos'] ?? true) ? 'true' : 'false',
            ':facturacion_tipo_id'       => $fn($data['facturacion_tipo_id'] ?? null),
            ':facturacion_num_id'        => $fn($data['facturacion_num_id'] ?? null),
            ':facturacion_nombres'       => $fn($data['facturacion_nombres'] ?? null),
            ':facturacion_direccion'     => $fn($data['facturacion_direccion'] ?? null),
            ':facturacion_correo'        => $fn($data['facturacion_correo'] ?? null),
            ':facturacion_telefono'      => $fn($data['facturacion_telefono'] ?? null),
        ];
    }
}
