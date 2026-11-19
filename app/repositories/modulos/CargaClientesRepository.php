<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos (solo lectura) para la carga masiva de clientes vía Excel.
 *
 * Precarga en pocas consultas los clientes y los catálogos para validar miles de
 * filas sin una consulta por fila. La escritura la hace ClienteService, para
 * conservar sus reglas, su transacción y su auditoría.
 */
class CargaClientesRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('clientes');
    }

    /** ¿La BD ya tiene la columna clientes.observaciones (2026-11-17)? */
    public function tieneObservaciones(): bool
    {
        return $this->columnaExiste('clientes', 'observaciones');
    }

    /**
     * Clientes vigentes de la empresa con los nombres de sus catálogos, en orden
     * alfabético. Con $idUsuarioFiltro solo los que creó ese usuario (§6).
     *
     * dias_visita / semanas_visita salen como texto "1,3,5" (array_to_string),
     * así no hace falta decodificar el literal de array de Postgres en PHP.
     */
    public function getClientes(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $obs = $this->tieneObservaciones() ? 'c.observaciones' : 'NULL::text';

        $sql = "SELECT c.id, c.identificacion, c.tipo_id, c.nombre, c.email, c.telefono,
                       c.direccion, c.plazo, c.provincia, c.ciudad, c.status,
                       c.id_vendedor, c.id_forma_pago_sri, c.created_by,
                       {$obs} AS observaciones,
                       array_to_string(c.dias_visita, ',')    AS dias_visita,
                       c.frecuencia_visita,
                       array_to_string(c.semanas_visita, ',') AS semanas_visita,
                       to_char(c.hora_visita_desde, 'HH24:MI') AS hora_visita_desde,
                       to_char(c.hora_visita_hasta, 'HH24:MI') AS hora_visita_hasta,
                       c.orden_visita, c.observacion_visita,
                       c.id_forma_cobro_predeterminada, c.tipo_operacion_bancaria_predeterminada,
                       c.monto_minimo_auto_cobro, c.monto_maximo_auto_cobro,
                       c.id_ingreso_concepto_predeterminado, c.latitud, c.longitud
                FROM clientes c
                " . $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro) . "
                ORDER BY c.nombre, c.id";

        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Identificaciones de clientes ELIMINADOS de la empresa. La restricción única
     * (id_empresa, identificacion) no considera `eliminado`, así que esas
     * identificaciones no se pueden volver a crear.
     *
     * @return array<string,true>
     */
    public function getIdentificacionesEliminadas(int $idEmpresa): array
    {
        $sql = "SELECT identificacion FROM clientes
                WHERE id_empresa = :id_empresa AND eliminado = true";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $set = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ident) {
            $clave = trim((string) $ident);
            if ($clave !== '') {
                $set[$clave] = true;
            }
        }
        return $set;
    }

    /** Provincias (catálogo global), en orden alfabético. */
    public function getProvincias(): array
    {
        $st = $this->db->query("SELECT codigo, nombre FROM provincia ORDER BY nombre");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ciudades (catálogo global) con su provincia. */
    public function getCiudades(): array
    {
        $st = $this->db->query(
            "SELECT c.codigo, c.cod_prov, c.nombre, p.nombre AS provincia
             FROM ciudad c
             LEFT JOIN provincia p ON p.codigo = c.cod_prov
             ORDER BY p.nombre, c.nombre"
        );
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vendedores no eliminados de la empresa (activos e inactivos). */
    public function getVendedores(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, identificacion, status
                FROM vendedores
                WHERE id_empresa = :id_empresa AND eliminado = false
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Formas de pago del SRI (catálogo global). La clave primaria se llama `id`
     * o `id_forma_pago` según la base (ver App\models\FormaPagoSri).
     */
    public function getFormasPagoSri(): array
    {
        $pk = $this->columnaExiste('formas_pago_sri', 'id_forma_pago') ? 'id_forma_pago' : 'id';
        $st = $this->db->query(
            "SELECT {$pk} AS id, TRIM(codigo) AS codigo, nombre, status
             FROM formas_pago_sri ORDER BY codigo"
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
