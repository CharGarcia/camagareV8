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
        'Nota de débito'                       => ['tabla' => 'notas_debito_cabecera',   'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Retenciones de compras'               => ['tabla' => 'retencion_compra_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Guía de remisión'                     => ['tabla' => 'guias_remision_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Liquidación de compras o servicios'   => ['tabla' => 'liquidaciones_cabecera',  'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Proformas'                            => ['tabla' => 'proformas_cabecera',      'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ingresos'                             => ['tabla' => 'ingresos_cabecera',       'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Egresos'                              => ['tabla' => 'egresos_cabecera',        'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Pedidos'                              => ['tabla' => 'pedidos_cabecera',        'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Órdenes de compra'                    => ['tabla' => 'ordenes_compra',           'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Consignaciones ventas'                => ['tabla' => 'consignaciones_ventas',   'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Retornos consignaciones ventas'       => ['tabla' => 'retornos_cv',             'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Facturacion consignaciones ventas'    => ['tabla' => 'consignaciones_facturas', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ordenes car-wash'                     => ['tabla' => 'carwash_ordenes',         'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();
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
