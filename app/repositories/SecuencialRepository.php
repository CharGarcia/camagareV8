<?php

declare(strict_types=1);

namespace App\repositories;

use App\core\Database;
use PDO;

/**
 * Repository centralizado para la gestión de secuenciales de documentos electrónicos.
 * 
 * Responsabilidades:
 * - Obtener la configuración del secuencial inicial por punto de emisión y tipo de documento.
 * - Obtener los secuenciales ya utilizados en las tablas de documentos.
 * - Detectar huecos (gaps) en la numeración.
 */
class SecuencialRepository
{
    protected PDO $db;

    /**
     * Mapeo de tipo_documento → [tabla, columna_secuencial, columna_punto_emision]
     * Permite agregar nuevos tipos de documentos simplemente añadiendo una entrada.
     */
    private const DOCUMENT_MAP = [
        'Facturas de venta'                    => ['tabla' => 'ventas_cabecera',        'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Recibos de venta'                     => ['tabla' => 'recibos_venta_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Nota de crédito'                      => ['tabla' => 'notas_credito_cabecera',  'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Nota de débito'                       => ['tabla' => 'nota_debito_cabecera',    'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Facturas de reembolso'                => ['tabla' => 'factura_reembolso_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Retenciones de compras'               => ['tabla' => 'retencion_compra_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Guía de remisión'                     => ['tabla' => 'guias_remision_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Liquidación de compras o servicios'   => ['tabla' => 'liquidaciones_cabecera',  'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Proformas'                            => ['tabla' => 'proformas_cabecera',      'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ingresos'                             => ['tabla' => 'ingresos_cabecera',       'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Egresos'                              => ['tabla' => 'egresos_cabecera',        'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Traspasos'                            => ['tabla' => 'traspasos_cabecera',      'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Pedidos'                              => ['tabla' => 'pedidos_cabecera',        'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Órdenes de compra'                    => ['tabla' => 'ordenes_compra',           'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Consignaciones ventas'                => ['tabla' => 'consignaciones_ventas',   'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Retornos consignaciones ventas'       => ['tabla' => 'retornos_cv',             'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Facturacion consignaciones ventas'    => ['tabla' => 'consignaciones_facturas', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ordenes car-wash'                     => ['tabla' => 'carwash_ordenes',         'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ordenes de taller'                    => ['tabla' => 'taller_ordenes',          'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ordenes servicio externo'             => ['tabla' => 'servicioexterno_ordenes', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Cambios de productos'                 => ['tabla' => 'cambios_producto_cv',    'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Importaciones'                        => ['tabla' => 'importaciones_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
    ];

    /**
     * Tipos de documento que, ante el SRI, comparten el MISMO codDoc (Tabla 3) y
     * por tanto NO PUEDEN compartir el mismo punto de emisión: la numeración
     * estab-ptoEmi-secuencial debe ser única por (establecimiento, punto, codDoc),
     * y cada tipo de este listado lleva su propio contador independiente en
     * `empresa_secuencial` — si dos tipos de la misma familia comparten punto,
     * se generarían dos comprobantes distintos con el mismo número de documento.
     * Ej.: "Facturas de venta" y "Facturas de reembolso" son ambas codDoc=01
     * (el "reembolso" código 41 del ATS es solo un sub-campo del mismo XML de
     * Factura, ver XmlFacturaReembolsoService — no cambia el codDoc).
     */
    private const FAMILIAS_CODDOC = [
        '01' => ['Facturas de venta', 'Facturas de reembolso'],
    ];

    /**
     * Tipos de documento que solo pueden tener UN punto de emisión por empresa
     * (no varios, a diferencia del resto de tipos que sí pueden repetirse en
     * distintos puntos — p. ej. "Facturas de venta" en varias cajas). Hoy:
     * "Facturas de reembolso" — el sistema busca/crea un único punto dedicado
     * al dar de alta la empresa (ver
     * EmpresaInicializadorService::obtenerOCrearPuntoEmisionReembolso) y el
     * resto del flujo de Reembolso asume que existe solo uno.
     */
    private const TIPOS_PUNTO_UNICO = ['Facturas de reembolso'];

    /**
     * Agrupación por área de los tipos de documento soportados (solo para mostrar
     * en la ayuda de la pestaña Secuenciales). Los nombres deben coincidir EXACTO
     * con las claves de DOCUMENT_MAP.
     */
    private const DOCUMENT_AREAS = [
        'Ventas'         => ['Facturas de venta', 'Recibos de venta', 'Nota de crédito', 'Nota de débito', 'Facturas de reembolso', 'Proformas', 'Guía de remisión'],
        'Compras'        => ['Retenciones de compras', 'Liquidación de compras o servicios', 'Órdenes de compra', 'Importaciones'],
        'Tesorería'      => ['Ingresos', 'Egresos', 'Traspasos'],
        'Operativos'     => ['Pedidos', 'Cambios de productos', 'Ordenes car-wash', 'Ordenes de taller', 'Ordenes servicio externo'],
        'Consignaciones' => ['Consignaciones ventas', 'Retornos consignaciones ventas', 'Facturacion consignaciones ventas'],
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Tipos soportados agrupados por área para la tarjeta de ayuda.
     * Cualquier tipo de DOCUMENT_MAP no clasificado cae en "Otros".
     *
     * @return array<string, string[]>
     */
    public function getTiposDocumentoAgrupados(): array
    {
        $grupos = self::DOCUMENT_AREAS;
        $clasificados = array_merge(...array_values(self::DOCUMENT_AREAS));
        $otros = array_values(array_diff(array_keys(self::DOCUMENT_MAP), $clasificados));
        if (!empty($otros)) {
            $grupos['Otros'] = $otros;
        }
        return $grupos;
    }

    /**
     * Mapa tipoDocumento => [otros tipos en conflicto de codDoc], solo para los
     * tipos que SÍ tienen algún conflicto (para pintar la ayuda/validación en el
     * frontend de Empresa → Secuenciales sin duplicar la lista en la vista).
     */
    public function getMapaConflictosCodDoc(): array
    {
        $mapa = [];
        foreach (self::FAMILIAS_CODDOC as $familia) {
            foreach ($familia as $tipo) {
                $mapa[$tipo] = array_values(array_diff($familia, [$tipo]));
            }
        }
        return $mapa;
    }

    /** Otros tipos de DOCUMENT_MAP que comparten codDoc SRI con $tipoDocumento (ver FAMILIAS_CODDOC). */
    public function tiposEnConflictoCodDoc(string $tipoDocumento): array
    {
        foreach (self::FAMILIAS_CODDOC as $familia) {
            if (in_array($tipoDocumento, $familia, true)) {
                return array_values(array_diff($familia, [$tipoDocumento]));
            }
        }
        return [];
    }

    /** Tipos de documento que solo pueden existir en un único punto de emisión por empresa (ver TIPOS_PUNTO_UNICO). */
    public function getTiposPuntoUnico(): array
    {
        return self::TIPOS_PUNTO_UNICO;
    }

    /**
     * Para un tipo de TIPOS_PUNTO_UNICO: el id del punto de emisión (de esta
     * empresa) que ya lo tiene configurado, excluyendo $idPuntoExcluir. Null si
     * el tipo no está en TIPOS_PUNTO_UNICO o si ningún otro punto lo tiene.
     * Usar antes de crear ese tipo en un punto nuevo: si devuelve un id, no
     * debe ofrecerse/crearse ahí, porque el resto del sistema asume un único
     * punto para ese tipo.
     */
    public function getPuntoConTipoUnico(string $tipoDocumento, int $idEmpresa, int $idPuntoExcluir = 0): ?int
    {
        if (!in_array($tipoDocumento, self::TIPOS_PUNTO_UNICO, true)) {
            return null;
        }

        // JOIN contra empresa_punto_emision (y no solo empresa_secuencial.eliminado):
        // un punto eliminado puede dejar secuenciales huérfanos si se borró antes de
        // que deletePuntoEmision() diera de baja también sus secuenciales (datos ya
        // en producción de antes de ese fix) — sin este JOIN, ese huérfano seguiría
        // bloqueando el tipo en cualquier punto nuevo aunque el original ya no exista.
        $sql = "SELECT es.id_punto_emision
                  FROM empresa_secuencial es
                  INNER JOIN empresa_punto_emision pe ON pe.id = es.id_punto_emision
                 WHERE es.id_empresa = :id_empresa AND es.tipo_documento = :tipo
                   AND es.eliminado = false AND pe.eliminado = false
                   AND es.id_punto_emision <> :id_punto_excluir
                 LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'        => $idEmpresa,
            ':tipo'              => $tipoDocumento,
            ':id_punto_excluir'  => $idPuntoExcluir,
        ]);
        $row = $st->fetchColumn();
        return $row !== false ? (int) $row : null;
    }

    /**
     * ¿El punto de emisión ya tiene configurado (activo) algún tipo de documento
     * que comparta codDoc SRI con $tipoDocumento? Si es así, devuelve el nombre
     * del tipo en conflicto; si no, null. Usar antes de crear una nueva
     * configuración de secuencial para no duplicar numeración ante el SRI.
     */
    public function getConflictoCodDoc(int $idPuntoEmision, string $tipoDocumento, int $idEmpresa): ?string
    {
        $conflictivos = $this->tiposEnConflictoCodDoc($tipoDocumento);
        if ($conflictivos === []) {
            return null;
        }

        $placeholders = [];
        $params = [':id_punto' => $idPuntoEmision, ':id_empresa' => $idEmpresa];
        foreach ($conflictivos as $i => $tipo) {
            $ph = ":t{$i}";
            $placeholders[] = $ph;
            $params[$ph] = $tipo;
        }

        $sql = "SELECT tipo_documento FROM empresa_secuencial
                WHERE id_punto_emision = :id_punto
                  AND id_empresa = :id_empresa
                  AND eliminado = false
                  AND tipo_documento IN (" . implode(',', $placeholders) . ")
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string) $row['tipo_documento'] : null;
    }

    /**
     * Bloqueo transaccional (se libera solo al COMMIT/ROLLBACK) por punto de emisión + tipo de
     * documento. DEBE llamarse antes de calcular el siguiente secuencial (obtenerSiguienteSecuencial),
     * dentro de la MISMA transacción que luego inserta la cabecera del documento — igual que
     * InventarioRepository::lockStock() (ver CLAUDE.md §8). Sin esto, dos documentos emitidos casi
     * al mismo tiempo pueden calcular el mismo "siguiente número" antes de que ninguno lo inserte.
     * No hace falta id_empresa: id_punto_emision ya es único por sí solo.
     */
    public function lockSecuencial(int $idPuntoEmision, string $tipoDocumento): void
    {
        $sql = "SELECT pg_advisory_xact_lock(hashtext('secuencial:' || :p || ':' || :t))";
        $st = $this->db->prepare($sql);
        $st->execute([':p' => $idPuntoEmision, ':t' => $tipoDocumento]);
    }

    /**
     * Serie por defecto de la empresa: establecimiento ACTIVO + punto de emisión ACTIVO de menor
     * código, EXCLUYENDO el punto dedicado a "Facturas de reembolso" (ver TIPOS_PUNTO_UNICO): ese
     * punto existe solo para esa familia de codDoc y no debe recibir documentos de otros tipos.
     *
     * Es la serie que se asigna a los documentos que NO traen serie propia — hoy, los migrados del
     * sistema anterior cuyo origen no guarda establecimiento/punto (ingresos, egresos, pedidos,
     * cambios de producto). Los documentos autorizados por el SRI SIEMPRE traen la suya y nunca
     * deben pasar por aquí.
     *
     * Los activos van primero en el ORDER BY (en vez de filtrarse con WHERE) para que una empresa
     * cuyos establecimientos/puntos estén marcados inactivos siga obteniendo una serie en lugar de
     * quedarse sin ninguna.
     *
     * @return array{id_establecimiento:int,establecimiento:string,id_punto_emision:int,punto_emision:string}|null
     */
    public function getSerieDefecto(int $idEmpresa): ?array
    {
        $sql = "SELECT e.id AS id_establecimiento,
                       LPAD(REGEXP_REPLACE(e.codigo, '[^0-9]', '', 'g'), 3, '0')       AS establecimiento,
                       p.id AS id_punto_emision,
                       LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0') AS punto_emision
                  FROM empresa_punto_emision p
                  INNER JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE e.id_empresa = :id_empresa
                   AND e.eliminado = false
                   AND p.eliminado = false
                   AND NOT EXISTS (
                        SELECT 1 FROM empresa_secuencial s
                         WHERE s.id_punto_emision = p.id
                           AND s.eliminado = false
                           AND s.tipo_documento = :tipo_reembolso
                   )
                 ORDER BY CASE WHEN LOWER(COALESCE(e.estado, '')) = 'activo' THEN 0 ELSE 1 END,
                          CASE WHEN LOWER(COALESCE(p.estado, '')) = 'activo' THEN 0 ELSE 1 END,
                          LPAD(REGEXP_REPLACE(e.codigo, '[^0-9]', '', 'g'), 3, '0') ASC,
                          LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0') ASC,
                          p.id ASC
                 LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_empresa'     => $idEmpresa,
            ':tipo_reembolso' => 'Facturas de reembolso',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id_establecimiento' => (int) $row['id_establecimiento'],
            'establecimiento'    => (string) $row['establecimiento'],
            'id_punto_emision'   => (int) $row['id_punto_emision'],
            'punto_emision'      => (string) $row['punto_emision'],
        ];
    }

    /**
     * Obtiene la configuración del secuencial para un punto de emisión y tipo de documento.
     * Retorna el secuencial_inicial configurado.
     */
    public function getConfigSecuencial(int $idPuntoEmision, string $tipoDocumento): array
    {
        $sql = "SELECT id, COALESCE(secuencial_inicial, 1) AS secuencial_inicial
                FROM empresa_secuencial
                WHERE id_punto_emision = :id_punto
                  AND tipo_documento = :tipo
                  AND eliminado = false
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_punto' => $idPuntoEmision,
            ':tipo'     => $tipoDocumento,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'id'                 => null,
                'secuencial_inicial' => 1,
            ];
        }

        return $row;
    }

    /**
     * Obtiene el tipo_ambiente ('1' Pruebas, '2' Producción) basado en el punto de emisión.
     */
    private function getTipoAmbiente(int $idPuntoEmision): string
    {
        $sql = "SELECT CAST(e.tipo_ambiente AS VARCHAR(1)) 
                FROM empresa_punto_emision pe
                JOIN empresa_establecimiento ee ON pe.id_establecimiento = ee.id
                JOIN empresas e ON ee.id_empresa = e.id
                WHERE pe.id = :id_punto";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_punto' => $idPuntoEmision]);
        return $stmt->fetchColumn() ?: '1';
    }

    /**
     * Obtiene TODOS los secuenciales ya utilizados para un punto de emisión y tipo de documento.
     * Solo consulta la tabla correspondiente al tipo de documento.
     * Retorna un array de enteros con los secuenciales usados, ordenados ASC.
     */
    public function getSecuencialesUsados(int $idPuntoEmision, string $tipoDocumento): array
    {
        $map = self::DOCUMENT_MAP[$tipoDocumento] ?? null;

        if (!$map) {
            return [];
        }

        // Verificar que la tabla exista antes de consultar
        if (!$this->tableExists($map['tabla'])) {
            return [];
        }

        $tabla    = $map['tabla'];
        $colSec   = $map['col_sec'];
        $colPunto = $map['col_punto'];
        $tipoAmbiente = $this->getTipoAmbiente($idPuntoEmision);

        $sql = "SELECT CAST({$colSec} AS BIGINT) AS sec_num
                FROM {$tabla}
                WHERE {$colPunto} = :id_punto 
                  AND tipo_ambiente = :tipo_ambiente
                  AND eliminado = false
                ORDER BY sec_num ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_punto' => $idPuntoEmision,
            ':tipo_ambiente' => $tipoAmbiente
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Obtiene el número máximo de secuencial utilizado para un punto de emisión y tipo.
     */
    public function getMaxSecuencialUsado(int $idPuntoEmision, string $tipoDocumento): int
    {
        $map = self::DOCUMENT_MAP[$tipoDocumento] ?? null;

        if (!$map || !$this->tableExists($map['tabla'])) {
            return 0;
        }

        $tabla    = $map['tabla'];
        $colSec   = $map['col_sec'];
        $colPunto = $map['col_punto'];
        $tipoAmbiente = $this->getTipoAmbiente($idPuntoEmision);

        $sql = "SELECT COALESCE(MAX(CAST({$colSec} AS BIGINT)), 0) AS max_sec
                FROM {$tabla}
                WHERE {$colPunto} = :id_punto 
                  AND tipo_ambiente = :tipo_ambiente
                  AND eliminado = false";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_punto' => $idPuntoEmision,
            ':tipo_ambiente' => $tipoAmbiente
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Verifica si un secuencial específico ya está en uso.
     */
    public function secuencialEnUso(int $idPuntoEmision, string $tipoDocumento, int $secuencial): bool
    {
        $map = self::DOCUMENT_MAP[$tipoDocumento] ?? null;

        if (!$map || !$this->tableExists($map['tabla'])) {
            return false;
        }

        $tabla    = $map['tabla'];
        $colSec   = $map['col_sec'];
        $colPunto = $map['col_punto'];
        $tipoAmbiente = $this->getTipoAmbiente($idPuntoEmision);

        $secStr = str_pad((string) $secuencial, 9, '0', STR_PAD_LEFT);

        $sql = "SELECT COUNT(*) 
                FROM {$tabla}
                WHERE {$colPunto} = :id_punto 
                  AND tipo_ambiente = :tipo_ambiente
                  AND ({$colSec} = :sec_num OR {$colSec} = :sec_str)
                  AND eliminado = false";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_punto' => $idPuntoEmision,
            ':tipo_ambiente' => $tipoAmbiente,
            ':sec_num'  => (string) $secuencial,
            ':sec_str'  => $secStr,
        ]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Obtiene todos los secuenciales configurados para un punto de emisión.
     */
    public function getAllConfigByPunto(int $idPuntoEmision): array
    {
        $sql = "SELECT id, tipo_documento, COALESCE(secuencial_inicial, 1) AS secuencial_inicial
                FROM empresa_secuencial 
                WHERE id_punto_emision = :id_punto 
                  AND eliminado = false
                ORDER BY tipo_documento ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_punto' => $idPuntoEmision]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si una tabla existe en la base de datos.
     */
    private function tableExists(string $tableName): bool
    {
        static $cache = [];

        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        $sql = "SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                      AND table_name = :table_name
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':table_name' => $tableName]);

        $cache[$tableName] = (bool) $stmt->fetchColumn();

        return $cache[$tableName];
    }

    /**
     * Retorna la lista de tipos de documentos soportados (los que tienen tabla mapeada).
     */
    public function getTiposDocumentoSoportados(): array
    {
        return array_keys(self::DOCUMENT_MAP);
    }

    /**
     * Verifica si un tipo de documento tiene tabla mapeada.
     */
    public function tipoDocumentoSoportado(string $tipoDocumento): bool
    {
        return isset(self::DOCUMENT_MAP[$tipoDocumento]);
    }
}
