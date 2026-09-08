<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos del Anexo de Dividendos (ADI).
 *
 * Cubre las tres tablas del módulo (cabecera, beneficiarios y detalle de la
 * distribución) y las consultas que permiten armar el anexo a partir de la
 * contabilidad: qué cuentas del plan registran dividendos, qué movimientos
 * hubo en ellas durante el año y a qué tercero se le imputaron.
 *
 * Todas las consultas operativas filtran por id_empresa y eliminado = false, y
 * las que leen asientos respetan además el tipo_ambiente de la empresa (igual
 * que el balance de comprobación y los estados financieros).
 */
class AnexoDividendosRepository extends BaseRepository
{
    /**
     * Columnas por las que se puede ordenar el listado (whitelist del ORDER BY).
     * El tipo de informante se ordena por su código, no por la etiqueta: esa
     * vive en el catálogo del SRI (CatalogoAdi), no en la base.
     */
    public const COLUMNAS_ORDEN = [
        'anio', 'razon_social', 'id_informante', 'tipo_informante', 'estado',
        'total_beneficiarios', 'total_distribuido', 'total_gravado', 'total_retencion',
    ];

    public function __construct()
    {
        parent::__construct('anexo_dividendos');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** ¿Ya se aplicó el SQL del módulo? Evita romper si el código va por delante del despliegue. */
    public function tablasListas(): bool
    {
        return $this->tablaExiste('anexo_dividendos')
            && $this->tablaExiste('anexo_dividendos_beneficiario')
            && $this->tablaExiste('anexo_dividendos_detalle');
    }

    // ── Cabecera ─────────────────────────────────────────────────────────────

    /**
     * Listado paginado del módulo, con buscador y ordenamiento.
     *
     * @param int $perPage 0 = sin paginar (exportaciones a PDF y Excel).
     * @return array{total:int, rows:array}
     */
    public function getListado(
        int $idEmpresa,
        string $buscar = '',
        int $page = 1,
        int $perPage = 20,
        string $ordenCol = 'anio',
        string $ordenDir = 'desc',
        ?int $idUsuarioFiltro = null
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'anio';
        }
        $dir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = $this->getBaseWhere($idEmpresa, 'a', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // Los totales se calculan en subconsultas escalares y se exponen como
        // columnas para poder buscarlas y ordenar por ellas igual que el resto.
        $totales = "
            (SELECT COUNT(*) FROM anexo_dividendos_beneficiario b
              WHERE b.id_anexo = a.id AND b.eliminado = false) AS total_beneficiarios,
            (SELECT COALESCE(SUM(d.monto_dividendo_distribuido), 0) FROM anexo_dividendos_detalle d
              WHERE d.id_anexo = a.id AND d.eliminado = false) AS total_distribuido,
            (SELECT COALESCE(SUM(d.ingreso_gravado), 0) FROM anexo_dividendos_detalle d
              WHERE d.id_anexo = a.id AND d.eliminado = false) AS total_gravado,
            (SELECT COALESCE(SUM(d.monto_retencion), 0) FROM anexo_dividendos_detalle d
              WHERE d.id_anexo = a.id AND d.eliminado = false) AS total_retencion";

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = FiltrosBusqueda::condicionTexto(
                ['a.razon_social', 'a.id_informante', 'a.anio::text', 'a.observaciones'],
                $parsed['texto_libre'],
                $params,
                'adi_b'
            );

            // El estado se ve en la tabla como una etiqueta, no como su código:
            // se acepta escribirla, pero solo la palabra exacta (con un ILIKE
            // parcial, "gen" traería medio listado).
            $etiqueta = mb_strtolower(trim($parsed['texto_libre']), 'UTF-8');
            $extra = match ($etiqueta) {
                'borrador'   => "a.estado = 'borrador'",
                'generado'   => "a.estado = 'generado'",
                'presentado' => "a.estado = 'presentado'",
                default      => null,
            };

            if ($condicion !== '') {
                $where .= ' AND (' . $condicion . ($extra !== null ? ' OR ' . $extra : '') . ')';
            } elseif ($extra !== null) {
                $where .= ' AND ' . $extra;
            }
        }

        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => [
                'informante'     => 'a.razon_social',
                'razon'          => 'a.razon_social',
                'ruc'            => 'a.id_informante',
                'identificacion' => 'a.id_informante',
                'observaciones'  => 'a.observaciones',
            ],
            'exacto'   => [
                'estado'          => 'a.estado',
                'tipo_informante' => 'a.tipo_informante',
            ],
            'numerico' => ['anio' => 'a.anio'],
        ]);

        $sqlCount = "SELECT COUNT(*) FROM anexo_dividendos a {$where}";
        $total    = (int) $this->query($sqlCount, $params)->fetchColumn();

        // Los totales son alias de subconsultas: ordenar por ellos exige
        // envolver el SELECT, porque en Postgres no se pueden referenciar alias
        // del SELECT dentro de su propio ORDER BY cuando además hay LIMIT.
        $sql = "SELECT * FROM (
                    SELECT a.*, {$totales}
                    FROM anexo_dividendos a
                    {$where}
                ) t
                ORDER BY t.{$ordenCol} {$dir}, t.id DESC";

        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }

        return [
            'total' => $total,
            'rows'  => $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $row = $this->query(
            "SELECT * FROM anexo_dividendos WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [':id' => $id, ':id_empresa' => $idEmpresa]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPorAnio(int $idEmpresa, int $anio, string $tipoAmbiente): ?array
    {
        $row = $this->query(
            "SELECT * FROM anexo_dividendos
              WHERE id_empresa = :id_empresa AND anio = :anio AND tipo_ambiente = :amb AND eliminado = false
              LIMIT 1",
            [':id_empresa' => $idEmpresa, ':anio' => $anio, ':amb' => $tipoAmbiente]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(array $d): int
    {
        $sql = "INSERT INTO anexo_dividendos (
                    id_empresa, anio, tipo_ambiente,
                    tipo_informante, tipo_id_informante, id_informante, razon_social,
                    cuentas_dividendos, cuentas_resultados_acum, sbu,
                    estado, observaciones, created_by, updated_by
                ) VALUES (
                    :id_empresa, :anio, :amb,
                    :tipo_informante, :tipo_id_informante, :id_informante, :razon_social,
                    CAST(:cuentas_div AS JSONB), CAST(:cuentas_res AS JSONB), :sbu,
                    :estado, :observaciones, :usuario, :usuario
                ) RETURNING id";

        return (int) $this->query($sql, [
            ':id_empresa'         => (int) $d['id_empresa'],
            ':anio'               => (int) $d['anio'],
            ':amb'                => (string) $d['tipo_ambiente'],
            ':tipo_informante'    => (string) $d['tipo_informante'],
            ':tipo_id_informante' => (string) $d['tipo_id_informante'],
            ':id_informante'      => (string) $d['id_informante'],
            ':razon_social'       => (string) $d['razon_social'],
            ':cuentas_div'        => json_encode($d['cuentas_dividendos'] ?? [], JSON_UNESCAPED_UNICODE),
            ':cuentas_res'        => json_encode($d['cuentas_resultados_acum'] ?? [], JSON_UNESCAPED_UNICODE),
            ':sbu'                => (float) ($d['sbu'] ?? 0),
            ':estado'             => (string) ($d['estado'] ?? 'borrador'),
            ':observaciones'      => $d['observaciones'] ?? null,
            ':usuario'            => (int) $d['id_usuario'],
        ])->fetchColumn();
    }

    public function actualizarCabecera(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE anexo_dividendos SET
                    tipo_informante = :tipo_informante,
                    tipo_id_informante = :tipo_id_informante,
                    id_informante = :id_informante,
                    razon_social = :razon_social,
                    utilidad_ejercicio = :b1,
                    utilidad_distribuida_distinta_reinv = :b2,
                    utilidad_reinvertida_con_derecho = :b3,
                    utilidad_reinvertida_sin_derecho = :b4,
                    utilidad_pagada_anticipado = :b5,
                    utilidad_no_distribuida = :b6,
                    utilidad_no_distrib_ejer_ant = :b7,
                    utilidad_distrib_ejercicios_ant = :b8,
                    cuentas_dividendos = CAST(:cuentas_div AS JSONB),
                    cuentas_resultados_acum = CAST(:cuentas_res AS JSONB),
                    sbu = :sbu,
                    estado = :estado,
                    observaciones = :observaciones,
                    updated_by = :usuario,
                    updated_at = NOW()
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";

        $this->query($sql, [
            ':tipo_informante'    => (string) $d['tipo_informante'],
            ':tipo_id_informante' => (string) $d['tipo_id_informante'],
            ':id_informante'      => (string) $d['id_informante'],
            ':razon_social'       => (string) $d['razon_social'],
            ':b1'                 => (float) $d['utilidad_ejercicio'],
            ':b2'                 => (float) $d['utilidad_distribuida_distinta_reinv'],
            ':b3'                 => (float) $d['utilidad_reinvertida_con_derecho'],
            ':b4'                 => (float) $d['utilidad_reinvertida_sin_derecho'],
            ':b5'                 => (float) $d['utilidad_pagada_anticipado'],
            ':b6'                 => (float) $d['utilidad_no_distribuida'],
            ':b7'                 => (float) $d['utilidad_no_distrib_ejer_ant'],
            ':b8'                 => (float) $d['utilidad_distrib_ejercicios_ant'],
            ':cuentas_div'        => json_encode($d['cuentas_dividendos'] ?? [], JSON_UNESCAPED_UNICODE),
            ':cuentas_res'        => json_encode($d['cuentas_resultados_acum'] ?? [], JSON_UNESCAPED_UNICODE),
            ':sbu'                => (float) ($d['sbu'] ?? 0),
            ':estado'             => (string) ($d['estado'] ?? 'borrador'),
            ':observaciones'      => $d['observaciones'] ?? null,
            ':usuario'            => (int) $d['id_usuario'],
            ':id'                 => $id,
            ':id_empresa'         => $idEmpresa,
        ]);
    }

    public function marcarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $this->query(
            "UPDATE anexo_dividendos SET estado = :estado, updated_by = :usuario, updated_at = NOW()
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [':estado' => $estado, ':usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]
        );
    }

    public function softDelete(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->query(
            "UPDATE anexo_dividendos SET eliminado = true, deleted_at = NOW(), deleted_by = :usuario
              WHERE id = :id AND id_empresa = :id_empresa",
            [':usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]
        );
        // El detalle y los beneficiarios se marcan también: el anexo eliminado
        // no debe seguir sumando en ninguna consulta agregada.
        foreach (['anexo_dividendos_beneficiario', 'anexo_dividendos_detalle'] as $tabla) {
            $this->query(
                "UPDATE {$tabla} SET eliminado = true, deleted_at = NOW(), deleted_by = :usuario
                  WHERE id_anexo = :id AND id_empresa = :id_empresa AND eliminado = false",
                [':usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]
            );
        }
    }

    // ── Beneficiarios (C.1) ──────────────────────────────────────────────────

    public function getBeneficiarios(int $idAnexo, int $idEmpresa): array
    {
        return $this->query(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM anexo_dividendos_detalle d
                      WHERE d.id_beneficiario = b.id AND d.eliminado = false) AS total_detalles,
                    (SELECT COALESCE(SUM(d.monto_dividendo_distribuido), 0) FROM anexo_dividendos_detalle d
                      WHERE d.id_beneficiario = b.id AND d.eliminado = false) AS total_distribuido,
                    (SELECT COALESCE(SUM(d.ingreso_gravado), 0) FROM anexo_dividendos_detalle d
                      WHERE d.id_beneficiario = b.id AND d.eliminado = false) AS total_gravado,
                    (SELECT COALESCE(SUM(d.monto_retencion), 0) FROM anexo_dividendos_detalle d
                      WHERE d.id_beneficiario = b.id AND d.eliminado = false) AS total_retencion
             FROM anexo_dividendos_beneficiario b
             WHERE b.id_anexo = :id_anexo AND b.id_empresa = :id_empresa AND b.eliminado = false
             ORDER BY b.secuencial ASC, b.id ASC",
            [':id_anexo' => $idAnexo, ':id_empresa' => $idEmpresa]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBeneficiario(int $id, int $idEmpresa): ?array
    {
        $row = $this->query(
            "SELECT * FROM anexo_dividendos_beneficiario
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [':id' => $id, ':id_empresa' => $idEmpresa]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Busca un beneficiario ya registrado en el anexo por su identificación. */
    public function getBeneficiarioPorIdentificacion(int $idAnexo, string $identificacion): ?array
    {
        $row = $this->query(
            "SELECT * FROM anexo_dividendos_beneficiario
              WHERE id_anexo = :id_anexo AND numero_id_perceptor = :ident AND eliminado = false
              LIMIT 1",
            [':id_anexo' => $idAnexo, ':ident' => $identificacion]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function siguienteSecuencialBeneficiario(int $idAnexo): int
    {
        $max = $this->query(
            "SELECT COALESCE(MAX(secuencial), 0) FROM anexo_dividendos_beneficiario
              WHERE id_anexo = :id_anexo AND eliminado = false",
            [':id_anexo' => $idAnexo]
        )->fetchColumn();
        return (int) $max + 1;
    }

    public function crearBeneficiario(array $d): int
    {
        $sql = "INSERT INTO anexo_dividendos_beneficiario (
                    id_empresa, id_anexo, secuencial,
                    tipo_id_perceptor, numero_id_perceptor, nombre_beneficiario,
                    tipo_beneficiario, pais_residencia, regimen_fiscal_preferente,
                    tipo_id_beneficiario_efectivo, numero_id_beneficiario_efectivo,
                    tipo_entidad, id_entidad, created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_anexo, :secuencial,
                    :tipo_id, :numero_id, :nombre,
                    :tipo_benef, :pais, :regimen,
                    :tipo_id_efec, :numero_id_efec,
                    :tipo_entidad, :id_entidad, :usuario, :usuario
                ) RETURNING id";

        return (int) $this->query($sql, [
            ':id_empresa'     => (int) $d['id_empresa'],
            ':id_anexo'       => (int) $d['id_anexo'],
            ':secuencial'     => (int) $d['secuencial'],
            ':tipo_id'        => (string) $d['tipo_id_perceptor'],
            ':numero_id'      => (string) $d['numero_id_perceptor'],
            ':nombre'         => (string) ($d['nombre_beneficiario'] ?? ''),
            ':tipo_benef'     => (string) $d['tipo_beneficiario'],
            ':pais'           => (string) $d['pais_residencia'],
            ':regimen'        => $d['regimen_fiscal_preferente'] ?: null,
            ':tipo_id_efec'   => $d['tipo_id_beneficiario_efectivo'] ?: null,
            ':numero_id_efec' => $d['numero_id_beneficiario_efectivo'] ?: null,
            ':tipo_entidad'   => $d['tipo_entidad'] ?: null,
            ':id_entidad'     => !empty($d['id_entidad']) ? (int) $d['id_entidad'] : null,
            ':usuario'        => (int) $d['id_usuario'],
        ])->fetchColumn();
    }

    public function actualizarBeneficiario(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE anexo_dividendos_beneficiario SET
                    tipo_id_perceptor = :tipo_id,
                    numero_id_perceptor = :numero_id,
                    nombre_beneficiario = :nombre,
                    tipo_beneficiario = :tipo_benef,
                    pais_residencia = :pais,
                    regimen_fiscal_preferente = :regimen,
                    tipo_id_beneficiario_efectivo = :tipo_id_efec,
                    numero_id_beneficiario_efectivo = :numero_id_efec,
                    tipo_entidad = :tipo_entidad,
                    id_entidad = :id_entidad,
                    updated_by = :usuario,
                    updated_at = NOW()
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";

        $this->query($sql, [
            ':tipo_id'        => (string) $d['tipo_id_perceptor'],
            ':numero_id'      => (string) $d['numero_id_perceptor'],
            ':nombre'         => (string) ($d['nombre_beneficiario'] ?? ''),
            ':tipo_benef'     => (string) $d['tipo_beneficiario'],
            ':pais'           => (string) $d['pais_residencia'],
            ':regimen'        => $d['regimen_fiscal_preferente'] ?: null,
            ':tipo_id_efec'   => $d['tipo_id_beneficiario_efectivo'] ?: null,
            ':numero_id_efec' => $d['numero_id_beneficiario_efectivo'] ?: null,
            ':tipo_entidad'   => $d['tipo_entidad'] ?: null,
            ':id_entidad'     => !empty($d['id_entidad']) ? (int) $d['id_entidad'] : null,
            ':usuario'        => (int) $d['id_usuario'],
            ':id'             => $id,
            ':id_empresa'     => $idEmpresa,
        ]);
    }

    public function softDeleteBeneficiario(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->query(
            "UPDATE anexo_dividendos_detalle SET eliminado = true, deleted_at = NOW(), deleted_by = :usuario
              WHERE id_beneficiario = :id AND id_empresa = :id_empresa AND eliminado = false",
            [':usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]
        );
        $this->query(
            "UPDATE anexo_dividendos_beneficiario SET eliminado = true, deleted_at = NOW(), deleted_by = :usuario
              WHERE id = :id AND id_empresa = :id_empresa",
            [':usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]
        );
    }

    // ── Detalle de la distribución (C.2) ─────────────────────────────────────

    public function getDetalles(int $idAnexo, int $idEmpresa, ?int $idBeneficiario = null): array
    {
        $sql = "SELECT d.*, b.numero_id_perceptor, b.nombre_beneficiario, b.tipo_beneficiario, b.secuencial
                FROM anexo_dividendos_detalle d
                INNER JOIN anexo_dividendos_beneficiario b ON b.id = d.id_beneficiario AND b.eliminado = false
                WHERE d.id_anexo = :id_anexo AND d.id_empresa = :id_empresa AND d.eliminado = false";
        $params = [':id_anexo' => $idAnexo, ':id_empresa' => $idEmpresa];

        if ($idBeneficiario !== null) {
            $sql .= " AND d.id_beneficiario = :id_beneficiario";
            $params[':id_beneficiario'] = $idBeneficiario;
        }
        $sql .= " ORDER BY b.secuencial ASC, d.anio_genera_utilidad ASC, d.fecha_registro_contable ASC, d.id ASC";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $row = $this->query(
            "SELECT * FROM anexo_dividendos_detalle
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [':id' => $id, ':id_empresa' => $idEmpresa]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearDetalle(array $d): int
    {
        $sql = "INSERT INTO anexo_dividendos_detalle (
                    id_empresa, id_anexo, id_beneficiario,
                    anio_genera_utilidad, tipo_dividendo, fecha_registro_contable,
                    monto_dividendo_distribuido, ingreso_gravado, monto_retencion,
                    dividendo_pagado, isd_pagado, id_asiento, origen,
                    created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_anexo, :id_beneficiario,
                    :anio_gen, :tipo_div, :fecha,
                    :monto, :gravado, :retencion,
                    :pagado, :isd, :id_asiento, :origen,
                    :usuario, :usuario
                ) RETURNING id";

        return (int) $this->query($sql, [
            ':id_empresa'      => (int) $d['id_empresa'],
            ':id_anexo'        => (int) $d['id_anexo'],
            ':id_beneficiario' => (int) $d['id_beneficiario'],
            ':anio_gen'        => (int) $d['anio_genera_utilidad'],
            ':tipo_div'        => (string) $d['tipo_dividendo'],
            ':fecha'           => (string) $d['fecha_registro_contable'],
            ':monto'           => (float) $d['monto_dividendo_distribuido'],
            ':gravado'         => (float) ($d['ingreso_gravado'] ?? 0),
            ':retencion'       => (float) ($d['monto_retencion'] ?? 0),
            ':pagado'          => (string) ($d['dividendo_pagado'] ?? '02'),
            ':isd'             => (float) ($d['isd_pagado'] ?? 0),
            ':id_asiento'      => !empty($d['id_asiento']) ? (int) $d['id_asiento'] : null,
            ':origen'          => (string) ($d['origen'] ?? 'manual'),
            ':usuario'         => (int) $d['id_usuario'],
        ])->fetchColumn();
    }

    public function actualizarDetalle(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE anexo_dividendos_detalle SET
                    id_beneficiario = :id_beneficiario,
                    anio_genera_utilidad = :anio_gen,
                    tipo_dividendo = :tipo_div,
                    fecha_registro_contable = :fecha,
                    monto_dividendo_distribuido = :monto,
                    ingreso_gravado = :gravado,
                    monto_retencion = :retencion,
                    dividendo_pagado = :pagado,
                    isd_pagado = :isd,
                    updated_by = :usuario,
                    updated_at = NOW()
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";

        $this->query($sql, [
            ':id_beneficiario' => (int) $d['id_beneficiario'],
            ':anio_gen'        => (int) $d['anio_genera_utilidad'],
            ':tipo_div'        => (string) $d['tipo_dividendo'],
            ':fecha'           => (string) $d['fecha_registro_contable'],
            ':monto'           => (float) $d['monto_dividendo_distribuido'],
            ':gravado'         => (float) ($d['ingreso_gravado'] ?? 0),
            ':retencion'       => (float) ($d['monto_retencion'] ?? 0),
            ':pagado'          => (string) ($d['dividendo_pagado'] ?? '02'),
            ':isd'             => (float) ($d['isd_pagado'] ?? 0),
            ':usuario'         => (int) $d['id_usuario'],
            ':id'              => $id,
            ':id_empresa'      => $idEmpresa,
        ]);
    }

    public function softDeleteDetalle(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->query(
            "UPDATE anexo_dividendos_detalle SET eliminado = true, deleted_at = NOW(), deleted_by = :usuario
              WHERE id = :id AND id_empresa = :id_empresa",
            [':usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]
        );
    }

    /** Borra el detalle importado desde contabilidad, para poder reimportar limpio. */
    public function borrarDetalleImportado(int $idAnexo, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->query(
            "UPDATE anexo_dividendos_detalle SET eliminado = true, deleted_at = NOW(), deleted_by = :usuario
              WHERE id_anexo = :id_anexo AND id_empresa = :id_empresa
                AND origen = 'contabilidad' AND eliminado = false",
            [':usuario' => $idUsuario, ':id_anexo' => $idAnexo, ':id_empresa' => $idEmpresa]
        );
        return $st->rowCount();
    }

    /** Beneficiarios importados que quedaron sin ningún detalle tras reimportar. */
    public function limpiarBeneficiariosSinDetalle(int $idAnexo, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->query(
            "UPDATE anexo_dividendos_beneficiario b
                SET eliminado = true, deleted_at = NOW(), deleted_by = :usuario
              WHERE b.id_anexo = :id_anexo AND b.id_empresa = :id_empresa AND b.eliminado = false
                AND b.id_entidad IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM anexo_dividendos_detalle d
                     WHERE d.id_beneficiario = b.id AND d.eliminado = false
                )",
            [':usuario' => $idUsuario, ':id_anexo' => $idAnexo, ':id_empresa' => $idEmpresa]
        );
        return $st->rowCount();
    }

    /** Totales del anexo, para el talón resumen y las validaciones. */
    public function getTotales(int $idAnexo, int $idEmpresa): array
    {
        $row = $this->query(
            "SELECT COALESCE(SUM(monto_dividendo_distribuido), 0) AS distribuido,
                    COALESCE(SUM(ingreso_gravado), 0)             AS gravado,
                    COALESCE(SUM(monto_retencion), 0)             AS retencion,
                    COALESCE(SUM(isd_pagado), 0)                  AS isd,
                    COUNT(*)                                       AS registros
             FROM anexo_dividendos_detalle
             WHERE id_anexo = :id_anexo AND id_empresa = :id_empresa AND eliminado = false",
            [':id_anexo' => $idAnexo, ':id_empresa' => $idEmpresa]
        )->fetch(PDO::FETCH_ASSOC);

        $porAnio = $this->query(
            "SELECT anio_genera_utilidad, COALESCE(SUM(monto_dividendo_distribuido), 0) AS monto
             FROM anexo_dividendos_detalle
             WHERE id_anexo = :id_anexo AND id_empresa = :id_empresa AND eliminado = false
             GROUP BY anio_genera_utilidad
             ORDER BY anio_genera_utilidad",
            [':id_anexo' => $idAnexo, ':id_empresa' => $idEmpresa]
        )->fetchAll(PDO::FETCH_ASSOC);

        $row['por_anio'] = $porAnio;
        return $row ?: [];
    }

    // ── Origen contable ──────────────────────────────────────────────────────

    /** tipo_ambiente de la empresa, como cadena, igual que lo guardan los asientos. */
    public function getTipoAmbiente(int $idEmpresa): string
    {
        $amb = $this->query(
            "SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id",
            [':id' => $idEmpresa]
        )->fetchColumn();
        return (string) ($amb ?: '1');
    }

    /** Plan de cuentas de la empresa, para los selectores de configuración. */
    public function getPlanCuentas(int $idEmpresa): array
    {
        return $this->query(
            "SELECT id, codigo, nombre, nivel, supercias_esf, supercias_ecp_codigo
             FROM plan_cuentas
             WHERE id_empresa = :id_empresa AND eliminado = false
             ORDER BY codigo ASC",
            [':id_empresa' => $idEmpresa]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuentas candidatas a registrar la distribución de dividendos: las mapeadas
     * al casillero SuperCías de dividendos por pagar (2010706) o a la fila 990204
     * del ECP, más las que se llaman así en el plan.
     */
    public function getCuentasSugeridasDividendos(int $idEmpresa): array
    {
        return $this->query(
            "SELECT id, codigo, nombre, supercias_esf, supercias_ecp_codigo
             FROM plan_cuentas
             WHERE id_empresa = :id_empresa AND eliminado = false
               AND (
                    supercias_esf LIKE '2010706%'
                 OR supercias_ecp_codigo = '990204'
                 OR nombre ILIKE '%dividendo%'
                 OR nombre ILIKE '%utilidades por pagar%'
                 OR nombre ILIKE '%participes%'
               )
             ORDER BY codigo ASC",
            [':id_empresa' => $idEmpresa]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuentas candidatas a contener las utilidades pendientes de distribución:
     * resultados acumulados del patrimonio (SuperCías 3060x) o cuentas que se
     * llaman así en el plan.
     */
    public function getCuentasSugeridasResultados(int $idEmpresa): array
    {
        return $this->query(
            "SELECT id, codigo, nombre, supercias_esf
             FROM plan_cuentas
             WHERE id_empresa = :id_empresa AND eliminado = false
               AND (
                    supercias_esf LIKE '3060%'
                 OR nombre ILIKE '%resultados acumulados%'
                 OR nombre ILIKE '%utilidades acumuladas%'
                 OR nombre ILIKE '%utilidades retenidas%'
                 OR nombre ILIKE '%ganancias acumuladas%'
               )
             ORDER BY codigo ASC",
            [':id_empresa' => $idEmpresa]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Movimientos de distribución de dividendos del año, agrupados por tercero.
     *
     * Cada fila devuelve el importe del lado configurado para la cuenta ('haber'
     * para las de pasivo, 'debe' para las de patrimonio), la fecha del asiento y
     * los datos del tercero al que se imputó la línea. Las líneas sin tercero
     * vuelven con id_entidad NULL: el Service las agrupa aparte para que el
     * usuario asigne el beneficiario a mano.
     *
     * @param array<int,string> $cuentas  id de plan_cuentas => 'debe' | 'haber'
     */
    public function getMovimientosDividendos(int $idEmpresa, int $anio, array $cuentas, string $tipoAmbiente): array
    {
        if ($cuentas === []) {
            return [];
        }

        // Un CASE por cuenta resuelve el lado sin traer líneas de más: las
        // cuentas de pasivo solo aportan su haber (la distribución) y las de
        // patrimonio solo su debe, así el pago posterior del dividendo no se
        // cuenta como una segunda distribución.
        $ids     = [];
        $casos   = [];
        $params  = [':id_empresa' => $idEmpresa, ':anio' => $anio, ':amb' => $tipoAmbiente];
        $i       = 0;
        foreach ($cuentas as $idCuenta => $lado) {
            $idCuenta = (int) $idCuenta;
            $col      = $lado === 'debe' ? 'ad.debe' : 'ad.haber';
            $key      = ':c' . $i++;
            $ids[]                = $key;
            $params[$key]         = $idCuenta;
            $casos[]              = "WHEN ad.id_cuenta_contable = {$key} THEN {$col}";
        }
        $caseMonto = 'CASE ' . implode(' ', $casos) . ' ELSE 0 END';
        $inCuentas = implode(', ', $ids);

        $sql = "SELECT ac.id            AS id_asiento,
                       ac.fecha_asiento,
                       ac.numero_comprobante,
                       ac.concepto,
                       ad.id_cuenta_contable,
                       pc.codigo         AS codigo_cuenta,
                       pc.nombre         AS nombre_cuenta,
                       ad.referencia_detalle,
                       ad.tipo_entidad,
                       ad.id_entidad,
                       {$caseMonto}      AS monto
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac
                        ON ac.id = ad.id_asiento
                       AND ac.eliminado = false
                       AND ac.id_empresa = ad.id_empresa
                       AND ac.estado = 'contabilizado'
                       AND ac.tipo_ambiente = :amb
                       AND EXTRACT(YEAR FROM ac.fecha_asiento) = :anio
                INNER JOIN plan_cuentas pc
                        ON pc.id = ad.id_cuenta_contable
                       AND pc.eliminado = false
                WHERE ad.id_empresa = :id_empresa
                  AND ad.eliminado = false
                  AND ad.id_cuenta_contable IN ({$inCuentas})
                ORDER BY ac.fecha_asiento ASC, ac.id ASC, ad.id ASC";

        $filas = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        // Solo interesan las líneas con importe en el lado configurado.
        return array_values(array_filter($filas, static fn(array $f): bool => round((float) $f['monto'], 2) > 0));
    }

    /**
     * Saldo acreedor de las cuentas de resultados acumulados al cierre del año
     * anterior: es la utilidad de ejercicios previos que al 1 de enero del
     * período informado estaba pendiente de distribución (campo B.7).
     *
     * @param int[] $idsCuentas
     */
    public function getSaldoResultadosAcumulados(int $idEmpresa, int $anio, array $idsCuentas, string $tipoAmbiente): float
    {
        if ($idsCuentas === []) {
            return 0.0;
        }
        $ph     = [];
        $params = [
            ':id_empresa' => $idEmpresa,
            ':amb'        => $tipoAmbiente,
            ':hasta'      => ($anio - 1) . '-12-31',
        ];
        foreach (array_values($idsCuentas) as $i => $id) {
            $key          = ':rc' . $i;
            $ph[]         = $key;
            $params[$key] = (int) $id;
        }

        $saldo = $this->query(
            "SELECT COALESCE(SUM(ad.haber - ad.debe), 0)
             FROM asientos_contables_detalle ad
             INNER JOIN asientos_contables_cabecera ac
                     ON ac.id = ad.id_asiento
                    AND ac.eliminado = false
                    AND ac.id_empresa = ad.id_empresa
                    AND ac.estado = 'contabilizado'
                    AND ac.tipo_ambiente = :amb
                    AND ac.fecha_asiento <= CAST(:hasta AS DATE)
             WHERE ad.id_empresa = :id_empresa
               AND ad.eliminado = false
               AND ad.id_cuenta_contable IN (" . implode(', ', $ph) . ")",
            $params
        )->fetchColumn();

        return round((float) $saldo, 2);
    }

    /**
     * Utilidad del ejercicio (campo B.1), con el mismo criterio del Estado de
     * Resultados: ingresos (código 4) por su saldo acreedor, costos (5) y
     * gastos (6) por su saldo deudor. Se calcula aquí con una sola consulta
     * agregada en lugar de instanciar EstadosFinancierosService, que arrastra
     * el generador de reportes y la consolidación de grupos.
     */
    public function getUtilidadEjercicio(int $idEmpresa, int $anio, string $tipoAmbiente): float
    {
        $utilidad = $this->query(
            "SELECT COALESCE(SUM(
                        CASE
                            WHEN pc.codigo LIKE '4%' THEN ad.haber - ad.debe
                            WHEN pc.codigo LIKE '5%' OR pc.codigo LIKE '6%' THEN -(ad.debe - ad.haber)
                            ELSE 0
                        END
                    ), 0)
             FROM asientos_contables_detalle ad
             INNER JOIN asientos_contables_cabecera ac
                     ON ac.id = ad.id_asiento
                    AND ac.eliminado = false
                    AND ac.id_empresa = ad.id_empresa
                    AND ac.estado = 'contabilizado'
                    AND ac.tipo_ambiente = :amb
                    AND EXTRACT(YEAR FROM ac.fecha_asiento) = :anio
             INNER JOIN plan_cuentas pc
                     ON pc.id = ad.id_cuenta_contable
                    AND pc.eliminado = false
             WHERE ad.id_empresa = :id_empresa
               AND ad.eliminado = false",
            [':id_empresa' => $idEmpresa, ':anio' => $anio, ':amb' => $tipoAmbiente]
        )->fetchColumn();

        return round((float) $utilidad, 2);
    }

    /**
     * Datos del tercero al que apunta una línea de asiento, normalizados para
     * el anexo (identificación, nombre y tipo de identificación del sistema).
     */
    public function getTercero(string $tipoEntidad, int $idEntidad, int $idEmpresa): ?array
    {
        $sql = match ($tipoEntidad) {
            'cliente'  => "SELECT id, nombre AS nombre, tipo_id, identificacion FROM clientes
                            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            'proveedor' => "SELECT id, razon_social AS nombre, tipo_id_proveedor AS tipo_id, identificacion FROM proveedores
                             WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            'empleado' => "SELECT id, nombres_apellidos AS nombre, tipo_id, identificacion FROM empleados
                            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            default    => null,
        };
        if ($sql === null) {
            return null;
        }

        $row = $this->query($sql, [':id' => $idEntidad, ':id_empresa' => $idEmpresa])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Buscador de terceros para el modal del beneficiario: clientes, proveedores
     * y empleados de la empresa en una sola lista.
     */
    public function buscarTerceros(int $idEmpresa, string $texto, int $limite = 20): array
    {
        $like   = '%' . trim($texto) . '%';
        $params = [':id_empresa' => $idEmpresa, ':q' => $like, ':limite' => $limite];

        $sql = "SELECT * FROM (
                    SELECT 'cliente' AS tipo_entidad, id AS id_entidad, nombre, tipo_id, identificacion
                      FROM clientes
                     WHERE id_empresa = :id_empresa AND eliminado = false
                       AND (nombre ILIKE :q OR identificacion ILIKE :q)
                    UNION ALL
                    SELECT 'proveedor', id, razon_social, tipo_id_proveedor, identificacion
                      FROM proveedores
                     WHERE id_empresa = :id_empresa AND eliminado = false
                       AND (razon_social ILIKE :q OR identificacion ILIKE :q)
                    UNION ALL
                    SELECT 'empleado', id, nombres_apellidos, tipo_id, identificacion
                      FROM empleados
                     WHERE id_empresa = :id_empresa AND eliminado = false
                       AND (nombres_apellidos ILIKE :q OR identificacion ILIKE :q)
                ) t
                ORDER BY t.nombre ASC
                LIMIT :limite";

        $st = $this->db->prepare($sql);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':q', $like, PDO::PARAM_STR);
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Salario básico unificado por año, del catálogo global `salarios` (el mismo
     * que usa la nómina). Con él se calcula la franja exenta de tres SBU del
     * art. 39.2 LRTI, así que se lee de una sola fuente y no se retipea.
     *
     * @return array<int,float> anio => sbu
     */
    public function getSalariosBasicos(): array
    {
        if (!$this->tablaExiste('salarios')) {
            return [];
        }

        $filas = $this->query("SELECT ano, sbu FROM salarios ORDER BY ano DESC")->fetchAll(PDO::FETCH_ASSOC);

        $salarios = [];
        foreach ($filas as $f) {
            $salarios[(int) $f['ano']] = round((float) $f['sbu'], 2);
        }

        return $salarios;
    }

    /** SBU del año, o 0.00 si ese año no está en el catálogo. */
    public function getSbuPorAnio(int $anio): float
    {
        return $this->getSalariosBasicos()[$anio] ?? 0.0;
    }

    /** Años con movimientos contabilizados, para el selector de período. */
    public function getAniosDisponibles(int $idEmpresa, string $tipoAmbiente): array
    {
        $rows = $this->query(
            "SELECT DISTINCT EXTRACT(YEAR FROM fecha_asiento)::int AS anio
             FROM asientos_contables_cabecera
             WHERE id_empresa = :id_empresa AND eliminado = false AND tipo_ambiente = :amb
             ORDER BY anio DESC",
            [':id_empresa' => $idEmpresa, ':amb' => $tipoAmbiente]
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows ?: []);
    }
}
