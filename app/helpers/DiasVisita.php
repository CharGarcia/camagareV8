<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Días, frecuencia y semanas de visita del vendedor a un cliente.
 *
 * Fuente de verdad única del catálogo (días, abreviaturas, frecuencias) y de su
 * formato para pantalla, PDF y Excel. Los días siguen ISO-8601 (1=Lunes ..
 * 7=Domingo), igual que `date('N')` de PHP y que `EXTRACT(ISODOW)` de Postgres,
 * así que el número guardado se compara directo contra el día de hoy sin mapeos.
 */
class DiasVisita
{
    /** Nombre completo por número ISO. */
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /** Abreviatura de una letra para las celdas del listado (X = miércoles). */
    public const DIAS_LETRA = [
        1 => 'L',
        2 => 'M',
        3 => 'X',
        4 => 'J',
        5 => 'V',
        6 => 'S',
        7 => 'D',
    ];

    /** Abreviatura de tres letras para PDF/Excel. */
    public const DIAS_CORTO = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mié',
        4 => 'Jue',
        5 => 'Vie',
        6 => 'Sáb',
        7 => 'Dom',
    ];

    public const FRECUENCIAS = [
        'SEMANAL'   => 'Semanal',
        'QUINCENAL' => 'Quincenal',
        'MENSUAL'   => 'Mensual',
    ];

    /** Semanas del mes admitidas para frecuencia quincenal/mensual. */
    public const SEMANAS = [
        1 => 'Semana 1',
        2 => 'Semana 2',
        3 => 'Semana 3',
        4 => 'Semana 4',
        5 => 'Semana 5',
    ];

    /**
     * Normaliza una lista cruda de días (POST, JSON, array de Postgres ya
     * decodificado) a enteros 1..7 únicos y ordenados. Devuelve null si no
     * queda ninguno, para guardar NULL en vez de un array vacío.
     */
    public static function normalizarDias($valor): ?array
    {
        return self::normalizarLista($valor, 1, 7);
    }

    /** Igual que normalizarDias() pero para las semanas del mes (1..5). */
    public static function normalizarSemanas($valor): ?array
    {
        return self::normalizarLista($valor, 1, 5);
    }

    /**
     * Traduce lo que el usuario escribe en el buscador a un número de día:
     * acepta el número ("3"), el nombre ("miércoles", con o sin tilde) y las
     * abreviaturas de una o tres letras ("X", "mie"). Devuelve null si no
     * corresponde a ningún día.
     */
    public static function parsearDia(string $texto): ?int
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        if (ctype_digit($texto)) {
            $n = (int) $texto;
            return ($n >= 1 && $n <= 7) ? $n : null;
        }

        $norm = self::sinAcentos($texto);

        foreach (self::DIAS as $num => $nombre) {
            if ($norm === self::sinAcentos($nombre)) {
                return $num;
            }
        }
        foreach (self::DIAS_CORTO as $num => $corto) {
            if ($norm === self::sinAcentos($corto)) {
                return $num;
            }
        }
        foreach (self::DIAS_LETRA as $num => $letra) {
            if ($norm === strtolower($letra)) {
                return $num;
            }
        }

        return null;
    }

    /**
     * Días en formato compacto para el listado: "L M · J · · ·", donde los días
     * no seleccionados quedan como punto medio para que la columna conserve
     * siempre el mismo ancho y se lea como una matriz.
     *
     * @return array<int,array{letra:string,activo:bool,titulo:string}>
     */
    public static function matrizSemana(?array $dias): array
    {
        $dias = $dias ?? [];
        $matriz = [];
        foreach (self::DIAS_LETRA as $num => $letra) {
            $matriz[$num] = [
                'letra'  => $letra,
                'activo' => in_array($num, $dias, true),
                'titulo' => self::DIAS[$num],
            ];
        }
        return $matriz;
    }

    /** Días en texto corto separados por coma: "Lun, Mié, Vie". Vacío -> '-'. */
    public static function formatearDias(?array $dias, string $vacio = '-'): string
    {
        if (empty($dias)) {
            return $vacio;
        }
        $partes = [];
        foreach ($dias as $d) {
            $d = (int) $d;
            if (isset(self::DIAS_CORTO[$d])) {
                $partes[] = self::DIAS_CORTO[$d];
            }
        }
        return $partes ? implode(', ', $partes) : $vacio;
    }

    /** Etiqueta legible de la frecuencia. */
    public static function formatearFrecuencia(?string $frecuencia, string $vacio = '-'): string
    {
        $frecuencia = strtoupper(trim((string) $frecuencia));
        return self::FRECUENCIAS[$frecuencia] ?? $vacio;
    }

    /** Semanas del mes en texto: "S1, S3". Vacío -> '-'. */
    public static function formatearSemanas(?array $semanas, string $vacio = '-'): string
    {
        if (empty($semanas)) {
            return $vacio;
        }
        $partes = [];
        foreach ($semanas as $s) {
            $s = (int) $s;
            if ($s >= 1 && $s <= 5) {
                $partes[] = 'S' . $s;
            }
        }
        return $partes ? implode(', ', $partes) : $vacio;
    }

    /**
     * Resumen de una línea de toda la pauta de visita, para tooltips y
     * exportaciones: "Semanal · Lun, Mié · 08:00-11:00".
     */
    public static function resumen(?array $dias, ?string $frecuencia, ?array $semanas, ?string $desde = null, ?string $hasta = null): string
    {
        if (empty($dias)) {
            return '';
        }

        $partes = [];

        $frec = self::formatearFrecuencia($frecuencia, '');
        if ($frec !== '') {
            $partes[] = $frec;
        }

        $partes[] = self::formatearDias($dias, '');

        // Las semanas solo aportan información cuando no se visita todas.
        if (strtoupper((string) $frecuencia) !== 'SEMANAL' && !empty($semanas)) {
            $partes[] = self::formatearSemanas($semanas, '');
        }

        $ventana = self::formatearVentana($desde, $hasta, '');
        if ($ventana !== '') {
            $partes[] = $ventana;
        }

        return implode(' · ', array_filter($partes, fn($p) => $p !== ''));
    }

    /** Ventana horaria: "08:00-11:00", "desde 08:00", "hasta 11:00". */
    public static function formatearVentana(?string $desde, ?string $hasta, string $vacio = '-'): string
    {
        $desde = self::recortarHora($desde);
        $hasta = self::recortarHora($hasta);

        if ($desde !== '' && $hasta !== '') {
            return $desde . '-' . $hasta;
        }
        if ($desde !== '') {
            return 'desde ' . $desde;
        }
        if ($hasta !== '') {
            return 'hasta ' . $hasta;
        }
        return $vacio;
    }

    /**
     * Celda del listado: la semana como una matriz fija de 7 letras, con los días
     * de visita resaltados y el resto en gris. El ancho no cambia entre filas, así
     * que la columna se lee de un vistazo en vertical. El title lleva el resumen
     * completo (frecuencia, semanas y horario), que no cabe en la celda.
     *
     * Devuelve HTML ya escapado; se usa tanto en el render inicial de la vista
     * como en el HTML que arma searchAjax(), para no duplicar el marcado.
     */
    public static function renderCelda(?array $dias, ?string $frecuencia = null, ?array $semanas = null, ?string $desde = null, ?string $hasta = null): string
    {
        if (empty($dias)) {
            return '<span class="text-muted">-</span>';
        }

        $titulo = self::resumen($dias, $frecuencia, $semanas, $desde, $hasta);

        $html = '<span class="d-inline-flex gap-1" title="' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '">';
        foreach (self::matrizSemana($dias) as $dia) {
            $html .= $dia['activo']
                ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-1" style="min-width:18px;">' . $dia['letra'] . '</span>'
                : '<span class="text-muted opacity-50 px-1" style="min-width:18px;display:inline-block;text-align:center;">·</span>';
        }
        $html .= '</span>';

        // La frecuencia distinta de semanal cambia el significado de la fila:
        // sin esto, "Lun" quincenal y "Lun" semanal se verían idénticos.
        $frecNorm = strtoupper(trim((string) $frecuencia));
        if ($frecNorm !== '' && $frecNorm !== 'SEMANAL') {
            $html .= ' <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-1" style="font-size:.65rem;">'
                . htmlspecialchars(self::formatearFrecuencia($frecNorm, ''), ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        return $html;
    }

    /** Deja una hora de Postgres ("08:00:00") en HH:MM. */
    public static function recortarHora(?string $hora): string
    {
        $hora = trim((string) $hora);
        if ($hora === '') {
            return '';
        }
        return preg_match('/^(\d{2}:\d{2})/', $hora, $m) ? $m[1] : $hora;
    }

    // ─── Internos ───────────────────────────────────────────────────────────

    /** @return int[]|null */
    private static function normalizarLista($valor, int $min, int $max): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (!is_array($valor)) {
            // Acepta "1,3,5" por si llega de un input de texto o de una URL.
            $valor = explode(',', (string) $valor);
        }

        $limpios = [];
        foreach ($valor as $v) {
            if (!is_scalar($v)) {
                continue;
            }
            $v = trim((string) $v);
            if ($v === '' || !ctype_digit($v)) {
                continue;
            }
            $n = (int) $v;
            if ($n >= $min && $n <= $max) {
                $limpios[$n] = $n;
            }
        }

        if (!$limpios) {
            return null;
        }

        ksort($limpios);
        return array_values($limpios);
    }

    /**
     * Minúsculas sin acentos. Se traducen primero los acentos (mayúsculas y
     * minúsculas) y después se baja a minúsculas con strtolower, que ya solo ve
     * ASCII: así el helper no depende de mbstring, que no está garantizado en
     * todos los SAPI (el CLI del entorno de desarrollo no lo carga).
     */
    private static function sinAcentos(string $texto): string
    {
        $texto = strtr(trim($texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ]);
        return strtolower($texto);
    }
}
