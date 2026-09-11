<?php
declare(strict_types=1);

namespace App\Services;

use App\core\Database;
use App\Helpers\AuditoriaCampos;
use PDO;

class LogSistemaService
{
    private PDO $db;

    /** Caché de resoluciones id → nombre dentro de la misma petición. */
    private array $cacheValores = [];

    /** Datos de control del registro: el diff los omite y el detalle completo los deja al final. */
    private const CAMPOS_CONTROL = ['id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'eliminado', 'deleted_at', 'deleted_by', 'id_empresa', 'id_usuario'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Registra una acción en la bitácora de auditoría.
     */
    public function registrar(
        int $idUsuario,
        ?int $idEmpresa,
        string $accion,
        string $tabla,
        ?int $idRegistro = null,
        ?array $antes = null,
        ?array $despues = null
    ): void {
        $sql = "INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro, datos_anteriores, datos_nuevos, ip_usuario, user_agent) 
                VALUES (:id_u, :id_e, :acc, :tab, :id_r, :ant, :des, :ip, :ua)";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_u' => $idUsuario,
            ':id_e' => $idEmpresa,
            ':acc'  => $accion,
            ':tab'  => $tabla,
            ':id_r' => $idRegistro,
            ':ant'  => $antes ? json_encode($antes) : null,
            ':des'  => $despues ? json_encode($despues) : null,
            ':ip'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':ua'   => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);

        // Invalidar la caché de contadores del navbar si la tabla es relevante.
        // Envuelto en try/catch: la invalidación de caché JAMÁS debe afectar la auditoría.
        try {
            \App\Services\ContadoresNavbarService::invalidarPorTabla($tabla, $idEmpresa);
        } catch (\Throwable $e) {
            // Silencioso a propósito.
        }
    }

    /**
     * Obtiene el historial de cambios de un registro específico.
     */
    public function getHistorial(string $tabla, int $idRegistro, ?int $idEmpresa = null): array
    {
        $where = "WHERE tabla_afectada = :tab AND id_registro = :id_r";
        $params = [':tab' => $tabla, ':id_r' => $idRegistro];

        if ($idEmpresa !== null) {
            $where .= " AND id_empresa = :id_e";
            $params[':id_e'] = $idEmpresa;
        }

        $sql = "SELECT l.*, u.nombre AS usuario_nombre
                FROM log_sistema l
                LEFT JOIN usuarios u ON u.id = l.id_usuario
                $where
                ORDER BY l.created_at DESC";
        
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $logs = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as &$log) {
            $log['created_at'] = date('d-m-Y H:i:s', strtotime($log['created_at']));
            $log['detalles'] = $this->generarDetalleCambios(
                $log['datos_anteriores'] ? json_decode($log['datos_anteriores'], true) : null,
                $log['datos_nuevos'] ? json_decode($log['datos_nuevos'], true) : null
            );
        }

        return $logs;
    }

    /**
     * Envoltura pública de generarDetalleCambios() para reutilizar el diff legible
     * desde el módulo de consulta de auditoría (LogSistemaConsultaService).
     */
    public function formatearCambios(?array $antes, ?array $despues): array
    {
        return $this->generarDetalleCambios($antes, $despues);
    }

    /**
     * Todos los datos guardados en el evento —no solo los que cambiaron—, legibles, para el
     * detalle completo de la consulta de auditoría (config/log-sistema). A diferencia de
     * formatearCambios() no omite nada (id, fechas de registro, quién lo hizo…) y muestra las
     * listas anexas (detalles, pagos…) como tabla en vez de "3 registros".
     *
     * @return array<int, array{campo: string, antes: string|array|null, despues: string|array|null, cambio: bool}>
     *         Cada lado es null si el evento no guardó ese campo de ese lado, un texto, o una
     *         tabla ['columnas' => string[], 'filas' => string[][]] para listas y objetos.
     */
    public function formatearDatosCompletos(?array $antes, ?array $despues): array
    {
        $antes   = $antes ?? [];
        $despues = $despues ?? [];

        $claves = array_keys($despues);
        foreach (array_keys($antes) as $clave) {
            if (!array_key_exists($clave, $despues)) {
                $claves[] = $clave;
            }
        }

        $etiquetas = self::etiquetasUnicas(array_map('strval', $claves));
        $filas = [];
        $orden = [];
        foreach ($claves as $clave) {
            $key        = (string) $clave;
            $hayAntes   = array_key_exists($clave, $antes);
            $hayDespues = array_key_exists($clave, $despues);
            $campo      = $etiquetas[$key];
            $filas[] = [
                'campo'   => $campo,
                'antes'   => $hayAntes ? $this->presentarCompleto($key, $antes[$clave]) : null,
                'despues' => $hayDespues ? $this->presentarCompleto($key, $despues[$clave]) : null,
                'cambio'  => $hayAntes && $hayDespues && !AuditoriaCampos::iguales($antes[$clave], $despues[$clave]),
            ];
            $orden[] = [
                in_array($key, self::CAMPOS_CONTROL, true) ? 1 : 0,
                strtr(mb_strtolower($campo, 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']),
            ];
        }

        // Orden alfabético y los datos de control al final: el JSON guardado (JSONB) no
        // conserva el orden de las columnas, así que el orden en que llega no dice nada.
        $indices = array_keys($filas);
        usort($indices, fn($a, $b) => $orden[$a] <=> $orden[$b]);

        return array_map(fn($i) => $filas[$i], $indices);
    }

    /** Valor para el detalle completo: listas y objetos como tabla; lo demás, como en el diff. */
    private function presentarCompleto(string $key, $valor)
    {
        // El mismo dato llega como arreglo o como texto JSON según de dónde venga el log.
        if (is_string($valor) && preg_match('/^\s*[\[{]/', $valor)) {
            $decodificado = json_decode($valor, true);
            if (is_array($decodificado)) {
                $valor = $decodificado;
            }
        }

        // Los campos con traducción propia (opciones, casilleros SRI) conservan su texto.
        if (is_array($valor) && $valor !== [] && $key !== 'casilleros_sri' && AuditoriaCampos::valorLegible($key, $valor) === null) {
            $tabla = $this->tablaDeDatos($valor);
            if ($tabla !== null) {
                return $tabla;
            }
            if (array_keys($valor) === range(0, count($valor) - 1)) {
                return implode(', ', array_map(
                    fn($v) => is_array($v) ? (json_encode($v, JSON_UNESCAPED_UNICODE) ?: '') : $this->textoLegible((string) $v),
                    $valor
                ));
            }
        }

        return $this->textoLegible($this->presentarValor($key, $valor));
    }

    /**
     * Lista de registros (detalles, pagos…) → tabla con una columna por campo, sin las que
     * vienen vacías en todas las filas; objeto → tabla Campo / Valor. Null si es una lista de
     * valores simples.
     *
     * @return array{columnas: string[], filas: string[][]}|null
     */
    private function tablaDeDatos(array $valor): ?array
    {
        if (array_keys($valor) !== range(0, count($valor) - 1)) {
            $etiquetas = self::etiquetasUnicas(array_map('strval', array_keys($valor)));
            $filas = [];
            foreach ($valor as $k => $v) {
                $filas[] = [$etiquetas[(string) $k], $this->textoCelda((string) $k, $v)];
            }
            return ['columnas' => ['Campo', 'Valor'], 'filas' => $filas];
        }

        foreach ($valor as $registro) {
            if (!is_array($registro)) {
                return null;
            }
        }

        // Columnas en el orden en que aparecen; fuera las vacías en todas las filas.
        $conDato = [];
        foreach ($valor as $registro) {
            foreach ($registro as $k => $v) {
                $k = (string) $k;
                $conDato[$k] = ($conDato[$k] ?? false) || ($v !== null && $v !== '' && $v !== []);
            }
        }
        $claves = array_map('strval', array_keys(array_filter($conDato)));
        if ($claves === []) {
            return null;
        }

        $filas = [];
        foreach ($valor as $registro) {
            $fila = [];
            foreach ($claves as $k) {
                $fila[] = array_key_exists($k, $registro) ? $this->textoCelda($k, $registro[$k]) : '';
            }
            $filas[] = $fila;
        }

        return [
            'columnas' => array_values(self::etiquetasUnicas($claves)),
            'filas'    => $filas,
        ];
    }

    /**
     * Etiqueta legible por clave, sin confusiones: `establecimiento` e `id_establecimiento`
     * se ven juntas en el detalle completo y la segunda es el id del catálogo, así que se
     * muestra como "Establecimiento (ID)". Igual con cualquier otro id cuya etiqueta choque.
     *
     * @param string[] $claves
     * @return array<string, string> clave => etiqueta, en el mismo orden.
     */
    private static function etiquetasUnicas(array $claves): array
    {
        $etiquetas = [];
        foreach ($claves as $clave) {
            $etiquetas[$clave] = AuditoriaCampos::etiqueta($clave);
        }

        // Un id junto a su dato (id_punto_emision + punto_emision): la etiqueta del dato + " (ID)".
        foreach (array_keys($etiquetas) as $clave) {
            $base = preg_replace('/^id_|_id$/', '', (string) $clave);
            if ($base !== (string) $clave && isset($etiquetas[$base])) {
                $etiquetas[$clave] = $etiquetas[$base] . ' (ID)';
            }
        }

        $repetidas = array_filter(array_count_values($etiquetas), fn($n) => $n > 1);
        foreach ($etiquetas as $clave => $etiqueta) {
            if (isset($repetidas[$etiqueta]) && preg_match('/^id_|_id$/', (string) $clave)) {
                $etiquetas[$clave] = $etiqueta . ' (ID)';
            }
        }

        return $etiquetas;
    }

    /** Valor de una celda de las tablas anexas: legible, en una línea, vacío si no hay dato. */
    private function textoCelda(string $key, $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (is_array($valor)) {
            return AuditoriaCampos::valorLegible($key, $valor) ?? (json_encode($valor, JSON_UNESCAPED_UNICODE) ?: '');
        }
        return $this->textoLegible($this->presentarValor($key, $valor));
    }

    /**
     * Texto de un valor de la BD en el formato del sistema: fechas ISO (2026-07-04,
     * 2026-07-04 10:22:33.123456-05) como d-m-Y H:i:s, y decimales rellenos de ceros
     * (150.000000) sin el relleno (150.00).
     */
    private function textoLegible(string $texto): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?[\d.]*(?:Z|[+-]\d{2}(?::?\d{2})?)?)?$/', $texto, $m)) {
            $fecha = "{$m[3]}-{$m[2]}-{$m[1]}";
            if (isset($m[4]) && $m[4] !== '') {
                $fecha .= " {$m[4]}:{$m[5]}:" . (isset($m[6]) && $m[6] !== '' ? $m[6] : '00');
            }
            return $fecha;
        }
        if (preg_match('/^-?\d+\.\d{4,}$/', $texto)) {
            [$entero, $decimales] = explode('.', $texto, 2);
            return $entero . '.' . str_pad(rtrim($decimales, '0'), 2, '0');
        }
        return $texto;
    }

    /**
     * Compara dos arreglos de datos y retorna una lista de cambios legibles.
     *
     * La comparación es semántica (App\Helpers\AuditoriaCampos::iguales): un mismo
     * valor guardado con distinta forma — booleano 't' de PostgreSQL frente a true
     * de PHP, JSON con las claves en otro orden, '12.00' frente a '12' — no se
     * reporta como cambio, porque el usuario no cambió nada.
     */
    private function generarDetalleCambios(?array $antes, ?array $despues): array
    {
        if (!$antes && $despues) return [['campo' => 'Registro', 'antes' => 'No existía', 'despues' => 'Creado']];
        if (!$despues) return [['campo' => 'Registro', 'antes' => 'Existía', 'despues' => 'Eliminado']];

        $cambios = [];
        $omitir = self::CAMPOS_CONTROL;

        foreach ($despues as $key => $valNuevo) {
            if (in_array($key, $omitir)) continue;

            $valAnterior = $antes[$key] ?? null;

            if (AuditoriaCampos::iguales($valAnterior, $valNuevo)) continue;

            $antesTexto   = $this->presentarValor($key, $valAnterior);
            $despuesTexto = $this->presentarValor($key, $valNuevo);

            // Campos cuyo texto resume el contenido (listas, archivos): si el resumen
            // no cambia, decirlo, en vez de mostrar "1 registro → 1 registro".
            if ($antesTexto === $despuesTexto) {
                $despuesTexto .= ' (con cambios)';
            }

            $cambios[] = [
                'campo'   => AuditoriaCampos::etiqueta($key),
                'antes'   => $antesTexto,
                'despues' => $despuesTexto
            ];
        }

        return $cambios;
    }

    /**
     * Valor tal como debe verlo el usuario: primero la traducción del campo
     * (enumeraciones, booleanos con etiqueta propia, JSON de opciones) y, si no
     * aplica, la resolución del id contra su catálogo.
     */
    private function presentarValor(string $key, $valor): string
    {
        $legible = AuditoriaCampos::valorLegible($key, $valor);
        if ($legible !== null) {
            return $legible;
        }

        // Un guion no dice si el dato se borró o nunca existió.
        if ($valor === null || $valor === '') {
            return '(vacío)';
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        // El mismo dato llega como arreglo o como texto JSON según de dónde venga el
        // log; se normaliza para que ambas formas se lean igual.
        if (is_string($valor) && preg_match('/^\s*[\[{]/', $valor)) {
            $decodificado = json_decode($valor, true);
            if (is_array($decodificado)) {
                $valor = $decodificado;
            }
        }

        // Listas anexas (componentes, variantes…): el JSON completo no le sirve a nadie.
        if (is_array($valor) && in_array($key, ['componentes', 'variantes', 'inventarios', 'precios'], true)) {
            $n = count($valor);
            return $n === 0 ? 'Ninguno' : $n . ($n === 1 ? ' registro' : ' registros');
        }

        if (is_array($valor) && empty($valor)) {
            return '(vacío)';
        }

        return $this->resolverValor($key, $valor);
    }

    private function resolverValor(string $key, $value): string
    {
        if ($value === null || $value === '') return '-';
        if (is_array($value)) {
            if ($key === 'casilleros_sri') {
                $labels = [
                    'v_brutas' => 'Ventas Brutas',
                    'v_nc'     => 'NC Ventas',
                    'v_iva'    => 'IVA Ventas',
                    'c_brutas' => 'Compras Brutas',
                    'c_nc'     => 'NC Compras',
                    'c_iva'    => 'IVA Compras'
                ];
                $parts = [];
                foreach ($value as $k => $v) {
                    if ($v !== '') {
                        $label = $labels[$k] ?? $k;
                        $parts[] = "{$label}: {$v}";
                    }
                }
                return empty($parts) ? '-' : implode(' | ', $parts);
            }
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $map = [
            'id_forma_pago_sri' => ['table' => 'formas_pago_sri', 'field' => "codigo || ' - ' || nombre"],
            'id_vendedor'       => ['table' => 'vendedores', 'field' => 'nombre'],
            // Catálogos de producto: sin esto el historial mostraba "Medida: 12 → 13"
            'id_medida'         => ['table' => 'unidades_medida', 'field' => 'nombre'],
            'id_tipo_medida'    => ['table' => 'tipo_medida', 'field' => 'nombre'],
            'id_categoria'      => ['table' => 'categorias', 'field' => 'nombre'],
            'id_marca'          => ['table' => 'marcas', 'field' => 'nombre'],
            'tarifa_iva'        => ['table' => 'tarifa_iva', 'field' => 'tarifa'],
            'id_ice'            => ['table' => 'empresa_ice', 'field' => 'nombre_ice'],
            'id_bodega'         => ['table' => 'bodegas', 'field' => 'nombre'],
            'id_producto'       => ['table' => 'productos', 'field' => "codigo || ' - ' || nombre"],
            'id_cliente'        => ['table' => 'clientes', 'field' => 'nombre'],
            'id_proveedor'      => ['table' => 'proveedores', 'field' => 'razon_social'],
            'id_cuenta_cobrar'  => ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_cuenta_ingreso' => ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_cuenta_pagar'   => ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_cuenta_gasto'   => ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_cuenta_inventario'=> ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_banco'          => ['table' => 'bancos_ecuador', 'field' => 'nombre_banco'],
            'tipo_id'           => ['table' => 'identificadores_comprador_vendedor', 'field' => 'nombre', 'key' => 'codigo'],
            'tipo_id_proveedor' => ['table' => 'identificadores_comprador_vendedor', 'field' => 'nombre', 'key' => 'codigo'],
            'tipo_empresa'      => ['table' => 'tipo_empresa', 'field' => 'nombre'],
            'provincia'         => ['table' => 'provincias', 'field' => 'nombre', 'key' => 'codigo'],
            'ciudad'            => ['table' => 'ciudades', 'field' => 'nombre', 'key' => 'codigo'],
            'id_forma_cobro_predeterminada' => ['table' => 'formas_cobro', 'field' => 'nombre'],
            // Quién y en qué empresa: solo se ven en el detalle completo de la consulta de
            // auditoría (formatearDatosCompletos); el diff de cambios omite estas claves.
            'created_by'        => ['table' => 'usuarios', 'field' => 'nombre'],
            'updated_by'        => ['table' => 'usuarios', 'field' => 'nombre'],
            'deleted_by'        => ['table' => 'usuarios', 'field' => 'nombre'],
            'id_usuario'        => ['table' => 'usuarios', 'field' => 'nombre'],
            'id_empresa'        => ['table' => 'empresas', 'field' => 'COALESCE(nombre_comercial, nombre)'],
        ];

        if (isset($map[$key])) {
            $t = $map[$key]['table'];
            $f = $map[$key]['field'];
            $k = $map[$key]['key'] ?? 'id';

            $cacheKey = $t . '|' . $k . '|' . (string) $value;
            if (array_key_exists($cacheKey, $this->cacheValores)) {
                return $this->cacheValores[$cacheKey] ?? (string) $value;
            }

            try {
                $sql = "SELECT {$f} FROM {$t} WHERE {$k} = :val LIMIT 1";
                $st = $this->db->prepare($sql);
                $st->execute([':val' => $value]);
                $res = $st->fetchColumn();
                $this->cacheValores[$cacheKey] = ($res !== false && $res !== null) ? (string) $res : null;
                if ($this->cacheValores[$cacheKey] !== null) {
                    return $this->cacheValores[$cacheKey];
                }
            } catch (\Throwable $e) {
                $this->cacheValores[$cacheKey] = null;
            }
        }

        return (string) $value;
    }
}
