<?php
declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de las cuotas extraordinarias a plazos (Administración del condominio → pestaña
 * Cuotas extraordinarias). Mensajes con «|#id» para que la vista enfoque el control (prefijo cex_).
 * Diseño: docs/diseno/condominios-cuotas-extraordinarias.md.
 */
class CondominioCuotaExtraRules
{
    public const CALCULOS  = ['iguales' => 'Partes iguales', 'porcentaje' => 'Por % de alícuota'];
    public const DONDE     = ['junto' => 'Con la alícuota (mismo documento)', 'aparte' => 'En un documento aparte'];
    public const PAGADORES = ['propietario' => 'Propietario', 'arrendatario' => 'Arrendatario'];
    public const PLAZO_MAX = 120;

    private static function numero($v): float
    {
        return round((float) str_replace(',', '.', (string) ($v ?? 0)), 2);
    }

    /** AAAA-MM (input type=month) o AAAAMM → AAAAMM; '' si no es válido. */
    public static function periodo(string $v): string
    {
        $v = trim($v);
        if (preg_match('/^(\d{4})-?(\d{2})$/', $v, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return $m[1] . $m[2];
        }
        return '';
    }

    /** Suma $n meses a un período AAAAMM. */
    public static function sumarMeses(string $periodo, int $n): string
    {
        $d = \DateTime::createFromFormat('Ymd', $periodo . '01');
        $d->modify(($n >= 0 ? '+' : '') . $n . ' month');
        return $d->format('Ym');
    }

    public function normalizarAporte(array $d): array
    {
        $plazos = array_map('intval', is_array($d['plazos_permitidos'] ?? null)
            ? $d['plazos_permitidos']
            : preg_split('/[\s,;]+/', (string) ($d['plazos_permitidos'] ?? ''), -1, PREG_SPLIT_NO_EMPTY));
        $plazos = array_values(array_unique(array_filter($plazos, fn($p) => $p >= 1 && $p <= self::PLAZO_MAX)));
        sort($plazos);

        $n = [
            'nombre'            => mb_substr(trim((string) ($d['nombre'] ?? '')), 0, 150),
            'descripcion'       => trim((string) ($d['descripcion'] ?? '')),
            'id_producto'       => (int) ($d['id_producto'] ?? 0),
            'acta'              => mb_substr(trim((string) ($d['acta'] ?? '')), 0, 100),
            'fecha_acta'        => trim((string) ($d['fecha_acta'] ?? '')),
            'meta_total'        => self::numero($d['meta_total'] ?? 0),
            'calculo'           => array_key_exists($d['calculo'] ?? '', self::CALCULOS) ? $d['calculo'] : 'iguales',
            'plazos_permitidos' => $plazos,
            'plazo_defecto'     => (int) ($d['plazo_defecto'] ?? 0),
            'primer_periodo'    => self::periodo((string) ($d['primer_periodo'] ?? '')),
            'donde_cobro'       => ($d['donde_cobro'] ?? 'junto') === 'aparte' ? 'aparte' : 'junto',
            'pagador_defecto'   => ($d['pagador_defecto'] ?? 'propietario') === 'arrendatario' ? 'arrendatario' : 'propietario',
            'tipo_comprobante'  => ($d['tipo_comprobante'] ?? 'recibo') === 'factura' ? 'factura' : 'recibo',
            'id_punto_emision'  => (int) ($d['id_punto_emision'] ?? 0),
            'id_forma_anticipo' => (int) ($d['id_forma_anticipo'] ?? 0),
        ];

        if ($n['nombre'] === '') {
            throw new \InvalidArgumentException('Escriba el nombre de la cuota extraordinaria (p. ej. «Pintura del edificio»).|#cex_nombre');
        }
        if ($n['id_producto'] <= 0) {
            throw new \InvalidArgumentException('Elija el concepto (servicio de Productos) con el que se cobra.|#cex_producto_txt');
        }
        if ($n['meta_total'] <= 0 || $n['meta_total'] > 99999999) {
            throw new \InvalidArgumentException('Indique el monto total aprobado (mayor que cero).|#cex_meta_total');
        }
        if ($n['fecha_acta'] !== '') {
            $f = \DateTime::createFromFormat('Y-m-d', $n['fecha_acta']);
            if (!$f || $f->format('Y-m-d') !== $n['fecha_acta']) {
                throw new \InvalidArgumentException('La fecha del acta no es válida.|#cex_fecha_acta');
            }
        }
        if ($n['plazo_defecto'] < 1 || $n['plazo_defecto'] > self::PLAZO_MAX) {
            throw new \InvalidArgumentException('Indique las cuotas por defecto (1 a ' . self::PLAZO_MAX . ').|#cex_plazo_defecto');
        }
        // Las cuotas por defecto se escriben libremente: si no están entre las permitidas, se agregan.
        if (!in_array($n['plazo_defecto'], $n['plazos_permitidos'], true)) {
            $n['plazos_permitidos'][] = $n['plazo_defecto'];
            sort($n['plazos_permitidos']);
        }
        if ($n['primer_periodo'] === '') {
            throw new \InvalidArgumentException('Indique el mes de la primera cuota.|#cex_primer_periodo');
        }
        if ($n['id_punto_emision'] <= 0) {
            throw new \InvalidArgumentException('Elija la serie de los documentos propios (abono inicial, cuotas aparte y liquidaciones).|#cex_id_punto_emision');
        }
        return $n;
    }

    /**
     * Fila del plan que manda la vista: {id_unidad, incluir, monto, manual, anticipo, plazo, pagador, primer_periodo}.
     * Valida lo que no depende de la base; el servicio revalida montos y anticipos.
     */
    public function normalizarFila(array $f, array $aporte): array
    {
        $n = [
            'id_unidad'      => (int) ($f['id_unidad'] ?? 0),
            'incluir'        => in_array((string) ($f['incluir'] ?? '1'), ['1', 'true', 'on'], true),
            'monto'          => isset($f['monto']) && $f['monto'] !== '' ? self::numero($f['monto']) : null,
            'manual'         => in_array((string) ($f['manual'] ?? ''), ['1', 'true'], true),
            'anticipo'       => self::numero($f['anticipo'] ?? 0),
            'plazo'          => (int) ($f['plazo'] ?? $aporte['plazo_defecto']),
            'pagador'        => ($f['pagador'] ?? $aporte['pagador_defecto']) === 'arrendatario' ? 'arrendatario' : 'propietario',
            'primer_periodo' => self::periodo((string) ($f['primer_periodo'] ?? '')) ?: (string) $aporte['primer_periodo'],
        ];
        $plazos = array_map('intval', is_array($aporte['plazos_permitidos']) ? $aporte['plazos_permitidos'] : (json_decode((string) $aporte['plazos_permitidos'], true) ?: []));
        if ($n['incluir'] && !in_array($n['plazo'], $plazos, true)) {
            throw new \InvalidArgumentException("El plazo de {$n['plazo']} cuotas no está entre los permitidos (" . implode(', ', $plazos) . ').');
        }
        if ($n['anticipo'] < 0) {
            throw new \InvalidArgumentException('El anticipo a aplicar no puede ser negativo.');
        }
        return $n;
    }

    /** Reparte $total en $n cuotas: todas iguales y la última absorbe los centavos. */
    public static function cuotas(float $total, int $n): array
    {
        $total = round($total, 2);
        if ($n <= 0 || $total <= 0) {
            return ['cuota' => 0.0, 'ultima' => 0.0];
        }
        // round(…, 6) antes de floor: 441,60 / 24 da 1839,9999… en coma flotante y no debe bajar a 18,39.
        $cuota = floor(round($total * 100 / $n, 6)) / 100;
        $ultima = round($total - $cuota * ($n - 1), 2);
        return ['cuota' => round($cuota, 2), 'ultima' => $ultima];
    }
}
