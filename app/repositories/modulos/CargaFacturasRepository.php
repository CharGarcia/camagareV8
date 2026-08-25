<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos para la carga masiva de facturas de venta vía Excel.
 *
 * Precarga en mapas los catálogos que el validador necesita (clientes,
 * productos, tarifas, formas de pago, puntos de emisión, bodegas y vendedores)
 * para poder validar miles de filas sin lanzar una consulta por fila.
 *
 * Aquí NO se escribe ninguna factura: eso lo hace FacturaVentaService a través
 * de CargaFacturasAplicacionService, para conservar reglas, transacciones,
 * inventario y auditoría del módulo de Facturas de Venta.
 */
class CargaFacturasRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    /**
     * Añade un `AND campo IN (...)` con parámetros nombrados, o cadena vacía si
     * la lista viene vacía (se traen todos).
     *
     * @param array $valores  Se limpian y deduplican.
     * @param array $params   Se le agregan los placeholders (por referencia).
     * @param string $prefijo Prefijo del placeholder, único por consulta.
     */
    private function filtroEnLista(string $campo, array $valores, array &$params, string $prefijo): string
    {
        $valores = array_values(array_unique(array_filter(
            array_map(static fn($v) => trim((string) $v), $valores),
            static fn($v) => $v !== ''
        )));

        if (!$valores) {
            return '';
        }

        $placeholders = [];
        foreach ($valores as $i => $valor) {
            $ph = ':' . $prefijo . $i;
            $placeholders[] = $ph;
            $params[$ph] = $valor;
        }

        return ' AND ' . $campo . ' IN (' . implode(', ', $placeholders) . ')';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mapas de catálogo para la validación
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Clientes de la empresa indexados por identificación (sin espacios).
     * Incluye el tipo de identificación para poder avisar si el Excel trae un
     * número que ya existe registrado con otro tipo.
     */
    public function getMapaClientes(int $idEmpresa, array $identificaciones = []): array
    {
        // Acotar a las identificaciones que realmente aparecen en el archivo: una
        // empresa con decenas de miles de clientes no puede traerlos todos por red
        // para validar tres facturas. Sin lista, se traen todos (compatibilidad).
        $sql = "SELECT c.id, c.nombre, c.tipo_id, c.identificacion, c.email,
                       c.id_forma_pago_sri, c.plazo,
                       COALESCE(icv.nombre, '') AS nombre_tipo_id
                FROM clientes c
                LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false";

        $params = [':id_empresa' => $idEmpresa];
        $sql   .= $this->filtroEnLista('c.identificacion', $identificaciones, $params, 'ident');

        $st = $this->db->prepare($sql);
        $st->execute($params);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $clave = trim((string) $row['identificacion']);
            if ($clave === '') {
                continue;
            }
            $mapa[$clave] = [
                'id'                  => (int) $row['id'],
                // También en el valor: PHP convierte a int la clave de un array
                // cuando es una cadena numérica, y varias validaciones necesitan
                // la identificación como string.
                'identificacion'      => $clave,
                'nombre'              => $row['nombre'],
                'tipo_id'             => trim((string) $row['tipo_id']),
                'email'               => $row['email'],
                // Forma de pago SRI preferida del cliente (id de formas_pago_sri).
                'id_forma_pago_sri'   => !empty($row['id_forma_pago_sri']) ? (int) $row['id_forma_pago_sri'] : null,
                'plazo'               => (int) ($row['plazo'] ?? 0),
                'es_consumidor_final' => (stripos((string) $row['nombre_tipo_id'], 'consumidor') !== false)
                                         || $clave === '9999999999999',
            ];
        }
        return $mapa;
    }

    /**
     * Identificaciones de clientes ELIMINADOS lógicamente, como conjunto.
     *
     * La tabla tiene UNIQUE (id_empresa, identificacion) sin considerar
     * `eliminado`: si alguien borró un cliente, su identificación sigue ocupada y
     * no se puede volver a crear. Sin esta comprobación la carga fallaría recién
     * al aplicar, con un error de llave duplicada imposible de entender.
     *
     * @return array<string,true>
     */
    public function getIdentificacionesEliminadas(int $idEmpresa, array $identificaciones = []): array
    {
        $sql = "SELECT identificacion FROM clientes
                WHERE id_empresa = :id_empresa AND eliminado = true";

        $params = [':id_empresa' => $idEmpresa];
        $sql   .= $this->filtroEnLista('identificacion', $identificaciones, $params, 'del');

        $st = $this->db->prepare($sql);
        $st->execute($params);

        $set = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ident) {
            $clave = trim((string) $ident);
            if ($clave !== '') {
                $set[$clave] = true;
            }
        }
        return $set;
    }

    /**
     * Productos y servicios de la empresa, indexados por código en minúsculas
     * (el código es case-insensitive, igual que el índice único).
     */
    public function getMapaProductos(int $idEmpresa, array $codigos = []): array
    {
        // Igual que con los clientes: solo los códigos que trae el archivo. El
        // cruce es case-insensitive, como el índice único del código.
        $sql = "SELECT p.id, p.codigo, p.nombre, p.tipo_produccion, p.inventariable,
                       p.precio_base, p.status, p.tarifa_iva, ti.codigo AS codigo_iva
                FROM productos p
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id_empresa = :id_empresa AND p.eliminado = false";

        $params = [':id_empresa' => $idEmpresa];
        $sql   .= $this->filtroEnLista('LOWER(TRIM(p.codigo))', array_map(
            static fn($c) => mb_strtolower(trim((string) $c)),
            $codigos
        ), $params, 'cod');

        $st = $this->db->prepare($sql);
        $st->execute($params);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[mb_strtolower(trim((string) $row['codigo']))] = [
                'id'              => (int) $row['id'],
                'codigo'          => $row['codigo'],
                'nombre'          => $row['nombre'],
                'tipo_produccion' => trim((string) $row['tipo_produccion']),
                'inventariable'   => (bool) $row['inventariable'],
                'precio_base'     => (float) $row['precio_base'],
                'id_tarifa_iva'   => (int) $row['tarifa_iva'],
                'codigo_iva'      => $row['codigo_iva'],
                'status'          => (bool) $row['status'],
            ];
        }
        return $mapa;
    }

    /**
     * Tarifas de IVA indexadas por su código (catálogo global).
     *
     * Por defecto incluye también las INACTIVAS: hay documentos históricos con
     * tarifas ya derogadas (12%, 14%) que deben poder cargarse. El generador de
     * la plantilla pide solo las activas para el desplegable.
     */
    public function getMapaTarifasIva(bool $soloActivas = false): array
    {
        $sql = "SELECT id, codigo, tarifa, porcentaje_iva, status FROM tarifa_iva";
        if ($soloActivas) {
            $sql .= " WHERE status = 1";
        }
        $sql .= " ORDER BY porcentaje_iva ASC";
        $st = $this->db->query($sql);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[trim((string) $row['codigo'])] = [
                'id'             => (int) $row['id'],
                'codigo'         => trim((string) $row['codigo']),
                'tarifa'         => $row['tarifa'],
                'porcentaje_iva' => (float) $row['porcentaje_iva'],
                'activa'         => ((int) $row['status'] === 1),
            ];
        }
        return $mapa;
    }

    /** Formas de pago del SRI activas, indexadas por código. */
    public function getMapaFormasPago(): array
    {
        $st = $this->db->query("SELECT codigo, nombre FROM formas_pago_sri WHERE status = 1 ORDER BY codigo");

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[trim((string) $row['codigo'])] = trim((string) $row['nombre']);
        }
        return $mapa;
    }

    /**
     * Formas de pago del SRI indexadas por su ID, no por su código.
     *
     * La forma de pago preferida del cliente (`clientes.id_forma_pago_sri`) y la
     * del establecimiento (`empresa_establecimiento.id_forma_pago_sri_def`) se
     * guardan como ID; el documento electrónico necesita el CÓDIGO. Este mapa
     * hace la traducción.
     *
     * Incluye también las inactivas: si un cliente quedó apuntando a una forma
     * que luego se desactivó, hay que poder resolverla y avisar, no perderla en
     * silencio.
     *
     * @return array<int,array{codigo:string,nombre:string,activa:bool}>
     */
    public function getMapaFormasPagoPorId(): array
    {
        $st = $this->db->query("SELECT id, codigo, nombre, status FROM formas_pago_sri ORDER BY id");

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[(int) $row['id']] = [
                'codigo' => trim((string) $row['codigo']),
                'nombre' => trim((string) $row['nombre']),
                'activa' => ((int) $row['status'] === 1),
            ];
        }
        return $mapa;
    }

    /**
     * Puntos de emisión activos de la empresa indexados por "EST-PTO"
     * (p. ej. "001-001"). Solo se incluyen los que tienen configurado el
     * secuencial de facturas de venta: sin esa configuración el sistema no
     * puede asignar número.
     */
    public function getMapaPuntosEmision(int $idEmpresa): array
    {
        $sql = "SELECT p.id AS id_punto, p.codigo_punto,
                       e.id AS id_establecimiento, e.codigo AS codigo_establecimiento,
                       e.direccion,
                       s.id AS id_config_secuencial
                FROM empresa_punto_emision p
                JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                LEFT JOIN empresa_secuencial s
                       ON s.id_punto_emision = p.id
                      AND s.eliminado = false
                      AND s.tipo_documento = 'Facturas de venta'
                WHERE e.id_empresa = :id_empresa
                  AND p.eliminado = false AND e.eliminado = false
                  AND LOWER(p.estado) = 'activo'
                ORDER BY e.codigo, p.codigo_punto";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $codEst = str_pad(trim((string) $row['codigo_establecimiento']), 3, '0', STR_PAD_LEFT);
            $codPto = str_pad(trim((string) $row['codigo_punto']), 3, '0', STR_PAD_LEFT);
            $mapa[$codEst . '-' . $codPto] = [
                'id_punto'             => (int) $row['id_punto'],
                'id_establecimiento'   => (int) $row['id_establecimiento'],
                'establecimiento'      => $codEst,
                'punto_emision'        => $codPto,
                'direccion'            => $row['direccion'] ?? '',
                'tiene_secuencial'     => !empty($row['id_config_secuencial']),
            ];
        }
        return $mapa;
    }

    /** Bodegas de la empresa indexadas por nombre en minúsculas. */
    public function getMapaBodegas(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre FROM bodegas
                WHERE id_empresa = :id_empresa AND eliminado = false
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[mb_strtolower(trim((string) $row['nombre']))] = [
                'id'     => (int) $row['id'],
                'nombre' => $row['nombre'],
            ];
        }
        return $mapa;
    }

    /** Vendedores activos de la empresa indexados por nombre en minúsculas. */
    public function getMapaVendedores(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre FROM vendedores
                WHERE id_empresa = :id_empresa AND eliminado = false AND status = 1
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[mb_strtolower(trim((string) $row['nombre']))] = [
                'id'     => (int) $row['id'],
                'nombre' => $row['nombre'],
            ];
        }
        return $mapa;
    }

    /**
     * Stock disponible por producto en una bodega, para el pre-chequeo agregado
     * del archivo completo. Devuelve [id_producto => stock].
     *
     * Es una foto informativa tomada durante la validación: la verdad la sigue
     * imponiendo FacturaVentaService al aplicar, con su propio candado.
     */
    public function getStockPorProductos(array $idProductos, int $idBodega, int $idEmpresa): array
    {
        $idProductos = array_values(array_unique(array_map('intval', $idProductos)));
        if (!$idProductos || $idBodega <= 0) {
            return [];
        }

        $ph  = implode(',', array_fill(0, count($idProductos), '?'));
        $sql = "SELECT id_producto, COALESCE(SUM(cantidad), 0) AS stock
                FROM inventario_kardex
                WHERE id_bodega = ? AND id_empresa = ? AND eliminado = false
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                  AND id_producto IN ($ph)
                GROUP BY id_producto";
        $st = $this->db->prepare($sql);
        $st->execute(array_merge([$idBodega, $idEmpresa, $idEmpresa], $idProductos));

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[(int) $row['id_producto']] = (float) $row['stock'];
        }
        return $mapa;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Control de cargas repetidas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ¿Este archivo ya se aplicó antes en esta empresa?
     *
     * El rastro se busca en `log_sistema`, donde la aplicación deja una entrada
     * `CARGA_MASIVA_FACTURAS` con el hash del archivo. No hace falta una tabla de
     * control aparte: la auditoría ya guarda lo necesario.
     *
     * @return array|null ['created_at' => ..., 'creadas' => int, 'numeros' => string]
     */
    public function getCargaPreviaPorHash(string $hash, int $idEmpresa): ?array
    {
        if ($hash === '') {
            return null;
        }

        $sql = "SELECT created_at,
                       COALESCE(datos_nuevos->>'creadas', '0') AS creadas,
                       COALESCE(datos_nuevos->>'numeros', '')  AS numeros
                FROM log_sistema
                WHERE accion = 'CARGA_MASIVA_FACTURAS'
                  AND id_empresa = :id_empresa
                  AND datos_nuevos->>'hash_archivo' = :hash
                  AND COALESCE((datos_nuevos->>'creadas')::int, 0) > 0
                ORDER BY created_at DESC
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':hash' => $hash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Facturas ya emitidas que podrían ser las mismas que trae el archivo.
     *
     * Se acota por cliente y fecha —lo único indexable— y la comparación fina
     * (total y número de líneas) la hace el validador en memoria. Detecta el
     * archivo que se reeditó y volvió a subir, caso que el hash no ve.
     *
     * @param int[]    $idsCliente
     * @param string[] $fechas Fechas 'Y-m-d'
     */
    public function getFacturasExistentesPorClienteFecha(array $idsCliente, array $fechas, int $idEmpresa): array
    {
        $idsCliente = array_values(array_unique(array_filter(array_map('intval', $idsCliente))));
        $fechas     = array_values(array_unique(array_filter($fechas)));

        if (!$idsCliente || !$fechas) {
            return [];
        }

        $phCli = implode(',', array_fill(0, count($idsCliente), '?'));
        $phFec = implode(',', array_fill(0, count($fechas), '?'));

        $sql = "SELECT c.id, c.id_cliente, c.fecha_emision, c.importe_total, c.estado,
                       c.establecimiento, c.punto_emision, c.secuencial,
                       (SELECT COUNT(*) FROM ventas_detalle d WHERE d.id_venta = c.id) AS n_lineas
                FROM ventas_cabecera c
                WHERE c.id_empresa = ?
                  AND c.eliminado = false
                  AND c.id_cliente IN ($phCli)
                  AND c.fecha_emision IN ($phFec)";
        $st = $this->db->prepare($sql);
        $st->execute(array_merge([$idEmpresa], $idsCliente, $fechas));

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Datos de la empresa: rotulan la plantilla y además permiten validar al
     * EMISOR contra la ficha técnica antes de generar ningún comprobante.
     */
    public function getEmpresa(int $idEmpresa): ?array
    {
        $sql = "SELECT id, COALESCE(NULLIF(nombre_comercial, ''), nombre) AS nombre, ruc,
                       nombre AS razon_social, direccion
                FROM empresas WHERE id = :id LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Exportación para la plantilla (una consulta por hoja, sin N+1)
    // ─────────────────────────────────────────────────────────────────────────

}
