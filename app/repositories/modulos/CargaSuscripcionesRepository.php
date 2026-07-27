<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos para la carga masiva de suscripciones vía Excel.
 *
 * Precarga en mapas los catálogos (clientes, productos, periodicidades, IVA) para
 * validar muchas filas sin una consulta por fila. La escritura la hace
 * SuscripcionesService (para conservar sus reglas, transacciones y auditoría);
 * aquí solo viven lecturas.
 */
class CargaSuscripcionesRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('suscripciones');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mapas para validación
    // ─────────────────────────────────────────────────────────────────────────

    /** Clientes de la empresa indexados por identificación (RUC/cédula). */
    public function getMapaClientes(int $idEmpresa): array
    {
        $sql = "SELECT id, identificacion, nombre, email
                FROM clientes
                WHERE id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $clave = trim((string) $row['identificacion']);
            if ($clave === '') {
                continue;
            }
            $mapa[$clave] = [
                'id'     => (int) $row['id'],
                'nombre' => $row['nombre'],
                'email'  => $row['email'] ?? '',
            ];
        }
        return $mapa;
    }

    /**
     * Productos de venta de la empresa, indexados por código en minúsculas,
     * con su precio base y su tarifa de IVA (para usarlos como valores por defecto).
     */
    public function getMapaProductos(int $idEmpresa): array
    {
        $sql = "SELECT p.id, p.codigo, p.nombre, p.precio_base,
                       p.tarifa_iva AS id_tarifa_iva,
                       ti.codigo AS codigo_iva, ti.porcentaje_iva
                FROM productos p
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id_empresa = :id_empresa AND p.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[mb_strtolower(trim($row['codigo']))] = [
                'id'             => (int) $row['id'],
                'codigo'         => $row['codigo'],
                'nombre'         => $row['nombre'],
                'precio_base'    => (float) $row['precio_base'],
                'id_tarifa_iva'  => $row['id_tarifa_iva'] !== null ? (int) $row['id_tarifa_iva'] : null,
                'codigo_iva'     => $row['codigo_iva'] ?? '',
                'porcentaje_iva' => $row['porcentaje_iva'] !== null ? (float) $row['porcentaje_iva'] : 0.0,
            ];
        }
        return $mapa;
    }

    /**
     * Periodicidades (catálogo global) indexadas por su código Y por su nombre,
     * ambos en minúsculas, para aceptar cualquiera de los dos en el Excel.
     */
    public function getMapaPeriodicidades(): array
    {
        $st = $this->db->query(
            "SELECT id, codigo, nombre, meses FROM suscripcion_periodicidades WHERE estado = true"
        );

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $info = [
                'id'     => (int) $row['id'],
                'codigo' => $row['codigo'],
                'nombre' => $row['nombre'],
                'meses'  => (int) $row['meses'],
            ];
            $mapa[mb_strtolower(trim((string) $row['codigo']))] = $info;
            $mapa[mb_strtolower(trim((string) $row['nombre']))] = $info;
        }
        return $mapa;
    }

    /** Tarifas de IVA indexadas por su código (catálogo global). */
    public function getMapaTarifasIva(bool $soloActivas = false): array
    {
        $sql = "SELECT id, codigo, tarifa, porcentaje_iva, status FROM tarifa_iva";
        if ($soloActivas) {
            $sql .= " WHERE status = 1";
        }
        $sql .= " ORDER BY codigo";
        $st = $this->db->query($sql);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[trim((string) $row['codigo'])] = [
                'id'             => (int) $row['id'],
                'tarifa'         => $row['tarifa'],
                'porcentaje_iva' => (int) $row['porcentaje_iva'],
                'activa'         => ((int) $row['status'] === 1),
            ];
        }
        return $mapa;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Datos para poblar las hojas de referencia de la plantilla
    // ─────────────────────────────────────────────────────────────────────────

    /** Clientes de la empresa (identificación + nombre) para la hoja Ref_Clientes. */
    public function getClientesParaPlantilla(int $idEmpresa): array
    {
        $sql = "SELECT identificacion, nombre
                FROM clientes
                WHERE id_empresa = :id_empresa AND eliminado = false
                  AND identificacion IS NOT NULL AND TRIM(identificacion) <> ''
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Productos de la empresa (código + nombre + precio + IVA) para la hoja Ref_Productos. */
    public function getProductosParaPlantilla(int $idEmpresa): array
    {
        $sql = "SELECT p.codigo, p.nombre, p.precio_base, ti.codigo AS codigo_iva
                FROM productos p
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id_empresa = :id_empresa AND p.eliminado = false
                ORDER BY p.codigo";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Periodicidades activas para la hoja Ref_Periodicidades. */
    public function getPeriodicidadesParaPlantilla(): array
    {
        $st = $this->db->query(
            "SELECT codigo, nombre, descripcion
             FROM suscripcion_periodicidades
             WHERE estado = true
             ORDER BY orden, nombre"
        );
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Datos de la empresa para rotular la plantilla. */
    public function getEmpresa(int $idEmpresa): ?array
    {
        $sql = "SELECT id, COALESCE(NULLIF(nombre_comercial, ''), nombre) AS nombre, ruc
                FROM empresas WHERE id = :id LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
