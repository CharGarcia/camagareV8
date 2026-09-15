<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Ordenamiento de listados: resuelve QUÉ pidió ordenar el usuario y arma la
 * cláusula ORDER BY ya validada.
 *
 * Existe porque el ORDER BY es el único trozo de SQL que no se puede parametrizar
 * con PDO: el nombre de la columna y la dirección se interpolan en la cadena. Cada
 * repositorio resolvía eso por su cuenta (unos con `in_array()` contra una
 * whitelist, otros con `match($ordenCol)`, otros con un mapa `$cols[$ordenCol]`),
 * siempre para un solo criterio. Este helper unifica los tres patrones y, de paso,
 * admite VARIOS criterios (ordenar por ciudad y, dentro de cada ciudad, por nombre).
 *
 * La regla de seguridad es la misma de siempre: lo que sale hacia el SQL viene
 * SIEMPRE del `$mapa` que escribe el programador. La entrada del usuario solo se
 * usa como clave de búsqueda en ese mapa; una clave que no esté se ignora en
 * silencio, igual que hace `FiltrosBusqueda` con el buscador.
 *
 * Formato de transporte (`?orden=`): `columna:DIR,columna:DIR` — cabe en una URL
 * sin codificar JSON, así los enlaces de PDF/Excel conservan el orden de pantalla.
 *
 * Uso típico en el controlador:
 *   $orden = OrdenListado::leer($prefsVista, 'nombre');
 *   $result = $this->service->getListado(..., OrdenListado::primeraCol($orden, 'nombre'),
 *                                        OrdenListado::primeraDir($orden), $idUsuarioFiltro, $orden);
 *
 * Y en el repositorio:
 *   $orderBy = OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'c.nombre', 'c.id DESC');
 *   $sql = "SELECT ... $where $orderBy $limitOffset";
 */
class OrdenListado
{
    /**
     * Tope de criterios simultáneos. Cada columna extra es un nivel más que
     * Postgres tiene que ordenar y un índice menos que puede aprovechar: con
     * LIMIT/OFFSET sobre tablas grandes eso se paga caro, y el usuario no
     * distingue el tercer desempate del cuarto.
     */
    public const MAX_CRITERIOS = 3;

    /** Claves donde se persiste el orden dentro de `__vista__` (ver PreferenciasHelper). */
    public const CLAVE_MULTI = '__ordenMulti__';
    public const CLAVE_COL   = '__ordenCol__';
    public const CLAVE_DIR   = '__ordenDir__';

    /**
     * Resuelve los criterios de orden vigentes, en este orden de prioridad:
     *   1. `orden=col:DIR,col:DIR` de la petición (lo que manda el motor JS).
     *   2. `sort=` + `dir=` de la petición (formato antiguo, sigue funcionando).
     *   3. `__ordenMulti__` guardado en las preferencias del usuario.
     *   4. `__ordenCol__` + `__ordenDir__` guardados (módulos que aún no usan multi).
     *   5. El defecto del módulo.
     *
     * Devuelve SIEMPRE al menos un criterio, en la forma [['col' => …, 'dir' => 'ASC'], …].
     *
     * @param array      $prefsVista Preferencias `__vista__` del usuario para el módulo.
     * @param string     $colDefecto Columna por defecto del módulo (clave lógica, no SQL).
     * @param array|null $request    Petición a leer; por omisión $_GET + $_POST.
     */
    public static function leer(
        array $prefsVista,
        string $colDefecto,
        string $dirDefecto = 'ASC',
        int $max = self::MAX_CRITERIOS,
        ?array $request = null
    ): array {
        $req = $request ?? ($_GET + $_POST);

        // 1. Formato compacto multi-columna.
        $cadena = self::texto($req['orden'] ?? null);
        if ($cadena !== '') {
            $criterios = self::parsear($cadena, $max);
            if ($criterios !== []) {
                return $criterios;
            }
        }

        // 2. sort/dir sueltos (enlaces y módulos que todavía no mandan `orden`).
        $col = self::texto($req['sort'] ?? null);
        if ($col !== '') {
            $dir = self::texto($req['dir'] ?? null);
            $criterios = self::normalizar([['col' => $col, 'dir' => $dir !== '' ? $dir : $dirDefecto]], $max);
            if ($criterios !== []) {
                return $criterios;
            }
        }

        // 3. Preferencia multi-columna del usuario.
        if (!empty($prefsVista[self::CLAVE_MULTI]) && is_array($prefsVista[self::CLAVE_MULTI])) {
            $criterios = self::normalizar($prefsVista[self::CLAVE_MULTI], $max);
            if ($criterios !== []) {
                return $criterios;
            }
        }

        // 4. Preferencia de una sola columna (la que guardan los ~80 módulos actuales).
        $col = self::texto($prefsVista[self::CLAVE_COL] ?? null);
        if ($col !== '') {
            $dir = self::texto($prefsVista[self::CLAVE_DIR] ?? null);
            $criterios = self::normalizar([['col' => $col, 'dir' => $dir !== '' ? $dir : $dirDefecto]], $max);
            if ($criterios !== []) {
                return $criterios;
            }
        }

        // 5. Defecto del módulo.
        return [['col' => $colDefecto, 'dir' => self::normalizarDir($dirDefecto)]];
    }

    /**
     * Texto de un valor que viene de fuera. Un parámetro puede llegar como array
     * (`?orden[]=x`) o como null; en ese caso no hay nada que leer y se devuelve ''
     * en vez de dejar que PHP intente convertir un array a cadena.
     */
    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /**
     * Convierte `"ciudad:ASC,nombre:DESC"` en la lista de criterios.
     * Una entrada sin dirección se asume ASC.
     */
    public static function parsear(string $cadena, int $max = self::MAX_CRITERIOS): array
    {
        $bruto = [];
        foreach (explode(',', $cadena) as $trozo) {
            $trozo = trim($trozo);
            if ($trozo === '') {
                continue;
            }
            $partes = explode(':', $trozo, 2);
            $bruto[] = ['col' => $partes[0], 'dir' => $partes[1] ?? 'ASC'];
        }
        return self::normalizar($bruto, $max);
    }

    /**
     * Limpia una lista de criterios: descarta columnas vacías o con caracteres que
     * no puede tener un identificador, quita las repetidas (la primera manda) y
     * recorta al tope.
     *
     * No comprueba que la columna exista: eso lo hace `clausula()` contra el mapa
     * del módulo, que es el único que sabe qué columnas son válidas.
     */
    public static function normalizar(array $criterios, int $max = self::MAX_CRITERIOS): array
    {
        $out = [];
        $vistas = [];
        foreach ($criterios as $c) {
            if (!is_array($c)) {
                continue;
            }
            $col = self::texto($c['col'] ?? null);
            // La clave solo sirve para buscar en el mapa y nunca llega al SQL, pero
            // no tiene sentido arrastrar basura: un identificador es esto y nada más.
            if ($col === '' || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $col) || isset($vistas[$col])) {
                continue;
            }
            $vistas[$col] = true;
            $out[] = ['col' => $col, 'dir' => self::normalizarDir(self::texto($c['dir'] ?? null))];
            if (count($out) >= max(1, $max)) {
                break;
            }
        }
        return $out;
    }

    /**
     * Arma la cláusula `ORDER BY` completa (incluida la palabra ORDER BY).
     *
     * @param array  $criterios   Salida de leer()/parsear(). Vacío = solo el defecto.
     * @param array  $mapa        clave lógica => expresión SQL ('nombre_ciudad' => 'ciu.nombre').
     * @param string $exprDefecto Expresión SQL a usar si ningún criterio es válido.
     * @param string $desempate   Expresión final estable ('c.id DESC'), para que dos
     *                            filas con el mismo valor no bailen entre páginas.
     */
    public static function clausula(array $criterios, array $mapa, string $exprDefecto, string $desempate = ''): string
    {
        $partes = [];
        foreach ($criterios as $c) {
            $col = (string) ($c['col'] ?? '');
            if ($col === '' || !isset($mapa[$col])) {
                continue; // columna desconocida: se ignora en silencio
            }
            $partes[] = $mapa[$col] . ' ' . self::normalizarDir((string) ($c['dir'] ?? 'ASC'));
        }

        if ($partes === []) {
            // Ninguna columna válida: se cae al defecto, pero conservando la dirección
            // que pidió el usuario (es lo que venían haciendo los repositorios).
            $partes[] = $exprDefecto . ' ' . self::normalizarDir((string) ($criterios[0]['dir'] ?? 'ASC'));
        }

        $desempate = trim($desempate);
        if ($desempate !== '' && !self::yaOrdenaPor($partes, $desempate)) {
            $partes[] = $desempate;
        }

        return 'ORDER BY ' . implode(', ', $partes);
    }

    /** ¿Alguna parte del ORDER BY usa ya la misma expresión que el desempate? */
    private static function yaOrdenaPor(array $partes, string $desempate): bool
    {
        $expr = strtolower((string) preg_replace('/\s+(ASC|DESC)$/i', '', $desempate));
        foreach ($partes as $p) {
            if (strtolower((string) preg_replace('/\s+(ASC|DESC)$/i', '', $p)) === $expr) {
                return true;
            }
        }
        return false;
    }

    /** Solo ASC o DESC llegan al SQL; cualquier otra cosa se vuelve ASC. */
    public static function normalizarDir(string $dir): string
    {
        return strtoupper(trim($dir)) === 'DESC' ? 'DESC' : 'ASC';
    }

    /** Primer criterio, para los Services/exportadores que siguen esperando una columna. */
    public static function primeraCol(array $criterios, string $colDefecto): string
    {
        $col = (string) ($criterios[0]['col'] ?? '');
        return $col !== '' ? $col : $colDefecto;
    }

    /** Dirección del primer criterio. */
    public static function primeraDir(array $criterios, string $dirDefecto = 'ASC'): string
    {
        return self::normalizarDir((string) ($criterios[0]['dir'] ?? $dirDefecto));
    }

    /** Serializa a `col:DIR,col:DIR` para ponerlo en una URL (export PDF/Excel, AJAX). */
    public static function aCadena(array $criterios): string
    {
        $partes = [];
        foreach ($criterios as $c) {
            $col = (string) ($c['col'] ?? '');
            if ($col === '') {
                continue;
            }
            $partes[] = $col . ':' . self::normalizarDir((string) ($c['dir'] ?? 'ASC'));
        }
        return implode(',', $partes);
    }

    /** Estado inicial para el motor JS (`CMG_initSort`), listo para incrustar en la vista. */
    public static function aJson(array $criterios): string
    {
        return json_encode(array_values($criterios), JSON_UNESCAPED_UNICODE) ?: '[]';
    }
}
