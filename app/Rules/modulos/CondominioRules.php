<?php
declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio de Condominios (fase 1: configuración, inmuebles, multas).
 * Los mensajes llevan `|#id` del control cuando aplica (el JS lo enfoca).
 */
class CondominioRules
{
    public const TIPOS_UNIDAD  = ['departamento', 'local', 'oficina', 'parqueadero', 'bodega', 'casa', 'otro'];
    public const TIPOS_LABEL   = ['departamento' => 'Departamento', 'local' => 'Local', 'oficina' => 'Oficina', 'parqueadero' => 'Parqueadero',
                                  'bodega' => 'Bodega', 'casa' => 'Casa', 'otro' => 'Otro'];
    public const METODOS       = ['porcentaje', 'm2', 'manual'];
    public const METODOS_LABEL = ['porcentaje' => 'Por %', 'm2' => 'Por m²', 'manual' => 'Manual'];
    public const COMPROBANTES  = ['recibo', 'factura'];

    private static function bool(mixed $v): bool
    {
        return in_array(is_string($v) ? strtolower(trim($v)) : $v, [true, 1, '1', 'true', 't', 'on', 'si', 'sí'], true);
    }

    private static function num(mixed $v, float $def = 0.0): float
    {
        $s = str_replace([',', '$', '%', ' '], '', (string) ($v ?? ''));
        return $s === '' || !is_numeric($s) ? $def : (float) $s;
    }

    private static function texto(mixed $v, int $max): ?string
    {
        $t = trim((string) ($v ?? ''));
        return $t === '' ? null : mb_substr($t, 0, $max);
    }

    // ── Configuración ────────────────────────────────────────────────────────

    /** Normaliza y valida la configuración del condominio. */
    public function validarConfig(array $d): array
    {
        $c = [];
        $c['nombre_condominio']    = self::texto($d['nombre_condominio'] ?? null, 200);
        $c['direccion']            = self::texto($d['direccion'] ?? null, 300);
        $c['administrador_nombre'] = self::texto($d['administrador_nombre'] ?? null, 150);
        if ($c['administrador_nombre'] === null) {
            throw new \InvalidArgumentException('El nombre del administrador es obligatorio (firma la liquidación).|#cfg_administrador_nombre');
        }
        $c['administrador_cedula'] = self::texto($d['administrador_cedula'] ?? null, 20);
        $c['administrador_cargo']  = self::texto($d['administrador_cargo'] ?? null, 100) ?? 'Administrador/a';
        $c['presidente_nombre']    = self::texto($d['presidente_nombre'] ?? null, 150);
        $c['presidente_cedula']    = self::texto($d['presidente_cedula'] ?? null, 20);

        // La emisión (comprobante, serie, día de cobro) la define cada suscripción en Suscripciones.
        $c['dias_gracia'] = (int) ($d['dias_gracia'] ?? 0);
        if ($c['dias_gracia'] < 0 || $c['dias_gracia'] > 60) {
            throw new InvalidArgumentException('Los días de gracia deben estar entre 0 y 60.|#cfg_dias_gracia');
        }

        // El producto de la alícuota lo elige cada suscripción (Suscripciones); aquí solo los
        // conceptos que genera el módulo (fondo, intereses), y el del fondo es opcional.
        $c['id_producto_fondo']   = (int) ($d['id_producto_fondo'] ?? 0) ?: null;
        $c['id_producto_interes'] = (int) ($d['id_producto_interes'] ?? 0) ?: null;

        $c['metodo_alicuota'] = (string) ($d['metodo_alicuota'] ?? 'porcentaje');
        if (!in_array($c['metodo_alicuota'], self::METODOS, true)) {
            throw new \InvalidArgumentException('El método de alícuota no es válido.|#cfg_metodo_alicuota');
        }
        $c['reparto_manuales'] = in_array($d['reparto_manuales'] ?? '', ['repartir_resto', 'aparte'], true) ? $d['reparto_manuales'] : 'repartir_resto';

        $c['fondo_reserva_tipo'] = (string) ($d['fondo_reserva_tipo'] ?? 'no');
        if (!in_array($c['fondo_reserva_tipo'], ['no', 'porcentaje', 'fijo'], true)) {
            throw new \InvalidArgumentException('El tipo de fondo de reserva no es válido.|#cfg_fondo_reserva_tipo');
        }
        $c['fondo_reserva_valor'] = round(self::num($d['fondo_reserva_valor'] ?? 0), 2);
        if ($c['fondo_reserva_tipo'] !== 'no') {
            if ($c['fondo_reserva_valor'] <= 0) {
                throw new \InvalidArgumentException('Indique el ' . ($c['fondo_reserva_tipo'] === 'porcentaje' ? '% sobre la alícuota' : 'monto fijo') . ' del fondo de reserva.|#cfg_fondo_reserva_valor');
            }
            if ($c['fondo_reserva_tipo'] === 'porcentaje' && $c['fondo_reserva_valor'] > 100) {
                throw new \InvalidArgumentException('El % del fondo de reserva no puede pasar de 100.|#cfg_fondo_reserva_valor');
            }
        }

        $c['cobra_intereses'] = self::bool($d['cobra_intereses'] ?? false);
        $c['interes_tipo']    = ($d['interes_tipo'] ?? 'legal') === 'fijo' ? 'fijo' : 'legal';
        $c['interes_tasa_mensual'] = round(self::num($d['interes_tasa_mensual'] ?? 0), 4);
        $c['interes_destino'] = ($d['interes_destino'] ?? '') === 'recibo_aparte' ? 'recibo_aparte' : 'siguiente_recibo';
        if ($c['cobra_intereses']) {
            if ($c['id_producto_interes'] === null) {
                throw new \InvalidArgumentException('Elija el producto con el que se facturan los intereses de mora.|#cfg_prod_interes_txt');
            }
            if ($c['interes_tipo'] === 'fijo' && ($c['interes_tasa_mensual'] <= 0 || $c['interes_tasa_mensual'] > 20)) {
                throw new \InvalidArgumentException('La tasa mensual fija debe estar entre 0,01 % y 20 %.|#cfg_interes_tasa_mensual');
            }
        }

        $c['cobra_multas'] = self::bool($d['cobra_multas'] ?? false);

        $c['pronto_pago_activo'] = self::bool($d['pronto_pago_activo'] ?? false);
        $c['pronto_pago_pct']    = round(self::num($d['pronto_pago_pct'] ?? 0), 2);
        $c['pronto_pago_dia']    = (int) ($d['pronto_pago_dia'] ?? 5);
        if ($c['pronto_pago_activo']) {
            if ($c['pronto_pago_pct'] <= 0 || $c['pronto_pago_pct'] > 100) {
                throw new \InvalidArgumentException('El % de pronto pago debe estar entre 0,01 y 100.|#cfg_pronto_pago_pct');
            }
            if ($c['pronto_pago_dia'] < 1 || $c['pronto_pago_dia'] > 28) {
                throw new \InvalidArgumentException('El día límite de pronto pago debe estar entre 1 y 28.|#cfg_pronto_pago_dia');
            }
        } elseif ($c['pronto_pago_dia'] < 1 || $c['pronto_pago_dia'] > 28) {
            $c['pronto_pago_dia'] = 5;
        }

        $c['anticipado_activo']    = self::bool($d['anticipado_activo'] ?? false);
        $c['anticipado_pct']       = round(self::num($d['anticipado_pct'] ?? 0), 2);
        $c['anticipado_meses_min'] = max(2, (int) ($d['anticipado_meses_min'] ?? 12));
        if ($c['anticipado_activo'] && ($c['anticipado_pct'] <= 0 || $c['anticipado_pct'] > 100)) {
            throw new \InvalidArgumentException('El % por pago anticipado debe estar entre 0,01 y 100.|#cfg_anticipado_pct');
        }

        $c['restriccion_auto']  = self::bool($d['restriccion_auto'] ?? false);
        $c['restriccion_meses'] = (int) ($d['restriccion_meses'] ?? 3);
        if ($c['restriccion_meses'] < 1 || $c['restriccion_meses'] > 24) {
            throw new \InvalidArgumentException('Los meses de mora para la restricción deben estar entre 1 y 24.|#cfg_restriccion_meses');
        }
        $c['liquidacion_min_vencidas'] = max(1, min(24, (int) ($d['liquidacion_min_vencidas'] ?? 1)));
        $c['observaciones'] = self::texto($d['observaciones'] ?? null, 5000);

        return $c;
    }

    // ── Inmuebles ─────────────────────────────────────────────────────────────

    /** Normaliza y valida un inmueble. $cfg = configuración del condominio (para reglas dependientes). */
    public function validarUnidad(array $d, array $cfg): array
    {
        $u = [];
        $u['codigo'] = self::texto($d['codigo'] ?? null, 30);
        if ($u['codigo'] === null) {
            throw new \InvalidArgumentException('El código del inmueble es obligatorio (p. ej. DPTO-302).|#uni_codigo');
        }
        $u['nombre'] = self::texto($d['nombre'] ?? null, 120) ?? $u['codigo'];
        $u['tipo'] = (string) ($d['tipo'] ?? 'departamento');
        if (!in_array($u['tipo'], self::TIPOS_UNIDAD, true)) {
            throw new \InvalidArgumentException('El tipo de inmueble no es válido.|#uni_tipo');
        }
        $u['torre_bloque'] = self::texto($d['torre_bloque'] ?? null, 60);
        $u['piso']         = self::texto($d['piso'] ?? null, 20);

        $u['area_m2'] = round(self::num($d['area_m2'] ?? 0), 2);
        if ($u['area_m2'] < 0) {
            throw new \InvalidArgumentException('El área no puede ser negativa.|#uni_area_m2');
        }
        $u['alicuota_pct'] = round(self::num($d['alicuota_pct'] ?? 0), 6);
        if ($u['alicuota_pct'] < 0 || $u['alicuota_pct'] > 100) {
            throw new \InvalidArgumentException('La alícuota debe estar entre 0 y 100 %.|#uni_alicuota_pct');
        }

        $u['id_propietario'] = (int) ($d['id_propietario'] ?? 0);
        if ($u['id_propietario'] <= 0) {
            throw new \InvalidArgumentException('Elija el propietario (cliente). El propietario siempre se guarda, aunque pague el arrendatario.|#uni_propietario_txt');
        }
        $u['id_arrendatario'] = (int) ($d['id_arrendatario'] ?? 0) ?: null;
        if ($u['id_arrendatario'] === $u['id_propietario']) {
            $u['id_arrendatario'] = null;
        }
        $u['pagador'] = ($d['pagador'] ?? 'propietario') === 'arrendatario' ? 'arrendatario' : 'propietario';
        if ($u['pagador'] === 'arrendatario' && $u['id_arrendatario'] === null) {
            throw new \InvalidArgumentException('Para que pague el arrendatario primero hay que indicarlo.|#uni_arrendatario_txt');
        }

        // El comprobante, la serie y el día de cobro viven en la suscripción del inmueble (Suscripciones).
        $u['metodo_alicuota'] = self::texto($d['metodo_alicuota'] ?? null, 12);
        if ($u['metodo_alicuota'] !== null && !in_array($u['metodo_alicuota'], self::METODOS, true)) {
            throw new \InvalidArgumentException('El método de alícuota del inmueble no es válido.|#uni_metodo_alicuota');
        }
        $metodoEfectivo = $u['metodo_alicuota'] ?? (string) ($cfg['metodo_alicuota'] ?? 'porcentaje');
        $montoManual = $d['monto_manual'] ?? null;
        $u['monto_manual'] = ($montoManual === null || trim((string) $montoManual) === '') ? null : round(self::num($montoManual), 2);
        if ($metodoEfectivo === 'manual' && ($u['monto_manual'] === null || $u['monto_manual'] < 0)) {
            throw new \InvalidArgumentException('Con método manual debe indicar el monto mensual acordado.|#uni_monto_manual');
        }
        if ($metodoEfectivo === 'porcentaje' && $u['alicuota_pct'] <= 0) {
            throw new \InvalidArgumentException('Con método por % el inmueble necesita su % de alícuota.|#uni_alicuota_pct');
        }
        if ($metodoEfectivo === 'm2' && $u['area_m2'] <= 0) {
            throw new \InvalidArgumentException('Con método por m² el inmueble necesita su área.|#uni_area_m2');
        }

        $fp = $d['fondo_reserva_valor_propio'] ?? null;
        $u['fondo_reserva_valor_propio'] = ($fp === null || trim((string) $fp) === '') ? null : round(self::num($fp), 2);
        if ($u['fondo_reserva_valor_propio'] !== null && $u['fondo_reserva_valor_propio'] < 0) {
            throw new \InvalidArgumentException('El fondo de reserva propio no puede ser negativo.|#uni_fondo_reserva_valor_propio');
        }

        $u['estado'] = ($d['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo';
        $u['observaciones'] = self::texto($d['observaciones'] ?? null, 5000);
        return $u;
    }

    // ── Multas ───────────────────────────────────────────────────────────────

    public function validarMulta(array $d): array
    {
        $m = [];
        $m['nombre'] = self::texto($d['nombre'] ?? null, 150);
        if ($m['nombre'] === null) {
            throw new \InvalidArgumentException('El nombre de la multa es obligatorio.|#multa_nombre');
        }
        $m['descripcion'] = self::texto($d['descripcion'] ?? null, 5000);
        $m['valor'] = round(self::num($d['valor'] ?? 0), 2);
        if ($m['valor'] < 0) {
            throw new \InvalidArgumentException('El valor de la multa no puede ser negativo.|#multa_valor');
        }
        $m['id_producto'] = (int) ($d['id_producto'] ?? 0);
        if ($m['id_producto'] <= 0) {
            throw new \InvalidArgumentException('Elija el producto (servicio) con el que se factura la multa.|#multa_prod_txt');
        }
        $m['estado'] = ($d['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo';
        return $m;
    }

    /** Fecha YYYY-MM-DD válida o excepción. */
    public static function fecha(mixed $v, string $campo, string $sel): string
    {
        $f = trim((string) ($v ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) || !checkdate((int) substr($f, 5, 2), (int) substr($f, 8, 2), (int) substr($f, 0, 4))) {
            throw new \InvalidArgumentException("Indique la fecha de {$campo}.|{$sel}");
        }
        return $f;
    }
}
