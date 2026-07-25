<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\Helpers\DocumentoOrigenAsiento;
use PDO;

/**
 * Lectura (solo lectura) del documento operativo que originó un asiento, para mostrarlo desde
 * los reportes contables sin entrar al módulo que lo emitió.
 *
 * Las consultas se arman con el mapa de App\Helpers\DocumentoOrigenAsiento: las expresiones y
 * nombres de tabla son constantes del código —nunca entrada del usuario—, y el id del documento
 * y la empresa van siempre por parámetros preparados.
 */
class DocumentoOrigenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Cabecera del documento: número, fecha, estado, tercero, totales y observaciones.
     * Devuelve null si el módulo no está mapeado o el documento no es de esta empresa.
     */
    public function getCabecera(string $modulo, int $idDocumento, int $idEmpresa): ?array
    {
        $doc = DocumentoOrigenAsiento::paraModulo($modulo);
        if ($doc === null || $idDocumento <= 0) {
            return null;
        }

        $tipo = $doc['tipo'];
        $entidad = $doc['entidad'];

        $cols = [
            "({$doc['numero']})::varchar AS numero",
            "t.{$doc['fecha']}::date AS fecha",
            $doc['estado'] ? "t.{$doc['estado']}::varchar AS estado" : "NULL::varchar AS estado",
            $doc['observaciones'] ? "t.{$doc['observaciones']}::text AS observaciones" : "NULL::text AS observaciones",
            "({$tipo})::varchar AS tipo_entidad",
            "COALESCE(cli.nombre, prov.razon_social, emp.nombres_apellidos) AS tercero",
            "COALESCE(cli.identificacion, prov.identificacion, emp.identificacion) AS tercero_identificacion",
        ];

        // Totales: cada uno con alias posicional y su etiqueta se repone en el service.
        $i = 0;
        foreach ($doc['totales'] as $columna) {
            $cols[] = "t.{$columna} AS total_{$i}";
            $i++;
        }

        $sql = "SELECT " . implode(",\n                       ", $cols) . "
                FROM {$doc['tabla']} t
                LEFT JOIN clientes cli ON ($tipo) = 'cliente' AND ($entidad) = cli.id
                LEFT JOIN proveedores prov ON ($tipo) = 'proveedor' AND ($entidad) = prov.id
                LEFT JOIN empleados emp ON ($tipo) = 'empleado' AND ($entidad) = emp.id
                WHERE t.id = :id AND t.id_empresa = :id_empresa
                LIMIT 1";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idDocumento, ':id_empresa' => $idEmpresa]);

        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Líneas del documento, en el orden en que se registraron. */
    public function getLineas(string $modulo, int $idDocumento): array
    {
        $doc = DocumentoOrigenAsiento::paraModulo($modulo);
        if ($doc === null || $idDocumento <= 0) {
            return [];
        }

        $det = $doc['detalle'];
        $cols = [];
        $i = 0;
        foreach ($det['columnas'] as $expresion) {
            $cols[] = "($expresion)::text AS c_{$i}";
            $i++;
        }

        // Solo algunas tablas de detalle llevan eliminación lógica.
        $filtroEliminado = $this->tieneColumna($det['tabla'], 'eliminado') ? ' AND d.eliminado = false' : '';

        $sql = "SELECT " . implode(",\n                       ", $cols) . "
                FROM {$det['tabla']} d
                " . ($det['join'] ?? '') . "
                WHERE d.{$det['fk']} = :id $filtroEliminado
                ORDER BY d.id ASC";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idDocumento]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** ¿La tabla tiene esta columna? (cacheado por request). */
    private function tieneColumna(string $tabla, string $columna): bool
    {
        static $cache = [];
        $clave = $tabla . '.' . $columna;

        if (!array_key_exists($clave, $cache)) {
            $st = $this->db->prepare("SELECT 1 FROM information_schema.columns
                                      WHERE table_schema = 'public' AND table_name = ? AND column_name = ?");
            $st->execute([$tabla, $columna]);
            $cache[$clave] = (bool) $st->fetchColumn();
        }

        return $cache[$clave];
    }
}
