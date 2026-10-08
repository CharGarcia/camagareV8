<?php

declare(strict_types=1);

namespace App\models;

/**
 * Catálogo fijo del módulo Roles de Pago: tipos de corrida y estados.
 */
final class CatalogoRol
{
    public const TIPOS = [
        'MENSUAL'  => 'Rol Mensual',
        'QUINCENA' => 'Quincena',
        'SEMANAL'  => 'Semanal',
    ];

    public const ESTADOS = [
        'borrador'      => 'Borrador',
        'generado'      => 'Generado',
        'pagado'        => 'Pagado',
        'contabilizado' => 'Contabilizado',
        'anulado'       => 'Anulado',
    ];

    /** tipo_rol -> valor de novedades.aplica_en */
    public const APLICA_EN = [
        'MENSUAL'  => 'rol',
        'QUINCENA' => 'quincena',
        'SEMANAL'  => 'semanal',
    ];

    public static function tipos(): array
    {
        return self::TIPOS;
    }

    public static function estados(): array
    {
        return self::ESTADOS;
    }

    public static function esTipoValido(string $t): bool
    {
        return array_key_exists($t, self::TIPOS);
    }

    public static function nombreTipo(string $t): string
    {
        return self::TIPOS[$t] ?? $t;
    }

    /**
     * Días que cubre una quincena o semana, por convención (el rol no guarda fechas reales):
     * quincena 1 = 1 al 15, quincena 2 = 16 al fin de mes; semana n = de 7 en 7
     * ((n-1)*7+1 al min(n*7, fin de mes)). Devuelve [desde, hasta] o null si no aplica
     * (rol mensual, número 0, o una semana 5 que cae fuera del mes, p. ej. febrero).
     */
    public static function rangoDias(string $tipo, int $anio, int $mes, int $num): ?array
    {
        if ($num <= 0 || $mes < 1 || $mes > 12 || $anio < 1) return null;
        $ultimo = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
        if ($tipo === 'QUINCENA') {
            return $num === 1 ? [1, 15] : [16, $ultimo];
        }
        if ($tipo === 'SEMANAL') {
            $desde = ($num - 1) * 7 + 1;
            if ($desde > $ultimo) return null;
            return [$desde, min($num * 7, $ultimo)];
        }
        return null;
    }

    /**
     * Período del rol tal como se muestra al usuario, en todos los sitios (listado, modal,
     * PDF, Excel, texto de búsqueda): "Mayo 2026" para el mensual; "Quincena 1 · 1 al 15 de
     * Mayo 2026" y "Semana 3 · 15 al 21 de Mayo 2026" para los demás. Antes salía "Mayo 2026 #1",
     * que no decía si el 1 era quincena o semana. Misma lógica en SQL: sqlNombrePeriodo().
     */
    public static function nombrePeriodo(string $tipo, int $anio, int $mes, int $num): string
    {
        $mesNombre = CatalogoNovedades::MESES[$mes] ?? (string) $mes;
        $mesAnio   = trim($mesNombre . ' ' . $anio);
        if ($num <= 0 || $tipo === 'MENSUAL') return $mesAnio;
        $nombre = $tipo === 'SEMANAL' ? 'Semana' : 'Quincena';
        $rango  = self::rangoDias($tipo, $anio, $mes, $num);
        if (!$rango) return "{$nombre} {$num} · {$mesAnio}";
        if ($rango[0] === $rango[1]) return "{$nombre} {$num} · {$rango[0]} de {$mesAnio}"; // semana 5 de un febrero bisiesto
        return "{$nombre} {$num} · {$rango[0]} al {$rango[1]} de {$mesAnio}";
    }

    /**
     * Mismo texto que nombrePeriodo(), como expresión SQL (PostgreSQL) sobre el alias de
     * rol_cabecera: lo usa el buscador del listado para que lo que se ve sea lo que se busca.
     */
    public static function sqlNombrePeriodo(string $a): string
    {
        $casos = '';
        foreach (CatalogoNovedades::MESES as $n => $nombre) {
            $casos .= ' WHEN ' . (int) $n . " THEN '" . str_replace("'", "''", $nombre) . "'";
        }
        $mesAnio = "CONCAT(CASE {$a}.periodo_mes{$casos} END, ' ', {$a}.periodo_anio)";
        $ultimo  = "EXTRACT(DAY FROM (make_date({$a}.periodo_anio, {$a}.periodo_mes, 1) + INTERVAL '1 month' - INTERVAL '1 day'))::int";
        $n       = "{$a}.numero_periodo";
        return "CASE
                  WHEN {$n} <= 0 OR {$a}.tipo_rol = 'MENSUAL' THEN {$mesAnio}
                  WHEN {$a}.tipo_rol = 'QUINCENA' THEN CONCAT('Quincena ', {$n}, ' · ',
                       CASE WHEN {$n} = 1 THEN '1 al 15' ELSE CONCAT('16 al ', {$ultimo}) END, ' de ', {$mesAnio})
                  WHEN {$a}.tipo_rol = 'SEMANAL' AND ({$n} - 1) * 7 + 1 > {$ultimo} THEN CONCAT('Semana ', {$n}, ' · ', {$mesAnio})
                  WHEN {$a}.tipo_rol = 'SEMANAL' AND ({$n} - 1) * 7 + 1 = {$ultimo} THEN CONCAT('Semana ', {$n}, ' · ', {$ultimo}, ' de ', {$mesAnio})
                  WHEN {$a}.tipo_rol = 'SEMANAL' THEN CONCAT('Semana ', {$n}, ' · ', ({$n} - 1) * 7 + 1, ' al ', LEAST({$n} * 7, {$ultimo}), ' de ', {$mesAnio})
                  ELSE CONCAT({$mesAnio}, ' #', {$n})
                END";
    }

    /**
     * Color Bootstrap e ícono (Bootstrap Icons) de cada tipo de rol, para que el listado y
     * el modal los distingan de un vistazo: mensual celeste, quincena gris oscuro, semanal ámbar.
     */
    public const ESTILO_TIPO = [
        'MENSUAL'  => ['color' => 'info',    'icono' => 'bi-calendar-month'],
        'QUINCENA' => ['color' => 'dark',    'icono' => 'bi-calendar2-week'],
        'SEMANAL'  => ['color' => 'warning', 'icono' => 'bi-calendar-week'],
    ];

    /** Badge HTML (ícono + nombre) del tipo de rol, ya escapado. */
    public static function badgeTipo(string $t): string
    {
        $e = self::ESTILO_TIPO[$t] ?? ['color' => 'secondary', 'icono' => 'bi-calendar'];
        return '<span class="badge bg-' . $e['color'] . ' bg-opacity-10 text-' . $e['color']
            . ' border border-' . $e['color'] . ' border-opacity-25 fw-medium">'
            . '<i class="bi ' . $e['icono'] . ' me-1"></i>'
            . htmlspecialchars(self::nombreTipo($t), ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public static function nombreEstado(string $e): string
    {
        return self::ESTADOS[$e] ?? $e;
    }

    public static function aplicaEn(string $tipo): string
    {
        return self::APLICA_EN[$tipo] ?? 'rol';
    }
}
